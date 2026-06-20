<?php
// This file is part of Moodle - https://moodle.org/
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
// along with Moodle.  If not, see <https://www.gnu.org/licenses/>.

/**
 * Admin settings page.
 *
 * @package    local_courseinsights
 * @copyright  2026 Andrei Toma
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

if ($hassiteconfig) {
    $ADMIN->add('reports', new admin_externalpage(
        'local_courseinsights',
        get_string('pluginname', 'local_courseinsights'),
        new moodle_url('/local/courseinsights/index.php'),
        'local/courseinsights:view'
    ));

    $settings = new admin_settingpage(
        'local_courseinsights_settings',
        get_string('settingspage', 'local_courseinsights')
    );

    $settings->add(new admin_setting_configtext(
        'local_courseinsights/miniexamkeywords',
        get_string('miniexamkeywords', 'local_courseinsights'),
        get_string('miniexamkeywords_desc', 'local_courseinsights'),
        'mini,mini exam',
        PARAM_TEXT
    ));

    $settings->add(new admin_setting_configtext(
        'local_courseinsights/examkeywords',
        get_string('examkeywords', 'local_courseinsights'),
        get_string('examkeywords_desc', 'local_courseinsights'),
        'exam,final',
        PARAM_TEXT
    ));

    $settings->add(new admin_setting_configtext(
        'local_courseinsights/studentroleids',
        get_string('studentroleids', 'local_courseinsights'),
        get_string('studentroleids_desc', 'local_courseinsights'),
        '5,11,25',
        PARAM_TEXT
    ));

    $settings->add(new admin_setting_configcheckbox(
        'local_courseinsights/enablecache',
        get_string('enablecache', 'local_courseinsights'),
        get_string('enablecache_desc', 'local_courseinsights'),
        0
    ));

    $ADMIN->add('localplugins', $settings);
}
