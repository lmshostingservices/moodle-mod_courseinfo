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

/**
 * Restore courseinfo activity structure step.
 */
class restore_courseinfo_activity_structure_step extends restore_activity_structure_step {
    /**
     * Define structure.
     */
    protected function define_structure() {
        $paths = [];
        $paths[] = new restore_path_element('courseinfo', '/activity/courseinfo');
        // The activity wrapper must be prepared too, or the activity context is never mapped
        // and restoring calendar events, files or a whole course fails with unknown_context_mapping.
        return $this->prepare_activity_structure($paths);
    }

    /**
     * Process courseinfo.
     */
    protected function process_courseinfo($data) {
        global $DB;
        $data = (object)$data;
        $oldid = $data->id;
        $data->course = $this->get_courseid();
        $data->timecreated = time();
        $data->timemodified = time();
        $newitemid = $DB->insert_record('courseinfo', $data);
        $this->apply_activity_instance($newitemid);
    }
}
