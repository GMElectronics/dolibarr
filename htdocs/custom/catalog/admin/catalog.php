<?php
/* Copyright (C) 2003-2004 Rodolphe Quiedeville         <rodolphe@quiedeville.org>
 * Copyright (C) 2004-2011 Laurent Destailleur          <eldy@users.sourceforge.net>
 * Copyright (C) 2005      Eric Seigne                  <eric.seigne@ryxeo.com>
 * Copyright (C) 2005-2009 Regis Houssin                <regis@dolibarr.fr>
 * Copyright (C) 2008 	   Raphael Bertrand (Resultic)  <raphael.bertrand@resultic.fr>
 * Copyright (C) 2012-2017 Ferran Marcet  				<fmarcet@2byte.es>
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
 *      \file       htdocs/admin/facture.php
 *		\ingroup    facture
 *		\brief      Page to setup invoice module
 */

$res = @include("../../main.inc.php");                                // For root directory
if (!$res) $res = @include("../../../main.inc.php");                // For "custom" directory

require_once(DOL_DOCUMENT_ROOT . "/core/lib/admin.lib.php");
dol_include_once("/catalog/lib/catalog.lib.php");

global $langs, $user, $db, $conf;

$langs->load("admin");
$langs->load("catalog@catalog");
$langs->load("errors");

if (!$user->admin)
    accessforbidden();

/*
 * Actions
 */

if (GETPOST("save")) {
    $db->begin();
    $res = 0;

    $res += dolibarr_set_const($db, 'CAT_SHOW_PRICE', trim(GETPOST("catPrice")), 'chaine', 0, '', $conf->entity);
    $res += dolibarr_set_const($db, 'CAT_SHOW_PRICE_VAT', trim(GETPOST("catPriceVat")), 'chaine', 0, '', $conf->entity);
    $res += dolibarr_set_const($db, 'CAT_SHOW_NO_SELL', trim(GETPOST("catNoSell")), 'chaine', 0, '', $conf->entity);
    $res += dolibarr_set_const($db, 'CAT_SHOW_NO_STOCK', trim(GETPOST("catNoStock")), 'chaine', 0, '', $conf->entity);
    $res += dolibarr_set_const($db, 'CAT_ORDER_BY_REF', trim(GETPOST("catOrder")), 'chaine', 0, '', $conf->entity);
    $res += dolibarr_set_const($db, 'CAT_GROUP_BY_CATEGORY', trim(GETPOST("catByCat")), 'chaine', 0, '', $conf->entity);
	$res += dolibarr_set_const($db, 'CAT_GROUP_BY_SUPPLIER', trim(GETPOST("catBySup")), 'chaine', 0, '', $conf->entity);
    $res += dolibarr_set_const($db, 'CAT_HIGH_QUALITY_IMAGES', trim(GETPOST("qualityImages")), 'chaine', 0, '', $conf->entity);
    $res += dolibarr_set_const($db, 'CAT_SHOW_WEIGHT', trim(GETPOST("catProdWeight")), 'chaine', 0, '', $conf->entity);
    $res += dolibarr_set_const($db, 'CAT_SHOW_LENGTH', trim(GETPOST("catProdLenght")), 'chaine', 0, '', $conf->entity);
	$res += dolibarr_set_const($db, 'CAT_SHOW_WIDTH', trim(GETPOST("catProdWidth")), 'chaine', 0, '', $conf->entity);
	$res += dolibarr_set_const($db, 'CAT_SHOW_HEIGHT', trim(GETPOST("catProdHeight")), 'chaine', 0, '', $conf->entity);
    $res += dolibarr_set_const($db, 'CAT_SHOW_SURFACE', trim(GETPOST("catProdSurface")), 'chaine', 0, '', $conf->entity);
    $res += dolibarr_set_const($db, 'CAT_SHOW_VOLUME', trim(GETPOST("catProdVolume")), 'chaine', 0, '', $conf->entity);
    $res += dolibarr_set_const($db, 'CAT_SHOW_PAYS_SOURCE', trim(GETPOST("catProdPaysSource")), 'chaine', 0, '', $conf->entity);
    $res += dolibarr_set_const($db, 'CAT_SHOW_DURATION', trim(GETPOST("catServDuration")), 'chaine', 0, '', $conf->entity);
    $res += dolibarr_set_const($db, 'CAT_SHOW_STOCK', trim(GETPOST("catProdStock")), 'chaine', 0, '', $conf->entity);
    $res += dolibarr_set_const($db, 'CAT_SHOW_BARCODE', trim(GETPOST("catProdBarcode")), 'chaine', 0, '', $conf->entity);
    $res += dolibarr_set_const($db, 'CAT_FOOTER', trim(GETPOST("catFooter")), 'chaine', 0, '', $conf->entity);
    $res += dolibarr_set_const($db, 'CAT_TITLE', trim(GETPOST("catTitle")), 'chaine', 0, '', $conf->entity);
    $res += dolibarr_set_const($db, 'CAT_TRUNCATE_NAME', trim(GETPOST("truncateName")), 'chaine', 0, '', $conf->entity);
	$res += dolibarr_set_const($db, 'CATALOG_RENDERING_OPTION_2', trim(GETPOST("catRender")), 'chaine', 0, '', $conf->entity);

    if ($res >= 15) {
        $db->commit();
        setEventMessage($langs->trans("CatSetupSaved"));
    } else {
        $db->rollback();
        setEventMessage($langs->trans("Error"), "errors");
        header("Location: " . $_SERVER["PHP_SELF"]);
        exit;
    }
}

