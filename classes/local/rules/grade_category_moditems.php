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
 * RF-R07: a grade category with the given idnumber contains a module grade item.
 *
 * @package    local_bbcotodobien
 * @copyright  2026 David Herney @ BambuCo
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class grade_category_moditems extends gradebook_rule {
    /**
     * Default display name.
     *
     * @return string
     */
    public static function get_name(): string {
        return get_string('rule_grade_category_moditems', 'local_bbcotodobien');
    }

    /**
     * Human-readable description.
     *
     * @return string
     */
    public static function get_description(): string {
        return get_string('rule_grade_category_moditems_desc', 'local_bbcotodobien');
    }

    /**
     * Stored parameter names.
     *
     * @return string[]
     */
    public function get_config_param_names(): array {
        return ['idnumber'];
    }

    /**
     * Add configuration fields.
     *
     * @param \MoodleQuickForm $mform Form to extend
     */
    public function add_config_form_elements($mform): void {
        $this->add_idnumber_element($mform);
    }

    /**
     * Evaluate the grade category.
     *
     * @param \stdClass $course Course record
     * @param array $params Decoded rule parameters
     * @return result
     */
    public function evaluate(\stdClass $course, array $params): result {
        global $DB;

        $idnumber = trim((string) ($params['idnumber'] ?? ''));
        if ($idnumber === '') {
            return $this->binary_result($course, self::STATUS_ERROR, 'rulemissingparams');
        }

        $categories = $this->find_categories((int) $course->id, $idnumber);
        if (!$categories) {
            return $this->binary_result(
                $course,
                self::STATUS_FAIL,
                'rulegradecategorymissing',
                ['idnumber' => $idnumber]
            );
        }

        foreach ($categories as $category) {
            if (
                $DB->record_exists(
                    'grade_items',
                    [
                        'categoryid' => $category->id,
                        'itemtype' => 'mod',
                    ]
                )
            ) {
                return $this->binary_result(
                    $course,
                    self::STATUS_PASS,
                    'rulegradecategorymoditems_pass',
                    ['idnumber' => $idnumber]
                );
            }
        }

        return $this->binary_result(
            $course,
            self::STATUS_FAIL,
            'rulegradecategorymoditems_fail',
            ['idnumber' => $idnumber]
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
        $idnumber = $detail->fields['idnumber'] ?? '';
        return match ($identifier) {
            'rulegradecategorymoditems_pass' => get_string(
                'rulegradecategorymoditems_pass',
                'local_bbcotodobien',
                $idnumber
            ),
            'rulegradecategorymoditems_fail' => get_string(
                'rulegradecategorymoditems_fail',
                'local_bbcotodobien',
                $idnumber
            ),
            default => parent::render_detail($detail),
        };
    }
}
