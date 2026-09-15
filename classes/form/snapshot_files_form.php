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

use local_bbcotodobien\local\type_matrix_file;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/formslib.php');

/**
 * File manager form for audit type snapshot files.
 *
 * @package    local_bbcotodobien
 * @copyright  2026 David Herney @ BambuCo
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class snapshot_files_form extends \moodleform {
    /**
     * Form definition.
     */
    protected function definition(): void {
        $mform = $this->_form;
        $audittypeid = (int) ($this->_customdata['audittypeid'] ?? 0);

        $mform->addElement(
            'filemanager',
            'files_filemanager',
            get_string('files'),
            null,
            type_matrix_file::filemanager_options()
        );

        $mform->addElement('hidden', 'audittypeid', $audittypeid);
        $mform->setType('audittypeid', PARAM_INT);

        $this->add_action_buttons(true, get_string('savechanges'));
        $this->set_data($this->_customdata['data'] ?? []);
    }
}
