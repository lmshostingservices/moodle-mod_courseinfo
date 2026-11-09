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

/**
 * mod_courseinfo file.
 *
 * @package    mod_courseinfo
 * @copyright  2026 LMS-Labs
 * @license    http://www.gnu.org/licenses/gpl-3.0.html GNU GPL v3 or later
 */

namespace mod_courseinfo;

defined('MOODLE_INTERNAL') || die();

/**
 * Volume calculator.
 */
class volume_calculator {
    protected $nominalhours;
    protected $activities;

    protected static $type_categories = [
        'contentcreator'      => 'contentcreator',
        'aiactivities'        => 'aiactivities',
        'essaymaker'          => 'essaymaker',
        'knowledgecheck'      => 'knowledgecheck',
        'assignment'          => 'assignment',
        'practicalassessment' => 'practicalassessment',
        'quiz'                => 'other',
        'forum'               => 'other',
        'resource'            => 'other',
        'videoactivity'       => 'other',
        'other'               => 'other',
    ];

    /* Canonical display order for all category keys. */
    protected static $category_order = [
        'contentcreator', 'aiactivities', 'essaymaker',
        'knowledgecheck', 'assignment', 'practicalassessment', 'other',
    ];

    protected static $category_labels = [
        'contentcreator'      => 'AI Content Creator',
        'aiactivities'        => 'AI Learning Activities',
        'essaymaker'          => 'AI Essay Maker',
        'knowledgecheck'      => 'AI Knowledge Check',
        'assignment'          => 'Assignments',
        'practicalassessment' => 'Practical Assessments',
        'other'               => 'Other Activities',
    ];

    /**
     * Construct.
     */
    public function __construct($nominalhours, $activities) {
        $this->nominalhours = max(1, (int)$nominalhours);
        $this->activities   = $activities;
    }

    /**
     * Calculate.
     */
    public function calculate() {

        /* ── Initialise flat breakdown (global category totals) ──────────── */
        $breakdown = [];
        foreach (self::$category_order as $cat) {
            $breakdown[$cat] = [
                'label'   => self::$category_labels[$cat],
                'minutes' => 0,
                'hours'   => 0,
                'count'   => 0,
            ];
        }

        /* ── Per-section data keyed by section_num ───────────────────────── */
        $sectiondata = [];

        $totalminutes = 0;

        foreach ($this->activities as $activity) {
            $type     = $activity['type'] ?? 'other';
            $category = self::$type_categories[$type] ?? 'other';
            $minutes  = (int)($activity['estimated_minutes'] ?? 15);
            $snum     = (int)($activity['section_num'] ?? 0);
            $sname    = $activity['section_name'] ?? 'General';

            /* Flat global breakdown */
            $breakdown[$category]['minutes'] += $minutes;
            $breakdown[$category]['count']++;
            $totalminutes += $minutes;

            /* Per-section breakdown — initialise section entry if needed */
            if (!isset($sectiondata[$snum])) {
                $sectiondata[$snum] = [
                    'num'           => $snum,
                    'name'          => $sname,
                    'total_minutes' => 0,
                    'total_hours'   => 0,
                    'categories'    => [],
                ];
                /* Pre-populate categories in canonical order so JSON preserves it */
                foreach (self::$category_order as $cat) {
                    $sectiondata[$snum]['categories'][$cat] = [
                        'label'   => self::$category_labels[$cat],
                        'minutes' => 0,
                        'hours'   => 0,
                        'count'   => 0,
                    ];
                }
            }

            $sectiondata[$snum]['categories'][$category]['minutes'] += $minutes;
            $sectiondata[$snum]['categories'][$category]['count']++;
            $sectiondata[$snum]['total_minutes']                    += $minutes;
        }

        /* ── Compute hours ───────────────────────────────────────────────── */
        foreach ($breakdown as &$data) {
            $data['hours'] = round($data['minutes'] / 60, 1);
        }
        unset($data);

        ksort($sectiondata); // chronological order by section number
        foreach ($sectiondata as &$sec) {
            $sec['total_hours'] = round($sec['total_minutes'] / 60, 1);
            foreach ($sec['categories'] as &$cat) {
                $cat['hours'] = round($cat['minutes'] / 60, 1);
            }
            unset($cat);
        }
        unset($sec);

        $sectionsbreakdown = array_values($sectiondata);

        /* ── Compliance calculation ──────────────────────────────────────── */
        $nominalminutes = $this->nominalhours * 60;
        $totalhours     = round($totalminutes / 60, 1);
        $gap            = round(($nominalminutes - $totalminutes) / 60, 1);

        return [
            'nominal_hours'      => $this->nominalhours,
            'nominal_minutes'    => $nominalminutes,
            'total_minutes'      => $totalminutes,
            'total_hours'        => $totalhours,
            'compliant'          => $totalminutes >= $nominalminutes,
            'gap_hours'          => max(0, $gap),
            'breakdown'          => $breakdown, // flat totals (kept for backward compat)
            'sections_breakdown' => $sectionsbreakdown, // per-section, per-category (v1.0.19+)
            'activities'         => $this->activities,
        ];
    }

    /**
     * Lookup nominal hours.
     */
    public static function lookup_nominal_hours($unitcode) {
        global $DB;
        try {
            if ($DB->get_manager()->table_exists('rtocompliance_units')) {
                $unit = $DB->get_record_select(
                    'rtocompliance_units',
                    $DB->sql_like('unitcode', ':code', false),
                    ['code' => $unitcode],
                    'nominalhours'
                );
                if ($unit && !empty($unit->nominalhours)) {
                    return (int)$unit->nominalhours;
                }
            }

            if ($DB->get_manager()->table_exists('rtocompliance_tas_units')) {
                $unit = $DB->get_record_select(
                    'rtocompliance_tas_units',
                    $DB->sql_like('unitcode', ':code', false),
                    ['code' => $unitcode],
                    'nominalhours'
                );
                if ($unit && !empty($unit->nominalhours)) {
                    return (int)$unit->nominalhours;
                }
            }
        } catch (\Exception $e) {
            // rtocompliance not installed.
        }
        return null;
    }
}
