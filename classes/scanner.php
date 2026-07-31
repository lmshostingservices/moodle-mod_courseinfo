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

namespace mod_courseinfo;

defined('MOODLE_INTERNAL') || die();

class scanner {

    protected $courseid;
    protected $course;

    public function __construct($courseid) {
        global $DB;
        $this->courseid = $courseid;
        $this->course = $DB->get_record('course', array('id' => $courseid), '*', MUST_EXIST);
    }

    public function scan_all_activities() {
        $result = array(
            'course' => array(
                'id' => $this->course->id,
                'fullname' => $this->course->fullname,
                'shortname' => $this->course->shortname,
                'summary' => strip_tags($this->course->summary),
                'format' => $this->course->format,
            ),
            'activities' => array(),
            'summary' => array(
                'total_activities' => 0,
                'by_type' => array(),
            ),
        );

        $modinfo = get_fast_modinfo($this->course);
        $sections = $modinfo->get_section_info_all();

        foreach ($sections as $section) {
            // Skip sections that are hidden from students entirely.
            if (!$section->visible) {
                continue;
            }
            if (empty($modinfo->sections[$section->section])) {
                continue;
            }
            foreach ($modinfo->sections[$section->section] as $cmid) {
                $cm = $modinfo->cms[$cmid];
                if (!$cm->uservisible || $cm->deletioninprogress) {
                    continue;
                }
                if ($cm->modname === 'courseinfo') {
                    continue;
                }

                $activity = $this->scan_activity($cm, $section);
                if ($activity) {
                    $result['activities'][] = $activity;
                    $result['summary']['total_activities']++;
                    $type = $activity['type'];
                    if (!isset($result['summary']['by_type'][$type])) {
                        $result['summary']['by_type'][$type] = 0;
                    }
                    $result['summary']['by_type'][$type]++;
                }
            }
        }

        return $result;
    }

    protected function scan_activity($cm, $section) {
        $base = array(
            'cmid' => $cm->id,
            'modname' => $cm->modname,
            'name' => format_string($cm->name),
            'section_name' => get_section_name($this->course, $section),
            'section_num' => $section->section,
            'visible' => $cm->visible,
        );

        switch ($cm->modname) {
            case 'contentcreator':
                return $this->scan_contentcreator($cm, $base);
            case 'aiactivities':
                return $this->scan_aiactivities($cm, $base);
            case 'aiknowledgecheck':
                return $this->scan_aiknowledgecheck($cm, $base);
            case 'assign':
                return $this->scan_assign($cm, $base);
            case 'practicalassessment':
                return $this->scan_practicalassessment($cm, $base);
            case 'quiz':
                return $this->scan_quiz($cm, $base);
            case 'forum':
                return $this->scan_forum($cm, $base);
            case 'resource':
            case 'url':
            case 'page':
            case 'book':
                return $this->scan_resource($cm, $base);
            case 'aivideoactivity':
                return $this->scan_aivideoactivity($cm, $base);
            default:
                return $this->scan_generic($cm, $base);
        }
    }

    protected function scan_contentcreator($cm, $base) {
        global $DB;
        $cc = $DB->get_record('contentcreator', array('id' => $cm->instance));
        if (!$cc) {
            return null;
        }

        $base['type'] = 'contentcreator';
        $base['description'] = 'Interactive AI-generated learning content with slides, activities and knowledge checks';
        $base['estimated_minutes'] = 0;

        $topics = array();
        $totalSlides = 0;

        if (!empty($cc->manifestjson)) {
            $manifest = json_decode($cc->manifestjson, true);
            if ($manifest && isset($manifest['topics'])) {
                foreach ($manifest['topics'] as $topic) {
                    $slideCount = 0;
                    if (isset($topic['slides'])) {
                        $slideCount = count($topic['slides']);
                    } elseif (isset($topic['subtopics'])) {
                        foreach ($topic['subtopics'] as $sub) {
                            $slideCount += isset($sub['slides']) ? count($sub['slides']) : 1;
                        }
                    }
                    $totalSlides += $slideCount;

                    $subtopicCount = isset($topic['subtopics']) ? count($topic['subtopics']) : 0;

                    $topics[] = array(
                        'title' => $topic['title'] ?? $topic['name'] ?? 'Untitled',
                        'subtopics' => $subtopicCount,
                        'slides' => $slideCount,
                    );
                }
            }
        }

        $avgMinPerSlide = 20;
        $base['estimated_minutes'] = max(20, round($totalSlides * $avgMinPerSlide));
        $base['topics'] = $topics;
        $base['total_slides'] = $totalSlides;
        $base['total_topics'] = count($topics);

        return $base;
    }

