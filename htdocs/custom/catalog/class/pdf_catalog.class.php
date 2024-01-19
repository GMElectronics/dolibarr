<?php
/* Copyright (C) 2011		Juanjo Menent 	<jmenent@2byte.es>
 * Copyright (C) 2012-2017 	Ferran Marcet  <fmarcet@2byte.es>
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
 * or see http://www.gnu.org/
 */

/**
 *	\file       htdocs/catalog/class/pdf_catalog.class.php
 *	\ingroup    product
 *	\brief      File to build catalogs
 */
require_once(DOL_DOCUMENT_ROOT.'/core/lib/pdf.lib.php');
//require_once(DOL_DOCUMENT_ROOT."/includes/fpdfi/fpdi.php");
//require_once(DOL_DOCUMENT_ROOT."/core/lib/company.lib.php");
require_once(DOL_DOCUMENT_ROOT."/product/class/product.class.php");
require_once(DOL_DOCUMENT_ROOT."/core/lib/company.lib.php");
require_once(DOL_DOCUMENT_ROOT."/core/lib/product.lib.php");
require_once(DOL_DOCUMENT_ROOT."/categories/class/categorie.class.php");

/**
 *	\class      pdf_catalog
 *	\brief      Generata catalog class
 */
class pdf_catalog
{
	var $db;
    /**
     * @brief  Constructeur
     * @param handler $db
     */
    public function __construct($db)
    {
        global $langs;
        $this->db = $db;
        $this->description = $langs->transnoentities("Catalog");

        // Dimension page pour format A4
        $this->type = 'pdf';
        $formatarray=pdf_getFormat();
        $this->page_largeur = $formatarray['width'];
        $this->page_hauteur = $formatarray['height'];
        $this->format = array($this->page_largeur,$this->page_hauteur);
        $this->marge_gauche=isset($conf->global->MAIN_PDF_MARGIN_LEFT)?$conf->global->MAIN_PDF_MARGIN_LEFT:10;
        $this->marge_droite=isset($conf->global->MAIN_PDF_MARGIN_RIGHT)?$conf->global->MAIN_PDF_MARGIN_RIGHT:10;
        $this->marge_haute =isset($conf->global->MAIN_PDF_MARGIN_TOP)?$conf->global->MAIN_PDF_MARGIN_TOP:10;
        $this->marge_basse =isset($conf->global->MAIN_PDF_MARGIN_BOTTOM)?$conf->global->MAIN_PDF_MARGIN_BOTTOM:10;
    }

