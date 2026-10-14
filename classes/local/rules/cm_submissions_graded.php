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
 * RF-R09: teacher grading / feedback coverage of real submissions.
 *
 * @package    local_bbcotodobien
 * @copyright  2026 David Herney @ BambuCo
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class cm_submissions_graded extends base {
    /** @var string Check final grades only */
    public const CHECKMODE_GRADE = 'grade';

    /** @var string Check feedback only */
    public const CHECKMODE_FEEDBACK = 'feedback';

    /** @var string Check both grade and feedback */
    public const CHECKMODE_BOTH = 'both';

    /** @var string Feedback from the gradebook only */
    public const FEEDBACK_GRADEBOOK = 'gradebook';

    /** @var string Feedback from the activity only */
    public const FEEDBACK_ACTIVITY = 'activity';

    /** @var string Feedback from gradebook or activity */
    public const FEEDBACK_HYBRID = 'hybrid';

    /** @var string[] Module names this rule can evaluate */
    public const SUPPORTED_MODS = ['assign', 'workshop', 'quiz', 'forum'];

    /** @var array<int, \assign> Assign instances keyed by cmid */
    protected array $assigncache = [];

    /**
     * Default display name.
     *
     * @return string
     */
    public static function get_name(): string {
        return get_string('rule_cm_submissions_graded', 'local_bbcotodobien');
    }

    /**
     * Human-readable description.
     *
     * @return string
     */
    public static function get_description(): string {
        return get_string('rule_cm_submissions_graded_desc', 'local_bbcotodobien');
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
        return ['modnames', 'threshold', 'checkmode', 'feedbacksource', 'gradecategoryidnumbers'];
    }

    /**
     * Extra field names stored in the detail JSON besides identifier.
     *
     * @return string[]
     */
    public function get_detail_field_names(): array {
        return ['percent', 'threshold', 'graded', 'total'];
    }

    /**
     * Add configuration fields.
     *
     * @param \MoodleQuickForm $mform Form to extend
     */
    public function add_config_form_elements($mform): void {
        $modules = $this->get_supported_mod_options();
        $mform->addElement(
            'autocomplete',
            'modnames',
            get_string('rulemodnames', 'local_bbcotodobien'),
            $modules,
            ['multiple' => true]
        );
        $mform->setType('modnames', PARAM_PLUGIN);
        $mform->addHelpButton('modnames', 'rulemodnames', 'local_bbcotodobien');

        $mform->addElement(
            'text',
            'threshold',
            get_string('rulethreshold', 'local_bbcotodobien'),
            ['size' => 6]
        );
        $mform->setType('threshold', PARAM_FLOAT);
        $mform->setDefault('threshold', 100);
        $mform->addHelpButton('threshold', 'rulethreshold', 'local_bbcotodobien');

        $mform->addElement('select', 'checkmode', get_string('rulecheckmode', 'local_bbcotodobien'), [
            self::CHECKMODE_GRADE => get_string('rulecheckmode_grade', 'local_bbcotodobien'),
            self::CHECKMODE_FEEDBACK => get_string('rulecheckmode_feedback', 'local_bbcotodobien'),
            self::CHECKMODE_BOTH => get_string('rulecheckmode_both', 'local_bbcotodobien'),
        ]);
        $mform->setDefault('checkmode', self::CHECKMODE_GRADE);
        $mform->addHelpButton('checkmode', 'rulecheckmode', 'local_bbcotodobien');

        $mform->addElement('select', 'feedbacksource', get_string('rulefeedbacksource', 'local_bbcotodobien'), [
            self::FEEDBACK_GRADEBOOK => get_string('rulefeedbacksource_gradebook', 'local_bbcotodobien'),
            self::FEEDBACK_ACTIVITY => get_string('rulefeedbacksource_activity', 'local_bbcotodobien'),
            self::FEEDBACK_HYBRID => get_string('rulefeedbacksource_hybrid', 'local_bbcotodobien'),
        ]);
        $mform->setDefault('feedbacksource', self::FEEDBACK_GRADEBOOK);
        $mform->addHelpButton('feedbacksource', 'rulefeedbacksource', 'local_bbcotodobien');

        section_helper::add_gradecategoryidnumbers_element($mform);
    }

    /**
     * Evaluate teacher grading / feedback coverage per applicable CM.
     *
     * @param \stdClass $course Course record
     * @param array $params Decoded rule parameters
     * @return result
     */
    public function evaluate(\stdClass $course, array $params): result {
        global $CFG;
        require_once($CFG->libdir . '/gradelib.php');
        require_once($CFG->dirroot . '/grade/lib.php');

        $threshold = $this->normalize_threshold($params['threshold'] ?? 100);
        if ($threshold === null) {
            return $this->result_from_details([
                $this->make_detail(
                    self::GRANULARITY_COURSE,
                    (int) $course->id,
                    $course->fullname ?? '',
                    self::STATUS_ERROR,
                    'rulethresholdinvalid'
                ),
            ]);
        }

        $checkmode = (string) ($params['checkmode'] ?? self::CHECKMODE_GRADE);
        if (!in_array($checkmode, [self::CHECKMODE_GRADE, self::CHECKMODE_FEEDBACK, self::CHECKMODE_BOTH], true)) {
            $checkmode = self::CHECKMODE_GRADE;
        }
        $feedbacksource = (string) ($params['feedbacksource'] ?? self::FEEDBACK_GRADEBOOK);
        if (!in_array($feedbacksource, [self::FEEDBACK_GRADEBOOK, self::FEEDBACK_ACTIVITY, self::FEEDBACK_HYBRID], true)) {
            $feedbacksource = self::FEEDBACK_GRADEBOOK;
        }

        $modnames = $this->resolve_modnames($params['modnames'] ?? []);
        if (!$modnames) {
            return $this->result_from_details([]);
        }

        $categoryidnumbers = section_helper::parse_idnumbers($params['gradecategoryidnumbers'] ?? '');
        $allowedcmids = null;
        if ($categoryidnumbers) {
            $allowedcmids = array_fill_keys(
                section_helper::get_cmids_in_grade_categories((int) $course->id, $categoryidnumbers),
                true
            );
        }

        $modinfo = get_fast_modinfo($course);
        $details = [];
        foreach ($modinfo->cms as $cm) {
            if ($cm->deletioninprogress || !isset($modnames[$cm->modname])) {
                continue;
            }
            if ($allowedcmids !== null && !isset($allowedcmids[(int) $cm->id])) {
                continue;
            }
            $detail = $this->evaluate_cm($course, $cm, $threshold, $checkmode, $feedbacksource);
            if ($detail) {
                $details[] = $detail;
            }
        }

        return $this->result_from_details($details);
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
            'rulethresholdinvalid' => get_string('rulethresholdinvalid', 'local_bbcotodobien'),
            'rulesubmissionsgraded_pass' => get_string('rulesubmissionsgraded_pass', 'local_bbcotodobien', (object) $fields),
            'rulesubmissionsgraded_fail' => get_string('rulesubmissionsgraded_fail', 'local_bbcotodobien', (object) $fields),
            default => parent::render_detail($detail),
        };
    }

    /**
     * Evaluate one course module, or null when not applicable.
     *
     * @param \stdClass $course Course record
     * @param \cm_info $cm Course module
     * @param float $threshold Required percentage
     * @param string $checkmode One of CHECKMODE_*
     * @param string $feedbacksource One of FEEDBACK_*
     * @return detail|null
     */
    protected function evaluate_cm(
        \stdClass $course,
        \cm_info $cm,
        float $threshold,
        string $checkmode,
        string $feedbacksource
    ): ?detail {
        if (!$this->requires_teacher_grading($cm)) {
            return null;
        }

        $itemnumber = $cm->modname === 'forum' ? 1 : 0;
        $gradeitem = $this->get_grade_item($course, $cm, $itemnumber, $checkmode);
        if (!$gradeitem) {
            return null;
        }

        $userids = $this->get_submission_userids($course, $cm);
        if (!$userids) {
            return null;
        }

        $grades = \grade_grade::fetch_users_grades($gradeitem, $userids, true);
        $ok = 0;
        foreach ($userids as $userid) {
            $gg = $grades[$userid] ?? null;
            $hasgrade = $gg && $gg->finalgrade !== null;
            $needsfeedback = $checkmode === self::CHECKMODE_FEEDBACK || $checkmode === self::CHECKMODE_BOTH;
            $hasfeedback = $needsfeedback
                ? $this->user_has_feedback($cm, $userid, $gg, $feedbacksource)
                : false;

            $pass = match ($checkmode) {
                self::CHECKMODE_FEEDBACK => $hasfeedback,
                self::CHECKMODE_BOTH => $hasgrade && $hasfeedback,
                default => $hasgrade,
            };
            if ($pass) {
                $ok++;
            }
        }

        $total = count($userids);
        $percent = $total > 0 ? (100.0 * $ok / $total) : 100.0;
        $fields = [
            'percent' => format_float($percent, 2),
            'threshold' => format_float($threshold, 2),
            'graded' => $ok,
            'total' => $total,
        ];
        $name = $cm->get_formatted_name();
        $status = ($percent + 0.00001) >= $threshold ? self::STATUS_PASS : self::STATUS_FAIL;
        $identifier = $status === self::STATUS_PASS ? 'rulesubmissionsgraded_pass' : 'rulesubmissionsgraded_fail';

        return $this->make_detail(
            self::GRANULARITY_CM,
            (int) $cm->id,
            $name,
            $status,
            $identifier,
            $fields
        );
    }

    /**
     * Whether the module instance requires teacher grading for this rule.
     *
     * @param \cm_info $cm Course module
     * @return bool
     */
    protected function requires_teacher_grading(\cm_info $cm): bool {
        global $DB;

        return match ($cm->modname) {
            'assign', 'workshop' => true,
            'quiz' => $this->quiz_requires_manual_grading($cm),
            'forum' => ((int) $DB->get_field('forum', 'grade_forum', ['id' => $cm->instance])) !== 0,
            default => false,
        };
    }

    /**
     * Whether a quiz contains at least one manually graded question type.
     *
     * @param \cm_info $cm Course module
     * @return bool
     */
    protected function quiz_requires_manual_grading(\cm_info $cm): bool {
        global $CFG;
        require_once($CFG->dirroot . '/mod/quiz/locallib.php');

        try {
            $quizobj = \mod_quiz\quiz_settings::create((int) $cm->instance, 0);
            $structure = $quizobj->get_structure();
        } catch (\Throwable $e) {
            return false;
        }

        foreach ($structure->get_slots() as $slot) {
            $qtype = $slot->qtype ?? $structure->get_question_type_for_slot((int) $slot->slot);
            if ($qtype === '' || $qtype === 'missingtype') {
                continue;
            }
            if (!\question_bank::is_qtype_installed($qtype)) {
                continue;
            }
            $type = \question_bank::get_qtype($qtype, false);
            if ($type && $type->is_manual_graded()) {
                return true;
            }
        }
        return false;
    }

    /**
     * Fetch the applicable grade item for a course module.
     *
     * @param \stdClass $course Course record
     * @param \cm_info $cm Course module
     * @param int $itemnumber Grade item number
     * @param string $checkmode One of CHECKMODE_*
     * @return \grade_item|null
     */
    protected function get_grade_item(
        \stdClass $course,
        \cm_info $cm,
        int $itemnumber,
        string $checkmode
    ): ?\grade_item {
        $items = \grade_item::fetch_all([
            'itemtype' => 'mod',
            'itemmodule' => $cm->modname,
            'iteminstance' => $cm->instance,
            'courseid' => $course->id,
            'itemnumber' => $itemnumber,
        ]);
        if (!$items) {
            return null;
        }
        $item = reset($items);
        $gradetype = (int) $item->gradetype;
        if ($checkmode === self::CHECKMODE_FEEDBACK) {
            if (!in_array($gradetype, [GRADE_TYPE_VALUE, GRADE_TYPE_SCALE, GRADE_TYPE_TEXT], true)) {
                return null;
            }
        } else if (!$item->is_gradable()) {
            return null;
        }
        return $item;
    }

    /**
     * User ids that count as submissions for the module.
     *
     * @param \stdClass $course Course record
     * @param \cm_info $cm Course module
     * @return int[]
     */
    protected function get_submission_userids(\stdClass $course, \cm_info $cm): array {
        return match ($cm->modname) {
            'assign' => $this->get_assign_submission_userids($cm),
            'workshop' => $this->get_workshop_submission_userids($cm),
            'quiz' => $this->get_quiz_attempt_userids($cm),
            'forum' => $this->get_forum_poster_userids($course, $cm),
            default => [],
        };
    }

    /**
     * Submitted assign user ids (expands team submissions).
     *
     * @param \cm_info $cm Course module
     * @return int[]
     */
    protected function get_assign_submission_userids(\cm_info $cm): array {
        global $CFG, $DB;
        require_once($CFG->dirroot . '/mod/assign/locallib.php');

        $submissions = $DB->get_records('assign_submission', [
            'assignment' => $cm->instance,
            'status' => ASSIGN_SUBMISSION_STATUS_SUBMITTED,
            'latest' => 1,
        ]);
        if (!$submissions) {
            return [];
        }

        $userids = [];
        foreach ($submissions as $submission) {
            if ((int) $submission->userid > 0) {
                $userids[(int) $submission->userid] = (int) $submission->userid;
                continue;
            }
            if ((int) $submission->groupid > 0) {
                foreach (groups_get_members((int) $submission->groupid, 'u.id') as $member) {
                    $userids[(int) $member->id] = (int) $member->id;
                }
            }
        }
        return array_values($userids);
    }

    /**
     * Workshop authors with a non-example submission.
     *
     * @param \cm_info $cm Course module
     * @return int[]
     */
    protected function get_workshop_submission_userids(\cm_info $cm): array {
        global $DB;

        $sql = "SELECT DISTINCT authorid
                  FROM {workshop_submissions}
                 WHERE workshopid = :workshopid
                   AND example = 0
                   AND authorid > 0";
        return array_map('intval', $DB->get_fieldset_sql($sql, ['workshopid' => $cm->instance]));
    }

    /**
     * Quiz users with a finished/submitted/abandoned attempt.
     *
     * @param \cm_info $cm Course module
     * @return int[]
     */
    protected function get_quiz_attempt_userids(\cm_info $cm): array {
        global $CFG, $DB;
        require_once($CFG->dirroot . '/mod/quiz/locallib.php');

        [$insql, $params] = $DB->get_in_or_equal([
            \mod_quiz\quiz_attempt::FINISHED,
            \mod_quiz\quiz_attempt::SUBMITTED,
            \mod_quiz\quiz_attempt::ABANDONED,
        ], SQL_PARAMS_NAMED);
        $params['quiz'] = $cm->instance;
        $sql = "SELECT DISTINCT userid
                  FROM {quiz_attempts}
                 WHERE quiz = :quiz
                   AND preview = 0
                   AND state {$insql}";
        return array_map('intval', $DB->get_fieldset_sql($sql, $params));
    }

    /**
     * Gradable users who posted at least once in the forum.
     *
     * @param \stdClass $course Course record
     * @param \cm_info $cm Course module
     * @return int[]
     */
    protected function get_forum_poster_userids(\stdClass $course, \cm_info $cm): array {
        global $CFG, $DB;
        require_once($CFG->dirroot . '/grade/lib.php');

        $gradable = get_gradable_users((int) $course->id, null, true);
        if (!$gradable) {
            return [];
        }

        [$insql, $params] = $DB->get_in_or_equal(array_keys($gradable), SQL_PARAMS_NAMED, 'u');
        $params['forum'] = $cm->instance;
        $sql = "SELECT DISTINCT p.userid
                  FROM {forum_posts} p
                  JOIN {forum_discussions} d ON d.id = p.discussion
                 WHERE d.forum = :forum
                   AND p.deleted = 0
                   AND p.userid {$insql}";
        return array_map('intval', $DB->get_fieldset_sql($sql, $params));
    }

    /**
     * Whether the user has feedback according to the configured source.
     *
     * @param \cm_info $cm Course module
     * @param int $userid User id
     * @param \grade_grade|null $gg Gradebook grade
     * @param string $feedbacksource One of FEEDBACK_*
     * @return bool
     */
    protected function user_has_feedback(
        \cm_info $cm,
        int $userid,
        ?\grade_grade $gg,
        string $feedbacksource
    ): bool {
        $ingradebook = $gg && trim((string) $gg->feedback) !== '';
        if ($feedbacksource === self::FEEDBACK_GRADEBOOK) {
            return $ingradebook;
        }

        $inactivity = $this->user_has_activity_feedback($cm, $userid);
        if ($feedbacksource === self::FEEDBACK_ACTIVITY) {
            return $inactivity;
        }
        return $ingradebook || $inactivity;
    }

    /**
     * Activity-native feedback detection.
     *
     * @param \cm_info $cm Course module
     * @param int $userid User id
     * @return bool
     */
    protected function user_has_activity_feedback(\cm_info $cm, int $userid): bool {
        return match ($cm->modname) {
            'assign' => $this->assign_user_has_feedback($cm, $userid),
            'quiz' => $this->quiz_user_has_feedback($cm, $userid),
            'workshop' => $this->workshop_user_has_feedback($cm, $userid),
            'forum' => $this->forum_user_has_feedback($cm, $userid),
            default => false,
        };
    }

    /**
     * Whether assign has non-empty feedback in any enabled feedback plugin.
     *
     * @param \cm_info $cm Course module
     * @param int $userid User id
     * @return bool
     */
    protected function assign_user_has_feedback(\cm_info $cm, int $userid): bool {
        $assign = $this->get_assign($cm);
        $grade = $assign->get_user_grade($userid, false);
        if (!$grade) {
            return false;
        }
        foreach ($assign->get_feedback_plugins() as $plugin) {
            if (!$plugin->is_enabled() || !$plugin->is_visible()) {
                continue;
            }
            if (!$plugin->is_empty($grade)) {
                return true;
            }
        }
        return false;
    }

    /**
     * Cached assign API instance.
     *
     * @param \cm_info $cm Course module
     * @return \assign
     */
    protected function get_assign(\cm_info $cm): \assign {
        global $CFG;
        require_once($CFG->dirroot . '/mod/assign/locallib.php');

        $cmid = (int) $cm->id;
        if (!isset($this->assigncache[$cmid])) {
            $this->assigncache[$cmid] = new \assign(
                \context_module::instance($cmid),
                $cm,
                $cm->get_course()
            );
        }
        return $this->assigncache[$cmid];
    }

    /**
     * Whether a quiz attempt has a manual comment for the user.
     *
     * @param \cm_info $cm Course module
     * @param int $userid User id
     * @return bool
     */
    protected function quiz_user_has_feedback(\cm_info $cm, int $userid): bool {
        global $CFG, $DB;
        require_once($CFG->dirroot . '/mod/quiz/locallib.php');

        $attempts = $DB->get_records_select(
            'quiz_attempts',
            'quiz = :quiz AND userid = :userid AND preview = 0 AND state IN (:f, :s, :a)',
            [
                'quiz' => $cm->instance,
                'userid' => $userid,
                'f' => \mod_quiz\quiz_attempt::FINISHED,
                's' => \mod_quiz\quiz_attempt::SUBMITTED,
                'a' => \mod_quiz\quiz_attempt::ABANDONED,
            ],
            'timestart DESC',
            '*',
            0,
            1
        );
        if (!$attempts) {
            return false;
        }
        $attempt = reset($attempts);
        try {
            $attemptobj = \mod_quiz\quiz_attempt::create((int) $attempt->id);
        } catch (\Throwable $e) {
            return false;
        }
        foreach ($attemptobj->get_slots() as $slot) {
            if ($attemptobj->get_question_attempt($slot)->has_manual_comment()) {
                return true;
            }
        }
        return false;
    }

    /**
     * Whether workshop has teacher feedback for the author's submission.
     *
     * @param \cm_info $cm Course module
     * @param int $userid User id
     * @return bool
     */
    protected function workshop_user_has_feedback(\cm_info $cm, int $userid): bool {
        global $DB;

        $submission = $DB->get_record('workshop_submissions', [
            'workshopid' => $cm->instance,
            'authorid' => $userid,
            'example' => 0,
        ]);
        if (!$submission) {
            return false;
        }
        if (trim((string) $submission->feedbackauthor) !== '') {
            return true;
        }

        $sql = "SELECT 1
                  FROM {workshop_assessments}
                 WHERE submissionid = :submissionid
                   AND " . $DB->sql_isnotempty('workshop_assessments', 'feedbackauthor', true, true);
        return $DB->record_exists_sql($sql, ['submissionid' => $submission->id]);
    }

    /**
     * Forum whole-forum feedback (gradebook text on item number 1).
     *
     * @param \cm_info $cm Course module
     * @param int $userid User id
     * @return bool
     */
    protected function forum_user_has_feedback(\cm_info $cm, int $userid): bool {
        global $CFG;
        require_once($CFG->libdir . '/gradelib.php');

        $items = \grade_item::fetch_all([
            'itemtype' => 'mod',
            'itemmodule' => 'forum',
            'iteminstance' => $cm->instance,
            'courseid' => $cm->course,
            'itemnumber' => 1,
        ]);
        if (!$items) {
            return false;
        }
        $item = reset($items);
        $grades = \grade_grade::fetch_users_grades($item, [$userid], true);
        $gg = $grades[$userid] ?? null;
        return $gg && trim((string) $gg->feedback) !== '';
    }

    /**
     * Supported module menu options that are installed and gradable.
     *
     * @return array<string, string>
     */
    protected function get_supported_mod_options(): array {
        $names = get_module_types_names();
        $options = [];
        foreach (self::SUPPORTED_MODS as $modname) {
            if (!plugin_supports('mod', $modname, FEATURE_GRADE_HAS_GRADE, false)) {
                continue;
            }
            if (!isset($names[$modname])) {
                continue;
            }
            $options[$modname] = $names[$modname];
        }
        asort($options, SORT_LOCALE_STRING);
        return $options;
    }

    /**
     * Resolve configured modnames against the supported list.
     *
     * @param array|string $raw Raw param
     * @return array<string, string> Map of modname => modname
     */
    protected function resolve_modnames(array|string $raw): array {
        if (is_string($raw)) {
            $raw = $raw === '' ? [] : preg_split('/\s*,\s*/', $raw);
        }
        $selected = [];
        foreach ($raw as $modname) {
            $modname = clean_param((string) $modname, PARAM_PLUGIN);
            if (
                $modname !== '' &&
                in_array($modname, self::SUPPORTED_MODS, true) &&
                plugin_supports('mod', $modname, FEATURE_GRADE_HAS_GRADE, false)
            ) {
                $selected[$modname] = $modname;
            }
        }
        if ($selected) {
            return $selected;
        }
        $all = [];
        foreach (self::SUPPORTED_MODS as $modname) {
            if (plugin_supports('mod', $modname, FEATURE_GRADE_HAS_GRADE, false)) {
                $all[$modname] = $modname;
            }
        }
        return $all;
    }

    /**
     * Normalize threshold to a float in [0, 100], or null when invalid.
     *
     * @param mixed $value Raw value
     * @return float|null
     */
    protected function normalize_threshold(mixed $value): ?float {
        if ($value === '' || $value === null) {
            return 100.0;
        }
        if (!is_numeric($value)) {
            return null;
        }
        $threshold = (float) $value;
        if ($threshold < 0 || $threshold > 100) {
            return null;
        }
        return $threshold;
    }
}