    protected function scan_aiactivities($cm, $base) {
        global $DB;
        $aa = $DB->get_record('aiactivities', array('id' => $cm->instance));
        if (!$aa) {
            return null;
        }

        $base['type'] = 'aiactivities';
        $base['description'] = 'AI-generated interactive learning activities and scenarios';

        // activitiesjson stores a direct JSON array of activity objects.
        // Fall back to activitycount (the requested count) if content not yet generated.
        $scenarioCount = 0;
        if (!empty($aa->activitiesjson)) {
            $content = json_decode($aa->activitiesjson, true);
            if (is_array($content)) {
                // Direct array of activity objects.
                $scenarioCount = count($content);
            }
        }
        // If still zero (not yet generated), use the configured activitycount setting.
        if ($scenarioCount === 0 && !empty($aa->activitycount)) {
            $scenarioCount = (int)$aa->activitycount;
        }

        $base['scenario_count'] = $scenarioCount;
        $base['estimated_minutes'] = max(2, $scenarioCount * 2);

        return $base;
    }

    protected function scan_aiknowledgecheck($cm, $base) {
        global $DB;
        $kc = $DB->get_record('aiknowledgecheck', array('id' => $cm->instance));
        if (!$kc) {
            return null;
        }

        $base['type'] = 'knowledgecheck';
        $base['description'] = 'AI-generated knowledge check assessment';

        $questionCount = 0;
        if (!empty($kc->generatedcontent)) {
            $content = json_decode($kc->generatedcontent, true);
            if ($content && isset($content['questions'])) {
                $questionCount = count($content['questions']);
            }
        }
        if ($questionCount === 0 && !empty($kc->questioncount)) {
            $questionCount = (int)$kc->questioncount;
        }

        $base['question_count'] = $questionCount;
        $base['estimated_minutes'] = max(2, $questionCount * 2);
        $base['max_attempts'] = isset($kc->maxattempts) ? (int)$kc->maxattempts : 0;
        $base['passing_grade'] = isset($kc->passinggrade) ? (int)$kc->passinggrade : 0;

        return $base;
    }

    protected function scan_assign($cm, $base) {
        global $DB;
        $assign = $DB->get_record('assign', array('id' => $cm->instance));
        if (!$assign) {
            return null;
        }

        $base['type'] = 'assignment';
        $base['description'] = strip_tags($assign->intro ?? '');
        $base['instructions'] = strip_tags($assign->intro ?? '');

        $hasSubmission   = false;
        $submissionTypes = array();
        $plugins = $DB->get_records('assign_plugin_config', array('assignment' => $assign->id, 'name' => 'enabled', 'value' => '1'));
        foreach ($plugins as $plugin) {
            if ($plugin->subtype === 'assignsubmission') {
                $hasSubmission     = true;
                $submissionTypes[] = $plugin->plugin;
            }
        }

        $isEssayGrader = false;
        if ($DB->get_manager()->table_exists('assignfeedback_aipdf')) {
            $isEssayGrader = $DB->record_exists('assign_plugin_config',
                array('assignment' => $assign->id, 'plugin' => 'aipdf', 'name' => 'enabled', 'value' => '1'));
        }

        $base['has_submission'] = $hasSubmission;
        $base['submission_types'] = $submissionTypes;
        $base['is_essay_grader'] = $isEssayGrader;
        $base['max_attempts'] = isset($assign->maxattempts) ? (int)$assign->maxattempts : -1;
        $base['due_date'] = !empty($assign->duedate) ? date('d M Y', $assign->duedate) : null;
        $base['estimated_minutes'] = $isEssayGrader ? 60 : 45;

        return $base;
    }