    /**
     * File to build document
     *
     * @param string    $_dir       	Dir
     * @param int       $month      	Month
     * @param int       $year       	Year
     * @param Translate	$outputlangs	Output language
     * @param string	$type			Type
     * @param int       $catlevel   	Price level
     * @param string	$footer			Footer
     * @param array		$catarray		Array of categories
     * @param null 		$pdf_input		More Pdf
     * @param int 		$position		Position of complementary PDF
     * @param int       $day        	Day
     * @param string    $search_ref 	Ref
     * @param int		$search_maxnb	MAx number of record in document
     * @return int
     * @internal param _dir $string Directory
     * @internal param month $int Catalog month
     * @internal param year $int Catalog year
     * @internal param outputlangs $string Lang output object
     * @internal param type $int type of catalog (0=products, 1=services)
     */
    public function write_file($_dir, $month, $year, $outputlangs, $type, $catlevel, $footer, $catarray, $pdf_input=null, $position=0, $day=0, $search_ref='', $search_maxnb=0, $socid, $divise=0)
    {
        include_once(DOL_DOCUMENT_ROOT . '/core/lib/date.lib.php');

        global $langs, $conf, $db;

        if (!is_object($outputlangs)) $outputlangs = $langs;

        $outputlangs->load("catalog@catalog");
        $outputlangs->load("products");
        $outputlangs->load("other");
        $outputlangs->load("companies");
        $outputlangs->load("main");

        // For backward compatibility with FPDF, force output charset to ISO, because FPDF expect text to be encoded in ISO
        if (!empty($conf->global->MAIN_USE_FPDF)) $outputlangs->charset_output = 'ISO-8859-1';

        if (empty($catlevel)) {
            $catlevel = -1;
        }

        $this->day = $day;
        $this->month = $month;
        $this->year = $year;

        $dir = $_dir . '/' . $year;

        if (!is_dir($dir)) {
            $result = dol_mkdir($dir);
            if ($result < 0) {
                $this->error = $outputlangs->transnoentities("ErrorCanNotCreateDir", $dir);
                return -1;
            }
        }

        if ($type) {
            $file = $dir . "/catalog_services-" . dol_print_date(dol_mktime(0, 0, 0, $this->month, $this->day, $this->year), "dayrfc", false, $outputlangs, true) . ($catlevel > 0 ? "-" . $catlevel : "") . (count($catarray)?"":"-all") . ".pdf";
        } else {
            $file = $dir . "/catalog_products-" . dol_print_date(dol_mktime(0, 0, 0, $this->month, $this->day, $this->year), "dayrfc", false, $outputlangs, true) . ($catlevel > 0 ? "-" . $catlevel : "") . (count($catarray)?"":"-all") . ".pdf";
        }

        //$num=0;
        $lines = array();
        $sql = "SELECT DISTINCT p.rowid, p.ref";
        if ($catlevel > 0) {
            $sql .= " , (select price from " . MAIN_DB_PREFIX . "product_price as pr where pr.fk_product=p.rowid and pr.price_level = " . $catlevel . " order by rowid desc limit 1)as price";
            $sql .= " , (select price_ttc from " . MAIN_DB_PREFIX . "product_price as pr where pr.fk_product=p.rowid and pr.price_level = " . $catlevel . " order by rowid desc limit 1)as price_ttc";
        } else {
            $sql .= ", p.price, p.price_ttc";
        }
        $sql .= ", p.tva_tx, p.tosell, p.fk_product_type, p.duration";
        $sql .= ", p.weight, p.weight_units, p.length, p.length_units";
		if($conf->global->CAT_SHOW_WIDTH) {
			$sql .= ", p.width, p.width_units";
		}
		if($conf->global->CAT_SHOW_HEIGHT){
			$sql .= ", p.height, p.height_units";
		}
        if($conf->global->CAT_SHOW_BARCODE){
            $sql .= ", p.barcode";
        }
        $sql .= ", p.surface, p.surface_units, volume, p.volume_units";
        if ($conf->global->MAIN_MULTILANGS) {
            $sql .= ", (SELECT pl.label FROM " . MAIN_DB_PREFIX . "product_lang as pl WHERE pl.fk_product = p.rowid AND pl.lang = '" . $outputlangs->defaultlang . "') as label_lang";
            $sql .= ", (SELECT pl.description FROM " . MAIN_DB_PREFIX . "product_lang as pl WHERE pl.fk_product = p.rowid AND pl.lang = '" . $outputlangs->defaultlang . "') as descr_lang";
        }

        $sql .= ", p.label, p.description, p.fk_country, p.stock";
        if ($conf->global->CAT_GROUP_BY_CATEGORY) {
            $sql .= ", c.fk_categorie";
        }
		if ($conf->global->CAT_GROUP_BY_SUPPLIER) {
			$sql .= ", pfp.fk_soc, s.nom";
		}
        $sql .= " FROM  " . MAIN_DB_PREFIX . "product as p";
        $sql .= " LEFT JOIN " . MAIN_DB_PREFIX . "categorie_product as c on c.fk_product = p.rowid ";
		$sql .= " LEFT JOIN " . MAIN_DB_PREFIX . "product_fournisseur_price as pfp on pfp.fk_product = p.rowid ";
		$sql .= " LEFT JOIN " . MAIN_DB_PREFIX . "societe as s on s.rowid = pfp.fk_soc AND s.fournisseur = 1";
        if ($catlevel > 0) {
            $sql .= " LEFT JOIN " . MAIN_DB_PREFIX . "product_price as pp on pp.fk_product=p.rowid AND pp.price_level = " . $catlevel;
        }
        $sql .= " WHERE p.fk_product_type =" . $type;

        if ($conf->global->CAT_SHOW_NO_SELL) {
            $sql .= " AND p.tosell=1";
        }
        if ($conf->global->CAT_SHOW_NO_STOCK && ($type == 0 || $conf->global->STOCK_SUPPORTS_SERVICES)) {
            $sql .= " AND p.stock > 0";
        }
        $sql .= " AND p.entity IN (" . getEntity('product', 1) . ")";
        //$sql .=" AND s.fournisseur = 1";
        if ($search_ref) $sql.=natural_search('p.ref', $search_ref);
		if (!empty($socid)) {
			$numcat = count($socid);
			if ($socid[0] == -1) {
				$sql .= " AND (pfp.fk_soc IS NULL";
			} else {
				$sql .= " AND (pfp.fk_soc = " . $socid[0];
			}
			for ($i = 1; $i < $numcat; $i++) {
				if ($socid[$i] == -1) {
					$sql .= " OR pfp.fk_soc IS NULL";
				} else {
					$sql .= " OR pfp.fk_soc = " . $socid[$i];
				}
			}
			$sql .= ")";
		}

        if (!empty($catarray)) {
            $numcat = count($catarray);
            if ($catarray[0] == -1) {
                $sql .= " AND (c.fk_categorie IS NULL";
            } else {
                $sql .= " AND (c.fk_categorie = " . $catarray[0];
            }
            for ($i = 1; $i < $numcat; $i++) {
                if ($catarray[$i] == -1) {
                    $sql .= " OR c.fk_categorie IS NULL";
                } else {
                    $sql .= " OR c.fk_categorie = " . $catarray[$i];
                }
            }
            $sql .= ")";
        }

        $sql .= " GROUP BY p.rowid, p.ref, p.price, p.price_ttc";
        $sql .= ", p.tva_tx, p.tosell, p.fk_product_type, p.duration";
        $sql .= ", p.weight, p.weight_units, p.length, p.length_units";
        if($conf->global->CAT_SHOW_WIDTH) {
			$sql .= ", p.width, p.width_units";
		}
        if($conf->global->CAT_SHOW_HEIGHT){
        	$sql .= ", p.height, p.height_units";
		}
        if($conf->global->CAT_SHOW_BARCODE){
            $sql .= ", p.barcode";
        }
        $sql .= ", p.surface, p.surface_units, volume, p.volume_units";
        $sql .= ", p.label, p.description, p.fk_country, p.stock";
        //mysql strict
        $sql .= ', s.nom';
        //
        if ($conf->global->CAT_GROUP_BY_CATEGORY) {
            $sql .= ", c.fk_categorie";
        }
		if ($conf->global->CAT_GROUP_BY_SUPPLIER) {
			$sql .= ", pfp.fk_soc";
		}

        $sql .= " ORDER BY ";
		if ($conf->global->CAT_GROUP_BY_SUPPLIER) {
			$sql .= "pfp.fk_soc,";
		}
        if ($conf->global->CAT_GROUP_BY_CATEGORY) {
            $sql .= "c.fk_categorie,";
        }
        if ($conf->global->CAT_ORDER_BY_REF) {
            $sql .= "p.ref ASC";
        } else {
            $sql .= "p.label ASC";
        }
		$sql.= $this->db->plimit($search_maxnb);

        dol_syslog(get_class($this) . "::write_file sql=" . $sql);
        $result = $this->db->query($sql);
        if ($result) {
            $num = $this->db->num_rows($result);
            $i = 0;
            $var = True;
            $objProd = new Product($db);
            while ($i < $num) {
                unset($realpath);

                $objp = $this->db->fetch_object($result);
                $var = !$var;

                if (!empty($conf->global->PRODUCT_USE_OLD_PATH_FOR_PHOTO)) {
                    $pdir[0] = get_exdir($objp->rowid, 2, 0, 0, $objProd, 'product') . $objp->rowid . "/photos/";
                    $pdir[1] = dol_sanitizeFileName($objp->ref) . '/';
                } else {
                    $pdir[0] = dol_sanitizeFileName($objp->ref) . '/';
                    $pdir[1] = get_exdir($objp->rowid, 2, 0, 0, $objProd, 'product') . $objp->rowid . "/photos/";
                }
                $arephoto = false;
                $realpath = "";
                foreach ($pdir as $midir) {
                    if (!$arephoto) {
                        $dir = $conf->product->dir_output . '/' . $midir;

                        foreach ($objProd->liste_photos($dir, 1) as $key => $obj) {
                            if ($conf->global->CAT_HIGH_QUALITY_IMAGES == 0) {
                                if ($obj['photo_vignette']) {
                                    $filename = $obj['photo_vignette'];
                                } else {
                                    $filename = $obj['photo'];
                                }
                            } else {
                                $filename = $obj['photo'];
                            }

                            $realpath = $dir . $filename;
                            $arephoto = true;
                        }
                    }
                }
                if($type==1){
					$objp->weight = null;
					$objp->volume = null;
				}
                $lines[$i][0] = $realpath;
                $lines[$i][1] = $objp->ref;
                $lines[$i][2] = $objp->price;
                $lines[$i][3] = $objp->price_ttc;
                $lines[$i][4] = $objp->tva_tx;
                $lines[$i][5] = $objp->fk_product_type;
                $lines[$i][6] = $objp->duration;
                $lines[$i][7] = $objp->weight;
                $lines[$i][8] = $objp->weight_units;
                $lines[$i][9] = $objp->length;
                $lines[$i][10] = $objp->length_units;
				$lines[$i][11] = $objp->width;
				$lines[$i][12] = $objp->width_units;
				$lines[$i][13] = $objp->height;
				$lines[$i][14] = $objp->height_units;
                $lines[$i][15] = $objp->surface;
                $lines[$i][16] = $objp->surface_units;
                $lines[$i][17] = $objp->volume;
                $lines[$i][18] = $objp->volume_units;
                $lines[$i][19] = $objp->lang;
                if (!empty($objp->label_lang))
                    $lines[$i][20] = $objp->label_lang;
                else
                    $lines[$i][20] = $objp->label;
                if (!empty($objp->descr_lang))
                    $lines[$i][21] = $objp->descr_lang;
                else
                    $lines[$i][21] = $objp->description;
                $lines[$i][22] = $objp->fk_country;
                if (empty($objp->fk_categorie)) {
                    $lines[$i][23] = 0;
                } else {
                    $lines[$i][23] = $objp->fk_categorie;
                }
                $lines[$i][24] = $objp->stock;
				if (empty($objp->fk_soc)) {
					$lines[$i][25] = 0;
					$lines[$i][26] = '';
				} else {
					$lines[$i][25] = $objp->fk_soc;
					$lines[$i][26] = $objp->nom;
				}
                if($conf->global->CAT_SHOW_BARCODE){
                    $lines[$i][27] = $objp->barcode;
                }

                $i++;
            }

        } else {
            dol_print_error($this->db);
        }

        $pdf = pdf_getInstance($this->format);

        if (class_exists('TCPDF')) {
            $pdf->setPrintHeader(false);
            $pdf->setPrintFooter(false);
        }

        if ($pdf_input !== null && $position == 0) {
            $this->add_pdf($pdf, $pdf_input);
        }

        //$pdf=new PDF();  // On crée une nouvelle instance pour le PDF
        $pdf->AddFont('helvetica', '', 'helvetica.php'); // On ajoute la police helvetica
        //$pdf->AliasNbPages(); // Allias pour le Nbre de page. Par défaut {nb}
        $pdf->AddPage();      // On ajoute une page. La première
        $pdf->SetFont(pdf_getPDFFont($outputlangs), '', 24); // On sélectionne la police helvetica de taille 24

		$pdf->SetMargins($this->marge_gauche, $this->marge_haute, $this->marge_droite);   // Left, Top, Right
		$pdf->setPageOrientation('', 1, $this->marge_basse + 8 + 12);	// The only function to edit the bottom margin of current page to set it.

        $title = $conf->global->CAT_TITLE;
        if (empty($title)) {
            $title = $outputlangs->transnoentities("Catalog".$type);
            $title .= ' - ' . dol_print_date(dol_mktime(0, 0, 0, $this->month, $this->day, $this->year), "daytext", false, $outputlangs, true);
            $title = strip_tags($title);
        }
        $this->_pagehead($pdf, 1);

        $pdf->SetY(120); // On se positionne à Y=100
        $pdf->SetX($this->marge_gauche);
        $sd = $pdf->getCellPaddings();
        $pdf->SetCellPaddings(10, 15, 0, 15);
        //$pdf->Cell(120,40,$title,1,2,'C',0); // On dessine une cellule avec le titre du catalogue
        $pdf->MultiCell(($this->page_largeur - $this->marge_gauche - $this->marge_droite), 0, $title, 1, 'C');
        //$pdf->writeHTMLCell(130, 0, 40 , 120, $title, 1, 1,0,1,'C',1);
        $pdf->SetCellPaddings($sd['L'], $sd['T'], $sd['R'], $sd['B']);
        // New page
        //$pdf->AddPage();
        if (!$conf->global->CAT_GROUP_BY_CATEGORY)
		{
            $this->_pagefoot($pdf, 1, $outputlangs);
			$pdf->AddPage();
		}

        //$this->_pagehead($pdf);
        $this->Body($pdf, $lines, $outputlangs, $footer, $divise);

        //$pdf->AliasNbPages();
        if ($pdf_input !== null && $position == 1) {
            $this->add_pdf($pdf, $pdf_input);
        }

        $pdf->Close();
        $pdf->Output($file, 'F');
        if (!empty($conf->global->MAIN_UMASK))
            @chmod($file, octdec($conf->global->MAIN_UMASK));

        return 1;
    }

