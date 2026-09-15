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

namespace local_bbcotodobien\reportbuilder\local\systemreports;

use core_reportbuilder\local\entities\user;
use core_reportbuilder\local\report\action;
use core_reportbuilder\system_report;
use lang_string;
use local_bbcotodobien\reportbuilder\local\entities\course_audit;
use moodle_url;
use pix_icon;

/**
 * Historical course audits for one course.
 *
 * @package    local_bbcotodobien
 * @copyright  2026 David Herney @ BambuCo
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class course_history extends system_report {
    /**
     * Report name.
     *
     * @return string
     */
    public static function get_name(): string {
        return get_string('history', 'local_bbcotodobien');
    }

    /**
     * Initialise the report.
     */
    protected function initialise(): void {
        $entity = new course_audit();
        $alias = $entity->get_table_alias('local_bbcotodobien_course_audit');
        $this->set_main_table('local_bbcotodobien_course_audit', $alias);
        $this->add_entity($entity);

        $this->add_base_fields("{$alias}.id, {$alias}.courseid");
        $this->add_base_condition_simple("{$alias}.courseid", $this->get_parameter('courseid', 0, PARAM_INT));

        $userentity = new user();
        $useralias = $userentity->get_table_alias('user');
        $this->add_entity($userentity
            ->add_join("LEFT JOIN {user} {$useralias} ON {$useralias}.id = {$alias}.userid"));

        $this->add_columns_from_entities([
            'course_audit:audittypename',
            'course_audit:compliance',
            'course_audit:status',
            'course_audit:timemodified',
            'user:fullnamewithlink',
        ]);
        $this->set_initial_sort_column('course_audit:timemodified', SORT_DESC);
        $this->set_downloadable(true, get_string('history', 'local_bbcotodobien'));

        if ($column = $this->get_column('user:fullnamewithlink')) {
            $column->add_callback(static function (string $fullname): string {
                return $fullname !== '' ? $fullname : get_string('scheduledtaskuser', 'local_bbcotodobien');
            });
        }

        $this->add_filters_from_entities([
            'course_audit:audittypename',
            'course_audit:status',
            'course_audit:compliance',
            'course_audit:timemodified',
            'user:fullname',
        ]);

        $this->add_action(new action(
            new moodle_url('/local/bbcotodobien/view.php', [
                'id' => ':courseid',
                'auditid' => ':id',
            ]),
            new pix_icon('t/preview', ''),
            [],
            false,
            new lang_string('viewsnapshot', 'local_bbcotodobien')
        ));
    }

    /**
     * Report access.
     *
     * @return bool
     */
    protected function can_view(): bool {
        return has_capability('local/bbcotodobien:viewreport', $this->get_context());
    }
}