    protected function scan_practicalassessment($cm, $base) {
        global $DB;
        $pa = $DB->get_record('practicalassessment', array('id' => $cm->instance));
        if (!$pa) {
            return null;
        }

        $base['type'] = 'practicalassessment';
        $base['description'] = 'Practical assessment activity';
        $base['estimated_minutes'] = 60;

        if (!empty($pa->generatedcontent)) {
            $content = json_decode($pa->generatedcontent, true);
            if ($content && isset($content['tasks'])) {
                $base['task_count'] = count($content['tasks']);
                $base['estimated_minutes'] = max(30, count($content['tasks']) * 15);
            }
        }

        return $base;
    }

    protected function scan_quiz($cm, $base) {
        global $DB;
        $quiz = $DB->get_record('quiz', array('id' => $cm->instance));
        if (!$quiz) {
            return null;
        }

        $base['type'] = 'quiz';
        $base['description'] = strip_tags($quiz->intro ?? 'Quiz assessment');

        $questionCount = $DB->count_records('quiz_slots', array('quizid' => $quiz->id));
        $base['question_count'] = $questionCount;
        $base['max_attempts'] = isset($quiz->attempts) ? (int)$quiz->attempts : 0;
        $base['time_limit'] = !empty($quiz->timelimit) ? round($quiz->timelimit / 60) : null;
        $base['passing_grade'] = !empty($quiz->gradepass) ? (float)$quiz->gradepass : 0;

        if (!empty($quiz->timelimit)) {
            $base['estimated_minutes'] = round($quiz->timelimit / 60);
        } else {
            $base['estimated_minutes'] = max(10, $questionCount * 2);
        }

        return $base;
    }

    protected function scan_forum($cm, $base) {
        global $DB;
        $forum = $DB->get_record('forum', array('id' => $cm->instance));
        if (!$forum) {
            return null;
        }

        $base['type'] = 'forum';
        $base['description'] = 'Discussion forum — ' . strip_tags($forum->intro ?? '');
        $base['estimated_minutes'] = 20;

        return $base;
    }

    protected function scan_resource($cm, $base) {
        $base['type'] = 'resource';
        $typeLabels = array(
            'resource' => 'File resource',
            'url' => 'External URL resource',
            'page' => 'Page resource',
            'book' => 'Book resource',
        );
        $base['description'] = $typeLabels[$cm->modname] ?? 'Learning resource';
        $base['estimated_minutes'] = 10;

        return $base;
    }

    protected function scan_aivideoactivity($cm, $base) {
        global $DB;
        $va = $DB->get_record('aivideoactivity', array('id' => $cm->instance));

        $base['type'] = 'videoactivity';
        $base['description'] = 'AI video activity';
        $base['estimated_minutes'] = 2;

        return $base;
    }

    protected function scan_generic($cm, $base) {
        $base['type'] = 'other';
        $base['description'] = 'Learning activity (' . $cm->modname . ')';
        $base['estimated_minutes'] = 15;

        return $base;
    }

    protected function scan_essaymaker_in_course() {
        global $DB;
        try {
            if (!$DB->get_manager()->table_exists('local_essaymaker_essays')) {
                return array();
            }
            $essays = $DB->get_records('local_essaymaker_essays', array('courseid' => $this->courseid));
            $result = array();
            foreach ($essays as $essay) {
                $questionCount = 0;
                if (!empty($essay->generatedcontent)) {
                    $content = json_decode($essay->generatedcontent, true);
                    if ($content && isset($content['questions'])) {
                        $questionCount = count($content['questions']);
                    }
                }
                $result[] = array(
                    'type' => 'essaymaker',
                    'name' => $essay->name ?? 'Essay Questions',
                    'description' => 'AI-generated essay questions for knowledge evidence',
                    'question_count' => $questionCount,
                    'estimated_minutes' => max(2, $questionCount * 2),
                );
            }
            return $result;
        } catch (\Exception $e) {
            return array();
        }
    }

