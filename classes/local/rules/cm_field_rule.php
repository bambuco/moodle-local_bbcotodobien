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
 * Shared behaviour for CM rules that inspect a module table field.
 *
 * @package    local_bbcotodobien
 * @copyright  2026 David Herney @ BambuCo
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
abstract class cm_field_rule extends base {
    /** @var string Match the pattern as literal text */
    public const MATCH_LITERAL = 'literal';

    /** @var string Match the pattern as a regular expression */
    public const MATCH_REGEX = 'regex';

    /**
     * Evaluation granularity.
     *
     * @return string
     */
    public static function get_granularity(): string {
        return self::GRANULARITY_CM;
    }

    /**
     * Extra field names stored in the detail JSON besides identifier.
     *
     * @return string[]
     */
    public function get_detail_field_names(): array {
        return ['modname', 'idnumber', 'field'];
    }

    /**
     * Render formative HTML for a stored detail.
     *
     * @param detail $detail Diagnostic detail
     * @return string HTML
     */
    public function render_detail(detail $detail): string {
        $identifier = $detail->fields['identifier'] ?? '';
        $fields = $detail->fields;
        return match ($identifier) {
            'rulemissingparams' => get_string('rulemissingparams', 'local_bbcotodobien'),
            'ruleinvalidmodule' => get_string('ruleinvalidmodule', 'local_bbcotodobien', $fields['modname'] ?? ''),
            'rulenomatches' => get_string('rulenomatches', 'local_bbcotodobien', (object) [
                'modname' => $fields['modname'] ?? '',
                'idnumber' => $fields['idnumber'] ?? '',
            ]),
            'rulemissinginstance' => get_string('rulemissinginstance', 'local_bbcotodobien'),
            'rulemissingfield' => get_string('rulemissingfield', 'local_bbcotodobien', (object) [
                'field' => $fields['field'] ?? '',
                'modname' => $fields['modname'] ?? '',
            ]),
            default => parent::render_detail($detail),
        };
    }

