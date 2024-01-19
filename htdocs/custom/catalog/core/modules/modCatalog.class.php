<?php
/* Copyright (C) 2003      Rodolphe Quiedeville <rodolphe@quiedeville.org>
 * Copyright (C) 2004-2009 Laurent Destailleur  <eldy@users.sourceforge.net>
 * Copyright (C) 2005-2010 Regis Houssin        <regis@dolibarr.fr>
 * Copyright (C) 2011-2012 Juanjo Menent		<jmenent@2byte.es>
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
 * 		\defgroup   catalog     Module catalog
 *      \brief      catalog tool.
 *					Such a file must be copied into htdocs/includes/module directory.
 */

/**
 *      \file       htdocs/includes/modules/modReports.class.php
 *      \ingroup    reports
 *      \brief      Description and activation file for module reports
 */
include_once(DOL_DOCUMENT_ROOT ."/core/modules/DolibarrModules.class.php");


/**
 * 		\class      modCatalog
 *      \brief      Description and activation class for module Catalog
 */
class modCatalog extends DolibarrModules
{
    /**
     *    Constructor.
     *
     * @param    DoliDB $db Database handler
     */
    public function __construct($db)
    {
        $this->db = $db;

        // Id for module (must be unique).
        $this->numero = 400006;

        // Key text used to identify module (for permissions, menus, etc...)
        $this->rights_class = 'catalog';

        // Family can be 'crm','financial','hr','projects','products','ecm','technic','other'
        $this->family = "technic";

        // Module label (no space allowed), used if translation string 'ModuleXXXName' not found (where XXX is value of numeric property 'numero' of module)
        $this->name = preg_replace('/^mod/i', '', get_class($this));

        // Module description, used if translation string 'ModuleXXXDesc' not found (where XXX is value of numeric property 'numero' of module)
        $this->description = "Catalog";

        $this->editor_name = "<b>2byte.es</b>";
        $this->editor_web = "www.2byte.es";

        // Possible values for version are: 'development', 'experimental', 'dolibarr' or version
        $this->version = '12.0.0';

        // Key used in llx_const table to save module status enabled/disabled (where MYMODULE is value of property name of module in uppercase)
        $this->const_name = 'MAIN_MODULE_' . strtoupper($this->name);

        // Where to store the module in setup page (0=common,1=interface,2=others,3=very specific)
        $this->special = 2;

        // Name of image file used for this module.
        $this->picto = 'generic';

        // Data directories to create when module is enabled.
        $this->dirs = array('/catalog/temp');

        // Config pages. Put here list of php page names stored in admmin directory used to setup module.
        $this->config_page_url = array('catalog.php@catalog');

        // Defined all module parts (triggers, login, substitutions, menus, css, etc...)
        $this->module_parts = array('css' => array('/catalog/css/catalog.css'));

        // Dependencies
        $this->depends = array("modProduct");        // List of modules id that must be enabled if this module is enabled
        $this->requiredby = array();                // List of modules id to disable if this one is disabled
        $this->phpmin = array(5, 6);                    // Minimum version of PHP required by module
        $this->need_dolibarr_version = array(7, 0);    // Minimum version of Dolibarr required by module
        $this->langfiles = array("catalog@catalog");

        // Constants
        $this->const = array();

        // Array to add new pages in new tabs
        $this->tabs = array();

        // Dictionnaries
        $this->dictionaries = array();

        // Boxes
        $this->boxes = array();            // List of boxes

        // Permissions
        $this->rights = array();
        $this->rights_class = 'catalog';
        $r = 0;

        $r++;
        $this->rights[$r][0] = 400061;
        $this->rights[$r][1] = 'Use catalog';
        $this->rights[$r][2] = 'a';
        $this->rights[$r][3] = 1;
        $this->rights[$r][4] = 'use';

        $r++;
        $this->rights[$r][0] = 400062;
        $this->rights[$r][1] = 'Download catalogs';
        $this->rights[$r][2] = 'd';
        $this->rights[$r][3] = 1;
        $this->rights[$r][4] = 'read';

        // Main menu entries
        $this->menus = array();            // List of menus to add
        $r = 0;

        //Menu left into products
        $this->menu[$r] = array('fk_menu' => 'fk_mainmenu=products',
            'type' => 'left',
            'titre' => 'Catalog',
            'mainmenu' => 'products',
            'leftmenu' => '1',
            'url' => '/catalog/catalog.php',
            'langs' => 'catalog@catalog',
            'position' => 100,
            'enabled' => '$conf->catalog->enabled',
            'perms' => '1',
            'target' => '',
            'user' => 0);

    }

    /**
     * Function called when module is enabled.
     * The init function adds tabs, constants, boxes, permissions and menus (defined in constructor) into Dolibarr database.
     * It also creates data directories
     *
     * @param string $options Options when enabling module ('', 'newboxdefonly', 'noboxes')
     *                          'noboxes' = Do not insert boxes
     *                          'newboxdefonly' = For boxes, insert def of boxes only and not boxes activation
     * @return int                1 if OK, 0 if KO
     */
    public function init($options = '')
    {
        $sql = array();

        $this->load_tables();

        return $this->_init($sql);
    }

    /**
     * Function called when module is disabled.
     * The remove function removes tabs, constants, boxes, permissions and menus from Dolibarr database.
     * Data directories are not deleted
     *
     * @param      string $options Options when enabling module ('', 'noboxes')
     * @return     int                    1 if OK, 0 if KO
     */
    public function remove($options = '')
    {
        $sql = array();

        return $this->_remove($sql);
    }


    /**
     *        \brief        Create tables, keys and data required by module
     *                    Files llx_table1.sql, llx_table1.key.sql llx_data.sql with create table, create keys
     *                    and create data commands must be stored in directory /mymodule/sql/
     *                    This function is called by this->init.
     *        \return        int        <=0 if KO, >0 if OK
     */
    public function load_tables()
    {
        return $this->_load_tables('/reports/sql/');
    }
}
