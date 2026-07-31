<?php
defined('MOODLE_INTERNAL') || die();

class restore_courseinfo_activity_structure_step extends restore_activity_structure_step {
    protected function define_structure() {
        $paths = array();
        $paths[] = new restore_path_element('courseinfo', '/activity/courseinfo');
        return $paths;
    }

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
