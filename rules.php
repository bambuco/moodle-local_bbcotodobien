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
 * List rules for an audit type.
 *
 * @package    local_bbcotodobien
 * @copyright  2026 David Herney @ BambuCo
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use local_bbcotodobien\local\audit_type_manager;
use local_bbcotodobien\local\rules\factory;

require_once(__DIR__ . '/../../config.php');
require_once($CFG->libdir . '/adminlib.php');

$audittypeid = required_param('audittypeid', PARAM_INT);
$action = optional_param('action', '', PARAM_ALPHANUMEXT);
$id = optional_param('id', 0, PARAM_INT);

admin_externalpage_setup('local_bbcotodobien_manage');

$type = audit_type_manager::get_type($audittypeid);
$manageurl = new moodle_url('/local/bbcotodobien/manage.php');
$rulesurl = new moodle_url('/local/bbcotodobien/rules.php', ['audittypeid' => $audittypeid]);
$PAGE->set_url($rulesurl);
$PAGE->navbar->add(get_string('manageaudittypes', 'local_bbcotodobien'), $manageurl);
$PAGE->navbar->add(get_string('rulesfor', 'local_bbcotodobien', format_string($type->name)));

if ($action === 'delete' && $id) {
    $config = audit_type_manager::get_rule_config($id);
    if ((int) $config->audittypeid !== $audittypeid) {
        throw new \moodle_exception('errorinvalidruleconfig', 'local_bbcotodobien');
    }
    if (optional_param('confirm', 0, PARAM_BOOL) && confirm_sesskey()) {
        audit_type_manager::delete_rule_config($id);
        redirect(
            $rulesurl,
            get_string('ruledeleted', 'local_bbcotodobien'),
            null,
            \core\output\notification::NOTIFY_SUCCESS
        );
    }

    echo $OUTPUT->header();
    echo $OUTPUT->heading(get_string('deleterule', 'local_bbcotodobien'));
    echo $OUTPUT->confirm(
        get_string('deleteruleconfirm', 'local_bbcotodobien', format_string($config->name)),
        new moodle_url('/local/bbcotodobien/rules.php', [
            'audittypeid' => $audittypeid,
            'action' => 'delete',
            'id' => $id,
            'confirm' => 1,
            'sesskey' => sesskey(),
        ]),
        $rulesurl
    );
    echo $OUTPUT->footer();
    exit;
}

$configs = audit_type_manager::get_rule_configs($audittypeid);

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('rulesfor', 'local_bbcotodobien', format_string($type->name)));
echo $OUTPUT->single_button(
    new moodle_url('/local/bbcotodobien/rule.php', ['audittypeid' => $audittypeid]),
    get_string('addrule', 'local_bbcotodobien'),
    'get'
);
echo $OUTPUT->single_button(
    new moodle_url('/local/bbcotodobien/import.php', ['audittypeid' => $audittypeid]),
    get_string('importrules', 'local_bbcotodobien'),
    'get'
);
if ($configs) {
    echo $OUTPUT->single_button(
        new moodle_url('/local/bbcotodobien/export.php', ['audittypeid' => $audittypeid]),
        get_string('exportrules', 'local_bbcotodobien'),
        'get'
    );
}

if (!$configs) {
    echo $OUTPUT->notification(get_string('norules', 'local_bbcotodobien'), \core\output\notification::NOTIFY_INFO);
    echo $OUTPUT->footer();
    exit;
}

$table = new html_table();
$table->head = [
    get_string('rulename', 'local_bbcotodobien'),
    get_string('ruleclass', 'local_bbcotodobien'),
    get_string('ruleweighting', 'local_bbcotodobien'),
    get_string('active', 'local_bbcotodobien'),
    get_string('actions'),
];
$table->attributes['class'] = 'generaltable admintable';

foreach ($configs as $config) {
    $displayname = factory::is_instantiatable_rule($config->ruleclass)
        ? $config->ruleclass::get_name()
        : $config->ruleclass;
    $editurl = new moodle_url('/local/bbcotodobien/rule.php', [
        'audittypeid' => $audittypeid,
        'id' => $config->id,
    ]);
    $deleteurl = new moodle_url('/local/bbcotodobien/rules.php', [
        'audittypeid' => $audittypeid,
        'action' => 'delete',
        'id' => $config->id,
        'sesskey' => sesskey(),
    ]);
    $actions = $OUTPUT->action_icon($editurl, new pix_icon('t/edit', get_string('edit'))) .
        $OUTPUT->action_icon($deleteurl, new pix_icon('t/delete', get_string('delete')));

    $table->data[] = [
        format_string($config->name),
        $displayname,
        !empty($config->mandatory) ? get_string('mandatory', 'local_bbcotodobien')
            : get_string('optional', 'local_bbcotodobien'),
        $config->active ? get_string('yes') : get_string('no'),
        $actions,
    ];
}

echo html_writer::table($table);
echo $OUTPUT->footer();
