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
 * Course audit report.
 *
 * @package    local_bbcotodobien
 * @copyright  2026 David Herney @ BambuCo
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use core_reportbuilder\system_report_factory;
use local_bbcotodobien\local\course_report;
use local_bbcotodobien\reportbuilder\local\systemreports\course_history;

require_once(__DIR__ . '/../../config.php');

global $OUTPUT, $PAGE, $USER;

$courseid = required_param('id', PARAM_INT);
$tab = optional_param('tab', 'current', PARAM_ALPHA);
$auditid = optional_param('auditid', 0, PARAM_INT);
$rerun = optional_param('rerun', 0, PARAM_BOOL);

$course = get_course($courseid);
if ((int) $course->id === (int) SITEID) {
    throw new moodle_exception('errorinvalidcourseaudit', 'local_bbcotodobien');
}
$context = context_course::instance($course->id);

require_login($course);
require_capability('local/bbcotodobien:viewreport', $context);

$canexecute = has_capability('local/bbcotodobien:execute', $context);
$urlparams = ['id' => $course->id];
if ($tab === 'history') {
    $urlparams['tab'] = 'history';
}
if ($auditid) {
    $urlparams['auditid'] = $auditid;
}
$PAGE->set_url(new moodle_url('/local/bbcotodobien/view.php', $urlparams));
$PAGE->set_context($context);
$PAGE->set_pagelayout('incourse');
$PAGE->set_title(get_string('pluginname', 'local_bbcotodobien'));
$PAGE->set_heading($course->fullname);
$PAGE->add_body_class('local-bbcotodobien-view');

if ($rerun) {
    require_capability('local/bbcotodobien:execute', $context);
    require_sesskey();
    course_report::rerun_all($course, (int) $USER->id);
    redirect(
        new moodle_url('/local/bbcotodobien/view.php', ['id' => $course->id]),
        get_string('auditrerun', 'local_bbcotodobien'),
        null,
        \core\output\notification::NOTIFY_SUCCESS
    );
}

$tabs = [
    new tabobject(
        'current',
        new moodle_url('/local/bbcotodobien/view.php', ['id' => $course->id]),
        get_string('currentreport', 'local_bbcotodobien')
    ),
    new tabobject(
        'history',
        new moodle_url('/local/bbcotodobien/view.php', ['id' => $course->id, 'tab' => 'history']),
        get_string('history', 'local_bbcotodobien')
    ),
];

if ($tab !== 'history') {
    $PAGE->requires->js_call_amd('local_bbcotodobien/reevaluate', 'init', [
        (int) $course->id,
        (int) $context->id,
        $auditid,
    ]);
}

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('pluginname', 'local_bbcotodobien'));
echo $OUTPUT->tabtree($tabs, $tab === 'history' ? 'history' : 'current');

if ($tab === 'history') {
    $report = system_report_factory::create(
        course_history::class,
        $context,
        '',
        '',
        0,
        ['courseid' => $course->id]
    );
    echo $report->output();
} else {
    $data = course_report::export_current($course, $OUTPUT, $canexecute, $auditid);
    if ($data['snapshot']) {
        echo $OUTPUT->notification(get_string('snapshotnotice', 'local_bbcotodobien'), \core\output\notification::NOTIFY_INFO);
    }
    echo $OUTPUT->render_from_template('local_bbcotodobien/current', $data);
}

echo $OUTPUT->footer();
