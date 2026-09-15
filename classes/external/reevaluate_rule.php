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

namespace local_bbcotodobien\external;

use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_single_structure;
use core_external\external_value;
use local_bbcotodobien\local\engine;

/**
 * Ajax web service to re-evaluate one audit rule.
 *
 * @package    local_bbcotodobien
 * @copyright  2026 David Herney @ BambuCo
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class reevaluate_rule extends external_api {
    /**
     * Describe parameters.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'courseid' => new external_value(PARAM_INT, 'Course id'),
            'ruleconfigid' => new external_value(PARAM_INT, 'Rule configuration id'),
        ]);
    }

    /**
     * Re-evaluate one rule on a course.
     *
     * @param int $courseid Course id
     * @param int $ruleconfigid Rule configuration id
     * @return array
     */
    public static function execute(int $courseid, int $ruleconfigid): array {
        $params = self::validate_parameters(self::execute_parameters(), [
            'courseid' => $courseid,
            'ruleconfigid' => $ruleconfigid,
        ]);

        $context = \context_course::instance($params['courseid']);
        self::validate_context($context);
        require_capability('local/bbcotodobien:execute', $context);

        $stored = engine::run_rule($params['courseid'], $params['ruleconfigid']);
        return [
            'auditid' => (int) $stored->courseauditid,
            'compliance' => (float) $stored->compliance,
            'status' => $stored->status,
        ];
    }

    /**
     * Describe the return value.
     *
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'auditid' => new external_value(PARAM_INT, 'Course audit id'),
            'compliance' => new external_value(PARAM_FLOAT, 'Rule compliance percentage'),
            'status' => new external_value(PARAM_ALPHA, 'Rule status'),
        ]);
    }
}
