<?php
/* Copyright (C) 2010-2011 	Juanjo Menent		<jmenent@2byte.es>
 * Copyright (C) 2010		Laurent Destailleur	<eldy@users.sourceforge.net>
 * Copyright (C) 2011      	Regis Houssin		<regis.houssin@capnetworks.com>
 * Copyright (C) 2013-2017	Ferran Marcet		<fmarcet@2byte.es>
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 3 of the License, or
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
 *	\file       htdocs/core/lib/prelevement.lib.php
 *	\brief      Ensemble de fonctions de base pour le module prelevement
 *	\ingroup    propal
 */


/**
 * Prepare array with list of tabs
 * @return array Array of tabs to shoc
 * @internal param Object $object Object related to tabs
 */
function catalogadmin_prepare_head()
{
    global $langs;
    $langs->load("catalog@catalog");

    $h = 0;
    $head = array();

    $head[$h][0] = dol_buildpath('/catalog/admin/catalog.php', 1);
    $head[$h][1] = $langs->trans("CatalogSetup");
    $head[$h][2] = 'configuration';
    $h++;

    return $head;
}

function getContacts($mail){
	global $db;
	$mail->withto = array();

	$sql = 'SELECT rowid,firstname,lastname,poste,email FROM ' . MAIN_DB_PREFIX . 'socpeople';
	$resql = $db->query($sql);
	if ($resql) {
		$num = $db->num_rows($resql);
		$i = 0;

		while ($i < $num) {
			$obj = $db->fetch_object($resql);
			$mail->withto[$obj->rowid] = $obj->firstname . ' ' . $obj->lastname . ' - ' . $obj->poste . '<' . ($obj->email?$obj->email:'Sin e-mail') . '>';
			$i++;
		}
	}
	return $mail;
}