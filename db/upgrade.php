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
 * Upgrade script for mod_courseinfo.
 *
 * @package    mod_courseinfo
 * @copyright  2026 AI Grader
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();


/**
 * Xmldb courseinfo upgrade.
 */
function xmldb_courseinfo_upgrade($oldversion) {
    global $DB;
    if ($oldversion < 2026072300) {
        upgrade_mod_savepoint(true, 2026072300, 'courseinfo');
    }
    if ($oldversion < 2026110501) {
        $dbman = $DB->get_manager();
        $table = new xmldb_table('courseinfo');
        $framework = new xmldb_field('framework', XMLDB_TYPE_CHAR, '32', null, XMLDB_NOTNULL, null, 'vet', 'enableasqa');
        if (!$dbman->field_exists($table, $framework)) {
            $dbman->add_field($table, $framework);
        }
        $frameworkdata = new xmldb_field('frameworkdata', XMLDB_TYPE_TEXT, null, null, null, null, null, 'framework');
        if (!$dbman->field_exists($table, $frameworkdata)) {
            $dbman->add_field($table, $frameworkdata);
        }
        upgrade_mod_savepoint(true, 2026110501, 'courseinfo');
    }
    return true;
}
