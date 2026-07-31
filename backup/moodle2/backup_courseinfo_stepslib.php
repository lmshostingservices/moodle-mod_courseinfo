<?php
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