    public function scan_course_environment() {
        global $DB, $CFG, $SITE;

        $env = array(
            'ai_course_format' => false,
            'ai_tutor_enabled' => false,
            'ai_support_installed' => false,
            'site_name' => '',
            'site_phone' => '',
            'site_email' => '',
            'site_url' => $CFG->wwwroot ?? '',
            'installed_plugins' => array(),
        );

        $env['ai_course_format'] = ($this->course->format === 'aicourseformat');

        if ($env['ai_course_format']) {
            try {
                // AI Tutor is a site-wide admin setting in format_aicourse (not per-course).
                // Read from config_plugins where plugin='format_aicourse', name='enabletutor'.
                $tutorVal = get_config('format_aicourse', 'enabletutor');
                if ($tutorVal !== false && $tutorVal !== '0' && !empty($tutorVal)) {
                    $env['ai_tutor_enabled'] = true;
                }
            } catch (\Exception $e) {
                // format_aicourse not installed — leave ai_tutor_enabled as false
            }
        }

        try {
            $blocks = $DB->get_records_sql(
                "SELECT bi.id, bi.blockname FROM {block_instances} bi
                 JOIN {context} ctx ON bi.parentcontextid = ctx.id
                 WHERE ctx.contextlevel = 50 AND ctx.instanceid = :courseid",
                array('courseid' => $this->courseid)
            );
            foreach ($blocks as $block) {
                if ($block->blockname === 'aiplugin_nav') {
                    $env['ai_support_installed'] = true;
                    break;
                }
            }
        } catch (\Exception $e) {
            // blocks query failed
        }

        try {
            $sysBlocks = $DB->get_records_sql(
                "SELECT bi.id, bi.blockname FROM {block_instances} bi
                 JOIN {context} ctx ON bi.parentcontextid = ctx.id
                 WHERE ctx.contextlevel = 10 AND bi.blockname = 'aiplugin_nav'"
            );
            if (!empty($sysBlocks)) {
                $env['ai_support_installed'] = true;
            }
        } catch (\Exception $e) {
            // system blocks query failed
        }

        $env['site_name'] = isset($SITE->fullname) ? $SITE->fullname : ($DB->get_field('course', 'fullname', array('id' => SITEID)) ?: '');

        $supportEmail = get_config('moodle', 'supportemail');
        if (empty($supportEmail)) {
            $supportEmail = $CFG->supportemail ?? '';
        }
        if (empty($supportEmail)) {
            $supportEmail = get_config('moodle', 'noreplyaddress') ?? '';
        }
        $env['site_email'] = $supportEmail;

        $supportPhone = get_config('moodle', 'supportphone');
        if (empty($supportPhone)) {
            $supportPhone = '';
        }
        $env['site_phone'] = $supportPhone;

        $pluginChecks = array(
            'mod_contentcreator' => 'AI Content Creator',
            'mod_aiactivities' => 'AI Learning Activities',
            'mod_aiknowledgecheck' => 'AI Knowledge Check',
            'mod_aivideoactivity' => 'AI Video Activity',
            'local_essaymaker' => 'AI Essay Maker',
            'mod_practicalassessment' => 'AI Practical Assessment',
            'local_moodlesupport' => 'AI Support',
            'mod_learningmapping' => 'AI Mapping',
        );

        foreach ($pluginChecks as $component => $label) {
            $version = get_config($component, 'version');
            if ($version) {
                $env['installed_plugins'][] = $label;
            }
        }

        return $env;
    }

    public function scan_complete() {
        $result = $this->scan_all_activities();

        $essayItems = $this->scan_essaymaker_in_course();
        foreach ($essayItems as $item) {
            $result['activities'][] = $item;
            $result['summary']['total_activities']++;
            if (!isset($result['summary']['by_type']['essaymaker'])) {
                $result['summary']['by_type']['essaymaker'] = 0;
            }
            $result['summary']['by_type']['essaymaker']++;
        }

        $result['environment'] = $this->scan_course_environment();

        return $result;
    }
}
