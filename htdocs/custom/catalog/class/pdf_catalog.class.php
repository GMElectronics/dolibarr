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

/********************************************************
 * G.M.ELECTRONICS
 * REMEMBER THAT ALL GLOBAL CATEGORIES ARE LINKED TO THE COLOR
 * CONFIGURED INTO DOLIBARR. IT MEANS THAT "HEAT" CATEGORY NEEDS
 * TO HAVE ITS ACCESSORIES, METERS, ETC. IN THE SAME COLOR !
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

	var $catPictures =
	[
		'chaleur.jpg',
		'eau.jpg',
		'electricite.jpg',
		'fuel.jpg',
		'gaz.jpg',
		'capteurs.jpg',
		'passerelles.jpg',
		'convertisseurs.jpg',
	];

	var $catDescription =
	[
		'SECTION: CHALEUR',
		'SECTION: EAU',
		'SECTION: ELECTRICITE',
		'SECTION: MAZOUT',
		'SECTION: GAZ',
		'SECTION: CAPTEURS',
		'SECTION: PASSERELLES',
		'SECTION: CONVERTISSEURS',
	];

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
        $sql .= ", p.surface, p.surface_units, volume, p.volume_units";
        $sql .= ", p.label, p.description, p.fk_country, p.stock";
		$sql .= ", c.fk_categorie, categorie.description, categorie.color";
        $sql .= " FROM  " . MAIN_DB_PREFIX . "product as p";
        $sql .= " LEFT JOIN " . MAIN_DB_PREFIX . "categorie_product as c on c.fk_product = p.rowid ";
		$sql .= " LEFT JOIN " . MAIN_DB_PREFIX . "categorie as categorie on categorie.rowid = c.fk_categorie";
		$sql .= " LEFT JOIN " . MAIN_DB_PREFIX . "categorie as categorie_parent ON categorie_parent.rowid = categorie.fk_parent";
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
        $sql .= ", p.surface, p.surface_units, volume, p.volume_units";
        $sql .= ", p.label, p.description, p.fk_country, p.stock";
        $sql .= ', s.nom';
		$sql .= ", c.fk_categorie";
        $sql .= " ORDER BY categorie_parent.rowid, categorie.label, p.label ASC";
		$sql.= $this->db->plimit($search_maxnb);

        dol_syslog(get_class($this) . "::write_file sql=" . $sql);
        $result = $this->db->query($sql);

		if ($result)
		{
            $num = $this->db->num_rows($result);
            $i = 0;
            $var = True;
            $objProd = new Product($db);

            while ($i < $num)
			{
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
                foreach ($pdir as $midir)
				{
                    if (!$arephoto)
					{
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

                if($type==1)
				{
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

				$lines[$i][28] = $objp->description;
				$lines[$i][29] = $objp->color;

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

		// Default configuration
        $pdf->AddFont('helvetica', '', 'helvetica.php');
		$pdf->SetFont(pdf_getPDFFont($outputlangs), '', 16);
		$pdf->SetMargins($this->marge_gauche, $this->marge_haute, $this->marge_droite);
		$pdf->setPageOrientation('', 1, $this->marge_basse + 8 + 12);

		// Create cover page
        $pdf->AddPage();
		$sd = $pdf->getCellPaddings();
		$this->createCover($pdf, $outputlangs);

		// Put the global configuration to create the main content
		$pdf->SetCellPaddings($sd['L'], $sd['T'], $sd['R'], $sd['B']);
		$pdf->SetTextColor(20, 20, 20);

		// Create the main content
        $this->Body($pdf, $lines, $outputlangs, $footer, $divise);

		// Create end page with activities of GME
		$pdf->AddPage();
		$this->createEndPage($pdf);

        $pdf->Close();
        $pdf->Output($file, 'F');

        return 1;
    }

	private function createCover(&$pdf, $outputlangs)
	{
		global $conf;

		$cover = $conf->mycompany->dir_output . '/cover/Cover_gmelectronics.jpg';
		$pdf->setXY(0, 0);

		if (is_readable($cover))
		{
			$height = 297;
			$width = 210;
			include_once DOL_DOCUMENT_ROOT . '/core/lib/images.lib.php';
			$pdf->SetMargins(0, 0, 0);
			$pdf->SetAutoPageBreak(false, 0);
			$pdf->Image($cover, 0, 0, $width, $height);
		}

		$pdf->SetTextColor(146, 208, 80);
		$pdf->SetY(230);
		$pdf->SetX(110);

		$pdf->SetCellPaddings(10, 15, 0, 15);
		$pdf->MultiCell(($this->page_largeur - $this->marge_gauche - $this->marge_droite), 0, 'CATALOGUE PRODUITS', 0, 'L');

		$pdf->SetFont(pdf_getPDFFont($outputlangs), '', 14);
		$pdf->SetTextColor(70, 70, 70);
		$title = dol_print_date(dol_mktime(0, 0, 0, $this->month, $this->day, $this->year), "daytext", false, $outputlangs, true);

		$pdf->SetY(238);
		$pdf->SetX(110);
		$pdf->SetCellPaddings(10, 15, 0, 15);
		$pdf->MultiCell(($this->page_largeur - $this->marge_gauche - $this->marge_droite), 0, $title, 0, 'L');

		$pdf->SetY(246);
		$pdf->SetX(110);
		$pdf->SetCellPaddings(10, 15, 0, 15);
		$pdf->MultiCell(($this->page_largeur - $this->marge_gauche - $this->marge_droite), 0, 'Prix public', 0, 'L');
	}

	private function createEndPage(&$pdf)
	{
		global $conf;

		$cover = $conf->mycompany->dir_output . '/cover/Cover_activities.jpg';
		$pdf->setXY(0, 0);

		if (is_readable($cover))
		{
			$height = 297;
			$width = 210;
			include_once DOL_DOCUMENT_ROOT . '/core/lib/images.lib.php';
			$pdf->SetMargins(0, 0, 0);
			$pdf->SetAutoPageBreak(false, 0);
			$pdf->Image($cover, 0, 0, $width, $height);
		}

		$this->_pagehead($pdf);
	}

    public function add_pdf(&$pdf, &$pdf_input)
    {
        $pagecount = $pdf->setSourceFile($pdf_input);

        for ($i = 1; $i <= $pagecount; $i++)
		{
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
    public function _pagehead(&$pdf)
    {
        global $conf, $mysoc;

		$logo = $conf->mycompany->dir_output . '/logos/' . $mysoc->logo;
		$pdf->setXY($this->marge_gauche, $this->marge_haute);
		$pdf->SetMargins($this->marge_gauche, $this->marge_haute, $this->marge_droite);
		$pdf->SetAutoPageBreak(true, 0);

		if (is_readable($logo) && !empty($mysoc->logo))
		{
			$height = pdf_getHeightForLogo($logo);
			$maxheight = 15;

			if ($height > $maxheight)
			{
				$height = $maxheight;
			}

			$pdf->Image($logo, 155, 12, 0, $height);
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
        if ($page > 2)
        {
            $pdf->SetY(280);
            $pdf->SetFont(pdf_getPDFFont($outputlangs), '', 8);
			$pdf->SetTextColor(20, 20, 20);
			$pdf->SetMargins(0, 0, 0);
			$pdf->SetAutoPageBreak(false, 0);

			$strpage = $outputlangs->transnoentities("Page")." ".$pdf->PageNo().'/'.$pdf->getAliasNbPages();

            $pdf->SetX($this->page_largeur - $this->marge_droite - 20);
            $pdf->Cell(30, 0, $outputlangs->convToOutputCharset($strpage), 0, 1, 'C');
			$pdf->SetY(280);
			$pdf->Cell(0, 0, 'G.M.Electronics - BE0426.751.795 - www.gmelectronics.be - Avenue Vésale, 23 1300 Wavre - 010/77.90.66', 0, 1, 'C');

			$pdf->SetMargins($this->marge_gauche, $this->marge_haute, $this->marge_droite);
			$pdf->SetAutoPageBreak(true, 0);
        }
    }

    public function myfoot(&$pdf, $page, $outputlangs, $footer)
    {
        if ($page > 2)
        {
            if ($footer)
			{
				$pdf->SetFont(pdf_getPDFFont($outputlangs), '', 8);
				$pdf->setPageOrientation('', 1, 0);	// The only function to edit the bottom margin of current page to set it.

		        $pdf->SetX($this->marge_gauche);
		        $pdf->SetY($this->page_hauteur - $this->marge_basse - 2);

                $pdf->MultiCell(($this->page_largeur - $this->marge_gauche - $this->marge_droite), 10, $this->page_hauteur.' - '.$footer, 0, 'C');
            }
        }
    }

    /**
     *  Return path of a category image
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
        global $db, $conf;

		$default_font_size = pdf_getPDFFontSize($outputlangs);	// Must be after pdf_getInstance
        $pdf->SetFont(pdf_getPDFFont($outputlangs), '', $default_font_size);

		$headerheight = 45;
        $y_axe = $headerheight;             // Position en Y par défaut
        $x_axe = $this->marge_gauche;       // Position en X par défaut
        $interligne = 0;      				// Interligne entre chaque produit. Initialisée à 0
        $i = 0;               				// Variable pour boucle
        $page = 3;
        $heightlogo = 40;
        $maxwidthlogo = 120;
		$max = 30;             				// Max nb or record per page
		$height = 20;
		$maxwidth = 30;
		if ($this->page_largeur < 210) 		// To work with US executive format
		{
			$height = 18;
			$maxwidth = 25;
		}

        $outputlangs->load('bills');

        $numlines = count($lines);

        $categories = new Categorie($db);
        $cat = $categories->get_all_categories(0);
        $categories->label = $outputlangs->transnoentities("NoCategorie");
        $cat[0] = $categories;

		/*
		 * CATEGORIES
		 */

        $cat_label = null;
		$cat_color = null;
		$cat_color_cnt = 0;
        $prov_label = null;
        for ($j = 0; $j < $numlines; $j++)
		{
			if ($cat_label != $cat[$lines[$j][23]]->label)
			{
				if ($cat_label !== null)
				{
					$pdf->SetY($y_axe + $interligne + 10);
					$pdf->SetFont(pdf_getPDFFont($outputlangs), 'I', 7);
					$pdf->SetFillColor(255, 255, 255);
					$message = '* Les prix sont indiqués en euros, hors TVA et à l\'unité. Des remises peuvent être appliquées en fonction des quantités. Les prix peuvent être modifiés sans préavis.';
					$pdf->MultiCell(($this->page_largeur - $this->marge_gauche - $this->marge_droite), 0, $message, 0, 'L');

					$pdf->SetY($y_axe + $interligne + 15);
					$pdf->SetFont(pdf_getPDFFont($outputlangs), 'I', 7);
					$pdf->SetFillColor(255, 255, 255);
					$message = '** Si vous ne trouvez pas ce que vous cherchez, nous nous ferons un plaisir de vous aider via l\'email sales@gmelectronics.be';
					$pdf->MultiCell(($this->page_largeur - $this->marge_gauche - $this->marge_droite), 0, $message, 0, 'L');
				}
			}

			if($cat_color != $lines[$j][29])
			{
				$pdf->AddPage();

				$catPicture = $conf->mycompany->dir_output . '/categories/' . $this->catPictures[$cat_color_cnt];
				$pdf->setXY(0, 0);

				if (is_readable($catPicture))
				{
					$height = 297;
					$width = 210;
					include_once DOL_DOCUMENT_ROOT . '/core/lib/images.lib.php';
					$pdf->SetMargins(0, 0, 0);
					$pdf->SetAutoPageBreak(false, 0);
					$pdf->Image($catPicture, 0, 0, $width, $height);
					$pdf->SetMargins($this->marge_gauche, $this->marge_haute, $this->marge_droite);
					$pdf->SetAutoPageBreak(true, 0);
				}

				$pdf->SetFont(pdf_getPDFFont($outputlangs), '', 24);
				$pdf->SetTextColor(255, 255, 255);
				$pdf->SetY(255);
				$pdf->SetX($this->marge_gauche);
				$pdf->MultiCell(($this->page_largeur - $this->marge_gauche - $this->marge_droite), 0, $this->catDescription[$cat_color_cnt], 0, 'R');

				$pdf->SetTextColor(20, 20, 20);

				$cat_color_cnt++;
				$cat_color = $lines[$j][29];
			}

			if ($cat_label != $cat[$lines[$j][23]]->label)
			{
				$pdf->AddPage();
				$pdf->SetFont(pdf_getPDFFont($outputlangs), '', 15);
				$cat_label = $cat[$lines[$j][23]]->label;
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

				$pdf->SetY(35);
				$pdf->SetX($this->marge_gauche);
				$pdf->MultiCell(($this->page_largeur - $this->marge_gauche - $this->marge_droite), 0, $cat_label, 0, 'L');

				$description = $lines[$j][28];

				if($description !== '')
				{
					$pdf->SetFont(pdf_getPDFFont($outputlangs), '', 9);
					$pdf->SetY(43);
					$pdf->SetX($this->marge_gauche);
					$pdf->MultiCell(($this->page_largeur - $this->marge_gauche - $this->marge_droite), 0, $description, 0, 'L');
					$y_axe = $headerheight + 3;
				}
				else
				{
					$y_axe = $headerheight - 8;
				}

				$this->myfoot($pdf, $page, $outputlangs, $footer);
				$this->_pagehead($pdf);

				$i = 0;
				$interligne = 0;

				$pdf->SetY($y_axe + $interligne + 7);
				$pdf->SetFont(pdf_getPDFFont($outputlangs), 'B', 9);
				$pdf->SetFillColor(246, 246, 246);
				$pdf->Cell($this->page_largeur - $this->marge_gauche - $this->marge_droite, 7, 'Référence', 'T', 0, 'L', 1);
				$pdf->SetX(50);
				$pdf->Cell($this->page_largeur - $this->marge_gauche - $this->marge_droite, 7, 'Description', 0, 0, 'L', 0);
				$pdf->SetX(5);
				$pdf->Cell($this->page_largeur - $this->marge_gauche - $this->marge_droite, 7, 'Prix HTVA', 0, 0, 'R', 0);
				$pdf->SetX(0);
				$pdf->SetY($y_axe + $interligne + 7);

				$interligne = $interligne + 7;

				$this->_pagefoot($pdf, $page, $outputlangs);

				$pdf->SetFont(pdf_getPDFFont($outputlangs), '', 12);
				$page++;
			}

            if ($i == $max)  // On vérifie si on a atteint le nombre de produit max par page
            {             // Si oui, on ré-initialise les variables
                $i = 0;
                $y_axe = $headerheight;
                $interligne = 0;

				$pdf->AddPage();

                $page++;

				$pdf->SetY($y_axe + $interligne + 7);
				$pdf->SetFont(pdf_getPDFFont($outputlangs), 'B', 9);
				$pdf->SetFillColor(246, 246, 246);
				$pdf->Cell($this->page_largeur - $this->marge_gauche - $this->marge_droite, 7, 'Référence', 'T', 0, 'L', 1);
				$pdf->SetX(50);
				$pdf->Cell($this->page_largeur - $this->marge_gauche - $this->marge_droite, 7, 'Description', 0, 0, 'L', 0);
				$pdf->SetX(5);
				$pdf->Cell($this->page_largeur - $this->marge_gauche - $this->marge_droite, 7, 'Prix HTVA', 0, 0, 'R', 0);
				$pdf->SetX(0);
				$pdf->SetY($y_axe + $interligne + 7);

				$interligne = $interligne + 7;

				$pdf->SetFont(pdf_getPDFFont($outputlangs), '', 12);
            }

            if ($i == 0) {
                // Output the header and footers before writing first record of page
                $this->_pagefoot($pdf, $page, $outputlangs);
                $this->myfoot($pdf, $page, $outputlangs, $footer);
                $this->_pagehead($pdf);
            }

			/*
			 * PRICE
			 */

			$vatPrice = $lines[$j][3];  // price_ttc
			$wvatPrice = $lines[$j][2];  // price_ht
			$pricewithcurrency = price(price2num($wvatPrice, 'MT'), 0, $outputlangs, 0, -1, 2, '€');
			$price= dol_html_entity_decode($pricewithcurrency,ENT_QUOTES);

			/*
			* REF LINE
			*/

			$ref = $lines[$j][1];
			$label = $lines[$j][20];

            $image = dol_buildpath('/public/theme/common/nophoto.png', 0);
            if ($lines[$j][0]) {
                $image = $lines[$j][0];
            }

            // Ref product + Label
            $pdf->SetY($y_axe + $interligne + 7); // On se positionne 8 mm sous la précédente rubrique

            $posproperties = 160;
            $maxwidth = 40;			// Max width of images

            if ($this->page_largeur < 210) // To work with US executive format
            {
            	$posproperties-=10;
            	$maxwidth = 35;
            }

			$label = (strlen($label) > 50) ? substr($label, 0, 75).'...' : $label;
			$pdf->SetFont(pdf_getPDFFont($outputlangs), '', 9);
			if($i % 2 == 1){$pdf->SetFillColor(246, 246, 246);}else{$pdf->SetFillColor(255, 255, 255);}
			$pdf->Cell($this->page_largeur - $this->marge_gauche - $this->marge_droite, 7, $ref, 0, 0, 'L', 1);
			$pdf->SetX(50);
			$pdf->Cell($this->page_largeur - $this->marge_gauche - $this->marge_droite, 7, ucfirst($label), 0, 0, 'L', 0);
			$pdf->SetX(5);
			$pdf->Cell($this->page_largeur - $this->marge_gauche - $this->marge_droite, 7, $price, 0, 0, 'R', 0);
			$pdf->SetX(0);
			$pdf->SetY($y_axe + $interligne + 7);
            if ($this->page_hauteur < 297) $interligne = $interligne;
            else $interligne = $interligne + 7;

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
