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

namespace local_bbcotodobien\local\rules;

use local_bbcotodobien\local\detail;
use local_bbcotodobien\local\result;

/**
 * RF-R04: a general forum has a discussion started by a course contact.
 *
 * @package    local_bbcotodobien
 * @copyright  2026 David Herney @ BambuCo
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class forum_coursecontact extends base {
    /**
     * Default display name.
     *
     * @return string
     */
    public static function get_name(): string {
        return get_string('rule_forum_coursecontact', 'local_bbcotodobien');
    }

    /**
     * Human-readable description.
     *
     * @return string
     */
    public static function get_description(): string {
        return get_string('rule_forum_coursecontact_desc', 'local_bbcotodobien');
    }

    /**
     * Evaluation granularity.
     *
     * @return string
     */
    public static function get_granularity(): string {
        return self::GRANULARITY_CM;
    }

    /**
     * Stored parameter names.
     *
     * @return string[]
     */
    public function get_config_param_names(): array {
        return ['idnumber'];
    }

    /**
     * Extra field names stored in the detail JSON besides identifier.
     *
     * @return string[]
     */
    public function get_detail_field_names(): array {
        return ['modname', 'idnumber'];
    }

    /**
     * Add configuration fields.
     *
     * @param \MoodleQuickForm $mform Form to extend
     */
    public function add_config_form_elements($mform): void {
        $mform->addElement('text', 'idnumber', get_string('ruleidnumber', 'local_bbcotodobien'), ['size' => 30]);
        $mform->setType('idnumber', PARAM_TEXT);
        $mform->addHelpButton('idnumber', 'ruleidnumber', 'local_bbcotodobien');
        $mform->addRule('idnumber', get_string('required'), 'required', null, 'client');
    }

    /**
     * Evaluate general forums with the given idnumber.
     *
     * @param \stdClass $course Course record
     * @param array $params Decoded rule parameters
     * @return result
     */
    public function evaluate(\stdClass $course, array $params): result {
        $idnumber = trim((string) ($params['idnumber'] ?? ''));
        if ($idnumber === '') {
            return $this->result_from_details([
                $this->make_detail(
                    self::GRANULARITY_COURSE,
                    (int) $course->id,
                    $course->fullname ?? '',
                    self::STATUS_ERROR,
                    'rulemissingparams'
                ),
            ]);
        }

        if (!cm_locator::table_exists('forum')) {
            return $this->result_from_details([
                $this->make_detail(
                    self::GRANULARITY_COURSE,
                    (int) $course->id,
                    $course->fullname ?? '',
                    self::STATUS_ERROR,
                    'ruleinvalidmodule',
                    ['modname' => 'forum']
                ),
            ]);
        }

        $cms = cm_locator::find((int) $course->id, 'forum', $idnumber);
        if (!$cms) {
            return $this->result_from_details([
                $this->make_detail(
                    self::GRANULARITY_COURSE,
                    (int) $course->id,
                    $course->fullname ?? '',
                    self::STATUS_FAIL,
                    'rulenomatches',
                    [
                        'modname' => 'forum',
                        'idnumber' => $idnumber,
                    ]
                ),
            ]);
        }

        $details = [];
        foreach ($cms as $cm) {
            $details[] = $this->evaluate_forum($course, $cm);
        }
        return $this->result_from_details($details);
    }

    /**
     * Evaluate one forum course module.
     *
     * @param \stdClass $course Course record
     * @param \stdClass $cm Course module locator record
     * @return detail
     */
    protected function evaluate_forum(\stdClass $course, \stdClass $cm): detail {
        global $DB;

        $name = format_string($cm->name ?? (string) $cm->id);
        $forum = $cm->instancerecord;
        if (!$forum) {
            return $this->make_detail(
                self::GRANULARITY_CM,
                (int) $cm->id,
                $name,
                self::STATUS_ERROR,
                'rulemissinginstance'
            );
        }

        if (($forum->type ?? '') !== 'general') {
            return $this->make_detail(
                self::GRANULARITY_CM,
                (int) $cm->id,
                $name,
                self::STATUS_FAIL,
                'ruleforumtypefail'
            );
        }

        $discussions = $DB->get_records('forum_discussions', ['forum' => $forum->id], 'id ASC', 'id, userid');
        $context = \context_course::instance((int) $course->id);
        foreach ($discussions as $discussion) {
            if ($this->user_is_course_contact((int) $discussion->userid, $context)) {
                return $this->make_detail(
                    self::GRANULARITY_CM,
                    (int) $cm->id,
                    $name,
                    self::STATUS_PASS,
                    'ruleforumdiscussion_pass'
                );
            }
        }

        return $this->make_detail(
            self::GRANULARITY_CM,
            (int) $cm->id,
            $name,
            self::STATUS_FAIL,
            'ruleforumdiscussion_fail'
        );
    }

    /**
     * Render formative HTML for a stored detail.
     *
     * @param detail $detail Diagnostic detail
     * @return string HTML
     */
    public function render_detail(detail $detail): string {
        $identifier = $detail->fields['identifier'] ?? '';
        $fields = $detail->fields;
        return match ($identifier) {
            'rulemissingparams' => get_string('rulemissingparams', 'local_bbcotodobien'),
            'ruleinvalidmodule' => get_string('ruleinvalidmodule', 'local_bbcotodobien', $fields['modname'] ?? ''),
            'rulenomatches' => get_string('rulenomatches', 'local_bbcotodobien', (object) [
                'modname' => $fields['modname'] ?? '',
                'idnumber' => $fields['idnumber'] ?? '',
            ]),
            'rulemissinginstance' => get_string('rulemissinginstance', 'local_bbcotodobien'),
            'ruleforumtypefail' => get_string('ruleforumtypefail', 'local_bbcotodobien'),
            'ruleforumdiscussion_pass' => get_string('ruleforumdiscussion_pass', 'local_bbcotodobien'),
            'ruleforumdiscussion_fail' => get_string('ruleforumdiscussion_fail', 'local_bbcotodobien'),
            default => parent::render_detail($detail),
        };
    }

    /**
     * Whether the user has a course-contact role in the course context.
     *
     * @param int $userid User id
     * @param \context_course $context Course context
     * @return bool
     */
    protected function user_is_course_contact(int $userid, \context_course $context): bool {
        global $CFG;

        $roleids = array_filter(array_map('intval', explode(',', $CFG->coursecontact ?? '')));
        foreach ($roleids as $roleid) {
            if (user_has_role_assignment($userid, $roleid, $context->id)) {
                return true;
            }
        }
        return false;
    }
}
