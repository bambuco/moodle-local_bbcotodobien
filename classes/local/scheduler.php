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
 * Batch runner for scheduled audits and skip policies.
 *
 * @package    local_bbcotodobien
 * @copyright  2026 David Herney @ BambuCo
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class scheduler {
    /**
     * Run every active audit type against the courses in its scope.
     *
     * @return array{ran: int, skipped: int, errors: int}
     */
    public static function run_scheduled_audits(): array {
        global $DB;

        $stats = [
            'ran' => 0,
            'skipped' => 0,
            'errors' => 0,
        ];
        $includehidden = !empty(get_config('local_bbcotodobien', 'includehidden'));

        foreach (audit_type_manager::get_types(true) as $type) {
            $courseids = scope::get_course_ids_for_type($type, !$includehidden);
            if (!$courseids) {
                continue;
            }
            $courses = $DB->get_records_list('course', 'id', $courseids);
            foreach ($courses as $course) {
                if (self::should_skip($course, $type)) {
                    $stats['skipped']++;
                    continue;
                }
                try {
                    engine::run_audit((int) $course->id, (int) $type->id, 0);
                    $stats['ran']++;
                } catch (\Throwable) {
                    $stats['errors']++;
                }
            }
        }

        return $stats;
    }

    /**
     * Whether a scheduled run should skip this course and audit type.
     *
     * @param \stdClass $course Course record
     * @param \stdClass $type Audit type record
     * @return bool
     */
    public static function should_skip(\stdClass $course, \stdClass $type): bool {
        $latest = writer::get_latest_course_audit((int) $course->id, (int) $type->id);
        if (self::should_skip_complete($latest)) {
            return true;
        }
        return self::should_skip_unchanged($course, $latest);
    }

    /**
     * Delete stored history for a course (used when the course is deleted).
     *
     * @param int $courseid Course id
     * @return int Number of course audits deleted
     */
    public static function purge_course(int $courseid): int {
        return writer::delete_audits_for_course($courseid);
    }

    /**
     * Skip when the latest execution of this type is already fully compliant.
     *
     * @param \stdClass|null $latest Latest course audit
     * @return bool
     */
    protected static function should_skip_complete(?\stdClass $latest): bool {
        if (empty(get_config('local_bbcotodobien', 'skipcomplete')) || !$latest) {
            return false;
        }
        return (float) $latest->compliance >= 100.0;
    }

    /**
     * Skip when the course and its latest audit are both older than N days.
     *
     * Never-audited courses are not skipped.
     *
     * @param \stdClass $course Course record
     * @param \stdClass|null $latest Latest course audit
     * @return bool
     */
    protected static function should_skip_unchanged(\stdClass $course, ?\stdClass $latest): bool {
        $days = (int) get_config('local_bbcotodobien', 'skipunchangeddays');
        if ($days <= 0 || !$latest) {
            return false;
        }
        $cutoff = time() - ($days * DAYSECS);
        $coursetime = max((int) $course->timemodified, (int) ($course->timecreated ?? 0));
        $audittime = max((int) $latest->timemodified, (int) $latest->timecreated);
        return $coursetime < $cutoff && $audittime < $cutoff;
    }
}
