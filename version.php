<?php
// This file is part of Moodle - http://moodle.org/

/**
 * AI Course Information plugin v1.0.48 version information.
 *
 * v1.0.48: STAT-TILE LABEL/VALUE ORDER FIX (FIX-CI-STAT-ORDER) — addresses the
 *   "body text is sized like a heading, why is this wrong?" complaint about
 *   the Quick Stats Strip. Each tile (DURATION, DELIVERY, TYPE, PROVIDER) was
 *   rendering the prominent value (e.g. "1–2 Hours", "Online · Self-Paced")
 *   on top with the small uppercase label below it, which read like a
 *   dashboard metric tile rather than a labelled metadata field. Auditors
 *   and learners expect the label to act as the heading and the value to
 *   act as the body — i.e. label on top, value beneath, like a definition
 *   list.
 *   FIX 1 — server/routes.ts AI prompt for QUICK STATS STRIP: DOM order is
 *     now mandated as label-first then value-second (with an explicit
 *     example), so the rendered HTML matches the visual order and is
 *     accessibility-correct (screen readers read "DURATION 1–2 Hours"
 *     instead of "1–2 Hours DURATION").
 *   FIX 2 — styles.css: applied the flexbox `order` property to .ci-stat-label
 *     (order:1) and .ci-stat-value (order:2) so the visual stack is pinned
 *     label-on-top regardless of the DOM order in the stored HTML. This
 *     means already-generated documents in the database render correctly
 *     immediately after the plugin upgrade with no regenerate required.
 *   No PHP, DB schema, or AMD changes. version.php → 2026050100048.
 *
 * v1.0.47: TYPOGRAPHY LOCKDOWN + BREVITY MANDATE + GLANCE STRIP — "TEXT GROWS
 *   AS YOU SCROLL" + "AI OUTPUT TOO VERBOSE" REMEDIATION:
 *   ROOT CAUSE 1 (text-grows-as-you-scroll): styles.css contained 14 separate
 *     font-size declarations using a chaotic mix of em values (1em, 1.05em,
 *     1.1em, 1.5em) and px values (10.5–22px). em values cascade-multiply
 *     with the parent's font-size, so once any AI-generated wrapper set a
 *     parent font-size, all child elements grew. The AI's <style> block was
 *     also free to emit its own font-size rules that competed with the
 *     plugin's, producing visible inconsistency from the top of the page
 *     (where the hero is sized in px) to the bottom (where deep cards
 *     compounded em multiplications and AI overrides).
 *   ROOT CAUSE 2 (AI output too verbose): The system prompt asked for ASQA
 *     2025 disclosure breadth but never gave the AI a per-section word cap,
 *     so each section grew to 200–400 words and the document became a
 *     scrollable wall of text rather than a scannable pre-enrolment summary.
 *   FIX 1 — Single px-based scale via CSS custom properties (styles.css):
 *     :root tokens — --ci-fs-label 11, --ci-fs-caption 12, --ci-fs-body 13.5,
 *     --ci-fs-h3 15, --ci-fs-h2 17, --ci-fs-metric 16, --ci-fs-hero 22 (with
 *     --ci-lh-tight 1.3 / --ci-lh-body 1.55 / --ci-lh-loose 1.7). All 14
 *     font-size declarations rewritten to use these tokens. Every body
 *     element rule (p, li, span, h2, h3) carries !important so it wins over
 *     the AI's non-!important inline styles via the cascade.
 *   FIX 2 — Server-side font-size stripper for AI <style> blocks (routes.ts):
 *     Post-processing pipeline now runs a regex over every <style> block in
 *     the AI output and removes every font-size declaration. Inline
 *     style="font-size:..." attributes on individual elements are PRESERVED
 *     because they are functional sizing for FA icon glyphs (e.g.
 *     <i class="fa" style="font-size:13px">) — the plugin's CSS !important
 *     rules on body elements already beat the AI's non-!important inline
 *     font-size declarations on h2/h3/p/li/span via cascade specificity.
 *   FIX 3 — BREVITY MANDATE in system prompt (routes.ts): Hard per-section
 *     word caps added: Course Overview ≤60 words, Outcomes ≤5 bullets at
 *     ≤12 words each, Entry Requirements ≤25 words per sub-section, Mode
 *     of Delivery ≤40 words, Course Duration ≤30 words, Course Structure
 *     ≤30 words intro then table only, Fees & Refunds ≤4 bullets at ≤12
 *     words, Step-by-Step Guide ≤6 steps at ≤15 words each, Assessment
 *     Summary ≤25 words intro then table only, Completion Requirements ≤4
 *     bullets at ≤12 words, Support Services ≤3 cards at ≤18 words,
 *     Learner Rights ≤6 bullets at ≤14 words, RPL ≤50 words per sub-
 *     section, Complaints ≤60 words per sub-section + ASQA escalation ≤25
 *     words, Privacy ≤4 bullets at ≤14 words, Consumer Rights ≤30 words.
 *     Learner Acknowledgement Statement remains exempt (legally prescribed).
 *   FIX 4 — LEAN-WRITING RULES: Hedge-word ban (simply, in order to, please
 *     note that, it is important that), prefer noun-then-verb sentences,
 *     prefer bullets over prose, no heading-restating paragraphs.
 *   FIX 5 — GLANCE STRIP: New compact summary row rendered immediately after
 *     the hero, before the Quick Stats Strip. Four cells separated by thin
 *     vertical dividers: ≈X min read (computed from total word count / 220),
 *     N sections, ASQA 2025, English. Gives auditors and learners an
 *     instant scan-time and compliance snapshot without scrolling.
 *   FIX 6 — DEEP-DETAIL COLLAPSE: Privacy & Data Protection, RPL Sub-section
 *     B (Credit Transfer detail), and Complaints Sub-section B (Assessment
 *     Appeals + External Escalation) now render their long-form content
 *     inside <details>/<summary> elements that are collapsed by default. A
 *     1-line summary (first paragraph or first 2 bullets) stays visible
 *     above the collapse so learners always see the headline; the full
 *     legalistic disclosure remains one click away for audit/print.
 *   FIX 7 — VISUAL TIGHTEN: Card accent bar reduced from 4px → 2px (also in
 *     @media print). Icon hover softened: removed the 5° rotate, kept only
 *     the subtle 1.06 scale. Card hover shadow softened from 0 8px 22px
 *     rgba(0,0,0,0.13) to 0 4px 14px rgba(0,0,0,0.08).
 *   PHP CHANGES: None (no schema, no .php). System-prompt and CSS only.
 *   Existing generated HTML is unaffected by the prompt changes — teachers
 *   must click "Regenerate Course Information" to pick up the BREVITY
 *   MANDATE and the GLANCE STRIP. The TYPOGRAPHY LOCKDOWN (CSS + server-
 *   side <style> stripper) takes effect immediately on next page load
 *   regardless of when the HTML was generated.
 *   CI_VERSION updated to 1.0.47. version.php → 2026050100047.
 *
 * v1.0.43: WORLD-CLASS VISUAL OVERHAUL — AI PROMPT DESIGN SYSTEM UPGRADE:
 *   System prompt in routes.ts enhanced with 5 design improvements to produce
 *   more visually stunning, premium-quality, scan-friendly course information:
 *   1. QUICK STATS STRIP: A horizontal row of 4 stat tiles (Duration, Delivery,
 *      Type, Provider) renders directly below the hero banner and above the
 *      Overview card. Each tile has an icon circle, bold value, and UPPERCASE
 *      label — gives auditors and students instant at-a-glance course facts.
 *   2. HERO VALUE PROPOSITION: An italic tagline sentence is now added below the
 *      organisation name in the hero banner, describing the learner's key benefit
 *      from completing the course. Makes the hero immediately engaging.
 *   3. TEXT DENSITY RULE: Explicit "max 2 sentences per paragraph" and "prefer
 *      bullet lists for 3+ items" density rule added to the prompt. Prevents
 *      walls of text and ensures the document is scannable in under 30 seconds.
 *   4. COURSE OUTCOMES — BOLD ACTION VERBS: The opening action verb of each
 *      outcome bullet is now wrapped in <strong> tags styled in the primary
 *      colour, making the action words visually pop and aiding quick scanning.
 *   5. SUPPORT SERVICES — 3-COLUMN GRID: Changed from 2-column to 3-column CSS
 *      grid layout for the support cards, making better use of horizontal space
 *      and reducing vertical scrolling through the support section.
 *   System-prompt-only change — no PHP, AMD JS, or DB schema changes.
 *   Existing generated HTML in courseinfo.generatedhtml is unaffected; teachers
 *   must click "Regenerate Course Information" to apply the new design.
 *   CI_VERSION updated to 1.0.43. version.php → 2026042000043.
 *
 * v1.0.42: LEARNER ACKNOWLEDGEMENT CHECKBOX + ASQA MAX EFFORT SYSTEM PROMPT:
 *   NEW TABLE: courseinfo_ack — timestamped audit trail of learner acknowledgements.
 *     Fields: id, courseinfoid (FK to courseinfo.id), userid, timeacknowledged (unix ts),
 *             ipaddress (IPv4/IPv6, 45 chars), useragent (text, up to 1000 chars).
 *     Unique index on (courseinfoid, userid) — one record per learner per activity
 *     instance. Provides ASQA audit evidence of pre-enrolment disclosure.
 *   PHP CHANGES:
 *     view.php — Student view adds a formal acknowledgement section below generated
 *       content. Unacknowledged: amber box with checkbox + "Acknowledge" button.
 *       Acknowledged: green confirmation card with date/time of acknowledgement.
 *       Teacher view: acknowledgement count badge in toolbar ("N students acknowledged")
 *       and an audit trail card below content showing total count + ASQA purpose.
 *     ajax.php — New 'acknowledge' action saves acknowledgement record with timestamp
 *       and IP address; prevents duplicate submissions; returns date string for JS.
 *       New 'getackcount' action returns count + top-50 list of acknowledgements
 *       (userid, full name, email, date, IP) for teacher reporting/export.
 *   SYSTEM PROMPT IMPROVEMENTS (13 ASQA enhancements):
 *     1. Entry Requirements: LLN bullet expanded with ACSF framework reference and
 *        formal LLN assessment process disclosure.
 *     2. Entry Requirements: New USI sub-bullet for accredited courses — creation at
 *        usi.gov.au, enrolment verification, pre-certification requirement.
 *     3. Support Services: 3 new support cards — Language & Literacy Support (LLN),
 *        CALD Support (Culturally & Linguistically Diverse), Personal Wellbeing Support.
 *     4–5. Learner Rights: 4 additional rights — training record access, reasonable
 *        adjustment, safe environment, non-discrimination. Plus statutory reference to
 *        Standards for RTOs 2015, ACL, Privacy Act 1988, anti-discrimination legislation.
 *     6. Learner Responsibilities: USI maintenance obligation added.
 *     7. RPL & Credit Transfer: Complete rewrite — two separate sub-sections (RPL and
 *        Credit Transfer) with evidence types, 10–15 business day assessment timeline,
 *        3–5 day CT verification, no-charge guarantee, legislative basis.
 *     8. Complaints & Appeals: Complete rewrite — two sub-sections (Complaints Process
 *        and Assessment Appeals). Formal process with 5-business-day acknowledgement,
 *        30-day resolution target, 60-day appeal window, 10-day appeal decision.
 *        ASQA external escalation pathway at asqa.gov.au added explicitly.
 *     9. Privacy: AVETMISS reporting to NCVER (legislative requirement), USI Registry
 *        data sharing under Student Identifiers Act 2014, state/territory training
 *        authority disclosure for funding validation.
 *    10. New Section 17: Consumer Rights Notice — Australian Consumer Law (ACL /
 *        Competition and Consumer Act 2010, Schedule 2), ACCC reference, state agency
 *        contacts (Consumer Affairs VIC, NSW Fair Trading, QLD OFT).
 *    11. New Section 18: Learner Acknowledgement Statement — print-ready signed
 *        declaration with 3-column signature grid (name, date/USI, witness). Rendered
 *        visibly on-screen and formatted for print/audit file.
 *    12. Section order updated to include Consumer Rights Notice + Learner
 *        Acknowledgement Statement before Volume of Learning.
 *    13. Structured JSON return gains "acknowledgement_record" compliance flags:
 *        usi_disclosed, lln_assessment_disclosed, avetmiss_disclosed,
 *        complaints_process_disclosed, rpl_disclosed, consumer_rights_disclosed,
 *        learner_acknowledgement_included.
 *   Existing generated HTML is unaffected — teachers must click Regenerate to apply
 *   the new sections. CI_VERSION updated to 1.0.42. version.php → 2026041700042.
 *
 * v1.0.41: ASQA 2025 COMPLIANCE + LAYOUT TIGHTEN — 4 NEW SECTIONS + 7 SECTION UPDATES:
 *   AI system prompt in routes.ts updated to address all ASQA pre-enrolment and
 *   pre-commencement disclosure requirements identified in compliance review.
 *   NEW SECTIONS ADDED:
 *     - Course Outcomes (section 3): 4-6 action-verb bullet points describing
 *       what students will be able to do after completing the course. Based on
 *       course name, unit title, and scanned activities. ASQA requires students
 *       to know what skills/knowledge they gain BEFORE they enrol.
 *     - Course Structure (section 7): 2-column grid of module/section cards showing
 *       what content is included. Addresses ASQA requirement for students to know
 *       the course structure and topics covered before commencing.
 *     - Learner Rights & Responsibilities (section 13): Two-column layout listing
 *       student rights and responsibilities. Required by ASQA Standards — covers code
 *       of conduct, academic integrity, fair treatment, and appeal rights.
 *     - Privacy & Data Protection (section 16): Compact card disclosing how student
 *       data is handled under the Privacy Act 1988 (Cth). Addresses ASQA expectation
 *       and Privacy Act compliance.
 *   UPDATED SECTIONS:
 *     - Course Overview: Now includes mandatory accreditation statement (italic,
 *       secondary colour) — "This is an accredited unit of competency (nationally
 *       recognised training under the AQF)" or "This is a non-accredited course. It
 *       does not lead to a nationally recognised qualification under the AQF."
 *     - Entry Requirements: Added Minimum Age sub-section — students must be 18+,
 *       or 16–17 with guardian consent.
 *     - Course Duration: Now generates an estimated hour range (e.g. "2–3 hours")
 *       derived from scanned activity times. No longer just "Self-paced".
 *     - Completion Requirements: Now explicitly states AQF/certification type —
 *       accredited courses: "Statement of Attainment (nationally recognised under the
 *       AQF)"; non-accredited: "Certificate of Completion (non-accredited — not
 *       nationally recognised under the AQF)."
 *     - Fees & Refunds: Now references the organisation's Fees Schedule and Refund
 *       Policy documents. No longer just a "contact us" statement.
 *     - Support Services: Now lists three specific support types — Technical Support
 *       (LMS access), Learning Support (course content guidance), LLN Support
 *       (language/literacy/numeracy assistance).
 *     - Assessment Summary: Now includes an "Assessment Requirements" paragraph
 *       after the table — covering competency standard, feedback, and reattempt policy.
 *   LAYOUT TIGHTENED (system prompt design spec changes):
 *     Hero padding: 40/32px → 28/24px; Hero title: 28px → 22px;
 *     Section headings: 22px → 18px; Body text: 14.5px → 13.5px;
 *     Card padding: 28/32px → 18/22px; Card gap: 24px → 14px;
 *     Icon circles: 40×40px → 34×34px; Icon font-size: 18px → 16px.
 *   System-prompt-only change — no PHP, AMD JS, or DB schema changes.
 *   Existing generated HTML in courseinfo.generatedhtml is unaffected; teachers
 *   must click "Regenerate Course Information" to apply the new sections.
 *   CI_VERSION updated to 1.0.41. version.php → 2026041700041.
 *
 * v1.0.40: CSS FULL REBUILD.
 *
 * v1.0.32 (below):
 *
 * v1.0.31: CARD BORDER + SHADOW CONSISTENCY — BUG-CI-CARD-BORDER:
 *   All ci-card elements across all plugin containers (generated HTML, scan results,
 *   volume panel, custom boxes) now render with a consistent thin full-border outline
 *   and a subtle box-shadow matching the top card style. Root cause: old AI-generated
 *   HTML stored in the database before v1.0.27 may contain inline border-left:4px solid
 *   styles baked into ci-card elements; CSS border-color:!important (added in v1.0.27)
 *   only changed the colour, not the width or the left-only nature of the border.
 *   Fix: styles.css now uses border shorthand + all four individual border-side
 *   properties with !important, which overrides inline border-left regardless of
 *   when the HTML was generated. Adds box-shadow:0 2px 8px rgba(0,0,0,0.07) to the
 *   same rule so stored HTML cards match the shadow on fresh cards. JS renderScanResults,
 *   renderVolumeCard, buildBoxCard all updated with inline shadow. PHP
 *   courseinfo_render_volume_panel updated with inline shadow. CI_VERSION → 1.0.31.
 *   No DB schema changes.
 *
 * v1.0.30: ICON RENDERING + HERO DISCOVERABILITY + BOX WIDTH — 3-BUG REMEDIATION:
 *   BUG-CI-ICONFA6: Both styles.css and view.php inline <style> used
 *     font-weight:normal !important on the FA icon restore rule. Font Awesome 6
 *     Solid (Moodle 4.3+) requires font-weight:900 — at weight 400 only icons
 *     with a Regular variant render; Solid-only icons (fa-info-circle, fa-laptop
 *     etc.) show as ☐. FA4 is a single-weight font so 900 is safe for both.
 *     Fixed: both changed to font-weight:900 !important.
 *   BUG-CI-HEROUI: Hero colour picker was a near-invisible white label styled
 *     with a light-grey border (height:30px, font-size:0.8rem, color:#495057) —
 *     indistinguishable from whitespace. Its paint-brush FA icon also rendered as
 *     ☐ (same font-weight bug). Fixed: button now uses btn-outline-info btn-sm
 *     to match the toolbar button row; a frosted-glass "Change Colour" overlay
 *     button is also injected directly onto the .ci-header hero banner by JS
 *     (addHeroEditOverlay) so teachers can click the banner itself. The label is
 *     now always rendered (hidden until content exists) so JS can reveal it after
 *     first-time generation without DOM reconstruction.
 *   BUG-CI-BOXWIDTH: AI sometimes generates Bootstrap-style negative left/right
 *     margins (margin-left:-15px) on the outer content wrapper, making cards bleed
 *     outside the Bootstrap column padding and appear wider than the toolbar.
 *     Fixed: added negative-margin stripping regex to BOTH sanitizers (routes.ts
 *     server-side and courseinfo.js client-side sanitizeGeneratedHtml). CSS rule
 *     added in styles.css (#courseinfo-generated > div) to force margin-left:0 /
 *     margin-right:0 / max-width:100% / box-sizing:border-box on every direct
 *     child div of the generated content container.
 *   CI_VERSION updated to 1.0.30. No DB schema changes.
 *
 * v1.0.29: SCAN RESULTS CSS CORRECTNESS — 5-BUG REMEDIATION:
 *   BUG-CI-VOL-NESTED: renderVolumePanel() was appended to the scan results HTML
 *     BEFORE the closing </div> of the scan results ci-card, nesting a rounded
 *     ci-card inside another rounded ci-card (violates design rule, caused layout
 *     collapse visible as second red-arrow symptom). Fixed: html += '</div>' now
 *     executes FIRST to close the scan results card, then renderVolumePanel()
 *     appends the volume card as a sibling inside #courseinfo-scan-results.
 *   BUG-CI-CSSVAR-SCAN: applyThemeCssVars() forEach list omitted 'courseinfo-scan-
 *     results', so --ci-border/--ci-surface/--ci-primary CSS vars were never set on
 *     this container. Fixed: 'courseinfo-scan-results' added to the forEach list.
 *   BUG-CI-SCAN-NOSWATCH: Activity column had no type colour swatch after the
 *     Bootstrap-removal rewrite (BUG-CI-SCANCSS in v1.0.28). First row appeared as
 *     a coloured block in the old plugin because Bootstrap styled the type badge
 *     full-width; the new code had NO indicator at all. Fixed: added 10x10px
 *     colour swatch (same pattern as volume breakdown table) keyed by activity type.
 *   BUG-CI-SCAN-RAWTYPE: Type column showed raw Moodle module names (contentcreator,
 *     aiactivities, knowledgecheck) instead of readable labels. Fixed: added a
 *     friendlyTypes lookup map covering all 10 recognised activity types.
 *   BUG-CI-BOOTSTRAP-MB: Environment badge wrapper still used class="mb-2" and
 *     badge elements used class="badge" (Bootstrap dependencies) after the v1.0.28
 *     rewrite. Fixed: replaced with equivalent inline styles throughout.
 *   CI_VERSION updated to 1.0.29. No DB schema changes.
 *
 * v1.0.27: CSS CONSISTENCY + BOX UX + HERO COLOUR — 4-BUG REMEDIATION + FEATURE:
 *   BUG-CI-BOXCSS: buildBoxCard() applied border-left:4px solid on a border-radius:12px
 *     element — a single-sided border on a rounded card, violating the design rule.
 *     styles.css reinforced this with border-left-color:var(--ci-primary)!important.
 *     Fixed: border-left removed from buildBoxCard() inline style; border-left-color
 *     removed from styles.css .ci-custom-box rule. Full border:1px solid only.
 *   BUG-CI-VOLCSS: Both renderVolumeCard() (JS) and courseinfo_render_volume_panel()
 *     (PHP view.php) used Bootstrap class="card" + border-left:4px solid, not matching
 *     the AI-generated section card design. Fixed: both rewritten to ci-card design —
 *     full border, border-radius:12px, icon circle + heading row, flexbox stats tiles,
 *     themed table with inline styles (no Bootstrap grid dependency).
 *   BUG-CI-BOXPLACE: populatePositionDropdown() generated option values 10,20,30… (one
 *     per AI section) that exactly collided with normalised custom-box positions 0,10,20…
 *     New box at position=10 tied with existing box → JS sort unstable on ties → box
 *     appeared at end. Also: dropdown showed AI section headings but boxes can only
 *     be ordered among themselves (all in #courseinfo-custom-boxes after AI content).
 *     Fixed: dropdown now lists EXISTING CUSTOM BOXES with interleaved offset values
 *     (−5 for First, box.position+5 for After-N, 9999 for Last) — no collisions possible.
 *   BUG-CI-BOXMOVE: moveBox() called customBoxes.findIndex() which returns the UNSORTED
 *     array index. If box was at end of unsorted array, targetIdx >= length triggered a
 *     silent return even when box was not at a visual boundary. Direction swap then
 *     operated on wrong pair. Fixed: find VISUAL (sorted) position first, then locate
 *     both boxes by ID in original array and swap only their position values.
 *   FEATURE-HEROBG: Hero banner background colour picker. Teachers can select a colour
 *     via a <label><input type=color> button in the toolbar. Colour is applied live to
 *     .ci-header (overriding the AI gradient), persisted via new ajax.php actions
 *     getherocolor / saveherocolor (plugin config, no DB schema change), and re-applied
 *     after AI content regeneration. picker value initialised from saved colour on load.
 *   CI_VERSION updated 1.0.26 → 1.0.27. version.php → 2026032401207.
 *
 * v1.0.26: FONT AWESOME ICON FIX — 2-BUG REMEDIATION:
 *   BUG-CI-ICONBREAK: Section heading icons rendered as "li" text in the icon
 *     circles instead of Font Awesome glyphs. Two root causes:
 *     (1) styles.css applied font-family:inherit !important to ALL descendants
 *     of #courseinfo-generated via the wildcard * selector. This overrode
 *     Font Awesome's required font-family:FontAwesome on <i class="fa fa-*">
 *     elements, so no glyph could render. Fixed: wildcard selector changed to
 *     *:not(i) to exclude <i> elements from the font-family override.
 *     (2) AI sometimes generated icon circles using text-content icons
 *     (Material Icons style: <i class="fa fa-*">li</i>) instead of the correct
 *     empty-element pattern (<i class="fa fa-*"></i>). Fixed: server-side
 *     regex in routes.ts strips text content from FA icon elements post-
 *     generation; sanitizeGeneratedHtml() in courseinfo.js does the same
 *     client-side; new fixFaIconsInDom() removes residual text nodes from FA
 *     <i> elements in the live DOM on init and after every inject.
 *   BUG-CI-ICONHTML: System prompt gave no explicit HTML structure for icon
 *     circles, leading to AI improvisation and icon system confusion. Fixed:
 *     prompt now includes exact icon circle HTML template, mandatory FA4 icon
 *     assignment for each section (fa-tasks for Assessment Summary, fa-life-ring
 *     for Support Services, fa-check-circle for Completion Requirements, etc.),
 *     and explicit prohibition on Material Icons, Bootstrap Icons, Heroicons,
 *     Lucide, and any font-family CSS rules targeting .fa / i / * selectors.
 *   Belt-and-suspenders: view.php now echoes a <style> block after
 *     #courseinfo-generated with FA font-family !important at ID+class
 *     specificity (wins over any AI-generated ID+universal wildcard rule).
 *     styles.css adds a positive FA restore rule with higher specificity.
 *   CI_VERSION updated 1.0.25 → 1.0.26. version.php → 2026032401206.
 *
 * v1.0.25: AI CREDIT COST CONFIRMATION POPUP:
 *   BUG-CI-CREDITPOPUP: The "Generate Course Information" button called
 *     generateCourseInfo() directly with no credit check or user confirmation.
 *     Teachers had no visibility into the 100-credit ($10.00 AUD) cost before
 *     the AI generation was triggered, leading to unexpected credit deductions.
 *     Fixed: btn-generate now calls checkCreditsAndGenerate() which (1) disables
 *     the button and shows a "Checking credits…" spinner, (2) calls the new
 *     ajax.php getcredits action to fetch the live balance from the AI Grader
 *     Central API, (3) shows a styled Promise-based modal (ciShowConfirm) with
 *     cost, current balance and after-generation balance, (4) only proceeds to
 *     generateCourseInfo() if the teacher clicks Generate. If the balance is
 *     insufficient a clear error is shown without opening the popup. If the
 *     credits API is unreachable the popup falls back to "Proceed anyway?" with
 *     a warning tone. All modal rendering uses inline styles — no external CSS
 *     dependency, no jQuery. Mirrors the same UX pattern in mod_learningmapping.
 *   CI_VERSION updated 1.0.24 → 1.0.25. version.php → 2026032401205.
 *
 * v1.0.24: NCVER NOMINAL HOURS LOOKUP FIX — 2-BUG REMEDIATION:
 *   BUG-CI-NCVER403: ncverLookup.ts fetched Nationally-agreed-hours.txt
 *     exclusively over HTTP from ncver.edu.au, which is Cloudflare-protected
 *     and returns HTTP 403 on all automated server-side requests. Every
 *     nominal-hours lookup returned success:false, making the feature
 *     permanently non-functional. Fixed: bundled local copy of the NCVER data
 *     file added at server/data/nominal-hours.txt (67,624 lines, 67,564
 *     records). ncverLookup.ts now reads this local file first via
 *     fs.readFileSync(); the NCVER URL is retained only as a never-reached
 *     fallback. Verified: TLIF0009 → 20 h, BSBTEC201 → 60 h, BSBWHS201 → 20 h.
 *   BUG-CI-CACHESTALL: On HTTP 403 failure, ncverCache remained null and
 *     lastFetch remained 0, causing ensureLoaded() to re-attempt the blocked
 *     HTTP request on every subsequent lookup (5 s timeout each time). Fixed:
 *     on any fetch failure the cache is now set to new Map() with lastFetch =
 *     Date.now() so no retry occurs for 24 h. BUG-CI-NCVER403 makes this
 *     path unreachable in practice; guard retained as belt-and-suspenders.
 *   CI_VERSION updated 1.0.23 → 1.0.24. version.php → 2026032400124.
 *
 * v1.0.23: DESKTOP LAYOUT FIX — 7-BUG REMEDIATION:
 *   Root cause: AI was generating a full HTML document (<!DOCTYPE><html><head>
 *   <body>) instead of a pure HTML fragment. Injecting a full document into a
 *   Moodle content div causes browsers to abandon the Moodle DOM and re-parse
 *   from scratch, making content render in a mobile-width "quirks" column.
 *   7 bugs identified and fixed:
 *   BUG-CI-DOCTYPE: AI generated full HTML document structure instead of
 *     fragment. Fixed: system prompt now explicitly prohibits DOCTYPE/html/
 *     head/body/meta tags and demands a pure HTML fragment.
 *   BUG-CI-RESPONSIVE: Unscoped @media (max-width:768px) breakpoints fired
 *     in Moodle's narrow content column, collapsing two-column layouts.
 *     Fixed: system prompt now prohibits all responsive @media breakpoints.
 *   BUG-CI-CSSBLEED: AI-generated CSS selectors (*, html, body) had no
 *     #courseinfo-generated scope prefix, bleeding into Moodle theme.
 *     Fixed: system prompt now mandates #courseinfo-generated prefix on all
 *     CSS rules; prohibited *, html, body global selectors.
 *   BUG-CI-MAXWIDTH: Nested max-width:900px; margin:0 auto on the outermost
 *     wrapper constrained layout inside Moodle's already-constrained column.
 *     Fixed: system prompt removes max-width constraint; uses width:100%.
 *   BUG-CI-FONTRESET: font-family declarations in generated CSS overrode
 *     Moodle's inherited theme font.
 *     Fixed: system prompt prohibits font-family in generated CSS;
 *     styles.css already enforces font-family:inherit !important.
 *   BUG-CI-HTMLSTRIP: No server-side sanitization — document tags stored
 *     verbatim in courseinfo.generatedhtml DB column.
 *     Fixed: ajax.php generate action now strips DOCTYPE/html/head/body/meta
 *     tags and max-width:900px patterns before DB storage (belt-and-suspenders
 *     on top of API-level stripping already done in routes.ts).
 *   BUG-CI-JSINJECT: No client-side sanitization — contentEl.innerHTML =
 *     data.html injected raw AI output directly into the Moodle page.
 *     Fixed: sanitizeGeneratedHtml() function added to courseinfo.js and
 *     courseinfo.min.js; called before every innerHTML assignment.
 *   Additional: ABSOLUTE RULES section of system prompt extended with 5 new
 *     CSS/HTML fragment rules for belt-and-suspenders enforcement.
 *   Additional: styles.css adds width:100% !important overrides on
 *     #courseinfo-generated and its first child to guard against any
 *     residual max-width constraints in already-stored HTML.
 *   version.php → 2026032400123.
 *
 * v1.0.22: HTTP 500 ROOT CAUSE FIX + SECURITY HARDENING:
 *   1. DUPLICATE FUNCTION FATAL ERROR (HTTP 500 ROOT CAUSE): ajax.php was
 *      declaring courseinfo_require_manage() locally, while lib.php also
 *      declares the same function. When require_login() triggers Moodle to
 *      load lib.php (for completion tracking and module info), PHP throws a
 *      Fatal Error: Cannot redeclare courseinfo_require_manage() — which
 *      occurred OUTSIDE the try/catch, causing a raw HTTP 500. Fixed by:
 *      (a) adding require_once($CFG->dirroot.'/mod/courseinfo/lib.php') to
 *      ajax.php so lib.php is loaded first via require_once (idempotent), and
 *      (b) removing the duplicate function declaration from ajax.php entirely.
 *   2. INIT CODE OUTSIDE TRY/CATCH: required_param(), get_coursemodule_from_id(),
 *      DB lookups, and require_login() were all outside the try/catch block.
 *      Any failure (missing param, module not found, not logged in) bypassed
 *      the JSON error handler and produced Moodle's raw error output. All
 *      initialisation code is now inside the try/catch so every error path
 *      returns a clean {"success":false,"error":"..."} JSON response.
 *   3. MISSING SESSKEY VALIDATION: require_sesskey() is now called for every
 *      request. The JS already sends sesskey= with every ajaxCall(), so
 *      existing behaviour is unchanged. This closes a CSRF gap on mutating
 *      actions (scan, generate, save, saveboxes).
 *   4. CI_VERSION in AMD JS updated from 1.0.20 → 1.0.22.
 *   version.php → 2026032400122.
 * v1.0.21: BUG FIXES:
 *   1. ajax.php catch (\Exception) → catch (\Throwable) so PHP 7+ Error objects
 *      (TypeError, ArgumentCountError, etc.) are caught and returned as JSON
 *      instead of causing a raw HTTP 500 response.
 *   2. scan action now calls write_close() before scanning so the session lock
 *      is released, preventing conflicts with concurrent requests.
 *   3. scan_assign() submission detection fixed: was checking
 *      strpos($plugin->plugin, 'submission') which never matched Moodle's plugin
 *      names (onlinetext, file, etc.). Now correctly checks
 *      $plugin->subtype === 'assignsubmission'. Submission types now detected.
 *   4. scan_course_environment() fixed: was using $CFG->fullname (does not exist)
 *      instead of $SITE->fullname for the site name.
 *   5. lang nocourseinfo string changed from "click the button below" to
 *      "use the buttons above" — the buttons render above the notice in view.php.
 *   version.php → 2026032400121.
 * v1.0.20: ASQA COMPLIANCE TOGGLE:
 *   New `enableasqa` DB field (int, default 1) and mod_form advcheckbox.
 *   When unchecked, the generated document omits ASQA-specific sections
 *   (RPL & Credit Transfer, Complaints & Appeals) and removes all regulatory
 *   compliance framing — suitable for non-accredited / corporate courses.
 *   Enabled by default for full backward compatibility with existing courses.
 *   DB: install.xml + upgrade.php step 2026032300120. version.php → 2026032300120.
 * v1.0.19: SECTION-GROUPED VOLUME OF LEARNING TABLE:
 *   The breakdown table in the Volume of Learning card now groups activities
 *   by Moodle course section, showing a shaded section-header row (folder
 *   icon + section name in primary colour) before each group of category rows.
 *   Categories within each section are indented (padding-left:22px) and shown
 *   in canonical order. Sections with no activities are silently skipped.
 *   volume_calculator::calculate() now returns `sections_breakdown` — an
 *   ordered array of {num, name, total_hours, categories[]} objects — in
 *   addition to the existing flat `breakdown` (kept for backward compat).
 *   Both JS renderVolumeCard() and PHP courseinfo_render_volume_panel() prefer
 *   sections_breakdown and fall back to breakdown for data scanned before
 *   v1.0.19. A re-scan is required to populate sections_breakdown for existing
 *   courses. version.php → 2026032300119.
 * v1.0.18: VOLUME OF LEARNING CARD CONSISTENCY. version.php → 2026032300118.
 * v1.0.17: AI TUTOR SUPPORT SERVICES CARD. version.php → 2026032300117.
 * v1.0.16: CUSTOM BOXES + STUDENT VIEW. version.php → 2026032300116.
 * v1.0.15: FULL THEME COLOUR INTEGRATION. version.php → 2026032300115.
 * v1.0.14: THEME FONT INHERITANCE. version.php → 2026032300114.
 * v1.0.13: TIME-COLUMN-FIX. version.php → 2026032300113.
 * v1.0.12: MOODLE THEME COLOUR INTEGRATION + FLOATING RTE. version.php → 2026032300112.
 * v1.0.0:  Initial release.
 *
 * @package    mod_courseinfo
 * @copyright  2026 SA Grader
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$plugin->version   = 2026072300229;
$plugin->requires  = 2022041900;
$plugin->component = 'mod_courseinfo';
$plugin->maturity  = MATURITY_STABLE;
$plugin->release   = '1.0.51';
