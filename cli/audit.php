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
 * CLI runner for a course audit type or a single rule.
 *
 * @package    local_bbcotodobien
 * @copyright  2026 David Herney @ BambuCo
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define('CLI_SCRIPT', true);

require(__DIR__ . '/../../../config.php');
require_once($CFG->libdir . '/clilib.php');

[$options, $unrecognized] = cli_get_params(
    [
        'help' => false,
        'courseid' => 0,
        'audittypeid' => 0,
        'ruleconfigid' => 0,
    ],
    [
        'h' => 'help',
    ]
);

if ($unrecognized) {
    $unrecognized = implode("\n  ", $unrecognized);
    cli_error(get_string('cliunknowoption', 'admin', $unrecognized));
}

if ($options['help']) {
    $help = "Run a ToDo good audit type or a single rule against a course.

Options:
-h, --help            Print out this help
--courseid=INT        Course id to audit (required)
--audittypeid=INT     Audit type id for a full run
--ruleconfigid=INT    Rule configuration id for a single-rule re-evaluation

Examples:
\$ php local/bbcotodobien/cli/audit.php --courseid=2 --audittypeid=1
\$ php local/bbcotodobien/cli/audit.php --courseid=2 --ruleconfigid=5
";
    echo $help;
    exit(0);
}

$courseid = (int) $options['courseid'];
$audittypeid = (int) $options['audittypeid'];
$ruleconfigid = (int) $options['ruleconfigid'];

if ($courseid <= 0) {
    cli_error(get_string('errorclismissingoptions', 'local_bbcotodobien'));
}

if ($audittypeid && $ruleconfigid) {
    cli_error(get_string('errorcliconflictingoptions', 'local_bbcotodobien'));
}

if (!$audittypeid && !$ruleconfigid) {
    cli_error(get_string('errorclismissingoptions', 'local_bbcotodobien'));
}

if ($ruleconfigid) {
    $stored = \local_bbcotodobien\local\engine::run_rule($courseid, $ruleconfigid, 0);
    $audit = \local_bbcotodobien\local\writer::require_course_audit((int) $stored->courseauditid);
    cli_writeln(get_string('clirulereevaluated', 'local_bbcotodobien', (object) [
        'courseid' => $courseid,
        'ruleconfigid' => $ruleconfigid,
        'compliance' => format_float((float) $stored->compliance, 2),
        'status' => $stored->status,
        'auditid' => $audit->id,
    ]));
    exit(0);
}

$audit = \local_bbcotodobien\local\engine::run_audit($courseid, $audittypeid, 0);
cli_writeln(get_string('cliauditcompleted', 'local_bbcotodobien', (object) [
    'courseid' => $courseid,
    'audittypeid' => $audittypeid,
    'compliance' => format_float((float) $audit->compliance, 2),
    'status' => $audit->status,
    'auditid' => $audit->id,
]));
exit(0);
