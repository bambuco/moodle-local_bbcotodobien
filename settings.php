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
 * Plugin administration settings.
 *
 * @package    local_bbcotodobien
 * @copyright  2026 David Herney @ BambuCo
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

if ($hassiteconfig) {
    $ADMIN->add('localplugins', new admin_category(
        'local_bbcotodobien_folder',
        new lang_string('pluginname', 'local_bbcotodobien')
    ));

    $settings = new admin_settingpage(
        'local_bbcotodobien',
        new lang_string('settings')
    );
    $ADMIN->add('local_bbcotodobien_folder', $settings);

    $settings->add(new admin_setting_configcheckbox(
        'local_bbcotodobien/includehidden',
        new lang_string('includehidden', 'local_bbcotodobien'),
        new lang_string('includehidden_desc', 'local_bbcotodobien'),
        0
    ));

    $settings->add(new admin_setting_configtext(
        'local_bbcotodobien/skipunchangeddays',
        new lang_string('skipunchangeddays', 'local_bbcotodobien'),
        new lang_string('skipunchangeddays_desc', 'local_bbcotodobien'),
        0,
        PARAM_INT
    ));

    $settings->add(new admin_setting_configcheckbox(
        'local_bbcotodobien/skipcomplete',
        new lang_string('skipcomplete', 'local_bbcotodobien'),
        new lang_string('skipcomplete_desc', 'local_bbcotodobien'),
        0
    ));

    $settings->add(new admin_setting_configtext(
        'local_bbcotodobien/retentiondays',
        new lang_string('retentiondays', 'local_bbcotodobien'),
        new lang_string('retentiondays_desc', 'local_bbcotodobien'),
        0,
        PARAM_INT
    ));

    $settings->add(new admin_setting_configtext(
        'local_bbcotodobien/maxhistorypercourse',
        new lang_string('maxhistorypercourse', 'local_bbcotodobien'),
        new lang_string('maxhistorypercourse_desc', 'local_bbcotodobien'),
        0,
        PARAM_INT
    ));

    $settings->add(new admin_setting_configtext(
        'local_bbcotodobien/weighttolerance',
        new lang_string('weighttolerance', 'local_bbcotodobien'),
        new lang_string('weighttolerance_desc', 'local_bbcotodobien'),
        '0.01',
        PARAM_FLOAT
    ));

    $ADMIN->add('local_bbcotodobien_folder', new admin_externalpage(
        'local_bbcotodobien_manage',
        new lang_string('manageaudittypes', 'local_bbcotodobien'),
        new moodle_url('/local/bbcotodobien/manage.php')
    ));

    $ADMIN->add('local_bbcotodobien_folder', new admin_externalpage(
        'local_bbcotodobien_dashboard',
        new lang_string('dashboard', 'local_bbcotodobien'),
        new moodle_url('/local/bbcotodobien/dashboard.php')
    ));
}
