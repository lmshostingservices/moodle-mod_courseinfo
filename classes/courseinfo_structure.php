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
 * Audit-derived section and field structure for course information documents.
 *
 * This class defines the canonical audit schema that every generated course
 * information document must satisfy. The structure is hierarchical:
 *   Level 1 — main sections (auditors check these exist)
 *   Level 2 — fields inside each section (auditors check specific content)
 *
 * Fields marked required=true must be populated for the document to be
 * considered audit-compliant. Fields marked required=false are optional
 * (e.g. trainer_video, licensing) but should be populated where relevant.
 *
 * @package   mod_courseinfo
 * @since     v1.0.38
 */
class courseinfo_structure {

    /**
     * Returns the full audit-locked section and field schema.
     *
     * @return array Nested array: section_key => [ label, required, fields => [ field_key => required ] ]
     */
    public static function get(): array {
        return [

            'overview' => [
                'label'    => 'Overview',
                'required' => true,
                'fields'   => [],
            ],

            'course_identity' => [
                'label'    => 'Course Identity',
                'required' => true,
                'fields'   => [
                    'course_title' => true,
                    'course_code'  => false,  // Optional: only required for accredited units
                ],
            ],

            'course_introduction' => [
                'label'    => 'Course Introduction',
                'required' => true,
                'fields'   => [
                    'introduction'  => true,
                    'trainer_video' => false,  // Optional: populated when video is available
                ],
            ],

            'target_audience' => [
                'label'    => 'Target Audience',
                'required' => true,
                'fields'   => [
                    'learner_profile'    => true,
                    'entry_requirements' => true,
                ],
            ],

            'content_delivery' => [
                'label'    => 'Course Content & Delivery',
                'required' => true,
                'fields'   => [
                    'topics'      => true,
                    'structure'   => true,
                    'mode'        => true,
                    'duration'    => true,
                    'prelearning' => true,
                ],
            ],

            'assessment_safety' => [
                'label'    => 'Assessment & Safety',
                'required' => true,
                'fields'   => [
                    'assessment_methods' => true,
                    'whs'                => true,
                ],
            ],

            'course_fees' => [
                'label'    => 'Course Fees',
                'required' => true,
                'fields'   => [
                    'fees' => true,
                ],
            ],

            'pathways_outcomes' => [
                'label'    => 'Pathways & Outcomes',
                'required' => true,
                'fields'   => [
                    'pathways'     => true,
                    'licensing'    => false,  // Optional: only for licensed occupations
                    'job_outcomes' => true,
                ],
            ],
        ];
    }

    /**
     * Returns the list of required field keys for a given section key.
     *
     * @param  string $sectionKey
     * @return string[]
     */
    public static function required_fields(string $sectionKey): array {
        $structure = self::get();
        if (empty($structure[$sectionKey]['fields'])) {
            return [];
        }
        return array_keys(array_filter($structure[$sectionKey]['fields']));
    }

    /**
     * Returns only the section keys that are required.
     *
     * @return string[]
     */
    public static function required_sections(): array {
        return array_keys(array_filter(
            self::get(),
            fn($s) => !empty($s['required'])
        ));
    }
}