    public function add_pdf(&$pdf, &$pdf_input)
    {
        $pagecount = $pdf->setSourceFile($pdf_input);
        for ($i = 1; $i <= $pagecount; $i++) {
            $tplidx = $pdf->importPage($i);
            $s = $pdf->getTemplatesize($tplidx);
            $pdf->AddPage($s['h'] > $s['w'] ? 'P' : 'L');
            $pdf->useTemplate($tplidx);
        }
    }

    /**
     *    Show header of page
     *
     * @param      $pdf            Object PDF
     * @param $page
     */
    public function _pagehead(&$pdf, $page)
    {
        global $conf, $mysoc;

		$pdf->setXY($this->marge_gauche, $this->marge_haute);

        $logo = $conf->mycompany->dir_output . '/logos/' . $mysoc->logo;
        if (is_readable($logo) && !empty($mysoc->logo)) {
            if ($page != 1) 	// Logo on header
            {
                $height = pdf_getHeightForLogo($logo);
				$maxheight = 50;
			    if ($height > $maxheight)
				{
					$height = $maxheight;
				}
                $pdf->Image($logo, 10, 8, 0, $height);
            } else {			// Logo on first page
                $height = 60;
                $maxwidth = 150;
                include_once DOL_DOCUMENT_ROOT . '/core/lib/images.lib.php';
                $tmp = dol_getImageSize($logo);
                if ($tmp['height']) {
                    $width = $height * $tmp['width'] / $tmp['height'];
                    if ($width > $maxwidth) {
                        $height = $height * $maxwidth / $width;
                        $width = $maxwidth;
                    }
                }
                $absx = ($this->page_largeur - $width) / 2;
                $pdf->Image($logo, $absx, 40, 0, $height);
            }
        }
    }

