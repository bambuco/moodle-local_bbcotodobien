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
 * RF-R03: formatted HTML contains a CSS class whose text matches a literal or regex.
 *
 * @package    local_bbcotodobien
 * @copyright  2026 David Herney @ BambuCo
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class cm_html_selector extends cm_field_rule {
    /**
     * Default display name.
     *
     * @return string
     */
    public static function get_name(): string {
        return get_string('rule_cm_html_selector', 'local_bbcotodobien');
    }

    /**
     * Human-readable description.
     *
     * @return string
     */
    public static function get_description(): string {
        return get_string('rule_cm_html_selector_desc', 'local_bbcotodobien');
    }

    /**
     * Stored parameter names.
     *
     * @return string[]
     */
    public function get_config_param_names(): array {
        return ['modname', 'idnumber', 'field', 'cssclass', 'pattern', 'matchmode', 'skipfilters'];
    }

    /**
     * Extra field names stored in the detail JSON besides identifier.
     *
     * @return string[]
     */
    public function get_detail_field_names(): array {
        return array_merge(parent::get_detail_field_names(), ['cssclass']);
    }

    /**
     * Add configuration fields.
     *
     * @param \MoodleQuickForm $mform Form to extend
     */
    public function add_config_form_elements($mform): void {
        $this->add_cm_filter_elements($mform);
        $mform->addElement('text', 'cssclass', get_string('rulecssclass', 'local_bbcotodobien'), ['size' => 30]);
        $mform->setType('cssclass', PARAM_TEXT);
        $mform->addHelpButton('cssclass', 'rulecssclass', 'local_bbcotodobien');
        $mform->addRule('cssclass', get_string('required'), 'required', null, 'client');
        $this->add_pattern_elements($mform, true);
    }

    /**
     * Require a CSS class as well as the pattern.
     *
     * @param array $params Decoded rule parameters
     * @return bool
     */
    protected function has_required_params(array $params): bool {
        return parent::has_required_params($params) && $this->normalise_cssclass($params['cssclass'] ?? '') !== '';
    }

    /**
     * Check that an element with the CSS class matches the pattern.
     *
     * @param \stdClass $cm Course module locator record
     * @param string $html Formatted HTML
     * @param string $text Plain text
     * @param array $params Decoded rule parameters
     * @return detail
     */
    protected function check_field(\stdClass $cm, string $html, string $text, array $params): detail {
        $name = format_string($cm->name ?? (string) $cm->id);
        $cssclass = $this->normalise_cssclass($params['cssclass'] ?? '');
        $pattern = (string) $params['pattern'];
        $matchmode = ($params['matchmode'] ?? self::MATCH_LITERAL) === self::MATCH_REGEX
            ? self::MATCH_REGEX
            : self::MATCH_LITERAL;

        $nodes = $this->query_class_nodes($html, $cssclass);
        if ($nodes === null) {
            return $this->make_detail(
                self::GRANULARITY_CM,
                (int) $cm->id,
                $name,
                self::STATUS_ERROR,
                'rulehtmlinvalid'
            );
        }

        if ($nodes === []) {
            return $this->make_detail(
                self::GRANULARITY_CM,
                (int) $cm->id,
                $name,
                self::STATUS_FAIL,
                'rulehtmlselector_fail_noclass',
                ['cssclass' => $cssclass]
            );
        }

        foreach ($nodes as $nodetext) {
            $matched = $this->text_matches($nodetext, $pattern, $matchmode);
            if ($matched === null) {
                return $this->make_detail(
                    self::GRANULARITY_CM,
                    (int) $cm->id,
                    $name,
                    self::STATUS_ERROR,
                    'ruleinvalidregex'
                );
            }
            if ($matched) {
                return $this->make_detail(
                    self::GRANULARITY_CM,
                    (int) $cm->id,
                    $name,
                    self::STATUS_PASS,
                    'rulehtmlselector_pass',
                    ['cssclass' => $cssclass]
                );
            }
        }

        return $this->make_detail(
            self::GRANULARITY_CM,
            (int) $cm->id,
            $name,
            self::STATUS_FAIL,
            'rulehtmlselector_fail_notext',
            ['cssclass' => $cssclass]
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
        $cssclass = $detail->fields['cssclass'] ?? '';
        return match ($identifier) {
            'rulehtmlinvalid' => get_string('rulehtmlinvalid', 'local_bbcotodobien'),
            'rulehtmlselector_fail_noclass' => get_string('rulehtmlselector_fail_noclass', 'local_bbcotodobien', $cssclass),
            'rulehtmlselector_fail_notext' => get_string('rulehtmlselector_fail_notext', 'local_bbcotodobien', $cssclass),
            'rulehtmlselector_pass' => get_string('rulehtmlselector_pass', 'local_bbcotodobien', $cssclass),
            'ruleinvalidregex' => get_string('ruleinvalidregex', 'local_bbcotodobien'),
            default => parent::render_detail($detail),
        };
    }

    /**
     * Strip a leading dot from a CSS class name.
     *
     * @param string $cssclass Raw class or selector
     * @return string
     */
    protected function normalise_cssclass(string $cssclass): string {
        $cssclass = trim($cssclass);
        $cssclass = ltrim($cssclass, '.');
        return str_replace(["'", '"'], '', $cssclass);
    }

    /**
     * Return text content of nodes matching the class, or null when HTML cannot be parsed.
     *
     * @param string $html Formatted HTML
     * @param string $cssclass Class name without a leading dot
     * @return string[]|null
     */
    protected function query_class_nodes(string $html, string $cssclass): ?array {
        if (strpos($html, "\0") !== false) {
            return null;
        }
        if (trim($html) === '') {
            return [];
        }

        $previous = libxml_use_internal_errors(true);
        libxml_clear_errors();
        $dom = new \DOMDocument();
        $wrapped = '<div id="local-bbcotodobien-root">' . $html . '</div>';
        try {
            $loaded = $dom->loadHTML('<?xml encoding="utf-8">' . $wrapped, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
        } catch (\Throwable) {
            libxml_clear_errors();
            libxml_use_internal_errors($previous);
            return null;
        }
        $errors = libxml_get_errors();
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        if ($loaded === false) {
            return null;
        }
        foreach ($errors as $error) {
            if ((int) $error->level === LIBXML_ERR_FATAL) {
                return null;
            }
        }

        $xpath = new \DOMXPath($dom);
        $query = "//*[contains(concat(' ', normalize-space(@class), ' '), ' " . $cssclass . " ')]";
        $nodelist = $xpath->query($query);
        if ($nodelist === false) {
            return null;
        }

        $texts = [];
        foreach ($nodelist as $node) {
            $texts[] = $node->textContent ?? '';
        }
        return $texts;
    }
}
