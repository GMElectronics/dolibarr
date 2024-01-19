<?php
/* Copyright (C) 2011-2012 Juanjo Menent  <jmenent@2byte.es>
 * Copyright (C) 2012-2017 Ferran Marcet  <fmarcet@2byte.es>
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 2 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program. If not, see <http://www.gnu.org/licenses/>.
 */

/**
 *	\file       htdocs/catalog/catalog.php
 *	\ingroup    products
 *	\brief      Catalog page
 */

$res=@include("../main.inc.php");					// For root directory
if (! $res) $res=@include("../../main.inc.php");	// For "custom" directory
require_once DOL_DOCUMENT_ROOT.'/categories/class/categorie.class.php';

global $langs;

$langs->load("admin");
$langs->load('errors');
dol_include_once("/catalog/class/pdf_catalog.class.php");
dol_include_once("/catalog/lib/catalog.lib.php");
require_once(DOL_DOCUMENT_ROOT."/core/lib/date.lib.php");
require_once(DOL_DOCUMENT_ROOT."/core/lib/files.lib.php");
require_once DOL_DOCUMENT_ROOT.'/core/lib/treeview.lib.php';
require_once DOL_DOCUMENT_ROOT .'/core/class/html.formfile.class.php';

global $user, $conf, $db, $langs;

// Security check
if (! $user->rights->catalog->use) accessforbidden();

$dir = $conf->catalog->dir_output;

$action=GETPOST('action','alpha');
$cancel = GETPOST('cancel');
$catlevel = GETPOST('catlevel','int');
$footer = $conf->global->CAT_FOOTER;
$catid = GETPOST('catid','int');
$section=GETPOST('section','int');
$category_catalog=GETPOST('category_to_catalog','array');
$lang_id = GETPOST('lang_id');
$search_type = GETPOST('search_type','int');

if (empty($search_type)) $search_type='0';

$pdf=null;
$position=null;

if($_FILES['pdf']['error']!=4 && $_FILES['pdf'] !== null) {
	if (strpos($_FILES['pdf']['type'], 'pdf') !== false) {
        $pdf = $_FILES['pdf'];
        $position = GETPOST('position_pdf');
    } else {
        $action = '';
        setEventMessages($langs->trans('ErrorFileMustHaveFormat', 'pdf'), null, 'errors');
        dol_syslog('Format incorrect');
    }
}

$socid=0;
if ($user->socid > 0) {
    $action = '';
    $socid = $user->socid;
    $dir = $conf->product->dir_output . '/catalogs/private/' . $user->id;
}

$socid = GETPOST('thirdparties', 'array');

$year = GETPOST('year','int');
if (! $year) { $year=date("Y"); }


// Increase limit of time. Works only if we are not in safe mode
$ExecTimeLimit=600;
if (!empty($ExecTimeLimit))
{
    $err=error_reporting();
    error_reporting(0);     // Disable all errors
    //error_reporting(E_ALL);
    @set_time_limit($ExecTimeLimit);   // Need more than 240 on Windows 7/64
    error_reporting($err);
}
$MemoryLimit=0;
if (!empty($MemoryLimit))
{
    @ini_set('memory_limit', $MemoryLimit);
}

$MAXNB=1000;


/*
 * Actions
 */
$cat = new pdf_catalog($db);

$hookmanager->initHooks(array('catalogcard'));
$parameters = null;
$reshook=$hookmanager->executeHooks('doActions',$parameters,$thirdparty,$action);    // Note that $action and $object may have been modified by some hooks

if ($action == 'builddoc') {


    $outputlangs = $langs;

    if (!empty($lang_id)) {
        $outputlangs = new Translate("", $conf);
        $outputlangs->setDefaultLang($lang_id);
    }

   $cat->write_file($dir, $_POST["remonth"], $_POST["reyear"], $outputlangs, $search_type, $catlevel, $footer, $category_catalog, $pdf['tmp_name'], $position, $_POST["reday"], $_POST["search_ref"], GETPOST('search_maxnb','int'), $socid, GETPOST('divise','int'));

   $year = $_POST["reyear"];
}