/*
 * View
 */
$helpurl = 'EN:Module_Catalog|FR:Module_Catalog_FR|ES:M&oacute;dulo_Catalog';
llxHeader("", $langs->trans("CatalogSetup"), $helpurl);
$html = new Form($db);

// read const
$catprice = dolibarr_get_const($db, "CAT_SHOW_PRICE", $conf->entity);
$catpricevat = dolibarr_get_const($db, "CAT_SHOW_PRICE_VAT", $conf->entity);
$catnosell = dolibarr_get_const($db, "CAT_SHOW_NO_SELL", $conf->entity);
$catnostock = dolibarr_get_const($db, "CAT_SHOW_NO_STOCK", $conf->entity);
$catorder = dolibarr_get_const($db, "CAT_ORDER_BY_REF", $conf->entity);
$catbycat = dolibarr_get_const($db, "CAT_GROUP_BY_CATEGORY", $conf->entity);
$catbysup = dolibarr_get_const($db, "CAT_GROUP_BY_SUPPLIER", $conf->entity);
$qualityimages = dolibarr_get_const($db, "CAT_HIGH_QUALITY_IMAGES", $conf->entity);
$catprodweight = dolibarr_get_const($db, "CAT_SHOW_WEIGHT", $conf->entity);
$catprodlenght = dolibarr_get_const($db, "CAT_SHOW_LENGTH", $conf->entity);
$catprodwidth = dolibarr_get_const($db, "CAT_SHOW_WIDTH", $conf->entity);
$catprodheight = dolibarr_get_const($db, "CAT_SHOW_HEIGHT", $conf->entity);
$catprodsurface = dolibarr_get_const($db, "CAT_SHOW_SURFACE", $conf->entity);
$catprodvolume = dolibarr_get_const($db, "CAT_SHOW_VOLUME", $conf->entity);
$catprodpayssource = dolibarr_get_const($db, "CAT_SHOW_PAYS_SOURCE", $conf->entity);
$catservduration = dolibarr_get_const($db, "CAT_SHOW_DURATION", $conf->entity);
$catstock = dolibarr_get_const($db, "CAT_SHOW_STOCK", $conf->entity);
$catbarcode = dolibarr_get_const($db, "CAT_SHOW_BARCODE", $conf->entity);
$catrender = dolibarr_get_const($db, "CATALOG_RENDERING_OPTION_2", $conf->entity);

//Page
$linkback = '<a href="' . DOL_URL_ROOT . '/admin/modules.php">' . $langs->trans("BackToModuleList") . '</a>';
print load_fiche_titre($langs->trans("CatalogSetup"), $linkback, 'setup');
print '<br>';
$head = catalogadmin_prepare_head();
dol_fiche_head($head, 'configuration', $langs->trans("Catalog"), 0, 'generic');

print '<form name="catalogconfig" action="' . $_SERVER["PHP_SELF"] . '" method="post">';
print '<input type="hidden" name="token" value="' . $_SESSION['newtoken'] . '">';

/*
 *  General Optiones
 */
print load_fiche_titre($langs->trans("GeneralOptions"));
print '<table class="noborder" width="100%">';
print '<tr class="liste_titre">';
print '<td>' . $langs->trans("Parameter") . '</td>';
print '<td align="center" width="60">' . $langs->trans("Value") . '</td>';
print "</tr>\n";
$var = true;

