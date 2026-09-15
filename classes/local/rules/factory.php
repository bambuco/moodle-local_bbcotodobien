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
 * Factory that discovers and instantiates concrete audit rules.
 *
 * @package    local_bbcotodobien
 * @copyright  2026 David Herney @ BambuCo
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class factory {
    /**
     * Return concrete rule classes keyed by identifier.
     *
     * @return string[] Identifier => fully-qualified class name
     */
    public static function get_rule_classes(): array {
        $classes = \core_component::get_component_classes_in_namespace('local_bbcotodobien', 'local\\rules');
        $rules = [];
        foreach (array_keys($classes) as $classname) {
            if (!self::is_instantiatable_rule($classname)) {
                continue;
            }
            $rules[$classname::get_identifier()] = $classname;
        }
        ksort($rules);
        return $rules;
    }

    /**
     * Return a menu of rule identifiers to default names.
     *
     * @return string[] Identifier => default name
     */
    public static function get_rule_menu(): array {
        $menu = [];
        foreach (self::get_rule_classes() as $identifier => $classname) {
            $menu[$identifier] = $classname::get_name();
        }
        return $menu;
    }

    /**
     * Return a menu of fully-qualified class names to default names.
     *
     * @return string[] Class name => default name
     */
    public static function get_class_menu(): array {
        $menu = [];
        foreach (self::get_rule_classes() as $classname) {
            $menu[$classname] = $classname::get_name();
        }
        return $menu;
    }

    /**
     * Instantiate a rule class.
     *
     * @param string $classname Fully-qualified rule class name
     * @return base
     */
    public static function create(string $classname): base {
        if (!self::is_instantiatable_rule($classname)) {
            throw new \coding_exception('Invalid audit rule class: ' . $classname);
        }
        return new $classname();
    }

    /**
     * Instantiate a rule by its identifier.
     *
     * @param string $identifier Rule identifier
     * @return base
     */
    public static function create_from_identifier(string $identifier): base {
        $classes = self::get_rule_classes();
        if (!isset($classes[$identifier])) {
            throw new \coding_exception('Unknown audit rule identifier: ' . $identifier);
        }
        return self::create($classes[$identifier]);
    }

    /**
     * Whether the class is a concrete subclass of the rule base.
     *
     * @param string $classname Fully-qualified class name
     * @return bool
     */
    public static function is_instantiatable_rule(string $classname): bool {
        if (!class_exists($classname)) {
            return false;
        }
        $reflection = new \ReflectionClass($classname);
        return !$reflection->isAbstract() && $reflection->isSubclassOf(base::class);
    }
}
