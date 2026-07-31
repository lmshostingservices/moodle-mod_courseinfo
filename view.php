<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.

require_once('../../config.php');

$id = optional_param('id', 0, PARAM_INT);
$n  = optional_param('n',  0, PARAM_INT);

if ($id) {
    $cm         = get_coursemodule_from_id('courseinfo', $id, 0, false, MUST_EXIST);
    $course     = $DB->get_record('course', array('id' => $cm->course), '*', MUST_EXIST);
    $courseinfo = $DB->get_record('courseinfo', array('id' => $cm->instance), '*', MUST_EXIST);
} else if ($n) {
    $courseinfo = $DB->get_record('courseinfo', array('id' => $n), '*', MUST_EXIST);
    $course     = $DB->get_record('course', array('id' => $courseinfo->course), '*', MUST_EXIST);
    $cm         = get_coursemodule_from_instance('courseinfo', $courseinfo->id, $course->id, false, MUST_EXIST);
} else {
    print_error('missingparam');
}

require_login($course, true, $cm);
$context = context_module::instance($cm->id);
require_capability('mod/courseinfo:view', $context);

courseinfo_view($courseinfo, $course, $cm, $context);

$PAGE->set_url('/mod/courseinfo/view.php', array('id' => $cm->id));
$PAGE->set_title(format_string($courseinfo->name));
$PAGE->set_heading(format_string($course->fullname));
$PAGE->set_context($context);

$canManage  = has_capability('mod/courseinfo:manage', $context) || has_capability('moodle/course:manageactivities', $context);
$enableVol  = !empty($courseinfo->enablevol);
$hasContent = !empty($courseinfo->generatedhtml);

/* ── Learner Acknowledgement Status ───────────────────────────────────────── */
$userHasAcknowledged = false;
$ackRecord           = null;
$ackCount            = 0;
if ($hasContent && $DB->get_manager()->table_exists('courseinfo_ack')) {
    if (!$canManage) {
        $ackRecord           = $DB->get_record('courseinfo_ack', array('courseinfoid' => $courseinfo->id, 'userid' => $USER->id));
        $userHasAcknowledged = !empty($ackRecord);
    } else {
        $ackCount = $DB->count_records('courseinfo_ack', array('courseinfoid' => $courseinfo->id));
    }
}

/* ── Theme colours ────────────────────────────────────────────────────────── */
$primarycolour   = '#1a73e8';
$secondarycolour = '#1a73e8';
try {
    $themeconfig = \theme_config::load($CFG->theme);
    if (!empty($themeconfig->settings->brandcolor)) {
        $primarycolour = $themeconfig->settings->brandcolor;
    }
    if (!empty($themeconfig->settings->secondarybrandcolor)) {
        $secondarycolour = $themeconfig->settings->secondarybrandcolor;
    } else if (!empty($themeconfig->settings->navbarbrandcolor)) {
        $secondarycolour = $themeconfig->settings->navbarbrandcolor;
    } else {
        $secondarycolour = $primarycolour;
    }
} catch (\Exception $e) { /* fallback */ }

/* ── AMD init ─────────────────────────────────────────────────────────────── */
$PAGE->requires->js_call_amd('mod_courseinfo/courseinfo', 'init', array(
    $cm->id,
    $canManage,
    $hasContent,
    $courseinfo->unitcode ?: '',
    (int)($courseinfo->nominalhours ?: 0),
    $enableVol,
    $primarycolour,
    $secondarycolour,
));

echo $OUTPUT->header();
echo '<div id="courseinfo-app" data-cmid="' . $cm->id . '">';

/* ════════════════════════════════════════════════════════════════════════════
   TEACHER VIEW
   ════════════════════════════════════════════════════════════════════════════ */
