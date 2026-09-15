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
 * Capability helpers for course reports and the site dashboard.
 *
 * @package    local_bbcotodobien
 * @copyright  2026 David Herney @ BambuCo
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class access {
    /**
     * Whether the user can view the institutional dashboard.
     *
     * @param int|null $userid User id or null for the current user
     * @return bool
     */
    public static function user_can_view_dashboard(?int $userid = null): bool {
        return self::get_viewable_course_ids($userid, 1) !== [];
    }

    /**
     * Course ids where the user has viewreport.
     *
     * @param int|null $userid User id or null for the current user
     * @param int $limit Maximum courses to return (0 = no limit)
     * @return int[]
     */
    public static function get_viewable_course_ids(?int $userid = null, int $limit = 0): array {
        global $USER;

        $userid = $userid ?? (int) $USER->id;
        if ($userid <= 0 || isguestuser($userid)) {
            return [];
        }
        // Fetch one extra row so excluding the site course does not empty a small limit.
        $fetchlimit = $limit > 0 ? $limit + 1 : 0;
        $courses = get_user_capability_course(
            'local/bbcotodobien:viewreport',
            $userid,
            true,
            '',
            '',
            $fetchlimit
        );
        if (!$courses) {
            return [];
        }
        $ids = [];
        foreach ($courses as $course) {
            $id = (int) $course->id;
            if ($id === (int) SITEID) {
                continue;
            }
            $ids[] = $id;
            if ($limit > 0 && count($ids) >= $limit) {
                break;
            }
        }
        return $ids;
    }
}
