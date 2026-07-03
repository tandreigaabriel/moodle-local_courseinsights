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
 * External function: get course overview data.
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
 * Returns course overview data for external BI tools.
 */
class get_courses extends external_api {
    /**
     * Describes the function parameters.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'categoryid'    => new external_value(
                PARAM_INT,
                'Category ID (0 = all categories)',
                VALUE_DEFAULT,
                0
            ),
            'cohortid'      => new external_value(
                PARAM_INT,
                'Cohort ID (0 = all cohorts)',
                VALUE_DEFAULT,
                0
            ),
            'activitytype'  => new external_value(
                PARAM_ALPHA,
                'Activity type: all, assign, quiz, exam, mini',
                VALUE_DEFAULT,
                'all'
            ),
            'studentstatus' => new external_value(
                PARAM_ALPHA,
                'Student status: all, active, suspended',
                VALUE_DEFAULT,
                'active'
            ),
            'startdate'     => new external_value(
                PARAM_TEXT,
                'Start date filter in YYYY-MM-DD format, empty string for none',
                VALUE_DEFAULT,
                ''
            ),
            'enddate'       => new external_value(
                PARAM_TEXT,
                'End date filter in YYYY-MM-DD format, empty string for none',
                VALUE_DEFAULT,
                ''
            ),
        ]);
    }

    /**
     * Returns course overview data.
     *
     * @param int    $categoryid    Category filter (0 = all).
     * @param int    $cohortid      Cohort filter (0 = all).
     * @param string $activitytype  Activity type filter.
     * @param string $studentstatus Student status filter.
     * @param string $startdate     Start date (YYYY-MM-DD) or empty.
     * @param string $enddate       End date (YYYY-MM-DD) or empty.
     * @return array
     */
    public static function execute(
        int $categoryid = 0,
        int $cohortid = 0,
        string $activitytype = 'all',
        string $studentstatus = 'active',
        string $startdate = '',
        string $enddate = ''
    ): array {
        $context = \core\context\system::instance();
        self::validate_context($context);
        require_capability('local/courseinsights:view', $context);

        $params = self::validate_parameters(self::execute_parameters(), [
            'categoryid'    => $categoryid,
            'cohortid'      => $cohortid,
            'activitytype'  => $activitytype,
            'studentstatus' => $studentstatus,
            'startdate'     => $startdate,
            'enddate'       => $enddate,
        ]);

        $filters = [
            'categoryid'    => (int) $params['categoryid'],
            'cohortid'      => (int) $params['cohortid'],
            'activitytype'  => $params['activitytype'],
            'studentstatus' => $params['studentstatus'],
            'startdate'     => $params['startdate'],
            'enddate'       => $params['enddate'],
            'courseid'      => 0,
            'usecache'      => 0,
            'sortby'        => 'course',
            'sortdir'       => 'asc',
        ];

        $records = \local_courseinsights\report_service::get_course_overview($filters, 0, 0);

        $now        = time();
        $thirtydays = 30 * DAYSECS;
        $result     = [];

        foreach ($records as $record) {
            $health       = \local_courseinsights\report_service::calculate_health_score($record);
            $lastactivity = !empty($record->lastactivity) ? (int) $record->lastactivity : 0;

            $result[] = [
                'courseid'             => (int) $record->id,
                'coursename'           => format_string($record->fullname),
                'isactive'             => $lastactivity > 0 && ($now - $lastactivity) < $thirtydays,
                'enrolledstudents'     => (int) ($record->enrolledstudents ?? 0),
                'completionrate'       => (float) ($record->completionrate ?? 0),
                'assignments'          => (int) ($record->assignments ?? 0),
                'submittedassignments' => (int) ($record->submittedassignments ?? 0),
                'quizzes'              => (int) ($record->quizzes ?? 0),
                'quizattempts'         => (int) ($record->quizattempts ?? 0),
                'avgquizgrade'         => (float) ($record->avgquizgrade ?? 0),
                'healthscore'          => (int) $health['healthscore'],
                'healthgrade'          => $health['healthgrade'],
                'lastactivity'         => $lastactivity,
            ];
        }

        return $result;
    }

    /**
     * Describes the function return values.
     *
     * @return external_multiple_structure
     */
    public static function execute_returns(): external_multiple_structure {
        return new external_multiple_structure(
            new external_single_structure([
                'courseid'             => new external_value(PARAM_INT, 'Course ID'),
                'coursename'           => new external_value(PARAM_TEXT, 'Course full name'),
                'isactive'             => new external_value(PARAM_BOOL, 'True if last activity was within 30 days'),
                'enrolledstudents'     => new external_value(PARAM_INT, 'Enrolled active students'),
                'completionrate'       => new external_value(PARAM_FLOAT, 'Completion rate as a percentage'),
                'assignments'          => new external_value(PARAM_INT, 'Total assignment activities in the course'),
                'submittedassignments' => new external_value(PARAM_INT, 'Students with at least one assignment submission'),
                'quizzes'              => new external_value(PARAM_INT, 'Total quiz activities in the course'),
                'quizattempts'         => new external_value(PARAM_INT, 'Students with at least one finished quiz attempt'),
                'avgquizgrade'         => new external_value(PARAM_FLOAT, 'Average quiz grade as a percentage'),
                'healthscore'          => new external_value(PARAM_INT, 'Course health score 0-100'),
                'healthgrade'          => new external_value(PARAM_ALPHA, 'Course health grade A through F'),
                'lastactivity'         => new external_value(PARAM_INT, 'Unix timestamp of last student activity, 0 if none'),
            ])
        );
    }
}
