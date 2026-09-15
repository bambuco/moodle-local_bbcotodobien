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
 * Form to create or edit a rule configuration.
 *
 * @package    local_bbcotodobien
 * @copyright  2026 David Herney @ BambuCo
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class rule_config_form extends \moodleform {
    /**
     * Editor options for the guidance file area.
     *
     * @return array
     */
    public static function editor_options(): array {
        global $CFG;

        return [
            'maxfiles' => EDITOR_UNLIMITED_FILES,
            'maxbytes' => $CFG->maxbytes,
            'trusttext' => false,
            'noclean' => true,
            'context' => \context_system::instance(),
            'subdirs' => false,
        ];
    }

    /**
     * Form definition.
     */
    protected function definition(): void {
        $mform = $this->_form;
        $id = (int) ($this->_customdata['id'] ?? 0);
        $ruleclass = (string) ($this->_customdata['ruleclass'] ?? '');
        if (!$id) {
            $submitted = optional_param('ruleclass', '', PARAM_RAW);
            if ($submitted !== '') {
                $ruleclass = $submitted;
            }
        }

        $mform->addElement('hidden', 'id');
        $mform->setType('id', PARAM_INT);
        $mform->addElement('hidden', 'audittypeid');
        $mform->setType('audittypeid', PARAM_INT);

        $mform->addElement('text', 'name', get_string('rulename', 'local_bbcotodobien'), ['size' => 60]);
        $mform->setType('name', PARAM_TEXT);
        $mform->addRule('name', get_string('required'), 'required', null, 'client');

        $menu = factory::get_class_menu();
        if ($ruleclass !== '' && !isset($menu[$ruleclass])) {
            $menu[$ruleclass] = factory::is_instantiatable_rule($ruleclass)
                ? $ruleclass::get_name()
                : $ruleclass;
        }
        if (!$menu) {
            $menu = ['' => get_string('noruleclasses', 'local_bbcotodobien')];
        }
        if ($id) {
            $mform->addElement('select', 'ruleclass', get_string('ruleclass', 'local_bbcotodobien'), $menu);
            $mform->freeze('ruleclass');
        } else {
            $classgroup = [];
            $classgroup[] = $mform->createElement('select', 'ruleclass', get_string('ruleclass', 'local_bbcotodobien'), $menu);
            $classgroup[] = $mform->createElement(
                'submit',
                'loadparameters',
                get_string('loadparameters', 'local_bbcotodobien')
            );
            $mform->addGroup($classgroup, 'ruleclassgroup', get_string('ruleclass', 'local_bbcotodobien'), ' ', false);
            $mform->registerNoSubmitButton('loadparameters');
        }
        $mform->addHelpButton($id ? 'ruleclass' : 'ruleclassgroup', 'ruleclass', 'local_bbcotodobien');
        $mform->setType('ruleclass', PARAM_RAW);
        if ($ruleclass !== '') {
            $mform->setDefault('ruleclass', $ruleclass);
        }

        $mform->addElement('select', 'mandatory', get_string('ruleweighting', 'local_bbcotodobien'), [
            1 => get_string('mandatory', 'local_bbcotodobien'),
            0 => get_string('optional', 'local_bbcotodobien'),
        ]);
        $mform->setDefault('mandatory', 1);
        $mform->addHelpButton('mandatory', 'ruleweighting', 'local_bbcotodobien');

        $mform->addElement('advcheckbox', 'active', get_string('active', 'local_bbcotodobien'));
        $mform->setDefault('active', 1);

        $mform->addElement(
            'editor',
            'guidance_editor',
            get_string('guidance', 'local_bbcotodobien'),
            null,
            self::editor_options()
        );
        $mform->setType('guidance_editor', PARAM_RAW);
        $mform->addHelpButton('guidance_editor', 'guidance', 'local_bbcotodobien');

        if ($ruleclass !== '' && factory::is_instantiatable_rule($ruleclass)) {
            $mform->addElement('header', 'ruleparameters', get_string('ruleparameters', 'local_bbcotodobien'));
            factory::create($ruleclass)->add_config_form_elements($mform);
        }

        $this->add_action_buttons();
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
        if (empty($data['ruleclass']) || !factory::is_instantiatable_rule($data['ruleclass'])) {
            $key = !empty($this->_customdata['id']) ? 'ruleclass' : 'ruleclassgroup';
            $errors[$key] = get_string('errorinvalidruleclass', 'local_bbcotodobien');
        }
        return $errors;
    }
}
