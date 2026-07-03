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
 * External web service function definitions.
 *
 * @package    local_courseinsights
 * @copyright  2026 Andrei Toma
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$functions = [
    'local_courseinsights_get_courses' => [
        'classname'    => 'local_courseinsights\external\get_courses',
        'description'  => 'Returns course overview data including health scores for external BI tools.',
        'type'         => 'read',
        'capabilities' => 'local/courseinsights:view',
        'ajax'         => true,
    ],
    'local_courseinsights_search_courses' => [
        'classname'    => 'local_courseinsights\external\search_courses',
        'description'  => 'Searches visible courses by name for the dashboard filter autocomplete.',
        'type'         => 'read',
        'capabilities' => 'local/courseinsights:view',
        'ajax'         => true,
        'loginrequired' => true,
    ],
    'local_courseinsights_search_users' => [
        'classname'    => 'local_courseinsights\external\search_users',
        'description'  => 'Searches users by name/email for the user progress report autocomplete.',
        'type'         => 'read',
        'capabilities' => 'local/courseinsights:view',
        'ajax'         => true,
        'loginrequired' => true,
    ],
];

$services = [
    'Course Insights API' => [
        'functions'       => ['local_courseinsights_get_courses'],
        'restrictedusers' => 0,
        'enabled'         => 1,
        'shortname'       => 'courseinsights_api',
    ],
];
