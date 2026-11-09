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
 * Courseinfo supports.
 */
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

/**
 * Courseinfo add instance.
 */
function courseinfo_add_instance($data, $mform = null) {
    global $DB;
    courseinfo_pack_framework_fields($data);
    $data->timecreated = time();
    $data->timemodified = time();
    $id = $DB->insert_record('courseinfo', $data);
    return $id;
}

/**
 * Courseinfo update instance.
 */
function courseinfo_update_instance($data, $mform = null) {
    global $DB;
    courseinfo_pack_framework_fields($data);
    $data->id = $data->instance;
    $data->timemodified = time();
    $DB->update_record('courseinfo', $data);
    return true;
}

/**
 * Save framework-specific form controls in the single JSON frameworkdata field.
 *
 * The legacy VET columns remain the source of truth for VET activities.
 *
 * @param stdClass $data
 */
function courseinfo_pack_framework_fields($data) {
    $validframeworks = ['vet', 'au_curriculum', 'sg_moe', 'university', 'workplace', 'custom'];
    $framework = isset($data->framework) && in_array($data->framework, $validframeworks, true)
        ? $data->framework : 'vet';
    $fieldsets = [
        'au_curriculum' => ['learningarea', 'strand', 'substrand', 'contentdescriptor', 'yearlevel', 'achievementstandard'],
        'sg_moe' => ['syllabus', 'theme', 'learningoutcome', 'level', 'syllabuscode'],
        'university' => ['course', 'courselearningoutcome', 'creditpoints', 'graduateattributes'],
        'workplace' => ['program', 'requirement', 'role', 'policyreference'],
    ];
    $frameworkdata = [];
    if (isset($fieldsets[$framework])) {
        foreach ($fieldsets[$framework] as $key) {
            $value = isset($data->{'fw_' . $key}) ? clean_param($data->{'fw_' . $key}, PARAM_TEXT) : '';
            if ($value !== '') {
                $frameworkdata[$key] = $value;
            }
        }
    } else if ($framework === 'custom') {
        $levels = [];
        for ($level = 1; $level <= 4; $level++) {
            $label = isset($data->{'fw_customlabel' . $level})
                ? clean_param($data->{'fw_customlabel' . $level}, PARAM_TEXT) : '';
            $value = isset($data->{'fw_customvalue' . $level})
                ? clean_param($data->{'fw_customvalue' . $level}, PARAM_TEXT) : '';
            if ($label !== '' || $value !== '') {
                $levels[] = ['label' => $label, 'value' => $value];
            }
        }
        if ($levels) {
            $frameworkdata['customLevels'] = $levels;
        }
    }

    $data->framework = $framework;
    $data->frameworkdata = json_encode($frameworkdata);
    foreach (
        ['learningarea', 'strand', 'substrand', 'contentdescriptor', 'yearlevel',
        'achievementstandard', 'syllabus', 'theme', 'learningoutcome', 'level', 'syllabuscode',
        'course', 'courselearningoutcome', 'creditpoints', 'graduateattributes', 'program',
        'requirement', 'role', 'policyreference'] as $key
    ) {
        unset($data->{'fw_' . $key});
    }
    for ($level = 1; $level <= 4; $level++) {
        unset($data->{'fw_customlabel' . $level}, $data->{'fw_customvalue' . $level});
    }
}

/**
 * Courseinfo delete instance.
 */
function courseinfo_delete_instance($id) {
    global $DB;
    if (!$DB->record_exists('courseinfo', ['id' => $id])) {
        return false;
    }
    $DB->delete_records('courseinfo', ['id' => $id]);
    return true;
}

/**
 * Courseinfo view.
 */
function courseinfo_view($courseinfo, $course, $cm, $context) {
    $event = \mod_courseinfo\event\course_module_viewed::create([
        'objectid' => $courseinfo->id,
        'context' => $context,
    ]);
    $event->add_record_snapshot('course_modules', $cm);
    $event->add_record_snapshot('course', $course);
    $event->add_record_snapshot('courseinfo', $courseinfo);
    $event->trigger();

    $completion = new \completion_info($course);
    $completion->set_module_viewed($cm);
}

/**
 * Courseinfo get coursemodule info.
 */
function courseinfo_get_coursemodule_info($coursemodule) {
    global $DB;
    $courseinfo = $DB->get_record('courseinfo', ['id' => $coursemodule->instance], 'id, name, intro, introformat, unitcode');
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

/**
 * Courseinfo require manage.
 */
function courseinfo_require_manage($context) {
    if (has_capability('mod/courseinfo:manage', $context)) {
        return;
    }
    if (has_capability('moodle/course:manageactivities', $context)) {
        return;
    }
    require_capability('mod/courseinfo:manage', $context);
}
