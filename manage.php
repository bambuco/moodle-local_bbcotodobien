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
 * Manage audit types.
 *
 * @package    local_bbcotodobien
 * @copyright  2026 David Herney @ BambuCo
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use local_bbcotodobien\local\audit_type_manager;
use local_bbcotodobien\local\scope;

require_once(__DIR__ . '/../../config.php');
require_once($CFG->libdir . '/adminlib.php');

$action = optional_param('action', '', PARAM_ALPHANUMEXT);
$id = optional_param('id', 0, PARAM_INT);

admin_externalpage_setup('local_bbcotodobien_manage');

if ($action === 'delete' && $id) {
    $type = audit_type_manager::get_type($id);
    if (optional_param('confirm', 0, PARAM_BOOL) && confirm_sesskey()) {
        audit_type_manager::delete_type($id);
        redirect(
            new moodle_url('/local/bbcotodobien/manage.php'),
            get_string('audittypedeleted', 'local_bbcotodobien'),
            null,
            \core\output\notification::NOTIFY_SUCCESS
        );
    }

    echo $OUTPUT->header();
    echo $OUTPUT->heading(get_string('deleteaudittype', 'local_bbcotodobien'));
    echo $OUTPUT->confirm(
        get_string('deleteaudittypeconfirm', 'local_bbcotodobien', format_string($type->name)),
        new moodle_url('/local/bbcotodobien/manage.php', [
            'action' => 'delete',
            'id' => $id,
            'confirm' => 1,
            'sesskey' => sesskey(),
        ]),
        new moodle_url('/local/bbcotodobien/manage.php')
    );
    echo $OUTPUT->footer();
    exit;
}

$types = audit_type_manager::get_types();

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('manageaudittypes', 'local_bbcotodobien'));
echo $OUTPUT->single_button(
    new moodle_url('/local/bbcotodobien/type.php'),
    get_string('addaudittype', 'local_bbcotodobien'),
    'get'
);

if (!$types) {
    echo $OUTPUT->notification(get_string('noaudittypes', 'local_bbcotodobien'), \core\output\notification::NOTIFY_INFO);
    echo $OUTPUT->footer();
    exit;
}

$table = new html_table();
$table->head = [
    get_string('audittypename', 'local_bbcotodobien'),
    get_string('categories', 'local_bbcotodobien'),
    get_string('active', 'local_bbcotodobien'),
    get_string('rules', 'local_bbcotodobien'),
    get_string('actions'),
];
$table->colclasses = ['leftalign', 'leftalign', 'centeralign', 'centeralign', 'rightalign'];
$table->attributes['class'] = 'generaltable admintable';

foreach ($types as $type) {
    $configs = audit_type_manager::get_rule_configs((int) $type->id);
    $editurl = new moodle_url('/local/bbcotodobien/type.php', ['id' => $type->id]);
    $rulesurl = new moodle_url('/local/bbcotodobien/rules.php', ['audittypeid' => $type->id]);
    $deleteurl = new moodle_url('/local/bbcotodobien/manage.php', [
        'action' => 'delete',
        'id' => $type->id,
        'sesskey' => sesskey(),
    ]);
    $snapshotsurl = new moodle_url('/local/bbcotodobien/snapshots.php', ['audittypeid' => $type->id]);
    $actions = $OUTPUT->action_icon($editurl, new pix_icon('t/edit', get_string('edit'))) .
        $OUTPUT->action_icon($rulesurl, new pix_icon('t/viewdetails', get_string('rules', 'local_bbcotodobien'))) .
        $OUTPUT->action_icon($snapshotsurl, new pix_icon('i/backup', get_string('snapshots', 'local_bbcotodobien'))) .
        $OUTPUT->action_icon($deleteurl, new pix_icon('t/delete', get_string('delete')));

    $table->data[] = [
        format_string($type->name),
        scope::format_categories_for_display($type->categories),
        $type->active ? get_string('yes') : get_string('no'),
        count($configs),
        $actions,
    ];
}

echo html_writer::table($table);
echo $OUTPUT->footer();
