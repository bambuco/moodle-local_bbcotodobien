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
 * Institutional audit dashboard.
 *
 * @package    local_bbcotodobien
 * @copyright  2026 David Herney @ BambuCo
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use core_reportbuilder\system_report_factory;
use local_bbcotodobien\local\access;
use local_bbcotodobien\reportbuilder\local\systemreports\dashboard;

require_once(__DIR__ . '/../../config.php');

global $OUTPUT, $PAGE;

require_login();

$context = context_system::instance();
$PAGE->set_context($context);
$PAGE->set_url(new moodle_url('/local/bbcotodobien/dashboard.php'));
$PAGE->set_pagelayout('report');
$PAGE->set_title(get_string('dashboard', 'local_bbcotodobien'));
$PAGE->set_heading(get_string('dashboard', 'local_bbcotodobien'));

if (!access::user_can_view_dashboard()) {
    throw new moodle_exception('errorcannotviewdashboard', 'local_bbcotodobien');
}

$report = system_report_factory::create(dashboard::class, $context);

echo $OUTPUT->header();
echo $report->output();
echo $OUTPUT->footer();
