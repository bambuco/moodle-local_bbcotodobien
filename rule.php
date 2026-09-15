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
 * Create or edit a rule configuration.
 *
 * @package    local_bbcotodobien
 * @copyright  2026 David Herney @ BambuCo
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use local_bbcotodobien\form\rule_config_form;
use local_bbcotodobien\local\audit_type_manager;
use local_bbcotodobien\local\rules\factory;

require_once(__DIR__ . '/../../config.php');
require_once($CFG->libdir . '/adminlib.php');

$audittypeid = required_param('audittypeid', PARAM_INT);
$id = optional_param('id', 0, PARAM_INT);

admin_externalpage_setup('local_bbcotodobien_manage');

$type = audit_type_manager::get_type($audittypeid);
$context = context_system::instance();
$editoroptions = rule_config_form::editor_options();
$manageurl = new moodle_url('/local/bbcotodobien/manage.php');
$rulesurl = new moodle_url('/local/bbcotodobien/rules.php', ['audittypeid' => $audittypeid]);
$pageurl = new moodle_url('/local/bbcotodobien/rule.php', ['audittypeid' => $audittypeid] + ($id ? ['id' => $id] : []));
$PAGE->set_url($pageurl);
$PAGE->navbar->add(get_string('manageaudittypes', 'local_bbcotodobien'), $manageurl);
$PAGE->navbar->add(get_string('rulesfor', 'local_bbcotodobien', format_string($type->name)), $rulesurl);
$PAGE->navbar->add($id ? get_string('editrule', 'local_bbcotodobien') : get_string('addrule', 'local_bbcotodobien'));

$config = null;
$ruleclass = '';
if ($id) {
    $config = audit_type_manager::get_rule_config($id);
    if ((int) $config->audittypeid !== $audittypeid) {
        throw new \moodle_exception('errorinvalidruleconfig', 'local_bbcotodobien');
    }
    $ruleclass = $config->ruleclass;
}

$form = new rule_config_form($pageurl, [
    'audittypeid' => $audittypeid,
    'id' => $id,
    'ruleclass' => $ruleclass,
]);

if ($form->is_cancelled()) {
    redirect($rulesurl);
}

if ($data = $form->get_data()) {
    $rule = factory::create($data->ruleclass);
    $params = $rule->extract_params($data);
    $record = [
        'ruleclass' => $data->ruleclass,
        'name' => $data->name,
        'mandatory' => !empty($data->mandatory),
        'active' => !empty($data->active),
        'params' => $params,
        'guidance' => '',
        'guidanceformat' => FORMAT_HTML,
    ];

    if ($id) {
        $itemid = $id;
        audit_type_manager::update_rule_config($id, $record);
    } else {
        $itemid = audit_type_manager::create_rule_config($audittypeid, $record);
        $data->id = $itemid;
    }

    $data = file_postupdate_standard_editor(
        $data,
        'guidance',
        $editoroptions,
        $context,
        'local_bbcotodobien',
        'guidance',
        $itemid
    );
    audit_type_manager::update_rule_config($itemid, [
        'guidance' => $data->guidance,
        'guidanceformat' => $data->guidanceformat,
    ]);

    redirect(
        $rulesurl,
        $id ? get_string('ruleupdated', 'local_bbcotodobien') : get_string('rulecreated', 'local_bbcotodobien'),
        null,
        \core\output\notification::NOTIFY_SUCCESS
    );
}

$defaults = (object) [
    'id' => $id,
    'audittypeid' => $audittypeid,
];
if ($config) {
    $defaults->name = $config->name;
    $defaults->ruleclass = $config->ruleclass;
    $defaults->mandatory = $config->mandatory;
    $defaults->active = $config->active;
    $defaults->guidance = $config->guidance;
    $defaults->guidanceformat = $config->guidanceformat;
    $defaults = file_prepare_standard_editor(
        $defaults,
        'guidance',
        $editoroptions,
        $context,
        'local_bbcotodobien',
        'guidance',
        $id
    );
    foreach ($config->paramsdecoded as $name => $value) {
        $defaults->$name = $value;
    }
} else {
    $defaults->guidance = '';
    $defaults->guidanceformat = FORMAT_HTML;
    $defaults = file_prepare_standard_editor(
        $defaults,
        'guidance',
        $editoroptions,
        $context,
        'local_bbcotodobien',
        'guidance',
        0
    );
}
if (!$form->is_submitted()) {
    $form->set_data($defaults);
}

echo $OUTPUT->header();
echo $OUTPUT->heading($id ? get_string('editrule', 'local_bbcotodobien') : get_string('addrule', 'local_bbcotodobien'));
if (!factory::get_class_menu()) {
    echo $OUTPUT->notification(get_string('noruleclasses', 'local_bbcotodobien'), \core\output\notification::NOTIFY_INFO);
}
$form->display();
echo $OUTPUT->footer();
