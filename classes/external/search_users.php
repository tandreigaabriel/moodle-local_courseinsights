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
 * External function: search users for the user report autocomplete.
 *
 * @package    local_courseinsights
 * @copyright  2026 Andrei Toma
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_courseinsights\external;

use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_multiple_structure;
use core_external\external_single_structure;
use core_external\external_value;

/**
 * Returns users matching a query string for the user report autocomplete.
 */
class search_users extends external_api {
    /**
     * Returns the parameters definition for execute().
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'query' => new external_value(PARAM_TEXT, 'Search query', VALUE_DEFAULT, ''),
        ]);
    }

    /**
     * Searches users by name, email, or username for the autocomplete field.
     *
     * @param  string $query Search string (minimum 2 characters).
     * @return array         Array of {value, label} objects.
     */
    public static function execute(string $query): array {
        global $DB;

        ['query' => $query] = self::validate_parameters(self::execute_parameters(), ['query' => $query]);

        $context = \core\context\system::instance();
        self::validate_context($context);
        require_capability('local/courseinsights:view', $context);

        $query = trim($query);
        if (strlen($query) < 2) {
            return [];
        }

        $escaped = $DB->sql_like_escape($query);
        $likefn  = $DB->sql_like('u.firstname', ':qfn', false);
        $likeln  = $DB->sql_like('u.lastname', ':qln', false);
        $likeem  = $DB->sql_like('u.email', ':qem', false);
        $likeun  = $DB->sql_like('u.username', ':qun', false);

        $records = $DB->get_records_sql(
            "SELECT u.id, u.firstname, u.lastname, u.username
               FROM {user} u
              WHERE u.deleted = 0
                AND u.confirmed = 1
                AND ($likefn OR $likeln OR $likeem OR $likeun)
              ORDER BY u.lastname ASC, u.firstname ASC",
            [
                'qfn' => '%' . $escaped . '%',
                'qln' => '%' . $escaped . '%',
                'qem' => '%' . $escaped . '%',
                'qun' => '%' . $escaped . '%',
            ],
            0,
            30
        );

        $results = [];
        foreach ($records as $u) {
            $results[] = [
                'value' => (int) $u->id,
                'label' => trim($u->firstname . ' ' . $u->lastname) . ' (' . $u->username . ')',
            ];
        }
        return $results;
    }

    /**
     * Returns the return type definition for execute().
     *
     * @return external_multiple_structure
     */
    public static function execute_returns(): external_multiple_structure {
        return new external_multiple_structure(
            new external_single_structure([
                'value' => new external_value(PARAM_INT, 'User ID'),
                'label' => new external_value(PARAM_TEXT, 'Display name'),
            ])
        );
    }
}
