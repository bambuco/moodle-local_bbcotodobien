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
 * RF-R05: a section summary contains a label followed by a parseable date.
 *
 * @package    local_bbcotodobien
 * @copyright  2026 David Herney @ BambuCo
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class section_date_label extends base {
    /**
     * Default display name.
     *
     * @return string
     */
    public static function get_name(): string {
        return get_string('rule_section_date_label', 'local_bbcotodobien');
    }

    /**
     * Human-readable description.
     *
     * @return string
     */
    public static function get_description(): string {
        return get_string('rule_section_date_label_desc', 'local_bbcotodobien');
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
        return ['datelabel', 'excludesections', 'skipfilters'];
    }

    /**
     * Extra field names stored in the detail JSON besides identifier.
     *
     * @return string[]
     */
    public function get_detail_field_names(): array {
        return ['label'];
    }

    /**
     * Add configuration fields.
     *
     * @param \MoodleQuickForm $mform Form to extend
     */
    public function add_config_form_elements($mform): void {
        $mform->addElement('text', 'datelabel', get_string('ruledatelabel', 'local_bbcotodobien'), ['size' => 40]);
        $mform->setType('datelabel', PARAM_TEXT);
        $mform->addHelpButton('datelabel', 'ruledatelabel', 'local_bbcotodobien');
        $mform->addRule('datelabel', get_string('required'), 'required', null, 'client');
        section_helper::add_exclude_element($mform);
        $this->add_skipfilters_element($mform);
    }

    /**
     * Evaluate numbered sections.
     *
     * @param \stdClass $course Course record
     * @param array $params Decoded rule parameters
     * @return result
     */
    public function evaluate(\stdClass $course, array $params): result {
        global $CFG;
        require_once($CFG->libdir . '/filelib.php');

        $label = trim((string) ($params['datelabel'] ?? ''));
        if ($label === '') {
            return $this->result_from_details([
                $this->make_detail(
                    self::GRANULARITY_COURSE,
                    (int) $course->id,
                    $course->fullname ?? '',
                    self::STATUS_ERROR,
                    'rulemissingparams'
                ),
            ]);
        }

        $excluded = section_helper::parse_excluded_sections($params['excludesections'] ?? '');
        $sections = section_helper::get_numbered_sections($course, $excluded);
        if (!$sections) {
            return $this->result_from_details([]);
        }

        $context = \context_course::instance((int) $course->id);
        $applyfilters = empty($params['skipfilters']);
        $details = [];
        foreach ($sections as $section) {
            $details[] = $this->evaluate_section($course, $section, $label, $context, $applyfilters);
        }
        return $this->result_from_details($details);
    }

    /**
     * Evaluate one section summary.
     *
     * @param \stdClass $course Course record
     * @param \section_info $section Section info
     * @param string $label Literal label
     * @param \context_course $context Course context
     * @param bool $applyfilters Whether to apply Moodle text filters
     * @return detail
     */
    protected function evaluate_section(
        \stdClass $course,
        \section_info $section,
        string $label,
        \context_course $context,
        bool $applyfilters = true
    ): detail {
        $name = get_section_name($course, $section);
        $summary = \file_rewrite_pluginfile_urls(
            (string) $section->summary,
            'pluginfile.php',
            $context->id,
            'course',
            'section',
            $section->id
        );
        $html = format_text($summary, (int) $section->summaryformat, [
            'context' => $context,
            'filter' => $applyfilters,
        ]);
        $text = html_to_text($html, 75, false);
        $pos = mb_stripos($text, $label, 0, 'UTF-8');
        if ($pos === false) {
            return $this->make_detail(
                self::GRANULARITY_SECTION,
                (int) $section->id,
                $name,
                self::STATUS_FAIL,
                'rulesectiondatelabel_fail_nolabel',
                ['label' => $label]
            );
        }

        $after = ltrim(mb_substr($text, $pos + mb_strlen($label, 'UTF-8'), null, 'UTF-8'));
        $line = trim(explode("\n", $after)[0]);
        if (!$this->line_starts_with_date($line)) {
            return $this->make_detail(
                self::GRANULARITY_SECTION,
                (int) $section->id,
                $name,
                self::STATUS_FAIL,
                'rulesectiondatelabel_fail_nodate',
                ['label' => $label]
            );
        }

        return $this->make_detail(
            self::GRANULARITY_SECTION,
            (int) $section->id,
            $name,
            self::STATUS_PASS,
            'rulesectiondatelabel_pass',
            ['label' => $label]
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
        $label = $detail->fields['label'] ?? '';
        return match ($identifier) {
            'rulemissingparams' => get_string('rulemissingparams', 'local_bbcotodobien'),
            'rulesectiondatelabel_fail_nolabel' => get_string(
                'rulesectiondatelabel_fail_nolabel',
                'local_bbcotodobien',
                $label
            ),
            'rulesectiondatelabel_fail_nodate' => get_string(
                'rulesectiondatelabel_fail_nodate',
                'local_bbcotodobien',
                $label
            ),
            'rulesectiondatelabel_pass' => get_string('rulesectiondatelabel_pass', 'local_bbcotodobien', $label),
            default => parent::render_detail($detail),
        };
    }

    /**
     * Whether the text starts with a strtotime()-parseable date that includes a digit.
     *
     * @param string $line Remaining summary text
     * @return bool
     */
    protected function line_starts_with_date(string $line): bool {
        if ($line === '') {
            return false;
        }
        $parts = preg_split('/\s+/', $line) ?: [];
        $candidate = '';
        foreach ($parts as $part) {
            $candidate = trim($candidate . ' ' . $part);
            $try = rtrim($candidate, '.,;:');
            if ($try !== '' && preg_match('/\d/', $try) && strtotime($try) !== false) {
                return true;
            }
        }
        return false;
    }
}
