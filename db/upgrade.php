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

function xmldb_courseinfo_upgrade($oldversion) {
    global $DB;
    $dbman = $DB->get_manager();

    // v1.0.0: Initial release.
    if ($oldversion < 2026032300100) {
        upgrade_mod_savepoint(true, 2026032300100, 'courseinfo');
    }

    // v1.0.1: Free download, 100 credits per generation. Added to AI Plugins menu and Quick Links block.
    if ($oldversion < 2026032300101) {
        upgrade_mod_savepoint(true, 2026032300101, 'courseinfo');
    }

    // v1.0.2: Updated time estimations — CC 30min/slide, LA/KC/VA/EM 2min/question. Warning message above toolbar. NCVER auto-populate JS.
    if ($oldversion < 2026032300102) {
        upgrade_mod_savepoint(true, 2026032300102, 'courseinfo');
    }

    // v1.0.3: Enable Volume of Learning toggle. Removed proportional expansion — shows raw gap instead. Non-accredited course support.
    if ($oldversion < 2026032300103) {
        $table = new xmldb_table('courseinfo');
        $field = new xmldb_field('enablevol', XMLDB_TYPE_INTEGER, '1', null, XMLDB_NOTNULL, null, '0', 'nominalhours');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }
        upgrade_mod_savepoint(true, 2026032300103, 'courseinfo');
    }

    // v1.0.4: ASQA 2025 compliance upgrade. Environment scanning, step-by-step guide, editable output, support services.
    if ($oldversion < 2026032300104) {
        upgrade_mod_savepoint(true, 2026032300104, 'courseinfo');
    }

    // v1.0.5: BUMP — clean release. No DB changes.
    if ($oldversion < 2026032300105) {
        upgrade_mod_savepoint(true, 2026032300105, 'courseinfo');
    }

    // v1.0.6: NCVER nominal hours lookup fixed. Server fetches Nationally-agreed-hours.txt from NCVER (cached 24h).
    //         Lookup button added to settings form. No DB changes.
    if ($oldversion < 2026032300106) {
        upgrade_mod_savepoint(true, 2026032300106, 'courseinfo');
    }

    // v1.0.7: Fixed AI Learning Activities (aiactivities) scanner — was reading wrong DB field (generatedcontent)
    //         instead of activitiesjson, causing scenario count to always be 0 (2 min flat).
    //         Now reads activitiesjson directly as a JSON array and counts items correctly.
    //         Falls back to activitycount setting if activities not yet generated. No DB changes.
    if ($oldversion < 2026032300107) {
        upgrade_mod_savepoint(true, 2026032300107, 'courseinfo');
    }

    // v1.0.8: Major output upgrade. Step-by-step completion guide converted to 3-column table.
    //         World-class design: scroll-reveal animations, hover card elevation, icon spin on hover,
    //         gradient header, coloured type badges, hover table rows. Removed all AI-generated references
    //         from student-facing output. Hidden sections now excluded from scan. Added new ASQA sections:
    //         Completion Requirements and Complaints & Appeals. No DB changes.
    if ($oldversion < 2026032300108) {
        upgrade_mod_savepoint(true, 2026032300108, 'courseinfo');
    }

    // v1.0.9: BUMP — Section order corrected to proper ASQA student journey flow (Entry Requirements and
    //         Fees & Refunds now appear pre-enrolment, before course content). CI_VERSION synced to 1.0.9.
    //         No DB changes.
    if ($oldversion < 2026032300109) {
        upgrade_mod_savepoint(true, 2026032300109, 'courseinfo');
    }

    // v1.0.20: ASQA COMPLIANCE TOGGLE — new enableasqa field (int, DEFAULT 1).
    //          When 0, the generated document omits ASQA-specific sections
    //          (RPL & Credit Transfer, Complaints & Appeals). DEFAULT 1 ensures
    //          all existing courses continue to include compliance sections.
    if ($oldversion < 2026032300120) {
        $table = new xmldb_table('courseinfo');
        $field = new xmldb_field('enableasqa', XMLDB_TYPE_INTEGER, '1', null, XMLDB_NOTNULL, null, '1', 'enablevol');
        if (!$DB->get_manager()->field_exists($table, $field)) {
            $DB->get_manager()->add_field($table, $field);
        }
        upgrade_mod_savepoint(true, 2026032300120, 'courseinfo');
    }

    // v1.0.22: HTTP 500 root-cause fix. ajax.php now requires lib.php explicitly
    //          (preventing Fatal Error: Cannot redeclare courseinfo_require_manage),
    //          moves all init code inside try/catch, and adds require_sesskey().
    //          CI_VERSION in AMD JS updated to 1.0.22. No DB changes.
    if ($oldversion < 2026032400122) {
        upgrade_mod_savepoint(true, 2026032400122, 'courseinfo');
    }

    // v1.0.23: DESKTOP LAYOUT FIX — 7-bug remediation (DOCTYPE/RESPONSIVE/CSSBLEED/
    //          MAXWIDTH/FONTRESET/HTMLSTRIP/JSINJECT). CI_VERSION updated to 1.0.23.
    //          No DB schema changes.
    if ($oldversion < 2026032400123) {
        upgrade_mod_savepoint(true, 2026032400123, 'courseinfo');
    }

    // v1.0.24: NCVER NOMINAL HOURS LOOKUP FIX — 2-bug remediation:
    //          BUG-CI-NCVER403: server now reads bundled local NCVER data file
    //          (server/data/nominal-hours.txt, 67,564 records) as primary source;
    //          HTTP fetch from ncver.edu.au demoted to fallback (Cloudflare-blocked).
    //          BUG-CI-CACHESTALL: cache is now set to empty Map on fetch failure to
    //          prevent retry storms. CI_VERSION updated to 1.0.24. No DB changes.
    if ($oldversion < 2026032400124) {
        upgrade_mod_savepoint(true, 2026032400124, 'courseinfo');
    }

    // v1.0.25: AI CREDIT COST CONFIRMATION POPUP — BUG-CI-CREDITPOPUP:
    //          btn-generate now calls checkCreditsAndGenerate() which checks live
    //          credit balance via ajax.php getcredits, shows ciShowConfirm() modal,
    //          and only proceeds to generateCourseInfo() on teacher confirmation.
    //          CI_VERSION updated to 1.0.25. No DB changes.
    if ($oldversion < 2026032401205) {
        upgrade_mod_savepoint(true, 2026032401205, 'courseinfo');
    }

    // v1.0.26: FONT AWESOME ICON FIX — 2-bug remediation:
    //          BUG-CI-ICONBREAK: styles.css wildcard *{font-family:inherit!important}
    //          overrode Font Awesome's required font-family on <i class="fa fa-*">
    //          elements, causing section icons to render as "li" text. Fixed:
    //          wildcard changed to *:not(i); positive FA restore rule added at
    //          higher specificity; belt-and-suspenders style block added in view.php.
    //          BUG-CI-ICONHTML: AI generated text-content icons instead of empty
    //          <i class="fa fa-*"></i>. Fixed: server-side regex strips text from
    //          FA <i> elements post-generation; fixFaIconsInDom() strips residual
    //          text nodes on DOM init and after every inject; prompt updated with
    //          exact icon HTML template and mandatory per-section icon assignments.
    //          CI_VERSION updated to 1.0.26. No DB changes.
    if ($oldversion < 2026032401206) {
        upgrade_mod_savepoint(true, 2026032401206, 'courseinfo');
    }

    // v1.0.27: CSS CONSISTENCY + BOX UX + HERO COLOUR — 4 bugs fixed + feature:
    //          BUG-CI-BOXCSS: border-left:4px on rounded custom boxes removed.
    //          BUG-CI-VOLCSS: Volume of Learning card redesigned to match ci-card.
    //          BUG-CI-BOXPLACE: position dropdown now uses existing box ordering
    //            with offset values to avoid sort collisions.
    //          BUG-CI-BOXMOVE: moveBox() now uses sorted visual index, not unsorted
    //            array index. Boundary check and swap direction are now correct.
    //          FEATURE-HEROBG: Hero banner colour picker added to teacher toolbar.
    //            Colour persisted via getherocolor / saveherocolor plugin config
    //            (no DB schema changes — Moodle plugin_config table only).
    //          CI_VERSION updated to 1.0.27. No DB schema changes.
    if ($oldversion < 2026032401207) {
        upgrade_mod_savepoint(true, 2026032401207, 'courseinfo');
    }

    // v1.0.28: SCAN RESULTS CSS CONSISTENCY — 1 bug fixed:
    //          BUG-CI-SCANCSS: renderScanResults() used Bootstrap class=card +
    //            border-left:4px solid + card-header/card-body Bootstrap classes +
    //            table-sm table-striped + text-right Bootstrap utilities.
    //            Rewrote to ci-card design matching all other panels: full border,
    //            border-radius:12px, icon-circle heading row, themed inline-style
    //            table (alternating rows, no Bootstrap dependency).
    //          CI_VERSION updated to 1.0.28. No DB schema changes.
    if ($oldversion < 2026032401208) {
        upgrade_mod_savepoint(true, 2026032401208, 'courseinfo');
    }

    // v1.0.29: SCAN RESULTS CSS CORRECTNESS — 5 bugs fixed:
    //          BUG-CI-VOL-NESTED: volume panel was nested INSIDE scan results card div
    //            (rendered before closing </div>). Fixed: card closed first, volume
    //            panel appended as a sibling inside #courseinfo-scan-results.
    //          BUG-CI-CSSVAR-SCAN: applyThemeCssVars() omitted 'courseinfo-scan-results'
    //            from the forEach list; CSS vars not set on that container.
    //          BUG-CI-SCAN-NOSWATCH: Activity column had no type colour swatch after
    //            Bootstrap-removal rewrite. Added 10x10px swatches keyed by type.
    //          BUG-CI-SCAN-RAWTYPE: Type column showed raw modnames. Added friendlyTypes
    //            lookup map covering all 10 recognised activity types.
    //          BUG-CI-BOOTSTRAP-MB: class="mb-2" and class="badge" still used in env
    //            badge section. Replaced with inline styles throughout.
    //          CI_VERSION updated to 1.0.29. No DB schema changes.
    if ($oldversion < 2026032401209) {
        upgrade_mod_savepoint(true, 2026032401209, 'courseinfo');
    }

    // v1.0.30: BUG-CI-ICONFA6 (font-weight:900 for FA6 Solid),
    //          BUG-CI-HEROUI (hero colour picker discoverability — btn-outline-info +
    //            frosted overlay button on .ci-header + always-rendered hidden label),
    //          BUG-CI-BOXWIDTH (negative-margin stripping in both sanitizers +
    //            CSS containment on #courseinfo-generated > div).
    //          CI_VERSION updated to 1.0.30. No DB schema changes.
    if ($oldversion < 2026032401210) {
        upgrade_mod_savepoint(true, 2026032401210, 'courseinfo');
    }

    // v1.0.31: CARD BORDER + SHADOW CONSISTENCY — BUG-CI-CARD-BORDER.
    //          styles.css uses border shorthand + all four border-side properties with
    //          !important to override inline border-left on stored AI-generated HTML.
    //          Box-shadow added to match fresh card style. CI_VERSION → 1.0.31.
    //          No DB schema changes.
    if ($oldversion < 2026032401211) {
        upgrade_mod_savepoint(true, 2026032401211, 'courseinfo');
    }

    // v1.0.32: GENERATED TABLE CELL PADDING — BUG-CI-TABLE-PADDING:
    //          Bottom AI-generated table (#courseinfo-generated table td/th) lacked
    //          the 8px 12px cell padding applied to the top data table
    //          (#courseinfo-content table td/th). Added matching padding, border, and
    //          header background via !important rules in styles.css. CI_VERSION → 1.0.32.
    //          No DB schema changes.
    if ($oldversion < 2026032401212) {
        upgrade_mod_savepoint(true, 2026032401212, 'courseinfo');
    }

    // v1.0.33: ASYNC GENERATION — Eliminated Replit proxy 120s timeout failures.
    //          JS calls action=generate_async → PHP hits Express /api/moodle/courseinfo/start
    //          → returns {jobId} in ~500ms. JS polls action=poll every 3s → Express
    //          GET /api/jobs/:jobId. When status=done, applyResult() processes payload
    //          identically to the former sync response. Internal loopback bypasses proxy
    //          hard limit. CI_VERSION → 1.0.33. No DB schema changes.
    if ($oldversion < 2026032401213) {
        upgrade_mod_savepoint(true, 2026032401213, 'courseinfo');
    }

    // v1.0.34: VERSION BUMP — Clean release following master release process.
    //          No code changes beyond v1.0.33. CI_VERSION → 1.0.34. No DB schema changes.
    if ($oldversion < 2026032401234) {
        upgrade_mod_savepoint(true, 2026032401234, 'courseinfo');
    }

    // v1.0.35: ICON + BANNER WIDTH — DEFINITIVE 2-BUG REMEDIATION:
    //   BUG-CI-ICONBREAK-STYLE: sanitizeGeneratedHtml() (JS + server routes.ts) now strips
    //     font-family from ALL CSS rules in ALL generated <style> blocks, not just rules
    //     with #courseinfo-generated prefix. Root cause: AI generates unscoped rules like
    //     "* { font-family: 'Inter' }" that survive the old narrow regex, become globally
    //     active CSS when injected into the DOM, and can override Font Awesome's font-family
    //     even on elements targeted by our !important rules (cascade source order wins on
    //     equal specificity). Server-side regex in routes.ts fixed identically.
    //   BUG-CI-ICONBREAK-JS: fixFaIconsInDom() now forces FontAwesome font via
    //     el.style.setProperty('font-family', ..., 'important') — an inline !important
    //     set via JS setProperty beats ALL CSS rules regardless of specificity or source
    //     order. This is the definitive fix: no AI-generated <style> block can override it.
    //     Also expands selector to include span.fa for non-<i> FA usage.
    //   BUG-CI-BANNERWIDTH: styles.css adds #courseinfo-generated .ci-header rule with
    //     width:100% !important, max-width:none !important to force the hero banner to
    //     always span the full content-column width, matching the AI Course Format banner.
    //     sanitizeGeneratedHtml() max-width regex expanded from 900px-only to any px value.
    //   CI_VERSION → 1.0.35. No DB schema changes.
    if ($oldversion < 2026032401235) {
        upgrade_mod_savepoint(true, 2026032401235, 'courseinfo');
    }

    // v1.0.36: DBWRITE AUDIT FIX — All write operations in ajax.php (generate,
    //   save, saveboxes, saveherocolor) now verify require_sesskey() and enforce
    //   courseinfo_require_manage() capability before touching the DB.
    //   No DB schema changes.
    if ($oldversion < 2026032401236) {
        upgrade_mod_savepoint(true, 2026032401236, 'courseinfo');
    }

    // v1.0.37: CSS FIX (2 bugs) — styles.css only, no DB schema changes.
    //   BUG-CI-TH-COLOR: AI generates table headers with color:white inline
    //     (designed for gradient bg). CSS override forced background:#f5f7fa
    //     but left color:white, making header text invisible on the light bg.
    //     Fixed: added color:#1e293b !important to th rule.
    //   BUG-CI-STEPNUM: Step-number circles used display:inline-block +
    //     line-height for centering. Moodle theme interference caused numbers
    //     to appear above centre. Fixed: td:first-child children now use flex.
    if ($oldversion < 2026040201237) {
        upgrade_mod_savepoint(true, 2026040201237, 'courseinfo');
    }

    // v1.0.38: AUDIT-LOCKED STRUCTURED JSON — ChatGPT compliance review applied.
    //   The SaaS API (/api/moodle/course-info/generate) now returns a "structured"
    //   key alongside "html" containing an audit-compliance JSON schema with
    //   field-level enforcement across 8 sections (overview, course_identity,
    //   course_introduction, target_audience, content_delivery, assessment_safety,
    //   course_fees, pathways_outcomes). New PHP classes:
    //     - classes/courseinfo_structure.php — canonical audit schema definition
    //     - classes/courseinfo_compliance.php — field-level compliance validator
    //   ajax.php (generate case) now:
    //     - Extracts structured data from API response
    //     - Validates it with courseinfo_compliance::validate()
    //     - Saves audit schema to the existing generatedjson column (no new columns)
    //     - Returns compliance status and score to the frontend JS
    //   No DB schema changes (generatedjson column added in prior release).
    if ($oldversion < 2026040201238) {
        upgrade_mod_savepoint(true, 2026040201238, 'courseinfo');
    }

    // v1.0.41: ASQA 2025 COMPLIANCE + LAYOUT TIGHTEN:
    //   AI system prompt updated with 4 new sections and 7 updated sections:
    //   NEW: Course Outcomes, Course Structure, Learner Rights & Responsibilities,
    //     Privacy & Data Protection.
    //   UPDATED: Course Overview (accreditation status disclosure), Entry Requirements
    //     (minimum age sub-section), Course Duration (estimated hour range),
    //     Completion Requirements (explicit AQF/certification type statement),
    //     Fees & Refunds (policy link reference), Support Services (Technical/LLN/
    //     Learning types), Assessment Summary (reassessment policy paragraph).
    //   Layout tightened: hero padding 28/24px, hero title 22px, section headings 18px,
    //     body text 13.5px, card padding 18/22px, card gap 14px, icon circles 34px.
    //   Changes are system-prompt-only in routes.ts — no PHP or DB schema changes.
    //   Existing generated HTML in courseinfo.generatedhtml is unaffected; teachers
    //   must click Regenerate to apply the new sections.
    //   CI_VERSION updated to 1.0.41. version.php → 2026041700041.
    if ($oldversion < 2026041700041) {
        upgrade_mod_savepoint(true, 2026041700041, 'courseinfo');
    }

    // v1.0.42: LEARNER ACKNOWLEDGEMENT CHECKBOX + ASQA MAX EFFORT SYSTEM PROMPT:
    //   NEW TABLE: courseinfo_ack — timestamped audit trail of learner acknowledgements.
    //     Fields: id, courseinfoid (FK), userid, timeacknowledged (unix ts),
    //             ipaddress (IPv4/IPv6, 45 chars), useragent (text).
    //     Unique index on (courseinfoid, userid) — one record per learner per activity.
    //     This table provides ASQA audit evidence that students received and read
    //     pre-enrolment information before commencing training.
    //   PHP CHANGES:
    //     - view.php: Students see a formal acknowledgement section below the generated
    //       content. If not yet acknowledged: checkbox "I have read and understood this
    //       course information" + Acknowledge button. If already acknowledged: green
    //       confirmation card with the acknowledgement timestamp. Teachers see an
    //       acknowledgement count badge ("N student(s) have acknowledged").
    //     - ajax.php: New 'acknowledge' action — saves acknowledgement record (or returns
    //       existing record if already done); prevents duplicate submissions. New
    //       'getackcount' action — returns count of acknowledgements for teacher view.
    //   SYSTEM PROMPT IMPROVEMENTS (13 ASQA enhancements in routes.ts):
    //     1. Entry Req — LLN formal assessment mention (ACSF framework reference).
    //     2. Entry Req — USI requirement for accredited courses (usi.gov.au).
    //     3. Completion Requirements — NCVER reporting disclosure.
    //     4. Support Services — CALD Support card, LLN Support card, Wellbeing card.
    //     5. Learner Rights — 4 additional rights (training records, reasonable
    //        adjustment, safe environment, non-discrimination).
    //     6. Learner Rights — statutory reference (Standards for RTOs 2015 + ACL).
    //     7. RPL & Credit Transfer — major expansion: separate RPL and Credit Transfer
    //        sub-sections with evidence types, process timeline, who to contact.
    //     8. Complaints & Appeals — major expansion: acknowledgement (5 business days),
    //        resolution target (30 days), ASQA external escalation pathway, appeals
    //        timeframe (60 days from result), no disadvantage guarantee.
    //     9. Privacy — AVETMISS reporting to NCVER, USI data sharing (Student
    //        Identifiers Act 2014), state/territory training authority disclosure.
    //    10. New Section: Consumer Rights Notice — Australian Consumer Law (ACL /
    //        Competition and Consumer Act 2010), ACCC reference.
    //    11. New Section: Learner Acknowledgement Statement — printed sign-off form
    //        at end of document for hardcopy audit file evidence.
    //    12. Section order in GENERATION INSTRUCTIONS updated to include new sections.
    //    13. Structured JSON return updated with acknowledgement_record compliance flags.
    if ($oldversion < 2026041700042) {
        $dbman = $DB->get_manager();

        $table = new xmldb_table('courseinfo_ack');
        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
        $table->add_field('courseinfoid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_field('userid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_field('timeacknowledged', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_field('ipaddress', XMLDB_TYPE_CHAR, '45', null, null, null, null);
        $table->add_field('useragent', XMLDB_TYPE_TEXT, null, null, null, null, null);
        $table->add_key('primary', XMLDB_KEY_PRIMARY, array('id'));
        $table->add_index('courseinfoid', XMLDB_INDEX_NOTUNIQUE, array('courseinfoid'));
        $table->add_index('userid', XMLDB_INDEX_NOTUNIQUE, array('userid'));
        $table->add_index('courseinfo_user', XMLDB_INDEX_UNIQUE, array('courseinfoid', 'userid'));

        if (!$dbman->table_exists($table)) {
            $dbman->create_table($table);
        }

        upgrade_mod_savepoint(true, 2026041700042, 'courseinfo');
    }

    // v1.0.43: WORLD-CLASS VISUAL OVERHAUL — AI PROMPT DESIGN SYSTEM UPGRADE.
    //   System prompt enhanced with Quick Stats strip, hero value proposition,
    //   text density rules, bold action verbs in outcomes, 3-column support grid.
    //   System-prompt-only change — no DB schema changes.
    if ($oldversion < 2026042000043) {
        upgrade_mod_savepoint(true, 2026042000043, 'courseinfo');
    }

    // v1.0.44: FIX-CI-FULLNAME-FIELDS — ajax.php (acknowledgement list) loaded the user
    //   record with only id,firstname,lastname,email, so calling fullname() emitted a
    //   Moodle 4.x debugging() warning ("name fields missing: firstnamephonetic,
    //   lastnamephonetic, middlename, alternatename") that leaked into the JSON payload
    //   and broke the teacher report's acknowledgement table render. Fix: include all six
    //   name fields in the get_record() field list. PHP only — no DB schema changes.
    if ($oldversion < 2026042100044) {
        upgrade_mod_savepoint(true, 2026042100044, 'courseinfo');
    }
    // v1.0.45: AMD ENCODING FIX: All non-ASCII characters (em dashes, arrows, box-drawing chars, ellipsis, bullets, emoji, accented Latin) scrubbed from all AMD JS files (amd/src, amd/build, amd/build/*.min.js). Root cause of Moodle primary/secondary navigation menus disappearing site-wide: non-ASCII bytes in any installed plugin's AMD file cause a SyntaxError inside RequireJS's first.js bundle, throwing "No define call for core/first" and aborting the entire AMD module chain. No PHP, DB schema, or functional changes in this release.
    if ($oldversion < 2026042200045) {
        upgrade_mod_savepoint(true, 2026042200045, 'courseinfo');
    }
    // v1.0.46: PREMIUM DESIGN UPGRADE (styles.css + AI prompt): gradient icon circles (primary-to-dark gradient with white icons), left-side primary-colour accent bar on all section cards, glassmorphism tagline strip in hero banner with decorative depth circle, bold step-number gradient circles, ci-time-pill class for consistent time pills, upgraded Learner Acknowledgement box (gold gradient background, stamp watermark, dashed signature separator, shield note icon), refined quick stats strip (gradient icon circles, heavier font weights), support grid cards with gradient icon tops, retroactive CSS improvements to existing stored content. No PHP, DB schema, or AMD changes.
    if ($oldversion < 2026043000046) {
        upgrade_mod_savepoint(true, 2026043000046, 'courseinfo');
    }

    // v1.0.47: TYPOGRAPHY LOCKDOWN + BREVITY MANDATE + GLANCE STRIP — addresses
    //   "text grows as you scroll" and "AI output too verbose" complaints.
    //   styles.css: 14 chaotic font-size declarations (mix of em + px) collapsed
    //     into a single px-based scale via 7 :root CSS custom properties
    //     (--ci-fs-label/caption/body/h3/h2/metric and --ci-fs-hero=22px), with
    //     !important on h2/h3/p/li/span. Card accent bar 4px → 2px (also in
    //     @media print). Icon hover softened (1.06 scale only, no rotate).
    //     Card hover shadow softened.
    //   server/routes.ts: AI system prompt now enforces hard per-section word
    //     caps (BREVITY MANDATE), bans hedge words (LEAN-WRITING RULES),
    //     mandates a compact GLANCE STRIP (≈X min read · N sections · ASQA
    //     2025 · English) immediately after the hero, and wraps Privacy /
    //     RPL-B / Complaints-B detail in <details>/<summary> collapses.
    //     Post-processing pipeline strips every font-size declaration from
    //     every AI <style> block (inline styles preserved for FA icon glyphs).
    //   No PHP, DB schema, or AMD changes.
    if ($oldversion < 2026050100047) {
        upgrade_mod_savepoint(true, 2026050100047, 'courseinfo');
    }

    // v1.0.48: STAT-TILE LABEL/VALUE ORDER FIX (FIX-CI-STAT-ORDER) — addresses
    //   the "body text is sized like a heading" complaint about the Quick
    //   Stats Strip. Each tile now renders the small uppercase label above
    //   the prominent value (label-as-heading, value-as-body), matching
    //   definition-list reading order.
    //   server/routes.ts: AI prompt for QUICK STATS STRIP now mandates
    //     label-first then value-second DOM order with explicit example so
    //     freshly generated documents are accessibility-correct.
    //   styles.css: flexbox `order` property pins .ci-stat-label to visual
    //     position 1 and .ci-stat-value to visual position 2 regardless of
    //     DOM order, so already-stored documents render correctly after the
    //     plugin upgrade with no regenerate required.
    //   No PHP, DB schema, or AMD changes.
    if ($oldversion < 2026050100048) {
        upgrade_mod_savepoint(true, 2026050100048, 'courseinfo');
    }

    if ($oldversion < 2026072300227) {
        // FIX-API-DOMAIN: Updated all API endpoint URLs from lms-labs.com to lms-labs.com.
        // lms-labs.com has no DNS resolution from Moodle server side; lms-labs.com is the
        // correct working domain. All ajax.php, api_client, unlock_verifier, lib.php calls updated.
        if (function_exists('opcache_invalidate')) {
            $_pluginDir = realpath(__DIR__ . '/..');
            foreach (['version.php', 'db/upgrade.php'] as $_f) {
                $_full = $_pluginDir . '/' . $_f;
                if (file_exists($_full)) {
                    opcache_invalidate($_full, true);
                }
            }
        } elseif (function_exists('opcache_reset')) {
            opcache_reset();
        }
        upgrade_mod_savepoint(true, 2026072300227, 'courseinfo');
    }

    if ($oldversion < 2026072300228) {
        // FIX-API-DOMAIN: Reverted API endpoint to lms-labs.com (correct domain).
        // essaygraderai.app was the original single-plugin domain; lms-labs.com is correct.
        if (function_exists('opcache_invalidate')) {
            $_pluginDir = realpath(__DIR__ . '/..');
            foreach (['version.php', 'db/upgrade.php'] as $_f) {
                $_full = $_pluginDir . '/' . $_f;
                if (file_exists($_full)) { opcache_invalidate($_full, true); }
            }
        } elseif (function_exists('opcache_reset')) { opcache_reset(); }
        upgrade_mod_savepoint(true, 2026072300228, 'courseinfo');
    }

    if ($oldversion < 2026072300229) {
        // Domain update: lms-labs.com → lms-labs.com
        if (function_exists('opcache_invalidate')) {
            $_pluginDir = realpath(__DIR__ . '/..');
            foreach (['version.php', 'lib.php', 'db/upgrade.php'] as $_f) {
                $_full = $_pluginDir . '/' . $_f;
                if (file_exists($_full)) { opcache_invalidate($_full, true); }
            }
        } elseif (function_exists('opcache_reset')) { opcache_reset(); }
        upgrade_mod_savepoint(true, 2026072300229, 'courseinfo');
    }

    return true;
}