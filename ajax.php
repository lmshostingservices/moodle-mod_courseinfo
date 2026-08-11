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

define('AJAX_SCRIPT', true);

require_once('../../config.php');
require_once($CFG->libdir . '/filelib.php');
require_once($CFG->dirroot . '/mod/courseinfo/lib.php');

// Send the JSON header immediately — before any output can leak.
header('Content-Type: application/json; charset=utf-8');

/* ── Internal helpers (not duplicated in lib.php) ────────────────────────── */

function courseinfo_get_api_config() {
    global $DB;
    $config = array('siteid' => '', 'apikey' => '', 'apiurl' => 'https://lms-labs.com');
    try {
        if ($DB->get_manager()->table_exists('local_aiconfig')) {
            $aiconfig = $DB->get_record('local_aiconfig', array('id' => 1));
            if ($aiconfig) {
                $config['siteid'] = $aiconfig->siteid ?? '';
                $config['apikey'] = $aiconfig->apikey ?? '';
                if (!empty($aiconfig->apiurl)) {
                    $config['apiurl'] = $aiconfig->apiurl;
                }
            }
        }
    } catch (\Exception $e) { /* fallback */ }
    if (empty($config['siteid'])) {
        $config['siteid'] = get_config('local_aiconfig', 'siteid') ?: '';
        $config['apikey'] = get_config('local_aiconfig', 'apikey') ?: '';
    }
    return $config;
}

function courseinfo_api_call($url, $data, $config) {
    $curl = new \curl();
    $curl->setopt(array('CURLOPT_TIMEOUT' => 120, 'CURLOPT_CONNECTTIMEOUT' => 15));
    $headers  = array('Content-Type: application/json', 'Accept: application/json');
    $postdata = json_encode(array_merge($data, array('siteId' => $config['siteid'], 'apiKey' => $config['apikey'])));
    $response = $curl->post($url, $postdata, array('CURLOPT_HTTPHEADER' => $headers));
    $httpcode = $curl->get_info()['http_code'] ?? 0;
    if ($httpcode === 0) {
        return array('success' => false, 'error' => "API error: 0 (curl " . $curl->get_errno() . ")");
    }
    if ($httpcode >= 400) {
        $decoded = json_decode($response, true);
        return array('success' => false, 'error' => "API error: " . ($decoded['error'] ?? $decoded['message'] ?? "HTTP $httpcode"));
    }
    $decoded = json_decode($response, true);
    return $decoded ?: array('success' => false, 'error' => 'Invalid JSON response from API');
}

/* ── Main handler — everything inside try/catch so errors return JSON ─────── */

