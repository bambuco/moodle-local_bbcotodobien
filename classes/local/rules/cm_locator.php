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

/**
 * Locates course modules by module name and idnumber.
 *
 * @package    local_bbcotodobien
 * @copyright  2026 David Herney @ BambuCo
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class cm_locator {
    /**
     * Return course modules of a type with the given idnumber, including hidden ones.
     *
     * @param int $courseid Course id
     * @param string $modname Module name (for example page, forum)
     * @param string $idnumber Course module idnumber
     * @return \stdClass[] Records with id, instance, course, idnumber, name
     */
    public static function find(int $courseid, string $modname, string $idnumber): array {
        global $DB;

        $sql = "SELECT cm.id, cm.instance, cm.course, cm.idnumber
                  FROM {course_modules} cm
                  JOIN {modules} m ON m.id = cm.module
                 WHERE cm.course = :courseid
                   AND m.name = :modname
                   AND cm.idnumber = :idnumber
                   AND cm.deletioninprogress = 0
              ORDER BY cm.id ASC";
        $cms = $DB->get_records_sql($sql, [
            'courseid' => $courseid,
            'modname' => $modname,
            'idnumber' => $idnumber,
        ]);

        foreach ($cms as $cm) {
            $instance = $DB->get_record($modname, ['id' => $cm->instance]);
            $cm->name = $instance->name ?? (string) $cm->id;
            $cm->instanceloaded = (bool) $instance;
            $cm->instancerecord = $instance ?: null;
        }

        return array_values($cms);
    }

    /**
     * Whether the module instance table exists.
     *
     * @param string $modname Module name
     * @return bool
     */
    public static function table_exists(string $modname): bool {
        global $DB;

        if ($modname === '' || !preg_match('/^[a-z][a-z0-9_]*$/', $modname)) {
            return false;
        }
        return $DB->get_manager()->table_exists($modname);
    }

    /**
     * Whether a column exists on the module instance table.
     *
     * @param string $modname Module name
     * @param string $field Column name
     * @return bool
     */
    public static function field_exists(string $modname, string $field): bool {
        global $DB;

        if (!self::table_exists($modname) || $field === '') {
            return false;
        }
        $columns = $DB->get_columns($modname);
        return isset($columns[$field]);
    }
}