    /**
     * Evaluate each matching course module.
     *
     * @param \stdClass $course Course record
     * @param array $params Decoded rule parameters
     * @return result
     */
    public function evaluate(\stdClass $course, array $params): result {
        $modname = clean_param(trim((string) ($params['modname'] ?? '')), PARAM_PLUGIN);
        $idnumber = trim((string) ($params['idnumber'] ?? ''));
        $field = trim((string) ($params['field'] ?? ''));

        if ($modname === '' || $idnumber === '' || $field === '' || !$this->has_required_params($params)) {
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

        if (!cm_locator::table_exists($modname)) {
            return $this->result_from_details([
                $this->make_detail(
                    self::GRANULARITY_COURSE,
                    (int) $course->id,
                    $course->fullname ?? '',
                    self::STATUS_ERROR,
                    'ruleinvalidmodule',
                    ['modname' => $modname]
                ),
            ]);
        }

        $cms = cm_locator::find((int) $course->id, $modname, $idnumber);
        if (!$cms) {
            return $this->result_from_details([
                $this->make_detail(
                    self::GRANULARITY_COURSE,
                    (int) $course->id,
                    $course->fullname ?? '',
                    self::STATUS_FAIL,
                    'rulenomatches',
                    [
                        'modname' => $modname,
                        'idnumber' => $idnumber,
                    ]
                ),
            ]);
        }

        $details = [];
        foreach ($cms as $cm) {
            $details[] = $this->evaluate_cm($cm, $modname, $field, $params);
        }
        return $this->result_from_details($details);
    }

    /**
     * Whether extra required parameters are present.
     *
     * @param array $params Decoded rule parameters
     * @return bool
     */
    protected function has_required_params(array $params): bool {
        return trim((string) ($params['pattern'] ?? '')) !== '';
    }

    /**
     * Evaluate one matching course module.
     *
     * @param \stdClass $cm Course module locator record
     * @param string $modname Module name
     * @param string $field Instance table column
     * @param array $params Decoded rule parameters
     * @return detail
     */
    protected function evaluate_cm(\stdClass $cm, string $modname, string $field, array $params): detail {
        $name = format_string($cm->name ?? (string) $cm->id);
        if (empty($cm->instanceloaded) || !$cm->instancerecord) {
            return $this->make_detail(
                self::GRANULARITY_CM,
                (int) $cm->id,
                $name,
                self::STATUS_ERROR,
                'rulemissinginstance'
            );
        }

        if (!cm_locator::field_exists($modname, $field)) {
            return $this->make_detail(
                self::GRANULARITY_CM,
                (int) $cm->id,
                $name,
                self::STATUS_ERROR,
                'rulemissingfield',
                [
                    'field' => $field,
                    'modname' => $modname,
                ]
            );
        }

        [$html, $text] = $this->format_field($cm, $cm->instancerecord, $field, $modname);
        return $this->check_field($cm, $html, $text, $params);
    }

    /**
     * Format a module field through Moodle filters and return HTML plus plain text.
     *
     * @param \stdClass $cm Course module record
     * @param \stdClass $instance Module instance
     * @param string $field Column name
     * @param string $modname Module name
     * @return array{0: string, 1: string} Formatted HTML and plain text
     */
    protected function format_field(\stdClass $cm, \stdClass $instance, string $field, string $modname): array {
        $raw = (string) ($instance->$field ?? '');
        $formatfield = $field . 'format';
        $format = isset($instance->$formatfield) ? (int) $instance->$formatfield : FORMAT_HTML;
        $context = \context_module::instance((int) $cm->id);
        $filearea = $field === 'intro' ? 'intro' : $field;
        $rewritten = file_rewrite_pluginfile_urls(
            $raw,
            'pluginfile.php',
            $context->id,
            'mod_' . $modname,
            $filearea,
            0
        );
        $html = format_text($rewritten, $format, [
            'context' => $context,
            'filter' => true,
        ]);
        $text = html_to_text($html, 75, false);
        return [$html, $text];
    }

    /**
     * Check the formatted field of one course module.
     *
     * @param \stdClass $cm Course module locator record
     * @param string $html Formatted HTML
     * @param string $text Plain text
     * @param array $params Decoded rule parameters
     * @return detail
     */
    abstract protected function check_field(\stdClass $cm, string $html, string $text, array $params): detail;

    /**
     * Whether plain text matches the configured pattern.
     *
     * @param string $text Plain text
     * @param string $pattern Pattern or literal
     * @param string $matchmode One of MATCH_*
     * @return bool|null True/false, or null when the regular expression is invalid
     */
    protected function text_matches(string $text, string $pattern, string $matchmode): ?bool {
        if ($matchmode === self::MATCH_REGEX) {
            $delimited = '/' . str_replace('/', '\/', $pattern) . '/u';
            $result = @preg_match($delimited, $text);
            if ($result === false) {
                return null;
            }
            return $result === 1;
        }
        return mb_stripos($text, $pattern, 0, 'UTF-8') !== false;
    }

    /**
     * Add shared module / idnumber / field inputs.
     *
     * @param \MoodleQuickForm $mform Form to extend
     */
    protected function add_cm_filter_elements($mform): void {
        $modules = ['' => get_string('choosedots')] + get_module_types_names();
        asort($modules, SORT_LOCALE_STRING);
        $mform->addElement('select', 'modname', get_string('rulemodname', 'local_bbcotodobien'), $modules);
        $mform->addHelpButton('modname', 'rulemodname', 'local_bbcotodobien');
        $mform->addRule('modname', get_string('required'), 'required', null, 'client');

        $mform->addElement('text', 'idnumber', get_string('ruleidnumber', 'local_bbcotodobien'), ['size' => 30]);
        $mform->setType('idnumber', PARAM_TEXT);
        $mform->addHelpButton('idnumber', 'ruleidnumber', 'local_bbcotodobien');
        $mform->addRule('idnumber', get_string('required'), 'required', null, 'client');

        $mform->addElement('text', 'field', get_string('rulefield', 'local_bbcotodobien'), ['size' => 30]);
        $mform->setType('field', PARAM_ALPHANUMEXT);
        $mform->addHelpButton('field', 'rulefield', 'local_bbcotodobien');
        $mform->addRule('field', get_string('required'), 'required', null, 'client');
    }

    /**
     * Add pattern and optional match-mode inputs.
     *
     * @param \MoodleQuickForm $mform Form to extend
     * @param bool $withmatchmode Whether to include regex/literal
     */
    protected function add_pattern_elements($mform, bool $withmatchmode = true): void {
        $mform->addElement('text', 'pattern', get_string('rulepattern', 'local_bbcotodobien'), ['size' => 60]);
        $mform->setType('pattern', PARAM_RAW);
        $mform->addHelpButton('pattern', 'rulepattern', 'local_bbcotodobien');
        $mform->addRule('pattern', get_string('required'), 'required', null, 'client');

        if ($withmatchmode) {
            $mform->addElement('select', 'matchmode', get_string('rulematchmode', 'local_bbcotodobien'), [
                self::MATCH_LITERAL => get_string('rulematchmodeliteral', 'local_bbcotodobien'),
                self::MATCH_REGEX => get_string('rulematchmoderegex', 'local_bbcotodobien'),
            ]);
            $mform->setDefault('matchmode', self::MATCH_LITERAL);
            $mform->addHelpButton('matchmode', 'rulematchmode', 'local_bbcotodobien');
        }
    }
}