if ($action == 'addfile') {
    require_once(DOL_DOCUMENT_ROOT . "/core/lib/files.lib.php");
    include_once(DOL_DOCUMENT_ROOT . '/core/class/html.formmail.class.php');

    $formmail = new FormMail($db);
    $relativepath = $conf->catalog->dir_output;
    $relativepath .= '/' . $year;
    $filemail = GETPOST('urlfile');    // Do not use urldecode here ($_GET and $_REQUEST are already decoded by PHP).

    //$formmail->clear_attached_files();
    $formmail->add_attached_files($relativepath . "/" . $filemail, $filemail, dol_mimetype($filemail, '', 1));

    $_GET["action"] = 'presend';
    $_POST["action"] = 'presend';
}

if(GETPOST('removedfile')) {

    require_once(DOL_DOCUMENT_ROOT . "/core/lib/files.lib.php");
    include_once(DOL_DOCUMENT_ROOT . '/core/class/html.formmail.class.php');

    $formmail = new FormMail($db);
    $formmail->remove_attached_files(GETPOST('removedfile') - 1);

    $_GET["action"] = 'presend';
    $_POST["action"] = 'presend';
}

if (($action === 'send') && ! GETPOST('addfile') && ! GETPOST('removedfile') && ! GETPOST('cancel')) {
    $langs->load('mails');
    $actiontypecode = '';
    $subject = '';
    $actionmsg = '';
    $actionmsg2 = '';

    if ($socid > 0) {
        $societe = new Societe($db);
        $societe->fetch($socid);
    }

    $sendto = null;

    if (GETPOST('sendto')) {
        // Le destinataire a ete fourni via le champ libre
        $sendto = GETPOST('sendto');
        $sendtoid = 0;
    }
    if (GETPOST('receiver')) {
        // Le destinataire a ete fourni via la liste deroulante
        if (GETPOST('receiver') == 'thirdparty')    // Id du tiers
        {
            $sendto = $societe->email;
            $sendtoid = 0;
        } else    // Id du contact
        {
            if(is_array(GETPOST('receiver')) && $sendto){
				/*$sendto = '';
				$sendtoid = '';*/
                foreach (GETPOST('receiver') as $receiver){
					$sendto .= ','.$societe->contact_get_property($receiver, 'email');
					$sendtoid .= ','.$receiver;
                }
            }
            else {
				$sendto = $societe->contact_get_property(GETPOST('receiver'), 'email');
				$sendtoid = GETPOST('receiver');
			}
        }
    }

    if (dol_strlen($sendto) || is_array($sendtoid)) {
        $langs->load("commercial");

        $from = GETPOST('fromname') . ' <' . GETPOST('frommail') . '>';
        $replyto = GETPOST('replytoname') . ' <' . GETPOST('replytomail') . '>';
        $message = GETPOST('message', 'alpha');
        $sendtocc = GETPOST('sendtocc');
        $deliveryreceipt = GETPOST('deliveryreceipt');

        if (GETPOST('action', 'alpha') == 'send') {
            if (dol_strlen(GETPOST('subject', 'alpha'))) $subject = GETPOST('subject', 'alpha');
            else $subject = $langs->transnoentities('Bill') . ' ' . $object->ref;
            $actiontypecode = 'AC_EMAIL';
            $actionmsg = $langs->transnoentities('MailSentBy') . ' ' . $from . ' ' . $langs->transnoentities('To') . ' ' . $sendto . ".\n";
            if ($message) {
                $actionmsg .= $langs->transnoentities('MailTopic') . ": " . $subject . "\n";
                $actionmsg .= $langs->transnoentities('TextUsedInTheMessageBody') . ":\n";
                $actionmsg .= $message;
            }
        }

        // Create form object
        include_once(DOL_DOCUMENT_ROOT . '/core/class/html.formmail.class.php');
        $formmail = new FormMail($db);

        $attachedfiles = $formmail->get_attached_files();
        $filepath = $attachedfiles['paths'];
        $filename = $attachedfiles['names'];
        $mimetype = $attachedfiles['mimes'];

        // Send mail
        require_once(DOL_DOCUMENT_ROOT . '/core/class/CMailFile.class.php');
        $mailfile = new CMailFile($subject, $sendto, $from, $message, $filepath, $mimetype, $filename, $sendtocc, '', $deliveryreceipt, -1);
        if ($mailfile->error) {
            setEventMessage($mailfile->error, "errors");
        } else {
            $result = $mailfile->sendfile();
            if ($result) {
                setEventMessage($langs->trans('MailSuccessfulySent', $from, $sendto));        // Must not contain "

                // Redirect here
                // This avoid sending mail twice if going out and then back to page

                $formmail->clear_attached_files();

                header('Location: ' . $_SERVER["PHP_SELF"] . '?id=' . $socid . '&mesg=1');
                exit;

            } else {
                $langs->load("other");
                if ($mailfile->error) {
                    setEventMessage($langs->trans('ErrorFailedToSendMail', $from, $sendto) . '<br>' . $mailfile->error, "errors");
                } else {
                    setEventMessage('No mail sent. Feature is disabled by option MAIN_DISABLE_ALL_MAILS', "warnings");
                }

            }
        }
    } else {
        $langs->load("other");
        setEventMessage($langs->trans('ErrorMailRecipientIsEmpty'), "errors");
        dol_syslog('Recipient email is empty');
    }

    $_GET['action'] = 'presend';
}

