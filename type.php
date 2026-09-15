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
 * Create or edit an audit type.
 *
 * @package    local_bbcotodobien
 * @copyright  2026 David Herney @ BambuCo
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use local_bbcotodobien\form\audit_type_form;
use local_bbcotodobien\local\audit_type_manager;
use local_bbcotodobien\local\scope;

require_once(__DIR__ . '/../../config.php');
require_once($CFG->libdir . '/adminlib.php');

$id = optional_param('id', 0, PARAM_INT);

admin_externalpage_setup('local_bbcotodobien_manage');

$manageurl = new moodle_url('/local/bbcotodobien/manage.php');
$pageurl = new moodle_url('/local/bbcotodobien/type.php', $id ? ['id' => $id] : []);
$PAGE->set_url($pageurl);
$PAGE->navbar->add(get_string('manageaudittypes', 'local_bbcotodobien'), $manageurl);
$PAGE->navbar->add($id ? get_string('editaudittype', 'local_bbcotodobien') : get_string('addaudittype', 'local_bbcotodobien'));

$form = new audit_type_form($pageurl, ['id' => $id]);

if ($form->is_cancelled()) {
    redirect($manageurl);
}

if ($data = $form->get_data()) {
    $categories = $data->categories ?? [];
    if ($id) {
        audit_type_manager::update_type($id, [
            'name' => $data->name,
            'categories' => $categories,
            'active' => !empty($data->active),
        ]);
        $message = get_string('audittypeupdated', 'local_bbcotodobien');
    } else {
        audit_type_manager::create_type($data->name, $categories, !empty($data->active));
        $message = get_string('audittypecreated', 'local_bbcotodobien');
    }
    redirect($manageurl, $message, null, \core\output\notification::NOTIFY_SUCCESS);
}

if (!$form->is_submitted()) {
    if ($id) {
        $type = audit_type_manager::get_type($id);
        $type->categories = scope::parse_category_ids($type->categories);
        $form->set_data($type);
    }
}

echo $OUTPUT->header();
echo $OUTPUT->heading($id ? get_string('editaudittype', 'local_bbcotodobien') : get_string('addaudittype', 'local_bbcotodobien'));
$form->display();
echo $OUTPUT->footer();
