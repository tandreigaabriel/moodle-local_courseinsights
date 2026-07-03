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
 * External function: search courses for the autocomplete filter.
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
 * Returns courses matching a query string for the dashboard course filter autocomplete.
 */
class search_courses extends external_api {
    /**
     * Returns the parameter definition for the execute function.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'query' => new external_value(PARAM_TEXT, 'Search query', VALUE_DEFAULT, ''),
        ]);
    }

    /**
     * Searches for courses matching the given query string.
     *
     * @param string $query Search string.
     * @return array
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

        $like = $DB->sql_like('fullname', ':query', false);
        $records = $DB->get_records_sql(
            "SELECT id, fullname
               FROM {course}
              WHERE id <> :siteid
                AND visible = 1
                AND $like
              ORDER BY fullname ASC",
            ['siteid' => SITEID, 'query' => '%' . $DB->sql_like_escape($query) . '%'],
            0,
            30
        );

        $results = [];
        foreach ($records as $record) {
            $results[] = [
                'value' => (int) $record->id,
                'label' => format_string($record->fullname),
            ];
        }
        return $results;
    }

    /**
     * Returns the structure definition for the execute return value.
     *
     * @return external_multiple_structure
     */
    public static function execute_returns(): external_multiple_structure {
        return new external_multiple_structure(
            new external_single_structure([
                'value' => new external_value(PARAM_INT, 'Course ID'),
                'label' => new external_value(PARAM_TEXT, 'Course name'),
            ])
        );
    }
}
