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

$string['modulename'] = 'AI Course Information';
$string['modulenameplural'] = 'AI Course Information';
$string['modulename_help'] = 'The AI Course Information activity generates ASQA 2025-compliant course information documents by scanning all activities in the course and using AI to create step-by-step student guides with Volume of Learning compliance checking.';
$string['pluginname'] = 'AI Course Information';
$string['pluginadministration'] = 'AI Course Information administration';

$string['courseinfofieldset'] = 'Course Information Settings';
$string['courseinfoname'] = 'Activity name';
$string['courseinfoname_help'] = 'The name of this Course Information activity.';

$string['enableasqa'] = 'Enable ASQA Compliance Sections';
$string['enableasqa_desc'] = 'Include ASQA compliance sections (RPL &amp; Credit Transfer, Complaints &amp; Appeals) in the generated document. Disable for non-accredited or corporate courses.';
$string['enableasqa_help'] = 'When enabled (default), the generated course information document includes ASQA 2025-compliant sections for Recognition of Prior Learning and the complaints and appeals procedure — required for registered RTOs. Uncheck this for non-accredited professional development, corporate training, or any course that does not require regulatory compliance language.';
$string['enablevol'] = 'Enable Volume of Learning';
$string['enablevol_desc'] = 'Enable Volume of Learning tracking for vocational (accredited) courses. When enabled, you can enter a unit code, title and nominal hours to track compliance.';
$string['enablevol_help'] = 'Check this box for accredited/vocational courses that require Volume of Learning compliance tracking. Leave unchecked for non-accredited courses.';

$string['unitcode'] = 'Unit of Competency Code';
$string['unitcode_help'] = 'Enter the national unit of competency code (e.g. BSBWHS211). This will be used to look up nominal hours from the NCVER database.';
$string['unittitle'] = 'Unit of Competency Title';
$string['unittitle_help'] = 'Enter the full title of the unit of competency.';
$string['nominalhours'] = 'Nominal Hours';
$string['nominalhours_help'] = 'The nationally agreed nominal hours for this unit of competency. If connected to the RTO Compliance plugin, this will be auto-populated. Otherwise enter manually from the NCVER data or training.gov.au.';

$string['generatecourseinfo'] = 'Generate Course Information';
$string['regeneratecourseinfo'] = 'Regenerate Course Information';
$string['generating'] = 'Generating course information...';
$string['scanningactivities'] = 'Scanning course activities...';
$string['generationsuccess'] = 'Course information generated successfully.';
$string['generationfailed'] = 'Failed to generate course information.';
$string['nocourseinfo'] = 'Course information has not been generated yet. Use the buttons above to scan all course activities and generate the course information document.';

$string['volumeoflearning'] = 'Volume of Learning';
$string['volumeoflearning_help'] = 'The Volume of Learning represents the total amount of time a student is expected to spend on all learning activities for this unit, including supervised training, self-directed study, assessment, and workplace practice.';
$string['volbreakdown'] = 'Volume of Learning Breakdown';
$string['totalhours'] = 'Total Hours';
$string['nominalhoursrequired'] = 'Nominal Hours Required';
$string['compliancestatus'] = 'Compliance Status';
$string['compliant'] = 'Compliant';
$string['noncompliant'] = 'Non-Compliant';
$string['learninggap'] = 'Volume of learning gap: {$a} hours below the nominal hours requirement.';

$string['contentcreatorhours'] = 'AI Content Creator';
$string['aiactivitieshours'] = 'AI Learning Activities';
$string['essaymakerhours'] = 'AI Essay Maker';
$string['knowledgecheckhours'] = 'AI Knowledge Check';
$string['assignmenthours'] = 'Assignments';
$string['practicalassessmenthours'] = 'Practical Assessments';
$string['otherhours'] = 'Other Activities';
$string['selfdirectedhours'] = 'Self-Directed Study';

$string['stepbystepguide'] = 'Step-by-Step Course Completion Guide';
$string['estimatedtiming'] = 'Estimated Timing';
$string['step'] = 'Step';
$string['activity'] = 'Activity';
$string['description'] = 'Description';
$string['duration'] = 'Duration';
$string['hours'] = 'hours';
$string['minutes'] = 'minutes';
$string['printcourseinfo'] = 'Print Course Information';

$string['courseinfointro'] = 'Course Information';
$string['asqacompliance'] = 'ASQA 2025 Standards Compliance';
$string['deliverymode'] = 'Mode of Delivery';
$string['assessmentrequirements'] = 'Assessment Requirements';
$string['entryrequirements'] = 'Entry Requirements';
$string['supportservices'] = 'Support Services';
$string['rplinfo'] = 'Recognition of Prior Learning (RPL)';

$string['noactivities'] = 'No activities found in this course. Please add activities before generating the course information.';
$string['configerror'] = 'Plugin is not configured. Please contact your administrator.';
$string['creditsrequired'] = 'This generation requires {$a} credits.';
$string['insufficientcredits'] = 'Insufficient credits. You need {$a} credits to generate course information.';

$string['privacy:metadata'] = 'The AI Course Information plugin does not store any personal data.';

$string['eventcoursemoduleviewed'] = 'Course Information viewed';
$string['eventcourseinfoGenerated'] = 'Course Information generated';

$string['courseinfo:addinstance'] = 'Add a new AI Course Information activity';
$string['courseinfo:view'] = 'View AI Course Information';
$string['courseinfo:manage'] = 'Manage AI Course Information';