if ($canManage) {

    /* ── Teacher toolbar ── */
    echo '<div id="courseinfo-toolbar" class="mb-3">';
    echo '<div class="d-flex flex-wrap align-items-center gap-2">';

    if ($enableVol && !empty($courseinfo->unitcode)) {
        echo '<span class="badge badge-info" style="font-size:0.9em;padding:6px 12px;background:var(--primary,' . s($primarycolour) . ');color:#fff;border-radius:4px;">';
        echo s($courseinfo->unitcode);
        if (!empty($courseinfo->unittitle)) { echo ' — ' . s($courseinfo->unittitle); }
        echo '</span>';
    }
    if ($enableVol && !empty($courseinfo->nominalhours)) {
        echo '<span class="badge badge-secondary" style="font-size:0.9em;padding:6px 12px;border-radius:4px;">';
        echo 'Nominal Hours: ' . (int)$courseinfo->nominalhours;
        echo '</span>';
    }

    if ($hasContent && $ackCount > 0) {
        echo '<span class="badge badge-success" style="font-size:0.85em;padding:5px 10px;border-radius:4px;background:#1a7340;color:#fff;" title="Number of students who have formally acknowledged reading this course information">';
        echo '<i class="fa fa-check-circle mr-1"></i> ' . $ackCount . ' student' . ($ackCount === 1 ? '' : 's') . ' acknowledged';
        echo '</span>';
    } else if ($hasContent) {
        echo '<span class="badge badge-warning" style="font-size:0.85em;padding:5px 10px;border-radius:4px;background:#856404;color:#fff;" title="No students have acknowledged this course information yet">';
        echo '<i class="fa fa-clock-o mr-1"></i> 0 acknowledged';
        echo '</span>';
    }

    echo '<button id="btn-scan" class="btn btn-outline-secondary btn-sm"><i class="fa fa-search mr-1"></i> Scan Activities</button>';

    $btnLabel = $hasContent ? get_string('regeneratecourseinfo', 'courseinfo') : get_string('generatecourseinfo', 'courseinfo');
    echo '<button id="btn-generate" class="btn btn-primary btn-sm"><i class="fa fa-magic mr-1"></i> ' . $btnLabel . '</button>';

    echo '<button id="btn-add-box" class="btn btn-outline-success btn-sm" style="' . ($hasContent ? '' : 'display:none') . '">';
    echo '<i class="fa fa-plus-circle mr-1"></i> Add Box</button>';

    echo '<button id="btn-edit" class="btn btn-outline-warning btn-sm" style="' . ($hasContent ? '' : 'display:none') . '">';
    echo '<i class="fa fa-pencil mr-1"></i> Edit</button>';
    echo '<button id="btn-save" class="btn btn-success btn-sm" style="display:none">';
    echo '<i class="fa fa-check mr-1"></i> Save Changes</button>';
    echo '<button id="btn-cancel-edit" class="btn btn-outline-secondary btn-sm" style="display:none">';
    echo '<i class="fa fa-times mr-1"></i> Cancel</button>';

    echo '<button id="btn-print" class="btn btn-outline-secondary btn-sm" style="' . ($hasContent ? '' : 'display:none') . '">';
    echo '<i class="fa fa-print mr-1"></i> ' . get_string('printcourseinfo', 'courseinfo') . '</button>';

    /* FEATURE-HEROBG: Hero banner colour picker.
       BUG-CI-HEROUI: Always rendered (hidden when no content) so JS can reveal it
       after first-time generation without needing to rebuild the DOM element.
       Uses btn-outline-info btn-sm to match the other toolbar buttons. */
    echo '<label id="btn-hero-color-wrap" title="Change hero banner colour" ';
    echo 'class="btn btn-outline-info btn-sm" ';
    echo 'style="cursor:pointer;position:relative;display:' . ($hasContent ? 'inline-flex' : 'none') . ';align-items:center;gap:5px;white-space:nowrap;margin:0;">';
    echo '<i class="fa fa-paint-brush"></i> Hero Colour';
    echo '<input type="color" id="btn-hero-color" value="' . s($primarycolour) . '" ';
    echo 'style="position:absolute;opacity:0;width:100%;height:100%;top:0;left:0;cursor:pointer;border:none;padding:0;">';
    echo '</label>';

    echo '</div></div>'; /* end toolbar */

    echo '<div id="courseinfo-status" style="display:none;" class="alert alert-info mb-3">';
    echo '<i class="fa fa-spinner fa-spin mr-2"></i> <span id="courseinfo-status-text"></span>';
    echo '</div>';

    echo '<div id="courseinfo-scan-results" style="display:none;" class="mb-3"></div>';

/* ════════════════════════════════════════════════════════════════════════════
   STUDENT VIEW  — no scan / regenerate / edit, just info badges + print
   ════════════════════════════════════════════════════════════════════════════ */
} else {

    if ($enableVol && (!empty($courseinfo->unitcode) || !empty($courseinfo->nominalhours))) {
        echo '<div class="mb-3 d-flex flex-wrap align-items-center gap-2">';
        if (!empty($courseinfo->unitcode)) {
            echo '<span class="badge badge-info" style="font-size:0.9em;padding:6px 12px;background:var(--primary,' . s($primarycolour) . ');color:#fff;border-radius:4px;">';
            echo s($courseinfo->unitcode);
            if (!empty($courseinfo->unittitle)) { echo ' — ' . s($courseinfo->unittitle); }
            echo '</span>';
        }
        if (!empty($courseinfo->nominalhours)) {
            echo '<span class="badge badge-secondary" style="font-size:0.9em;padding:6px 12px;border-radius:4px;">';
            echo 'Nominal Hours: ' . (int)$courseinfo->nominalhours;
            echo '</span>';
        }
        echo '</div>';
    }

    if ($hasContent) {
        echo '<div class="mb-3">';
        echo '<button id="btn-print" class="btn btn-outline-secondary btn-sm">';
        echo '<i class="fa fa-print mr-1"></i> ' . get_string('printcourseinfo', 'courseinfo') . '</button>';
        echo '</div>';
    } else {
        echo '<div class="alert alert-info mb-3"><i class="fa fa-info-circle mr-2"></i>Course information is being prepared. Please check back later.</div>';
    }
}

