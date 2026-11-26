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

namespace mod_courseinfo\local;

/**
 * Bring your own AI: write Course Information on this site's own AI instead of LMS Labs credits.
 *
 * A port of the LMS Labs /api/moodle/course-info/generate service (and its /start + /api/jobs
 * pair): the same prompts for every framework, the same HTML clean-up and the same response,
 * run through the shared engine in AI Grader Central Config (local_aiconfig 1.1.0+). Course data
 * goes from this site straight to the site's AI provider; nothing is sent to LMS Labs and no credits
 * are spent. The prompt templates are generated from the service source and checked word for word
 * against it.
 *
 * @package    mod_courseinfo
 * @copyright  2026 LMS Hosting Services
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class byok {
    /** This plugin, as named in the licence's products list. */
    const COMPONENT = 'mod_courseinfo';

    /** Job ids this class issues start with this. */
    const JOBPREFIX = 'byok';

    /** Course frameworks the service knows. */
    const FRAMEWORKS = ['vet', 'au_curriculum', 'sg_moe', 'university', 'workplace', 'custom'];

    /** @var callable|null Test hook: replaces the AI call with fn(system, user, maxtokens, temperature): string. */
    public static $aicall = null;

    /**
     * Should this site write Course Information on its own AI?
     *
     * @return bool
     */
    public static function enabled(): bool {
        return class_exists('\local_aiconfig\byok\ai')
            && \local_aiconfig\byok\ai::uses_own_ai(self::COMPONENT, 'text');
    }

    /**
     * Answer a service call locally when bring your own AI is on.
     *
     * @param string $url The LMS Labs URL the plugin was about to call.
     * @param array $data Its JSON payload.
     * @return array|null The service's reply, or null to call LMS Labs as usual.
     */
    public static function handle(string $url, array $data): ?array {
        $path = (string) parse_url($url, PHP_URL_PATH);
        if (
            !in_array($path, ['/api/moodle/course-info/generate', '/api/moodle/course-info/generate/start',
                '/api/moodle/course-info/check-config'], true) || !self::enabled()
        ) {
            return null;
        }
        if (substr($path, -13) === '/check-config') {
            return ['success' => true, 'byok' => true];
        }
        $result = self::generate(json_decode(json_encode($data), true));
        if (substr($path, -6) !== '/start') {
            return $result;
        }
        $jobid = self::JOBPREFIX . bin2hex(random_bytes(10));
        self::jobs()->set($jobid, $result);
        return ['ok' => true, 'jobId' => $jobid, 'byok' => true];
    }

    /**
     * Poll a job this class started.
     *
     * @param string $jobid Job id.
     * @return array|null The /api/jobs reply, or null when the job is not ours.
     */
    public static function poll(string $jobid): ?array {
        if (strpos($jobid, self::JOBPREFIX) !== 0) {
            return null;
        }
        $result = self::jobs()->get($jobid);
        if ($result === false) {
            return ['ok' => false, 'error' => 'Job not found or expired'];
        }
        if (empty($result['success'])) {
            return ['ok' => true, 'status' => 'error', 'error' => $result['error'] ?? 'Generation failed'];
        }
        return ['ok' => true, 'status' => 'done', 'result' => $result];
    }

    /**
     * Finished jobs, kept for an hour.
     *
     * @return \cache
     */
    private static function jobs(): \cache {
        return \cache::make_from_params(
            \cache_store::MODE_APPLICATION,
            'mod_courseinfo',
            'byokjobs',
            [],
            ['simplekeys' => true, 'simpledata' => false, 'ttl' => HOURSECS]
        );
    }

    /**
     * Port of POST /api/moodle/course-info/generate.
     *
     * @param array $p The service payload.
     * @return array success, html, structured, data | success false, error.
     */
    public static function generate(array $p): array {
        \core_php_time_limit::raise(600);
        if (isset($p['framework']) && !in_array($p['framework'], self::FRAMEWORKS, true)) {
            return ['success' => false, 'error' => 'Invalid request parameters'];
        }
        try {
            [$systemprompt, $userprompt] = self::prompts($p);
            $airesponse = self::ai($systemprompt, $userprompt, 12000, 0.25);
        } catch (\Throwable $e) {
            return ['success' => false, 'error' => self::message($e)];
        }
        $html = '';
        $structured = null;
        $parsed = json_decode($airesponse, true);
        if (is_array($parsed) && json_last_error() === JSON_ERROR_NONE) {
            $html = self::js_truthy($parsed['html'] ?? null) ? $parsed['html'] : '';
            $structured = self::js_truthy($parsed['structured'] ?? null) ? $parsed['structured'] : null;
        } else if (json_last_error() !== JSON_ERROR_NONE) {
            $html = preg_match('/<[^>]+>[\s\S]*<\/[^>]+>/', $airesponse, $m) ? $m[0] : $airesponse;
        }
        if (!self::js_truthy($html)) {
            return ['success' => false, 'error' => 'AI returned empty content'];
        }
        $html = self::clean_html((string) $html);
        $data = [
            'courseName' => $p['courseName'] ?? '',
            'unitCode' => $p['unitCode'] ?? null,
            'nominalHours' => $p['nominalHours'] ?? 0,
            'activityCount' => count((array) ($p['activities'] ?? [])),
            'generatedAt' => gmdate('Y-m-d\TH:i:s.000\Z'),
        ];
        if ($data['unitCode'] === null) {
            unset($data['unitCode']);
        }
        return ['success' => true, 'html' => $html, 'structured' => $structured, 'data' => $data, 'byok' => true];
    }

    /**
     * The service's clean-up of the generated fragment.
     *
     * @param string $html Fragment.
     * @return string
     */
    public static function clean_html(string $html): string {
        $html = preg_replace(['/<!DOCTYPE[^>]*>/i', '/<html[^>]*>/i', '/<\/html>/i', '/<head[^>]*>[\s\S]*?<\/head>/i',
            '/<body[^>]*>/i', '/<\/body>/i', '/<meta[^>]*/i'], '', $html);
        $html = self::js_trim($html);
        $html = preg_replace('/max-width\s*:\s*\d+(?:\.\d+)?px\s*;?\s*margin\s*:\s*0\s+auto/i', 'width:100%', $html);
        $html = preg_replace('/margin\s*:\s*0\s+auto\s*;?\s*max-width\s*:\s*\d+(?:\.\d+)?px/i', 'width:100%', $html);
        $html = preg_replace('/margin-left\s*:\s*-[\d.]+[a-z%]*/i', 'margin-left:0', $html);
        $html = preg_replace('/margin-right\s*:\s*-[\d.]+[a-z%]*/i', 'margin-right:0', $html);
        $html = preg_replace('/<i([^>]*class="[^"]*\bfa\b[^"]*"[^>]*)>([^<]+)<\/i>/i', '<i$1></i>', $html);
        foreach (['/font-family\s*:[^;!}]*(!important)?\s*;?/i', '/font-size\s*:[^;!}]*(!important)?\s*;?/i'] as $rule) {
            $html = preg_replace_callback('/(<style[^>]*>)([\s\S]*?)(<\/style>)/i', function ($m) use ($rule) {
                return $m[1] . preg_replace($rule, '', $m[2]) . $m[3];
            }, $html);
        }
        return preg_replace('/<script[^>]*>[\s\S]*?<\/script>/i', '', $html);
    }

    /**
     * The system and user prompts the service would send for this payload.
     *
     * @param array $p The service payload.
     * @return string[] [system, user]
     */
    public static function prompts(array $p): array {
        $coursename = (string) ($p['courseName'] ?? '');
        $courseshortname = $p['courseShortName'] ?? null;
        $coursesummary = $p['courseSummary'] ?? null;
        $learningoutcomes = (array) ($p['learningOutcomes'] ?? []);
        $framework = $p['framework'] ?? 'vet';
        $frameworkdata = array_key_exists('frameworkdata', $p) ? $p['frameworkdata'] : [];
        $unitcode = $p['unitCode'] ?? null;
        $unittitle = $p['unitTitle'] ?? null;
        $nominalhours = $p['nominalHours'] ?? 0;
        $enablevol = (bool) ($p['enableVol'] ?? false);
        $activities = array_values((array) ($p['activities'] ?? []));
        $volume = $p['volume'] ?? null;
        $environment = $p['environment'] ?? null;
        $primarycolour = $p['primaryColour'] ?? '#1a73e8';
        $secondarycolour = $p['secondaryColour'] ?? '#1a73e8';
        $enableasqa = $p['enableAsqa'] ?? true;
        $selectedframework = in_array($framework, self::FRAMEWORKS, true) ? $framework : 'vet';

        $hex = '/^#[0-9a-fA-F]{6}$/';
        $themep = preg_match($hex, (string) ($primarycolour ?? '')) ? $primarycolour : '#1a73e8';
        $themes = preg_match($hex, (string) ($secondarycolour ?? '')) ? $secondarycolour : $themep;
        $themepdk = self::darken($themep);
        $themepborder = self::tint($themep, 0.18);
        $themepsurface = self::tint($themep, 0.06);
        $themephover = self::tint($themep, 0.12);

        $activitiessummary = self::activities_summary($activities);

        $volumeinfo = '';
        if ($enablevol && self::js_truthy($volume)) {
            $totalhours = self::js_or(self::js_or($volume['total_hours'] ?? null, $volume['raw_total_hours'] ?? null), 0);
            $nominal = self::js_or($volume['nominal_hours'] ?? null, $nominalhours);
            $gaphours = self::js_or($volume['gap_hours'] ?? null, max(0, self::js_num($nominal) - self::js_num($totalhours)));
            $compliant = self::js_truthy($volume['compliant'] ?? null);
            $volumeinfo = "\n\nVOLUME OF LEARNING DATA:\n- Nominal Hours Required: " . self::js_str($nominal)
                . "\n- Total Activity Hours: " . self::js_str($totalhours) . " hours\n- Compliant: " . ($compliant ? 'Yes' :
                    'No')
                . "\n- Gap: " . self::js_str($gaphours) . ' hours '
                . ($compliant ? '(none — meets requirement)' : 'below nominal hours requirement') . "\n\nBREAKDOWN BY " .
                    "CATEGORY:";
            if (self::js_truthy($volume['breakdown'] ?? null) && is_array($volume['breakdown'])) {
                foreach ($volume['breakdown'] as $data) {
                    $data = (array) $data;
                    $mins = self::js_or(self::js_or($data['minutes'] ?? null, $data['adjusted_minutes'] ?? null), 0);
                    if (self::js_num($mins) > 0 || (isset($data['count']) && self::js_num($data['count']) > 0)) {
                        $volumeinfo .= "\n- " . self::tplv($data, 'label') . ': ' . self::tplv($data, 'hours') . ' hours ('
                            . self::tplv($data, 'count') . ' activities)';
                    }
                }
            }
        }

        $isaccredited = $enablevol && self::js_truthy($unitcode);
        $isasqa = $enableasqa !== false;

        $envinfo = '';
        $env = is_array($environment) ? $environment : [];
        if (self::js_truthy($environment)) {
            $envinfo = "\n\nCOURSE ENVIRONMENT:";
            if (self::js_truthy($env['ai_course_format'] ?? null)) {
                $envinfo .= "\n- AI Course Format: ENABLED (the course uses an AI-powered course format that provides " .
                    "intelligent navigation)";
            }
            if (self::js_truthy($env['ai_tutor_enabled'] ?? null)) {
                $envinfo .= "\n- AI Tutor: ENABLED (students have access to an AI tutor that can answer questions about " .
                    "the course content)";
            }
            if (self::js_truthy($env['ai_support_installed'] ?? null)) {
                $envinfo .= "\n- AI Support Dashboard: INSTALLED (students can access the AI Support block for quick " .
                    "links to help resources)";
            }
            foreach (
                ['site_name' => 'Organisation Name', 'site_phone' => 'Support Phone', 'site_email' => 'Support Email',
                    'site_url' => 'Website'] as $key => $label
            ) {
                if (self::js_truthy($env[$key] ?? null)) {
                    $envinfo .= "\n- {$label}: " . self::js_str($env[$key]);
                }
            }
            if (!empty($env['installed_plugins']) && is_array($env['installed_plugins'])) {
                $envinfo .= "\n- Installed AI Plugins: " . implode(', ', array_map(
                    [self::class, 'join_item'],
                    $env['installed_plugins']
                ));
            }
        }
        $supportphone = self::js_or($env['site_phone'] ?? null, '');
        $supportemail = self::js_or($env['site_email'] ?? null, '');
        $sitename = self::js_or($env['site_name'] ?? null, 'the training organisation');
        $hasaitutor = self::js_or($env['ai_tutor_enabled'] ?? null, false);
        $hasaisupport = self::js_or($env['ai_support_installed'] ?? null, false);
        $hasaiformat = self::js_or($env['ai_course_format'] ?? null, false);

        $systemprompt = ('You are an elite Australian RTO compliance officer and world-class instructional designer ' .
            'specialising in ASQA 2025 Standards. You produce stunning, publication-quality course information documents ' .
            'that are fully ASQA-compliant and visually breathtaking — the kind of document that impresses students, ' .
            'auditors, and training managers alike.

Your output must be a single JSON object with one key "html" containing a pure HTML FRAGMENT (absolutely NO <!DOCTYPE>, ' .
            '<html>, <head>, <body>, or <meta> tags — this HTML is injected directly into an existing Moodle page div). ' .
            'The fragment must include a <style> block and an inline <script> block for scroll animations.

CRITICAL HTML INJECTION RULES (VIOLATIONS WILL BREAK THE MOODLE PAGE):
1. NEVER output <!DOCTYPE>, <html>, <head>, <body>, or <meta> tags — output ONLY the style+content+script fragment.
2. NEVER add @media (max-width:...) or @media (min-width:...) responsive breakpoints — the only permitted @media rule is ' .
            '@media print.
3. NEVER add * { box-sizing: ... }, html { ... }, or body { ... } rules — these will corrupt Moodle\'s page layout.
4. NEVER set font-family anywhere (inline or in <style>) — fonts must inherit from Moodle\'s theme.
5. ALL CSS rules in the <style> block MUST be prefixed with #courseinfo-generated to prevent bleeding into Moodle\'s own UI ' .
            'elements. For example: #courseinfo-generated .ci-card { ... } not just .ci-card { ... }. The ONLY exceptions ' .
            'are @keyframes and @media print rules.

═════════════════════════════════════════' .
                '══════
WORLD-CLASS DESIGN SYSTEM
═════════════════════════════════════════' .
                '══════

COLOUR PALETTE:
  --primary:    ' . self::js_str($themep) . '   (site primary colour)
  --primary-dk: ' . self::js_str($themepdk) . '   (darkened primary)
  --secondary:  ' . self::js_str($themes) . '   (site secondary/accent colour — use for compliant badges, positive ' .
        'indicators)
  --warning:    #d97706   (amber)
  --danger:     #dc2626   (red)
  --surface:    #ffffff
  --surface-2:  ' . self::js_str($themepsurface) . '   (very pale primary tint — used for alternating rows, support card ' .
        'backgrounds)
  --border:     ' . self::js_str($themepborder) . '   (light primary tint — used for card borders, table borders, dividers)
  --text:       #1e293b
  --text-2:     #475569
  --text-3:     #94a3b8

TYPOGRAPHY:
  - Font stack: inherit (IMPORTANT: do NOT set any font-family anywhere in the generated HTML — all elements must inherit ' .
        'the font from the Moodle theme via CSS inheritance. Never write font-family in any inline style or <style> tag.)
  - Section headings: 18px, font-weight 700, color #1e293b
  - Sub headings: 14px, font-weight 600
  - Body: 13.5px, line-height 1.7, color #475569
  - ALL TEXT: Never reference or imply that content is AI-generated. Use words like "interactive", "scenario-based", ' .
        '"structured", "personalised" instead.
  - DENSITY RULE: Maximum 2 sentences per paragraph anywhere in the document. For any list of 3 or more items, ALWAYS use a ' .
        'bullet list — never write 3+ items as a prose sentence. Never create a wall of text — the document must be easy ' .
        'to scan in under 30 seconds.
  - BREVITY MANDATE (HARD WORD CAPS PER SECTION — VIOLATIONS WILL BE TRUNCATED CLIENT-SIDE):
      Course Overview body: max 60 words (excluding the accreditation statement)
      Course Outcomes: max 5 bullet points, each bullet max 12 words
      Entry Requirements: each of the 4 sub-sections max 25 words; use bullets within sub-sections wherever possible
      Mode of Delivery: max 40 words total
      Course Duration: max 30 words plus the optional volume table
      Course Structure: max 30 words of intro, then the activity table — no extra prose
      Fees & Refunds: max 4 lines of bullets, max 12 words per bullet
      Step-by-Step Completion Guide: max 6 numbered steps, each step description max 15 words
      Assessment Summary: table only — max 25 words of intro
      Completion Requirements: max 4 bullets, each max 12 words
      Support Services: max 3 cards, each max 18 words of description
      Learner Rights & Responsibilities: max 6 bullets, each max 14 words
      RPL & Credit Transfer: each sub-section max 50 words
      Complaints & Appeals: each sub-section max 60 words; ASQA escalation note max 25 words
      Privacy & Data Protection: max 4 bullets, each max 14 words
      Consumer Rights Notice: max 30 words
      Learner Acknowledgement Statement: keep the formal acknowledgement paragraph as specified — this is the ONLY section ' .
        'exempt from the brevity cap because its wording is legally prescribed
  - LEAN-WRITING RULES: Prefer noun-then-verb sentences. Cut every hedge word ("simply", "in order to", "please note that", ' .
        '"it is important that"). If a sentence can be a bullet, make it a bullet. If a paragraph repeats the section ' .
            'heading, ' .
        'delete the repetition.
  - GLANCE STRIP (NEW — render IMMEDIATELY AFTER the .ci-header and BEFORE the Quick Stats Strip): A single ci-card-style ' .
        'row class="ci-glance" with display:flex; align-items:center; gap:14px; padding:10px 16px; background:#fff; ' .
            'border:1px ' .
        'solid ' . self::js_str($themepborder) . '; border-radius:10px; ' .
        'margin-bottom:14px. Inside, render exactly four spans separated by a thin vertical divider ' .
        '(style="width:1px;height:14px;background:#e2e8f0;display:inline-block;"). The four spans are: (1) <i class="fa ' .
        'fa-clock-o" style="color:' . self::js_str($themep) . ';margin-right:6px;"></i><strong>≈X min read</strong> ' .
          'where X is computed as ceil(totalWordsInDocument / 220). (2) <i class="fa fa-list" style="color:' .
              self::js_str($themep) .
                  ';margin-right:6px;"></i><strong>N sections</strong> where N is the number of major section cards you ' .
                      'actually render. (3) <i class="fa fa-shield" style="color:' .
                      self::js_str($themep) .
                          ';margin-right:6px;"></i><strong>ASQA 2025</strong>. (4) <i class="fa fa-language" style="color:' .
                              self::js_str($themep) . ';margin-right:6px;"></i><strong>English</strong>. Do NOT add prose ' .
                                  'around the glance strip — it must stand alone.
  - DEEP-DETAIL COLLAPSE (NEW): For Privacy & Data Protection, RPL & Credit Transfer (sub-section B), and Complaints & ' .
              'Appeals (sub-section B "Assessment Appeals" and "External Escalation"), wrap the long-form content in a ' .
              '<details> element with a <summary> set to the section subhead text and ' .
              'style="cursor:pointer;font-weight:600;color:' . self::js_str($themep) . ';margin:6px 0;list-style:none;". ' .
                  'Inside the <summary> prepend an arrow icon: <i class="fa fa-chevron-right" ' .
                  'style="margin-right:6px;font-size:11px;transition:transform 0.2s;"></i>. Add a tiny CSS rule inside your ' .
                  '<style> block: #courseinfo-generated details[open] > summary > .fa-chevron-right { transform: ' .
                  'rotate(90deg); }. The first paragraph (or first 2 bullets) of each of these sections stays OUTSIDE the ' .
                  '<details> as a 1-line summary visible by default; the rest is collapsed.

LAYOUT:
  - Outer wrapper: width 100% (DO NOT add max-width or margin:0 auto — Moodle handles the container width). Add padding: 0 ' .
                  '0 16px 0 at most.
  - Section cards: class="ci-card ci-reveal"; background:#fff; border:1px solid ' . self::js_str($themepborder) . '; ' .
        'border-left:4px solid ' . self::js_str($themep) . '; border-radius:12px; padding:20px 22px; margin-bottom:14px; ' .
          'overflow:hidden
  - Card box-shadow: 0 2px 6px rgba(0,0,0,0.06), 0 1px 2px rgba(0,0,0,0.04)
  - Card hover box-shadow (via CSS class .ci-card:hover): 0 12px 36px rgba(0,0,0,0.10)
  - Card transition: box-shadow 0.2s ease, transform 0.2s ease
  - Card hover transform: translateY(-2px)

HEADER:
  - Full-width gradient bar: background linear-gradient(135deg, ' . self::js_str($themepdk) . ' 0%, ' . self::js_str($themep) .
        ' 100%)
  - Give the hero container div class="ci-header"
  - Padding 30px 26px, border-radius 12px, margin-bottom 14px, overflow:hidden, position:relative
  - Subtle decorative circle in top-right corner ' .
        '(position:absolute;top:-30px;right:-30px;width:180px;height:180px;border-radius:50%;background:rgba(255,255,255,0.07' .
            ')) — visual depth
  - ALL text in the hero MUST be colour:#fff !important — course title 24px, font-weight:800, letter-spacing:-0.02em, ' .
        'white; unit code badge: background rgba(255,255,255,0.18); border 1px solid rgba(255,255,255,0.35); ' .
        'border-radius:20px; padding:3px 12px; font-size:12px; font-weight:700; color:#fff; margin-left:10px; ' .
        'vertical-align:middle
  - Organisation name: colour:rgba(255,255,255,0.88); font-weight:400; font-size:0.88em; margin-top:6px; ' .
        'letter-spacing:0.01em
  - Tagline strip: Immediately below the organisation name, render a glassmorphism strip: display:inline-block; ' .
        'margin-top:10px; background:rgba(255,255,255,0.14); border:1px solid rgba(255,255,255,0.28); border-radius:8px; ' .
        'padding:6px 14px; backdrop-filter:blur(8px); -webkit-backdrop-filter:blur(8px)
    Inside this strip: an italic 1-sentence tagline about what the learner will gain (font-size:12.5px; ' .
        'color:rgba(255,255,255,0.92); font-style:italic; margin:0). Write it as "Develop practical [domain] skills with ' .
            '[key ' .
        'benefit]." Infer from the course name and activities. Prepend with <i class="fa fa-star" ' .
        'style="font-size:11px;color:rgba(255,255,255,0.7);margin-right:6px;"></i>

QUICK STATS STRIP (rendered immediately after .ci-header, before the Overview card — NOT inside the hero):
  - A horizontal flex row of 4 equal-width stat tiles: display:flex; gap:10px; margin-bottom:14px; flex-wrap:wrap
  - Each tile: class="ci-stat-tile"; flex:1; min-width:110px; background:#fff; border:1px solid ' .
        self::js_str($themepborder) . '; border-radius:10px; padding:12px 14px; display:flex; align-items:center; gap:10px; ' .
            'box-shadow:0 1px 4px rgba(0,0,0,0.06); transition:box-shadow 0.2s ease,transform 0.2s ease
  - Each tile contains: a gradient icon circle (class="ci-icon"; width:32px; height:32px; border-radius:50%; ' .
        'background:linear-gradient(135deg,' . self::js_str($themepdk) . ' 0%,' . self::js_str($themep) . ' 100%); ' .
          'display:flex; align-items:center; justify-content:center; flex-shrink:0; box-shadow:0 2px 6px rgba(0,0,0,0.18)) + ' .
          'a text column (display:flex; flex-direction:column)
  - Text column TOP line (label, the small heading — this MUST come FIRST in DOM order): class="ci-stat-label"; ' .
          'font-size:9.5px; text-transform:uppercase; letter-spacing:0.07em; color:#94a3b8; font-weight:700; line-height:1.2
  - Text column BOTTOM line (value, the prominent body number — MUST come SECOND in DOM order, immediately after the ' .
          'label): class="ci-stat-value"; font-size:14px; font-weight:800; color:#1e293b; line-height:1.2; ' .
          'letter-spacing:-0.01em; margin-top:2px
  - DOM ORDER IS MANDATORY: <span class="ci-stat-label">DURATION</span> then <span class="ci-stat-value">1–2 Hours</span>. ' .
          'Label is the heading on top, value is the body underneath — like a definition list. Do NOT reverse this order.
  - Tile 1 — Duration: icon fa-clock-o (font-size:13px; color:#fff; inside gradient circle), label "DURATION", value = ' .
          'estimated duration range (e.g. "2–3 Hours") derived from total activity minutes
   - Tile 2 — Delivery: icon fa-laptop (font-size:13px; color:#fff), label "DELIVERY", value = the exact supplied delivery ' .
          'mode; if none is supplied, show "Mode not specified"
  - Tile 3 — Type: icon fa-certificate (font-size:13px; color:#fff), label "TYPE", value = "' .
        self::js_str((self::js_truthy($isaccredited) ? 'Accredited VET' : 'Short Course')) . '"
  - Tile 4 — Provider: icon fa-university (font-size:13px; color:#fff), label "PROVIDER", value = ' .
        self::js_str($sitename) . ' (if longer than 22 chars truncate with …)
  - Icon elements MUST be empty: <i class="fa fa-ICONNAME" style="font-size:13px;color:#fff;"></i>
  - The stats strip MUST NOT have class ci-card or ci-reveal — it is its own standalone row, not a section card

SECTION HEADINGS:
  - Each section card MUST have class="ci-card ci-reveal" AND the style border-left:4px solid ' . self::js_str($themep) .
        ' — the accent bar is a mandatory design element, not optional
  - Section card heading row (icon + title): display:flex; align-items:center; gap:12px; margin-bottom:14px
  - Above the section title text (inside the heading row, in a flex-column div), render a small section category label: ' .
        'font-size:10px; font-weight:700; letter-spacing:0.1em; text-transform:uppercase; color:' . self::js_str($themep) .
            '; ' .
          'margin-bottom:2px; — use short labels like "PRE-ENROLMENT", "COURSE CONTENT", "ASSESSMENT", "STUDENT SUPPORT", ' .
          '"YOUR RIGHTS", "COMPLIANCE", "LEGAL"
  - Section title: font-size:1.02em; font-weight:700; color:#1e293b; margin:0; letter-spacing:-0.01em
  - Icon circle MUST use this EXACT HTML structure — no other format is permitted:
    <div class="ci-icon" style="width:40px;height:40px;border-radius:50%;background:linear-gradient(135deg,' .
        self::js_str($themepdk) . ' 0%,' . self::js_str($themep) .
            ' 100%);display:flex;align-items:center;justify-content:center;flex-shrink:0;transition:transform 0.3s ' .
                'ease;box-shadow:0 2px 8px rgba(0,0,0,0.18);"><i class="fa fa-ICONNAME" ' .
                'style="font-size:16px;color:#fff;"></i></div>
    Replace ICONNAME with the section-appropriate FA4 icon class (fa-info-circle, fa-check-square-o, etc.)
  - MANDATORY icon assignments for each section (use EXACTLY these FA4 class names):
    Header / Document hero: no icon circle needed
    Course Overview: fa-info-circle
    Course Outcomes: fa-bullseye
    Entry Requirements: fa-clipboard
    Mode of Delivery: fa-laptop
    Course Duration: fa-clock-o
    Course Structure: fa-sitemap
    Fees & Refunds: fa-money
    Step-by-Step Completion Guide: fa-list-ol
    Assessment Summary: fa-tasks
    Completion Requirements: fa-check-circle
    Support Services: fa-life-ring
    Learner Rights & Responsibilities: fa-balance-scale
    RPL & Credit Transfer: fa-graduation-cap
    Complaints & Appeals: fa-gavel
    Privacy & Data Protection: fa-lock
    Volume of Learning: fa-bar-chart
  - The <i> element inside the icon circle MUST be empty — NEVER put text content inside <i class="fa fa-*"></i>
  - Icon circle has transition: transform 0.3s ease — on .ci-card:hover the icon circle does transform: scale(1.12) ' .
        'rotate(8deg)

BADGES / PILLS:
  - Type badges: 11px, font-weight 600, padding 3px 10px, border-radius 20px
  - contentcreator: bg #dbeafe, color #1e40af
  - aiactivities: bg #dcfce7, color #166534
  - knowledgecheck / quiz: bg #fef3c7, color #92400e
  - assignment: bg #f3e8ff, color #6b21a8
  - practicalassessment: bg #fee2e2, color #991b1b
  - essaymaker: bg #e0f2fe, color #0c4a6e
  - videoactivity / aivideoactivity: bg #fce7f3, color #831843
  - other / resource / forum: bg #f1f5f9, color #475569

SCROLL ANIMATIONS:
  - Add a <style> block defining:
      @keyframes ci-fadein { from { opacity:0; transform:translateY(28px); } to { opacity:1; transform:translateY(0); } }
      .ci-reveal { opacity:0; }
      .ci-reveal.ci-visible { animation: ci-fadein 0.55s cubic-bezier(0.22,0.61,0.36,1) both; }
  - Add staggered animation-delay on each .ci-card: 0.05s increments (first card 0s, second 0.05s, third 0.10s …)
  - Add an inline <script> at the bottom using IntersectionObserver:
      const obs = new IntersectionObserver((entries) => { entries.forEach(e => { if(e.isIntersecting) { ' .
        'e.target.classList.add(\'ci-visible\'); obs.unobserve(e.target); } }); }, {threshold:0.1});
      document.querySelectorAll(\'.ci-reveal\').forEach(el => obs.observe(el));

HOVER EFFECTS:
  - All .ci-card elements: cursor default, transition box-shadow 0.3s ease, transform 0.3s ease
  - On hover: box-shadow 0 8px 32px rgba(0,0,0,0.10), translateY(-2px)
  - Icon circles inside cards: transition transform 0.3s ease; on card hover: scale(1.12) rotate(8deg) — achieve with CSS ' .
        '.ci-card:hover .ci-icon { transform: scale(1.12) rotate(8deg); }
  - Step rows in the completion table: background transition 0.2s — on hover row goes to background ' .
        self::js_str($themephover) . '
  - Assessment table rows: same hover background ' . self::js_str($themephover) . '
  - Add CSS: .ci-card:hover .ci-icon { transform: scale(1.12) rotate(8deg); }

PRINT STYLES:
  - @media print: remove box-shadows, no page-break inside cards, font-size 13px
  - ABSOLUTELY NO other @media rules (no @media (max-width:...), no @media (min-width:...)) — only @media print is ' .
        'permitted.

CSS SCOPING REMINDER:
  - Every single CSS rule in your <style> block MUST start with #courseinfo-generated. This is non-negotiable.
  - Correct: #courseinfo-generated .ci-card { ... }
  - Wrong: .ci-card { ... }
  - Correct: #courseinfo-generated h2 { ... }
  - Wrong: h2 { ... }
  - Exception ONLY for @keyframes (animation definitions) and @media print rules.

═════════════════════════════════════════' .
            '══════
ASQA 2025 COMPLIANCE — SECTIONS IN CORRECT ORDER
(Follows the student journey: pre-enrolment decision → course completion → rights & compliance)
═════════════════════════════════════════' .
            '══════

━━━ PART A: PRE-ENROLMENT INFORMATION (student asks: "Should I enrol?") ━━━

1. DOCUMENT HEADER — Gradient hero banner with ' . self::js_str((self::js_truthy($isaccredited) ? ('unit code "' .
        self::js_str(self::js_or($unitcode, '')) . '" in a pill badge, unit/course ' .
        'title (large), and organisation name below') : ('course title (large) and organisation name'))) . '.

2. COURSE OVERVIEW — Write 2-3 concise sentences from the supplied course summary and learning outcomes. Do not invent ' .
            'career outcomes, industry demand, job eligibility or benefits. Include an accreditation statement only when ' .
            'framework data confirms the status.

3. COURSE OUTCOMES — When learningOutcomes were supplied, render each one verbatim and in the same order, retaining its ' .
            'code and exact wording. Do not paraphrase, combine, add or infer outcomes from activities. If none were ' .
            'supplied, summarize only learning directly evidenced by the course data and do not claim formal unit outcomes.

4. ENTRY REQUIREMENTS — Must appear BEFORE course content so students know if they are eligible FIRST. State entry ' .
            'requirements only when supplied by the organisation or framework data. Do not invent age limits, consent ' .
            'requirements, LLN outcomes, or technical rules. If a requirement is unknown, omit it or direct the learner to ' .
            'contact the organisation.
    - LLN, digital literacy and technical requirements: include only requirements explicitly supplied by the organisation or ' .
            'course data. Do not assume an assessment, device, browser, internet, or support policy.
    - Minimum Age: Include only an age limit explicitly supplied in the course data. If none is supplied, omit this ' .
            'requirement.' . self::js_str((self::js_truthy($isaccredited) ? ('
   - Unique Student Identifier (USI): All students enrolled in nationally recognised training in Australia are required by ' .
                'law to have a valid Unique Student Identifier (USI). Your USI is free to create at usi.gov.au and takes ' .
                'approximately 5 minutes. ' . self::js_str($sitename) . ' will verify your USI as part ' .
        'of the enrolment process. A valid USI must be provided before a Statement of Attainment can be issued. You can ' .
        'view your complete national training record at any time through the USI Registry at usi.gov.au.') : '')) . '

5. MODE OF DELIVERY — Describe only the schedule, format and delivery mode explicitly supplied in the course data. Do not ' .
            'describe a scheduled class as self-paced or fully online. If the delivery mode is unknown, omit that claim. ' .
            'Mention available course tools only when listed in COURSE ENVIRONMENT.

6. COURSE DURATION — Use the supplied schedule and actual activity timings. Do not infer a self-paced mode or invent a ' .
            'time range. If a duration or nominal-hours figure is supplied, label it accurately and distinguish scheduled ' .
            'contact time from independent study.' . self::js_str((self::js_truthy(self::js_and($isaccredited, $nominalhours)) ?
                (' Supplied nominal hours for ' .
        'this unit: ' . self::js_str($nominalhours) . ' hours.') : '')) . '

7. COURSE STRUCTURE — A visual summary of what is included in the course. Use a 2-column grid of compact "module cards" ' .
            '(background ' . self::js_str($themepsurface) . ', border-radius 8px, padding ' .
        '12px 14px, border 1px solid ' . self::js_str($themepborder) . '). Each card shows a Moodle section name with an ' .
            'icon and the count of activities in that section. If the course has no distinct sections, list the activity ' .
            'types present (e.g. "3 × Knowledge Check", "1 × Assignment"). Heading: "What\'s Included".

8. FEES & REFUNDS — Include only prices and refund terms supplied by the organisation. If none are supplied, say to ' .
            'contact ' . self::js_str($sitename) . self::js_str((self::js_truthy($supportphone) ? self::js_add(
                ' on ',
                $supportphone
            ) : '')) . self::js_str((self::js_truthy($supportemail) ? self::js_add(
                ' or email ',
                $supportemail
            ) : '')) . ' for current fee and refund information. Do not invent a fees schedule, policy, deadlines or charges.

━━━ PART B: COURSE COMPLETION INFORMATION (student asks: "How do I complete this?") ━━━

9. STEP-BY-STEP COMPLETION GUIDE — The MAIN section. Present as a clean HTML TABLE with these exact columns:
   - Column 1 (width:56px): Step number in a gradient circle: class="ci-step-circle"; width:28px; height:28px; ' .
                'border-radius:50%; background:linear-gradient(135deg,' . self::js_str($themepdk) . ' 0%,' .
                    self::js_str($themep) . ' 100%); color:#fff; font-weight:800; font-size:13px; display:inline-flex; ' .
                        'align-items:center; justify-content:center; box-shadow:0 2px 6px rgba(0,0,0,0.2)
   - Column 2 (auto, ~65% width): Activity name in bold (font-size:13.5px; font-weight:700; color:#1e293b) + type badge pill ' .
                    '(class="ci-badge") on same line, then below in a div: a concise 1-2 sentence description ' .
                    '(font-size:12.5px; color:#475569; margin-top:4px). For assessments include: pass mark, max attempts, ' .
                    'due date if available — each on its own small line with icon. NEVER write "passing grade" — always ' .
                    'use the numeric percentage.
   - Column 3 (width:80px, min-width:80px, white-space:nowrap, right-aligned, vertical-align:middle): Estimated time in a ' .
                    'pill: class="ci-time-pill"; background:rgba(0,0,0,0.06); color:' . self::js_str($themep) . '; ' .
                        'padding:3px 10px; border-radius:20px; font-weight:700; font-size:11.5px; white-space:nowrap; ' .
                        'display:inline-block; letter-spacing:0.01em
   Table style: width 100%, border-collapse:collapse, border-radius 10px, overflow hidden, border 1px solid ' .
        self::js_str($themepborder) . ', class="ci-step-table"
   Header row: background linear-gradient(90deg,' . self::js_str($themepdk) . ',' . self::js_str($themep) . '), color #fff, ' .
        'font-size 10.5px, font-weight:700, text-transform:uppercase, letter-spacing:0.07em, padding 11px 14px
   Data rows: alternating background #fff / ' . self::js_str($themepsurface) . ', border-bottom 1px solid ' .
        self::js_str($themepborder) . ', padding 13px 14px, transition:background 0.2s
   Hover row: class ci-tr-hover, use CSS .ci-step-table tr.ci-tr-hover:hover { background: ' . self::js_str($themephover) .
        ' !important; }
   NEVER use card/list layout for this section — TABLE ONLY.

   ACTIVITY DESCRIPTIONS (NEVER use the word "AI-generated"):
   - contentcreator: "Work through the interactive learning slides at your own pace. Each slide presents key concepts with ' .
        'rich visual content — review all slides in each topic before progressing."
   - aiactivities: "Complete the structured scenario-based activities. Read each scenario carefully and select the best ' .
        'response to demonstrate your understanding."
   - knowledgecheck: "Complete this knowledge assessment. You must achieve [insert passing_grade]% to demonstrate competency ' .
        '— read each question carefully before answering." (replace [insert passing_grade] with the actual number from the ' .
        'activity data; if unavailable use "the required pass mark")
   - quiz: "Complete this quiz within the time allowed. Read each question carefully and select your best answer."
   - assignment: "Complete and submit the required assessment task. Follow the instructions carefully — your submission ' .
        'will be reviewed and marked by a qualified assessor."
   - videoactivity / aivideoactivity: "Watch the video content and complete any associated reflection or comprehension ' .
        'tasks."
   - essaymaker: "Complete the written response questions. Provide detailed, well-structured answers that demonstrate your ' .
        'understanding and competency."
   - practicalassessment: "Complete the practical assessment tasks as directed. Demonstrate your skills and competency in a ' .
        'realistic workplace context."
   - forum: "Participate in the discussion forum. Share your insights and engage thoughtfully with the topic."
   - resource: "Review the provided learning resource material before proceeding."

10. ASSESSMENT SUMMARY — Clean table: Assessment Name | Type | Pass Mark | Max Attempts | Est. Time
    - The "Pass Mark" column must contain the actual percentage value (e.g. "80%", "100%") — NOT the words "Passing Grade" ' .
        'or "Passing Requirement". If no passing grade is provided for an activity, show "—".
    Only include assessable activities (knowledgecheck, quiz, assignment, essaymaker, practicalassessment).
    Same table styling (gradient header, alternating rows, hover).
    After the table, add an "Assessment Requirements" paragraph only when supported by activity data. State pass ' .
        'requirements, feedback and attempts only when supplied; otherwise omit those claims.

11. COMPLETION REQUIREMENTS — State only completion steps supported by actual course activities and their supplied pass ' .
        'marks, attempts or practical requirements. Identify an award or certificate only if the specific award is supplied ' .
        'in course data; do not promise a Statement of Attainment or Certificate of Completion based only on the ' .
            'organisation ' .
        'or course name.

━━━ PART C: STUDENT SUPPORT & RIGHTS ━━━

12. SUPPORT SERVICES — Render cards only for support services and contact methods explicitly supplied in COURSE ' .
        'ENVIRONMENT or course data. Do not promise technical, LLN, adjustment, interpreter, wellbeing, referral, ' .
        'confidentiality, or 24/7 support unless that promise was supplied. If no support details are provided, use one ' .
            'brief ' .
        'line directing learners to contact ' . self::js_str($sitename) . ' for current support ' .
        'information. ' . self::js_str((self::js_truthy($hasaitutor) ? ('The AI Course Tutor is enabled in COURSE ' .
        'ENVIRONMENT; describe only that available feature, without adding service hours or guarantees.') : '')) . ' ' .
            self::js_str((self::js_truthy($hasaisupport) ? 'The Support Hub is installed; ' .
            'describe it only as a link to resources available there.' : '')) . '

13. LEARNER RIGHTS & RESPONSIBILITIES — Use a two-column layout only when relevant rights and responsibilities are ' .
                'established by supplied course data or a clearly applicable framework. Keep statutory rights general and ' .
                'accurately scoped. Do not promise a support service, appeal route, response time, privacy/security ' .
                'practice, record access method, adjustment, or specific outcome unless supplied. Do not state that a policy ' .
                'complies with a named law unless that compliance is established in the request. Responsibilities must come ' .
                'from actual course rules; do not present generic suggestions as enforceable RTO policy.

' . self::js_str((self::js_truthy($isasqa) ? ('14. RECOGNITION OF PRIOR LEARNING (RPL) & CREDIT TRANSFER — Present as two ' .
        'clearly labelled sub-sections within the one card (icon: fa-graduation-cap):
    SUB-SECTION A — RECOGNITION OF PRIOR LEARNING (RPL):
    ' . self::js_str((self::js_truthy($isaccredited) ? ('Explain RPL in general terms. Describe only the application ' .
        'steps, evidence requirements, assessment timelines, costs and outcomes supplied by ' . self::js_str($sitename) . '. ' .
            'If those details are not ' .
            'supplied, direct learners to contact ' . self::js_str($sitename) . self::js_str((self::js_truthy($supportemail) ?
                self::js_add(' at ', $supportemail) : '')) . ' for its current RPL policy. ' .
                'Do not invent a deadline, fee, evidence rule or result.') : ('Students with relevant prior experience ' .
                    'may contact ' . self::js_str($sitename) . ' to discuss whether prior learning can inform a ' .
                        'tailored learning path. Do not promise a credit or outcome.'))) . '
    SUB-SECTION B — CREDIT TRANSFER:
    ' . self::js_str((self::js_truthy($isaccredited) ? ('Explain credit transfer only to the extent supported by the ' .
        'supplied course data and applicable framework. Describe ' . self::js_str($sitename) . '\'s evidence, process, ' .
            'timeframes, charges and outcome only when supplied. Otherwise direct learners to contact ' .
                self::js_str($sitename) . self::js_str((self::js_truthy($supportemail) ? self::js_add(' at ', $supportemail) :
                    '')) . ' for its current policy. Do ' .
                'not invent a deadline, fee or guarantee.') : ('Credit from prior accredited study may be discussed with ' .
                    self::js_str($sitename) . '; do not promise an ' .
                    'outcome.'))) . '
    Refer learners to ' . self::js_str($sitename) . self::js_str((self::js_truthy($supportemail) ? self::js_add(
                        ' at ',
                        $supportemail
                    ) : '')) . ' for its current RPL and Credit Transfer Policy. Do not invent regulatory guarantees or cite ' .
                        'superseded standards.

15. COMPLAINTS & APPEALS — Present as two sub-sections within the one card (icon: fa-balance-scale):
    SUB-SECTION A — COMPLAINTS PROCESS:
    Explain how to access ' . self::js_str($sitename) . '\'s complaints process using only details supplied in the ' .
        'course data. Do not invent acknowledgement or resolution deadlines, forms, stages, appeal windows, or ' .
        'protections. If the process is not supplied, direct the learner to contact ' . self::js_str($sitename) .
            self::js_str((self::js_truthy($supportemail) ? self::js_add(' at ', $supportemail) : '')) . ' for the current ' .
                'Complaints and Appeals Policy.
    SUB-SECTION B — ASSESSMENT APPEALS:
    Describe assessment appeals only using steps, review arrangements and deadlines provided by ' . self::js_str($sitename) .
        '. Otherwise direct learners to ' .
        'contact ' . self::js_str($sitename) . self::js_str((self::js_truthy($supportemail) ? self::js_add(
            ' at ',
            $supportemail
        ) : '')) . ' for its current policy; do not invent an appeal window or decision timeframe.
    EXTERNAL ESCALATION: If you remain unsatisfied after ' . self::js_str($sitename) . '\'s internal process, you may ' .
        'contact the Australian Skills Quality Authority (ASQA) at asqa.gov.au — ASQA is the national regulator for ' .
        'vocational education and training in Australia. For consumer rights issues, you may also contact the Australian ' .
        'Competition and Consumer Commission (ACCC) at accc.gov.au, or your state or territory consumer protection agency.
    Do not promise confidentiality, response times, fairness outcomes or absence of disadvantage as an organisation-specific ' .
        'policy unless the supplied data says so.') : '')) . '

16. PRIVACY & DATA PROTECTION — State collection purposes, storage, sharing, retention, access and deletion practices only ' .
            'when supplied. Do not claim data is secure, confidential, never shared, or handled in a particular way without ' .
            'evidence. For required framework disclosures, include only facts established by the supplied course data and ' .
            'applicable framework; otherwise direct learners to ' . self::js_str($sitename) .
                self::js_str((self::js_truthy($supportemail) ? self::js_add(' at ', $supportemail) : '')) . ' for its ' .
                    'current privacy information.

17. CONSUMER RIGHTS NOTICE — Present as a compact card only when applicable. Use the supplied jurisdiction and framework. ' .
                'Do not apply Australian law to another country\'s course or invent legal protections. Where applicability ' .
                'is not established, direct learners to contact ' . self::js_str($sitename) . ' for relevant consumer-rights ' .
                    'information.

18. LEARNER ACKNOWLEDGEMENT STATEMENT — Present as a formal, print-ready sign-off box at the very end of the document. ' .
                    'This section provides hardcopy audit evidence for ASQA — always render it visibly (do NOT hide it). ' .
                    'Style the outer div: background:linear-gradient(135deg,#fffdf0 0%,#fff9e0 100%); border:2px solid ' .
                    '#d4a820; border-radius:10px; padding:22px 26px; box-shadow:0 2px 12px rgba(212,168,32,0.12); ' .
                    'position:relative; overflow:hidden
    Add a decorative stamp-style circle watermark ' .
                    '(position:absolute;top:-20px;right:-20px;width:100px;height:100px;border-radius:50%;border:3px solid ' .
                    'rgba(212,168,32,0.15);pointer-events:none)
    Header row: display:flex; align-items:center; gap:12px; margin-bottom:12px
      Icon circle: width:38px; height:38px; border-radius:50%; background:linear-gradient(135deg,#c89010 0%,#d4a820 100%); ' .
                    'display:flex; align-items:center; justify-content:center; box-shadow:0 2px 8px rgba(180,130,0,0.3)
      Inside: <i class="fa fa-pencil-square-o" style="font-size:16px;color:#fff;"></i>
      Title: font-size:1em; font-weight:800; color:#4a3400; margin:0; letter-spacing:-0.01em
      Subtitle below title: font-size:0.8em; color:#7a5a00; font-weight:400; margin-top:2px
    Heading text: "Learner Acknowledgement — Pre-Enrolment Disclosure"
    Subheading text: "Please sign and date this form after reading the above course information. Retain a copy for your ' .
                    'records."
    Body paragraph (font-size:0.87em, color:#3d2c00, margin:12px 0, line-height:1.65): "I, the undersigned, confirm that I ' .
                    'have received, read, and understood the Course Information document for ' . self::js_str($coursename) .
                        self::js_str((self::js_truthy(self::js_and($isaccredited, $unitcode)) ? self::js_add(self::js_add(
                            ' (',
                            $unitcode
                        ), ')') : '')) . ' provided by ' . self::js_str($sitename) .
                                '. I acknowledge that I have ' .
        'been informed of: the course entry requirements, fees and refund policy, assessment requirements and grading ' .
        'criteria, available support services, my rights and responsibilities as a learner, the complaints and appeals ' .
        'process' . self::js_str((self::js_truthy($isaccredited) ? ', my RPL and Credit Transfer options, my USI ' .
            'obligations, and the AVETMISS reporting requirements that apply to my enrolment' : '')) . '."
    Signature grid: display:grid; grid-template-columns:repeat(3,1fr); gap:16px; margin-top:16px; padding-top:16px; ' .
                'border-top:1px dashed rgba(180,140,0,0.35)
    Each signature field: a label line (font-size:11px; font-weight:600; color:#7a5a00; text-transform:uppercase; ' .
                'letter-spacing:0.06em; margin-bottom:4px) above a blank signing line (border-bottom:1.5px solid #c4a030; ' .
                'padding-bottom:22px; margin-bottom:12px)
      Column 1: "Learner Full Name" + "Learner Signature"
      Column 2: "Date" + "Student ID / USI"
      Column 3: "Authorised Witness" + "Witness Signature"
    Below the grid, include a note about digital acknowledgement or record retention only if those features and policies are ' .
                'explicitly confirmed in the supplied course data. Do not claim that submitting a confirmation creates or ' .
                'retains an audit record without evidence.

━━━ PART D: COMPLIANCE DATA (regulatory — accredited courses only) ━━━

' . self::js_str((self::js_truthy($isaccredited) ? ('17. VOLUME OF LEARNING — Compliance summary panel (styled with subtle ' .
        'coloured background):
    - Nominal Hours required for ' . self::js_str(self::js_or($unitcode, 'this unit')) . ': ' . self::js_str($nominalhours) .
        ' hours
    - Actual course hours: (calculated from activities — show the real number, do NOT inflate)
    - Compliance status: Compliant (badge styled with background ' . self::js_str($themes) . ', white text) or Non-Compliant ' .
        '(red badge with gap stated)
    - Do NOT pad or inflate activity times') : '')) . '

═════════════════════════════════════════' .
        '══════
ABSOLUTE RULES
═════════════════════════════════════════' .
        '══════
- NEVER use the words "AI-generated", "AI-powered activities", or any variation in student-facing text
- NEVER include a "Generated on" footer or document metadata footer
- Every section heading must have a Font Awesome 4.7 icon in a styled circle using the EXACT HTML structure specified above
- NEVER use Material Icons, Bootstrap Icons, Heroicons, Lucide, or any other icon system — ONLY Font Awesome 4.7 (fa fa-*) ' .
        'classes
- NEVER put text content inside <i class="fa fa-*"> elements — the <i> element MUST be completely empty: <i class="fa ' .
        'fa-tasks"></i>
- NEVER generate CSS rules that set font-family on <i> elements, .fa elements, or any wildcard (*) selector — Font Awesome ' .
        'requires its own font-family and must not be overridden
- Sections MUST appear in the numbered order above — do not reorder them
- NEVER output <!DOCTYPE>, <html>, <head>, <body>, or <meta> tags — HTML fragment only
- NEVER add @media (max-width:...) or @media (min-width:...) responsive breakpoints
- NEVER add * { }, html { }, or body { } CSS rules
- NEVER set font-family in any style rule or inline style attribute — this includes rules targeting i, .fa, *, or any ' .
        'other selector
- ALL CSS selectors in the <style> block MUST be prefixed with #courseinfo-generated
- Output ONLY valid JSON with exactly two top-level keys: "html" and "structured"
- "html": the full HTML fragment as described above
- "structured": an object with the EXACT audit-compliance schema below — ALL required fields must be populated with full ' .
        'student-facing text (minimum 10 characters per field):
{
  "overview": "<2-3 sentence course summary>",
  "course_identity": { "course_title": "<full course title>", "course_code": "<unit code or empty string>" },
  "course_introduction": { "introduction": "<engaging course introduction paragraph>", "trainer_video": "" },
   "target_audience": { "learner_profile": "<who this course is for, based on supplied profile>", "entry_requirements": ' .
        '"<only supplied requirements, otherwise direct learner to contact the provider>" },
   "content_delivery": { "topics": "<supplied learning outcomes and covered topics>", "structure": "<actual sections and ' .
        'activities>", "mode": "<exact supplied delivery mode or \'Mode not specified\'>", "duration": "<supplied schedule ' .
        'and actual activity times; nominal hours only when supplied>", "prelearning": "<only supplied pre-learning ' .
        'requirements>" },
   "assessment_safety": { "assessment_methods": "<actual assessment types, pass marks and attempts from activity data>", ' .
        '"whs": "<only include where relevant and supported by supplied data>" },
  "course_fees": { "fees": "<fees and contact information>" },
   "pathways_outcomes": { "pathways": "<only supplied further-study pathways>", "licensing": "<only supplied licensing ' .
        'outcomes or empty string>", "job_outcomes": "<only supplied career outcomes or a contact-provider note>" }
}
- The "structured" fields must contain plain text only (no HTML tags)
- Output: {"html": "...", "structured": {...}}');

        $userprompt = ('Generate the ' . self::js_str((self::js_truthy($isasqa) ? 'ASQA 2025-compliant' : 'professional')) .
            ' Course Information document for:

COURSE: ' . self::js_str($coursename) . self::js_str((self::js_truthy($courseshortname) ? (' (' .
        self::js_str($courseshortname) . ')') : '')) . '
' . self::js_str((self::js_truthy(self::js_and($isaccredited, $unitcode)) ? ('UNIT DELIVERED: ' . self::js_str($unitcode) .
        self::js_str((self::js_truthy($unittitle) ? self::js_add(' — ', $unittitle) : ''))) :
            'TYPE: Non-accredited course')) . '
' . self::js_str((self::js_truthy(self::js_and($isaccredited, $nominalhours)) ? ('NOMINAL HOURS: ' .
        self::js_str($nominalhours)) : '')) . '
ORGANISATION: ' . self::js_str($sitename) . '
' . self::js_str((self::js_truthy($coursesummary) ? ('COURSE SUMMARY: ' . self::js_str($coursesummary)) : '')) . '
' . self::js_str($envinfo) . '

ACTIVITIES IN THIS COURSE (in order — these are the EXACT rows for the Step-by-Step table):
' . self::js_str($activitiessummary) . '
' . self::js_str($volumeinfo) . '

GENERATION INSTRUCTIONS:
- SECTION ORDER IS MANDATORY: Header → Overview → Course Outcomes → Entry Requirements → Mode of Delivery → ' .
        'Duration → Course Structure → Fees & Refunds → Step-by-Step Guide → Assessment Summary → Completion ' .
        'Requirements → Support Services → Learner Rights & Responsibilities' . self::js_str((self::js_truthy($isasqa) ?
            ' → RPL & Credit Transfer → ' .
        'Complaints & Appeals' : '')) . ' → Privacy & Data Protection → Consumer Rights Notice → Learner ' .
        'Acknowledgement Statement' . self::js_str((self::js_truthy($isaccredited) ? ' → Volume of Learning' : '')) . '. ' .
            'DO NOT deviate from this order.
- The Step-by-Step Completion Guide MUST be a proper HTML <table> — 3-column (step circle | activity + description | time ' .
            'pill). NO card/list layout.
- Use the RAW estimated timing for each activity — do NOT inflate or pad.
- Include specific data per activity (slide count, question count, passing grade, max attempts, due date, time limit) ' .
            'wherever available.
- NEVER write "AI-generated" in any student-facing text. Use "interactive", "structured", "scenario-based", "personalised" ' .
            'instead.
' . self::js_str((self::js_truthy(!self::js_truthy($isasqa)) ? '- This is a non-accredited course: do NOT include any ' .
        'ASQA, RTO, or regulatory compliance language. Do NOT include Complaints & Appeals or Recognition of Prior Learning ' .
        'sections. Still include Learner Rights & Responsibilities and Privacy & Data Protection sections.' : '')) . '
- Include scroll animations, hover effects on cards and table rows exactly as specified.
- Make every section card have class="ci-card ci-reveal" with appropriate animation-delay.
- The document must be stunning, professional, and print-ready.
' . self::js_str((self::js_truthy($isaccredited) ? ('- Show actual total hours — do NOT pad to match nominal hours of ' .
        self::js_str($nominalhours) . '. If non-compliant, state the ' .
        'gap clearly.') : '- Do NOT include Volume of Learning, nominal hours, or any compliance metrics.')) . '

Return ONLY: {"html": "<HTML FRAGMENT: starts with <style> block (all rules prefixed #courseinfo-generated), then content ' .
            'divs, then <script> block — NO <!DOCTYPE>, NO <html>, NO <head>, NO <body>, NO <meta> tags>", "structured": ' .
            '{"overview":"...","course_identity":{...},"course_introduction":{...},"target_audience":{...},"content_delivery"' .
                ':{...},"assessment_safety":{...},"course_fees":{...},"pathways_outcomes":{...},"acknowledgement_record":{"us' .
                'i_disclosed":true,"lln_assessment_disclosed":true,"avetmiss_disclosed":true,"complaints_process_disclosed":t' .
                'rue,"rpl_disclosed":true,"consumer_rights_disclosed":true,"learner_acknowledgement_included":true}}}');

        if ($selectedframework !== 'vet') {
            [$systemprompt, $userprompt] = self::nonvet_prompts(
                $selectedframework,
                self::parse_framework_data($frameworkdata),
                ['courseName' => $coursename, 'courseShortName' => $courseshortname, 'courseSummary' => $coursesummary,
                'organisationName' => $sitename,
                'activitiesSummary' => $activitiessummary,
                'environmentInfo' => $envinfo]
            );
        }
        $fdobject = is_array($frameworkdata) && !self::is_list($frameworkdata);
        $workplacesector = $fdobject && isset($frameworkdata['sector']) && is_string($frameworkdata['sector'])
            && preg_match('/workplace|induction|professional development/i', $frameworkdata['sector']);
        $systemprompt .= ('

FACTUALITY OVERRIDE (highest priority; applies to every section and structured field): Use only policies, fees, timeframes, ' .
            'entry requirements, support arrangements, delivery modes, assessment rules and outcomes supplied in the ' .
            'request. Never invent an organisation-specific rule, deadline, fee, age limit, consent rule, RPL or ' .
            'credit-transfer process, refund term, complaint step, privacy practice, support promise or appeal window. If a ' .
            'detail is not supplied, omit it or direct the learner to contact the organisation for its current policy. Do ' .
            'not turn any template, example or generic legal wording in this prompt into a claim about this organisation. A ' .
            'fabricated policy is an incorrect answer even if it is common practice elsewhere. Preserve teacher-provided ' .
            'learning outcomes word for word. Follow the supplied spelling setting even when it differs from generic ' .
            'Australian spelling guidance.' . self::js_str((self::js_truthy($workplacesector) ? ' For a workplace course, ' .
        'address support to the learner’s supervisor or manager, not an instructor.' : '')));
        $deliveredunit = self::js_truthy($unitcode)
            ? self::js_str($unitcode) . (self::js_truthy($unittitle) ? ' — ' . self::js_str($unittitle) : '') : '';
        $unitcopyinstruction = $deliveredunit !== '' ? ('Describe this course as delivering the unit ' .
            self::js_str($deliveredunit) . '; use wording such as "This ' .
            'course delivers the unit ' . self::js_str($deliveredunit) . '." Do not say "accredited under ' .
                self::js_str($unitcode) . '" or infer accreditation from ' .
                'a code alone.') : 'Do not infer accreditation from a unit code alone.';
        $systemprompt .= ('

UNIT AND ACTIVITY LABELS: ' . self::js_str($unitcopyinstruction) . ' For every activity, use its supplied display label ' .
        'exactly; never show an internal component name or raw identifier. In particular, use "Slide Flow", not "Product ' .
        'Explainer" or "productexplainer".');

        $builderdata = $fdobject ? $frameworkdata : self::parse_framework_data($frameworkdata);
        $outcomes = $builderdata['outcomes'] ?? null;
        if (is_array($outcomes)) {
            $frameworkoutcomes = array_values($outcomes);
        } else {
            $frameworkoutcomes = [];
        }
        $outcometexts = [];
        $seen = [];
        foreach (array_merge($learningoutcomes, $frameworkoutcomes) as $outcome) {
            if (is_string($outcome)) {
                $text = self::js_trim($outcome);
            } else if (!is_array($outcome) || !array_key_exists('text', $outcome)) {
                $text = '';
            } else {
                $t = is_string($outcome['text']) ? self::js_trim($outcome['text']) : '';
                $c = isset($outcome['code']) && is_string($outcome['code']) ? self::js_trim($outcome['code']) : '';
                $s = isset($outcome['strand']) && is_string($outcome['strand']) ? self::js_trim($outcome['strand']) : '';
                $text = implode(' ', array_filter([$s !== '' ? "[{$s}]" : '', $c !== '' ? "{$c}:" : '', $t], 'strlen'));
            }
            $key = mb_strtolower($text);
            if ($text === '' || isset($seen[$key])) {
                continue;
            }
            $seen[$key] = true;
            $outcometexts[] = $text;
        }
        $ctxlines = [];
        foreach (
            ['Country' => 'country', 'State or system' => 'state', 'Sector' => 'sector', 'Level' => 'level',
                'Subject' => 'subject', 'Learners' => 'learnerProfile', 'Language' => 'language', 'Spelling' =>
                    'spelling'] as $label => $k
        ) {
            $v = isset($builderdata[$k]) && is_string($builderdata[$k]) ? self::js_slice(
                self::js_trim($builderdata[$k]),
                0,
                300
            ) : '';
            if ($v !== '') {
                $ctxlines[] = "- {$label}: {$v}";
            }
        }
        $delivery = (isset($builderdata['delivery']) && is_array($builderdata['delivery']) &&
            !self::is_list($builderdata['delivery']))
            ? $builderdata['delivery'] : [];
        $deliverylines = [];
        if (
            isset($builderdata['delivery']) && is_string($builderdata['delivery']) &&
            self::js_trim($builderdata['delivery']) !== ''
        ) {
            $deliverylines[] = "- Teacher's delivery schedule: " . self::js_trim($builderdata['delivery']);
        }
        $num = function ($k) use ($delivery) {
            return array_key_exists($k, $delivery) ? self::js_num($delivery[$k]) : NAN;
        };
        $fin = function ($v) {
            return (is_int($v) || is_float($v)) && is_finite($v);
        };
        $totallearnerhours = $num('totalLearnerHours');
        $minutespersession = $num('minutesPerSession');
        $weeks = $num('weeks');
        $sessionsperweek = $num('sessionsPerWeek');
        if (($delivery['selfPaced'] ?? null) === true) {
            $deliverylines[] = '- Delivery mode: self-paced' . (($fin($totallearnerhours) && $totallearnerhours > 0)
                ? ', approximately ' . self::js_str($totallearnerhours) . ' learner hours' : '');
        } else if ($fin($sessionsperweek) && $sessionsperweek > 0 && $fin($minutespersession) && $minutespersession > 0) {
            $deliverylines[] = '- Schedule: ' . self::js_str($sessionsperweek) . ' session' . ($sessionsperweek == 1 ? '' : 's')
                . ' per week, ' . self::js_str($minutespersession) . ' minutes each'
                . (($fin($weeks) && $weeks > 0) ? ', over ' . self::js_str($weeks) . ' weeks' : '');
            $deliverylines[] = '- Delivery mode: scheduled sessions; do not describe as self-paced';
        }
        $startdate = $num('startDate');
        if ($fin($startdate) && $startdate > 0) {
            $deliverylines[] = '- Start date: ' . gmdate('Y-m-d', (int) floor($startdate));
        }
        $courseformat = isset($delivery['courseFormat']) && is_string($delivery['courseFormat']) ?
            self::js_trim($delivery['courseFormat']) : '';
        if ($courseformat !== '') {
            $deliverylines[] = "- Course format: {$courseformat}";
        }
        $unitlines = [];
        foreach (
            [['Unit code', self::js_or($unitcode, $builderdata['unitcode'] ?? null)],
                ['Unit title', self::js_or($unittitle, $builderdata['unittitle'] ?? null)],
                ['Nominal hours', self::js_or($nominalhours, $builderdata['nominalhours'] ?? null)]] as [$label, $value]
        ) {
            $v = (is_string($value) || is_int($value) || is_float($value)) ? self::js_trim(self::js_str($value)) : '';
            if ($v !== '') {
                $unitlines[] = "- {$label}: {$v}";
            }
        }
        if ($ctxlines) {
            $userprompt .= "\n\nTEACHING CONTEXT (from the teacher):\n" . implode("\n", $ctxlines)
                . "\nWrite for these learners and this level. Use the stated spelling.";
        }
        if ($deliverylines) {
            $userprompt .= "\n\nDELIVERY SCHEDULE (from the teacher; authoritative):\n" . implode("\n", $deliverylines)
                . "\nUse this schedule consistently in delivery mode, duration and course structure. Do not replace a " .
                    "scheduled delivery with self-paced wording.";
        }
        if ($unitlines) {
            $userprompt .= "\n\nUNIT DETAILS (from the teacher; preserve exactly):\n" . implode("\n", $unitlines);
        }
        if ($outcometexts) {
            $numbered = [];
            foreach ($outcometexts as $i => $o) {
                $numbered[] = ($i + 1) . ". {$o}";
            }
            $userprompt .= "\n\nTEACHER-PROVIDED LEARNING OUTCOMES FOR THIS COURSE (use these exact outcomes; do not " .
                "invent, merge, reword or broaden them):\n"
                . implode("\n", $numbered)
                . "\nThe Course Outcomes section must list every one of these outcomes, in this order, word for word. " .
                    "This overrides any limit on the number or length of outcome bullets. Do not add other outcomes. In " .
                    "Course Structure, show which lessons and activities teach each outcome.";
        }
        return [$systemprompt, $userprompt];
    }

    /**
     * The activity list the service writes into the prompt.
     *
     * @param array $activities Scanned activities.
     * @return string
     */
    private static function activities_summary(array $activities): string {
        $known = [
            'productexplainer' => 'Slide Flow', 'modproductexplainer' => 'Slide Flow', 'slideflow' => 'Slide Flow',
            'aiactivities' => 'Learning Activities', 'modaiactivities' => 'Learning Activities',
            'aiknowledgecheck' => 'Knowledge Check', 'modaiknowledgecheck' => 'Knowledge Check',
            'visuallearning' => 'Visual Learning', 'modvisuallearning' => 'Visual Learning',
            'contentcreator' => 'Content Creator', 'modcontentcreator' => 'Content Creator',
            'courseinfo' => 'Course Information', 'modcourseinfo' => 'Course Information',
            'learningmapping' => 'Mapping', 'modlearningmapping' => 'Mapping',
        ];
        $lines = [];
        foreach ($activities as $i => $a) {
            $a = is_array($a) ? $a : [];
            $supplied = null;
            foreach ([$a['typeLabel'] ?? null, $a['type_label'] ?? null] as $v) {
                if (is_string($v) && self::js_trim($v) !== '') {
                    $supplied = $v;
                    break;
                }
            }
            $normprovided = $supplied !== null ? preg_replace('/[\s_-]+/u', '', mb_strtolower($supplied)) : '';
            $rawtype = self::js_trim(self::js_str(self::js_or(
                self::js_or($a['type'] ?? null, $a['modname'] ?? null),
                'activity'
            )));
            $normtype = preg_replace('/[\s_-]+/u', '', mb_strtolower($rawtype));
            if (in_array($normprovided, ['productexplainer', 'modproductexplainer'], true)) {
                $friendly = 'Slide Flow';
            } else if ($supplied !== null) {
                $friendly = self::js_trim($supplied);
            } else if (isset($known[$normtype])) {
                $friendly = $known[$normtype];
            } else {
                $friendly = preg_replace('/([a-z0-9])([A-Z])/', '$1 $2', $rawtype);
                $friendly = preg_replace('/[_-]+/', ' ', $friendly);
                $friendly = preg_replace('/\s+/u', ' ', $friendly);
                $friendly = preg_replace_callback('/\b[a-z]/', function ($m) {
                    return strtoupper($m[0]);
                }, $friendly);
            }
            $line = ($i + 1) . '. [Section: ' . self::js_str(self::js_or($a['section_name'] ?? null, 'General')) . '] '
                . self::js_str(self::js_or($a['name'] ?? null, 'Untitled')) . ' (' . ($friendly !== '' ? $friendly :
                    'Activity') . ')';
            if (self::js_truthy($a['estimated_minutes'] ?? null)) {
                $line .= ' — Est. ' . self::js_str($a['estimated_minutes']) . ' min';
            }
            if (self::js_truthy($a['total_slides'] ?? null)) {
                $line .= ', ' . self::js_str($a['total_slides']) . ' slides across '
                    . self::js_str(self::js_or($a['total_topics'] ?? null, 0)) . ' topics';
            }
            if (self::js_truthy($a['topics'] ?? null) && self::js_len($a['topics']) > 0) {
                $line .= "\n   Topics: " . implode(', ', array_map(function ($t) {
                    return self::join_item(is_array($t) ? ($t['title'] ?? null) : null);
                }, (array) $a['topics']));
            }
            if (self::js_truthy($a['scenario_count'] ?? null)) {
                $line .= ', ' . self::js_str($a['scenario_count']) . ' scenarios';
            }
            if (self::js_truthy($a['question_count'] ?? null)) {
                $line .= ', ' . self::js_str($a['question_count']) . ' questions';
            }
            if (array_key_exists('max_attempts', $a) && $a['max_attempts'] !== 0) {
                $line .= ', max attempts: ' . ($a['max_attempts'] === -1 ? 'unlimited' : self::js_str($a['max_attempts']));
            }
            if (self::js_truthy($a['passing_grade'] ?? null)) {
                $line .= ', passing grade: ' . self::js_str($a['passing_grade']) . '%';
            }
            if (self::js_truthy($a['time_limit'] ?? null)) {
                $line .= ', time limit: ' . self::js_str($a['time_limit']) . ' min';
            }
            if (self::js_truthy($a['is_essay_grader'] ?? null)) {
                $line .= ', AI Essay Grader enabled';
            }
            if (self::js_truthy($a['due_date'] ?? null)) {
                $line .= ', due: ' . self::js_str($a['due_date']);
            }
            if (self::js_truthy($a['instructions'] ?? null)) {
                $line .= "\n   Instructions: " . self::js_slice($a['instructions'], 0, 200);
            }
            if (self::js_truthy($a['description'] ?? null) && ($a['description'] !== ($a['instructions'] ?? null))) {
                $line .= "\n   Description: " . self::js_slice($a['description'], 0, 150);
            }
            $lines[] = $line;
        }
        return implode("\n", $lines);
    }

    /**
     * Port of buildNonVetCourseInfoPrompts().
     *
     * @param string $id Framework id.
     * @param array $data Framework data.
     * @param array $input Course details.
     * @return string[] [system, user]
     */
    private static function nonvet_prompts(string $id, array $data, array $input): array {
        $fw = self::describe_framework($id, $data);
        $frameworkdetails = implode("\n", array_merge(['Framework: ' . $fw['label']], $fw['levels'], $fw['metadata']));
        $input['frameworkDetails'] = $frameworkdetails;
        $system = ('You write accurate, clear course information for teachers and learners in the ' .
            self::js_str(self::js_get($fw, 'label')) . ' framework. Use the framework\'s own hierarchy and terminology ' .
                'consistently. Avoid terminology and compliance language from unrelated education frameworks. Do not make ' .
                'regulatory or accreditation claims unless the supplied course data explicitly supports them. Do not invent ' .
                'credentials, fees, support contacts, or entry requirements. Leave out any section whose facts are unknown ' .
                'instead of writing "Not specified" or "Not available". Never call the content or assessments "AI-generated" ' .
                'and never use the words "AI scenarios": name activities by their titles (Visual Learning, Learning ' .
                'Activities, Knowledge Check). When the user message gives a TEACHING CONTEXT, write the overview and target ' .
                'audience for exactly those learners, at that level, in that spelling. When it gives TEACHER-PROVIDED ' .
                'LEARNING OUTCOMES, the Course Outcomes section lists every one of them word for word.

Return only a JSON object with exactly two keys: "html" and "structured". The html value must be a safe HTML fragment for ' .
            'insertion inside an existing Moodle page: no document tags, no scripts, and all CSS selectors must begin with ' .
            '#courseinfo-generated. Use semantic headings and concise cards, inherit the Moodle theme font, and do not set ' .
            'global html/body/* styles.

The structured object must contain plain-text values (no HTML) for: overview; course_identity {course_title, course_code}; ' .
            'course_introduction {introduction, trainer_video}; target_audience {learner_profile, entry_requirements}; ' .
            'content_delivery {topics, structure, mode, duration, prelearning}; assessment_safety {assessment_methods, whs}; ' .
            'course_fees {fees}; pathways_outcomes {pathways, licensing, job_outcomes}. Use empty strings when information ' .
            'is unknown. Do not imply a qualification or regulated status unless the supplied course data explicitly ' .
            'establishes it.');
        $user = ('Create the course information page using only the supplied facts.

COURSE: ' . self::js_str(self::js_get($input, 'courseName')) . self::js_str((self::js_truthy(self::js_get(
            $input,
            'courseShortName'
        )) ? (' (' . self::js_str(self::js_get($input, 'courseShortName')) . ')') : '')) . '
ORGANISATION: ' . self::js_str(self::js_get($input, 'organisationName')) . '
FRAMEWORK DETAILS:
' . self::js_str($frameworkdetails) . '
' . self::js_str((self::js_truthy(self::js_get($input, 'courseSummary')) ? ('COURSE SUMMARY:
' . self::js_str(self::js_get($input, 'courseSummary'))) : '')) . '
' . self::js_str((self::js_truthy(self::js_get($input, 'environmentInfo')) ? ('COURSE ENVIRONMENT:
' . self::js_str(self::js_get($input, 'environmentInfo'))) : '')) . '

ACTIVITIES IN COURSE:
' . self::js_str(self::js_get($input, 'activitiesSummary')) . '

Use the framework hierarchy in headings and explain what learners will learn, how the course is structured, how activities ' .
        'support the outcomes, and what assessment is known. Clearly mark unknown details instead of inventing them.

Write a complete, polished page with these sections in this order: a header with the course title and a one-line summary; ' .
        'Course Overview (2-3 sentences written for the learners described); Course Outcomes; Course Structure (one card per ' .
        'lesson listing its activities, time and the outcome it covers); How to Complete This Course (4-6 numbered steps: ' .
            'open ' .
        'each lesson in order, work through the teaching activity, do the practice activity, pass the knowledge check, track ' .
        'progress); Assessment (what each check measures and that it can be retried if that is supported); Support (how to ' .
            'get ' .
        'help from the teacher; do not invent contacts). Never describe content as "AI-generated", "AI scenarios" or ' .
            'similar: ' .
        'call activities by their names (Visual Learning, Learning Activities, Knowledge Check). Use the spelling and level ' .
        'given in the teaching context. Return JSON only.');
        return [$system, $user];
    }

    /**
     * Port of describeCourseFramework().
     *
     * @param string $id Framework id.
     * @param array $data Framework data.
     * @return array label, levels, metadata
     */
    private static function describe_framework(string $id, array $data): array {
        $defs = [
            'vet' => ['VET', [['unit', 'Unit'], ['element', 'Element'], ['performanceCriterion', 'Performance criterion']],
                [['unitCode', 'Unit code'], ['aqfLevel', 'AQF level'], ['nominalHours', 'Nominal hours'],
                 ['performanceEvidence', 'Performance evidence'], ['knowledgeEvidence', 'Knowledge evidence']]],
            'au_curriculum' => ['Australian Curriculum', [['learningArea', 'Learning area'], ['strand', 'Strand'],
                ['subStrand', 'Sub-strand'], ['contentDescriptor', 'Content descriptor']],
                [['yearLevel', 'Year level'], ['achievementStandard', 'Achievement standard']]],
            'sg_moe' => ['Singapore MOE', [['syllabus', 'Syllabus'], ['theme', 'Theme'], ['learningOutcome', 'Learning ' .
                'outcome']],
                [['level', 'Level'], ['syllabusCode', 'Syllabus code']]],
            'university' => ['University', [['course', 'Course'], ['courseLearningOutcome', 'Course learning outcome']],
                [['creditPoints', 'Credit points'], ['graduateAttributes', 'Graduate attributes']]],
            'workplace' => ['Workplace', [['program', 'Program'], ['requirement', 'Requirement']],
                [['role', 'Role'], ['policyReference', 'Policy reference']]],
            'custom' => ['Custom', [], []],
        ];
        [$label, $levelkeys, $metakeys] = $defs[$id];
        $levels = [];
        if ($id === 'custom') {
            $custom = isset($data['customLevels']) && is_array($data['customLevels']) && self::is_list($data['customLevels'])
                ? array_slice($data['customLevels'], 0, 4) : [];
            foreach ($custom as $item) {
                if (!is_array($item)) {
                    continue;
                }
                $l = self::clean_text($item['label'] ?? null, 80);
                $v = self::clean_text($item['value'] ?? null);
                if ($l !== '' && $v !== '') {
                    $levels[] = "{$l}: {$v}";
                }
            }
        } else {
            foreach ($levelkeys as [$k, $l]) {
                $v = self::clean_text($data[$k] ?? null);
                if ($v !== '') {
                    $levels[] = "{$l}: {$v}";
                }
            }
        }
        $metadata = [];
        foreach ($metakeys as [$k, $l]) {
            $v = self::clean_text($data[$k] ?? null);
            if ($v !== '') {
                $metadata[] = "{$l}: {$v}";
            }
        }
        return ['label' => $label, 'levels' => $levels, 'metadata' => $metadata];
    }

    /**
     * Port of parseCourseFrameworkData().
     *
     * @param mixed $value Framework data or its JSON.
     * @return array
     */
    private static function parse_framework_data($value): array {
        if (is_string($value)) {
            $parsed = json_decode($value, true);
            return (is_array($parsed) && !self::is_list($parsed)) ? $parsed : [];
        }
        return (is_array($value) && !self::is_list($value)) ? $value : [];
    }

    /**
     * Port of cleanText().
     *
     * @param mixed $value Value.
     * @param int $limit UTF-16 length limit.
     * @return string
     */
    private static function clean_text($value, int $limit = 1000): string {
        if (!is_string($value) && !is_int($value) && !is_float($value)) {
            return '';
        }
        $s = preg_replace('/[\x{0000}-\x{0008}\x{000B}\x{000C}\x{000E}-\x{001F}]/u', '', self::js_str($value));
        return self::js_slice(self::js_trim($s), 0, $limit);
    }

    /**
     * Template value as JavaScript prints it: "undefined" for a missing key.
     *
     * @param array $a Object.
     * @param string $k Key.
     * @return string
     */
    private static function tplv(array $a, string $k): string {
        return array_key_exists($k, $a) ? self::js_str($a[$k]) : 'undefined';
    }

    /**
     * Array.join() prints null and undefined as empty.
     *
     * @param mixed $v Item.
     * @return string
     */
    private static function join_item($v): string {
        return $v === null ? '' : self::js_str($v);
    }

    /**
     * JavaScript String.trim(): also trims Unicode spaces.
     *
     * @param mixed $s Text.
     * @return string
     */
    private static function js_trim($s): string {
        return preg_replace(
            '/^[\s\x{00A0}\x{FEFF}\x{1680}\x{2000}-\x{200A}\x{2028}\x{2029}\x{202F}\x{205F}\x{3000}]+'
            . '|[\s\x{00A0}\x{FEFF}\x{1680}\x{2000}-\x{200A}\x{2028}\x{2029}\x{202F}\x{205F}\x{3000}]+$/u',
            '',
            self::js_str($s)
        );
    }

    /**
     * The service's darkenHex().
     *
     * @param string $hex Colour.
     * @param float $amt Amount.
     * @return string
     */
    private static function darken(string $hex, float $amt = 0.22): string {
        $c = str_replace('#', '', $hex);
        if (strlen($c) !== 6) {
            return $hex;
        }
        $out = '#';
        foreach ([0, 2, 4] as $i) {
            $out .= str_pad(dechex((int) max(0, self::js_round(hexdec(substr($c, $i, 2)) * (1 - $amt)))), 2, '0', STR_PAD_LEFT);
        }
        return $out;
    }

    /**
     * The service's tintHex().
     *
     * @param string $hex Colour.
     * @param float $strength 0 = white, 1 = full colour.
     * @return string
     */
    private static function tint(string $hex, float $strength): string {
        $c = str_replace('#', '', $hex);
        if (strlen($c) !== 6) {
            return $hex;
        }
        $out = '#';
        foreach ([0, 2, 4] as $i) {
            $out .= str_pad(
                dechex((int) self::js_round(255 + (hexdec(substr($c, $i, 2)) - 255) * $strength)),
                2,
                '0',
                STR_PAD_LEFT
            );
        }
        return $out;
    }

    /**
     * JavaScript Math.round (halves round up).
     *
     * @param float $v Value.
     * @return float
     */
    private static function js_round(float $v): float {
        return floor($v + 0.5);
    }

    /**
     * Call the site's own AI for JSON.
     *
     * @param string $system System prompt.
     * @param string $user User prompt.
     * @param int $maxtokens Output budget.
     * @param float $temperature Temperature.
     * @return string
     */
    private static function ai(string $system, string $user, int $maxtokens, float $temperature): string {
        if (self::$aicall) {
            return (string) call_user_func(self::$aicall, $system, $user, $maxtokens, $temperature);
        }
        return \local_aiconfig\byok\ai::text(self::COMPONENT, $system, $user, [
            'json' => true, 'maxtokens' => $maxtokens, 'temperature' => $temperature,
        ]);
    }

    /**
     * A teacher-readable message for a failed AI call.
     *
     * @param \Throwable $e The failure.
     * @return string
     */
    private static function message(\Throwable $e): string {
        if ($e instanceof \moodle_exception && $e->errorcode === 'byok_notavailable') {
            return get_string('byok_notavailable', 'courseinfo');
        }
        return get_string('byok_failed', 'courseinfo', s($e->getMessage()));
    }

    /**
     * array_is_list() for PHP before 8.1.
     *
     * @param array $a Array.
     * @return bool
     */
    private static function is_list(array $a): bool {
        return $a === [] || array_keys($a) === range(0, count($a) - 1);
    }

    // JavaScript semantics used by the ported prompt templates (generated with the port).

    /**
     * Js truthy.
     *
     * @param mixed $v
     * @return bool JavaScript truthiness.
     */
    private static function js_truthy($v): bool {
        if (is_array($v) || is_object($v)) {
            return true;
        }
        if (is_float($v) && is_nan($v)) {
            return false;
        }
        return $v !== null && $v !== false && $v !== '' && $v !== 0 && $v !== 0.0;
    }

    /**
     * Js or.
     *
     * @param mixed $a
     * @param mixed $b
     * @return mixed a || b
     */
    private static function js_or($a, $b) {
        return self::js_truthy($a) ? $a : $b;
    }

    /**
     * Js and.
     *
     * @param mixed $a
     * @param mixed $b
     * @return mixed a && b
     */
    private static function js_and($a, $b) {
        return self::js_truthy($a) ? $b : $a;
    }

    /**
     * Js add.
     *
     * @param mixed $a
     * @param mixed $b
     * @return mixed a + b
     */
    private static function js_add($a, $b) {
        if ((is_int($a) || is_float($a)) && (is_int($b) || is_float($b))) {
            return $a + $b;
        }
        return self::js_str($a) . self::js_str($b);
    }

    /**
     * Js str.
     *
     * @param mixed $v
     * @return string String(v)
     */
    private static function js_str($v): string {
        if ($v === null) {
            return 'null';
        }
        if ($v === true) {
            return 'true';
        }
        if ($v === false) {
            return 'false';
        }
        if (is_float($v)) {
            if (floor($v) == $v && abs($v) < 1e15) {
                return (string) (int) $v;
            }
            return rtrim(rtrim(sprintf('%.15F', $v), '0'), '.');
        }
        if (is_array($v)) {
            if ($v === [] || array_keys($v) === range(0, count($v) - 1)) {
                return implode(',', array_map(function ($x) {
                    return $x === null ? '' : self::js_str($x);
                }, $v));
            }
            return '[object Object]';
        }
        return (string) $v;
    }

    /**
     * Js num.
     *
     * @param mixed $v
     * @return float Number(v)
     */
    private static function js_num($v) {
        if (is_int($v) || is_float($v)) {
            return $v;
        }
        if ($v === null || $v === false || (is_string($v) && trim($v) === '')) {
            return 0;
        }
        if ($v === true) {
            return 1;
        }
        return is_numeric(trim((string) $v)) ? trim((string) $v) + 0 : NAN;
    }

    /**
     * Js get.
     *
     * @param mixed $o
     * @param mixed $k
     * @return mixed o[k] or null
     */
    private static function js_get($o, $k) {
        if (is_array($o)) {
            return $o[$k] ?? null;
        }
        if (is_string($o) && is_int($k)) {
            return mb_substr($o, $k, 1);
        }
        return null;
    }

    /**
     * Js len.
     *
     * @param mixed $v
     * @return int .length
     */
    private static function js_len($v): int {
        if (is_array($v)) {
            return count($v);
        }
        return strlen(mb_convert_encoding(self::js_str($v), 'UTF-16LE', 'UTF-8')) / 2;
    }

    /**
     * Js includes.
     *
     * @param mixed $h
     * @param mixed $n
     * @return bool .includes()
     */
    private static function js_includes($h, $n): bool {
        if (is_array($h)) {
            return in_array($n, $h, true);
        }
        return strpos(self::js_str($h), self::js_str($n)) !== false;
    }

    /**
     * Js slice.
     *
     * @param mixed $v
     * @param int $a
     * @param int|null $b
     * @return mixed .slice() on strings (UTF-16) and arrays
     */
    private static function js_slice($v, $a = 0, $b = null) {
        if (is_array($v)) {
            $n = count($v);
            $a = $a < 0 ? max(0, $n + $a) : min($a, $n);
            $b = $b === null ? $n : ($b < 0 ? max(0, $n + $b) : min($b, $n));
            return array_slice($v, $a, max(0, $b - $a));
        }
        $u = mb_convert_encoding(self::js_str($v), 'UTF-16LE', 'UTF-8');
        $n = strlen($u) / 2;
        $a = $a < 0 ? max(0, $n + $a) : min($a, $n);
        $b = $b === null ? $n : ($b < 0 ? max(0, $n + $b) : min($b, $n));
        return mb_convert_encoding(substr($u, $a * 2, max(0, $b - $a) * 2), 'UTF-8', 'UTF-16LE');
    }

    /**
     * Js map.
     *
     * @param mixed $v
     * @param callable $f
     * @return array .map()
     */
    private static function js_map($v, callable $f): array {
        $out = [];
        foreach (array_values((array) $v) as $i => $x) {
            $out[] = $f($x, $i);
        }
        return $out;
    }

    /**
     * Js filter.
     *
     * @param mixed $v
     * @param callable $f
     * @return array .filter()
     */
    private static function js_filter($v, callable $f): array {
        $out = [];
        foreach (array_values((array) $v) as $i => $x) {
            if (self::js_truthy($f($x, $i))) {
                $out[] = $x;
            }
        }
        return $out;
    }

    /**
     * Js typeof.
     *
     * @param mixed $v
     * @return string typeof
     */
    private static function js_typeof($v): string {
        if (is_string($v)) {
            return 'string';
        }
        if (is_int($v) || is_float($v)) {
            return 'number';
        }
        if (is_bool($v)) {
            return 'boolean';
        }
        return $v === null ? 'undefined' : 'object';
    }

    /**
     * Js replace first.
     *
     * @param mixed $s
     * @param string $a
     * @param string $b
     * @return string .replace(string, string)
     */
    private static function js_replace_first($s, $a, $b): string {
        $s = self::js_str($s);
        $p = strpos($s, $a);
        return $p === false ? $s : substr_replace($s, $b, $p, strlen($a));
    }
}