// Render option
$var = !$var;
print "<tr " . $bc[$var ? 1 : 0] . ">";
print "<td>" . $langs->trans("UseAlternativeRender") . "</td>";
print '<td>';
print $html->selectyesno("catRender", $catrender, 1);
print '</td>';
print "</tr>";

// Show price
$var = !$var;
print "<tr " . $bc[$var ? 1 : 0] . ">";
print "<td>" . $langs->trans("ShowPrice") . "</td>";
print '<td>';
print $html->selectyesno("catPrice", $catprice, 1);
print '</td>';
print "</tr>";

if ($catprice == 1) {
    // Show price VAT
    $var = !$var;
    print "<tr " . $bc[$var ? 1 : 0] . ">";
    print "<td>" . $langs->trans("ShowPriceVat") . "</td>";
    print '<td>';
    print $html->selectyesno("catPriceVat", $catpricevat, 1);
    print '</td>';
    print "</tr>";
}

// Show prod/serv not sell
$var = !$var;
print "<tr " . $bc[$var ? 1 : 0] . ">";
print "<td>" . $langs->trans("ShowNoSell") . "</td>";
print '<td>';
print $html->selectyesno("catNoSell", $catnosell, 1);
print '</td>';
print "</tr>";

// Show prod/serv not in stock
$var = !$var;
print "<tr " . $bc[$var ? 1 : 0] . ">";
print "<td>" . $langs->trans("ShowNoStock") . "</td>";
print '<td>';
print $html->selectyesno("catNoStock", $catnostock, 1);
print '</td>';
print "</tr>";

// Order by ref or label
$var = !$var;
print "<tr " . $bc[$var ? 1 : 0] . ">";
print "<td>" . $langs->trans("OrderByRef") . "</td>";
print '<td>';
print $html->selectyesno("catOrder", $catorder, 1);
print '</td>';
print "</tr>";

$var = !$var;
print "<tr " . $bc[$var ? 1 : 0] . ">";
print "<td>" . $langs->trans("GroupByCategory") . "</td>";
print '<td>';
print $html->selectyesno("catByCat", $catbycat, 1);
print '</td>';
print "</tr>";

$var = !$var;
print "<tr " . $bc[$var ? 1 : 0] . ">";
print "<td>" . $langs->trans("GroupBySupplier") . "</td>";
print '<td>';
print $html->selectyesno("catBySup", $catbysup, 1);
print '</td>';
print "</tr>";

$var = !$var;
print "<tr " . $bc[$var ? 1 : 0] . ">";
print "<td>" . $langs->trans("TruncateName") . "</td>";
print '<td align="left">';
$norms[0] = $langs->trans("Left");
$norms[1] = $langs->trans("Middle");
$norms[2] = $langs->trans("Right");
print Form::selectarray('truncateName', $norms, $conf->global->CAT_TRUNCATE_NAME);
print '</td>';
print "</tr>";

$var = !$var;
print "<tr " . $bc[$var ? 1 : 0] . ">";
print "<td>" . $langs->trans("HighQualityImages") . "</td>";
print '<td>';
print $html->selectyesno("qualityImages", $qualityimages, 1);
print '</td>';
print "</tr>";

$var = !$var;
print '<tr ' . $bc[$var ? 1 : 0] . '><td colspan=2>';
print $langs->trans("CatalogTitle") . ' (' . $langs->trans("AddCRIfTooLong") . ')<br>';
print '<textarea name="catTitle" class="flat" cols="120">' . $conf->global->CAT_TITLE . '</textarea>';
print '</td></tr>';

$var = !$var;
print '<tr ' . $bc[$var ? 1 : 0] . '><td colspan=2>';
print $langs->trans("FreeTextOnFooter") . ' (' . $langs->trans("AddCRIfTooLong") . ')<br>';
print '<textarea name="catFooter" class="flat" cols="120">' . $conf->global->CAT_FOOTER . '</textarea>';
print '</td></tr>';

print '</table>';


/*
 *  Product Options
 */