if ($action=='send' && $cancel) {
    include_once(DOL_DOCUMENT_ROOT . '/core/class/html.formmail.class.php');
    $formmail = new FormMail($db);
    $formmail->clear_attached_files();

    Header('Location: ' . $_SERVER["PHP_SELF"] . '?id=' . $socid);
    exit;
}

if ($action=='deletefile') {
    $file = $dir . "/" . $year . "/" . GETPOST('urlfile', 'alpha');    // Do not use urldecode here ($_GET and $_REQUEST are already decoded by PHP).

    $result = dol_delete_file($file);

    if ($result)
        setEventMessage($langs->trans("FileWasRemoved", GETPOST('urlfile', 'alpha')));
    else
        setEventMessage($langs->trans("ErrorFileToDelete", GETPOST('urlfile', 'alpha')), "errors");
}



/*
 * View
 */

$arrayofjs=array('/includes/jquery/plugins/jquerytreeview/jquery.treeview.js', '/includes/jquery/plugins/jquerytreeview/lib/jquery.cookie.js');
$arrayofcss=array('/includes/jquery/plugins/jquerytreeview/jquery.treeview.css');

$helpurl='EN:Module_Catalog|FR:Module_Catalog_FR|ES:M&oacute;dulo_Catalog';
llxHeader('','',$helpurl,'',0,0,$arrayofjs,$arrayofcss);

dol_htmloutput_events();
?>
<script type="text/javascript">
	jQuery(document).ready(function() {
	jQuery("#checkall").click(function() {
		console.log("Check all with class .checkforcatalog");
		jQuery(".checkforcatalog").attr('checked', true);
	});
	jQuery("#checknone").click(function() {
		console.log("Uncheck all with class .checkforcatalog");
		jQuery(".checkforcatalog").attr('checked' , false);
	});
});
</script>
<?php

$titre=($year?$langs->trans("CatalogsForYear",$year):$langs->trans("Catalogs"));
print load_fiche_titre($titre);

// Formulaire de generation
print '<form method="post" enctype="multipart/form-data" action="catalog.php?year='.$year.'">';
print '<input type="hidden" name="token" value="'.$_SESSION['newtoken'].'">';
print '<input type="hidden" name="action" value="builddoc">';

$cmonth = GETPOST("remonth")?GETPOST("remonth"):date("n", time());
$syear = GETPOST("reyear")?GETPOST("reyear"):date("Y", time());

print '<fieldset>';
print '<legend>'.$langs->trans("RenderingOptions").'</legend>';

print '<div>';

print $langs->trans("DateForPrices").': ';

$time=dol_get_first_day($syear, $cmonth);

// Use date selector
print $form->select_date($time, 're');


if ($conf->global->MAIN_MULTILANGS) {
    include_once DOL_DOCUMENT_ROOT . '/core/class/html.formadmin.class.php';
    $formadmin = new FormAdmin($db);
    $defaultlang = $langs->getDefaultLang();
    print ' &nbsp; '.$langs->trans("Language").': '.$formadmin->select_language($defaultlang);
}
print '</div>';

