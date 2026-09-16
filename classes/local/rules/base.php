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
 * Abstract base class for audit validation rules.
 *
 * @package    local_bbcotodobien
 * @copyright  2026 David Herney @ BambuCo
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
abstract class base {
    /** @var string Course-level evaluation */
    public const GRANULARITY_COURSE = 'course';

    /** @var string Section-level evaluation */
    public const GRANULARITY_SECTION = 'section';

    /** @var string Course-module-level evaluation */
    public const GRANULARITY_CM = 'cm';

    /** @var string Gradebook-level evaluation */
    public const GRANULARITY_GRADEBOOK = 'gradebook';

    /** @var string Target passed the check */
    public const STATUS_PASS = result::STATUS_PASS;

    /** @var string Target failed the check */
    public const STATUS_FAIL = result::STATUS_FAIL;

    /** @var string Technical error while evaluating */
    public const STATUS_ERROR = result::STATUS_ERROR;

    /** @var string Not applicable */
    public const STATUS_NA = result::STATUS_NA;

    /**
     * Machine identifier for the rule class.
     *
     * @return string
     */
    public static function get_identifier(): string {
        $parts = explode('\\', static::class);
        return end($parts);
    }

    /**
     * Default display name of the rule.
     *
     * @return string
     */
    abstract public static function get_name(): string;

    /**
     * Human-readable description of the rule.
     *
     * @return string
     */
    public static function get_description(): string {
        return '';
    }

    /**
     * Names of the rule-specific form fields stored in params.
     *
     * @return string[]
     */
    public function get_config_param_names(): array {
        return [];
    }

    /**
     * Copy submitted form fields into a params array.
     *
     * @param \stdClass $data Submitted form data
     * @return array
     */
    public function extract_params(\stdClass $data): array {
        $params = [];
        foreach ($this->get_config_param_names() as $name) {
            if (property_exists($data, $name)) {
                $params[$name] = $data->$name;
            }
        }
        return $params;
    }

    /**
     * Evaluation granularity.
     *
     * @return string One of the GRANULARITY_* constants
     */
    abstract public static function get_granularity(): string;

    /**
     * Add rule-specific parameter fields to a configuration form.
     *
     * @param \MoodleQuickForm $mform Form to extend
     */
    abstract public function add_config_form_elements($mform): void;

    /**
     * Add the shared "comply without applying filters" checkbox.
     *
     * @param \MoodleQuickForm $mform Form to extend
     */
    protected function add_skipfilters_element($mform): void {
        $mform->addElement('advcheckbox', 'skipfilters', get_string('ruleskipfilters', 'local_bbcotodobien'));
        $mform->setDefault('skipfilters', 0);
        $mform->addHelpButton('skipfilters', 'ruleskipfilters', 'local_bbcotodobien');
    }

    /**
     * Evaluate the rule against a course.
     *
     * @param \stdClass $course Course record
     * @param array $params Decoded rule parameters
     * @return result
     */
    abstract public function evaluate(\stdClass $course, array $params): result;

    /**
     * Extra field names stored in the detail JSON besides identifier.
     *
     * @return string[]
     */
    public function get_detail_field_names(): array {
        return [];
    }

    /**
     * Build a detail with the language string identifier and allowed placeholders.
     *
     * @param string $targettype Target type
     * @param int $targetid Target id
     * @param string $targetname Human-readable target name
     * @param string $status One of pass, fail, error, na
     * @param string $identifier Language string identifier
     * @param array $fields Placeholder values
     * @return detail
     */
    protected function make_detail(
        string $targettype,
        int $targetid,
        string $targetname,
        string $status,
        string $identifier,
        array $fields = []
    ): detail {
        $payload = ['identifier' => $identifier];
        foreach ($this->get_detail_field_names() as $name) {
            if (array_key_exists($name, $fields)) {
                $payload[$name] = $fields[$name];
            }
        }
        return new detail($targettype, $targetid, $targetname, $status, $payload);
    }

    /**
     * Translate a stored detail payload into the current language.
     *
     * @param detail $detail Diagnostic detail
     * @return string
     */
    public static function format_detail(detail $detail): string {
        $identifier = $detail->fields['identifier'] ?? '';
        if ($identifier === '') {
            return '';
        }
        $placeholders = $detail->fields;
        unset($placeholders['identifier']);
        if ($placeholders === []) {
            return get_string($identifier, 'local_bbcotodobien');
        }
        if (count($placeholders) === 1) {
            return get_string($identifier, 'local_bbcotodobien', reset($placeholders));
        }
        return get_string($identifier, 'local_bbcotodobien', (object) $placeholders);
    }

    /**
     * Render formative HTML for a stored detail.
     *
     * @param detail $detail Diagnostic detail
     * @return string HTML
     */
    public function render_detail(detail $detail): string {
        return self::format_detail($detail);
    }

    /**
     * Intro text for a grouped list of failing section or activity targets.
     *
     * Prefers a rule-specific "{identifier}_list" string; otherwise uses a
     * generic sections or activities list string.
     *
     * @param detail $detail Sample failing detail from the group
     * @return string
     */
    public function render_failing_list_intro(detail $detail): string {
        $identifier = $detail->fields['identifier'] ?? '';
        $listkey = $identifier !== '' ? $identifier . '_list' : '';
        if ($listkey !== '' && get_string_manager()->string_exists($listkey, 'local_bbcotodobien')) {
            $placeholders = $detail->fields;
            unset($placeholders['identifier']);
            if ($placeholders === []) {
                return get_string($listkey, 'local_bbcotodobien');
            }
            if (count($placeholders) === 1) {
                return get_string($listkey, 'local_bbcotodobien', reset($placeholders));
            }
            return get_string($listkey, 'local_bbcotodobien', (object) $placeholders);
        }

        if ($detail->targettype === self::GRANULARITY_SECTION) {
            return get_string('rulefailingsections_list', 'local_bbcotodobien');
        }
        return get_string('rulefailingactivities_list', 'local_bbcotodobien');
    }

    /**
     * Build a result from per-target details using the shared formula.
     *
     * @param detail[] $details Diagnostic details
     * @return result
     */
    protected function result_from_details(array $details): result {
        return result::from_details($details);
    }
}
