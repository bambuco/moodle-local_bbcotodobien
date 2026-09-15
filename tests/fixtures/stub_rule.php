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

namespace local_bbcotodobien\tests\fixtures;

use local_bbcotodobien\local\detail;
use local_bbcotodobien\local\result;
use local_bbcotodobien\local\rules\base;

/**
 * Test-only rule used to exercise the factory and aggregator.
 *
 * @package    local_bbcotodobien
 * @copyright  2026 David Herney @ BambuCo
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class stub_rule extends base {
    /**
     * Return the default name.
     *
     * @return string
     */
    public static function get_name(): string {
        return get_string('stubrule', 'local_bbcotodobien');
    }

    /**
     * Return the granularity.
     *
     * @return string
     */
    public static function get_granularity(): string {
        return self::GRANULARITY_COURSE;
    }

    /**
     * Add a dummy configuration field.
     *
     * @param \MoodleQuickForm $mform Form to extend
     */
    public function add_config_form_elements($mform): void {
        $mform->addElement('text', 'expected', get_string('stubruleexpected', 'local_bbcotodobien'));
        $mform->setType('expected', PARAM_TEXT);
    }

    /**
     * Return the stub parameter field names.
     *
     * @return string[]
     */
    public function get_config_param_names(): array {
        return ['expected'];
    }

    /**
     * Evaluate using either a binary pass flag or a list of detail items.
     *
     * @param \stdClass $course Course record
     * @param array $params Decoded rule parameters
     * @return result
     */
    public function evaluate(\stdClass $course, array $params): result {
        if (!empty($params['items']) && is_array($params['items'])) {
            $details = [];
            foreach ($params['items'] as $item) {
                $status = (string) $item['status'];
                $details[] = new detail(
                    $item['targettype'] ?? self::GRANULARITY_SECTION,
                    (int) $item['targetid'],
                    (string) $item['targetname'],
                    $status,
                    [
                        'identifier' => $item['identifier'] ?? ($status === self::STATUS_PASS
                            ? 'stubrulepassed'
                            : 'stubrulefailed'),
                    ]
                );
            }
            return $this->result_from_details($details);
        }

        $pass = !empty($params['pass']);
        $status = $pass ? self::STATUS_PASS : self::STATUS_FAIL;
        $detail = $this->make_detail(
            self::GRANULARITY_COURSE,
            (int) $course->id,
            $course->fullname ?? '',
            $status,
            $pass ? 'stubrulepassed' : 'stubrulefailed'
        );
        return $this->result_from_details([$detail]);
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
            'stubrulepassed' => get_string('stubrulepassed', 'local_bbcotodobien'),
            'stubrulefailed' => get_string('stubrulefailed', 'local_bbcotodobien'),
            default => parent::render_detail($detail),
        };
    }
}