print '<div><label for="pdf">'.$langs->trans('FileAddFinal').':</label>';
print '<input type="file" style="margin-left:10px" accept="application/pdf" name="pdf" id="pdf">';
print '<label for="position_pdf" style="margin-left:10px">'.$langs->trans('Position').'</label>';
print '<select name="position_pdf" style="margin-left:10px" id="position_pdf"><option value="0">'.$langs->trans('Before').'</option><option value="1">'.$langs->trans('After').'</option></select></div>';

if (! empty($conf->global->PRODUIT_MULTIPRICES)) {
    print '<br>';
    print $langs->trans("PriceLevel") . ' ';
    print '<input type="text" class="flat" name="catlevel" value="' . (empty($catlevel) ? 1 : $catlevel) . '" size="3">';
}

if($conf->multicurrency->enabled){
	$sql = 'SELECT rowid, name FROM ' . MAIN_DB_PREFIX . 'multicurrency WHERE entity = ' . $conf->entity;
	$resql = $db->query($sql);
	if ($resql) {
		$num = $db->num_rows($resql);
		$i = 0;
		$arr = array();

		while ($i < $num) {
			$obj = $db->fetch_object($resql);
			$arr[$i]['id'] = $obj->rowid;
			$arr[$i]['name'] = $obj->name;
			$i++;
		}

		$sql0 = 'SELECT rowid FROM ' . MAIN_DB_PREFIX . 'multicurrency WHERE entity = ' . $conf->entity . ' AND code = "' . $conf->currency . '"';
		$resql0 = $db->query($sql0);
		if ($resql0) {
			$num0 = $db->num_rows($resql0);

			if($num0==1) {
				$obj0 = $db->fetch_object($resql0);

				print '<br>';
				print $langs->trans("Divise") . ' ';
				print '<select name="divise">';
				foreach ($arr as $divise){
					if($divise['id']==$obj0->rowid){
						print '<option value="' . $divise['id'] . '" selected>' . $divise['name'] . '</option>';
					}
					else {
						print '<option value="' . $divise['id'] . '">' . $divise['name'] . '</option>';
					}
				}
				print '</select>';
			}
		}
	}
}
print '</fieldset>';

print '<br>';

print '<fieldset>';
print '<legend>'.$langs->trans("FilterOptions").'</legend>';

print $langs->trans("MaxNbOfRecord").': <input type="text" name="search_maxnb" value="'.dol_escape_htmltag(GETPOST('search_maxnb','int')?GETPOST('search_maxnb','int'):$MAXNB).'"><br>';
$arrayoftypes=array();
if ($conf->product->enabled) $arrayoftypes['0']=$langs->trans("Product");
if ($conf->service->enabled) $arrayoftypes['1']=$langs->trans("Service");
print $langs->trans("ByProductType").': '.$form->selectarray('search_type', $arrayoftypes, $search_type).'<br>';
print $langs->trans("ByProductRef").': <input type="text" name="search_ref" value="'.dol_escape_htmltag(GETPOST('search_ref','alpha')).'"><br>';

print $langs->trans("BySupplier").': ';

$prov_arbo = $cat->select_suppliers();
$arrayselected=array();
print $form->multiselectarray('thirdparties', $prov_arbo, $arrayselected, '', 0, '', 0, '100%');

print '<br/><br/>';
print $langs->trans("ByCategory").': ';
print '<div id="catalog_list_cat" class="fichecenter">';

// Charge tableau des categories
$categstatic = new Categorie($db);
$cate_arbo = $categstatic->get_full_arbo(0);

// Define fulltree array
$fulltree=$cate_arbo;
$nocat = array();
$nocat['rowid'] = -1;
$nocat['id'] = -1;
$nocat['fk_parent'] = 0;
$nocat['label'] = $langs->transnoentities('NoCategorie');
$nocat['description'] = $langs->transnoentities('NoCategorieDescr');
$nocat['fullpath'] = _-1;
$nocat['fulllabel'] = $langs->transnoentities('NoCategorie');
$nocat['level'] = 1;
$numcats=count($fulltree);
$fulltree[$numcats] = $nocat;

