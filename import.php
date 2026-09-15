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
 * Import rules into the current audit type.
 *
 * @package    local_bbcotodobien
 * @copyright  2026 David Herney @ BambuCo
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use local_bbcotodobien\form\import_rules_form;
use local_bbcotodobien\local\audit_type_manager;
use local_bbcotodobien\local\rule_transfer;

require_once(__DIR__ . '/../../config.php');
require_once($CFG->libdir . '/adminlib.php');

$audittypeid = required_param('audittypeid', PARAM_INT);

admin_externalpage_setup('local_bbcotodobien_manage');

$type = audit_type_manager::get_type($audittypeid);
$manageurl = new moodle_url('/local/bbcotodobien/manage.php');
$rulesurl = new moodle_url('/local/bbcotodobien/rules.php', ['audittypeid' => $audittypeid]);
$pageurl = new moodle_url('/local/bbcotodobien/import.php', ['audittypeid' => $audittypeid]);
$PAGE->set_url($pageurl);
$PAGE->navbar->add(get_string('manageaudittypes', 'local_bbcotodobien'), $manageurl);
$PAGE->navbar->add(get_string('rulesfor', 'local_bbcotodobien', format_string($type->name)), $rulesurl);
$PAGE->navbar->add(get_string('importrules', 'local_bbcotodobien'));

$form = new import_rules_form($pageurl, ['audittypeid' => $audittypeid]);

if ($form->is_cancelled()) {
    redirect($rulesurl);
}

if ($form->get_data()) {
    $json = $form->get_file_content('ruleexportfile');
    if ($json === false || $json === '') {
        throw new \moodle_exception('errorinvalidruleexport', 'local_bbcotodobien');
    }
    $result = rule_transfer::import_rules($audittypeid, $json);
    $notify = $result->imported
        ? \core\output\notification::NOTIFY_SUCCESS
        : \core\output\notification::NOTIFY_INFO;
    redirect($rulesurl, get_string('importrulessummary', 'local_bbcotodobien', $result), null, $notify);
}

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('importrules', 'local_bbcotodobien'));
$form->display();
echo $OUTPUT->footer();
