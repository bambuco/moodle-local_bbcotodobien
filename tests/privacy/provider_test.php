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

namespace local_bbcotodobien\privacy;

use core_privacy\local\request\approved_contextlist;
use core_privacy\local\request\approved_userlist;
use core_privacy\local\request\userlist;
use core_privacy\local\request\writer;
use local_bbcotodobien\local\audit_type_manager;
use local_bbcotodobien\local\engine;
use local_bbcotodobien\tests\fixtures\stub_rule;

/**
 * Privacy provider tests.
 *
 * @package    local_bbcotodobien
 * @copyright  2026 David Herney @ BambuCo
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[\PHPUnit\Framework\Attributes\CoversClass(provider::class)]
final class provider_test extends \core_privacy\tests\provider_testcase {
    /**
     * Load the fixture class.
     */
    public static function setUpBeforeClass(): void {
        global $CFG;
        parent::setUpBeforeClass();
        require_once($CFG->dirroot . '/local/bbcotodobien/tests/fixtures/stub_rule.php');
    }

    /**
     * Create an audit executed by a user.
     *
     * @param \stdClass $course Course
     * @param int $userid Runner
     * @return \stdClass Course audit
     */
    protected function create_user_audit(\stdClass $course, int $userid): \stdClass {
        $typeid = audit_type_manager::create_type('Quality');
        audit_type_manager::create_rule_config($typeid, [
            'ruleclass' => stub_rule::class,
            'name' => get_string('stubrule', 'local_bbcotodobien'),
            'mandatory' => true,
            'params' => ['pass' => true],
        ]);
        return engine::run_audit((int) $course->id, $typeid, $userid);
    }

    /**
     * Course contexts containing the user's executions are returned.
     */
    public function test_get_contexts_for_userid(): void {
        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course();
        $user = $this->getDataGenerator()->create_user();
        $other = $this->getDataGenerator()->create_user();
        $this->create_user_audit($course, (int) $user->id);

        $contextlist = provider::get_contexts_for_userid((int) $user->id);
        $this->assertCount(1, $contextlist);
        $this->assertEquals(\context_course::instance($course->id), $contextlist->current());

        $otherlist = provider::get_contexts_for_userid((int) $other->id);
        $this->assertCount(0, $otherlist);
    }

    /**
     * Users with identifiers in a course context are listed.
     */
    public function test_get_users_in_context(): void {
        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course();
        $user = $this->getDataGenerator()->create_user();
        $this->create_user_audit($course, (int) $user->id);
        $context = \context_course::instance($course->id);

        $userlist = new userlist($context, 'local_bbcotodobien');
        provider::get_users_in_context($userlist);
        $this->assertEquals([$user->id], $userlist->get_userids());
    }

    /**
     * Export includes the user's audit and rule results.
     */
    public function test_export_user_data(): void {
        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course();
        $user = $this->getDataGenerator()->create_user();
        $this->create_user_audit($course, (int) $user->id);
        $context = \context_course::instance($course->id);

        $contextlist = new approved_contextlist($user, 'local_bbcotodobien', [$context->id]);
        provider::export_user_data($contextlist);

        $data = writer::with_context($context)->get_data([get_string('pluginname', 'local_bbcotodobien')]);
        $this->assertNotEmpty($data->courseaudits);
        $this->assertNotEmpty($data->ruleresults);
    }

    /**
     * Deleting a user anonymises userid without dropping institutional rows.
     */
    public function test_delete_data_for_user_anonymises_userid(): void {
        global $DB;
        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course();
        $user = $this->getDataGenerator()->create_user();
        $audit = $this->create_user_audit($course, (int) $user->id);
        $context = \context_course::instance($course->id);

        $contextlist = new approved_contextlist($user, 'local_bbcotodobien', [$context->id]);
        provider::delete_data_for_user($contextlist);

        $stored = $DB->get_record('local_bbcotodobien_course_audit', ['id' => $audit->id], '*', MUST_EXIST);
        $this->assertEquals(0, $stored->userid);
        $this->assertTrue($DB->record_exists('local_bbcotodobien_course_audit', ['id' => $audit->id]));
        $this->assertTrue($DB->record_exists('local_bbcotodobien_rule_result', [
            'courseauditid' => $audit->id,
            'userid' => 0,
        ]));
    }

    /**
     * Deleting all users in a context anonymises every identifier.
     */
    public function test_delete_data_for_all_users_in_context(): void {
        global $DB;
        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course();
        $user = $this->getDataGenerator()->create_user();
        $audit = $this->create_user_audit($course, (int) $user->id);

        provider::delete_data_for_all_users_in_context(\context_course::instance($course->id));

        $stored = $DB->get_record('local_bbcotodobien_course_audit', ['id' => $audit->id], '*', MUST_EXIST);
        $this->assertEquals(0, $stored->userid);
    }

    /**
     * Approved user lists are anonymised in the given context.
     */
    public function test_delete_data_for_users(): void {
        global $DB;
        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course();
        $user = $this->getDataGenerator()->create_user();
        $audit = $this->create_user_audit($course, (int) $user->id);
        $context = \context_course::instance($course->id);

        $userlist = new approved_userlist($context, 'local_bbcotodobien', [$user->id]);
        provider::delete_data_for_users($userlist);

        $stored = $DB->get_record('local_bbcotodobien_course_audit', ['id' => $audit->id], '*', MUST_EXIST);
        $this->assertEquals(0, $stored->userid);
    }
}
