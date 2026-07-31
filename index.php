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

require_once('../../config.php');

$id = required_param('id', PARAM_INT);
$course = $DB->get_record('course', array('id' => $id), '*', MUST_EXIST);

require_course_login($course);

$PAGE->set_url('/mod/courseinfo/index.php', array('id' => $id));
$PAGE->set_title(format_string($course->fullname));
$PAGE->set_heading(format_string($course->fullname));
$PAGE->set_pagelayout('incourse');

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('modulenameplural', 'courseinfo'));

$courseinfos = get_all_instances_in_course('courseinfo', $course);
if (empty($courseinfos)) {
    notice(get_string('noactivities', 'courseinfo'), new moodle_url('/course/view.php', array('id' => $course->id)));
}

$table = new html_table();
$table->head = array(get_string('name'), get_string('unitcode', 'courseinfo'), get_string('nominalhours', 'courseinfo'));
$table->align = array('left', 'left', 'center');

foreach ($courseinfos as $ci) {
    $link = html_writer::link(new moodle_url('/mod/courseinfo/view.php', array('id' => $ci->coursemodule)), format_string($ci->name));
    $table->data[] = array($link, $ci->unitcode ?? '-', $ci->nominalhours ?? '-');
}

echo html_writer::table($table);
echo $OUTPUT->footer();
