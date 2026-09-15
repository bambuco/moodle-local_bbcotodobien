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

// NOTE: no MOODLE_INTERNAL test here, this file may be required by behat before including /config.php.

require_once(__DIR__ . '/../../../../lib/behat/behat_base.php');

/**
 * Behat steps for ToDo good.
 *
 * @package    local_bbcotodobien
 * @category   test
 * @copyright  2026 David Herney @ BambuCo
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class behat_local_bbcotodobien extends behat_base {
    /**
     * Seed a failing site-wide audit for a course.
     *
     * @Given the course :shortname has a ToDo good audit that is not fully compliant
     * @param string $shortname Course shortname
     */
    public function the_course_has_a_todo_good_audit_that_is_not_fully_compliant(string $shortname): void {
        $generator = behat_util::get_data_generator()->get_plugin_generator('local_bbcotodobien');
        $generator->create_failing_audit(['course' => $shortname]);
    }
}