/* ── Generated content (both teacher + student) ── */
echo '<div id="courseinfo-generated">';
if ($hasContent) {
    echo \mod_courseinfo\manifest_storage::decompress($courseinfo->generatedhtml);
} else if ($canManage) {
    echo '<div class="alert alert-warning mb-3"><i class="fa fa-info-circle mr-2"></i>' . get_string('nocourseinfo', 'courseinfo') . '</div>';
}
echo '</div>';

/* BUG-CI-ICONBREAK: Belt-and-suspenders Font Awesome restore.
   Placed AFTER #courseinfo-generated so it wins the CSS cascade over any
   font-family:inherit rules the AI may have emitted in its <style> block.
   Covers Moodle 3.x (FontAwesome), Moodle 4.x FA6 (--fa-font-solid var), FA5.
   Specificity: #courseinfo-generated .fa = ID+class = 110 beats
   #courseinfo-generated * = ID+universal = 011. */
echo '<style>
#courseinfo-generated .fa,
#courseinfo-generated i[class*="fa-"] {
    font-family: FontAwesome, "Font Awesome 6 Free", "Font Awesome 5 Free" !important;
    font-style: normal !important;
    /* BUG-CI-ICONFA6: 900 required for FA6 Solid; FA4 ignores weight → safe for both */
    font-weight: 900 !important;
    font-variant: normal !important;
    text-transform: none !important;
    speak: never !important;
    -webkit-font-smoothing: antialiased !important;
    -moz-osx-font-smoothing: grayscale !important;
    display: inline-block !important;
}
/* BUG-CI-BLANK-CONTENT: Force-reveal any ci-reveal scroll-animation elements.
   The AI generates IntersectionObserver <script> blocks that initialise .ci-reveal
   cards at opacity:0 and fade them in on scroll. On initial PHP page load those
   scripts do execute, but this !important override ensures the content is always
   immediately visible — preventing blank sections if the observer fails or fires late.
   On AJAX generation, scripts injected via innerHTML are never executed (browser
   security), so this rule (together with the JS force-reveal in courseinfo.js) is the
   authoritative fix for blank content after generation. */
