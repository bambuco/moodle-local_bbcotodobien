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
 * Aggregates rule details and mandatory rule results into compliance percentages.
 *
 * @package    local_bbcotodobien
 * @copyright  2026 David Herney @ BambuCo
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class aggregator {
    /**
     * Aggregate per-target details into a rule result.
     *
     * @param detail[] $details Diagnostic details
     * @return result
     */
    public static function from_details(array $details): result {
        return result::from_details($details);
    }

    /**
     * Build a contribution item for {@see from_rule_results()}.
     *
     * @param result $result Rule result
     * @param bool $mandatory Whether the rule is mandatory
     * @return array
     */
    public static function contribution(result $result, bool $mandatory): array {
        return [
            'result' => $result,
            'mandatory' => $mandatory,
        ];
    }

    /**
     * Average mandatory rule results into an audit-level result.
     *
     * Optional rules and mandatory rules with status na are excluded from N.
     * If N is 0, compliance is 100 and status is na.
     *
     * @param array[] $contributions Each item has keys result (result) and mandatory (bool)
     * @return result
     */
    public static function from_rule_results(array $contributions): result {
        $counted = [];
        foreach ($contributions as $contribution) {
            $ruleresult = $contribution['result'];
            $mandatory = !empty($contribution['mandatory']);
            if (!$mandatory || $ruleresult->status === result::STATUS_NA) {
                continue;
            }
            $counted[] = $ruleresult;
        }

        if (!$counted) {
            return new result(100.0, result::STATUS_NA);
        }

        $sum = 0.0;
        $errors = 0;
        foreach ($counted as $ruleresult) {
            $sum += $ruleresult->compliance;
            if ($ruleresult->status === result::STATUS_ERROR) {
                $errors++;
            }
        }

        $compliance = round($sum / count($counted), 2);
        if ($errors > 0) {
            $status = result::STATUS_ERROR;
        } else if ($compliance >= 100.0) {
            $status = result::STATUS_PASS;
        } else {
            $status = result::STATUS_FAIL;
        }

        return new result($compliance, $status);
    }
}
