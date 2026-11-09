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

require_once($CFG->dirroot . '/course/moodleform_mod.php');

/**
 * Mod courseinfo mod form.
 */
class mod_courseinfo_mod_form extends moodleform_mod {
    /**
     * Definition.
     */
    public function definition() {
        global $PAGE;

        $mform = $this->_form;

        $mform->addElement('header', 'general', get_string('general', 'form'));

        $mform->addElement('text', 'name', get_string('courseinfoname', 'courseinfo'), ['size' => '64']);
        $mform->setType('name', PARAM_TEXT);
        $mform->addRule('name', null, 'required', null, 'client');
        $mform->addRule('name', get_string('maximumchars', '', 255), 'maxlength', 255, 'client');
        $mform->addHelpButton('name', 'courseinfoname', 'courseinfo');

        $this->standard_intro_elements();

        $frameworks = [
            'vet' => get_string('frameworkvet', 'courseinfo'),
            'au_curriculum' => get_string('frameworkaucurriculum', 'courseinfo'),
            'sg_moe' => get_string('frameworksgmoe', 'courseinfo'),
            'university' => get_string('frameworkuniversity', 'courseinfo'),
            'workplace' => get_string('frameworkworkplace', 'courseinfo'),
            'custom' => get_string('frameworkcustom', 'courseinfo'),
        ];
        $mform->addElement('select', 'framework', get_string('framework', 'courseinfo'), $frameworks);
        $mform->setType('framework', PARAM_ALPHANUMEXT);
        $mform->setDefault('framework', 'vet');
        $mform->addHelpButton('framework', 'framework', 'courseinfo');
        $mform->addElement('hidden', 'frameworkdata', '');
        $mform->setType('frameworkdata', PARAM_RAW); // pipeline-ignore: PARAM_RAW — preserve hidden framework JSON; decoded and validated before use.

        $frameworkfields = [
            'au_curriculum' => [
                'learningarea' => 'learningarea',
                'strand' => 'strand',
                'substrand' => 'substrand',
                'contentdescriptor' => 'contentdescriptor',
                'yearlevel' => 'yearlevel',
                'achievementstandard' => 'achievementstandard',
            ],
            'sg_moe' => [
                'syllabus' => 'syllabus',
                'theme' => 'theme',
                'learningoutcome' => 'learningoutcome',
                'level' => 'level',
                'syllabuscode' => 'syllabuscode',
            ],
            'university' => [
                'course' => 'course',
                'courselearningoutcome' => 'courselearningoutcome',
                'creditpoints' => 'creditpoints',
                'graduateattributes' => 'graduateattributes',
            ],
            'workplace' => [
                'program' => 'program',
                'requirement' => 'requirement',
                'role' => 'role',
                'policyreference' => 'policyreference',
            ],
        ];
        foreach ($frameworkfields as $framework => $fields) {
            foreach ($fields as $field => $stringkey) {
                $mform->addElement('text', 'fw_' . $field, get_string($stringkey, 'courseinfo'), ['size' => '64']);
                $mform->setType('fw_' . $field, PARAM_TEXT);
                $mform->hideIf('fw_' . $field, 'framework', 'neq', $framework);
            }
        }
        for ($level = 1; $level <= 4; $level++) {
            $mform->addElement('text', 'fw_customlabel' . $level, get_string('customlevellabel', 'courseinfo', $level), ['size' => '30']);
            $mform->setType('fw_customlabel' . $level, PARAM_TEXT);
            $mform->hideIf('fw_customlabel' . $level, 'framework', 'neq', 'custom');
            $mform->addElement('text', 'fw_customvalue' . $level, get_string('customlevelvalue', 'courseinfo', $level), ['size' => '64']);
            $mform->setType('fw_customvalue' . $level, PARAM_TEXT);
            $mform->hideIf('fw_customvalue' . $level, 'framework', 'neq', 'custom');
        }

        $mform->addElement('header', 'unitofcompetency', get_string('courseinfofieldset', 'courseinfo'));

        $mform->addElement('advcheckbox', 'enablevol', get_string('enablevol', 'courseinfo'), get_string('enablevol_desc', 'courseinfo'));
        $mform->setDefault('enablevol', 0);
        $mform->addHelpButton('enablevol', 'enablevol', 'courseinfo');
        $mform->hideIf('enablevol', 'framework', 'neq', 'vet');

        $mform->addElement('text', 'unitcode', get_string('unitcode', 'courseinfo'), ['size' => '20']);
        $mform->setType('unitcode', PARAM_ALPHANUMEXT);
        $mform->addHelpButton('unitcode', 'unitcode', 'courseinfo');
        $mform->hideIf('unitcode', 'enablevol', 'notchecked');
        $mform->hideIf('unitcode', 'framework', 'neq', 'vet');

        $mform->addElement('text', 'unittitle', get_string('unittitle', 'courseinfo'), ['size' => '64']);
        $mform->setType('unittitle', PARAM_TEXT);
        $mform->addHelpButton('unittitle', 'unittitle', 'courseinfo');
        $mform->hideIf('unittitle', 'enablevol', 'notchecked');
        $mform->hideIf('unittitle', 'framework', 'neq', 'vet');

        $mform->addElement('text', 'nominalhours', get_string('nominalhours', 'courseinfo'), ['size' => '10']);
        $mform->setType('nominalhours', PARAM_INT);
        $mform->addHelpButton('nominalhours', 'nominalhours', 'courseinfo');
        $mform->setDefault('nominalhours', 0);
        $mform->hideIf('nominalhours', 'enablevol', 'notchecked');
        $mform->hideIf('nominalhours', 'framework', 'neq', 'vet');

        $mform->addElement('advcheckbox', 'enableasqa', get_string('enableasqa', 'courseinfo'), get_string('enableasqa_desc', 'courseinfo'));
        $mform->setDefault('enableasqa', 1);
        $mform->addHelpButton('enableasqa', 'enableasqa', 'courseinfo');
        $mform->hideIf('enableasqa', 'framework', 'neq', 'vet');

        $apiurl = $this->get_api_url();
        $PAGE->requires->js_call_amd('mod_courseinfo/form_autofill', 'init', [$apiurl]);

        $this->standard_coursemodule_elements();
        $this->add_action_buttons();
    }

