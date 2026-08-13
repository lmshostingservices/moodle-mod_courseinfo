<?php
defined('MOODLE_INTERNAL') || die();

function xmldb_courseinfo_upgrade($oldversion) {
    if ($oldversion < 2026072300) {
        upgrade_mod_savepoint(true, 2026072300, 'courseinfo');
    }
    return true;
}
