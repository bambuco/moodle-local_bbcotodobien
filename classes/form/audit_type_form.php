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

namespace local_bbcotodobien\form;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->libdir . '/formslib.php');

/**
 * Form to create or edit an audit type.
 *
 * @package    local_bbcotodobien
 * @copyright  2026 David Herney @ BambuCo
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class audit_type_form extends \moodleform {
    /**
     * Form definition.
     */
    protected function definition(): void {
        $mform = $this->_form;

        $mform->addElement('hidden', 'id');
        $mform->setType('id', PARAM_INT);

        $mform->addElement('text', 'name', get_string('audittypename', 'local_bbcotodobien'), ['size' => 60]);
        $mform->setType('name', PARAM_TEXT);
        $mform->addRule('name', get_string('required'), 'required', null, 'client');
        $mform->addHelpButton('name', 'audittypename', 'local_bbcotodobien');

        $displaylist = \core_course_category::make_categories_list();
        $mform->addElement('autocomplete', 'categories', get_string('categories', 'local_bbcotodobien'), $displaylist, [
            'multiple' => true,
            'noselectionstring' => get_string('selectcategories', 'local_bbcotodobien'),
        ]);
        $mform->setType('categories', PARAM_INT);
        $mform->addHelpButton('categories', 'categories', 'local_bbcotodobien');

        $mform->addElement('advcheckbox', 'active', get_string('active', 'local_bbcotodobien'));
        $mform->setDefault('active', 1);

        $this->add_action_buttons();
    }
}
