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
 * ToDo good data generator.
 *
 * @package    local_bbcotodobien
 * @category   test
 * @copyright  2026 David Herney @ BambuCo
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use local_bbcotodobien\local\audit_type_manager;
use local_bbcotodobien\local\engine;
use local_bbcotodobien\local\rules\grade_category_moditems;

/**
 * Plugin data generator.
 *
 * @package    local_bbcotodobien
 * @category   test
 * @copyright  2026 David Herney @ BambuCo
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class local_bbcotodobien_generator extends component_generator_base {
    /**
     * Create an audit type.
     *
     * @param array|stdClass $record Type fields
     * @return stdClass
     */
    public function create_audit_type($record = null): stdClass {
        $record = (object) (array) $record;
        $id = audit_type_manager::create_type(
            $record->name ?? 'Quality',
            $record->categories ?? '',
            !isset($record->active) || !empty($record->active)
        );
        return audit_type_manager::get_type($id);
    }

    /**
     * Create a rule configuration.
     *
     * @param array|stdClass $record Rule fields; audittypeid is required
     * @return stdClass
     */
    public function create_rule_config($record = null): stdClass {
        $record = (array) $record;
        if (empty($record['audittypeid'])) {
            throw new coding_exception('audittypeid is required to create a rule configuration.');
        }
        $id = audit_type_manager::create_rule_config((int) $record['audittypeid'], [
            'ruleclass' => $record['ruleclass'] ?? grade_category_moditems::class,
            'name' => $record['name'] ?? get_string('rule_grade_category_moditems', 'local_bbcotodobien'),
            'mandatory' => array_key_exists('mandatory', $record) ? !empty($record['mandatory']) : true,
            'active' => array_key_exists('active', $record) ? !empty($record['active']) : true,
            'params' => $record['params'] ?? ['idnumber' => 'missing-category'],
            'guidance' => $record['guidance'] ?? '',
        ]);
        return audit_type_manager::get_rule_config($id);
    }

    /**
     * Create a site-wide failing audit and run it on a course.
     *
     * @param array|stdClass $record Must include course (id or shortname)
     * @return stdClass Course audit
     */
    public function create_failing_audit($record = null): stdClass {
        global $DB;

        $record = (array) $record;
        if (empty($record['course'])) {
            throw new coding_exception('course is required to create a failing audit.');
        }
        $course = $record['course'];
        if (is_string($course) && !ctype_digit($course)) {
            $course = $DB->get_record('course', ['shortname' => $course], '*', MUST_EXIST);
        } else if (!is_object($course)) {
            $course = get_course((int) $course);
        }
        $type = $this->create_audit_type(['name' => $record['name'] ?? 'Quality']);
        $this->create_rule_config(['audittypeid' => $type->id]);
        return engine::run_audit((int) $course->id, (int) $type->id, 0);
    }
}
