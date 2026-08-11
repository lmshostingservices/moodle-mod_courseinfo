<?php
/**
 * mod_courseinfo file.
 *
 * @package    mod_courseinfo
 * @copyright  2026 LMS-Labs
 * @license    http://www.gnu.org/licenses/gpl-3.0.html GNU GPL v3 or later
 */

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

require_once($CFG->dirroot . '/course/moodleform_mod.php');

class mod_courseinfo_mod_form extends moodleform_mod {
    public function definition() {
        global $PAGE;

        $mform = $this->_form;

        $mform->addElement('header', 'general', get_string('general', 'form'));

        $mform->addElement('text', 'name', get_string('courseinfoname', 'courseinfo'), array('size' => '64'));
        $mform->setType('name', PARAM_TEXT);
        $mform->addRule('name', null, 'required', null, 'client');
        $mform->addRule('name', get_string('maximumchars', '', 255), 'maxlength', 255, 'client');
        $mform->addHelpButton('name', 'courseinfoname', 'courseinfo');

        $this->standard_intro_elements();

        $mform->addElement('header', 'unitofcompetency', get_string('courseinfofieldset', 'courseinfo'));

        $mform->addElement('advcheckbox', 'enablevol', get_string('enablevol', 'courseinfo'), get_string('enablevol_desc', 'courseinfo'));
        $mform->setDefault('enablevol', 0);
        $mform->addHelpButton('enablevol', 'enablevol', 'courseinfo');

        $mform->addElement('text', 'unitcode', get_string('unitcode', 'courseinfo'), array('size' => '20'));
        $mform->setType('unitcode', PARAM_ALPHANUMEXT);
        $mform->addHelpButton('unitcode', 'unitcode', 'courseinfo');
        $mform->hideIf('unitcode', 'enablevol', 'notchecked');

        $mform->addElement('text', 'unittitle', get_string('unittitle', 'courseinfo'), array('size' => '64'));
        $mform->setType('unittitle', PARAM_TEXT);
        $mform->addHelpButton('unittitle', 'unittitle', 'courseinfo');
        $mform->hideIf('unittitle', 'enablevol', 'notchecked');

        $mform->addElement('text', 'nominalhours', get_string('nominalhours', 'courseinfo'), array('size' => '10'));
        $mform->setType('nominalhours', PARAM_INT);
        $mform->addHelpButton('nominalhours', 'nominalhours', 'courseinfo');
        $mform->setDefault('nominalhours', 0);
        $mform->hideIf('nominalhours', 'enablevol', 'notchecked');

        $mform->addElement('advcheckbox', 'enableasqa', get_string('enableasqa', 'courseinfo'), get_string('enableasqa_desc', 'courseinfo'));
        $mform->setDefault('enableasqa', 1);
        $mform->addHelpButton('enableasqa', 'enableasqa', 'courseinfo');

        $apiurl = $this->get_api_url();
        $PAGE->requires->js_call_amd('mod_courseinfo/form_autofill', 'init', [$apiurl]);

        $this->standard_coursemodule_elements();
        $this->add_action_buttons();
    }

    public function data_preprocessing(&$default_values) {
        if (!empty($default_values['unitcode']) && empty($default_values['nominalhours'])) {
            $default_values['nominalhours'] = $this->lookup_nominal_hours($default_values['unitcode']);
        }
    }

    private function lookup_nominal_hours($unitcode) {
        global $DB;
        try {
            if ($DB->get_manager()->table_exists('rtocompliance_units')) {
                $unit = $DB->get_record('rtocompliance_units', array('unitcode' => $unitcode), 'nominalhours');
                if ($unit && !empty($unit->nominalhours)) {
                    return (int)$unit->nominalhours;
                }
            }
        } catch (\Exception $e) {
            // rtocompliance not installed — ignore.
        }
        return 0;
    }

    private function get_api_url() {
        global $DB;
        $apiurl = 'https://lms-labs.com';
        try {
            if ($DB->get_manager()->table_exists('local_aiconfig')) {
                $aiconfig = $DB->get_record('local_aiconfig', array('id' => 1));
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
