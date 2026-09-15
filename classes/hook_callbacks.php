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

namespace local_bbcotodobien;

use core\hook\output\before_standard_top_of_body_html_generation;
use local_bbcotodobien\local\course_report;

/**
 * Output hook callbacks.
 *
 * @package    local_bbcotodobien
 * @copyright  2026 David Herney @ BambuCo
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class hook_callbacks {
    /**
     * Inject a course header alert when applicable audits are below 100%.
     *
     * @param before_standard_top_of_body_html_generation $hook Hook
     */
    public static function before_standard_top_of_body_html_generation(
        before_standard_top_of_body_html_generation $hook
    ): void {
        global $PAGE, $OUTPUT;

        if (strpos($PAGE->pagetype, 'course-view') !== 0) {
            return;
        }

        if (((int) $PAGE->course->id === (int) SITEID)) {
            return;
        }

        if (!isloggedin() || isguestuser()) {
            return;
        }

        $context = \context_course::instance((int) $PAGE->course->id);
        if (!has_capability('local/bbcotodobien:viewreport', $context)) {
            return;
        }

        try {
            $alert = course_report::get_header_alert($PAGE->course);
        } catch (\dml_exception $exception) {
            // During upgrades the new plugin code can run before the schema is updated.
            return;
        }

        if (!$alert) {
            return;
        }

        $hook->add_html($OUTPUT->render_from_template('local_bbcotodobien/header_alert', $alert));
    }
}
