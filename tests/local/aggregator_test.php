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

/**
 * Tests for audit compliance aggregation.
 *
 * @package    local_bbcotodobien
 * @copyright  2026 David Herney @ BambuCo
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
#[\PHPUnit\Framework\Attributes\CoversClass(aggregator::class)]
#[\PHPUnit\Framework\Attributes\CoversClass(result::class)]
final class aggregator_test extends \advanced_testcase {
    /**
     * Create a detail for tests.
     *
     * @param string $status Status value
     * @param int $targetid Target id
     * @param string $targettype Target type
     * @return detail
     */
    private function make_detail(
        string $status,
        int $targetid = 1,
        string $targettype = 'section'
    ): detail {
        return new detail($targettype, $targetid, 'Target ' . $targetid, $status);
    }

    /**
     * Course-level binary pass is 100%.
     */
    public function test_course_binary_pass(): void {
        $result = aggregator::from_details([
            $this->make_detail(result::STATUS_PASS, 10, 'course'),
        ]);
        $this->assertSame(100.0, $result->compliance);
        $this->assertSame(result::STATUS_PASS, $result->status);
    }

    /**
     * Course-level binary fail is 0%.
     */
    public function test_course_binary_fail(): void {
        $result = aggregator::from_details([
            $this->make_detail(result::STATUS_FAIL, 10, 'course'),
        ]);
        $this->assertSame(0.0, $result->compliance);
        $this->assertSame(result::STATUS_FAIL, $result->status);
    }

    /**
     * Section-level percentage uses applicable items only.
     */
    public function test_section_percentage(): void {
        $result = aggregator::from_details([
            $this->make_detail(result::STATUS_PASS, 1),
            $this->make_detail(result::STATUS_PASS, 2),
            $this->make_detail(result::STATUS_FAIL, 3),
        ]);
        $this->assertSame(66.67, $result->compliance);
        $this->assertSame(result::STATUS_FAIL, $result->status);
    }

    /**
     * Resource-level percentage uses the same formula.
     */
    public function test_cm_percentage(): void {
        $result = aggregator::from_details([
            $this->make_detail(result::STATUS_PASS, 1, 'cm'),
            $this->make_detail(result::STATUS_PASS, 2, 'cm'),
            $this->make_detail(result::STATUS_PASS, 3, 'cm'),
            $this->make_detail(result::STATUS_FAIL, 4, 'cm'),
        ]);
        $this->assertSame(75.0, $result->compliance);
        $this->assertSame(result::STATUS_FAIL, $result->status);
    }

    /**
     * Not-applicable details are excluded from the denominator.
     */
    public function test_na_details_are_excluded_from_denominator(): void {
        $result = aggregator::from_details([
            $this->make_detail(result::STATUS_PASS, 1),
            $this->make_detail(result::STATUS_NA, 2),
            $this->make_detail(result::STATUS_FAIL, 3),
        ]);
        $this->assertSame(50.0, $result->compliance);
        $this->assertSame(result::STATUS_FAIL, $result->status);
    }

    /**
     * Empty or all-na details yield 100% with status na.
     */
    public function test_rule_n_zero_is_na(): void {
        $empty = aggregator::from_details([]);
        $this->assertSame(100.0, $empty->compliance);
        $this->assertSame(result::STATUS_NA, $empty->status);

        $allna = aggregator::from_details([
            $this->make_detail(result::STATUS_NA, 1),
            $this->make_detail(result::STATUS_NA, 2),
        ]);
        $this->assertSame(100.0, $allna->compliance);
        $this->assertSame(result::STATUS_NA, $allna->status);
    }

    /**
     * Technical errors count as not passed and set status error.
     */
    public function test_error_status_from_details(): void {
        $result = aggregator::from_details([
            $this->make_detail(result::STATUS_PASS, 1),
            $this->make_detail(result::STATUS_ERROR, 2),
        ]);
        $this->assertSame(50.0, $result->compliance);
        $this->assertSame(result::STATUS_ERROR, $result->status);
    }

    /**
     * Optional rules are excluded from the audit average.
     */
    public function test_audit_ignores_optional_rules(): void {
        $result = aggregator::from_rule_results([
            aggregator::contribution(new result(100.0, result::STATUS_PASS), true),
            aggregator::contribution(new result(50.0, result::STATUS_FAIL), true),
            aggregator::contribution(new result(0.0, result::STATUS_FAIL), false),
        ]);
        $this->assertSame(75.0, $result->compliance);
        $this->assertSame(result::STATUS_FAIL, $result->status);
    }

    /**
     * Mandatory rules with status na are not applicable and drop out of N.
     */
    public function test_audit_n_zero_when_all_optional_or_na(): void {
        $optionalonly = aggregator::from_rule_results([
            aggregator::contribution(new result(0.0, result::STATUS_FAIL), false),
            aggregator::contribution(new result(40.0, result::STATUS_FAIL), false),
        ]);
        $this->assertSame(100.0, $optionalonly->compliance);
        $this->assertSame(result::STATUS_NA, $optionalonly->status);

        $mandatoryna = aggregator::from_rule_results([
            aggregator::contribution(new result(100.0, result::STATUS_NA), true),
            aggregator::contribution(new result(0.0, result::STATUS_FAIL), false),
        ]);
        $this->assertSame(100.0, $mandatoryna->compliance);
        $this->assertSame(result::STATUS_NA, $mandatoryna->status);

        $empty = aggregator::from_rule_results([]);
        $this->assertSame(100.0, $empty->compliance);
        $this->assertSame(result::STATUS_NA, $empty->status);
    }

    /**
     * A mandatory technical error sets the audit status to error.
     */
    public function test_audit_error_status_from_mandatory_rule(): void {
        $result = aggregator::from_rule_results([
            aggregator::contribution(new result(100.0, result::STATUS_PASS), true),
            aggregator::contribution(new result(50.0, result::STATUS_ERROR), true),
        ]);
        $this->assertSame(75.0, $result->compliance);
        $this->assertSame(result::STATUS_ERROR, $result->status);
    }

    /**
     * All mandatory rules at 100% produce a passing audit.
     */
    public function test_audit_all_mandatory_pass(): void {
        $result = aggregator::from_rule_results([
            aggregator::contribution(new result(100.0, result::STATUS_PASS), true),
            aggregator::contribution(new result(100.0, result::STATUS_PASS), true),
        ]);
        $this->assertSame(100.0, $result->compliance);
        $this->assertSame(result::STATUS_PASS, $result->status);
    }
}
