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
 * Export selected rules of an audit type.
 *
 * @package    local_bbcotodobien
 * @copyright  2026 David Herney @ BambuCo
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use local_bbcotodobien\form\export_rules_form;
use local_bbcotodobien\local\audit_type_manager;
use local_bbcotodobien\local\rule_transfer;

require_once(__DIR__ . '/../../config.php');
require_once($CFG->libdir . '/adminlib.php');
require_once($CFG->libdir . '/filelib.php');

$audittypeid = required_param('audittypeid', PARAM_INT);

admin_externalpage_setup('local_bbcotodobien_manage');

$type = audit_type_manager::get_type($audittypeid);
$configs = audit_type_manager::get_rule_configs($audittypeid);
$manageurl = new moodle_url('/local/bbcotodobien/manage.php');
$rulesurl = new moodle_url('/local/bbcotodobien/rules.php', ['audittypeid' => $audittypeid]);
$pageurl = new moodle_url('/local/bbcotodobien/export.php', ['audittypeid' => $audittypeid]);
$PAGE->set_url($pageurl);
$PAGE->navbar->add(get_string('manageaudittypes', 'local_bbcotodobien'), $manageurl);
$PAGE->navbar->add(get_string('rulesfor', 'local_bbcotodobien', format_string($type->name)), $rulesurl);
$PAGE->navbar->add(get_string('exportrules', 'local_bbcotodobien'));

if (!$configs) {
    redirect($rulesurl, get_string('norules', 'local_bbcotodobien'), null, \core\output\notification::NOTIFY_INFO);
}

$form = new export_rules_form($pageurl, [
    'audittypeid' => $audittypeid,
    'configs' => $configs,
]);

if ($form->is_cancelled()) {
    redirect($rulesurl);
}

if ($data = $form->get_data()) {
    $ruleconfigids = [];
    foreach ($data->ruleids ?? [] as $id => $selected) {
        if (!empty($selected)) {
            $ruleconfigids[] = (int) $id;
        }
    }
    $json = rule_transfer::export_rules($audittypeid, $ruleconfigids);
    $filename = 'bbcotodobien-rules-' . $audittypeid . '-' . time() . '.json';
    send_file($json, $filename, 0, 0, true, true, 'application/json');
}

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('exportrules', 'local_bbcotodobien'));
$form->display();
echo $OUTPUT->footer();
