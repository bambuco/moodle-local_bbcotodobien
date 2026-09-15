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
 * Resolves which audit types apply to a course via category cascade.
 *
 * An empty categories CSV means the type applies to the whole site.
 * A type assigned to a parent category applies to courses in all descendant categories.
 *
 * @package    local_bbcotodobien
 * @copyright  2026 David Herney @ BambuCo
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class scope {
    /**
     * Parse a CSV list of category IDs.
     *
     * @param string $categories Comma-separated category IDs
     * @return int[]
     */
    public static function parse_category_ids(string $categories): array {
        $ids = [];
        foreach (explode(',', $categories) as $value) {
            $id = (int) trim($value);
            if ($id > 0) {
                $ids[$id] = $id;
            }
        }
        return array_values($ids);
    }

    /**
     * Normalise category IDs to a CSV string.
     *
     * @param array|string $categories Category IDs or CSV
     * @return string
     */
    public static function format_categories(array|string $categories): string {
        if (is_string($categories)) {
            $ids = self::parse_category_ids($categories);
        } else {
            $ids = [];
            foreach ($categories as $value) {
                $id = (int) $value;
                if ($id > 0) {
                    $ids[$id] = $id;
                }
            }
            $ids = array_values($ids);
        }
        return implode(',', $ids);
    }

    /**
     * Format stored categories for display.
     *
     * @param string $categoriescsv Stored CSV
     * @return string
     */
    public static function format_categories_for_display(string $categoriescsv): string {
        $ids = self::parse_category_ids($categoriescsv);
        if (!$ids) {
            return get_string('allcategories', 'local_bbcotodobien');
        }
        $names = [];
        foreach ($ids as $id) {
            $category = \core_course_category::get($id, IGNORE_MISSING);
            if ($category) {
                $names[] = $category->get_formatted_name();
            } else {
                $names[] = get_string('unknowncategory', 'local_bbcotodobien', $id);
            }
        }
        return implode(', ', $names);
    }

    /**
     * Return the category id and all ancestor ids.
     *
     * @param int $categoryid Course category id
     * @return int[]
     */
    public static function get_category_chain(int $categoryid): array {
        $category = \core_course_category::get($categoryid, IGNORE_MISSING);
        if (!$category) {
            return [];
        }
        $chain = array_map('intval', $category->get_parents());
        $chain[] = (int) $category->id;
        return $chain;
    }

    /**
     * Return the category id and all descendant ids.
     *
     * @param int $categoryid Course category id
     * @return int[]
     */
    public static function get_category_tree_ids(int $categoryid): array {
        $category = \core_course_category::get($categoryid, IGNORE_MISSING);
        if (!$category) {
            return [];
        }
        $ids = [(int) $category->id];
        foreach ($category->get_all_children_ids() as $childid) {
            $ids[] = (int) $childid;
        }
        return $ids;
    }

    /**
     * Whether an audit type applies to a course.
     *
     * @param string $categoriescsv Stored categories CSV; empty means all courses
     * @param \stdClass $course Course record with id and category
     * @return bool
     */
    public static function type_applies_to_course(string $categoriescsv, \stdClass $course): bool {
        if ((int) $course->id === (int) SITEID) {
            return false;
        }
        $configured = self::parse_category_ids($categoriescsv);
        if (!$configured) {
            return true;
        }
        $chain = self::get_category_chain((int) $course->category);
        return (bool) array_intersect($configured, $chain);
    }

    /**
     * Return active (or all) audit types that apply to a course.
     *
     * @param int $courseid Course id
     * @param bool $activeonly Only return active types
     * @return \stdClass[]
     */
    public static function get_audit_types_for_course(int $courseid, bool $activeonly = true): array {
        global $DB;

        $course = $DB->get_record('course', ['id' => $courseid], '*', MUST_EXIST);
        $types = $DB->get_records('local_bbcotodobien_audit_type', $activeonly ? ['active' => 1] : [], 'id ASC');
        $applicable = [];
        foreach ($types as $type) {
            if (self::type_applies_to_course($type->categories, $course)) {
                $applicable[$type->id] = $type;
            }
        }
        return $applicable;
    }

    /**
     * Return course IDs covered by an audit type.
     *
     * @param \stdClass $type Audit type record
     * @param bool $onlyvisible Exclude hidden courses
     * @return int[]
     */
    public static function get_course_ids_for_type(\stdClass $type, bool $onlyvisible = false): array {
        global $DB;

        $conditions = 'id <> :siteid';
        $params = ['siteid' => (int) SITEID];
        if ($onlyvisible) {
            $conditions .= ' AND visible = 1';
        }

        $configured = self::parse_category_ids($type->categories);
        if ($configured) {
            $categoryids = [];
            foreach ($configured as $categoryid) {
                foreach (self::get_category_tree_ids($categoryid) as $id) {
                    $categoryids[$id] = $id;
                }
            }
            if (!$categoryids) {
                return [];
            }
            [$insql, $inparams] = $DB->get_in_or_equal($categoryids, SQL_PARAMS_NAMED);
            $conditions .= " AND category $insql";
            $params = array_merge($params, $inparams);
        }

        return $DB->get_fieldset_select('course', 'id', $conditions, $params);
    }
}
