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
 * Consolidated evaluation result for a rule or an audit.
 *
 * @package    local_bbcotodobien
 * @copyright  2026 David Herney @ BambuCo
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class result {
    /** @var string Target passed the check */
    public const STATUS_PASS = 'pass';

    /** @var string Target failed the check */
    public const STATUS_FAIL = 'fail';

    /** @var string Technical error while evaluating */
    public const STATUS_ERROR = 'error';

    /** @var string Not applicable */
    public const STATUS_NA = 'na';

    /** @var string[] Allowed status values */
    public const STATUSES = [
        self::STATUS_PASS,
        self::STATUS_FAIL,
        self::STATUS_ERROR,
        self::STATUS_NA,
    ];

    /** @var float Compliance percentage 0-100 */
    public float $compliance;

    /** @var string Result status */
    public string $status;

    /** @var detail[] Diagnostic details */
    public array $details;

    /**
     * Create a result.
     *
     * @param float $compliance Compliance percentage 0-100
     * @param string $status One of pass, fail, error, na
     * @param detail[] $details Diagnostic details
     */
    public function __construct(float $compliance, string $status, array $details = []) {
        if (!self::is_valid_status($status)) {
            throw new \coding_exception('Invalid result status: ' . $status);
        }
        $this->compliance = round(max(0, min(100, $compliance)), 2);
        $this->status = $status;
        $this->details = $details;
    }

    /**
     * Whether the given status is allowed.
     *
     * @param string $status Status value
     * @return bool
     */
    public static function is_valid_status(string $status): bool {
        return in_array($status, self::STATUSES, true);
    }

    /**
     * Build a result from per-target details.
     *
     * Applicable items are those whose status is not na. Errors count as not passed.
     *
     * @param detail[] $details Diagnostic details
     * @return result
     */
    public static function from_details(array $details): self {
        $applicable = [];
        $passed = 0;
        $errors = 0;
        foreach ($details as $detail) {
            if ($detail->status === self::STATUS_NA) {
                continue;
            }
            $applicable[] = $detail;
            if ($detail->status === self::STATUS_PASS) {
                $passed++;
            } else if ($detail->status === self::STATUS_ERROR) {
                $errors++;
            }
        }

        if (!$applicable) {
            return new self(100.0, self::STATUS_NA, $details);
        }

        $compliance = round(($passed / count($applicable)) * 100, 2);
        if ($errors > 0) {
            $status = self::STATUS_ERROR;
        } else if ($compliance >= 100.0) {
            $status = self::STATUS_PASS;
        } else {
            $status = self::STATUS_FAIL;
        }

        return new self($compliance, $status, $details);
    }
}