    /**
     * Data preprocessing.
     */
    public function data_preprocessing(&$default_values) {
        $default_values['framework'] = $default_values['framework'] ?? 'vet';
        $frameworkdata = [];
        if (!empty($default_values['frameworkdata'])) {
            $decoded = json_decode($default_values['frameworkdata'], true);
            if (is_array($decoded)) {
                $frameworkdata = $decoded;
            }
        }
        foreach (
            ['learningarea', 'strand', 'substrand', 'contentdescriptor', 'yearlevel',
            'achievementstandard', 'syllabus', 'theme', 'learningoutcome', 'level', 'syllabuscode',
            'course', 'courselearningoutcome', 'creditpoints', 'graduateattributes', 'program',
            'requirement', 'role', 'policyreference'] as $key
        ) {
            $default_values['fw_' . $key] = $frameworkdata[$key] ?? '';
        }
        $customlevels = $frameworkdata['customLevels'] ?? [];
        for ($level = 1; $level <= 4; $level++) {
            $custom = $customlevels[$level - 1] ?? [];
            $default_values['fw_customlabel' . $level] = $custom['label'] ?? '';
            $default_values['fw_customvalue' . $level] = $custom['value'] ?? '';
        }
        if (!empty($default_values['unitcode']) && empty($default_values['nominalhours'])) {
            $default_values['nominalhours'] = $this->lookup_nominal_hours($default_values['unitcode']);
        }
    }

    /**
     * Lookup nominal hours.
     */
    private function lookup_nominal_hours($unitcode) {
        global $DB;
        try {
            if ($DB->get_manager()->table_exists('rtocompliance_units')) {
                $unit = $DB->get_record('rtocompliance_units', ['unitcode' => $unitcode], 'nominalhours');
                if ($unit && !empty($unit->nominalhours)) {
                    return (int)$unit->nominalhours;
                }
            }
        } catch (\Exception $e) {
            // rtocompliance not installed — ignore.
        }
        return 0;
    }

    /**
     * Get api url.
     */
    private function get_api_url() {
        global $DB;
        $apiurl = 'https://lms-labs.com';
        try {
            if ($DB->get_manager()->table_exists('local_aiconfig')) {
                $aiconfig = $DB->get_record('local_aiconfig', ['id' => 1]);
                if ($aiconfig && !empty($aiconfig->apiurl)) {
                    $apiurl = $aiconfig->apiurl;
                }
            }
        } catch (\Exception $e) {
            // local_aiconfig not installed — use default.
        }
        return $apiurl;
    }
}
