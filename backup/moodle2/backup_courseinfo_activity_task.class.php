<?php
defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/mod/courseinfo/backup/moodle2/backup_courseinfo_stepslib.php');

class backup_courseinfo_activity_task extends backup_activity_task {
    protected function define_my_settings() {
    }

    protected function define_my_steps() {
        $this->add_step(new backup_courseinfo_activity_structure_step('courseinfo_structure', 'courseinfo.xml'));
    }

    public static function encode_content_links($content) {
        return $content;
    }
}
