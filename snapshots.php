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
 * Snapshot files for an audit type.
 *
 * @package    local_bbcotodobien
 * @copyright  2026 David Herney @ BambuCo
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use local_bbcotodobien\form\snapshot_form;
use local_bbcotodobien\local\audit_type_manager;
use local_bbcotodobien\local\type_matrix_file;

require_once(__DIR__ . '/../../config.php');
require_once($CFG->libdir . '/adminlib.php');

$audittypeid = required_param('audittypeid', PARAM_INT);

admin_externalpage_setup('local_bbcotodobien_manage');

$type = audit_type_manager::get_type($audittypeid);
$manageurl = new moodle_url('/local/bbcotodobien/manage.php');
$pageurl = new moodle_url('/local/bbcotodobien/snapshots.php', ['audittypeid' => $audittypeid]);
$PAGE->set_url($pageurl);
$PAGE->navbar->add(get_string('manageaudittypes', 'local_bbcotodobien'), $manageurl);
$PAGE->navbar->add(get_string('snapshotsfor', 'local_bbcotodobien', format_string($type->name)));

$form = new snapshot_form($pageurl, ['audittypeid' => $audittypeid]);
if ($data = $form->get_data()) {
    type_matrix_file::generate($audittypeid, (string) $data->dataformat);
    redirect(
        $pageurl,
        get_string('snapshotcreated', 'local_bbcotodobien'),
        null,
        \core\output\notification::NOTIFY_SUCCESS
    );
}

$files = type_matrix_file::list_files($audittypeid);

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('snapshotsfor', 'local_bbcotodobien', format_string($type->name)));

echo $OUTPUT->heading(get_string('generatesnapshot', 'local_bbcotodobien'), 3);
$form->display();

echo $OUTPUT->heading(get_string('snapshots', 'local_bbcotodobien'), 3);
if (!$files) {
    echo $OUTPUT->notification(get_string('nosnapshots', 'local_bbcotodobien'), \core\output\notification::NOTIFY_INFO);
} else {
    $table = new html_table();
    $table->attributes['class'] = 'generaltable admintable';
    $table->head = [
        get_string('filename', 'backup'),
        get_string('time'),
        get_string('size'),
        get_string('download'),
    ];
    foreach ($files as $file) {
        $fileurl = moodle_url::make_pluginfile_url(
            $file->get_contextid(),
            $file->get_component(),
            $file->get_filearea(),
            $file->get_itemid(),
            $file->get_filepath(),
            $file->get_filename(),
            true
        );
        $table->data[] = [
            $file->get_filename(),
            userdate($file->get_timemodified()),
            display_size($file->get_filesize()),
            html_writer::link($fileurl, get_string('download')),
        ];
    }
    echo html_writer::table($table);
}

echo $OUTPUT->single_button(
    new moodle_url('/local/bbcotodobien/snapshots_files.php', ['audittypeid' => $audittypeid]),
    get_string('managesnapshotfiles', 'local_bbcotodobien'),
    'get'
);

echo $OUTPUT->footer();