// Define data (format for treeview)
$data=array();
$data[] = array('rowid'=>0,'fk_menu'=>-1,'title'=>"racine",'mainmenu'=>'','leftmenu'=>'','fk_mainmenu'=>'','fk_leftmenu'=>'');
foreach($fulltree as $key => $val) {
    $categstatic->id = $val['id'];
    $categstatic->ref = $val['label'];
    $categstatic->color = $val['color'];
    $categstatic->type = 0;
    //$li=$categstatic->getNomUrl(1,'',60);
    $li = '<label for="catalog_' . $categstatic->id . '">' . img_object($categstatic->label, 'category', 'class="classfortooltip"') . ' ' . dol_trunc(($categstatic->ref ? $categstatic->ref : $categstatic->label), 0) . '</label>';
    $desc = dol_htmlcleanlastbr($val['description']);

    $data[] = array(
        'rowid' => $val['rowid'],
        'fk_menu' => $val['fk_parent'],
        'entry' => '<table class="nobordernopadding centpercent"><tr><td>' . (version_compare(DOL_VERSION, 3.9) >= 0 ? '<span class="noborderoncategories" ' . ($categstatic->color ? ' style="background: #' . $categstatic->color . ';"' : ' style="background: #aaa"') . '>' . $li . '</span>' : $li) .
            '</td><td width="50%">' .
            ' ' . $val['description'] . '</td>' .
            '<td align="right" width="20px;"><input class="flat checkforcatalog" id="catalog_' . $categstatic->id . '" type="checkbox" name="category_to_catalog[]" value="' . $categstatic->id . '"></td>' .
            '</tr></table>'
    );
}


print '<table class="liste" width="100%">';
print '<tr class="liste_titre"><td>'.$langs->trans("Categories").'</td><td></td><td align="right">';
if (! empty($conf->use_javascript_ajax)) {
    print '<div id="iddivjstreecontrol" ><a href="#" id="undoexpand">' . img_picto('', 'object_category') . ' ' . $langs->trans("UndoExpandAll") . '</a> | <a href="#" id="expand">' . img_picto('', 'object_category-expanded') . ' ' . $langs->trans("ExpandAll") . '</a> &nbsp; </div>';
    print '<div style="float:right"><a href="#" id="checkall">' . $langs->trans("CheckAll") . '</a> / <a href="#" id="checknone">' . $langs->trans("CheckNone") . '</a></div>';
}
print '</td></tr>';

$nbofentries=(count($data) - 1);

if ($nbofentries > 0) {
    print '<tr><td colspan="3">';
    tree_recur($data, $data[0], 0);
    print '</td></tr>';
}
else {
    print '<tr>';
    print '<td colspan="3"><table class="nobordernopadding"><tr class="nobordernopadding"><td>' . img_picto_common('', 'treemenu/branchbottom.gif') . '</td>';
    print '<td valign="middle">';
    print $langs->trans("NoCategoryYet");
    print '</td>';
    print '<td>&nbsp;</td>';
    print '</table></td>';
    print '</tr>';
}

print "</table>";

print '</div>';
print '</fieldset>';

print '<br><center><input type="submit" class="button" value="'.$langs->trans("BuildDoc").'"></center>';


print '<br>';
print '<br>';
print '<br>';


// Show link on other years
$linkforyear=array();
$found=0;
if (is_dir($dir)) {
    $handle = opendir($dir);
    if (is_resource($handle)) {
        while (($file = readdir($handle)) !== false) {
            if (is_dir($dir . '/' . $file) && !preg_match('/^\./', $file) && is_numeric($file)) {
                $found = 1;
                $linkforyear[] = $file;
            }
        }
    }
}
asort($linkforyear);
foreach($linkforyear as $cursoryear) {
    print '<a href="catalog.php?year=' . $cursoryear . '">' . $cursoryear . '</a> &nbsp;';
}
print '<br>';



$sortfield='date';
$sortorder='asc';

