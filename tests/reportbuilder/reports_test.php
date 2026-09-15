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

namespace local_bbcotodobien\reportbuilder;

use core_reportbuilder\system_report_factory;
use local_bbcotodobien\reportbuilder\local\systemreports\course_history;
use local_bbcotodobien\reportbuilder\local\systemreports\dashboard;

/**
 * Tests for ToDo good system reports.
 *
 * @package    local_bbcotodobien
 * @copyright  2026 David Herney @ BambuCo
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[\PHPUnit\Framework\Attributes\CoversClass(course_history::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(dashboard::class)]
final class reports_test extends \advanced_testcase {
    /**
     * History and dashboard reports render for a user with viewreport.
     */
    public function test_system_reports_render(): void {
        global $PAGE;
        $this->resetAfterTest();
        $this->setAdminUser();

        $course = $this->getDataGenerator()->create_course();
        $generator = $this->getDataGenerator()->get_plugin_generator('local_bbcotodobien');
        $generator->create_failing_audit(['course' => $course, 'name' => 'Quality']);

        $PAGE->set_url('/local/bbcotodobien/view.php', ['id' => $course->id]);
        $history = system_report_factory::create(
            course_history::class,
            \context_course::instance($course->id),
            '',
            '',
            0,
            ['courseid' => $course->id]
        );
        $historyhtml = $history->output();
        $this->assertStringContainsString('Quality', $historyhtml);

        $PAGE->set_url('/local/bbcotodobien/dashboard.php');
        $dashboard = system_report_factory::create(dashboard::class, \context_system::instance());
        $dashboardhtml = $dashboard->output();
        $this->assertStringContainsString('Quality', $dashboardhtml);
        $this->assertStringContainsString(format_string($course->fullname), $dashboardhtml);
    }
}
