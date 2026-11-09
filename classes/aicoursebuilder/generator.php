<?php
// This file is part of Moodle - https://moodle.org/
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
// along with Moodle.  If not, see <https://www.gnu.org/licenses/>.

namespace mod_courseinfo\aicoursebuilder;

defined('MOODLE_INTERNAL') || die();

/**
 * AI Course Builder adapter for AI Course Information.
 *
 * @package    mod_courseinfo
 * @copyright  2026 LMS Labs
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class generator {
    private const COMPONENT = 'mod_courseinfo';
    private const PRICE_PER_GENERATION = 100;

    /**
     * Native estimate.
     */
    public static function native_estimate(array $brief): int {
        return self::PRICE_PER_GENERATION;
    }

    /**
     * Native generate.
     */
    public static function native_generate(int $cmid, array $brief): string {
        global $CFG, $DB;
        require_once($CFG->dirroot . '/mod/courseinfo/lib.php');
        require_once($CFG->dirroot . '/mod/courseinfo/classes/scanner.php');
        require_once($CFG->dirroot . '/mod/courseinfo/classes/manifest_storage.php');
        $cm = get_coursemodule_from_id('courseinfo', $cmid, 0, false, MUST_EXIST);
        $instance = $DB->get_record('courseinfo', ['id' => $cm->instance], '*', MUST_EXIST);
        $course = $DB->get_record('course', ['id' => $cm->course], '*', MUST_EXIST);

        // A retry after the native route completed must not charge again.
        if (!empty($instance->generatedhtml)) {
            return 'already-generated';
        }
        $enablevol = !empty($instance->enablevol);
        $scan = null;
        if (!empty($instance->scanneddata)) {
            $scan = json_decode(\mod_courseinfo\manifest_storage::decompress((string)$instance->scanneddata), true);
        }
        if (!is_array($scan)) {
            $scan = (new \mod_courseinfo\scanner((int)$course->id))->scan_complete();
            if ($enablevol && !empty($instance->nominalhours)) {
                $calculator = new \mod_courseinfo\volume_calculator((int)$instance->nominalhours, $scan['activities'] ?? []);
                $scan['volume'] = $calculator->calculate();
            }
        }
        $activities = $scan['activities'] ?? [];
        if (!$activities) {
            throw new \moodle_exception(
                'generationfailed',
                'local_aicoursebuilder',
                '',
                '',
                'Course Information must run after the lesson activities have been created.'
            );
        }
        $frameworkdata = json_decode((string)($instance->frameworkdata ?? ''), true);
        if (!is_array($frameworkdata)) {
            $frameworkdata = [];
        }
        $primarycolour = '#1a73e8';
        try {
            $themeconfig = \theme_config::load($CFG->theme);
            $primarycolour = $themeconfig->settings->brandcolor ?? $primarycolour;
        } catch (\Throwable $e) {
            // Retain the module's existing documented default when theme settings are absent.
        }
        $payload = [
            'courseName' => (string)$course->fullname,
            'courseShortName' => (string)$course->shortname,
            'courseSummary' => strip_tags((string)$course->summary),
            'framework' => (string)($instance->framework ?? 'vet'),
            'frameworkdata' => $frameworkdata,
            'unitCode' => $enablevol ? (string)($instance->unitcode ?? '') : '',
            'unitTitle' => $enablevol ? (string)($instance->unittitle ?? '') : '',
            'nominalHours' => $enablevol ? (int)($instance->nominalhours ?? 0) : 0,
            'enableVol' => $enablevol,
            'activities' => $activities,
            'volume' => $enablevol ? ($scan['volume'] ?? null) : null,
            'activitySummary' => $scan['summary'] ?? null,
            'environment' => $scan['environment'] ?? null,
            'primaryColour' => $primarycolour,
            'secondaryColour' => $primarycolour,
            'enableAsqa' => isset($instance->enableasqa) ? !empty($instance->enableasqa) : true,
            // The curriculum outcomes the teacher pasted into AI Course Builder for this course's unit.
            'learningOutcomes' => array_values(array_map('strval', (array)($frameworkdata['outcomes'] ?? (
                method_exists('\\local_aicoursebuilder\\local\\course_builder', 'unit_outcomes')
                    ? \local_aicoursebuilder\local\course_builder::unit_outcomes((int)$course->id) : []
            )))),
        ];
        $DB->update_record('courseinfo', (object)[
            'id' => $instance->id,
            'scanneddata' => \mod_courseinfo\manifest_storage::compress(json_encode($scan, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR)),
            'volumebreakdown' => \mod_courseinfo\manifest_storage::compress(json_encode($scan['volume'] ?? null, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR)),
            'timemodified' => time(),
        ]);
        $result = \local_aicoursebuilder\local\api_client::request_json(
            'POST',
            '/api/moodle/course-info/generate/start',
            $payload,
            20
        );
        if (empty($result['ok']) || empty($result['jobId'])) {
            throw new \moodle_exception('generationfailed', 'local_aicoursebuilder', '', '', 'Course Information did not return a job id.');
        }
        return clean_param((string)$result['jobId'], PARAM_ALPHANUMEXT);
    }

    /**
     * Native status.
     */
    public static function native_status(int $cmid, string $jobid): array {
        global $CFG, $DB;
        require_once($CFG->dirroot . '/mod/courseinfo/classes/manifest_storage.php');
        $cm = get_coursemodule_from_id('courseinfo', $cmid, 0, false, MUST_EXIST);
        $instance = $DB->get_record('courseinfo', ['id' => $cm->instance], '*', MUST_EXIST);
        if ($jobid === 'already-generated' && !empty($instance->generatedhtml)) {
            return ['status' => 'complete', 'retryable' => false, 'result' => ['saved' => true]];
        }
        $poll = \local_aicoursebuilder\local\api_client::request_json(
            'GET',
            '/api/jobs/' . rawurlencode($jobid),
            null,
            20
        );
        if (in_array(($poll['status'] ?? ''), ['pending', 'processing'], true)) {
            return ['status' => 'running', 'retryable' => false];
        }
        if (($poll['status'] ?? '') === 'error') {
            return ['status' => 'failed', 'retryable' => false, 'error' => clean_param((string)($poll['error'] ?? 'Course Information generation failed.'), PARAM_TEXT)];
        }
        $result = $poll['result'] ?? null;
        if (($poll['status'] ?? '') !== 'done' || !is_array($result) || empty($result['success']) || empty($result['html'])) {
            return ['status' => 'failed', 'retryable' => false, 'error' => clean_param((string)($result['error'] ?? 'Course Information returned no generated content.'), PARAM_TEXT)];
        }
        $scan = !empty($instance->scanneddata)
            ? json_decode(\mod_courseinfo\manifest_storage::decompress((string)$instance->scanneddata), true)
            : null;
        if (!is_array($scan)) {
            return ['status' => 'failed', 'retryable' => false, 'error' => 'The native course scan was not saved before generation.'];
        }
        $structured = $result['structured'] ?? ($result['data'] ?? []);
        $html = (string)$result['html'];
        $html = preg_replace('/<!DOCTYPE[^>]*>/i', '', $html);
        $html = preg_replace('/<html[^>]*>|<\/html>|<body[^>]*>|<\/body>|<meta[^>]*/i', '', $html);
        $html = preg_replace('/<head[^>]*>.*?<\/head>/si', '', $html);
        $html = preg_replace('/max-width\s*:\s*900px\s*;?\s*margin\s*:\s*0\s+auto/i', 'width:100%', $html);
        $html = preg_replace('/margin\s*:\s*0\s+auto\s*;?\s*max-width\s*:\s*900px/i', 'width:100%', $html);
        $html = trim($html);
        $json = !empty($result['structured']) ? $result['structured'] : ($result['data'] ?? $result);
        $DB->update_record('courseinfo', (object)[
            'id' => $instance->id,
            'generatedhtml' => \mod_courseinfo\manifest_storage::compress($html),
            'generatedjson' => \mod_courseinfo\manifest_storage::compress(json_encode($json, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR)),
            'volumebreakdown' => \mod_courseinfo\manifest_storage::compress(json_encode($scan['volume'] ?? null, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR)),
            'lastgenerated' => time(),
            'timemodified' => time(),
        ]);
        return ['status' => 'complete', 'retryable' => false, 'result' => ['saved' => true]];
    }

    /**
     * Native save result.
     */
    public static function native_save_result(int $cmid, array $result): void {
        global $DB;
        $cm = get_coursemodule_from_id('courseinfo', $cmid, 0, false, MUST_EXIST);
        $instance = $DB->get_record('courseinfo', ['id' => $cm->instance], '*', MUST_EXIST);
        if (empty($instance->generatedhtml)) {
            throw new \moodle_exception('emptygeneratorresult', 'local_aicoursebuilder');
        }
    }

    /**
     * Is available.
     */
    public static function is_available(): bool {
        return class_exists('\local_aicoursebuilder\local\generator_runtime')
            && \local_aicoursebuilder\local\generator_runtime::component_available(self::COMPONENT);
    }

    /**
     * Estimate.
     */
    public static function estimate(array $brief): int {
        return \local_aicoursebuilder\local\generator_runtime::estimate(self::COMPONENT, $brief);
    }

    /**
     * Generate.
     */
    public static function generate(int $cmid, array $brief): string {
        return \local_aicoursebuilder\local\generator_runtime::generate(self::COMPONENT, $cmid, $brief);
    }

    /**
     * Status.
     */
    public static function status(int $cmid, string $jobid): array {
        return \local_aicoursebuilder\local\generator_runtime::status(self::COMPONENT, $cmid, $jobid);
    }
}
