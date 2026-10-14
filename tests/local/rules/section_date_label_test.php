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
 * Tests for named-month date parsing in section_date_label.
 *
 * @package    local_bbcotodobien
 * @copyright  2026 David Herney @ BambuCo
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[\PHPUnit\Framework\Attributes\CoversClass(section_date_label::class)]
final class section_date_label_test extends \advanced_testcase {
    /**
     * Load course libraries used by the section tests.
     */
    public static function setUpBeforeClass(): void {
        global $CFG;
        parent::setUpBeforeClass();
        require_once($CFG->dirroot . '/course/lib.php');
    }

    /**
     * Create a topics course with numbered sections.
     *
     * @param int $numsections Number of sections besides 0
     * @return \stdClass
     */
    private function create_course_with_sections(int $numsections = 1): \stdClass {
        return $this->getDataGenerator()->create_course([
            'numsections' => $numsections,
            'format' => 'topics',
        ]);
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
     * Call the protected is_parseable_date() helper.
     *
     * @param string $try Date candidate
     * @return bool
     */
    private function is_parseable_date(string $try): bool {
        $rule = new section_date_label();
        $method = new \ReflectionMethod($rule, 'is_parseable_date');
        return $method->invoke($rule, $try);
    }

    /**
     * Call the protected get_named_month_map() helper.
     *
     * @return array<string, int>
     */
    private function get_named_month_map(): array {
        $rule = new section_date_label();
        $method = new \ReflectionMethod($rule, 'get_named_month_map');
        return $method->invoke($rule);
    }

    /**
     * Candidate dates and whether they must be accepted.
     *
     * @return array[]
     */
    public static function parseable_date_provider(): array {
        return [
            'english abbreviation slash' => ['14/sep/2026', true],
            'english full slash' => ['14/September/2026', true],
            'english oct abbreviation' => ['01/oct/2026', true],
            'numeric dmy slash' => ['14/09/2026', true],
            'english strtotime words' => ['1 January 2026', true],
            'unknown month' => ['01/xyz/2026', false],
            'invalid day english' => ['32/oct/2026', false],
            'invalid february' => ['29/feb/2026', false],
            'two digit year' => ['01/oct/26', false],
        ];
    }

    /**
     * Named-month and existing formats are accepted or rejected as expected.
     *
     * @param string $try Date candidate
     * @param bool $expected Whether it must parse
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('parseable_date_provider')]
    public function test_is_parseable_date_named_months(string $try, bool $expected): void {
        $this->assertSame($expected, $this->is_parseable_date($try), $try);
    }

    /**
     * Spanish month tokens are accepted when the Spanish pack is installed.
     */
    public function test_is_parseable_date_spanish_when_installed(): void {
        if (!get_string_manager()->translation_exists('es', false)) {
            $this->markTestSkipped('Spanish language pack is not installed');
        }
        $this->assertTrue($this->is_parseable_date('01/oct/2026'));
        $this->assertTrue($this->is_parseable_date('01/octubre/2026'));
        $this->assertTrue($this->is_parseable_date('1/Octubre/2026'));
        $this->assertTrue($this->is_parseable_date('01-octubre-2026'));
        $this->assertTrue($this->is_parseable_date('01/ene/2026'));
    }

    /**
     * The month map always includes English tokens from the language locale.
     */
    public function test_named_month_map_includes_english(): void {
        $map = $this->get_named_month_map();
        $this->assertSame(10, $map['october']);
        $this->assertSame(10, $map['oct']);
        $this->assertSame(9, $map['september']);
        $this->assertSame(9, $map['sep']);
        $this->assertSame(1, $map['january']);
        // ICU may expose "sept" or only "sep" depending on the English locale.
        if (isset($map['sept'])) {
            $this->assertSame(9, $map['sept']);
            $this->assertTrue($this->is_parseable_date('01/sept/2026'));
        }
    }

    /**
     * evaluate() passes when the label is followed by a Spanish named-month date.
     */
    public function test_evaluate_passes_spanish_named_month_date(): void {
        if (!get_string_manager()->translation_exists('es', false)) {
            $this->markTestSkipped('Spanish language pack is not installed');
        }
        $this->resetAfterTest();
        $course = $this->create_course_with_sections(1);
        $this->set_section_summary($course, 1, '<p>Inicio: 01/octubre/2026</p>');

        $result = (new section_date_label())->evaluate($course, [
            'datelabel' => 'Inicio:',
            'excludesections' => '0',
        ]);
        $this->assertSame(result::STATUS_PASS, $result->status);
        $this->assertSame(result::STATUS_PASS, $result->details[0]->status);
        $this->assertSame('rulesectiondatelabel_pass', $result->details[0]->fields['identifier']);
    }

    /**
     * evaluate() fails with nodate when the month token is not recognised.
     */
    public function test_evaluate_fails_unknown_named_month(): void {
        $this->resetAfterTest();
        $course = $this->create_course_with_sections(1);
        $this->set_section_summary($course, 1, '<p>Inicio: 01/xyz/2026</p>');

        $result = (new section_date_label())->evaluate($course, [
            'datelabel' => 'Inicio:',
            'excludesections' => '0',
        ]);
        $this->assertSame(result::STATUS_FAIL, $result->status);
        $this->assertSame('rulesectiondatelabel_fail_nodate', $result->details[0]->fields['identifier']);
    }
}
