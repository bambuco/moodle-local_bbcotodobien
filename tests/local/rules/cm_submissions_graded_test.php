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

use local_bbcotodobien\local\result;

/**
 * Tests for RF-R09 cm_submissions_graded.
 *
 * @package    local_bbcotodobien
 * @copyright  2026 David Herney @ BambuCo
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[\PHPUnit\Framework\Attributes\CoversClass(cm_submissions_graded::class)]
final class cm_submissions_graded_test extends \advanced_testcase {
    /**
     * Load libraries used by the tests.
     */
    public static function setUpBeforeClass(): void {
        global $CFG;
        parent::setUpBeforeClass();
        require_once($CFG->libdir . '/gradelib.php');
        require_once($CFG->dirroot . '/grade/lib.php');
        require_once($CFG->dirroot . '/mod/assign/locallib.php');
    }

    /**
     * Factory discovers the new rule class.
     */
    public function test_factory_discovers_rule(): void {
        $classes = factory::get_rule_classes();
        $this->assertArrayHasKey('cm_submissions_graded', $classes);
        $this->assertSame(cm_submissions_graded::class, $classes['cm_submissions_graded']);
        $this->assertArrayHasKey(cm_submissions_graded::class, factory::get_class_menu());
    }

    /**
     * Assign threshold: 1 of 2 graded fails at 100% and passes at 50%.
     */
    public function test_assign_threshold_grade_only(): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        $course = $this->getDataGenerator()->create_course();
        $student1 = $this->getDataGenerator()->create_and_enrol($course, 'student');
        $student2 = $this->getDataGenerator()->create_and_enrol($course, 'student');
        $assign = $this->create_assign_with_submissions($course, [$student1->id, $student2->id]);
        $this->grade_assign_user($course, $assign, (int) $student1->id, 80);

        $rule = new cm_submissions_graded();
        $fail = $rule->evaluate($course, [
            'modnames' => ['assign'],
            'threshold' => 100,
            'checkmode' => cm_submissions_graded::CHECKMODE_GRADE,
        ]);
        $this->assertCount(1, $fail->details);
        $this->assertSame(result::STATUS_FAIL, $fail->status);
        $this->assertSame(0.0, $fail->compliance);
        $this->assertSame('50.00', $fail->details[0]->fields['percent']);
        $this->assertSame(1, (int) $fail->details[0]->fields['graded']);
        $this->assertSame(2, (int) $fail->details[0]->fields['total']);
        $this->assertSame('rulesubmissionsgraded_fail', $fail->details[0]->fields['identifier']);

        $pass = $rule->evaluate($course, [
            'modnames' => ['assign'],
            'threshold' => 50,
            'checkmode' => cm_submissions_graded::CHECKMODE_GRADE,
        ]);
        $this->assertSame(result::STATUS_PASS, $pass->status);
        $this->assertSame(100.0, $pass->compliance);
    }

    /**
     * checkmode both requires gradebook feedback as well as a grade.
     */
    public function test_checkmode_both_gradebook_feedback(): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        $course = $this->getDataGenerator()->create_course();
        $student = $this->getDataGenerator()->create_and_enrol($course, 'student');
        $assign = $this->create_assign_with_submissions($course, [$student->id]);
        $this->grade_assign_user($course, $assign, (int) $student->id, 90, '');

        $rule = new cm_submissions_graded();
        $fail = $rule->evaluate($course, [
            'modnames' => ['assign'],
            'threshold' => 100,
            'checkmode' => cm_submissions_graded::CHECKMODE_BOTH,
            'feedbacksource' => cm_submissions_graded::FEEDBACK_GRADEBOOK,
        ]);
        $this->assertSame(result::STATUS_FAIL, $fail->status);

        $this->grade_assign_user($course, $assign, (int) $student->id, 90, 'Good work');
        $pass = $rule->evaluate($course, [
            'modnames' => ['assign'],
            'threshold' => 100,
            'checkmode' => cm_submissions_graded::CHECKMODE_BOTH,
            'feedbacksource' => cm_submissions_graded::FEEDBACK_GRADEBOOK,
        ]);
        $this->assertSame(result::STATUS_PASS, $pass->status);
    }

    /**
     * Activity feedback is counted only for activity/hybrid sources.
     */
    public function test_feedbacksource_activity_vs_gradebook(): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        $course = $this->getDataGenerator()->create_course();
        $student = $this->getDataGenerator()->create_and_enrol($course, 'student');
        $assign = $this->create_assign_with_submissions($course, [$student->id]);
        $this->add_assign_activity_feedback_only($course, $assign, (int) $student->id, 'Private comment');

        $rule = new cm_submissions_graded();
        $params = [
            'modnames' => ['assign'],
            'threshold' => 100,
            'checkmode' => cm_submissions_graded::CHECKMODE_FEEDBACK,
        ];

        $gradebook = $rule->evaluate($course, $params + [
            'feedbacksource' => cm_submissions_graded::FEEDBACK_GRADEBOOK,
        ]);
        $this->assertSame(result::STATUS_FAIL, $gradebook->status);

        $activity = $rule->evaluate($course, $params + [
            'feedbacksource' => cm_submissions_graded::FEEDBACK_ACTIVITY,
        ]);
        $this->assertSame(result::STATUS_PASS, $activity->status);

        $hybrid = $rule->evaluate($course, $params + [
            'feedbacksource' => cm_submissions_graded::FEEDBACK_HYBRID,
        ]);
        $this->assertSame(result::STATUS_PASS, $hybrid->status);
    }

    /**
     * Grade category idnumbers limit which activities are evaluated.
     */
    public function test_gradecategoryidnumbers_filter(): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        $course = $this->getDataGenerator()->create_course();
        $student = $this->getDataGenerator()->create_and_enrol($course, 'student');
        $infilter = $this->create_assign_with_submissions($course, [$student->id], 'In filter');
        $outside = $this->create_assign_with_submissions($course, [$student->id], 'Outside');

        $category = $this->create_category_with_idnumber($course, 'SUBG');
        $this->place_mod_in_category($infilter, 'assign', (int) $course->id, (int) $category->id);

        $rule = new cm_submissions_graded();
        $result = $rule->evaluate($course, [
            'modnames' => ['assign'],
            'threshold' => 100,
            'checkmode' => cm_submissions_graded::CHECKMODE_GRADE,
            'gradecategoryidnumbers' => 'SUBG',
        ]);
        $this->assertCount(1, $result->details);
        $modinfo = get_fast_modinfo($course);
        $expectedcmid = (int) $modinfo->instances['assign'][$infilter->id]->id;
        $this->assertSame($expectedcmid, (int) $result->details[0]->targetid);
        $this->assertNotSame(
            (int) $modinfo->instances['assign'][$outside->id]->id,
            (int) $result->details[0]->targetid
        );
    }

    /**
     * Auto-graded quizzes are omitted; essay quizzes with attempts are evaluated.
     */
    public function test_quiz_auto_omitted_essay_evaluated(): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        $course = $this->getDataGenerator()->create_course();
        $student = $this->getDataGenerator()->create_and_enrol($course, 'student');
        $quizgenerator = $this->getDataGenerator()->get_plugin_generator('mod_quiz');

        $quizgenerator->create_test_quiz([
            ['TF1', 1, 'truefalse'],
        ], ['course' => $course->id, 'sumgrades' => 1]);

        $essayobj = $quizgenerator->create_test_quiz([
            ['Essay1', 1, 'essay'],
        ], ['course' => $course->id, 'sumgrades' => 1]);
        $essayquiz = $essayobj->get_quiz();
        $this->setUser($student);
        $attempt = $quizgenerator->create_attempt((int) $essayquiz->id, (int) $student->id);
        $quizgenerator->submit_responses((int) $attempt->id, [1 => 'An essay answer'], false, true);
        $this->setAdminUser();

        $rule = new cm_submissions_graded();
        $result = $rule->evaluate($course, [
            'modnames' => ['quiz'],
            'threshold' => 100,
            'checkmode' => cm_submissions_graded::CHECKMODE_GRADE,
        ]);
        $this->assertCount(1, $result->details);
        $modinfo = get_fast_modinfo($course);
        $this->assertSame(
            (int) $modinfo->instances['quiz'][$essayquiz->id]->id,
            (int) $result->details[0]->targetid
        );
        $this->assertSame(result::STATUS_FAIL, $result->status);
    }

    /**
     * Forum whole-forum grading uses posters among gradable users as denominator.
     */
    public function test_forum_posters_denominator(): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        $course = $this->getDataGenerator()->create_course();
        $student1 = $this->getDataGenerator()->create_and_enrol($course, 'student');
        $student2 = $this->getDataGenerator()->create_and_enrol($course, 'student');
        $this->getDataGenerator()->create_and_enrol($course, 'student');

        $forum = $this->getDataGenerator()->create_module('forum', [
            'course' => $course->id,
            'grade_forum' => 100,
            'assessed' => 0,
        ]);
        $forumgenerator = $this->getDataGenerator()->get_plugin_generator('mod_forum');
        $forumgenerator->create_discussion((object) [
            'course' => $course->id,
            'forum' => $forum->id,
            'userid' => $student1->id,
        ]);
        $forumgenerator->create_discussion((object) [
            'course' => $course->id,
            'forum' => $forum->id,
            'userid' => $student2->id,
        ]);

        grade_update('mod/forum', $course->id, 'mod', 'forum', $forum->id, 1, [
            'userid' => $student1->id,
            'rawgrade' => 70,
        ]);

        $rule = new cm_submissions_graded();
        $result = $rule->evaluate($course, [
            'modnames' => ['forum'],
            'threshold' => 100,
            'checkmode' => cm_submissions_graded::CHECKMODE_GRADE,
        ]);
        $this->assertCount(1, $result->details);
        $this->assertSame(result::STATUS_FAIL, $result->status);
        $this->assertSame(0.0, $result->compliance);
        $this->assertSame('50.00', $result->details[0]->fields['percent']);
        $this->assertSame(1, (int) $result->details[0]->fields['graded']);
        $this->assertSame(2, (int) $result->details[0]->fields['total']);

        $ratingsonly = $this->getDataGenerator()->create_module('forum', [
            'course' => $course->id,
            'grade_forum' => 0,
            'assessed' => 1,
            'scale' => 100,
        ]);
        $forumgenerator->create_discussion((object) [
            'course' => $course->id,
            'forum' => $ratingsonly->id,
            'userid' => $student1->id,
        ]);
        $again = $rule->evaluate($course, [
            'modnames' => ['forum'],
            'threshold' => 100,
            'checkmode' => cm_submissions_graded::CHECKMODE_GRADE,
        ]);
        $this->assertCount(1, $again->details);
    }

    /**
     * Labels and pages are never evaluated.
     */
    public function test_non_supported_mods_omitted(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        $course = $this->getDataGenerator()->create_course();
        $this->getDataGenerator()->create_module('label', ['course' => $course->id]);
        $this->getDataGenerator()->create_module('page', ['course' => $course->id]);

        $result = (new cm_submissions_graded())->evaluate($course, [
            'threshold' => 100,
            'checkmode' => cm_submissions_graded::CHECKMODE_GRADE,
        ]);
        $this->assertSame([], $result->details);
        $this->assertSame(result::STATUS_NA, $result->status);
    }

    /**
     * Invalid threshold is a technical error.
     */
    public function test_invalid_threshold_is_error(): void {
        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course();
        $result = (new cm_submissions_graded())->evaluate($course, ['threshold' => 150]);
        $this->assertSame(result::STATUS_ERROR, $result->status);
        $this->assertSame('rulethresholdinvalid', $result->details[0]->fields['identifier']);
    }

    /**
     * Create an assignment and submitted online-text attempts for the given users.
     *
     * @param \stdClass $course Course
     * @param int[] $userids User ids
     * @param string $name Assignment name
     * @return \stdClass Assignment instance
     */
    private function create_assign_with_submissions(\stdClass $course, array $userids, string $name = 'Task'): \stdClass {
        $assign = $this->getDataGenerator()->create_module('assign', [
            'course' => $course->id,
            'name' => $name,
            'assignsubmission_onlinetext_enabled' => 1,
            'assignfeedback_comments_enabled' => 1,
            'submissiondrafts' => 0,
        ]);
        $generator = $this->getDataGenerator()->get_plugin_generator('mod_assign');
        $cm = get_coursemodule_from_instance('assign', $assign->id, $course->id);
        foreach ($userids as $userid) {
            $generator->create_submission([
                'cmid' => $cm->id,
                'userid' => $userid,
                'onlinetext' => 'Submission from ' . $userid,
                'status' => ASSIGN_SUBMISSION_STATUS_SUBMITTED,
            ]);
        }
        return $assign;
    }

    /**
     * Push a grade (and optional feedback) to the assign gradebook item.
     *
     * @param \stdClass $course Course
     * @param \stdClass $assign Assignment
     * @param int $userid User id
     * @param float $grade Grade value
     * @param string|null $feedback Feedback text or null to omit
     */
    private function grade_assign_user(
        \stdClass $course,
        \stdClass $assign,
        int $userid,
        float $grade,
        ?string $feedback = null
    ): void {
        $data = [
            'userid' => $userid,
            'rawgrade' => $grade,
        ];
        if ($feedback !== null) {
            $data['feedback'] = $feedback;
            $data['feedbackformat'] = FORMAT_HTML;
        }
        grade_update('mod/assign', $course->id, 'mod', 'assign', $assign->id, 0, $data);
    }

    /**
     * Store assign feedback comments without gradebook feedback text.
     *
     * @param \stdClass $course Course
     * @param \stdClass $assign Assignment
     * @param int $userid User id
     * @param string $comment Feedback comment
     */
    private function add_assign_activity_feedback_only(
        \stdClass $course,
        \stdClass $assign,
        int $userid,
        string $comment
    ): void {
        global $DB;

        $cm = get_coursemodule_from_instance('assign', $assign->id, $course->id, false, MUST_EXIST);
        $assignobj = new \assign(\context_module::instance($cm->id), $cm, $course);
        $grade = $assignobj->get_user_grade($userid, true);

        $existing = $DB->get_record('assignfeedback_comments', ['grade' => $grade->id]);
        if ($existing) {
            $existing->commenttext = $comment;
            $existing->commentformat = FORMAT_HTML;
            $DB->update_record('assignfeedback_comments', $existing);
        } else {
            $DB->insert_record('assignfeedback_comments', (object) [
                'assignment' => $assign->id,
                'grade' => $grade->id,
                'commenttext' => $comment,
                'commentformat' => FORMAT_HTML,
            ]);
        }

        $item = \grade_item::fetch([
            'itemtype' => 'mod',
            'itemmodule' => 'assign',
            'iteminstance' => $assign->id,
            'courseid' => $course->id,
        ]);
        if ($item) {
            $grades = \grade_grade::fetch_users_grades($item, [$userid], true);
            if (isset($grades[$userid])) {
                $grades[$userid]->feedback = '';
                $grades[$userid]->feedbackformat = FORMAT_HTML;
                $grades[$userid]->update();
            }
        }
    }

    /**
     * Create a grade category with an idnumber on its category item.
     *
     * @param \stdClass $course Course
     * @param string $idnumber Category idnumber
     * @return \stdClass Category record data
     */
    private function create_category_with_idnumber(\stdClass $course, string $idnumber): \stdClass {
        $record = $this->getDataGenerator()->create_grade_category([
            'courseid' => $course->id,
            'fullname' => 'Category ' . $idnumber,
        ]);
        $category = \grade_category::fetch(['id' => $record->id]);
        $item = $category->load_grade_item();
        $item->idnumber = $idnumber;
        $item->update();
        return $category->get_record_data();
    }

    /**
     * Move a mod grade item into a category.
     *
     * @param \stdClass $instance Module instance
     * @param string $modname Module name
     * @param int $courseid Course id
     * @param int $categoryid Grade category id
     */
    private function place_mod_in_category(
        \stdClass $instance,
        string $modname,
        int $courseid,
        int $categoryid
    ): void {
        $item = \grade_item::fetch([
            'itemtype' => 'mod',
            'itemmodule' => $modname,
            'iteminstance' => $instance->id,
            'courseid' => $courseid,
        ]);
        $item->set_parent($categoryid);
        $item->update();
    }
}
