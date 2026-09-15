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
 * Manage snapshot files for an audit type.
 *
 * @package    local_bbcotodobien
 * @copyright  2026 David Herney @ BambuCo
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use local_bbcotodobien\form\snapshot_files_form;
use local_bbcotodobien\local\audit_type_manager;
use local_bbcotodobien\local\type_matrix_file;

require_once(__DIR__ . '/../../config.php');
require_once($CFG->libdir . '/adminlib.php');
require_once($CFG->dirroot . '/repository/lib.php');

$audittypeid = required_param('audittypeid', PARAM_INT);

admin_externalpage_setup('local_bbcotodobien_manage');

$type = audit_type_manager::get_type($audittypeid);
$manageurl = new moodle_url('/local/bbcotodobien/manage.php');
$returnurl = new moodle_url('/local/bbcotodobien/snapshots.php', ['audittypeid' => $audittypeid]);
$PAGE->set_url(new moodle_url('/local/bbcotodobien/snapshots_files.php', ['audittypeid' => $audittypeid]));
$PAGE->navbar->add(get_string('manageaudittypes', 'local_bbcotodobien'), $manageurl);
$PAGE->navbar->add(get_string('snapshotsfor', 'local_bbcotodobien', format_string($type->name)), $returnurl);
$PAGE->navbar->add(get_string('managesnapshotfiles', 'local_bbcotodobien'));

$context = context_system::instance();
$options = type_matrix_file::filemanager_options();
$data = new stdClass();
file_prepare_standard_filemanager(
    $data,
    'files',
    $options,
    $context,
    'local_bbcotodobien',
    type_matrix_file::FILEAREA,
    $audittypeid
);

$form = new snapshot_files_form(null, ['audittypeid' => $audittypeid, 'data' => $data]);
if ($form->is_cancelled()) {
    redirect($returnurl);
}

$formdata = $form->get_data();
if ($formdata) {
    file_postupdate_standard_filemanager(
        $formdata,
        'files',
        $options,
        $context,
        'local_bbcotodobien',
        type_matrix_file::FILEAREA,
        $audittypeid
    );
    redirect($returnurl);
}

echo $OUTPUT->header();
echo $OUTPUT->heading(get_string('managesnapshotfiles', 'local_bbcotodobien'));
$form->display();
echo $OUTPUT->footer();
