<?php
defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/mod/courseinfo/backup/moodle2/restore_courseinfo_stepslib.php');

class restore_courseinfo_activity_task extends restore_activity_task {
    protected function define_my_settings() {
    }

    protected function define_my_steps() {
        $this->add_step(new restore_courseinfo_activity_structure_step('courseinfo_structure', 'courseinfo.xml'));
    }

    public static function define_decode_contents() {
        return array();
    }

    public static function define_decode_rules() {
        return array();
    }
}
