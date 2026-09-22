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
 * Tests for section and gradebook catalogue rules RF-R05 to RF-R08.
 *
 * @package    local_bbcotodobien
 * @copyright  2026 David Herney @ BambuCo
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[\PHPUnit\Framework\Attributes\CoversClass(section_helper::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(section_date_label::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(section_activity_dates::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(gradebook_rule::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(grade_category_moditems::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(grade_category_weights::class)]
final class section_gradebook_catalog_test extends \advanced_testcase {
    /**
     * Load grade libraries used by the catalogue tests.
     */
    public static function setUpBeforeClass(): void {
        global $CFG;
        parent::setUpBeforeClass();
        require_once($CFG->libdir . '/gradelib.php');
        require_once($CFG->dirroot . '/course/lib.php');
    }

    /**
     * Create a topics course with numbered sections.
     *
     * @param int $numsections Number of sections besides 0
     * @return \stdClass
     */
    private function create_course_with_sections(int $numsections = 2): \stdClass {
        return $this->getDataGenerator()->create_course([
            'numsections' => $numsections,
            'format' => 'topics',
        ]);
    }

    /**
     * Set a custom section name.
     *
     * @param \stdClass $course Course
     * @param int $sectionnum Section number
     * @param string $name Section name
     */
    private function set_section_name(\stdClass $course, int $sectionnum, string $name): void {
        $section = get_fast_modinfo($course)->get_section_info($sectionnum);
        course_update_section($course, $section, ['name' => $name]);
        rebuild_course_cache($course->id, true);
    }

    /**
     * Set a section summary.
     *
     * @param \stdClass $course Course
     * @param int $sectionnum Section number
     * @param string $summary HTML summary
     */
    private function set_section_summary(\stdClass $course, int $sectionnum, string $summary): void {
        $section = get_fast_modinfo($course)->get_section_info($sectionnum);
        course_update_section($course, $section, [
            'summary' => $summary,
            'summaryformat' => FORMAT_HTML,
        ]);
        rebuild_course_cache($course->id, true);
    }

    /**
     * Set course-section visibility (course_sections.visible).
     *
     * @param \stdClass $course Course
     * @param int $sectionnum Section number
     * @param int $visible 1 visible, 0 hidden
     */
    private function set_section_visible(\stdClass $course, int $sectionnum, int $visible): void {
        $section = get_fast_modinfo($course)->get_section_info($sectionnum);
        course_update_section($course, $section, ['visible' => $visible]);
        rebuild_course_cache($course->id, true);
    }

    /**
     * Create a grade category and set its item idnumber.
     *
     * @param \stdClass $course Course
     * @param string $idnumber Id number
     * @param int|null $aggregation Optional aggregation method
     * @return \stdClass Category record
     */
    private function create_category_with_idnumber(
        \stdClass $course,
        string $idnumber,
        ?int $aggregation = null
    ): \stdClass {
        $record = $this->getDataGenerator()->create_grade_category([
            'courseid' => $course->id,
            'fullname' => 'Category ' . $idnumber,
        ]);
        $category = \grade_category::fetch(['id' => $record->id]);
        if ($aggregation !== null) {
            $category->aggregation = $aggregation;
            $category->update();
        }
        $item = $category->load_grade_item();
        $item->idnumber = $idnumber;
        $item->update();
        return $category->get_record_data();
    }

    /**
     * Move an assignment grade item into a category and set its weight.
     *
     * @param \stdClass $assign Assignment instance
     * @param int $courseid Course id
     * @param int $categoryid Grade category id
     * @param float $weight Aggregation coefficient
     */
    private function place_assign_in_category(
        \stdClass $assign,
        int $courseid,
        int $categoryid,
        float $weight
    ): void {
        $item = \grade_item::fetch([
            'itemtype' => 'mod',
            'itemmodule' => 'assign',
            'iteminstance' => $assign->id,
            'courseid' => $courseid,
        ]);
        $item->set_parent($categoryid);
        $item->aggregationcoef = $weight;
        $item->weightoverride = 1;
        $item->update();
    }

    /**
     * A label followed by a parseable date passes; missing label or date fails.
     */
    public function test_section_date_label_pass_and_fail(): void {
        $this->resetAfterTest();
        $course = $this->create_course_with_sections(2);
        $this->set_section_summary($course, 1, '<p>Start: 15 March 2026 extra</p>');
        $this->set_section_summary($course, 2, '<p>No schedule here</p>');
        $rule = new section_date_label();
        $base = ['datelabel' => 'start:', 'excludesections' => '0'];

        $result = $rule->evaluate($course, $base);
        $this->assertCount(2, $result->details);
        $this->assertSame(50.0, $result->compliance);
        $this->assertSame(result::STATUS_FAIL, $result->status);
        $this->assertSame(result::STATUS_PASS, $result->details[0]->status);
        $this->assertSame(result::STATUS_FAIL, $result->details[1]->status);

        $this->set_section_summary($course, 2, '<p>START: soon</p>');
        $nodate = $rule->evaluate($course, ['datelabel' => 'Start:', 'excludesections' => '0']);
        $this->assertSame(result::STATUS_FAIL, $nodate->details[1]->status);
        $this->assertSame('rulesectiondatelabel_fail_nodate', $nodate->details[1]->fields['identifier']);
        $this->assertSame('Start:', $nodate->details[1]->fields['label']);
        $this->assertSame(
            get_string('rulesectiondatelabel_fail_nodate', 'local_bbcotodobien', 'Start:'),
            $rule->render_detail($nodate->details[1])
        );
    }

    /**
     * Slash dates in d/m/yyyy form pass; US m/d/yyyy still passes; invalid calendar dates fail.
     */
    public function test_section_date_label_accepts_dmy_slash_dates(): void {
        $this->resetAfterTest();
        $course = $this->create_course_with_sections(1);
        $rule = new section_date_label();

        $this->set_section_summary(
            $course,
            1,
            '<p>Fecha de Inicio: 14/09/2026 Fecha de Finalización: 15/09/2026</p>'
        );
        $dmy = $rule->evaluate($course, ['datelabel' => 'Fecha de Inicio:', 'excludesections' => '0']);
        $this->assertSame(result::STATUS_PASS, $dmy->status);
        $this->assertSame(result::STATUS_PASS, $dmy->details[0]->status);

        $this->set_section_summary($course, 1, '<p>Start: 10/14/2026</p>');
        $mdy = $rule->evaluate($course, ['datelabel' => 'Start:', 'excludesections' => '0']);
        $this->assertSame(result::STATUS_PASS, $mdy->status);

        $this->set_section_summary($course, 1, '<p>Start: 31/02/2026</p>');
        $invalid = $rule->evaluate($course, ['datelabel' => 'Start:', 'excludesections' => '0']);
        $this->assertSame(result::STATUS_FAIL, $invalid->status);
        $this->assertSame('rulesectiondatelabel_fail_nodate', $invalid->details[0]->fields['identifier']);
    }

    /**
     * Embedded @@PLUGINFILE@@ tokens in the summary must be rewritten before format_text().
     */
    public function test_section_date_label_rewrites_pluginfile_urls(): void {
        $this->resetAfterTest();
        $course = $this->create_course_with_sections(1);
        $this->set_section_summary(
            $course,
            1,
            '<p><img src="@@PLUGINFILE@@/banner.png" alt="" />Start: 15 March 2026</p>'
        );

        $result = (new section_date_label())->evaluate($course, [
            'datelabel' => 'Start:',
            'excludesections' => '0',
        ]);
        $this->assertDebuggingNotCalled();
        $this->assertSame(result::STATUS_PASS, $result->status);
        $this->assertSame(result::STATUS_PASS, $result->details[0]->status);
    }

    /**
     * Excluded section numbers are skipped, including section 0 when listed.
     */
    public function test_section_date_label_excludes_sections(): void {
        $this->resetAfterTest();
        $course = $this->create_course_with_sections(2);
        $this->set_section_summary($course, 0, '<p>Start: 2026-03-01</p>');
        $this->set_section_summary($course, 1, '<p>Start: 2026-04-01</p>');
        $this->set_section_summary($course, 2, '<p>Missing</p>');

        $result = (new section_date_label())->evaluate($course, [
            'datelabel' => 'Start:',
            'excludesections' => '0,2',
        ]);
        $this->assertCount(1, $result->details);
        $this->assertSame(result::STATUS_PASS, $result->status);
    }

    /**
     * Section 0 is evaluated like any other section.
     */
    public function test_section_date_label_evaluates_section_zero(): void {
        $this->resetAfterTest();
        $course = $this->create_course_with_sections(0);
        $this->set_section_summary($course, 0, '<p>Start: 15 March 2026</p>');

        $pass = (new section_date_label())->evaluate($course, ['datelabel' => 'Start:']);
        $this->assertCount(1, $pass->details);
        $this->assertSame(result::STATUS_PASS, $pass->status);

        $excluded = (new section_date_label())->evaluate($course, [
            'datelabel' => 'Start:',
            'excludesections' => '0',
        ]);
        $this->assertSame(result::STATUS_NA, $excluded->status);
        $this->assertSame([], $excluded->details);
    }

    /**
     * Hidden sections are skipped unless includehiddensections is enabled.
     */
    public function test_section_date_label_includehiddensections(): void {
        $this->resetAfterTest();
        $course = $this->create_course_with_sections(2);
        $this->set_section_summary($course, 1, '<p>Start: 15 March 2026</p>');
        $this->set_section_summary($course, 2, '<p>Start: 20 March 2026</p>');
        $this->set_section_visible($course, 2, 0);
        $rule = new section_date_label();
        $base = ['datelabel' => 'Start:', 'excludesections' => '0'];

        $visibleonly = $rule->evaluate($course, $base);
        $this->assertCount(1, $visibleonly->details);
        $this->assertSame(result::STATUS_PASS, $visibleonly->status);

        $withhidden = $rule->evaluate($course, $base + ['includehiddensections' => 1]);
        $this->assertCount(2, $withhidden->details);
        $this->assertSame(result::STATUS_PASS, $withhidden->status);
    }

    /**
     * A section name regex includes matching sections and skips the rest.
     */
    public function test_section_date_label_sectionnameregex(): void {
        $this->resetAfterTest();
        $course = $this->create_course_with_sections(2);
        $this->set_section_name($course, 1, 'Unidad 1');
        $this->set_section_name($course, 2, 'Introducción');
        $this->set_section_summary($course, 1, '<p>Start: 15 March 2026</p>');
        $this->set_section_summary($course, 2, '<p>Missing</p>');
        $rule = new section_date_label();
        $base = ['datelabel' => 'Start:', 'excludesections' => '0'];

        $filtered = $rule->evaluate($course, $base + ['sectionnameregex' => '^Unidad']);
        $this->assertCount(1, $filtered->details);
        $this->assertSame(result::STATUS_PASS, $filtered->status);
        $this->assertSame('Unidad 1', $filtered->details[0]->targetname);

        $unfiltered = $rule->evaluate($course, $base);
        $this->assertCount(2, $unfiltered->details);
        $this->assertSame(result::STATUS_FAIL, $unfiltered->status);

        $empty = $rule->evaluate($course, $base + ['sectionnameregex' => '   ']);
        $this->assertCount(2, $empty->details);
        $this->assertSame(result::STATUS_FAIL, $empty->status);

        $invalid = $rule->evaluate($course, $base + ['sectionnameregex' => '(']);
        $this->assertSame(result::STATUS_ERROR, $invalid->status);
        $this->assertSame('ruleinvalidregex', $invalid->details[0]->fields['identifier']);
        $this->assertSame(
            get_string('ruleinvalidregex', 'local_bbcotodobien'),
            $rule->render_detail($invalid->details[0])
        );
    }

    /**
     * skipfilters keeps multilang markup so a label in a hidden language still matches.
     */
    public function test_section_date_label_skipfilters_bypasses_multilang(): void {
        global $CFG;
        require_once($CFG->libdir . '/filterlib.php');

        $this->resetAfterTest();
        filter_set_global_state('multilang', TEXTFILTER_ON);
        \filter_manager::reset_caches();

        $course = $this->create_course_with_sections(1);
        $this->set_section_summary(
            $course,
            1,
            '<p><span lang="en" class="multilang">Hello</span>'
                . '<span lang="xx" class="multilang">Start: 15 March 2026</span></p>'
        );
        $rule = new section_date_label();
        $base = ['datelabel' => 'Start:', 'excludesections' => '0'];

        $filtered = $rule->evaluate($course, $base);
        $this->assertSame(result::STATUS_FAIL, $filtered->status);
        $this->assertSame('rulesectiondatelabel_fail_nolabel', $filtered->details[0]->fields['identifier']);

        $unfiltered = $rule->evaluate($course, $base + ['skipfilters' => 1]);
        $this->assertSame(result::STATUS_PASS, $unfiltered->status);
        $this->assertSame(result::STATUS_PASS, $unfiltered->details[0]->status);
    }

    /**
     * A course with only section 0 still evaluates that section.
     */
    public function test_section_date_label_with_only_section_zero(): void {
        $this->resetAfterTest();
        $course = $this->create_course_with_sections(0);

        $fail = (new section_date_label())->evaluate($course, ['datelabel' => 'Start:']);
        $this->assertCount(1, $fail->details);
        $this->assertSame(result::STATUS_FAIL, $fail->status);
        $this->assertSame('rulesectiondatelabel_fail_nolabel', $fail->details[0]->fields['identifier']);

        $dates = (new section_activity_dates())->evaluate($course, []);
        $this->assertSame(result::STATUS_NA, $dates->status);
        $this->assertSame(100.0, $dates->compliance);
        $this->assertSame([], $dates->details);
    }

    /**
     * Activities with a native date pair must have both dates set.
     */
    public function test_section_activity_dates_pair_and_na(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        $course = $this->create_course_with_sections(1);
        $start = time() - DAYSECS;
        $end = time() + DAYSECS;

        $this->getDataGenerator()->create_module('page', [
            'course' => $course->id,
            'section' => 1,
        ]);
        $this->getDataGenerator()->create_module('assign', [
            'course' => $course->id,
            'section' => 1,
            'allowsubmissionsfromdate' => $start,
            'duedate' => $end,
        ]);
        $this->getDataGenerator()->create_module('assign', [
            'course' => $course->id,
            'section' => 1,
            'allowsubmissionsfromdate' => 0,
            'duedate' => 0,
        ]);
        $this->getDataGenerator()->create_module('assign', [
            'course' => $course->id,
            'section' => 1,
            'allowsubmissionsfromdate' => $start,
            'duedate' => 0,
        ]);
        $this->getDataGenerator()->create_module('forum', [
            'course' => $course->id,
            'section' => 1,
            'duedate' => $end,
        ]);

        $result = (new section_activity_dates())->evaluate($course, []);
        $this->assertCount(3, $result->details);
        $statuses = array_map(static fn($detail) => $detail->status, $result->details);
        $this->assertContains(result::STATUS_PASS, $statuses);
        $this->assertSame(2, count(array_filter($statuses, static fn($status) => $status === result::STATUS_FAIL)));
        $this->assertNotContains(result::STATUS_NA, $statuses);
        $this->assertSame(33.33, $result->compliance);
        $this->assertSame(result::STATUS_FAIL, $result->status);
    }

    /**
     * Excluded sections are not inspected.
     */
    public function test_section_activity_dates_respects_exclusions(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        $course = $this->create_course_with_sections(2);
        $this->getDataGenerator()->create_module('assign', [
            'course' => $course->id,
            'section' => 2,
            'allowsubmissionsfromdate' => 0,
            'duedate' => 0,
        ]);

        $result = (new section_activity_dates())->evaluate($course, ['excludesections' => '2']);
        $this->assertSame(result::STATUS_NA, $result->status);
        $this->assertSame(100.0, $result->compliance);
    }

    /**
     * Hidden sections are skipped unless includehiddensections is enabled.
     */
    public function test_section_activity_dates_includehiddensections(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        $course = $this->create_course_with_sections(2);
        $this->getDataGenerator()->create_module('assign', [
            'course' => $course->id,
            'section' => 2,
            'allowsubmissionsfromdate' => 0,
            'duedate' => 0,
        ]);
        $this->set_section_visible($course, 2, 0);

        $visibleonly = (new section_activity_dates())->evaluate($course, ['excludesections' => '0']);
        $this->assertSame(result::STATUS_NA, $visibleonly->status);
        $this->assertSame([], $visibleonly->details);

        $withhidden = (new section_activity_dates())->evaluate($course, [
            'excludesections' => '0',
            'includehiddensections' => 1,
        ]);
        $this->assertCount(1, $withhidden->details);
        $this->assertSame(result::STATUS_FAIL, $withhidden->status);
    }

    /**
     * Activities in sections whose name does not match the regex are skipped.
     */
    public function test_section_activity_dates_sectionnameregex(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        $course = $this->create_course_with_sections(2);
        $this->set_section_name($course, 1, 'Unidad 1');
        $this->set_section_name($course, 2, 'Introducción');
        $this->getDataGenerator()->create_module('assign', [
            'course' => $course->id,
            'section' => 1,
            'allowsubmissionsfromdate' => 0,
            'duedate' => 0,
        ]);
        $this->getDataGenerator()->create_module('assign', [
            'course' => $course->id,
            'section' => 2,
            'allowsubmissionsfromdate' => 0,
            'duedate' => 0,
        ]);

        $filtered = (new section_activity_dates())->evaluate($course, [
            'excludesections' => '0',
            'sectionnameregex' => '^Unidad',
        ]);
        $this->assertCount(1, $filtered->details);
        $this->assertSame(result::STATUS_FAIL, $filtered->status);

        $unfiltered = (new section_activity_dates())->evaluate($course, ['excludesections' => '0']);
        $this->assertCount(2, $unfiltered->details);
        $this->assertSame(result::STATUS_FAIL, $unfiltered->status);

        $invalid = (new section_activity_dates())->evaluate($course, [
            'excludesections' => '0',
            'sectionnameregex' => '(',
        ]);
        $this->assertSame(result::STATUS_ERROR, $invalid->status);
        $this->assertSame('ruleinvalidregex', $invalid->details[0]->fields['identifier']);
    }

    /**
     * Optional grade category idnumbers limit which activities are evaluated.
     */
    public function test_section_activity_dates_gradecategoryidnumbers(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        $course = $this->create_course_with_sections(1);
        $start = time() - DAYSECS;
        $end = time() + DAYSECS;

        $infilter = $this->getDataGenerator()->create_module('assign', [
            'course' => $course->id,
            'section' => 1,
            'allowsubmissionsfromdate' => $start,
            'duedate' => $end,
        ]);
        $othercat = $this->getDataGenerator()->create_module('assign', [
            'course' => $course->id,
            'section' => 1,
            'allowsubmissionsfromdate' => 0,
            'duedate' => 0,
        ]);
        $uncategorised = $this->getDataGenerator()->create_module('assign', [
            'course' => $course->id,
            'section' => 1,
            'allowsubmissionsfromdate' => 0,
            'duedate' => 0,
        ]);
        $second = $this->getDataGenerator()->create_module('assign', [
            'course' => $course->id,
            'section' => 1,
            'allowsubmissionsfromdate' => $start,
            'duedate' => $end,
        ]);

        $catone = $this->create_category_with_idnumber($course, 'CAT1');
        $cattwo = $this->create_category_with_idnumber($course, 'CAT2');
        $this->place_assign_in_category($infilter, (int) $course->id, (int) $catone->id, 1.0);
        $this->place_assign_in_category($othercat, (int) $course->id, (int) $cattwo->id, 1.0);
        $this->place_assign_in_category($second, (int) $course->id, (int) $cattwo->id, 1.0);

        $modinfo = get_fast_modinfo($course);
        $infiltercmid = (int) $modinfo->instances['assign'][$infilter->id]->id;
        $secondcmid = (int) $modinfo->instances['assign'][$second->id]->id;

        $unfiltered = (new section_activity_dates())->evaluate($course, [
            'excludesections' => '0',
            'gradecategoryidnumbers' => '',
        ]);
        $this->assertCount(4, $unfiltered->details);

        $filtered = (new section_activity_dates())->evaluate($course, [
            'excludesections' => '0',
            'gradecategoryidnumbers' => 'CAT1',
        ]);
        $this->assertCount(1, $filtered->details);
        $this->assertSame($infiltercmid, (int) $filtered->details[0]->targetid);
        $this->assertSame(result::STATUS_PASS, $filtered->status);

        $multi = (new section_activity_dates())->evaluate($course, [
            'excludesections' => '0',
            'gradecategoryidnumbers' => 'CAT1, CAT2',
        ]);
        $this->assertCount(3, $multi->details);
        $ids = array_map(static fn($detail) => (int) $detail->targetid, $multi->details);
        $this->assertContains($infiltercmid, $ids);
        $this->assertContains($secondcmid, $ids);
        $this->assertNotContains(
            (int) $modinfo->instances['assign'][$uncategorised->id]->id,
            $ids
        );

        $missing = (new section_activity_dates())->evaluate($course, [
            'excludesections' => '0',
            'gradecategoryidnumbers' => 'DOESNOTEXIST',
        ]);
        $this->assertSame(result::STATUS_NA, $missing->status);
        $this->assertSame([], $missing->details);
    }

    /**
     * A category with a module item passes; missing category or only manuals fail.
     */
    public function test_grade_category_moditems(): void {
        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course();
        $rule = new grade_category_moditems();

        $missing = $rule->evaluate($course, ['idnumber' => 'ACT']);
        $this->assertSame(result::STATUS_FAIL, $missing->status);

        $category = $this->create_category_with_idnumber($course, 'ACT');
        $this->getDataGenerator()->create_grade_item([
            'courseid' => $course->id,
            'categoryid' => $category->id,
            'itemtype' => 'manual',
            'itemname' => 'Manual',
        ]);
        $nomod = $rule->evaluate($course, ['idnumber' => 'ACT']);
        $this->assertSame(result::STATUS_FAIL, $nomod->status);

        $assign = $this->getDataGenerator()->create_module('assign', ['course' => $course->id]);
        $this->place_assign_in_category($assign, (int) $course->id, (int) $category->id, 100);
        $pass = $rule->evaluate($course, ['idnumber' => 'ACT']);
        $this->assertSame(result::STATUS_PASS, $pass->status);
        $this->assertSame(100.0, $pass->compliance);
    }

    /**
     * Weighted mean children must sum to 100 ± tolerance.
     */
    public function test_grade_category_weights(): void {
        $this->resetAfterTest();
        set_config('weighttolerance', '0.01', 'local_bbcotodobien');
        $course = $this->getDataGenerator()->create_course();
        $rule = new grade_category_weights();

        $missing = $rule->evaluate($course, ['idnumber' => 'W']);
        $this->assertSame(result::STATUS_FAIL, $missing->status);

        $mean = $this->create_category_with_idnumber($course, 'MEAN', GRADE_AGGREGATE_MEAN);
        $na = $rule->evaluate($course, ['idnumber' => 'MEAN']);
        $this->assertSame(result::STATUS_NA, $na->status);
        $this->assertSame(100.0, $na->compliance);

        $weighted = $this->create_category_with_idnumber($course, 'W', GRADE_AGGREGATE_WEIGHTED_MEAN);
        $one = $this->getDataGenerator()->create_module('assign', ['course' => $course->id]);
        $two = $this->getDataGenerator()->create_module('assign', ['course' => $course->id]);
        $this->place_assign_in_category($one, (int) $course->id, (int) $weighted->id, 50);
        $this->place_assign_in_category($two, (int) $course->id, (int) $weighted->id, 40);
        $fail = $rule->evaluate($course, ['idnumber' => 'W']);
        $this->assertSame(result::STATUS_FAIL, $fail->status);

        $this->place_assign_in_category($two, (int) $course->id, (int) $weighted->id, 50.005);
        $pass = $rule->evaluate($course, ['idnumber' => 'W']);
        $this->assertSame(result::STATUS_PASS, $pass->status);
        $this->assertSame(100.0, $pass->compliance);
    }

    /**
     * Missing parameters are technical errors.
     */
    public function test_missing_params_are_errors(): void {
        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course();

        $section = (new section_date_label())->evaluate($course, []);
        $this->assertSame(result::STATUS_ERROR, $section->status);

        $grade = (new grade_category_moditems())->evaluate($course, []);
        $this->assertSame(result::STATUS_ERROR, $grade->status);
    }
}
