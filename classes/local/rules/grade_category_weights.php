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
 * RF-R08: weighted-mean grade category children sum to 100% ± tolerance.
 *
 * @package    local_bbcotodobien
 * @copyright  2026 David Herney @ BambuCo
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class grade_category_weights extends gradebook_rule {
    /**
     * Default display name.
     *
     * @return string
     */
    public static function get_name(): string {
        return get_string('rule_grade_category_weights', 'local_bbcotodobien');
    }

    /**
     * Human-readable description.
     *
     * @return string
     */
    public static function get_description(): string {
        return get_string('rule_grade_category_weights_desc', 'local_bbcotodobien');
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
     * Extra field names stored in the detail JSON besides identifier.
     *
     * @return string[]
     */
    public function get_detail_field_names(): array {
        return array_merge(parent::get_detail_field_names(), ['sum']);
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
     * Evaluate weighted aggregation of the named category.
     *
     * @param \stdClass $course Course record
     * @param array $params Decoded rule parameters
     * @return result
     */
    public function evaluate(\stdClass $course, array $params): result {
        global $CFG;
        require_once($CFG->libdir . '/gradelib.php');

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

        $weighted = [];
        foreach ($categories as $category) {
            if ((int) $category->aggregation === GRADE_AGGREGATE_WEIGHTED_MEAN) {
                $weighted[] = $category;
            }
        }
        if (!$weighted) {
            return $this->binary_result(
                $course,
                self::STATUS_NA,
                'rulegradeweights_na',
                ['idnumber' => $idnumber]
            );
        }

        $tolerance = (float) (get_config('local_bbcotodobien', 'weighttolerance') ?: 0.01);
        foreach ($weighted as $category) {
            $sum = $this->sum_child_weights($category);
            if (abs($sum - 100.0) > $tolerance) {
                return $this->binary_result(
                    $course,
                    self::STATUS_FAIL,
                    'rulegradeweights_fail',
                    [
                        'idnumber' => $idnumber,
                        'sum' => format_float($sum, 2),
                    ]
                );
            }
        }

        return $this->binary_result(
            $course,
            self::STATUS_PASS,
            'rulegradeweights_pass',
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
            'rulegradeweights_na' => get_string('rulegradeweights_na', 'local_bbcotodobien', $idnumber),
            'rulegradeweights_fail' => get_string('rulegradeweights_fail', 'local_bbcotodobien', (object) [
                'idnumber' => $idnumber,
                'sum' => $detail->fields['sum'] ?? '',
            ]),
            'rulegradeweights_pass' => get_string('rulegradeweights_pass', 'local_bbcotodobien', $idnumber),
            default => parent::render_detail($detail),
        };
    }

    /**
     * Sum aggregationcoef of non extra-credit children.
     *
     * @param \grade_category $category Grade category
     * @return float
     */
    protected function sum_child_weights(\grade_category $category): float {
        global $DB;

        $items = $DB->get_records('grade_items', ['categoryid' => $category->id], 'id ASC');
        $sum = 0.0;
        foreach ($items as $item) {
            if ($item->itemtype === 'course' || $item->itemtype === 'category') {
                continue;
            }
            if ((float) $item->aggregationcoef <= 0) {
                continue;
            }
            $sum += (float) $item->aggregationcoef;
        }
        return $sum;
    }
}
