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

namespace local_bbcotodobien\local;

use local_bbcotodobien\tests\fixtures\stub_rule;

/**
 * Tests for course report data and header alerts.
 *
 * @package    local_bbcotodobien
 * @copyright  2026 David Herney @ BambuCo
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[\PHPUnit\Framework\Attributes\CoversClass(course_report::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(access::class)]
final class course_report_test extends \advanced_testcase {
    /**
     * Load the fixture class.
     */
    public static function setUpBeforeClass(): void {
        global $CFG;
        parent::setUpBeforeClass();
        require_once($CFG->dirroot . '/local/bbcotodobien/tests/fixtures/stub_rule.php');
    }

    /**
     * Header alert is omitted when no types apply.
     */
    public function test_header_alert_omitted_without_types(): void {
        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course();
        $this->assertNull(course_report::get_header_alert($course));
    }

    /**
     * Never-run applicable types are treated as 0% compliance.
     */
    public function test_header_alert_shown_when_never_run(): void {
        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course();
        audit_type_manager::create_type('Quality');
        $alert = course_report::get_header_alert($course);
        $this->assertNotNull($alert);
        $this->assertSame(format_float(0, 2), $alert['percent']);
        $this->assertStringContainsString('id=' . $course->id, $alert['courseurl']);
    }

    /**
     * Fully compliant types do not produce a header alert.
     */
    public function test_header_alert_omitted_when_complete(): void {
        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course();
        $typeid = audit_type_manager::create_type('Quality');
        audit_type_manager::create_rule_config($typeid, [
            'ruleclass' => stub_rule::class,
            'name' => get_string('stubrule', 'local_bbcotodobien'),
            'mandatory' => true,
            'params' => ['pass' => true],
        ]);
        engine::run_audit($course->id, $typeid, 0);
        $this->assertNull(course_report::get_header_alert($course));
    }

    /**
     * Export includes rules, guidance and execute controls.
     */
    public function test_export_current_contains_rules(): void {
        global $PAGE;
        $this->resetAfterTest();
        $this->setAdminUser();
        $course = $this->getDataGenerator()->create_course();
        $typeid = audit_type_manager::create_type('Quality');
        audit_type_manager::create_rule_config($typeid, [
            'ruleclass' => stub_rule::class,
            'name' => get_string('stubrule', 'local_bbcotodobien'),
            'mandatory' => true,
            'guidance' => '<p>Fix this</p>',
            'params' => ['pass' => false],
        ]);
        engine::run_audit($course->id, $typeid);

        $PAGE->set_url('/local/bbcotodobien/view.php', ['id' => $course->id]);
        $data = course_report::export_current($course, $PAGE->get_renderer('core'), true);
        $this->assertTrue($data['hastypes']);
        $this->assertFalse($data['multipletypes']);
        $this->assertTrue($data['canexecute']);
        $this->assertCount(1, $data['types']);
        $this->assertTrue($data['types'][0]['hasrules']);
        $this->assertTrue($data['types'][0]['rules'][0]['hasdetails']);
        $this->assertTrue($data['types'][0]['rules'][0]['hasguidance']);
        $this->assertStringContainsString('Fix this', $data['types'][0]['rules'][0]['guidance']);
    }

    /**
     * Course guidance is formatted without Moodle text filters.
     */
    public function test_export_current_guidance_skips_filters(): void {
        global $PAGE, $SESSION;
        $this->resetAfterTest();
        $this->setAdminUser();
        filter_set_global_state('multilang', TEXTFILTER_ON);
        $SESSION->forcelang = 'en';

        $course = $this->getDataGenerator()->create_course();
        $typeid = audit_type_manager::create_type('Quality');
        audit_type_manager::create_rule_config($typeid, [
            'ruleclass' => stub_rule::class,
            'name' => get_string('stubrule', 'local_bbcotodobien'),
            'mandatory' => true,
            'guidance' => '<p>Use <span lang="en" class="multilang">Start:</span>'
                . '<span lang="es" class="multilang">Inicio:</span></p>',
            'guidanceformat' => FORMAT_HTML,
            'params' => ['pass' => false],
        ]);

        $PAGE->set_url('/local/bbcotodobien/view.php', ['id' => $course->id]);
        $data = course_report::export_current($course, $PAGE->get_renderer('core'), false);
        $guidance = $data['types'][0]['rules'][0]['guidance'];
        $this->assertTrue($data['types'][0]['rules'][0]['hasguidance']);
        $this->assertStringContainsString('Start:', $guidance);
        $this->assertStringContainsString('Inicio:', $guidance);
    }

    /**
     * Snapshot export is read-only and limited to that audit type.
     */
    public function test_export_current_snapshot_is_read_only(): void {
        global $PAGE;
        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course();
        $firsttype = audit_type_manager::create_type('Quality');
        $secondtype = audit_type_manager::create_type('Extra');
        foreach ([$firsttype, $secondtype] as $typeid) {
            audit_type_manager::create_rule_config($typeid, [
                'ruleclass' => stub_rule::class,
                'name' => get_string('stubrule', 'local_bbcotodobien'),
                'mandatory' => true,
                'params' => ['pass' => false],
            ]);
        }
        $audit = engine::run_audit($course->id, $firsttype, 0);
        engine::run_audit($course->id, $secondtype, 0);

        $PAGE->set_url('/local/bbcotodobien/view.php', ['id' => $course->id]);
        $data = course_report::export_current($course, $PAGE->get_renderer('core'), true, (int) $audit->id);
        $this->assertTrue($data['snapshot']);
        $this->assertFalse($data['canexecute']);
        $this->assertCount(1, $data['types']);
        $this->assertSame('Quality', $data['types'][0]['name']);
        $this->assertFalse($data['types'][0]['rules'][0]['canexecute']);
    }

    /**
     * Detail export uses one accordion item per evaluation and expands the first.
     */
    public function test_export_rule_detail_expands_first_item(): void {
        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course();
        $typeid = audit_type_manager::create_type('Quality');
        $ruleid = audit_type_manager::create_rule_config($typeid, [
            'ruleclass' => stub_rule::class,
            'name' => get_string('stubrule', 'local_bbcotodobien'),
            'mandatory' => true,
            'params' => [
                'items' => [
                    ['targetid' => 1, 'targetname' => 'One', 'status' => result::STATUS_FAIL],
                    ['targetid' => 2, 'targetname' => 'Two', 'status' => result::STATUS_PASS],
                ],
            ],
        ]);
        engine::run_audit($course->id, $typeid, 0);
        $data = course_report::export_rule_detail($course, $ruleid);
        $this->assertTrue($data['hasdetails']);
        $this->assertCount(1, $data['details']);
        $this->assertArrayNotHasKey('targetname', $data['details'][0]);
        $this->assertTrue($data['details'][0]['expanded']);
        $this->assertSame(result::STATUS_FAIL, $data['details'][0]['status']);
        $this->assertSame('local-bbcotodobien-status-fail', $data['details'][0]['statusclass']);
        $this->assertSame(get_string('stubrulefailed', 'local_bbcotodobien'), $data['details'][0]['details']);
        $this->assertTrue($data['details'][0]['hasfailinggroups']);
        $this->assertCount(1, $data['details'][0]['failinggroups']);
        $this->assertSame(
            get_string('rulefailingsections_list', 'local_bbcotodobien'),
            $data['details'][0]['failinggroups'][0]['intro']
        );
        $this->assertCount(1, $data['details'][0]['failinggroups'][0]['items']);
        $this->assertSame('One', $data['details'][0]['failinggroups'][0]['items'][0]['name']);
        $this->assertFalse($data['details'][0]['failinggroups'][0]['items'][0]['hasurl']);
    }

    /**
     * All-pass evaluations do not attach a failing list.
     */
    public function test_export_rule_detail_omits_failing_groups_when_all_pass(): void {
        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course();
        $typeid = audit_type_manager::create_type('Quality');
        $ruleid = audit_type_manager::create_rule_config($typeid, [
            'ruleclass' => stub_rule::class,
            'name' => get_string('stubrule', 'local_bbcotodobien'),
            'mandatory' => true,
            'params' => [
                'items' => [
                    ['targetid' => 1, 'targetname' => 'One', 'status' => result::STATUS_PASS],
                    ['targetid' => 2, 'targetname' => 'Two', 'status' => result::STATUS_PASS],
                ],
            ],
        ]);
        engine::run_audit($course->id, $typeid, 0);
        $data = course_report::export_rule_detail($course, $ruleid);
        $this->assertCount(1, $data['details']);
        $this->assertSame(result::STATUS_PASS, $data['details'][0]['status']);
        $this->assertFalse($data['details'][0]['hasfailinggroups']);
        $this->assertSame([], $data['details'][0]['failinggroups']);
        $this->assertSame(get_string('stubrulepassed', 'local_bbcotodobien'), $data['details'][0]['details']);
    }

    /**
     * Failing groups are scoped to each evaluation's own rule_detail rows.
     */
    public function test_export_rule_detail_failing_groups_follow_each_evaluation(): void {
        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course();
        $typeid = audit_type_manager::create_type('Quality');
        $ruleid = audit_type_manager::create_rule_config($typeid, [
            'ruleclass' => stub_rule::class,
            'name' => get_string('stubrule', 'local_bbcotodobien'),
            'mandatory' => true,
            'params' => [
                'items' => [
                    ['targetid' => 1, 'targetname' => 'OldFail', 'status' => result::STATUS_FAIL],
                    ['targetid' => 2, 'targetname' => 'OldPass', 'status' => result::STATUS_PASS],
                ],
            ],
        ]);
        engine::run_audit($course->id, $typeid, 0);
        audit_type_manager::update_rule_config($ruleid, [
            'params' => [
                'items' => [
                    [
                        'targetid' => 10,
                        'targetname' => 'NewFail',
                        'targettype' => 'cm',
                        'status' => result::STATUS_FAIL,
                    ],
                    [
                        'targetid' => 11,
                        'targetname' => 'NewPass',
                        'targettype' => 'cm',
                        'status' => result::STATUS_PASS,
                    ],
                ],
            ],
        ]);
        engine::run_rule($course->id, $ruleid, 0);

        $data = course_report::export_rule_detail($course, $ruleid);
        $this->assertCount(2, $data['details']);

        $this->assertTrue($data['details'][0]['expanded']);
        $this->assertTrue($data['details'][0]['hasfailinggroups']);
        $this->assertSame('NewFail', $data['details'][0]['failinggroups'][0]['items'][0]['name']);
        $this->assertSame(
            get_string('rulefailingactivities_list', 'local_bbcotodobien'),
            $data['details'][0]['failinggroups'][0]['intro']
        );

        $this->assertFalse($data['details'][1]['expanded']);
        $this->assertTrue($data['details'][1]['hasfailinggroups']);
        $this->assertSame('OldFail', $data['details'][1]['failinggroups'][0]['items'][0]['name']);
        $this->assertSame(
            get_string('rulefailingsections_list', 'local_bbcotodobien'),
            $data['details'][1]['failinggroups'][0]['intro']
        );
        $this->assertNotEquals(
            $data['details'][0]['failinggroups'][0]['items'][0]['name'],
            $data['details'][1]['failinggroups'][0]['items'][0]['name']
        );
    }

    /**
     * Section date-label failures include a live section URL in the failing list.
     */
    public function test_export_rule_detail_section_failures_include_urls(): void {
        global $CFG, $DB;
        $this->resetAfterTest();
        require_once($CFG->dirroot . '/course/lib.php');

        $course = $this->getDataGenerator()->create_course(['numsections' => 2, 'format' => 'topics']);
        course_create_sections_if_missing($course, [1, 2]);
        $modinfo = get_fast_modinfo($course);
        $sectionone = $modinfo->get_section_info(1, MUST_EXIST);
        $sectiontwo = $modinfo->get_section_info(2, MUST_EXIST);
        $DB->set_field('course_sections', 'summary', 'Inicio: 1 January 2026', ['id' => $sectionone->id]);
        $DB->set_field('course_sections', 'summary', 'No label here', ['id' => $sectiontwo->id]);
        rebuild_course_cache($course->id, true);

        $typeid = audit_type_manager::create_type('Quality');
        $ruleid = audit_type_manager::create_rule_config($typeid, [
            'ruleclass' => \local_bbcotodobien\local\rules\section_date_label::class,
            'name' => 'Section dates',
            'mandatory' => true,
            'params' => ['datelabel' => 'Inicio:'],
        ]);
        engine::run_audit($course->id, $typeid, 0);

        $data = course_report::export_rule_detail($course, $ruleid);
        $this->assertCount(1, $data['details']);
        $this->assertSame(result::STATUS_FAIL, $data['details'][0]['status']);
        $this->assertTrue($data['details'][0]['hasfailinggroups']);
        $group = $data['details'][0]['failinggroups'][0];
        $this->assertSame(
            get_string('rulesectiondatelabel_fail_nolabel_list', 'local_bbcotodobien', 'Inicio:'),
            $group['intro']
        );
        $urls = array_column($group['items'], 'url');
        $this->assertNotEmpty($urls);
        $this->assertTrue($group['items'][0]['hasurl']);
        $matched = false;
        foreach ($group['items'] as $item) {
            if (str_contains($item['url'], 'id=' . $sectiontwo->id)) {
                $matched = true;
                $this->assertStringContainsString('section.php', $item['url']);
            }
        }
        $this->assertTrue($matched);
    }

    /**
     * Detail export includes previous evaluations of the same rule, newest first.
     */
    public function test_export_rule_detail_includes_previous_evaluations(): void {
        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course();
        $typeid = audit_type_manager::create_type('Quality');
        $ruleid = audit_type_manager::create_rule_config($typeid, [
            'ruleclass' => stub_rule::class,
            'name' => get_string('stubrule', 'local_bbcotodobien'),
            'mandatory' => true,
            'params' => [
                'items' => [
                    ['targetid' => 1, 'targetname' => 'First', 'status' => result::STATUS_FAIL],
                ],
            ],
        ]);
        engine::run_audit($course->id, $typeid, 0);
        audit_type_manager::update_rule_config($ruleid, [
            'params' => [
                'items' => [
                    ['targetid' => 1, 'targetname' => 'Second', 'status' => result::STATUS_PASS],
                ],
            ],
        ]);
        engine::run_rule($course->id, $ruleid, 0);

        $data = course_report::export_rule_detail($course, $ruleid);
        $this->assertTrue($data['hasdetails']);
        $this->assertCount(2, $data['details']);
        $this->assertArrayNotHasKey('targetname', $data['details'][0]);
        $this->assertSame(result::STATUS_PASS, $data['details'][0]['status']);
        $this->assertTrue($data['details'][0]['expanded']);
        $this->assertSame(result::STATUS_FAIL, $data['details'][1]['status']);
        $this->assertFalse($data['details'][1]['expanded']);
        $this->assertNotEmpty($data['details'][0]['evaluatedat']);
        $this->assertNotEmpty($data['details'][1]['evaluatedat']);
        $this->assertSame(get_string('stubrulepassed', 'local_bbcotodobien'), $data['details'][0]['details']);
        $this->assertSame(get_string('stubrulefailed', 'local_bbcotodobien'), $data['details'][1]['details']);
        $this->assertFalse($data['details'][0]['hasfailinggroups']);
        $this->assertTrue($data['details'][1]['hasfailinggroups']);
        $this->assertSame('First', $data['details'][1]['failinggroups'][0]['items'][0]['name']);
    }

    /**
     * A found grade category that fails includes a live gradebook URL.
     */
    public function test_export_rule_detail_gradebook_failure_includes_category_url(): void {
        global $CFG;
        $this->resetAfterTest();
        require_once($CFG->libdir . '/gradelib.php');

        $course = $this->getDataGenerator()->create_course();
        $record = $this->getDataGenerator()->create_grade_category([
            'courseid' => $course->id,
            'fullname' => 'Activities',
        ]);
        $category = \grade_category::fetch(['id' => $record->id]);
        $item = $category->load_grade_item();
        $item->idnumber = 'ACT';
        $item->update();
        $this->getDataGenerator()->create_grade_item([
            'courseid' => $course->id,
            'categoryid' => $category->id,
            'itemtype' => 'manual',
            'itemname' => 'Manual',
        ]);

        $typeid = audit_type_manager::create_type('Quality');
        $ruleid = audit_type_manager::create_rule_config($typeid, [
            'ruleclass' => \local_bbcotodobien\local\rules\grade_category_moditems::class,
            'name' => 'Grade items',
            'mandatory' => true,
            'params' => ['idnumber' => 'ACT'],
        ]);
        engine::run_audit($course->id, $typeid, 0);

        $data = course_report::export_rule_detail($course, $ruleid);
        $this->assertCount(1, $data['details']);
        $this->assertSame(result::STATUS_FAIL, $data['details'][0]['status']);
        $this->assertTrue($data['details'][0]['hasfailinggroups']);
        $this->assertSame(
            get_string('rulegradecategorymoditems_fail_list', 'local_bbcotodobien'),
            $data['details'][0]['failinggroups'][0]['intro']
        );
        $this->assertCount(1, $data['details'][0]['failinggroups'][0]['items']);
        $link = $data['details'][0]['failinggroups'][0]['items'][0];
        $this->assertTrue($link['hasurl']);
        $this->assertStringContainsString('grade/edit/tree/index.php', $link['url']);
        $this->assertStringContainsString('id=' . $course->id, $link['url']);
    }

    /**
     * Missing activities and missing grade categories have no resource link.
     */
    public function test_export_rule_detail_missing_resource_has_no_failing_link(): void {
        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course();
        $typeid = audit_type_manager::create_type('Quality');

        $cmruleid = audit_type_manager::create_rule_config($typeid, [
            'ruleclass' => \local_bbcotodobien\local\rules\cm_content_contains::class,
            'name' => 'Contains',
            'mandatory' => true,
            'params' => [
                'modname' => 'page',
                'idnumber' => 'MISSING',
                'field' => 'content',
                'pattern' => 'Hello',
                'matchmode' => \local_bbcotodobien\local\rules\cm_field_rule::MATCH_LITERAL,
            ],
        ]);
        $graderuleid = audit_type_manager::create_rule_config($typeid, [
            'ruleclass' => \local_bbcotodobien\local\rules\grade_category_moditems::class,
            'name' => 'Grade items',
            'mandatory' => true,
            'params' => ['idnumber' => 'MISSING'],
        ]);
        engine::run_audit($course->id, $typeid, 0);

        $cmdata = course_report::export_rule_detail($course, $cmruleid);
        $this->assertCount(1, $cmdata['details']);
        $this->assertSame(result::STATUS_FAIL, $cmdata['details'][0]['status']);
        $this->assertFalse($cmdata['details'][0]['hasfailinggroups']);
        $this->assertSame([], $cmdata['details'][0]['failinggroups']);
        $this->assertSame(
            get_string('rulenomatches', 'local_bbcotodobien', (object) [
                'modname' => 'page',
                'idnumber' => 'MISSING',
            ]),
            $cmdata['details'][0]['details']
        );

        $gradedata = course_report::export_rule_detail($course, $graderuleid);
        $this->assertCount(1, $gradedata['details']);
        $this->assertSame(result::STATUS_FAIL, $gradedata['details'][0]['status']);
        $this->assertFalse($gradedata['details'][0]['hasfailinggroups']);
        $this->assertSame([], $gradedata['details'][0]['failinggroups']);
        $this->assertSame(
            get_string('rulegradecategorymissing', 'local_bbcotodobien', 'MISSING'),
            $gradedata['details'][0]['details']
        );
    }

    /**
     * Teachers with viewreport can open the dashboard; students cannot.
     */
    public function test_dashboard_access_follows_viewreport(): void {
        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course();
        $teacher = $this->getDataGenerator()->create_and_enrol($course, 'editingteacher');
        $student = $this->getDataGenerator()->create_and_enrol($course, 'student');

        $this->assertTrue(access::user_can_view_dashboard((int) $teacher->id));
        $this->assertContainsEquals((int) $course->id, access::get_viewable_course_ids((int) $teacher->id));
        $this->assertFalse(access::user_can_view_dashboard((int) $student->id));
        $this->assertSame([], access::get_viewable_course_ids((int) $student->id));

        $this->setAdminUser();
        $this->assertTrue(access::user_can_view_dashboard());
        $this->assertContainsEquals((int) $course->id, access::get_viewable_course_ids(null, 1));
    }
}