#courseinfo-generated .ci-reveal {
    opacity: 1 !important;
    animation: none !important;
    transform: none !important;
    visibility: visible !important;
}
</style>';

/* Custom boxes container — boxes injected here by JS */
echo '<div id="courseinfo-custom-boxes"></div>';

/* Volume of learning panel (teacher only) */
if ($canManage && $enableVol && !empty($courseinfo->volumebreakdown)) {
    $volData = json_decode(\mod_courseinfo\manifest_storage::decompress($courseinfo->volumebreakdown), true);
    if ($volData) {
        echo '<div id="courseinfo-volume" class="mt-4">';
        echo courseinfo_render_volume_panel($volData);
        echo '</div>';
    }
}

/* ── Learner Acknowledgement Section ──────────────────────────────────────── */
if ($hasContent) {
    echo '<div id="courseinfo-ack-wrap" class="mt-4">';

    if ($canManage) {
        /* Teacher view: acknowledgement list link */
        echo '<div style="background:#f0f9f0;border:1px solid #b7dfb9;border-radius:8px;padding:14px 18px;display:flex;align-items:center;gap:12px;">';
        echo '<i class="fa fa-check-circle" style="font-size:22px;color:#1a7340;flex-shrink:0;"></i>';
        echo '<div style="flex:1;">';
        echo '<div style="font-weight:600;font-size:0.95em;color:#1a7340;">Learner Acknowledgement Audit Trail</div>';
        echo '<div style="font-size:0.85em;color:#2d6a4f;margin-top:3px;">';
        if ($ackCount > 0) {
            echo '<strong>' . $ackCount . '</strong> student' . ($ackCount === 1 ? ' has' : 's have') . ' formally acknowledged reading this course information.';
            echo ' This record is stored with timestamp and IP address for ASQA audit evidence.';
        } else {
            echo 'No students have acknowledged this course information yet. Acknowledgements are recorded with timestamp and IP address for ASQA audit evidence.';
        }
        echo '</div>';
        echo '</div>';
        echo '</div>';

    } else {
        /* Student view */
        if ($userHasAcknowledged && $ackRecord) {
            /* Already acknowledged — show green confirmation */
            $ackDate = userdate($ackRecord->timeacknowledged, get_string('strftimedatetimeshort', 'langconfig'));
            echo '<div id="ci-ack-confirmed" style="background:#f0f9f0;border:1px solid #b7dfb9;border-radius:8px;padding:18px 20px;">';
            echo '<div style="display:flex;align-items:flex-start;gap:12px;">';
            echo '<i class="fa fa-check-circle" style="font-size:28px;color:#1a7340;flex-shrink:0;margin-top:2px;"></i>';
            echo '<div>';
            echo '<div style="font-weight:700;font-size:1em;color:#1a7340;">Course Information Acknowledged</div>';
            echo '<div style="font-size:0.88em;color:#2d6a4f;margin-top:4px;">';
            echo 'You acknowledged receipt and understanding of this course information on <strong>' . s($ackDate) . '</strong>.';
            echo ' This record is held by ' . s($courseinfo->name) . ' as evidence of pre-enrolment disclosure.';
            echo '</div>';
            echo '</div>';
            echo '</div>';
            echo '</div>';
        } else {
            /* Not yet acknowledged — show checkbox + button */
            echo '<div id="ci-ack-box" style="background:#fff8e1;border:1px solid #f0c040;border-radius:8px;padding:20px 22px;">';
            echo '<div style="display:flex;align-items:flex-start;gap:12px;margin-bottom:14px;">';
            echo '<i class="fa fa-pencil-square-o" style="font-size:22px;color:#856404;flex-shrink:0;margin-top:2px;"></i>';
            echo '<div>';
            echo '<div style="font-weight:700;font-size:0.95em;color:#5a3e00;">Learner Acknowledgement Required</div>';
            echo '<div style="font-size:0.85em;color:#7a5a00;margin-top:3px;">';
            echo 'In accordance with ASQA Standards for RTOs 2015, please confirm you have read and understood this course information before commencing your training.';
            echo '</div>';
            echo '</div>';
            echo '</div>';

            echo '<label style="display:flex;align-items:flex-start;gap:10px;cursor:pointer;margin-bottom:14px;">';
            echo '<input type="checkbox" id="ci-ack-checkbox" style="margin-top:3px;width:18px;height:18px;flex-shrink:0;cursor:pointer;">';
            echo '<span style="font-size:0.9em;color:#3d2c00;line-height:1.5;">';
            echo 'I confirm that I have read and understood this Course Information document, including the entry requirements, course fees, assessment requirements, complaints and appeals process, and my rights and responsibilities as a learner at ' . s($courseinfo->name) . '.';
            echo '</span>';
            echo '</label>';

            echo '<div id="ci-ack-error" style="display:none;background:#fde8e8;border:1px solid #f5c6cb;border-radius:6px;padding:8px 12px;font-size:0.85em;color:#842029;margin-bottom:10px;">';
            echo '<i class="fa fa-exclamation-circle mr-1"></i> Please tick the checkbox before acknowledging.';
            echo '</div>';

            echo '<button id="ci-ack-btn" type="button" class="btn btn-warning btn-sm" style="background:#856404;border-color:#856404;color:#fff;font-weight:600;">';
            echo '<i class="fa fa-check mr-1"></i> Acknowledge Course Information';
            echo '</button>';
            echo '</div>'; /* end ci-ack-box */

            /* Inline JS for acknowledgement submission */
            echo '<script>
(function() {
    var btn = document.getElementById("ci-ack-btn");
    var chk = document.getElementById("ci-ack-checkbox");
    var err = document.getElementById("ci-ack-error");
    var box = document.getElementById("ci-ack-box");
    if (!btn || !chk) { return; }
    btn.addEventListener("click", function() {
        if (!chk.checked) {
            err.style.display = "block";
            return;
        }
        err.style.display = "none";
        btn.disabled = true;
        btn.innerHTML = "<i class=\"fa fa-spinner fa-spin mr-1\"></i> Saving…";
        var xhr = new XMLHttpRequest();
        xhr.open("POST", M.cfg.wwwroot + "/mod/courseinfo/ajax.php", true);
        xhr.setRequestHeader("Content-Type", "application/x-www-form-urlencoded");
        xhr.onreadystatechange = function() {
            if (xhr.readyState !== 4) { return; }
            try {
                var resp = JSON.parse(xhr.responseText);
                if (resp && resp.success) {
                    var wrap = document.getElementById("courseinfo-ack-wrap");
                    if (wrap) {
                        var dateStr = resp.date || "just now";
                        wrap.innerHTML = "<div style=\"background:#f0f9f0;border:1px solid #b7dfb9;border-radius:8px;padding:18px 20px;\">" +
                            "<div style=\"display:flex;align-items:flex-start;gap:12px;\">" +
                            "<i class=\"fa fa-check-circle\" style=\"font-size:28px;color:#1a7340;flex-shrink:0;margin-top:2px;\"></i>" +
                            "<div>" +
                            "<div style=\"font-weight:700;font-size:1em;color:#1a7340;\">Course Information Acknowledged</div>" +
                            "<div style=\"font-size:0.88em;color:#2d6a4f;margin-top:4px;\">You acknowledged receipt and understanding of this course information on <strong>" + dateStr + "</strong>. This record is held as evidence of pre-enrolment disclosure.</div>" +
                            "</div></div></div>";
                    }
                } else {
                    btn.disabled = false;
                    btn.innerHTML = "<i class=\"fa fa-check mr-1\"></i> Acknowledge Course Information";
                    err.style.display = "block";
                    err.innerHTML = "<i class=\"fa fa-exclamation-circle mr-1\"></i> " + (resp && resp.error ? resp.error : "Unable to save acknowledgement. Please try again.");
                }
            } catch(e) {
                btn.disabled = false;
                btn.innerHTML = "<i class=\"fa fa-check mr-1\"></i> Acknowledge Course Information";
            }
        };
        xhr.send("action=acknowledge&cmid=' . $cm->id . '&sesskey=" + M.cfg.sesskey);
    });
})();
</script>';
        }
    }
    echo '</div>'; /* end courseinfo-ack-wrap */
}

