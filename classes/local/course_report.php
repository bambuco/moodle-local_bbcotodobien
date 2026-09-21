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

use local_bbcotodobien\local\rules\base;
use local_bbcotodobien\local\rules\factory;

/**
 * Builds course report and header-alert view data.
 *
 * @package    local_bbcotodobien
 * @copyright  2026 David Herney @ BambuCo
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class course_report {
    /**
     * Human-readable status label.
     *
     * @param string $status Result status
     * @return string
     */
    public static function get_status_label(string $status): string {
        $key = 'status' . $status;
        if (!get_string_manager()->string_exists($key, 'local_bbcotodobien')) {
            return $status;
        }
        return get_string($key, 'local_bbcotodobien');
    }

    /**
     * Pix icon name for a status.
     *
     * @param string $status Result status
     * @return string
     */
    public static function get_status_icon(string $status): string {
        return match ($status) {
            result::STATUS_PASS => 'i/checkedcircle',
            result::STATUS_FAIL => 'i/incorrect',
            result::STATUS_ERROR => 'i/warning',
            default => 'i/completion-auto-n',
        };
    }

    /**
     * Header alert payload, or null when nothing should be shown.
     *
     * @param \stdClass $course Course record
     * @return array|null
     */
    public static function get_header_alert(\stdClass $course): ?array {
        $types = scope::get_audit_types_for_course((int) $course->id, true);
        if (!$types) {
            return null;
        }

        $minimum = 100.0;
        foreach ($types as $type) {
            $audit = writer::get_latest_course_audit((int) $course->id, (int) $type->id);
            $compliance = $audit ? (float) $audit->compliance : 0.0;
            $minimum = min($minimum, $compliance);
        }
        if ($minimum >= 100.0) {
            return null;
        }

        $url = new \moodle_url('/local/bbcotodobien/view.php', ['id' => $course->id]);
        return [
            'percent' => format_float($minimum, 2),
            'courseurl' => $url->out(false),
            'coursename' => format_string($course->fullname),
            'level' => $minimum < 75.0 ? 'danger' : ($minimum < 90.0 ? 'warning' : 'success'),
        ];
    }

    /**
     * Template data for the current course report.
     *
     * @param \stdClass $course Course record
     * @param \renderer_base $output Renderer
     * @param bool $canexecute Whether the user can re-run audits
     * @param int $snapshotauditid Optional historical course audit id
     * @return array
     */
    public static function export_current(
        \stdClass $course,
        \renderer_base $output,
        bool $canexecute,
        int $snapshotauditid = 0
    ): array {
        $snapshot = null;
        if ($snapshotauditid) {
            $snapshot = writer::require_course_audit($snapshotauditid);
            if ((int) $snapshot->courseid !== (int) $course->id) {
                throw new \moodle_exception('errorinvalidcourseaudit', 'local_bbcotodobien');
            }
        }

        $types = scope::get_audit_types_for_course((int) $course->id, true);
        $typedata = [];
        foreach ($types as $type) {
            if ($snapshot && (int) $snapshot->audittypeid !== (int) $type->id) {
                continue;
            }
            $audit = $snapshot ?: writer::get_latest_course_audit((int) $course->id, (int) $type->id);
            $typedata[] = self::export_type($course, $type, $audit, $output, $canexecute && !$snapshot);
        }

        $multipletypes = count($typedata) > 1;
        foreach (array_keys($typedata) as $index) {
            $typedata[$index]['multipletypes'] = $multipletypes;
        }

        $rerunurl = new \moodle_url('/local/bbcotodobien/view.php', [
            'id' => $course->id,
            'rerun' => 1,
            'sesskey' => sesskey(),
        ]);

        return [
            'courseid' => (int) $course->id,
            'contextid' => \context_course::instance((int) $course->id)->id,
            'hastypes' => $typedata !== [],
            'multipletypes' => count($typedata) > 1,
            'types' => $typedata,
            'canexecute' => $canexecute && !$snapshot,
            'snapshot' => $snapshot !== null,
            'rerunurl' => $rerunurl->out(false),
            'dashboardurl' => (new \moodle_url('/local/bbcotodobien/dashboard.php'))->out(false),
        ];
    }

    /**
     * Template data for one rule's diagnostic details.
     *
     * @param \stdClass $course Course record
     * @param int $ruleconfigid Rule configuration id
     * @param int $auditid Optional specific course audit
     * @return array
     */
    public static function export_rule_detail(\stdClass $course, int $ruleconfigid, int $auditid = 0): array {
        $config = audit_type_manager::get_rule_config($ruleconfigid);
        $type = audit_type_manager::get_type((int) $config->audittypeid);
        if (!scope::type_applies_to_course($type->categories, $course)) {
            throw new \moodle_exception('erroraudittypenotapplicable', 'local_bbcotodobien');
        }

        $audit = $auditid
            ? writer::require_course_audit($auditid)
            : writer::get_latest_course_audit((int) $course->id, (int) $config->audittypeid);
        if ($audit && (int) $audit->courseid !== (int) $course->id) {
            throw new \moodle_exception('errorinvalidcourseaudit', 'local_bbcotodobien');
        }

        $results = $audit ? writer::get_rule_results((int) $audit->id, $ruleconfigid) : [];
        $details = [];
        if ($results) {
            $rule = factory::is_instantiatable_rule($config->ruleclass)
                ? factory::create($config->ruleclass)
                : null;
            foreach ($results as $stored) {
                $rows = writer::get_rule_details((int) $stored->id);
                $failinggroups = self::build_failing_groups($course, $rule, $rows);
                $details[] = [
                    'collapseid' => 'local-bbcotodobien-detail-' . $stored->id,
                    'evaluatedat' => userdate((int) $stored->timecreated),
                    'status' => $stored->status,
                    'statusclass' => 'local-bbcotodobien-status-' . $stored->status,
                    'statuslabel' => self::get_status_label($stored->status),
                    'details' => self::render_execution_messages($rule, $rows, $failinggroups !== []),
                    'expanded' => $details === [],
                    'hasfailinggroups' => $failinggroups !== [],
                    'failinggroups' => $failinggroups,
                ];
            }
        }

        return [
            'rulename' => format_string($config->name),
            'hasdetails' => $details !== [],
            'details' => $details,
        ];
    }

    /**
     * Unique formative messages for one evaluation.
     *
     * Listed section, activity and gradebook targets that passed are omitted when
     * the execution already has a failing list. Course-level "not found" messages
     * are kept so the accordion body is not empty.
     *
     * @param base|null $rule Rule instance, or null when the class is unavailable
     * @param \stdClass[] $rows Stored rule_detail rows for one evaluation
     * @param bool $hasfailinggroups Whether failing targets are listed separately
     * @return string HTML
     */
    protected static function render_execution_messages(?base $rule, array $rows, bool $hasfailinggroups): string {
        $htmlparts = [];
        $seen = [];
        foreach ($rows as $row) {
            $listed = self::is_listable_fail_target($row->targettype);
            if (
                $listed
                && $hasfailinggroups
                && $row->status !== result::STATUS_FAIL
                && $row->status !== result::STATUS_ERROR
            ) {
                continue;
            }

            $detail = detail::from_storage(
                $row->targettype,
                (int) $row->targetid,
                $row->targetname,
                $row->status,
                (string) $row->details
            );
            $identifier = (string) ($detail->fields['identifier'] ?? '');
            $key = $identifier !== '' ? $identifier : 'row-' . $row->id;
            if (isset($seen[$key])) {
                continue;
            }
            $seen[$key] = true;
            $htmlparts[] = $rule ? $rule->render_detail($detail) : base::format_detail($detail);
        }
        return implode('<br />', $htmlparts);
    }

    /**
     * Whether failing targets of this type are listed with optional URLs.
     *
     * @param string $targettype Target type
     * @return bool
     */
    protected static function is_listable_fail_target(string $targettype): bool {
        return $targettype === base::GRANULARITY_SECTION
            || $targettype === base::GRANULARITY_CM
            || $targettype === base::GRANULARITY_GRADEBOOK;
    }

    /**
     * Group failing found resources for the list shown in the accordion body.
     *
     * Course-level "not found" details are omitted: there is no resource to link.
     * Gradebook details are included only when the category still exists.
     *
     * @param \stdClass $course Course record
     * @param base|null $rule Rule instance, or null when the class is unavailable
     * @param \stdClass[] $rows Stored rule_detail rows for one evaluation
     * @return array[]
     */
    protected static function build_failing_groups(\stdClass $course, ?base $rule, array $rows): array {
        $groups = [];
        foreach ($rows as $row) {
            if ($row->status !== result::STATUS_FAIL) {
                continue;
            }
            if (!self::is_listable_fail_target($row->targettype)) {
                continue;
            }

            $detail = detail::from_storage(
                $row->targettype,
                (int) $row->targetid,
                $row->targetname,
                $row->status,
                (string) $row->details
            );
            $category = $row->targettype === base::GRANULARITY_GRADEBOOK
                ? self::find_grade_category($course, (string) ($detail->fields['idnumber'] ?? ''))
                : null;
            $url = self::get_target_url($course, $row->targettype, (int) $row->targetid, $detail, $category);
            if ($row->targettype === base::GRANULARITY_GRADEBOOK && $url === null) {
                continue;
            }

            $identifier = (string) ($detail->fields['identifier'] ?? '');
            $groupkey = $identifier !== '' ? $identifier : $row->targettype;
            if (!isset($groups[$groupkey])) {
                $intro = $rule
                    ? $rule->render_failing_list_intro($detail)
                    : self::failing_list_intro_fallback($detail);
                $groups[$groupkey] = [
                    'intro' => $intro,
                    'items' => [],
                ];
            }

            $name = $category ? $category->fullname : $row->targetname;
            $groups[$groupkey]['items'][] = [
                'name' => format_string($name),
                'url' => $url ? $url->out(false) : '',
                'hasurl' => $url !== null,
            ];
        }

        return array_values($groups);
    }

    /**
     * Generic list intro when the rule class is unavailable.
     *
     * @param detail $detail Sample failing detail
     * @return string
     */
    protected static function failing_list_intro_fallback(detail $detail): string {
        return match ($detail->targettype) {
            base::GRANULARITY_SECTION => get_string('rulefailingsections_list', 'local_bbcotodobien'),
            base::GRANULARITY_GRADEBOOK => get_string('rulefailinggradebook_list', 'local_bbcotodobien'),
            default => get_string('rulefailingactivities_list', 'local_bbcotodobien'),
        };
    }

    /**
     * Resolve a live URL for a found section, activity or grade category.
     *
     * Course targets always return null: they represent a missing resource.
     *
     * @param \stdClass $course Course record
     * @param string $targettype Target type
     * @param int $targetid Target id
     * @param detail|null $detail Stored detail, used for gradebook idnumber
     * @param \grade_category|null $category Already resolved grade category
     * @return \moodle_url|null
     */
    protected static function get_target_url(
        \stdClass $course,
        string $targettype,
        int $targetid,
        ?detail $detail = null,
        ?\grade_category $category = null
    ): ?\moodle_url {
        global $CFG;

        if ($targettype === base::GRANULARITY_COURSE) {
            return null;
        }

        if ($targettype === base::GRANULARITY_GRADEBOOK) {
            if (!$category && $detail) {
                $category = self::find_grade_category($course, (string) ($detail->fields['idnumber'] ?? ''));
            }
            if (!$category) {
                return null;
            }
            return new \moodle_url('/grade/edit/tree/index.php', [
                'id' => (int) $course->id,
            ]);
        }

        require_once($CFG->dirroot . '/course/lib.php');

        if ($targetid <= 0) {
            return null;
        }

        try {
            $modinfo = get_fast_modinfo($course);
        } catch (\Throwable) {
            return null;
        }

        if ($targettype === base::GRANULARITY_SECTION) {
            $section = $modinfo->get_section_info_by_id($targetid, IGNORE_MISSING);
            if (!$section) {
                return null;
            }
            return course_get_url($course, $section, ['navigation' => true]);
        }

        if ($targettype === base::GRANULARITY_CM) {
            if (!isset($modinfo->cms[$targetid])) {
                return null;
            }
            $cm = $modinfo->cms[$targetid];
            if (!empty($cm->deletioninprogress) || empty($cm->url)) {
                return null;
            }
            return $cm->url;
        }

        return null;
    }

    /**
     * Fetch a grade category by the idnumber stored on its category grade item.
     *
     * @param \stdClass $course Course record
     * @param string $idnumber Category idnumber
     * @return \grade_category|null
     */
    protected static function find_grade_category(\stdClass $course, string $idnumber): ?\grade_category {
        global $CFG, $DB;

        $idnumber = trim($idnumber);
        if ($idnumber === '') {
            return null;
        }

        require_once($CFG->libdir . '/gradelib.php');
        $items = $DB->get_records(
            'grade_items',
            [
                'courseid' => (int) $course->id,
                'itemtype' => 'category',
                'idnumber' => $idnumber,
            ],
            'id ASC',
            '*',
            0,
            1
        );
        $item = $items ? reset($items) : false;
        if (!$item) {
            return null;
        }

        $category = \grade_category::fetch(['id' => $item->iteminstance, 'courseid' => (int) $course->id]);
        return $category ?: null;
    }

    /**
     * Re-run every applicable audit type on a course.
     *
     * @param \stdClass $course Course record
     * @param int $userid Runner userid
     */
    public static function rerun_all(\stdClass $course, int $userid): void {
        foreach (scope::get_audit_types_for_course((int) $course->id, true) as $type) {
            engine::run_audit((int) $course->id, (int) $type->id, $userid);
        }
    }

    /**
     * Export one audit type panel.
     *
     * @param \stdClass $course Course record
     * @param \stdClass $type Audit type
     * @param \stdClass|null $audit Latest or snapshot audit
     * @param \renderer_base $output Renderer
     * @param bool $canexecute Whether re-evaluate controls are shown
     * @return array
     */
    protected static function export_type(
        \stdClass $course,
        \stdClass $type,
        ?\stdClass $audit,
        \renderer_base $output,
        bool $canexecute
    ): array {
        $status = $audit->status ?? result::STATUS_NA;
        $compliance = $audit ? (float) $audit->compliance : 0.0;
        $rules = [];
        foreach (audit_type_manager::get_rule_configs((int) $type->id, true) as $config) {
            $result = $audit ? writer::get_latest_rule_result((int) $audit->id, (int) $config->id) : null;
            $detailcount = $result ? count(writer::get_rule_details((int) $result->id)) : 0;
            $rulestatus = $result->status ?? result::STATUS_NA;
            $guidance = self::format_guidance($config);
            $rules[] = [
                'id' => (int) $config->id,
                'name' => format_string($config->name),
                'mandatory' => !empty($config->mandatory),
                'weighting' => !empty($config->mandatory)
                    ? get_string('mandatory', 'local_bbcotodobien')
                    : get_string('optional', 'local_bbcotodobien'),
                'compliance' => $result ? format_float((float) $result->compliance, 2) : '-',
                'status' => $rulestatus,
                'statuslabel' => self::get_status_label($rulestatus),
                'statusicon' => $output->pix_icon(
                    self::get_status_icon($rulestatus),
                    self::get_status_label($rulestatus)
                ),
                'hasguidance' => $guidance !== '',
                'guidance' => $guidance,
                'hasdetails' => $detailcount > 0,
                'canexecute' => $canexecute,
                'auditid' => $audit ? (int) $audit->id : 0,
            ];
        }

        $lastrun = $audit
            ? userdate((int) $audit->timemodified)
            : get_string('neverrun', 'local_bbcotodobien');

        return [
            'id' => (int) $type->id,
            'name' => format_string($type->name),
            'compliance' => format_float($compliance, 2),
            'status' => $status,
            'statuslabel' => self::get_status_label($status),
            'statusicon' => $output->pix_icon(self::get_status_icon($status), self::get_status_label($status)),
            'lastrun' => $lastrun,
            'rules' => $rules,
            'hasrules' => $rules !== [],
        ];
    }

    /**
     * Format stored guidance HTML.
     *
     * @param \stdClass $config Rule configuration
     * @return string
     */
    protected static function format_guidance(\stdClass $config): string {
        $raw = trim((string) $config->guidance);
        if ($raw === '') {
            return '';
        }
        $syscontext = \context_system::instance();
        $rewritten = file_rewrite_pluginfile_urls(
            $raw,
            'pluginfile.php',
            $syscontext->id,
            'local_bbcotodobien',
            'guidance',
            $config->id
        );
        return format_text($rewritten, (int) $config->guidanceformat, [
            'context' => $syscontext,
            'filter' => false,
        ]);
    }
}
