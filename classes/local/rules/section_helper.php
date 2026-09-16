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

/**
 * Helpers for section-level audit rules.
 *
 * @package    local_bbcotodobien
 * @copyright  2026 David Herney @ BambuCo
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class section_helper {
    /**
     * Parse excluded section numbers from a CSV param.
     *
     * Accepts non-negative integers including 0. Empty tokens and non-digit
     * values are ignored.
     *
     * @param array|string $value Raw param
     * @return int[]
     */
    public static function parse_excluded_sections(array|string $value): array {
        if (is_array($value)) {
            $parts = $value;
        } else {
            $parts = explode(',', $value);
        }
        $excluded = [];
        foreach ($parts as $part) {
            $token = trim((string) $part);
            if ($token === '' || !preg_match('/^\d+$/', $token)) {
                continue;
            }
            $number = (int) $token;
            $excluded[$number] = $number;
        }
        return array_values($excluded);
    }

    /**
     * Return course sections minus exclusions and optionally hidden ones.
     *
     * Section 0 is included like any other section. Hidden sections
     * (course_sections.visible = 0) are omitted unless $includehidden is true.
     * Availability conditions are not considered.
     *
     * @param \stdClass $course Course record
     * @param int[] $excluded Section numbers to skip
     * @param bool $includehidden Whether to include sections with visible = 0
     * @return \section_info[]
     */
    public static function get_numbered_sections(
        \stdClass $course,
        array $excluded = [],
        bool $includehidden = false
    ): array {
        $modinfo = get_fast_modinfo($course);
        $sections = [];
        foreach ($modinfo->get_section_info_all() as $section) {
            $number = (int) $section->sectionnum;
            if (in_array($number, $excluded, true)) {
                continue;
            }
            if (!$includehidden && (int) $section->visible === 0) {
                continue;
            }
            $sections[$number] = $section;
        }
        ksort($sections);
        return $sections;
    }

    /**
     * Add the shared excluded-sections field to a form.
     *
     * @param \MoodleQuickForm $mform Form to extend
     */
    public static function add_exclude_element($mform): void {
        $mform->addElement(
            'text',
            'excludesections',
            get_string('ruleexcludesections', 'local_bbcotodobien'),
            ['size' => 30]
        );
        $mform->setType('excludesections', PARAM_TEXT);
        $mform->addHelpButton('excludesections', 'ruleexcludesections', 'local_bbcotodobien');
    }

    /**
     * Add the shared include-hidden-sections checkbox to a form.
     *
     * @param \MoodleQuickForm $mform Form to extend
     */
    public static function add_includehiddensections_element($mform): void {
        $mform->addElement(
            'advcheckbox',
            'includehiddensections',
            get_string('ruleincludehiddensections', 'local_bbcotodobien')
        );
        $mform->setDefault('includehiddensections', 0);
        $mform->addHelpButton('includehiddensections', 'ruleincludehiddensections', 'local_bbcotodobien');
    }
}