echo '</div>'; /* end courseinfo-app */

/* ── Custom box add/edit modal ─────────────────────────────────────────────── */
echo '
<div id="ci-box-modal" style="display:none;position:fixed;inset:0;z-index:99999;background:rgba(0,0,0,0.5);overflow-y:auto;">
  <div id="ci-box-modal-inner" style="background:#fff;border-radius:12px;max-width:640px;margin:40px auto;padding:0;box-shadow:0 20px 60px rgba(0,0,0,0.3);">
    <div id="ci-box-modal-header" style="padding:20px 24px;border-bottom:1px solid #e2e8f0;display:flex;justify-content:space-between;align-items:center;">
      <h4 id="ci-box-modal-title" style="margin:0;font-size:1.1em;font-weight:700;">Add Custom Box</h4>
      <button id="ci-box-modal-close" type="button" style="background:none;border:none;font-size:1.4em;cursor:pointer;color:#64748b;line-height:1;">&times;</button>
    </div>
    <div style="padding:24px;">

      <div class="form-group mb-3">
        <label style="font-weight:600;font-size:0.85em;text-transform:uppercase;letter-spacing:0.05em;color:#64748b;">Icon</label>
        <div id="ci-icon-picker" style="display:flex;flex-wrap:wrap;gap:6px;margin-top:8px;max-height:180px;overflow-y:auto;padding:4px;border:1px solid #e2e8f0;border-radius:8px;"></div>
        <input type="hidden" id="ci-box-icon" value="fa-info-circle">
      </div>

      <div class="form-group mb-3">
        <label for="ci-box-heading" style="font-weight:600;font-size:0.85em;text-transform:uppercase;letter-spacing:0.05em;color:#64748b;">Heading</label>
        <input type="text" id="ci-box-heading" class="form-control mt-1" placeholder="Box heading…">
      </div>

      <div class="form-group mb-3">
        <label style="font-weight:600;font-size:0.85em;text-transform:uppercase;letter-spacing:0.05em;color:#64748b;">Position</label>
        <select id="ci-box-position" class="form-control mt-1"></select>
      </div>

      <div class="form-group mb-0">
        <label style="font-weight:600;font-size:0.85em;text-transform:uppercase;letter-spacing:0.05em;color:#64748b;">Content</label>
        <div id="ci-box-rte-toolbar" style="margin-top:8px;"></div>
        <div id="ci-box-body" contenteditable="true"
             style="min-height:120px;border:1px solid #e2e8f0;border-radius:8px;padding:12px;margin-top:0;outline:none;font-size:14px;line-height:1.7;"></div>
      </div>

    </div>
    <div style="padding:16px 24px;border-top:1px solid #e2e8f0;display:flex;justify-content:flex-end;gap:10px;">
      <button id="ci-box-cancel" type="button" class="btn btn-outline-secondary btn-sm">Cancel</button>
      <button id="ci-box-save" type="button" class="btn btn-primary btn-sm"><i class="fa fa-check mr-1"></i> Save Box</button>
    </div>
  </div>