if ($conf->product->enabled) {
    print '<br>';
    print load_fiche_titre($langs->trans("ProductOptions"));

    print '<table class="noborder" width="100%">';
    print '<tr class="liste_titre">';
    print '<td>' . $langs->trans("Parameter") . '</td>';
    print '<td align="center" width="60">' . $langs->trans("Value") . '</td>';
    print "</tr>\n";
    $var = true;

    // Show  weight
    $var = !$var;
    print "<tr " . $bc[$var ? 1 : 0] . ">";
    print "<td>" . $langs->trans("ShowWeight") . "</td>";
    print '<td>';
    print $html->selectyesno("catProdWeight", $catprodweight, 1);
    print '</td>';
    print "</tr>";

    // Show  length
    $var = !$var;
    print "<tr " . $bc[$var ? 1 : 0] . ">";
    print "<td>" . $langs->trans("ShowLength") . "</td>";
    print '<td>';
    print $html->selectyesno("catProdLenght", $catprodlenght, 1);
    print '</td>';
    print "</tr>";

	// Show  width
	$var = !$var;
	print "<tr " . $bc[$var ? 1 : 0] . ">";
	print "<td>" . $langs->trans("ShowWidth") . "</td>";
	print '<td>';
	print $html->selectyesno("catProdWidth", $catprodwidth, 1);
	print '</td>';
	print "</tr>";

	// Show  height
	$var = !$var;
	print "<tr " . $bc[$var ? 1 : 0] . ">";
	print "<td>" . $langs->trans("ShowHeight") . "</td>";
	print '<td>';
	print $html->selectyesno("catProdHeight", $catprodheight, 1);
	print '</td>';
	print "</tr>";


    // Show  surface
    $var = !$var;
    print "<tr " . $bc[$var ? 1 : 0] . ">";
    print "<td>" . $langs->trans("ShowSurface") . "</td>";
    print '<td>';
    print $html->selectyesno("catProdSurface", $catprodsurface, 1);
    print '</td>';
    print "</tr>";

    // Show  volume
    $var = !$var;
    print "<tr " . $bc[$var ? 1 : 0] . ">";
    print "<td>" . $langs->trans("ShowVolume") . "</td>";
    print '<td>';
    print $html->selectyesno("catProdVolume", $catprodvolume, 1);
    print '</td>';
    print "</tr>";

    // Show  pays source
    $var = !$var;
    print "<tr " . $bc[$var ? 1 : 0] . ">";
    print "<td>" . $langs->trans("ShowPaysSource") . "</td>";
    print '<td>';
    print $html->selectyesno("catProdPaysSource", $catprodpayssource, 1);
    print '</td>';
    print "</tr>";

    // Show  stock
    $var = !$var;
    print "<tr " . $bc[$var ? 1 : 0] . ">";
    print "<td>" . $langs->trans("ShowStockQty") . "</td>";
    print '<td>';
    print $html->selectyesno("catProdStock", $catstock, 1);
    print '</td>';
    print "</tr>";

    // Show  barcode
    $var = !$var;
    print "<tr " . $bc[$var ? 1 : 0] . ">";
    print "<td>" . $langs->trans("ShowBarcode") . "</td>";
    print '<td>';
    print $html->selectyesno("catProdBarcode", $catbarcode, 1);
    print '</td>';
    print "</tr>";

    print '</table>';
}

/*
 *  Services Options
 */
if ($conf->service->enabled) {
    print '<br>';
    print load_fiche_titre($langs->trans("ServicesOptions"));

    print '<table class="noborder" width="100%">';
    print '<tr class="liste_titre">';
    print '<td>' . $langs->trans("Parameter") . '</td>';
    print '<td align="center" width="60">' . $langs->trans("Value") . '</td>';
    print "</tr>\n";
    $var = true;

    // Show  Duration
    $var = !$var;
    print "<tr " . $bc[$var ? 1 : 0] . ">";
    print "<td>" . $langs->trans("ShowDuration") . "</td>";
    print '<td>';
    print $html->selectyesno("catServDuration", $catservduration, 1);
    print '</td>';
    print "</tr>";
    print '</table>';
}

/*
 *  Repertoire
 */
print '<br>';
print load_fiche_titre($langs->trans("PathToDocuments"));

print "<table class=\"noborder\" width=\"100%\">\n";
print "<tr class=\"liste_titre\">\n";
print "  <td>" . $langs->trans("Name") . "</td>\n";
print "  <td>" . $langs->trans("Value") . "</td>\n";
print "</tr>\n";
print "<tr " . $bc[0] . ">\n  <td width=\"140\">" . $langs->trans("PathDirectory") . "</td>\n  <td>" . $conf->catalog->dir_output . "</td>\n</tr>\n";
print "</table>\n";

print '<br><div style="text-align: center">';
print '<input type="submit" name="save" class="button" value="' . $langs->trans("Save") . '">';
print "</div>";
print "</form>\n";

//dol_fiche_end();
dol_htmloutput_events();

llxFooter();
$db->close();