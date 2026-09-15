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
 * Shared lookup for grade categories identified by their grade_item idnumber.
 *
 * Moodle stores the category idnumber on the associated grade_item (itemtype = category).
 *
 * @package    local_bbcotodobien
 * @copyright  2026 David Herney @ BambuCo
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
abstract class gradebook_rule extends base {
    /**
     * Evaluation granularity.
     *
     * @return string
     */
    public static function get_granularity(): string {
        return self::GRANULARITY_GRADEBOOK;
    }

    /**
     * Add the grade category idnumber field.
     *
     * @param \MoodleQuickForm $mform Form to extend
     */
    protected function add_idnumber_element($mform): void {
        $mform->addElement(
            'text',
            'idnumber',
            get_string('rulegradeidnumber', 'local_bbcotodobien'),
            ['size' => 30]
        );
        $mform->setType('idnumber', PARAM_TEXT);
        $mform->addHelpButton('idnumber', 'rulegradeidnumber', 'local_bbcotodobien');
        $mform->addRule('idnumber', get_string('required'), 'required', null, 'client');
    }

    /**
     * Fetch grade categories whose associated item has the given idnumber.
     *
     * @param int $courseid Course id
     * @param string $idnumber Category idnumber
     * @return \grade_category[]
     */
    protected function find_categories(int $courseid, string $idnumber): array {
        global $DB, $CFG;
        require_once($CFG->libdir . '/gradelib.php');

        $items = $DB->get_records('grade_items', [
            'courseid' => $courseid,
            'itemtype' => 'category',
            'idnumber' => $idnumber,
        ], 'id ASC');

        $categories = [];
        foreach ($items as $item) {
            $category = \grade_category::fetch(['id' => $item->iteminstance, 'courseid' => $courseid]);
            if ($category) {
                $categories[] = $category;
            }
        }
        return $categories;
    }

    /**
     * Extra field names stored in the detail JSON besides identifier.
     *
     * @return string[]
     */
    public function get_detail_field_names(): array {
        return ['idnumber'];
    }

    /**
     * Build a single course-level detail (gradebook rules are binary).
     *
     * @param \stdClass $course Course record
     * @param string $status Result status
     * @param string $identifier Language string identifier
     * @param array $fields Placeholder values
     * @return result
     */
    protected function binary_result(\stdClass $course, string $status, string $identifier, array $fields = []): result {
        $compliance = $status === self::STATUS_PASS || $status === self::STATUS_NA ? 100.0 : 0.0;
        return new result($compliance, $status, [
            $this->make_detail(
                self::GRANULARITY_GRADEBOOK,
                (int) $course->id,
                $course->fullname ?? '',
                $status,
                $identifier,
                $fields
            ),
        ]);
    }

    /**
     * Render formative HTML for a stored detail.
     *
     * @param detail $detail Diagnostic detail
     * @return string HTML
     */
    public function render_detail(detail $detail): string {
        $identifier = $detail->fields['identifier'] ?? '';
        $idnumber = $detail->fields['idnumber'] ?? '';
        return match ($identifier) {
            'rulemissingparams' => get_string('rulemissingparams', 'local_bbcotodobien'),
            'rulegradecategorymissing' => get_string('rulegradecategorymissing', 'local_bbcotodobien', $idnumber),
            default => parent::render_detail($detail),
        };
    }
}