    /**
     *    @brief      Show footer of page
     * @param $pdf
     * @param $page
     * @param $outputlangs
     */
    public function _pagefoot(&$pdf, $page, $outputlangs)
    {
        if ($page > 1) // Si on est pas sur la première page
        {
            //Positionnement à 1,5 cm du haut
            $pdf->SetY(15);
            $pdf->SetFont(pdf_getPDFFont($outputlangs), 'I', 8);

            //Num page
   			if (strtolower(pdf_getPDFFont($outputlangs)) == 'helvetica')
			{
				if (empty($conf->global->MAIN_USE_FPDF)) $strpage = $outputlangs->transnoentities("Page")." ".$pdf->PageNo().'/'.$pdf->getAliasNbPages();
				else $strpage = $outputlangs->transnoentities("Page")." ".$pdf->PageNo().'/{nb}';
			}
			else
			{
				$strpage = $outputlangs->transnoentities("Page")." ".$page;
			}
            $pdf->SetX($this->page_largeur - $this->marge_droite - 40);
            $pdf->Cell(30, 10, $outputlangs->convToOutputCharset($strpage), 0, 1, 'C');
            //$pdf->Cell(30, 10, " ", 0, 1, 'J');
        }
    }

    public function myfoot(&$pdf, $page, $outputlangs, $footer)
    {
        if ($page > 1) // Si on est pas sur la première page
        {
            if ($footer) {
				$heightforfooter = 10;

				$pdf->SetFont(pdf_getPDFFont($outputlangs), '', 8);
				$pdf->setPageOrientation('', 1, 0);	// The only function to edit the bottom margin of current page to set it.

		        $pdf->SetX($this->marge_gauche);
		        $pdf->SetY($this->page_hauteur - $this->marge_basse - 2);

                $pdf->MultiCell(($this->page_largeur - $this->marge_gauche - $this->marge_droite), 10, $this->page_hauteur.' - '.$footer, 0, 'C');
            }
        }
    }

    /**
     *  Return path of a catergory image
     * @param        int $idCat Id of Category
     * @return string Image path
     */
    public function getImageCategory($idCat)
    {
        global $conf, $db;

        if ($idCat > 0) {
            $objCat = new Categorie($db);

            $pdir = get_exdir($idCat, 2, 0, 0, $objCat, 'category') . $idCat . "/photos/";
            $dir = $conf->categorie->dir_output . '/' . $pdir;
            foreach ($objCat->liste_photos($dir, 1) as $key => $obj) {
                $filename = $dir . $obj['photo'];
            }
            return $filename;
        }
        return '';
    }

