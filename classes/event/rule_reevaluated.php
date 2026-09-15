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
 * The rule re-evaluated event.
 *
 * @package    local_bbcotodobien
 * @copyright  2026 David Herney @ BambuCo
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_bbcotodobien\event;

/**
 * Event triggered when a single audit rule is re-evaluated on a course.
 *
 * @package    local_bbcotodobien
 * @copyright  2026 David Herney @ BambuCo
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 *
 * @property-read array $other {
 *      Extra information about the event.
 *
 *      - int audittypeid: id of the audit type.
 *      - int ruleconfigid: id of the rule configuration.
 *      - float compliance: resulting compliance percentage.
 * }
 */
class rule_reevaluated extends \core\event\base {
    /**
     * Initialise required event data properties.
     */
    protected function init(): void {
        $this->data['objecttable'] = 'local_bbcotodobien_rule_result';
        $this->data['crud'] = 'c';
        $this->data['edulevel'] = self::LEVEL_TEACHING;
    }

    /**
     * Returns localised event name.
     *
     * @return string
     */
    public static function get_name(): string {
        return get_string('eventrulereevaluated', 'local_bbcotodobien');
    }

    /**
     * Returns non-localised description of what happened.
     *
     * @return string
     */
    public function get_description(): string {
        $audittypeid = $this->other['audittypeid'] ?? 0;
        $ruleconfigid = $this->other['ruleconfigid'] ?? 0;
        $compliance = $this->other['compliance'] ?? 0;
        return "The user with id '$this->userid' re-evaluated rule '$ruleconfigid' of audit type '$audittypeid' " .
            "in the course with id '$this->courseid' with compliance '$compliance'.";
    }

    /**
     * Returns relevant URL.
     *
     * @return \moodle_url
     */
    public function get_url(): \moodle_url {
        return new \moodle_url('/local/bbcotodobien/view.php', ['id' => $this->courseid]);
    }

    /**
     * Custom validation.
     *
     * @throws \coding_exception
     */
    protected function validate_data(): void {
        parent::validate_data();

        if (!isset($this->objectid)) {
            throw new \coding_exception('The \'objectid\' value must be set.');
        }
        if (!isset($this->other['audittypeid'])) {
            throw new \coding_exception('The \'audittypeid\' value must be set in other.');
        }
        if (!isset($this->other['ruleconfigid'])) {
            throw new \coding_exception('The \'ruleconfigid\' value must be set in other.');
        }
        if (!isset($this->other['compliance'])) {
            throw new \coding_exception('The \'compliance\' value must be set in other.');
        }
    }

    /**
     * Return the mapping of objectid for backup/restore.
     *
     * @return array
     */
    public static function get_objectid_mapping(): array {
        return ['db' => 'local_bbcotodobien_rule_result', 'restore' => \core\event\base::NOT_MAPPED];
    }

    /**
     * Return the mapping of other data for backup/restore.
     *
     * @return array
     */
    public static function get_other_mapping(): array {
        return [
            'audittypeid' => ['db' => 'local_bbcotodobien_audit_type', 'restore' => \core\event\base::NOT_MAPPED],
            'ruleconfigid' => ['db' => 'local_bbcotodobien_rule_config', 'restore' => \core\event\base::NOT_MAPPED],
        ];
    }
}
