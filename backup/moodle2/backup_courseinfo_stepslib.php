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
 * mod_courseinfo file.
 *
 * @package    mod_courseinfo
 * @copyright  2026 LMS-Labs
 * @license    http://www.gnu.org/licenses/gpl-3.0.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

class backup_courseinfo_activity_structure_step extends backup_activity_structure_step {
    protected function define_structure() {
        $courseinfo = new backup_nested_element('courseinfo', array('id'), array(
            'name', 'intro', 'introformat', 'unitcode', 'unittitle', 'nominalhours',
            'generatedhtml', 'generatedjson', 'scanneddata', 'volumebreakdown',
            'lastgenerated', 'timecreated', 'timemodified'
        ));
        $courseinfo->set_source_table('courseinfo', array('id' => backup::VAR_ACTIVITYID));
        return $this->prepare_activity_structure($courseinfo);
    }
}
