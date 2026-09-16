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

/**
 * Plugin library callbacks.
 *
 * @package    local_bbcotodobien
 * @copyright  2026 David Herney @ BambuCo
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Add a course administration link to the current audit report.
 *
 * @param navigation_node $navigation Course navigation
 * @param stdClass $course Course
 * @param context $context Course context
 */
function local_bbcotodobien_extend_navigation_course($navigation, $course, $context): void {
    if ((int) $course->id === (int) SITEID) {
        return;
    }
    if (!has_capability('local/bbcotodobien:viewreport', $context)) {
        return;
    }

    $url = new moodle_url('/local/bbcotodobien/view.php', ['id' => $course->id]);
    $node = navigation_node::create(
        get_string('pluginname', 'local_bbcotodobien'),
        $url,
        navigation_node::NODETYPE_LEAF,
        'local_bbcotodobien',
        'local_bbcotodobien',
        new pix_icon('i/report', get_string('pluginname', 'local_bbcotodobien'))
    );
    $navigation->add_node($node);
}

/**
 * Add a site navigation node for the institutional dashboard.
 *
 * @param global_navigation $navigation Site navigation
 */
function local_bbcotodobien_extend_navigation(global_navigation $navigation): void {
    if (!\local_bbcotodobien\local\access::user_can_view_dashboard()) {
        return;
    }
    $navigation->add(
        get_string('dashboard', 'local_bbcotodobien'),
        new moodle_url('/local/bbcotodobien/dashboard.php'),
        navigation_node::TYPE_CUSTOM,
        null,
        'localbbcotodobiendashboard',
        new pix_icon('i/report', '')
    );
}

/**
 * Fragment for rule diagnostic details.
 *
 * @param array $args Fragment arguments
 * @return string
 */
function local_bbcotodobien_output_fragment_rule_detail(array $args): string {
    global $OUTPUT;

    $courseid = (int) ($args['courseid'] ?? 0);
    $ruleconfigid = (int) ($args['ruleconfigid'] ?? 0);
    $auditid = (int) ($args['auditid'] ?? 0);
    $course = get_course($courseid);
    $context = context_course::instance($courseid);
    require_capability('local/bbcotodobien:viewreport', $context);

    $data = \local_bbcotodobien\local\course_report::export_rule_detail($course, $ruleconfigid, $auditid);
    return $OUTPUT->render_from_template('local_bbcotodobien/rule_detail', $data);
}

/**
 * Serve files from the plugin file areas.
 *
 * Files "guidance" are not private and they are served without special capabilities.
 * Files "snapshots" from global context are served with the site:config capability.
 *
 * @param stdClass $course the course object
 * @param stdClass $cm the course module object
 * @param context $context the context
 * @param string $filearea the name of the file area
 * @param array $args extra arguments (itemid, path)
 * @param bool $forcedownload whether or not force download
 * @param array $options additional options affecting the file serving
 * @return bool false if the file cannot be found, just send the file otherwise and do not return
 */
function local_bbcotodobien_pluginfile($course, $cm, $context, $filearea, $args, $forcedownload, array $options = []) {
    $snapshotsarea = \local_bbcotodobien\local\type_matrix_file::FILEAREA;
    if ($filearea !== 'guidance' && $filearea !== $snapshotsarea) {
        return false;
    }

    if ($context->contextlevel !== CONTEXT_SYSTEM) {
        return false;
    }

    require_login(null, false);

    if ($filearea === $snapshotsarea) {
        require_capability('moodle/site:config', $context);
    }

    $itemid = (int)array_shift($args);
    $filename = array_pop($args);
    if ($filename === null || $filename === false) {
        return false;
    }
    $filepath = $args ? '/' . implode('/', $args) . '/' : '/';

    if ($filearea === $snapshotsarea) {
        try {
            \local_bbcotodobien\local\audit_type_manager::require_type($itemid);
        } catch (moodle_exception $e) {
            return false;
        }
    }

    $fs = get_file_storage();
    $file = $fs->get_file($context->id, 'local_bbcotodobien', $filearea, $itemid, $filepath, $filename);
    if (!$file || $file->is_directory()) {
        return false;
    }

    send_stored_file($file, 0, 0, 1, $options);
}
