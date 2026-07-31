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

class volume_calculator {

    protected $nominalhours;
    protected $activities;

    protected static $TYPE_CATEGORIES = array(
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
    );

    /* Canonical display order for all category keys. */
    protected static $CATEGORY_ORDER = array(
        'contentcreator', 'aiactivities', 'essaymaker',
        'knowledgecheck', 'assignment', 'practicalassessment', 'other',
    );

    protected static $CATEGORY_LABELS = array(
        'contentcreator'      => 'AI Content Creator',
        'aiactivities'        => 'AI Learning Activities',
        'essaymaker'          => 'AI Essay Maker',
        'knowledgecheck'      => 'AI Knowledge Check',
        'assignment'          => 'Assignments',
        'practicalassessment' => 'Practical Assessments',
        'other'               => 'Other Activities',
    );

    public function __construct($nominalhours, $activities) {
        $this->nominalhours = max(1, (int)$nominalhours);
        $this->activities   = $activities;
    }

    public function calculate() {

        /* ── Initialise flat breakdown (global category totals) ──────────── */
        $breakdown = array();
        foreach (self::$CATEGORY_ORDER as $cat) {
            $breakdown[$cat] = array(
                'label'   => self::$CATEGORY_LABELS[$cat],
                'minutes' => 0,
                'hours'   => 0,
                'count'   => 0,
            );
        }

        /* ── Per-section data keyed by section_num ───────────────────────── */
        $sectionData = array();

        $totalMinutes = 0;

        foreach ($this->activities as $activity) {
            $type     = $activity['type'] ?? 'other';
            $category = self::$TYPE_CATEGORIES[$type] ?? 'other';
            $minutes  = (int)($activity['estimated_minutes'] ?? 15);
            $snum     = (int)($activity['section_num'] ?? 0);
            $sname    = $activity['section_name'] ?? 'General';

            /* Flat global breakdown */
            $breakdown[$category]['minutes'] += $minutes;
            $breakdown[$category]['count']++;
            $totalMinutes += $minutes;

            /* Per-section breakdown — initialise section entry if needed */
            if (!isset($sectionData[$snum])) {
                $sectionData[$snum] = array(
                    'num'           => $snum,
                    'name'          => $sname,
                    'total_minutes' => 0,
                    'total_hours'   => 0,
                    'categories'    => array(),
                );
                /* Pre-populate categories in canonical order so JSON preserves it */
                foreach (self::$CATEGORY_ORDER as $cat) {
                    $sectionData[$snum]['categories'][$cat] = array(
                        'label'   => self::$CATEGORY_LABELS[$cat],
                        'minutes' => 0,
                        'hours'   => 0,
                        'count'   => 0,
                    );
                }
            }

            $sectionData[$snum]['categories'][$category]['minutes'] += $minutes;
            $sectionData[$snum]['categories'][$category]['count']++;
            $sectionData[$snum]['total_minutes']                    += $minutes;
        }

        /* ── Compute hours ───────────────────────────────────────────────── */
        foreach ($breakdown as &$data) {
            $data['hours'] = round($data['minutes'] / 60, 1);
        }
        unset($data);

        ksort($sectionData); // chronological order by section number
        foreach ($sectionData as &$sec) {
            $sec['total_hours'] = round($sec['total_minutes'] / 60, 1);
            foreach ($sec['categories'] as &$cat) {
                $cat['hours'] = round($cat['minutes'] / 60, 1);
            }
            unset($cat);
        }
        unset($sec);

        $sectionsBreakdown = array_values($sectionData);

        /* ── Compliance calculation ──────────────────────────────────────── */
        $nominalMinutes = $this->nominalhours * 60;
        $totalHours     = round($totalMinutes / 60, 1);
        $gap            = round(($nominalMinutes - $totalMinutes) / 60, 1);

        return array(
            'nominal_hours'      => $this->nominalhours,
            'nominal_minutes'    => $nominalMinutes,
            'total_minutes'      => $totalMinutes,
            'total_hours'        => $totalHours,
            'compliant'          => $totalMinutes >= $nominalMinutes,
            'gap_hours'          => max(0, $gap),
            'breakdown'          => $breakdown,          // flat totals (kept for backward compat)
            'sections_breakdown' => $sectionsBreakdown,  // per-section, per-category (v1.0.19+)
            'activities'         => $this->activities,
        );
    }

    public static function lookup_nominal_hours($unitcode) {
        global $DB;
        try {
            if ($DB->get_manager()->table_exists('rtocompliance_units')) {
                $unit = $DB->get_record_select('rtocompliance_units',
                    $DB->sql_like('unitcode', ':code', false),
                    array('code' => $unitcode),
                    'nominalhours'
                );
                if ($unit && !empty($unit->nominalhours)) {
                    return (int)$unit->nominalhours;
                }
            }

            if ($DB->get_manager()->table_exists('rtocompliance_tas_units')) {
                $unit = $DB->get_record_select('rtocompliance_tas_units',
                    $DB->sql_like('unitcode', ':code', false),
                    array('code' => $unitcode),
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
