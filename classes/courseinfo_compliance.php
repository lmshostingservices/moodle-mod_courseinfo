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

/**
 * Validates generated course information against the audit-derived compliance structure.
 *
 * This is the audit shield: it checks not just that sections exist but that
 * the required fields inside each section are populated with meaningful content.
 *
 * Usage:
 *   $result = courseinfo_compliance::validate($structured);
 *   if (!$result['compliant']) {
 *       // $result['missing'] lists every field that needs attention
 *   }
 *
 * @package   mod_courseinfo
 * @since     v1.0.38
 */
class courseinfo_compliance {

    /** Minimum character length for a field to be considered "populated". */
    const MIN_FIELD_LENGTH = 10;

    /**
     * Validates a structured course information array against the full audit schema.
     *
     * @param  array  $data  Decoded structured JSON from AI response
     * @return array  [ 'compliant' => bool, 'missing' => string[], 'warnings' => string[], 'score' => int ]
     */
    public static function validate(array $data): array {

        $structure = courseinfo_structure::get();
        $missing   = [];
        $warnings  = [];
        $total     = 0;
        $passed    = 0;

        foreach ($structure as $sectionKey => $section) {

            // Section-level check.
            if ($section['required']) {
                $total++;
                if (empty($data[$sectionKey])) {
                    $missing[] = "{$section['label']} section is missing";
                    continue;
                }
                $passed++;
            }

            // Field-level check.
            foreach ($section['fields'] as $fieldKey => $required) {
                if (!$required) {
                    continue;  // Optional fields: skip for compliance score
                }

                $total++;
                $value = '';
                if (is_array($data[$sectionKey] ?? null)) {
                    $value = $data[$sectionKey][$fieldKey] ?? '';
                }

                if (empty($value) || strlen(trim((string)$value)) < self::MIN_FIELD_LENGTH) {
                    $missing[] = "{$section['label']} → {$fieldKey} is missing or too short";
                } else {
                    $passed++;
                }
            }
        }

        // Optional field warnings (not missing, but worth noting if empty).
        foreach ($structure as $sectionKey => $section) {
            foreach ($section['fields'] as $fieldKey => $required) {
                if ($required) {
                    continue;
                }
                $value = '';
                if (is_array($data[$sectionKey] ?? null)) {
                    $value = $data[$sectionKey][$fieldKey] ?? '';
                }
                if (empty($value)) {
                    $warnings[] = "{$section['label']} → {$fieldKey} is optional but not populated";
                }
            }
        }

        $score = $total > 0 ? (int)round(($passed / $total) * 100) : 0;

        return [
            'compliant' => empty($missing),
            'missing'   => $missing,
            'warnings'  => $warnings,
            'score'     => $score,
            'passed'    => $passed,
            'total'     => $total,
        ];
    }

    /**
     * Returns a human-readable compliance summary string.
     *
     * @param  array $result  Return value of validate()
     * @return string
     */
    public static function summary(array $result): string {
        if ($result['compliant']) {
            return "Audit compliant ({$result['score']}% — all required fields populated)";
        }
        $count = count($result['missing']);
        return "Not audit compliant ({$result['score']}% — {$count} required field(s) missing)";
    }
}