    /**
     * @param $pdf
     * @param $lines
     * @param $outputlangs
     * @param $footer
     */
    public function Body(&$pdf, $lines, $outputlangs, $footer, $divise=0)
    {
        global $conf, $db, $langs;

        if ($conf->global->CAT_TRUNCATE_NAME == 0)
            $truncate = 'left';
        else if ($conf->global->CAT_TRUNCATE_NAME == 1)
            $truncate = 'middle';
        else
            $truncate = 'right';

		$default_font_size = pdf_getPDFFontSize($outputlangs);	// Must be after pdf_getInstance
        $pdf->SetFont(pdf_getPDFFont($outputlangs), '', $default_font_size);

		$headerheight = 45;
        $y_axe = $headerheight;              // Position en Y par défaut
        $x_axe = $this->marge_gauche;        // Position en X par défaut
        $interligne = 0;      // Interligne entre chaque produit. Initialisée à 0                                                    //
        $i = 0;               // Variable pour boucle
        $page = 2;

        $max = empty($conf->global->CATALOG_MAXNB_PERPAGE)?4:$conf->global->CATALOG_MAXNB_PERPAGE;             	   // Max nb or record per page
        $heightlogo = 40;
        $maxwidthlogo = 120;
        $height = 35;
        $maxwidth = 40;
        $offsetximage = 5;
        $heightofline = 50;
        if ($this->page_largeur < 210) // To work with US executive format
        {
        	$height = 25;
        	$maxwidth = 35;
        }
        if (! empty($conf->global->CATALOG_RENDERING_OPTION_2))
        {
        	$max = empty($conf->global->CATALOG_MAXNB_PERPAGE)?6:$conf->global->CATALOG_MAXNB_PERPAGE;             // Max nb or record per page
        	$height = 20;
        	$maxwidth = 30;
        	$offsetximage = 10;
        	$heightofline = 30;
            if ($this->page_largeur < 210) // To work with US executive format
	        {
	        	$height = 18;
	        	$maxwidth = 25;
	        }
        }

        $outputlangs->load('bills');

        $numlines = count($lines);

        $categories = new Categorie($db);
        $cat = $categories->get_all_categories(0);
        $categories->label = $outputlangs->transnoentities("NoCategorie");
        $cat[0] = $categories;

        $cat_label = null;
        $prov_label = null;
        for ($j = 0; $j < $numlines; $j++) {
			if ($prov_label != $lines[$j][25] && $conf->global->CAT_GROUP_BY_SUPPLIER && $lines[$j][24]>0) {
				$cat_label = '';
				if(!$conf->global->CAT_GROUP_BY_CATEGORY){
					if($page > 2){
						$pdf->AddPage();
					}
				}
				else{
					$pdf->AddPage();
				}
				$pdf->SetFont(pdf_getPDFFont($outputlangs), 'I', 8);
				//$strpage=$outputlangs->transnoentities("Page")." ".($pdf->PageNo())."/".$pdf->getAliasNbPages();
				$pdf->SetX(180);
				$pdf->Cell(30, 10, $outputlangs->convToOutputCharset($strpage), 0, 1, 'C');
				$prov_label = $lines[$j][26];

				$pdf->SetFont(pdf_getPDFFont($outputlangs), '', 30); // On sélectionne la police helvetica de taille 24

				$pdf->SetY(120); // On se positionne à Y=100
				$pdf->SetX($this->marge_gauche);
				$pdf->MultiCell(($this->page_largeur - $this->marge_gauche - $this->marge_droite), 0, $outputlangs->transnoentities("catSupplier"), 0, 'C');
				$pdf->MultiCell(($this->page_largeur - $this->marge_gauche - $this->marge_droite), 0, '', 0, 'C');
				$pdf->MultiCell(($this->page_largeur - $this->marge_gauche - $this->marge_droite), 0, $prov_label, 0, 'C');

				$pdf->SetFont(pdf_getPDFFont($outputlangs), '', 30);
				$this->myfoot($pdf, $page, $outputlangs, $footer);
				$this->_pagehead($pdf, $page);

				$i = 0;
				$y_axe = $headerheight;
				$interligne = 0;

				$this->_pagefoot($pdf, $page, $outputlangs);
				if(!$conf->global->CAT_GROUP_BY_CATEGORY){
					$pdf->AddPage();
					$pdf->SetFont(pdf_getPDFFont($outputlangs), '', 12);
					$page++;
				}
			}

			if ($cat_label != $cat[$lines[$j][23]]->label && $conf->global->CAT_GROUP_BY_CATEGORY) {
				//$i = 0;
				//$y_axe=30;
				//$interligne=0;
				$pdf->AddPage();
				$pdf->SetFont(pdf_getPDFFont($outputlangs), 'I', 40);
				//$strpage=$outputlangs->transnoentities("Page")." ".($pdf->PageNo())."/".$pdf->getAliasNbPages();
				$pdf->SetX(180);
				$pdf->Cell(30, 10, $outputlangs->convToOutputCharset($strpage), 0, 1, 'C');
				if (!empty($cat[$lines[$j][23]]->multilangs[$outputlangs->defaultlang])) {
					$cat_label = $cat[$lines[$j][23]]->multilangs[$outputlangs->defaultlang][label];
				} else {
					$cat_label = $cat[$lines[$j][23]]->label;
				}
				$pdf->SetFont(pdf_getPDFFont($outputlangs), '', 40); // On sélectionne la police helvetica de taille 24
				$logo = $this->getImageCategory($lines[$j][23]);

				include_once DOL_DOCUMENT_ROOT . '/core/lib/images.lib.php';
				$tmp = dol_getImageSize($logo);
				if ($tmp['height']) {
					$width = $heightlogo * $tmp['width'] / $tmp['height'];
					if ($width > $maxwidthlogo) {
						$heightlogo = $heightlogo * $maxwidthlogo / $width;
						$width = $maxwidth;
					}
				}
				$absx = ($this->page_largeur - $width) / 2;
				if (!empty($logo)) {
					$pdf->Image($logo, $absx, 40, 0, $heightlogo);
				}

				$pdf->SetY(120); // On se positionne à Y=100
				$pdf->SetX($this->marge_gauche);
				$pdf->MultiCell(($this->page_largeur - $this->marge_gauche - $this->marge_droite), 0, $outputlangs->transnoentities("catCategorie"), 0, 'C');
				$pdf->MultiCell(($this->page_largeur - $this->marge_gauche - $this->marge_droite), 0, '', 0, 'C');
				$pdf->MultiCell(($this->page_largeur - $this->marge_gauche - $this->marge_droite), 0, $cat_label, 0, 'C');

				$pdf->SetFont(pdf_getPDFFont($outputlangs), '', 12);
				$this->myfoot($pdf, $page, $outputlangs, $footer);
				$this->_pagehead($pdf, $page);

				$i = 0;
				$y_axe = $headerheight;
				$interligne = 0;

				$this->_pagefoot($pdf, $page, $outputlangs);
				$pdf->AddPage();
				$pdf->SetFont(pdf_getPDFFont($outputlangs), '', 12);
				$page++;
			}

            if ($i == $max)  // On vérifie si on a atteint le nombre de produit max par page
            {             // Si oui, on ré-initialise les variables
                $i = 0;
                $y_axe = $headerheight;
                $interligne = 0;

				$pdf->AddPage();
                $pdf->SetFont(pdf_getPDFFont($outputlangs), '', 12);
                $page++;
            }
            if ($i == 0) {
                // Output the header and footers before writing first record of page
                $this->_pagefoot($pdf, $page, $outputlangs);
                $this->myfoot($pdf, $page, $outputlangs, $footer);
                $this->_pagehead($pdf, $page);
            }

            $description = $lines[$j][21];  // description courte

            if (dol_textishtml($description)) $description = preg_replace('/__N__/', '<br>', $description);
            else $description = preg_replace('/__N__/', "\n", $description);
            $description = dol_htmlentitiesbr($description, 1);
            //$description=$outputlangs->convToOutputCharset(strip_tags($description));     // On enlève les tags HTML

            // Description
            $tamano = 300;
            if ($this->page_hauteur < 297) $tamano = 250;

            if (strlen($description) > $tamano) {
                $contador = 0;
                $arrayDescription = explode(' ', $description);
                $description = '';

                while ($tamano >= strlen($description) + strlen($arrayDescription[$contador])) {
                    $description .= ' ' . $arrayDescription[$contador];
                    $contador++;
                }
                $description .= '...';
            }

            if ($conf->global->CAT_SHOW_PRICE) {
                if ($conf->global->CAT_SHOW_PRICE_VAT == 1) {
                    $price = $lines[$j][3];  // price_ttc
                } else {
                    $price = $lines[$j][2];  // price_ht
                }
                if ($price) {

                	if($divise){
						$sql = 'SELECT r.rate, m.code FROM ' . MAIN_DB_PREFIX . 'multicurrency_rate as r, ' . MAIN_DB_PREFIX . 'multicurrency as m ';
						$sql .= 'WHERE m.rowid = r.fk_multicurrency AND r.fk_multicurrency = ' . $divise . ' ORDER BY r.date_sync DESC LIMIT 1';
						$resql = $db->query($sql);
						if ($resql) {
							$num = $db->num_rows($resql);

							if ($num==1) {
								$obj = $db->fetch_object($resql);
								if($obj->code!=$conf->currency) {
									$price = $price * $obj->rate;
									$currency = $obj->code;
								}
								else{
									$currency = $conf->currency;
								}
							}
						}
					}
                	else{
                		$currency = $conf->currency;
					}

                    $pricewithcurrency = price(price2num($price, 'MT'), 0, $outputlangs, 0, -1, 2, empty($conf->global->CATALOG_SHOW_CURRENCY)?$currency:''); // ajout du signe de la devise
                    $price = '';
                    if (empty($conf->global->CATALOG_RENDERING_OPTION_2)) $price.= $outputlangs->transnoentities('Price') . ' : ';
                    $price.= dol_html_entity_decode($pricewithcurrency,ENT_QUOTES);
                }
            }

            if ($conf->global->CAT_SHOW_WEIGHT) {
                $weight = $lines[$j][7];
                if ($weight) {
                    $weight_units = $lines[$j][8];
                    $weight .= " " . $outputlangs->transnoentities(measuring_units_string($weight_units, "weight"));
                    $weight = $outputlangs->transnoentities('Weight') . ' : ' . $weight;
                }

            }
            if ($conf->global->CAT_SHOW_LENGTH) {
                $length = $lines[$j][9];
                if ($length) {
                    $length_units = $lines[$j][10];
                    $length .= " " . $outputlangs->transnoentities(measuring_units_string($length_units, "size"));
                    $length = $outputlangs->transnoentities('Length') . ' : ' . $length;
                }
            }

			if ($conf->global->CAT_SHOW_WIDTH) {
				$width1 = $lines[$j][11];
				if ($width1) {
					$width1_units = $lines[$j][12];
					$width1 .= " " . $outputlangs->transnoentities(measuring_units_string($width1_units, "size"));
					$width1 = $langs->trans('Width1') . ' : ' . $width1;
				}
			}
			if ($conf->global->CAT_SHOW_HEIGHT) {
				$height1 = $lines[$j][13];
				if ($height1) {
					$height1_units = $lines[$j][14];
					$height1 .= " " . $outputlangs->transnoentities(measuring_units_string($height1_units, "size"));
					$height1 = $outputlangs->transnoentities('Height') . ' : ' . $height1;
				}
			}
            if ($conf->global->CAT_SHOW_SURFACE) {
                $surface = $lines[$j][15];
                if ($surface) {
                    $surface_units = $lines[$j][16];
                    $surface .= " " . $outputlangs->transnoentities(html_entity_decode(measuring_units_string($surface_units, "surface")));
                    $surface = $outputlangs->transnoentities('Surface') . ' : ' . $surface;
                }

            }
            if ($conf->global->CAT_SHOW_VOLUME) {
                $volume = $lines [$j][17];
                if ($volume) {
                    $volume_units = $lines[$j][18];
                    $volume .= " " . $outputlangs->transnoentities(html_entity_decode(measuring_units_string($volume_units, "volume")));
                    $volume = $outputlangs->transnoentities('Volume') . ' : ' . $volume;
                }

            }
            if ($conf->global->CAT_SHOW_STOCK) {
                $stock='';
            	if (empty($conf->global->CATALOG_RENDERING_OPTION_2)) $stock.= $outputlangs->trans("Stock").' : ';
                $stock.= ($lines[$j][24]?$lines[$j][24]:0);
            }


            if ($conf->global->CAT_SHOW_PAYS_SOURCE) {
                $country = getCountry($lines [$j][22], '', 0, $outputlangs, 0);
                if ($country) {
                    $country = $outputlangs->transnoentities('Country') . ' : ' . $country;
                }
            }

            if ($conf->global->CAT_SHOW_DURATION) {
                $duration = $lines[$j][6];
                $duration_unit = substr($duration, -1);
                $duration = substr($duration, 0, strlen($duration) - 1);
                if ($duration) {
                    if ($duration > 1) {
                        $dur = array("i" => $outputlangs->transnoentities("Minutes"), "h" => $outputlangs->transnoentities("Hours"), "d" => $outputlangs->transnoentities("Days"), "w" => $outputlangs->transnoentities("Weeks"), "m" => $outputlangs->transnoentities("Months"), "y" => $outputlangs->transnoentities("Years"));
                    } else if ($duration > 0) {
                        $dur = array("i" => $outputlangs->transnoentities("Minute"), "h" => $outputlangs->transnoentities("Hour"), "d" => $outputlangs->transnoentities("Day"), "w" => $outputlangs->transnoentities("Week"), "m" => $outputlangs->transnoentities("Month"), "y" => $outputlangs->transnoentities("Year"));
                    }
                    $duration .= ' ' . $outputlangs->trans($dur[$duration_unit]);
                    $duration = $outputlangs->transnoentities('Duration') . ' : ' . $duration;
                }
            }

            if ($conf->global->CAT_SHOW_BARCODE) {
                $barcode = $lines[$j][27];
                if ($barcode) {
                    $barcode = $langs->trans('BarcodeValue') . ' : ' . $barcode;
                }
            }

			$ref = $lines[$j][1];
			$label = $lines[$j][20];

            $nameproduit = $ref . " - " . dol_trunc($label, 70 - dol_strlen($ref), $truncate);

            $image = dol_buildpath('/public/theme/common/nophoto.png', 0);
            if ($lines[$j][0]) {
                $image = $lines[$j][0];
            }

            // Ref product + Label
            $pdf->SetY($y_axe + $interligne + 8); // On se positionne 8 mm sous la précédente rubrique

            $nexY = $y_axe + $interligne + 8;

            $posproperties = 160;
            $maxwidth = 40;			// Max width of images
            if ($this->page_largeur < 210) // To work with US executive format
            {
            	$posproperties-=10;
            	$maxwidth = 35;
            }

            if (empty($conf->global->CATALOG_RENDERING_OPTION_2))
            {
	            $default_font_size = pdf_getPDFFontSize($outputlangs);	// Must be after pdf_getInstance
	            $pdf->SetFont('','', $default_font_size);

            	$pdf->SetFillColor(212, 212, 212);  // Couleur de la cellule pour le nom du produit
    	        $pdf->Cell($this->page_largeur - $this->marge_gauche - $this->marge_droite, 6, $nameproduit, 0, 2, 'L', 1); // Nom du produit
            }
            else
            {
            	$default_font_size = pdf_getPDFFontSize($outputlangs);	// Must be after pdf_getInstance

            	if ($i == 0) 	// Line of titles
            	{
            		$pdf->SetFont('','B', $default_font_size - 1);
            		$pos_y = $y_axe + $interligne + 8;
            		$pos_x = $x_axe;
            		$pdf->writeHTMLCell(10, 5, $pos_x, $pos_y, '#', 0, 1);
            		$pos_x = $x_axe + $maxwidth + 5;
            		$pdf->writeHTMLCell(50, 5, $pos_x, $pos_y, $outputlangs->transnoentities('Ref'), 0, 1);
            		$pos_x = $x_axe + $maxwidth + 55;
            		$pdf->writeHTMLCell(50, 5, $pos_x, $pos_y, $outputlangs->transnoentities('Label'), 0, 1);
            		$pos_x = $x_axe + $maxwidth + 55;
            		$pdf->writeHTMLCell(50, 5, $pos_x, $pos_y, $outputlangs->transnoentities('Label'), 0, 1);
            		$pos_x = $posproperties;
            		$pdf->writeHTMLCell(50, 5, $pos_x, $pos_y, $outputlangs->transnoentities('Price'), 0, 1);
            		//$pos_x = $posproperties + 20;
            		//$pdf->writeHTMLCell(50, 5, $pos_x, $pos_y, $outputlangs->transnoentities('Stock'), 0, 1);
            	}
            	$pdf->SetFont('','', $default_font_size - 1);

            	//$pdf->SetLineStyle(array('dash'=>'1,1','color'=>array(80,80,80)));
            	//$pdf->SetDrawColor(190,190,200);
            	$pdf->line($this->marge_gauche, $nexY+5, $this->page_largeur - $this->marge_droite, $nexY+5);
            }

            if (empty($conf->global->CATALOG_RENDERING_OPTION_2))
            {
	            // Description
	            $pos_y = $y_axe + $interligne + 16;
	            $pos_x = $x_axe + $maxwidth + 5;
	            $pdf->writeHTMLCell(($posproperties - $pos_x - 2), 5, $pos_x, $pos_y, $description, 0, 1);
            }
            else
            {
            	// Nb
            	$pos_y = $y_axe + $interligne + 16;
            	$pos_x = $x_axe;
            	$pdf->writeHTMLCell(10, 5, $pos_x, $pos_y, ($j+1), 0, 1);

            	// Ref
	            $pos_y = $y_axe + $interligne + 16;
	            $pos_x = $x_axe + $maxwidth + 5;
            	$pdf->writeHTMLCell(50, 5, $pos_x, $pos_y, $ref, 0, 1);

            	// Label
            	$pos_y = $y_axe + $interligne + 16;
            	$pos_x = $x_axe + $maxwidth + 55;
            	$pdf->writeHTMLCell(50, 5, $pos_x, $pos_y, $label, 0, 1);
            }

            $deltay = 8;

            // Property
            $pdf->SetY($y_axe + $interligne + $deltay); // On se décalle de 16 mm sous le nom du produit
            $pdf->SetX($posproperties); // On se décalle de 50 mm en x
            $pdf->Cell(20, 20, $price, 0, 2, 'L', 0); // On imprime le prix
            if ($price) $deltay+=5;

            $pdf->SetY($y_axe + $interligne + $deltay);
            $pdf->SetX($posproperties);
            $pdf->Cell(20, 20, $weight, 0, 2, 'L', 0);
            if ($weight) $deltay+=5;

            $pdf->SetY($y_axe + $interligne + $deltay);
            $pdf->SetX($posproperties);
            $pdf->Cell(20, 20, $length, 0, 2, 'L', 0);
            if ($length) $deltay+=5;

			if ($conf->global->CAT_SHOW_WIDTH) {
				$pdf->SetY($y_axe + $interligne + $deltay);
				$pdf->SetX($posproperties);
				$pdf->Cell(20, 20, $width1, 0, 2, 'L', 0);
				if ($width1) $deltay += 5;
			}

			if ($conf->global->CAT_SHOW_HEIGHT) {
				$pdf->SetY($y_axe + $interligne + $deltay);
				$pdf->SetX($posproperties);
				$pdf->Cell(20, 20, $height1, 0, 2, 'L', 0);
				if ($height1) $deltay += 5;
			}

            $pdf->SetY($y_axe + $interligne + $deltay);
            $pdf->SetX($posproperties);
            $pdf->Cell(20, 20, $surface, 0, 2, 'L', 0);
            if ($surface) $deltay+=5;

            $pdf->SetY($y_axe + $interligne + $deltay);
            $pdf->SetX($posproperties);
            $pdf->Cell(20, 20, $volume, 0, 2, 'L', 0);
            if ($volume) $deltay+=5;

            $pdf->SetY($y_axe + $interligne + $deltay);
            $pdf->SetX($posproperties);
            $pdf->Cell(20, 20, $country, 0, 2, 'L', 0);
            if ($country) $deltay+=5;

            $pdf->SetY($y_axe + $interligne + $deltay);
            $pdf->SetX($posproperties);
            $pdf->Cell(20, 20, $duration, 0, 2, 'L', 0);
            if ($duration) $deltay+=5;

			if ($conf->global->CAT_SHOW_BARCODE && $barcode) {
				$pdf->SetY($y_axe + $interligne + $deltay);
				$pdf->SetX($posproperties);
				$pdf->Cell(20, 20, $barcode, 0, 2, 'L', 0);
				if ($barcode) $deltay += 5;
			}

			if (!empty($conf->global->CATALOG_RENDERING_OPTION_2)) $deltay = 8;
			$pdf->SetY($y_axe + $interligne + $deltay);
			$pdf->SetX($posproperties + (!empty($conf->global->CATALOG_RENDERING_OPTION_2)?20:0));
			$pdf->Cell(20, 20, $stock, 0, 2, 'L', 0);
			if ($stock) $deltay+=5;

            $pdf->SetY($y_axe + $interligne + 16); //On se décalle de 16 mm sous le nom du produit
            $pdf->SetX($x_axe); // On se positionne à $x_axe
            //$pdf->Cell(30,50,$pdf->Image($image,null,null,80,80),0,2,'L',0);

            include_once DOL_DOCUMENT_ROOT . '/core/lib/images.lib.php';
            $tmp = dol_getImageSize($image);
            $tmp['height'] = $tmp['height'] * 0.265;
            $tmp['width'] = $tmp['width'] * 0.265;
            ($tmp['height'] < $height ? $height = $tmp['height'] : 0);
            if ($tmp['height']) {
                $width = $height * $tmp['width'] / $tmp['height'];
                if ($width > $maxwidth) {
                    $height = $height * $maxwidth / $width;
                    $width = $maxwidth;
                }
            }

            $pdf->Image($image, $x_axe + $offsetximage, $y_axe + $interligne + 16, 0, $height);
            //$pdf->Image($image,$x_axe,$y_axe+$interligne+16,21,21);

			// Une description de produit prend 50 mm en hauteur.
            // On saute donc de 50 mm ou plus pour le produit suivant
            if ($this->page_hauteur < 297) $interligne = $interligne + $heightofline;
            else $interligne = $interligne + $heightofline + 7; // Une description de produit prend 50 mm en hauteur.

            $i++;

        }
    }

	function select_suppliers(){

		$cate_arbo = Array();

		$sql = "SELECT rowid, nom ";
		$sql.= "FROM ".MAIN_DB_PREFIX."societe ";
		$sql.= "WHERE fournisseur = 1";

		$result = $this->db->query($sql);
		if ($result)
		{
			$num = $this->db->num_rows($result);
			$i = 0;
			while ($i < $num)
			{
				$objp = $this->db->fetch_object($result);
				if ($objp){
					$cate_arbo[$objp->rowid] = $objp->nom;
				}
				$i++;
			}
			$this->db->free($result);
		}
		else dol_print_error($this->db);
		return $cate_arbo;
	}
}
