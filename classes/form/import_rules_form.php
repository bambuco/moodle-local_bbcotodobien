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

require_once($CFG->libdir . '/formslib.php');

/**
 * Form to import rule configurations into the current audit type.
 *
 * @package    local_bbcotodobien
 * @copyright  2026 David Herney @ BambuCo
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class import_rules_form extends \moodleform {
    /**
     * Form definition.
     */
    protected function definition(): void {
        global $CFG;

        $mform = $this->_form;
        $audittypeid = (int) ($this->_customdata['audittypeid'] ?? 0);

        $mform->addElement('hidden', 'audittypeid', $audittypeid);
        $mform->setType('audittypeid', PARAM_INT);

        $mform->addElement('static', 'importintro', '', get_string('importrules_help', 'local_bbcotodobien'));
        $mform->addElement(
            'filepicker',
            'ruleexportfile',
            get_string('ruleexportfile', 'local_bbcotodobien'),
            null,
            [
                'accepted_types' => ['.json'],
                'maxbytes' => $CFG->maxbytes,
            ]
        );
        $mform->addRule('ruleexportfile', get_string('required'), 'required', null, 'client');
        $mform->addHelpButton('ruleexportfile', 'importrules', 'local_bbcotodobien');

        $this->add_action_buttons(true, get_string('importrules', 'local_bbcotodobien'));
    }
}