</div>';

echo $OUTPUT->footer();

function courseinfo_render_volume_panel($vol) {
    /* BUG-CI-VOLCSS: Rewritten to match the section-card design used by AI-generated
       sections. No Bootstrap "card" class, no border-left — full border + border-radius
       + icon circle heading row, identical to JS renderVolumeCard().
       CSS vars --ci-primary / --ci-secondary / --ci-surface are set on
       #courseinfo-volume by applyThemeCssVars() in courseinfo.js. */

    $totalHours   = $vol['total_hours'] ?? $vol['adjusted_total_hours'] ?? 0;
    $nominalHours = $vol['nominal_hours'] ?? 0;
    $compliant    = !empty($vol['compliant']);
    $gapHours     = $vol['gap_hours'] ?? max(0, $nominalHours - $totalHours);
    $sColor       = $compliant ? 'var(--ci-secondary,var(--primary,#34a853))' : '#dc3545';
    $sIcon        = $compliant ? 'fa-check-circle' : 'fa-exclamation-triangle';
    $sText        = $compliant ? 'Compliant' : 'Non-Compliant';

    /* Card wrapper — matches section card design exactly */
    $html  = '<div class="ci-card" style="background:#fff;border:1px solid var(--ci-border,#dbeafe);border-radius:12px;padding:28px 32px;margin-bottom:24px;box-shadow:0 2px 8px rgba(0,0,0,0.07);">';

    /* Icon circle + heading */
    $html .= '<div style="display:flex;align-items:center;gap:14px;margin-bottom:20px;">';
    $html .= '<div class="ci-icon" style="width:44px;height:44px;border-radius:50%;background:var(--ci-surface,#eef2ff);display:flex;align-items:center;justify-content:center;flex-shrink:0;">';
    $html .= '<i class="fa fa-clock-o" style="font-size:20px;color:var(--ci-primary,var(--primary,#1a73e8));"></i></div>';
    $html .= '<h3 style="margin:0;font-size:1.1em;font-weight:700;color:#1e293b;">Volume of Learning Summary</h3>';
    $html .= '</div>';

    /* Stats tiles — flexbox (no Bootstrap grid) */
    $html .= '<div style="display:flex;gap:12px;margin-bottom:20px;">';

    $html .= '<div style="flex:1;padding:14px 12px;background:var(--ci-surface,#eef2ff);border-radius:8px;text-align:center;">';
    $html .= '<div style="font-size:1.9em;font-weight:700;color:var(--ci-primary,var(--primary,#1a73e8));">' . $totalHours . '</div>';
    $html .= '<div style="font-size:0.82em;color:#64748b;margin-top:4px;">Total Hours</div></div>';

    $html .= '<div style="flex:1;padding:14px 12px;background:var(--ci-surface,#eef2ff);border-radius:8px;text-align:center;">';
    $html .= '<div style="font-size:1.9em;font-weight:700;color:#1e293b;">' . $nominalHours . '</div>';
    $html .= '<div style="font-size:0.82em;color:#64748b;margin-top:4px;">Nominal Required</div></div>';

    $html .= '<div style="flex:1;padding:14px 12px;background:var(--ci-surface,#eef2ff);border-radius:8px;text-align:center;">';
    $html .= '<div style="font-size:1.9em;font-weight:700;color:' . $sColor . ';"><i class="fa ' . $sIcon . '"></i></div>';
    $html .= '<div style="font-size:0.82em;color:' . $sColor . ';font-weight:600;margin-top:4px;">' . $sText . '</div></div>';
    $html .= '</div>';

    /* Gap alert */
    if (!$compliant && $gapHours > 0) {
        $html .= '<div style="background:#fff8e6;border:1px solid #f0c040;border-radius:8px;padding:10px 14px;margin-bottom:16px;font-size:0.88em;color:#7a5a00;">';
        $html .= '<i class="fa fa-exclamation-triangle" style="margin-right:6px;"></i>';
        $html .= 'Volume gap: <strong>' . $gapHours . ' hours</strong> below the ' . $nominalHours . ' hr requirement.</div>';
    }

    $colors = array(
        'contentcreator'      => 'var(--ci-primary,#1a73e8)',
        'aiactivities'        => 'var(--ci-secondary,#34a853)',
        'essaymaker'          => '#ea4335',
        'knowledgecheck'      => '#fbbc04',
        'assignment'          => '#673ab7',
        'practicalassessment' => '#ff6d00',
        'other'               => '#607d8b',
    );
    $catOrder = array('contentcreator','aiactivities','essaymaker','knowledgecheck','assignment','practicalassessment','other');

    $html .= '<table style="width:100%;border-collapse:collapse;font-size:0.88em;">';
    $html .= '<thead><tr style="border-bottom:2px solid var(--ci-border,#dbeafe);">';
    $html .= '<th style="padding:8px 4px;text-align:left;font-weight:600;color:#475569;">Category</th>';
    $html .= '<th style="padding:8px 4px;text-align:center;font-weight:600;color:#475569;">Activities</th>';
    $html .= '<th style="padding:8px 4px;text-align:right;font-weight:600;color:#475569;">Hours</th>';
    $html .= '</tr></thead><tbody>';

    if (!empty($vol['sections_breakdown'])) {
        /* ── Section-grouped rows (v1.0.19+) ── */
        foreach ($vol['sections_breakdown'] as $sect) {
            $hasContent = false;
            foreach ($catOrder as $cat) {
                if (!empty($sect['categories'][$cat]['count'])) { $hasContent = true; break; }
            }
            if (!$hasContent) { continue; }

            $html .= '<tr style="background:var(--ci-surface,#eef2ff);">';
            $html .= '<td colspan="3" style="font-weight:600;font-size:0.85em;padding:6px 4px;color:var(--ci-primary,#1a73e8);">';
            $html .= '<i class="fa fa-folder-open-o" style="margin-right:6px;"></i>' . htmlspecialchars($sect['name']) . '</td></tr>';

            foreach ($catOrder as $cat) {
                $catData = $sect['categories'][$cat] ?? null;
                if (!$catData || $catData['count'] <= 0) { continue; }
                $color = $colors[$cat] ?? '#607d8b';
                $html .= '<tr style="border-bottom:1px solid var(--ci-border,#dbeafe);">';
                $html .= '<td style="padding:7px 4px 7px 22px;"><span style="display:inline-block;width:10px;height:10px;background:' . $color . ';border-radius:2px;margin-right:8px;"></span>' . htmlspecialchars($catData['label']) . '</td>';
                $html .= '<td style="padding:7px 4px;text-align:center;">' . (int)$catData['count'] . '</td>';
                $html .= '<td style="padding:7px 4px;text-align:right;font-weight:600;">' . htmlspecialchars((string)$catData['hours']) . ' hrs</td>';
                $html .= '</tr>';
            }
        }
    } else if (!empty($vol['breakdown'])) {
        /* ── Flat rows (backward compat for pre-v1.0.19 scan data) ── */
        foreach ($catOrder as $cat) {
            $data = $vol['breakdown'][$cat] ?? null;
            if (!$data || ($data['minutes'] <= 0 && $data['count'] <= 0)) { continue; }
            $color = $colors[$cat] ?? '#607d8b';
            $html .= '<tr style="border-bottom:1px solid var(--ci-border,#dbeafe);">';
            $html .= '<td style="padding:7px 4px;"><span style="display:inline-block;width:10px;height:10px;background:' . $color . ';border-radius:2px;margin-right:8px;"></span>' . htmlspecialchars($data['label']) . '</td>';
            $html .= '<td style="padding:7px 4px;text-align:center;">' . (int)$data['count'] . '</td>';
            $html .= '<td style="padding:7px 4px;text-align:right;font-weight:600;">' . htmlspecialchars((string)$data['hours']) . ' hrs</td>';
            $html .= '</tr>';
        }
    }

    $html .= '<tr style="border-top:2px solid var(--ci-border,#dbeafe);font-weight:700;">';
    $html .= '<td style="padding:8px 4px;">Total</td><td></td>';
    $html .= '<td style="padding:8px 4px;text-align:right;">' . $totalHours . ' hrs</td></tr>';
    $html .= '</tbody></table>';
    $html .= '</div>'; /* close ci-card */
    return $html;
}
