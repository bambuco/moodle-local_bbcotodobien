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

declare(strict_types=1);

namespace local_bbcotodobien\reportbuilder\local\entities;

use core_reportbuilder\local\entities\base;
use core_reportbuilder\local\filters\date;
use core_reportbuilder\local\filters\number;
use core_reportbuilder\local\filters\select;
use core_reportbuilder\local\filters\text;
use core_reportbuilder\local\helpers\format;
use core_reportbuilder\local\report\column;
use core_reportbuilder\local\report\filter;
use lang_string;
use local_bbcotodobien\local\course_report;
use local_bbcotodobien\local\result;

/**
 * Course audit entity.
 *
 * @package    local_bbcotodobien
 * @copyright  2026 David Herney @ BambuCo
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class course_audit extends base {
    /**
     * The default tables for this entity.
     *
     * @return string[]
     */
    protected function get_default_tables(): array {
        return [
            'local_bbcotodobien_course_audit',
            'local_bbcotodobien_audit_type',
        ];
    }

    /**
     * The default title for this entity.
     *
     * @return lang_string
     */
    protected function get_default_entity_title(): lang_string {
        return new lang_string('entitycourseaudit', 'local_bbcotodobien');
    }

    /**
     * Initialise the entity.
     *
     * @return base
     */
    public function initialise(): base {
        foreach ($this->get_all_columns() as $column) {
            $this->add_column($column);
        }
        foreach ($this->get_all_filters() as $filter) {
            $this->add_filter($filter);
        }
        return $this;
    }

    /**
     * Join the audit type table.
     *
     * @return string
     */
    protected function get_audit_type_join(): string {
        $auditalias = $this->get_table_alias('local_bbcotodobien_course_audit');
        $typealias = $this->get_table_alias('local_bbcotodobien_audit_type');
        return "JOIN {local_bbcotodobien_audit_type} {$typealias} ON {$typealias}.id = {$auditalias}.audittypeid";
    }

    /**
     * Available columns.
     *
     * @return column[]
     */
    protected function get_all_columns(): array {
        $auditalias = $this->get_table_alias('local_bbcotodobien_course_audit');
        $typealias = $this->get_table_alias('local_bbcotodobien_audit_type');
        $columns = [];

        $columns[] = (new column(
            'audittypename',
            new lang_string('audittypename', 'local_bbcotodobien'),
            $this->get_entity_name()
        ))
            ->add_joins($this->get_joins())
            ->add_join($this->get_audit_type_join())
            ->set_type(column::TYPE_TEXT)
            ->set_is_sortable(true)
            ->add_field("{$typealias}.name")
            ->add_callback(static function (?string $name): string {
                return $name === null ? '' : format_string($name);
            });

        $columns[] = (new column(
            'compliance',
            new lang_string('compliance', 'local_bbcotodobien'),
            $this->get_entity_name()
        ))
            ->add_joins($this->get_joins())
            ->set_type(column::TYPE_FLOAT)
            ->set_is_sortable(true)
            ->add_field("{$auditalias}.compliance")
            ->add_callback(static function (?float $value): string {
                return format_float((float) $value, 2) . '%';
            });

        $columns[] = (new column(
            'status',
            new lang_string('status'),
            $this->get_entity_name()
        ))
            ->add_joins($this->get_joins())
            ->set_type(column::TYPE_TEXT)
            ->set_is_sortable(true)
            ->add_field("{$auditalias}.status")
            ->add_callback(static function (?string $status): string {
                return $status ? course_report::get_status_label($status) : '';
            });

        $columns[] = (new column(
            'timemodified',
            new lang_string('lastrun', 'local_bbcotodobien'),
            $this->get_entity_name()
        ))
            ->add_joins($this->get_joins())
            ->set_type(column::TYPE_TIMESTAMP)
            ->set_is_sortable(true)
            ->add_field("{$auditalias}.timemodified")
            ->add_callback([format::class, 'userdate']);

        $columns[] = (new column(
            'timecreated',
            new lang_string('timecreated'),
            $this->get_entity_name()
        ))
            ->add_joins($this->get_joins())
            ->set_type(column::TYPE_TIMESTAMP)
            ->set_is_sortable(true)
            ->add_field("{$auditalias}.timecreated")
            ->add_callback([format::class, 'userdate']);

        return $columns;
    }

    /**
     * Available filters.
     *
     * @return filter[]
     */
    protected function get_all_filters(): array {
        $auditalias = $this->get_table_alias('local_bbcotodobien_course_audit');
        $typealias = $this->get_table_alias('local_bbcotodobien_audit_type');
        $filters = [];

        $filters[] = (new filter(
            text::class,
            'audittypename',
            new lang_string('audittypename', 'local_bbcotodobien'),
            $this->get_entity_name(),
            "{$typealias}.name"
        ))
            ->add_joins($this->get_joins())
            ->add_join($this->get_audit_type_join());

        $filters[] = (new filter(
            number::class,
            'compliance',
            new lang_string('compliance', 'local_bbcotodobien'),
            $this->get_entity_name(),
            "{$auditalias}.compliance"
        ))
            ->add_joins($this->get_joins());

        $filters[] = (new filter(
            select::class,
            'status',
            new lang_string('status'),
            $this->get_entity_name(),
            "{$auditalias}.status"
        ))
            ->add_joins($this->get_joins())
            ->set_options([
                result::STATUS_PASS => get_string('statuspass', 'local_bbcotodobien'),
                result::STATUS_FAIL => get_string('statusfail', 'local_bbcotodobien'),
                result::STATUS_ERROR => get_string('statuserror', 'local_bbcotodobien'),
                result::STATUS_NA => get_string('statusna', 'local_bbcotodobien'),
            ]);

        $filters[] = (new filter(
            date::class,
            'timemodified',
            new lang_string('lastrun', 'local_bbcotodobien'),
            $this->get_entity_name(),
            "{$auditalias}.timemodified"
        ))
            ->add_joins($this->get_joins());

        return $filters;
    }
}