if ($year) {
	$formfile = new FormFile($db);
    if (is_dir($dir . '/' . $year)) {
        $listoffiles = dol_dir_list($dir . '/' . $year, 'files', 0, '', array(), 'date', SORT_DESC);

        if ($found) print '<br>';

        print '<table width="100%" class="noborder">';
        print '<tr class="liste_titre">';
        print '<td>' . $langs->trans("Catalog") . '</td>';
        print '<td align="right">' . $langs->trans("Size") . '</td>';
        print_liste_field_titre($langs->trans("Date"),$_SERVER["PHP_SELF"],'date','',$param,'align="center"',$sortfield,$sortorder);
        print '<td align="right"></td>';
        print '</tr>';
        $var = true;
        $url = $_SERVER["PHP_SELF"];

        foreach($listoffiles as $key => $val)
        {
            $file = $val['name'];
            if (preg_match('/^catalog/i', $file)) {
                $var = !$var;
                $tfile = $dir . '/' . $year . '/' . $file;
                $relativepath = $year . '/' . $file;
				$filedir=$conf->catalog->dir_output . '/' . dol_sanitizeFileName($file);
                print '<tr ' . $bc[$var ? 1 : 0] . '><td><a href="' . DOL_URL_ROOT . '/document.php?modulepart=catalog&amp;file=' . urlencode($relativepath) . '">' . img_pdf() . ' ' . $file . '</a>&nbsp;';
                print $formfile->showPreview($filedir,'catalog',$year.'/'.$file,0,'entity=1').'</td>';
                print '<td align="right">' . dol_print_size(dol_filesize($tfile)) . '</td>';
                print '<td align="center">' . dol_print_date(dol_filemtime($tfile), "dayhour") . '</td>';
                print '<td align="right">';
                if (empty($conf->global->CAT_DISABLE_SENDBYEMAIL_LINK))
                {
                    print '<a href="' . $url . '?action=addfile&year=' . $year . '&urlfile=' . urlencode($file) . '"><img src="' . DOL_URL_ROOT . '/theme/' . $conf->theme . '/img/stcomm0.png" border="0" alt="' . dol_escape_htmltag($langs->trans("SendbyMail")) . '" title="' . dol_escape_htmltag($langs->trans("SendbyMail")) . '"></a>';
                    print ' &nbsp; ';
                }
                print '<a href="' . $url . '?action=deletefile&year=' . $year . '&urlfile=' . urlencode($file) . '"><img src="' . DOL_URL_ROOT . '/theme/' . $conf->theme . '/img/delete.png" border="0" alt="' . dol_escape_htmltag($langs->trans("DeleteFile")) . '" title="' . dol_escape_htmltag($langs->trans("DeleteFile")) . '"></a>';
                print '</td></tr>';
            }
        }
        print '</table>';
    }
}
//Mail form
include_once(DOL_DOCUMENT_ROOT.'/core/class/html.formmail.class.php');
$formmail = new FormMail($db);
$arrayform =$formmail->get_attached_files();
$af=count($arrayform["names"]);
if($af>0 && $action=='addfile') {
    $action = 'send';
    $modelmail = 'body';

    print '<br>';

    print load_fiche_titre($langs->trans($titre), '', '');

    $formmail->fromtype = 'user';
    $formmail->fromid = $user->id;
    $formmail->fromname = $user->getFullName($langs);
    $formmail->frommail = $user->email;
    $formmail->withfrom = 1;
    $formmail->withto = empty($_POST["sendto"]) ? 1 : GETPOST('sendto');
    $formmail->withtosocid = $socid;
    $formmail->withtocc = 1;
    $formmail->withtoccsocid = 0;
    $formmail->withtoccc = $conf->global->MAIN_EMAIL_USECCC;
    $formmail->withtocccsocid = 0;
    $formmail->withtopic = $langs->trans("SendingCatalogue");
    $formmail->withfile = 1;
    $formmail->withbody = $langs->trans("WeSendCatalogueOfInterest") . "<br/>\n\n__SIGNATURE__";
    $formmail->withdeliveryreceipt = 1;
    $formmail->withcancel = 1;

    $formmail->substit['__SIGNATURE__'] = $user->signature;

    $formmail->param['action'] = $action;
    $formmail->param['models'] = $modelmail;
    $formmail->param['returnurl'] = $_SERVER["PHP_SELF"] . '?id=' . $socid;
	if($formmail->withto == 1){
        $formmail = getContacts($formmail);
	}
    $formmail->show_form();

    print '<br>';
}
llxFooter();
$db->close();
