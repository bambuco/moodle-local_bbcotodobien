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

/**
 * RF-R02: a matching activity field does not contain a literal or regular expression.
 *
 * @package    local_bbcotodobien
 * @copyright  2026 David Herney @ BambuCo
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class cm_content_excludes extends cm_field_rule {
    /**
     * Default display name.
     *
     * @return string
     */
    public static function get_name(): string {
        return get_string('rule_cm_content_excludes', 'local_bbcotodobien');
    }

    /**
     * Human-readable description.
     *
     * @return string
     */
    public static function get_description(): string {
        return get_string('rule_cm_content_excludes_desc', 'local_bbcotodobien');
    }

    /**
     * Stored parameter names.
     *
     * @return string[]
     */
    public function get_config_param_names(): array {
        return ['modname', 'idnumber', 'field', 'pattern', 'matchmode', 'skipfilters'];
    }

    /**
     * Add configuration fields.
     *
     * @param \MoodleQuickForm $mform Form to extend
     */
    public function add_config_form_elements($mform): void {
        $this->add_cm_filter_elements($mform);
        $this->add_pattern_elements($mform, true);
    }

    /**
     * Check that the plain text does not contain the pattern.
     *
     * @param \stdClass $cm Course module locator record
     * @param string $html Formatted HTML
     * @param string $text Plain text
     * @param array $params Decoded rule parameters
     * @return detail
     */
    protected function check_field(\stdClass $cm, string $html, string $text, array $params): detail {
        $pattern = (string) $params['pattern'];
        $matchmode = ($params['matchmode'] ?? self::MATCH_LITERAL) === self::MATCH_REGEX
            ? self::MATCH_REGEX
            : self::MATCH_LITERAL;
        $matched = $this->text_matches($text, $pattern, $matchmode);
        $name = format_string($cm->name ?? (string) $cm->id);

        if ($matched === null) {
            return $this->make_detail(
                self::GRANULARITY_CM,
                (int) $cm->id,
                $name,
                self::STATUS_ERROR,
                'ruleinvalidregex'
            );
        }

        return $this->make_detail(
            self::GRANULARITY_CM,
            (int) $cm->id,
            $name,
            $matched ? self::STATUS_FAIL : self::STATUS_PASS,
            $matched ? 'rulecontentexcludes_fail' : 'rulecontentexcludes_pass'
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
            'ruleinvalidregex' => get_string('ruleinvalidregex', 'local_bbcotodobien'),
            'rulecontentexcludes_pass' => get_string('rulecontentexcludes_pass', 'local_bbcotodobien'),
            'rulecontentexcludes_fail' => get_string('rulecontentexcludes_fail', 'local_bbcotodobien'),
            default => parent::render_detail($detail),
        };
    }
}
