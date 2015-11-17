<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Provides code to be executed during the module installation
 *
 * This file replaces the legacy STATEMENTS section in db/install.xml,
 * lib.php/modulename_install() post installation hook and partially defaults.php.
 *
 * @package    mod_scavengerhunt
 * @copyright  2015 Your Name <your@email.adress>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Post installation procedure
 *
 * @see upgrade_plugins_modules()
 */
function xmldb_scavengerhunt_install() {
    install_geometry_columns();
}

/**
 * Install, if needed, geometry columns in the database.
 * @global type $DB
 * @throws plugin_defective_exception
 */
function install_geometry_columns() {
    global $DB, $CFG;
    // If the database is initialized skips this function.
    if (get_config('mod_scavengerhunt', 'geometrysupport') == true) {
        return;
    }

    $dbman = $DB->get_manager();
    $table1 = new xmldb_table('current_scavengerhunt');
    $table2 = new xmldb_table('scavengerhunt_riddle');
    //Compruebo si existe la base de datos con la tabla
    if ($dbman->table_exists($table1) && $dbman->table_exists($table2)) {
        /* @var $DB accesor to DB services. */
        $dbtype = $DB->get_dbfamily();
        switch ($dbtype) {
            case 'postgres':
                try {
                    // Check if postgis is installed in the database. Install extenssion if not.
                    $postgis = $DB->get_record_sql("select * from pg_extension where extname='postgis'");
                    if (count($postgis) === 0) {
                        $DB->change_database_structure('create extension postgis;');
                    }
                    // Create multipolygon. change_database_structure no permite poner la tabla entre corchetes
                    if (!$dbman->field_exists('scavengerhunt_riddle', 'geom')) {
                        $DB->change_database_structure('ALTER TABLE ' . $CFG->prefix . 'scavengerhunt_riddle ADD geom geometry(MULTIPOLYGON,4326) NOT NULL');
                    }
                    // Create points
                    if (!$dbman->field_exists('current_scavengerhunt', 'locations')) {
                        $DB->change_database_structure('ALTER TABLE ' . $CFG->prefix . 'current_scavengerhunt ADD locations geometry(POINT,4326) NOT NULL');
                    }
                } catch (ddl_change_structure_exception $ex) {
                    set_config('geometrysupport', false, 'mod_scavengerhunt');
                    throw new plugin_defective_exception('scavengerhunt',
                    'Misconfigured database ' . $dbtype . ' Must have geometry capabilities installed. Please install postgis.');
                }
                break;
            case 'mysql':
                //Create multipolygon. change_database_structure no permite poner la tabla entre corchetes
                $DB->change_database_structure('ALTER TABLE mdl_scavengerhunt_riddle ADD geom MULTIPOLYGON NOT NULL');
                //Create points
                $DB->change_database_structure('ALTER TABLE mdl_current_scavengerhunt ADD locations POINT NOT NULL');
                break;
            default:
                set_config('geometrysupport', false, 'mod_scavengerhunt');
                throw new plugin_defective_exception('scavengerhunt',
                'Uncompatible database ' . $dbtype . ' Must have geometry capabilities.');
        }
        set_config('geometrysupport', true, 'mod_scavengerhunt');
    }
}

/**
 * Post installation recovery procedure
 *
 * @see upgrade_plugins_modules()
 */
function xmldb_scavengerhunt_install_recovery() {
    install_geometry_columns();
}
