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

use local_bbcotodobien\local\rules\factory;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/formslib.php');

/**
 * Form to choose which rules of an audit type to export.
 *
 * @package    local_bbcotodobien
 * @copyright  2026 David Herney @ BambuCo
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class export_rules_form extends \moodleform {
    /**
     * Form definition.
     */
    protected function definition(): void {
        $mform = $this->_form;
        $audittypeid = (int) ($this->_customdata['audittypeid'] ?? 0);
        $configs = $this->_customdata['configs'] ?? [];

        $mform->addElement('hidden', 'audittypeid', $audittypeid);
        $mform->setType('audittypeid', PARAM_INT);

        $mform->addElement('header', 'selectrules', get_string('selectrules', 'local_bbcotodobien'));
        $mform->addHelpButton('selectrules', 'exportrules', 'local_bbcotodobien');

        foreach ($configs as $config) {
            $displayname = factory::is_instantiatable_rule($config->ruleclass)
                ? $config->ruleclass::get_name()
                : $config->ruleclass;
            $label = format_string($config->name) . ' (' . $displayname . ')';
            $mform->addElement(
                'advcheckbox',
                'ruleids[' . $config->id . ']',
                $label,
                null,
                ['group' => 1]
            );
            $mform->setDefault('ruleids[' . $config->id . ']', 1);
        }

        if (count($configs) > 1) {
            $this->add_checkbox_controller(1, null, null, 1);
        }

        $this->add_action_buttons(true, get_string('exportrules', 'local_bbcotodobien'));
    }

    /**
     * Validate submitted data.
     *
     * @param array $data Submitted data
     * @param array $files Submitted files
     * @return array
     */
    public function validation($data, $files): array {
        $errors = parent::validation($data, $files);
        $selected = [];
        foreach ($data['ruleids'] ?? [] as $id => $value) {
            if (!empty($value)) {
                $selected[] = (int) $id;
            }
        }
        if (!$selected) {
            $errors['selectrules'] = get_string('errornorulesselected', 'local_bbcotodobien');
        }
        return $errors;
    }
}
