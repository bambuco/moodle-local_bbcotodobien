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

namespace local_bbcotodobien\local\rules;

use local_bbcotodobien\local\detail;
use local_bbcotodobien\local\result;

/**
 * RF-R06: activities with a native start/end pair have both dates set.
 *
 * @package    local_bbcotodobien
 * @copyright  2026 David Herney @ BambuCo
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class section_activity_dates extends base {
    /**
     * Default display name.
     *
     * @return string
     */
    public static function get_name(): string {
        return get_string('rule_section_activity_dates', 'local_bbcotodobien');
    }

    /**
     * Human-readable description.
     *
     * @return string
     */
    public static function get_description(): string {
        return get_string('rule_section_activity_dates_desc', 'local_bbcotodobien');
    }

    /**
     * Evaluation granularity.
     *
     * @return string
     */
    public static function get_granularity(): string {
        return self::GRANULARITY_SECTION;
    }

    /**
     * Stored parameter names.
     *
     * @return string[]
     */
    public function get_config_param_names(): array {
        return ['excludesections', 'includehiddensections'];
    }

    /**
     * Add configuration fields.
     *
     * @param \MoodleQuickForm $mform Form to extend
     */
    public function add_config_form_elements($mform): void {
        section_helper::add_exclude_element($mform);
        section_helper::add_includehiddensections_element($mform);
    }

    /**
     * Evaluate activities in course sections.
     *
     * @param \stdClass $course Course record
     * @param array $params Decoded rule parameters
     * @return result
     */
    public function evaluate(\stdClass $course, array $params): result {
        $excluded = section_helper::parse_excluded_sections($params['excludesections'] ?? '');
        $sections = section_helper::get_numbered_sections(
            $course,
            $excluded,
            !empty($params['includehiddensections'])
        );
        if (!$sections) {
            return $this->result_from_details([]);
        }

        $modinfo = get_fast_modinfo($course);
        $details = [];
        foreach ($sections as $section) {
            $cmids = $modinfo->get_sections()[(int) $section->sectionnum] ?? [];
            foreach ($cmids as $cmid) {
                if (!isset($modinfo->cms[$cmid])) {
                    continue;
                }
                $cm = $modinfo->cms[$cmid];
                if ($cm->deletioninprogress) {
                    continue;
                }
                $detail = $this->evaluate_cm($cm);
                if ($detail) {
                    $details[] = $detail;
                }
            }
        }

        return $this->result_from_details($details);
    }

    /**
     * Evaluate one course module, or null when it is not applicable.
     *
     * @param \cm_info $cm Course module
     * @return detail|null
     */
    protected function evaluate_cm(\cm_info $cm): ?detail {
        $slots = $this->get_date_slots($cm);
        if ($slots === null || count($slots) < 2) {
            return null;
        }

        $set = array_filter($slots, static fn(int $value): bool => $value > 0);
        $name = $cm->get_formatted_name();
        if (count($set) === 1) {
            return $this->make_detail(
                self::GRANULARITY_CM,
                (int) $cm->id,
                $name,
                self::STATUS_NA,
                'ruleactivitydates_na'
            );
        }
        if (count($set) >= 2) {
            return $this->make_detail(
                self::GRANULARITY_CM,
                (int) $cm->id,
                $name,
                self::STATUS_PASS,
                'ruleactivitydates_pass'
            );
        }

        return $this->make_detail(
            self::GRANULARITY_CM,
            (int) $cm->id,
            $name,
            self::STATUS_FAIL,
            'ruleactivitydates_fail'
        );
    }

    /**
     * Render formative HTML for a stored detail.
     *
     * @param detail $detail Diagnostic detail
     * @return string HTML
     */
    public function render_detail(detail $detail): string {
        $identifier = $detail->fields['identifier'] ?? '';
        return match ($identifier) {
            'ruleactivitydates_na' => get_string('ruleactivitydates_na', 'local_bbcotodobien'),
            'ruleactivitydates_pass' => get_string('ruleactivitydates_pass', 'local_bbcotodobien'),
            'ruleactivitydates_fail' => get_string('ruleactivitydates_fail', 'local_bbcotodobien'),
            default => parent::render_detail($detail),
        };
    }

    /**
     * Return native date slot timestamps, or null when the module has no start/end pair.
     *
     * Moodle omits zero dates from cm customdata, so the instance table is the source of the pair.
     *
     * @param \cm_info $cm Course module
     * @return int[]|null
     */
    protected function get_date_slots(\cm_info $cm): ?array {
        global $DB;

        $class = 'mod_' . $cm->modname . '\\dates';
        if (!class_exists($class) || !is_subclass_of($class, \core\activity_dates::class)) {
            return null;
        }
        if (!cm_locator::table_exists($cm->modname)) {
            return null;
        }

        $instance = $DB->get_record($cm->modname, ['id' => $cm->instance]);
        if (!$instance) {
            return null;
        }

        $slots = [];
        $hasstart = false;
        $hasend = false;
        foreach ((array) $instance as $key => $value) {
            if (is_array($value) || is_object($value) || is_bool($value)) {
                continue;
            }
            if ($value !== null && $value !== '' && !is_numeric($value)) {
                continue;
            }
            $keyname = (string) $key;
            if (preg_match('/(cutoff|assess)/i', $keyname)) {
                continue;
            }
            if (!preg_match('/(date|time|due|deadline|available|open|close)/i', $keyname)) {
                continue;
            }
            $isstart = (bool) preg_match('/(open|start|from|^available$|availablefrom)/i', $keyname);
            $isend = (bool) preg_match('/(due|close|end|deadline|until|availableto|availableuntil)/i', $keyname);
            if (!$isstart && !$isend) {
                continue;
            }
            $slots[$keyname] = (int) $value;
            $hasstart = $hasstart || $isstart;
            $hasend = $hasend || $isend;
        }

        if (!$hasstart || !$hasend) {
            return null;
        }
        return $slots;
    }
}
