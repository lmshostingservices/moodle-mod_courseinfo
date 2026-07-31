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

defined('MOODLE_INTERNAL') || die();

function courseinfo_supports($feature) {
    switch ($feature) {
        case FEATURE_MOD_INTRO:
            return true;
        case FEATURE_SHOW_DESCRIPTION:
            return true;
        case FEATURE_BACKUP_MOODLE2:
            return true;
        case FEATURE_COMPLETION_TRACKS_VIEWS:
            return true;
        default:
            return null;
    }
}

function courseinfo_add_instance($data, $mform = null) {
    global $DB;
    $data->timecreated = time();
    $data->timemodified = time();
    $id = $DB->insert_record('courseinfo', $data);
    return $id;
}

function courseinfo_update_instance($data, $mform = null) {
    global $DB;
    $data->id = $data->instance;
    $data->timemodified = time();
    $DB->update_record('courseinfo', $data);
    return true;
}

function courseinfo_delete_instance($id) {
    global $DB;
    if (!$DB->record_exists('courseinfo', array('id' => $id))) {
        return false;
    }
    $DB->delete_records('courseinfo', array('id' => $id));
    return true;
}

function courseinfo_view($courseinfo, $course, $cm, $context) {
    $event = \mod_courseinfo\event\course_module_viewed::create(array(
        'objectid' => $courseinfo->id,
        'context' => $context,
    ));
    $event->add_record_snapshot('course_modules', $cm);
    $event->add_record_snapshot('course', $course);
    $event->add_record_snapshot('courseinfo', $courseinfo);
    $event->trigger();

    $completion = new \completion_info($course);
    $completion->set_module_viewed($cm);
}

function courseinfo_get_coursemodule_info($coursemodule) {
    global $DB;
    $courseinfo = $DB->get_record('courseinfo', array('id' => $coursemodule->instance), 'id, name, intro, introformat, unitcode');
    if (!$courseinfo) {
        return null;
    }
    $info = new cached_cm_info();
    $info->name = $courseinfo->name;
    if ($courseinfo->intro) {
        $info->content = format_module_intro('courseinfo', $courseinfo, $coursemodule->id, false);
    }
    return $info;
}

function courseinfo_require_manage($context) {
    if (has_capability('mod/courseinfo:manage', $context)) {
        return;
    }
    if (has_capability('moodle/course:manageactivities', $context)) {
        return;
    }
    require_capability('mod/courseinfo:manage', $context);
}