try {
    $action = required_param('action', PARAM_ALPHANUMEXT);
    $cmid   = required_param('cmid',   PARAM_INT);

    $cm         = get_coursemodule_from_id('courseinfo', $cmid, 0, false, MUST_EXIST);
    $course     = $DB->get_record('course', array('id' => $cm->course), '*', MUST_EXIST);
    $courseinfo = $DB->get_record('courseinfo', array('id' => $cm->instance), '*', MUST_EXIST);
    $context    = context_module::instance($cm->id);

    require_login($course, true, $cm);
    require_sesskey();

    switch ($action) {

        /* ── Scan activities ─────────────────────────────────────────────── */
        case 'scan':
            courseinfo_require_manage($context);
            \core\session\manager::write_close();
            $scanner    = new \mod_courseinfo\scanner($course->id);
            $scanResult = $scanner->scan_complete();
            $enableVol  = !empty($courseinfo->enablevol);
            $volResult  = null;
            if ($enableVol && !empty($courseinfo->nominalhours)) {
                $calculator = new \mod_courseinfo\volume_calculator($courseinfo->nominalhours, $scanResult['activities']);
                $volResult  = $calculator->calculate();
                $scanResult['volume'] = $volResult;
            }
            $DB->update_record('courseinfo', (object)array(
                'id'             => $courseinfo->id,
                'scanneddata'    => \mod_courseinfo\manifest_storage::compress(json_encode($scanResult)),
                'volumebreakdown'=> $volResult ? \mod_courseinfo\manifest_storage::compress(json_encode($volResult)) : null,
                'timemodified'   => time(),
            ));
            echo json_encode(array('success' => true, 'data' => $scanResult));
            break;

        /* ── Generate course information ─────────────────────────────────── */
        case 'generate':
            courseinfo_require_manage($context);
            \core\session\manager::write_close();

            $primarycolour   = optional_param('primaryColour', '', PARAM_TEXT);
            $secondarycolour = optional_param('secondaryColour', '', PARAM_TEXT);
            if (empty($primarycolour)) {
                try {
                    $themeconfig   = \theme_config::load($CFG->theme);
                    $primarycolour = $themeconfig->settings->brandcolor ?? '#1a73e8';
                } catch (\Exception $e) {
                    $primarycolour = '#1a73e8';
                }
            }
            if (empty($secondarycolour)) {
                $secondarycolour = $primarycolour;
            }

            $enableVol   = !empty($courseinfo->enablevol);
            $enableAsqa  = isset($courseinfo->enableasqa) ? !empty($courseinfo->enableasqa) : true;
            $scannedData = null;
            if (!empty($courseinfo->scanneddata)) {
                $scannedData = json_decode(\mod_courseinfo\manifest_storage::decompress($courseinfo->scanneddata), true);
            }
            if (!$scannedData) {
                $scanner     = new \mod_courseinfo\scanner($course->id);
                $scannedData = $scanner->scan_complete();
                if ($enableVol && !empty($courseinfo->nominalhours)) {
                    $calculator = new \mod_courseinfo\volume_calculator($courseinfo->nominalhours, $scannedData['activities']);
                    $scannedData['volume'] = $calculator->calculate();
                }
            }

            if (empty($scannedData['activities'])) {
                echo json_encode(array('success' => false, 'error' => get_string('noactivities', 'courseinfo')));
                break;
            }

            $config = courseinfo_get_api_config();
            if (empty($config['siteid']) || empty($config['apikey'])) {
                echo json_encode(array('success' => false, 'error' => get_string('configerror', 'courseinfo')));
                break;
            }

            $apiurl  = rtrim($config['apiurl'], '/') . '/api/moodle/course-info/generate';
            $apiData = array(
                'courseName'      => $course->fullname,
                'courseShortName' => $course->shortname,
                'courseSummary'   => strip_tags($course->summary),
                'unitCode'        => $enableVol ? ($courseinfo->unitcode ?: '') : '',
                'unitTitle'       => $enableVol ? ($courseinfo->unittitle ?: '') : '',
                'nominalHours'    => $enableVol ? (int)($courseinfo->nominalhours ?: 0) : 0,
                'enableVol'       => $enableVol,
                'activities'      => $scannedData['activities'],
                'volume'          => $enableVol ? ($scannedData['volume'] ?? null) : null,
                'activitySummary' => $scannedData['summary'] ?? null,
                'environment'     => $scannedData['environment'] ?? null,
                'primaryColour'   => $primarycolour,
                'secondaryColour' => $secondarycolour,
                'enableAsqa'      => $enableAsqa,
            );

            $result = courseinfo_api_call($apiurl, $apiData, $config);

            if (!empty($result['success']) || !empty($result['html'])) {
                $html     = $result['html'] ?? '';

                /* BUG-CI-HTMLSTRIP: Strip document-level tags from AI-generated HTML.
                   The server API already strips these, but this is a belt-and-suspenders
                   safety net for any API version that doesn't sanitize. */
                $html = preg_replace('/<!DOCTYPE[^>]*>/i',    '', $html);
                $html = preg_replace('/<html[^>]*>/i',        '', $html);
                $html = preg_replace('/<\/html>/i',           '', $html);
                $html = preg_replace('/<head[^>]*>.*?<\/head>/si', '', $html);
                $html = preg_replace('/<body[^>]*>/i',        '', $html);
                $html = preg_replace('/<\/body>/i',           '', $html);
                $html = preg_replace('/<meta[^>]*/i',         '', $html);
                /* BUG-CI-MAXWIDTH: Strip max-width:900px centering that makes content look narrow */
                $html = preg_replace('/max-width\s*:\s*900px\s*;?\s*margin\s*:\s*0\s+auto/i', 'width:100%', $html);
                $html = preg_replace('/margin\s*:\s*0\s+auto\s*;?\s*max-width\s*:\s*900px/i', 'width:100%', $html);
                $html = trim($html);

                // Extract audit-structured data returned by the SaaS API.
                // The API (routes.ts) now returns a "structured" key alongside "html"
                // containing the full audit-compliance JSON schema.
                $structured = $result['structured'] ?? null;

                // Run compliance validation if structured data is present.
                $compliance = null;
                if (!empty($structured) && is_array($structured)) {
                    $compliance = \mod_courseinfo\courseinfo_compliance::validate($structured);
                }

                // Save structured JSON (audit schema) to generatedjson column.
                // Falls back to legacy data shape when structured is absent.
                $jsonToSave = !empty($structured) ? $structured : ($result['data'] ?? $result);

                $DB->update_record('courseinfo', (object)array(
                    'id'              => $courseinfo->id,
                    'generatedhtml'   => \mod_courseinfo\manifest_storage::compress($html),
                    'generatedjson'   => \mod_courseinfo\manifest_storage::compress(json_encode($jsonToSave)),
                    'scanneddata'     => \mod_courseinfo\manifest_storage::compress(json_encode($scannedData)),
                    'volumebreakdown' => \mod_courseinfo\manifest_storage::compress(json_encode($scannedData['volume'] ?? null)),
                    'lastgenerated'   => time(),
                    'timemodified'    => time(),
                ));
                echo json_encode(array(
                    'success'    => true,
                    'html'       => $html,
                    'structured' => $structured,
                    'compliance' => $compliance,
                    'volume'     => $scannedData['volume'] ?? null,
                ));
            } else {
                echo json_encode(array('success' => false, 'error' => $result['error'] ?? 'Unknown error'));
            }
            break;

        /* ── ASYNC: Start course info generation (returns jobId immediately) ── */
        case 'generate_async':
            courseinfo_require_manage($context);
            \core\session\manager::write_close();

            $config = courseinfo_get_api_config();
            if (empty($config['siteid']) || empty($config['apikey'])) {
                echo json_encode(array('success' => false, 'error' => get_string('configerror', 'courseinfo')));
                break;
            }

            // Build the same payload as case 'generate' but call the /start endpoint.
            $enableVol  = !empty($courseinfo->enablevol);
            $scannedData = null;
            if (!empty($courseinfo->scanneddata)) {
                $scannedData = json_decode(\mod_courseinfo\manifest_storage::decompress($courseinfo->scanneddata), true);
            }
            if (!$scannedData) {
                $scanner     = new \mod_courseinfo\scanner($course->id);
                $scannedData = $scanner->scan_complete();
                if ($enableVol && !empty($courseinfo->nominalhours)) {
                    $calculator = new \mod_courseinfo\volume_calculator($courseinfo->nominalhours, $scannedData['activities']);
                    $scannedData['volume'] = $calculator->calculate();
                }
            }
            if (empty($scannedData['activities'])) {
                echo json_encode(array('success' => false, 'error' => get_string('noactivities', 'courseinfo')));
                break;
            }

            $primarycolour   = optional_param('primaryColour', '', PARAM_TEXT);
            $secondarycolour = optional_param('secondaryColour', '', PARAM_TEXT);
            if (empty($primarycolour)) {
                try { $themeconfig = \theme_config::load($CFG->theme); $primarycolour = $themeconfig->settings->brandcolor ?? '#1a73e8'; }
                catch (\Exception $e) { $primarycolour = '#1a73e8'; }
            }
            if (empty($secondarycolour)) { $secondarycolour = $primarycolour; }

            $apiData = array(
                'courseName' => $course->fullname, 'courseShortName' => $course->shortname,
                'courseSummary' => strip_tags($course->summary),
                'unitCode' => $enableVol ? ($courseinfo->unitcode ?: '') : '',
                'unitTitle' => $enableVol ? ($courseinfo->unittitle ?: '') : '',
                'nominalHours' => $enableVol ? (int)($courseinfo->nominalhours ?: 0) : 0,
                'enableVol' => $enableVol, 'activities' => $scannedData['activities'],
                'volume' => $enableVol ? ($scannedData['volume'] ?? null) : null,
                'activitySummary' => $scannedData['summary'] ?? null,
                'environment' => $scannedData['environment'] ?? null,
                'primaryColour' => $primarycolour, 'secondaryColour' => $secondarycolour,
                'enableAsqa' => isset($courseinfo->enableasqa) ? !empty($courseinfo->enableasqa) : true,
            );

            $startUrl = rtrim($config['apiurl'], '/') . '/api/moodle/course-info/generate/start';
            $result   = courseinfo_api_call($startUrl, $apiData, $config);

            if (empty($result['ok']) || empty($result['jobId'])) {
                echo json_encode(array('success' => false, 'error' => $result['error'] ?? 'Failed to start generation job'));
                break;
            }
            echo json_encode(array('success' => true, 'jobId' => $result['jobId'], 'async' => true));
            break;

        /* ── ASYNC POLL: Check background generation job status ─────────────── */
        case 'poll':
            $jobId = required_param('jobId', PARAM_ALPHANUMEXT);
            \core\session\manager::write_close();
            $config = courseinfo_get_api_config();
            $pollUrl = rtrim($config['apiurl'], '/') . '/api/jobs/' . urlencode($jobId);
            $curl = new \curl();
            $curl->setopt(array('CURLOPT_TIMEOUT' => 10, 'CURLOPT_CONNECTTIMEOUT' => 5));
            $response = $curl->get($pollUrl);
            $result   = json_decode($response, true);
            echo json_encode($result ?: ['ok' => false, 'status' => 'error', 'error' => 'Could not reach job status endpoint']);
            break;

        /* ── Save edited HTML ────────────────────────────────────────────── */
        case 'save':
            courseinfo_require_manage($context);
            $html = required_param('html', PARAM_RAW);
            $DB->update_record('courseinfo', (object)array(
                'id'            => $courseinfo->id,
                'generatedhtml' => \mod_courseinfo\manifest_storage::compress($html),
                'timemodified'  => time(),
            ));
            echo json_encode(array('success' => true));
            break;

        /* ── Get custom boxes ────────────────────────────────────────────── */
        case 'getboxes':
            require_capability('mod/courseinfo:view', $context);
            $raw   = get_config('mod_courseinfo', 'customboxes_' . $cmid);
            $boxes = $raw ? json_decode($raw, true) : array();
            echo json_encode(array('success' => true, 'boxes' => $boxes ?: array()));
            break;

        /* ── Save custom boxes ───────────────────────────────────────────── */
        case 'saveboxes':
            courseinfo_require_manage($context);
            $boxesJson = required_param('boxes', PARAM_RAW);
            $boxes     = json_decode($boxesJson, true);
            if (!is_array($boxes)) {
                echo json_encode(array('success' => false, 'error' => 'Invalid boxes data'));
                break;
            }
            $clean = array();
            foreach ($boxes as $box) {
                $clean[] = array(
                    'id'       => preg_replace('/[^a-z0-9\-]/', '', $box['id'] ?? ''),
                    'icon'     => preg_replace('/[^a-z0-9\-]/', '', $box['icon'] ?? 'fa-info-circle'),
                    'heading'  => clean_param($box['heading'] ?? '', PARAM_TEXT),
                    'body'     => clean_param($box['body']    ?? '', PARAM_RAW),
                    'position' => (int)($box['position'] ?? 9999),
                );
            }
            set_config('customboxes_' . $cmid, json_encode($clean), 'mod_courseinfo');
            echo json_encode(array('success' => true));
            break;

        /* ── Check AI credits ────────────────────────────────────────────── */
        case 'getcredits':
            courseinfo_require_manage($context);
            $config = courseinfo_get_api_config();
            if (empty($config['siteid']) || empty($config['apikey'])) {
                echo json_encode(array('success' => false, 'error' => 'Plugin not configured: Missing Site ID or API Key. Go to Site admin > Plugins > AI Grader Central Config.'));
                break;
            }
            $creditsUrl = rtrim($config['apiurl'], '/') . '/api/credits?' . http_build_query(
                array('siteId' => $config['siteid'], 'apiKey' => $config['apikey']), '', '&'
            );
            $creditCurl = new \curl();
            $creditCurl->setopt(array('CURLOPT_TIMEOUT' => 15));
            $creditResponse = $creditCurl->get($creditsUrl);
            $creditHttpCode = $creditCurl->get_info()['http_code'] ?? 0;
            if ($creditHttpCode >= 200 && $creditHttpCode < 300) {
                $creditResult = json_decode($creditResponse, true);
                if (isset($creditResult['credits'])) {
                    echo json_encode(array('success' => true, 'credits' => $creditResult['credits']));
                } elseif (isset($creditResult['creditsRaw'])) {
                    echo json_encode(array('success' => true, 'credits' => $creditResult['creditsRaw']));
                } elseif (isset($creditResult['balance'])) {
                    echo json_encode(array('success' => true, 'credits' => $creditResult['balance']));
                } else {
                    echo json_encode(array('success' => true, 'credits' => $creditResult['creditsRemaining'] ?? 0));
                }
            } else {
                echo json_encode(array('success' => false, 'error' => 'Failed to fetch credit balance (HTTP ' . $creditHttpCode . ')'));
            }
            break;

        /* ── Get hero banner colour ──────────────────────────────────────── */
        case 'getherocolor':
            require_capability('mod/courseinfo:view', $context);
            $color = get_config('mod_courseinfo', 'herocolor_' . $cmid);
            echo json_encode(array('success' => true, 'color' => $color ?: ''));
            break;

        /* ── Save hero banner colour ─────────────────────────────────────── */
        case 'saveherocolor':
            courseinfo_require_manage($context);
            $color = required_param('color', PARAM_TEXT);
            /* Only allow valid hex colours — 3, 6, or 8 hex digits with leading # */
            if (!preg_match('/^#[0-9a-fA-F]{3}(?:[0-9a-fA-F]{3}(?:[0-9a-fA-F]{2})?)?$/', $color)) {
                echo json_encode(array('success' => false, 'error' => 'Invalid colour value'));
                break;
            }
            set_config('herocolor_' . $cmid, $color, 'mod_courseinfo');
            echo json_encode(array('success' => true));
            break;

        /* ── Check config ────────────────────────────────────────────────── */
        case 'checkconfig':
            courseinfo_require_manage($context);
            $config = courseinfo_get_api_config();
            if (empty($config['siteid']) || empty($config['apikey'])) {
                echo json_encode(array('success' => false, 'error' => get_string('configerror', 'courseinfo')));
                break;
            }
            $apiurl = rtrim($config['apiurl'], '/') . '/api/moodle/course-info/check-config';
            echo json_encode(courseinfo_api_call($apiurl, array(), $config));
            break;

        /* ── Student: submit learner acknowledgement ─────────────────────── */
        case 'acknowledge':
            require_capability('mod/courseinfo:view', $context);
            /* Check for existing acknowledgement — prevent duplicates */
            $existing = $DB->get_record('courseinfo_ack', array(
                'courseinfoid' => $courseinfo->id,
                'userid'       => $USER->id,
            ));
            if ($existing) {
                $dateStr = userdate($existing->timeacknowledged, get_string('strftimedatetimeshort', 'langconfig'));
                echo json_encode(array(
                    'success'         => true,
                    'alreadyAcknowledged' => true,
                    'timeacknowledged'   => $existing->timeacknowledged,
                    'date'               => $dateStr,
                ));
                break;
            }
            /* Check that courseinfo_ack table exists (safe guard for mid-upgrade) */
            if (!$DB->get_manager()->table_exists('courseinfo_ack')) {
                echo json_encode(array('success' => false, 'error' => 'Acknowledgement table not yet created. Please ask your administrator to run the plugin upgrade.'));
                break;
            }
            $ack = new \stdClass();
            $ack->courseinfoid      = $courseinfo->id;
            $ack->userid            = $USER->id;
            $ack->timeacknowledged  = time();
            $ack->ipaddress         = getremoteaddr('unknown');
            $ack->useragent         = substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 1000);
            $DB->insert_record('courseinfo_ack', $ack);
            $dateStr = userdate($ack->timeacknowledged, get_string('strftimedatetimeshort', 'langconfig'));
            echo json_encode(array(
                'success'         => true,
                'timeacknowledged' => $ack->timeacknowledged,
                'date'             => $dateStr,
            ));
            break;

        /* ── Teacher: get acknowledgement count + list ───────────────────── */
        case 'getackcount':
            courseinfo_require_manage($context);
            if (!$DB->get_manager()->table_exists('courseinfo_ack')) {
                echo json_encode(array('success' => true, 'count' => 0, 'records' => array()));
                break;
            }
            $ackCount   = $DB->count_records('courseinfo_ack', array('courseinfoid' => $courseinfo->id));
            $ackRecords = $DB->get_records('courseinfo_ack', array('courseinfoid' => $courseinfo->id), 'timeacknowledged DESC', 'id,userid,timeacknowledged,ipaddress', 0, 50);
            $ackList    = array();
            foreach ($ackRecords as $rec) {
                // FIX-CI-FULLNAME-FIELDS (v1.0.44): include all six name fields required by
                // fullname() in Moodle™ 4.x — partial user objects trigger a debugging()
                // warning ("name fields missing: firstnamephonetic, lastnamephonetic,
                // middlename, alternatename") which leaked into the AJAX JSON payload and
                // broke the acknowledgement list rendering on the teacher report.
                $u = $DB->get_record('user', array('id' => $rec->userid),
                    'id,firstname,lastname,firstnamephonetic,lastnamephonetic,middlename,alternatename,email',
                    IGNORE_MISSING);
                $ackList[] = array(
                    'userid'           => (int)$rec->userid,
                    'fullname'         => $u ? fullname($u) : 'User ' . $rec->userid,
                    'email'            => $u ? $u->email : '',
                    'timeacknowledged' => (int)$rec->timeacknowledged,
                    'date'             => userdate($rec->timeacknowledged, get_string('strftimedatetimeshort', 'langconfig')),
                    'ipaddress'        => $rec->ipaddress ?: '',
                );
            }
            echo json_encode(array('success' => true, 'count' => (int)$ackCount, 'records' => $ackList));
            break;

        default:
            echo json_encode(array('success' => false, 'error' => 'Unknown action: ' . $action));
    }

} catch (\Throwable $e) {
    echo json_encode(array('success' => false, 'error' => $e->getMessage()));
}
