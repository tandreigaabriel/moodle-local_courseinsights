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
 * Report data access and aggregation for local_courseinsights.
 *
 * @package    local_courseinsights
 * @copyright  2026 Andrei Toma
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_courseinsights;

/**
 * Builds and serves the course activity report data.
 */
class report_service {
    /**
     * Report metric columns, in display order, mapped to the activity
     * type(s) they belong to. A column is visible whenever the active
     * filter is 'all' or appears in its list of types.
     *
     * @var array<string, string[]>
     */
    private const DEFAULT_PER_PAGE = 12;

    /**
     * Total row count from the most recent get_course_overview() call.
     *
     * @var int
     */
    private static int $lasttotalcount = 0;

    /**
     * Returns the configured courses-per-page value, falling back to the hardcoded default.
     *
     * @return int
     */
    private static function get_per_page(): int {
        $configured = (int) get_config('local_courseinsights', 'coursesperpage');
        return $configured > 0 ? $configured : self::DEFAULT_PER_PAGE;
    }

    /**
     * Returns the total course count from the most recent get_course_overview() call.
     *
     * @return int
     */
    public static function get_last_total_count(): int {
        return self::$lasttotalcount;
    }

    /**
     * Map of sortable column keys to their SQL expressions used in ORDER BY.
     *
     * @var array<string, string>
     */
    private const SORT_COLUMNS = [
        'course'               => 'c.fullname',
        'enrolledstudents'     => 'enrolledstudents',
        'completionrate'       => 'completionrate',
        'teachers'             => 'c.fullname',
        'assignments'          => 'assignments',
        'submittedassignments' => 'submittedassignments',
        'quizzes'              => 'quizzes',
        'quizattempts'         => 'quizattempts',
        'exams'                => 'exams',
        'examattempts'         => 'examattempts',
        'miniquizzes'          => 'miniquizzes',
        'miniquizattempts'     => 'miniquizattempts',
        'avgquizgrade'         => 'avgquizgrade',
        'lastactivity'         => 'lastactivity',
    ];

    /**
     * Report metric columns mapped to the activity type(s) for which each
     * column is visible. A column is shown when the active filter is 'all'
     * or appears in its list of types.
     *
     * @var array<string, string[]>
     */
    private const COLUMN_TYPES = [
        'enrolledstudents' => ['all'],
        'completionrate' => ['all', 'assign', 'quiz', 'exam', 'mini'],
        'teachers' => ['all', 'assign', 'quiz', 'exam', 'mini'],
        'assignments' => ['all', 'assign'],
        'submittedassignments' => ['all', 'assign'],
        'quizzes' => ['all', 'quiz'],
        'quizattempts' => ['all', 'quiz'],
        'exams' => ['all', 'exam'],
        'examattempts' => ['all', 'exam'],
        'miniquizzes' => ['all', 'mini'],
        'miniquizattempts' => ['all', 'mini'],
        'avgquizgrade' => ['all', 'quiz', 'exam', 'mini'],
        'lastactivity' => ['all', 'assign', 'quiz', 'exam', 'mini'],
    ];

    /**
     * Returns the cohort options for the cohort filter, including the
     * "all cohorts" option.
     *
     * @return array
     */
    public static function get_cohort_options(): array {
        $cache = \cache::make('local_courseinsights', 'dropdown_options');
        $cached = $cache->get('cohort_options');
        if ($cached !== false) {
            return $cached;
        }

        global $DB;
        $cohorts = [0 => get_string('allcohorts', 'local_courseinsights')];
        $records = $DB->get_records_sql_menu("
            SELECT id, name
              FROM {cohort}
             WHERE visible = 1
             ORDER BY name ASC
        ", []);
        foreach ($records as $id => $name) {
            $cohorts[(int)$id] = format_string($name);
        }
        $cache->set('cohort_options', $cohorts);
        return $cohorts;
    }

    /**
     * Returns the category options for the category filter, including the
     * "all categories" option. Categories are ordered by tree path and
     * indented to reflect their hierarchy depth.
     *
     * @return array
     */
    public static function get_category_options(): array {
        $cache = \cache::make('local_courseinsights', 'dropdown_options');
        $cached = $cache->get('category_options');
        if ($cached !== false) {
            return $cached;
        }

        global $DB;
        $categories = [0 => get_string('allcategories', 'local_courseinsights')];
        $records = $DB->get_records_sql("
            SELECT id, name, depth
              FROM {course_categories}
             WHERE visible = 1
             ORDER BY path ASC
        ", []);
        foreach ($records as $record) {
            $indent = str_repeat('— ', max(0, (int)$record->depth - 1));
            $categories[(int)$record->id] = $indent . format_string($record->name);
        }
        $cache->set('category_options', $categories);
        return $categories;
    }

    /**
     * Reads and validates the report filters from the current request.
     *
     * @return array
     */
    public static function get_filters_from_request(): array {
        return [
            'cohortid'      => optional_param('cohortid', 0, PARAM_INT),
            'courseid'      => optional_param('courseid', 0, PARAM_INT),
            'categoryid'    => optional_param('categoryid', 0, PARAM_INT),
            'startdate'          => optional_param('startdate', '', PARAM_TEXT),
            'enddate'            => optional_param('enddate', '', PARAM_TEXT),
            'compare_startdate'  => optional_param('compare_startdate', '', PARAM_TEXT),
            'compare_enddate'    => optional_param('compare_enddate', '', PARAM_TEXT),
            'activitytype'  => optional_param('activitytype', 'all', PARAM_ALPHA),
            'studentstatus' => optional_param('studentstatus', 'active', PARAM_ALPHA),
            'usecache'      => optional_param('usecache', 0, PARAM_BOOL),
            'sortby'        => optional_param('sortby', 'course', PARAM_ALPHA),
            'sortdir'       => optional_param('sortdir', 'asc', PARAM_ALPHA),
        ];
    }

    /**
     * Returns the report column keys that should be visible for the given
     * activity type filter. The 'course' column is always shown by the
     * caller and is not part of this list.
     *
     * @param string $activitytype One of 'all', 'assign', 'quiz', 'exam', 'mini'.
     * @return string[]
     */
    public static function get_visible_columns(string $activitytype): array {
        $columns = [];

        foreach (self::COLUMN_TYPES as $column => $types) {
            if ($activitytype === 'all' || in_array($activitytype, $types, true)) {
                $columns[] = $column;
            }
        }

        return $columns;
    }

    /**
     * Computes summary stat card totals from the given report records.
     *
     * @param array $records As returned by get_course_overview().
     * @param string $activitytype Active activity type filter.
     * @return array Keys: statcourses, statenrolled, statsubmissions, statattempts,
     *               showstatsubmissions, showstatattempts.
     */
    public static function get_stats(array $records, string $activitytype): array {
        if (empty($records)) {
            return [
                'statcourses'         => 0,
                'statenrolled'        => 0,
                'statsubmissions'     => 0,
                'statattempts'        => 0,
                'showstatsubmissions' => false,
                'showstatattempts'    => false,
            ];
        }

        $attempts = 0;
        if (in_array($activitytype, ['all', 'quiz'], true)) {
            $attempts += (int) array_sum(array_column($records, 'quizattempts'));
        }
        if (in_array($activitytype, ['all', 'exam'], true)) {
            $attempts += (int) array_sum(array_column($records, 'examattempts'));
        }
        if (in_array($activitytype, ['all', 'mini'], true)) {
            $attempts += (int) array_sum(array_column($records, 'miniquizattempts'));
        }

        return [
            'statcourses'         => count($records),
            'statenrolled'        => (int) array_sum(array_column($records, 'enrolledstudents')),
            'statsubmissions'     => (int) array_sum(array_column($records, 'submittedassignments')),
            'statattempts'        => $attempts,
            'showstatsubmissions' => in_array($activitytype, ['all', 'assign'], true),
            'showstatattempts'    => in_array($activitytype, ['all', 'quiz', 'exam', 'mini'], true),
        ];
    }

    /**
     * Returns the raw value of a report column from a report record, for
     * display or export formatting by the caller.
     *
     * @param string $column
     * @param \stdClass $record
     * @return int|float|null
     */
    public static function get_column_value(string $column, \stdClass $record) {
        if ($column === 'completionrate') {
            $val = $record->completionrate ?? null;
            return $val !== null ? (float) $val : null;
        }

        if ($column === 'avgquizgrade') {
            return $record->avgquizgrade;
        }

        if ($column === 'lastactivity') {
            $ts = $record->lastactivity ?? null;
            return ($ts !== null && $ts > 0) ? (int) $ts : null;
        }

        if ($column === 'teachers') {
            $val = $record->teachers ?? null;
            return ($val !== null && $val !== '') ? (string) $val : null;
        }

        return (int) $record->$column;
    }

    /**
     * Neutralises CSV/spreadsheet formula injection by prefixing values
     * that start with a formula trigger character with a single quote.
     *
     * @param string $value
     * @return string
     */
    public static function csv_safe_value(string $value): string {
        if ($value !== '' && strpos('=+-@', $value[0]) !== false) {
            return "'" . $value;
        }

        return $value;
    }

    /**
     * Builds the course activity overview report, applying the given filters.
     *
     * @param array $filters Filters as returned by get_filters_from_request().
     * @param int $page Zero-based page number for pagination.
     * @param int $perpage Rows per page; 0 means unlimited (used by export and cache rebuild).
     * @return array
     */
    public static function get_course_overview(array $filters, int $page = 0, int $perpage = -1): array {
        global $DB;

        if ($perpage < 0) {
            $perpage = self::get_per_page();
        }

        $cohortid = (int) ($filters['cohortid'] ?? 0);
        $courseid = (int) ($filters['courseid'] ?? 0);
        $categoryid = (int) ($filters['categoryid'] ?? 0);
        $startdate = trim((string) ($filters['startdate'] ?? ''));
        $enddate = trim((string) ($filters['enddate'] ?? ''));
        $activitytype = $filters['activitytype'] ?? 'all';
        $studentstatus = $filters['studentstatus'] ?? 'active';
        $usecache = !empty($filters['usecache']);

        $sortbyraw = $filters['sortby'] ?? 'course';
        $sortdirraw = strtolower($filters['sortdir'] ?? 'asc') === 'desc' ? 'desc' : 'asc';
        if (!isset(self::SORT_COLUMNS[$sortbyraw])) {
            $sortbyraw = 'course';
        }
        $sortcol = self::SORT_COLUMNS[$sortbyraw];
        $sortdirsql = $sortdirraw === 'desc' ? 'DESC' : 'ASC';
        $ordersql = $sortcol === 'c.fullname'
            ? "ORDER BY c.fullname {$sortdirsql}"
            : "ORDER BY {$sortcol} IS NULL, {$sortcol} {$sortdirsql}";

        $cacheenabled = (bool) get_config('local_courseinsights', 'enablecache');

        if (
            $usecache && $cacheenabled && $startdate === '' && $enddate === ''
                && $activitytype === 'all' && $studentstatus === 'active' && $categoryid === 0
                && $cohortid === 0 && $sortbyraw === 'course' && $sortdirraw === 'asc'
        ) {
            self::$lasttotalcount = self::get_cached_course_count($courseid);
            return self::get_cached_course_overview($courseid, $page, $perpage);
        }

        $params = [
            'sitecourse' => SITEID,
        ];

        // Moodle named params must be unique across the whole query; each subquery
        // that filters by context level gets its own alias (ctx1..ctx7).
        for ($i = 1; $i <= 8; $i++) {
            $params["ctx{$i}"] = CONTEXT_COURSE;
        }
        $where = 'c.id <> :sitecourse AND c.visible = 1';

        if ($courseid > 0) {
            $where .= ' AND c.id = :courseid';
            $params['courseid'] = $courseid;
        }

        if ($categoryid > 0) {
            $catids = self::get_category_ids($categoryid);
            [$catinsql, $catp] = $DB->get_in_or_equal($catids, SQL_PARAMS_NAMED, 'catid_');
            $where .= " AND c.category {$catinsql}";
            $params = array_merge($params, $catp);
        }

        if ($cohortid > 0) {
            $where .= " AND EXISTS (
                SELECT 1
                  FROM {cohort_members} cm
                  JOIN {user_enrolments} ue ON ue.userid = cm.userid
                  JOIN {enrol} e ON e.id = ue.enrolid
                 WHERE e.courseid = c.id
                   AND cm.cohortid = :cohortid
                   AND e.status = 0
                   AND ue.status = 0
            )";
            $params['cohortid'] = $cohortid;
        }

        [$startts, $endts] = self::get_date_range_timestamps($startdate, $enddate);

        // Each occurrence of a date filter in the SQL needs its own unique param name.
        $submissiondatesql = '';
        $attemptdatesql1   = '';
        $attemptdatesql2   = '';
        $attemptdatesql3   = '';

        if ($startts > 0) {
            $submissiondatesql .= ' AND s.timemodified >= :substart';
            $attemptdatesql1   .= ' AND qa.timefinish >= :qattstart';
            $attemptdatesql2   .= ' AND qa.timefinish >= :xattstart';
            $attemptdatesql3   .= ' AND qa.timefinish >= :mattstart';
            $params['substart']  = $startts;
            $params['qattstart'] = $startts;
            $params['xattstart'] = $startts;
            $params['mattstart'] = $startts;
        }

        if ($endts > 0) {
            $submissiondatesql .= ' AND s.timemodified <= :subend';
            $attemptdatesql1   .= ' AND qa.timefinish <= :qattend';
            $attemptdatesql2   .= ' AND qa.timefinish <= :xattend';
            $attemptdatesql3   .= ' AND qa.timefinish <= :mattend';
            $params['subend']  = $endts;
            $params['qattend'] = $endts;
            $params['xattend'] = $endts;
            $params['mattend'] = $endts;
        }

        $statussql = self::get_student_status_sql($studentstatus);

        // Each of the six role-filtered subqueries needs its own get_in_or_equal
        // expansion so that every generated :ridN_X param name is unique.
        $roleids = self::get_student_role_ids();
        [$roleinsql1, $rp1] = $DB->get_in_or_equal($roleids, SQL_PARAMS_NAMED, 'rid1_');
        [$roleinsql2, $rp2] = $DB->get_in_or_equal($roleids, SQL_PARAMS_NAMED, 'rid2_');
        [$roleinsql3, $rp3] = $DB->get_in_or_equal($roleids, SQL_PARAMS_NAMED, 'rid3_');
        [$roleinsql4, $rp4] = $DB->get_in_or_equal($roleids, SQL_PARAMS_NAMED, 'rid4_');
        [$roleinsql5, $rp5] = $DB->get_in_or_equal($roleids, SQL_PARAMS_NAMED, 'rid5_');
        [$roleinsql6, $rp6] = $DB->get_in_or_equal($roleids, SQL_PARAMS_NAMED, 'rid6_');
        [$roleinsql7, $rp7] = $DB->get_in_or_equal($roleids, SQL_PARAMS_NAMED, 'rid7_');
        [$roleinsql8, $rp8] = $DB->get_in_or_equal($roleids, SQL_PARAMS_NAMED, 'rid8_');
        $params = array_merge($params, $rp1, $rp2, $rp3, $rp4, $rp5, $rp6, $rp7, $rp8);

        // Keyword SQL is also used in two subqueries each, so generate separate copies.
        $miniexamsql1 = self::get_keyword_like_sql('q.name', self::get_keywords('miniexamkeywords', 'mini'), 'minikey', $params);
        $miniexamsql2 = self::get_keyword_like_sql('q.name', self::get_keywords('miniexamkeywords', 'mini'), 'minikey2', $params);
        $examsql1 = self::get_keyword_like_sql('q.name', self::get_keywords('examkeywords', 'exam'), 'examkey', $params);
        $examsql2 = self::get_keyword_like_sql('q.name', self::get_keywords('examkeywords', 'exam'), 'examkey2', $params);

        // Two-step prefilter: cheap ID + COUNT queries first, then heavy JOINs only for those IDs.
        // Used for default sort (course name) where course ordering is known cheaply.
        $filtere   = '';
        $filtera   = '';
        $filterq   = '';
        $filterla  = '';
        $limitfrom = $perpage > 0 ? $page * $perpage : 0;
        $limitnum  = $perpage > 0 ? $perpage : 0;

        if ($perpage > 0 && $sortbyraw === 'course') {
            $cpwhere  = 'c.id <> :cp_sitecourse AND c.visible = 1';
            $cpparams = ['cp_sitecourse' => SITEID];

            if ($courseid > 0) {
                $cpwhere .= ' AND c.id = :cp_courseid';
                $cpparams['cp_courseid'] = $courseid;
            }

            if ($categoryid > 0) {
                [$cpinsql, $cpp] = $DB->get_in_or_equal($catids, SQL_PARAMS_NAMED, 'cp_catid_');
                $cpwhere .= " AND c.category {$cpinsql}";
                $cpparams = array_merge($cpparams, $cpp);
            }

            if ($cohortid > 0) {
                $cpwhere .= " AND EXISTS (
                    SELECT 1 FROM {cohort_members} cm
                      JOIN {user_enrolments} ue ON ue.userid = cm.userid
                      JOIN {enrol} e ON e.id = ue.enrolid
                     WHERE e.courseid = c.id AND cm.cohortid = :cp_cohortid
                       AND e.status = 0 AND ue.status = 0
                )";
                $cpparams['cp_cohortid'] = $cohortid;
            }

            self::$lasttotalcount = (int) $DB->count_records_sql(
                "SELECT COUNT(*) FROM {course} c WHERE {$cpwhere}",
                $cpparams
            );

            $pagedrows = $DB->get_records_sql(
                "SELECT c.id FROM {course} c WHERE {$cpwhere} ORDER BY c.fullname {$sortdirsql}",
                $cpparams,
                $limitfrom,
                $limitnum
            );
            $pageids = array_keys($pagedrows);

            if (empty($pageids)) {
                return [];
            }

            // IDs are DB-sourced integers; inlining avoids named-param reuse across multiple IN clauses.
            $inlistsql = 'IN (' . implode(',', array_map('intval', $pageids)) . ')';

            $where     = "c.id {$inlistsql}";
            $limitfrom = 0;
            $limitnum  = 0;

            $filtere   = " AND e.courseid {$inlistsql}";
            $filtera   = " AND a.course {$inlistsql}";
            $filterq   = " AND q.course {$inlistsql}";
            $filterla  = " AND la.courseid {$inlistsql}";
        } else if ($perpage > 0) {
            self::$lasttotalcount = self::get_course_count($filters);
        }

        $sql = "
            SELECT
                c.id,
                c.fullname,
                COALESCE(enr.enrolledstudents, 0)       AS enrolledstudents,
                comp.completionrate,
                COALESCE(asgn.assignments, 0)           AS assignments,
                COALESCE(sub.submittedassignments, 0)   AS submittedassignments,
                COALESCE(qz.quizzes, 0)                 AS quizzes,
                COALESCE(qatt.quizattempts, 0)          AS quizattempts,
                COALESCE(ex.exams, 0)                   AS exams,
                COALESCE(xatt.examattempts, 0)          AS examattempts,
                COALESCE(mq.miniquizzes, 0)             AS miniquizzes,
                COALESCE(matt.miniquizattempts, 0)      AS miniquizattempts,
                avgqz.avgquizgrade,
                lastact.lastactivity,
                NULL AS teachers

            FROM {course} c

            LEFT JOIN (
                SELECT e.courseid,
                       COUNT(DISTINCT ue.userid) AS enrolledstudents
                  FROM {enrol} e
                  JOIN {user_enrolments} ue ON ue.enrolid = e.id
                  JOIN {user} u ON u.id = ue.userid
                  JOIN {context} ctx ON ctx.instanceid = e.courseid AND ctx.contextlevel = :ctx1
                  JOIN {role_assignments} ra ON ra.contextid = ctx.id AND ra.userid = u.id AND ra.roleid {$roleinsql1}
                 WHERE e.status = 0
                   AND ue.status = 0
                   AND u.deleted = 0
                   {$statussql}
                   {$filtere}
                 GROUP BY e.courseid
            ) enr ON enr.courseid = c.id

            LEFT JOIN (
                SELECT e.courseid,
                       ROUND(
                           COUNT(DISTINCT CASE WHEN cc.timecompleted IS NOT NULL THEN ue.userid END)
                           * 100.0
                           / NULLIF(COUNT(DISTINCT ue.userid), 0),
                           1
                       ) AS completionrate
                  FROM {enrol} e
                  JOIN {user_enrolments} ue ON ue.enrolid = e.id
                  JOIN {user} u ON u.id = ue.userid
                  JOIN {context} ctx ON ctx.instanceid = e.courseid AND ctx.contextlevel = :ctx8
                  JOIN {role_assignments} ra ON ra.contextid = ctx.id AND ra.userid = u.id AND ra.roleid {$roleinsql8}
                  LEFT JOIN {course_completions} cc ON cc.course = e.courseid AND cc.userid = ue.userid
                 WHERE e.status = 0
                   AND ue.status = 0
                   AND u.deleted = 0
                   {$statussql}
                   {$filtere}
                 GROUP BY e.courseid
            ) comp ON comp.courseid = c.id

            LEFT JOIN (
                SELECT a.course AS courseid,
                       COUNT(*) AS assignments
                  FROM {assign} a
                 WHERE a.id > 0{$filtera}
                 GROUP BY a.course
            ) asgn ON asgn.courseid = c.id

            LEFT JOIN (
                SELECT a.course AS courseid,
                       COUNT(DISTINCT s.userid) AS submittedassignments
                  FROM {assign} a
                  JOIN {assign_submission} s ON s.assignment = a.id
                  JOIN {user} u ON u.id = s.userid
                  JOIN {context} ctx ON ctx.instanceid = a.course AND ctx.contextlevel = :ctx2
                  JOIN {role_assignments} ra ON ra.contextid = ctx.id AND ra.userid = u.id AND ra.roleid {$roleinsql2}
                 WHERE s.latest = 1
                   AND s.status = 'submitted'
                   AND u.deleted = 0
                   {$statussql}
                   {$submissiondatesql}
                   {$filtera}
                 GROUP BY a.course
            ) sub ON sub.courseid = c.id

            LEFT JOIN (
                SELECT q.course AS courseid,
                       COUNT(*) AS quizzes
                  FROM {quiz} q
                 WHERE q.id > 0{$filterq}
                 GROUP BY q.course
            ) qz ON qz.courseid = c.id

            LEFT JOIN (
                SELECT q.course AS courseid,
                       COUNT(DISTINCT qa.userid) AS quizattempts
                  FROM {quiz} q
                  JOIN {quiz_attempts} qa ON qa.quiz = q.id
                  JOIN {user} u ON u.id = qa.userid
                  JOIN {context} ctx ON ctx.instanceid = q.course AND ctx.contextlevel = :ctx3
                  JOIN {role_assignments} ra ON ra.contextid = ctx.id AND ra.userid = u.id AND ra.roleid {$roleinsql3}
                 WHERE qa.state = 'finished'
                   AND u.deleted = 0
                   {$statussql}
                   {$attemptdatesql1}
                   {$filterq}
                 GROUP BY q.course
            ) qatt ON qatt.courseid = c.id

            LEFT JOIN (
                SELECT q.course AS courseid,
                       COUNT(*) AS exams
                  FROM {quiz} q
                 WHERE {$examsql1}
                   {$filterq}
                 GROUP BY q.course
            ) ex ON ex.courseid = c.id

            LEFT JOIN (
                SELECT q.course AS courseid,
                       COUNT(DISTINCT qa.userid) AS examattempts
                  FROM {quiz} q
                  JOIN {quiz_attempts} qa ON qa.quiz = q.id
                  JOIN {user} u ON u.id = qa.userid
                  JOIN {context} ctx ON ctx.instanceid = q.course AND ctx.contextlevel = :ctx4
                  JOIN {role_assignments} ra ON ra.contextid = ctx.id AND ra.userid = u.id AND ra.roleid {$roleinsql4}
                 WHERE qa.state = 'finished'
                   AND {$examsql2}
                   AND u.deleted = 0
                   {$statussql}
                   {$attemptdatesql2}
                   {$filterq}
                 GROUP BY q.course
            ) xatt ON xatt.courseid = c.id

            LEFT JOIN (
                SELECT q.course AS courseid,
                       COUNT(*) AS miniquizzes
                  FROM {quiz} q
                 WHERE {$miniexamsql1}
                   {$filterq}
                 GROUP BY q.course
            ) mq ON mq.courseid = c.id

            LEFT JOIN (
                SELECT q.course AS courseid,
                       COUNT(DISTINCT qa.userid) AS miniquizattempts
                  FROM {quiz} q
                  JOIN {quiz_attempts} qa ON qa.quiz = q.id
                  JOIN {user} u ON u.id = qa.userid
                  JOIN {context} ctx ON ctx.instanceid = q.course AND ctx.contextlevel = :ctx5
                  JOIN {role_assignments} ra ON ra.contextid = ctx.id AND ra.userid = u.id AND ra.roleid {$roleinsql5}
                 WHERE qa.state = 'finished'
                   AND {$miniexamsql2}
                   AND u.deleted = 0
                   {$statussql}
                   {$attemptdatesql3}
                   {$filterq}
                 GROUP BY q.course
            ) matt ON matt.courseid = c.id

            LEFT JOIN (
                SELECT q.course AS courseid,
                       ROUND(AVG(
                           CASE
                               WHEN q.grade > 0 THEN (qg.grade / q.grade) * 100
                               ELSE NULL
                           END
                       ), 2) AS avgquizgrade
                  FROM {quiz} q
                  JOIN {quiz_grades} qg ON qg.quiz = q.id
                  JOIN {user} u ON u.id = qg.userid
                  JOIN {context} ctx ON ctx.instanceid = q.course AND ctx.contextlevel = :ctx6
                  JOIN {role_assignments} ra ON ra.contextid = ctx.id AND ra.userid = u.id AND ra.roleid {$roleinsql6}
                 WHERE u.deleted = 0
                   {$statussql}
                   {$filterq}
                 GROUP BY q.course
            ) avgqz ON avgqz.courseid = c.id

            LEFT JOIN (
                SELECT la.courseid,
                       MAX(la.timeaccess) AS lastactivity
                  FROM {user_lastaccess} la
                  JOIN {user} u ON u.id = la.userid
                  JOIN {context} ctx ON ctx.instanceid = la.courseid AND ctx.contextlevel = :ctx7
                  JOIN {role_assignments} ra ON ra.contextid = ctx.id AND ra.userid = u.id AND ra.roleid {$roleinsql7}
                 WHERE u.deleted = 0
                   {$statussql}
                   {$filterla}
                 GROUP BY la.courseid
            ) lastact ON lastact.courseid = c.id

            WHERE {$where}
            {$ordersql}
        ";

        $records = $DB->get_records_sql($sql, $params, $limitfrom, $limitnum);
        $records = self::attach_teacher_names($records);

        if ($perpage === 0) {
            self::$lasttotalcount = count($records);
        }

        return $records;
    }

    /**
     * Attaches comma-separated editing teacher names to course records.
     *
     * @param array $records Course records keyed by course ID.
     * @return array Course records with teachers populated.
     */
    private static function attach_teacher_names(array $records): array {
        global $DB;

        if (empty($records)) {
            return $records;
        }

        $courseids = array_map('intval', array_keys($records));
        [$courseinsql, $params] = $DB->get_in_or_equal($courseids, SQL_PARAMS_NAMED, 'teachercourse');
        $params['contextlevel'] = CONTEXT_COURSE;

        $rows = $DB->get_recordset_sql("
            SELECT ra.id AS assignmentid,
                   ctx.instanceid AS courseid,
                   u.*
              FROM {context} ctx
              JOIN {role_assignments} ra ON ra.contextid = ctx.id
              JOIN {role} r ON r.id = ra.roleid AND r.shortname = 'editingteacher'
              JOIN {user} u ON u.id = ra.userid
             WHERE ctx.contextlevel = :contextlevel
               AND ctx.instanceid {$courseinsql}
               AND u.deleted = 0
             ORDER BY ctx.instanceid ASC, u.lastname ASC, u.firstname ASC
        ", $params);

        $teachers = [];
        foreach ($rows as $row) {
            $courseid = (int) $row->courseid;
            $teachers[$courseid][(int) $row->id] = fullname($row);
        }
        $rows->close();

        foreach ($records as $record) {
            $courseid = (int) $record->id;
            $record->teachers = !empty($teachers[$courseid])
                ? implode(', ', array_values($teachers[$courseid]))
                : null;
        }

        return $records;
    }

    /**
     * Returns the total number of visible courses matching the course filter,
     * used to compute pagination totals without running the heavy subqueries.
     *
     * @param array $filters
     * @return int
     */
    public static function get_course_count(array $filters): int {
        global $DB;

        $cohortid = (int) ($filters['cohortid'] ?? 0);
        $courseid = (int) ($filters['courseid'] ?? 0);
        $categoryid = (int) ($filters['categoryid'] ?? 0);
        $params = ['sitecourse' => SITEID];
        $where = 'c.id <> :sitecourse AND c.visible = 1';

        if ($courseid > 0) {
            $where .= ' AND c.id = :courseid';
            $params['courseid'] = $courseid;
        }

        if ($categoryid > 0) {
            $catids = self::get_category_ids($categoryid);
            [$catinsql, $catp] = $DB->get_in_or_equal($catids, SQL_PARAMS_NAMED, 'catid_');
            $where .= " AND c.category {$catinsql}";
            $params = array_merge($params, $catp);
        }

        if ($cohortid > 0) {
            $where .= " AND EXISTS (
                SELECT 1
                  FROM {cohort_members} cm
                  JOIN {user_enrolments} ue ON ue.userid = cm.userid
                  JOIN {enrol} e ON e.id = ue.enrolid
                 WHERE e.courseid = c.id
                   AND cm.cohortid = :cohortid
                   AND e.status = 0
                   AND ue.status = 0
            )";
            $params['cohortid'] = $cohortid;
        }

        return (int) $DB->count_records_sql(
            "SELECT COUNT(*) FROM {course} c WHERE {$where}",
            $params
        );
    }

    /**
     * Returns Mustache template context for Bootstrap 5 pagination controls.
     * Returns ['haspagination' => false] when all results fit on one page.
     *
     * @param int $page Current 0-based page index.
     * @param int $totalcount Total number of records across all pages.
     * @return array
     */
    public static function get_pagination_context(int $page, int $totalcount): array {
        $perpage = self::get_per_page();

        $from = $totalcount > 0 ? $page * $perpage + 1 : 0;
        $to   = min(($page + 1) * $perpage, $totalcount);

        $paginationinfo = get_string('pagination_info', 'local_courseinsights', (object)[
            'from'  => $from,
            'to'    => $to,
            'total' => $totalcount,
        ]);

        if ($totalcount <= $perpage) {
            return [
                'haspagination'  => false,
                'pagination_info' => $paginationinfo,
            ];
        }

        $totalpages = (int) ceil($totalcount / $perpage);

        // Build smart page list: show all pages when <=9, otherwise show
        // first two, last two, and a ±1 window around the current page with
        // ellipsis gaps.
        if ($totalpages <= 9) {
            $show = range(0, $totalpages - 1);
        } else {
            $show = array_unique([
                0, 1,
                max(0, $page - 1),
                $page,
                min($totalpages - 1, $page + 1),
                $totalpages - 2,
                $totalpages - 1,
            ]);
            sort($show);
        }

        $pages = [];
        $prev = -1;
        foreach ($show as $i) {
            if ($prev >= 0 && $i > $prev + 1) {
                $pages[] = ['label' => '…', 'active' => false, 'isellipsis' => true];
            }
            $pages[] = [
                'num'        => $i,
                'label'      => (string)($i + 1),
                'active'     => $i === $page,
                'isellipsis' => false,
            ];
            $prev = $i;
        }

        return [
            'haspagination'  => true,
            'pagination_info' => $paginationinfo,
            'hasprev'        => $page > 0,
            'hasnext'        => $page < $totalpages - 1,
            'prevpage'       => max(0, $page - 1),
            'nextpage'       => min($totalpages - 1, $page + 1),
            'pages'          => $pages,
        ];
    }

    /**
     * Returns the cached all-time course summary, optionally restricted
     * to a single course, with server-side pagination.
     *
     * @param int $courseid
     * @param int $page Zero-based page index.
     * @param int $perpage Rows per page; 0 means unlimited.
     * @return array
     */
    public static function get_cached_course_overview(int $courseid = 0, int $page = 0, int $perpage = 0): array {
        global $DB;

        $params = [
            'periodstart' => 0,
            'periodend' => 0,
        ];

        $where = 's.periodstart = :periodstart AND s.periodend = :periodend';

        if ($courseid > 0) {
            $where .= ' AND s.courseid = :courseid';
            $params['courseid'] = $courseid;
        }

        $sql = "
            SELECT
                s.id,
                s.courseid,
                c.fullname,
                s.enrolledstudents,
                s.assignments,
                s.submittedassignments,
                s.quizzes,
                s.quizattempts,
                s.exams,
                s.examattempts,
                s.miniquizzes,
                s.miniquizattempts,
                s.avgquizgrade,
                s.completionrate,
                s.lastactivity,
                s.teachers
              FROM {local_courseinsights_summary} s
              JOIN {course} c ON c.id = s.courseid AND c.visible = 1
             WHERE {$where}
             ORDER BY c.fullname ASC
        ";

        $limitfrom = $perpage > 0 ? $page * $perpage : 0;
        $limitnum  = $perpage > 0 ? $perpage : 0;

        return $DB->get_records_sql($sql, $params, $limitfrom, $limitnum);
    }

    /**
     * Returns the total count of cached summary rows for pagination.
     *
     * @param int $courseid
     * @return int
     */
    private static function get_cached_course_count(int $courseid = 0): int {
        global $DB;

        $params = ['periodstart' => 0, 'periodend' => 0];
        $where  = 's.periodstart = :periodstart AND s.periodend = :periodend';

        if ($courseid > 0) {
            $where .= ' AND s.courseid = :courseid';
            $params['courseid'] = $courseid;
        }

        return (int) $DB->count_records_sql(
            "SELECT COUNT(*)
               FROM {local_courseinsights_summary} s
               JOIN {course} c ON c.id = s.courseid AND c.visible = 1
              WHERE {$where}",
            $params
        );
    }

    /**
     * Recomputes the all-time course overview and replaces the cached
     * summary rows used by get_cached_course_overview().
     *
     * @return void
     */
    public static function rebuild_summary_cache(): void {
        global $DB;

        $filters = [
            'courseid' => 0,
            'startdate' => '',
            'enddate' => '',
            'activitytype' => 'all',
            'studentstatus' => 'active',
            'usecache' => 0,
        ];

        $records = self::get_course_overview($filters, 0, 0);

        $transaction = $DB->start_delegated_transaction();

        $DB->delete_records('local_courseinsights_summary', [
            'periodstart' => 0,
            'periodend' => 0,
        ]);

        $now = time();

        foreach ($records as $record) {
            $summary = new \stdClass();
            $summary->courseid = $record->id;
            $summary->periodstart = 0;
            $summary->periodend = 0;
            $summary->enrolledstudents = (int) $record->enrolledstudents;
            $summary->assignments = (int) $record->assignments;
            $summary->submittedassignments = (int) $record->submittedassignments;
            $summary->quizzes = (int) $record->quizzes;
            $summary->quizattempts = (int) $record->quizattempts;
            $summary->exams = (int) $record->exams;
            $summary->examattempts = (int) $record->examattempts;
            $summary->miniquizzes = (int) $record->miniquizzes;
            $summary->miniquizattempts = (int) $record->miniquizattempts;
            $summary->avgquizgrade = $record->avgquizgrade;
            $summary->completionrate = isset($record->completionrate) ? (float) $record->completionrate : null;
            $summary->lastactivity = !empty($record->lastactivity) ? (int) $record->lastactivity : null;
            $summary->teachers = $record->teachers ?? null;
            $summary->timecreated = $now;
            $summary->timemodified = $now;

            $DB->insert_record('local_courseinsights_summary', $summary);
        }

        $transaction->allow_commit();
    }

    /**
     * Builds the aggregate detailed-report payload for a course.
     *
     * This snapshot intentionally excludes user-identifying widgets such as the
     * student activity table and leaderboard. Those remain live unless a future
     * privacy-aware personal snapshot is added.
     *
     * @param int $courseid Course ID.
     * @return array Detailed report aggregate payload.
     */
    private static function build_course_detail_snapshot_payload(int $courseid): array {
        return [
            'gradedist'     => self::get_grade_distribution($courseid),
            'heatmap'       => self::get_engagement_heatmap($courseid),
            'timeline'      => self::get_submission_timeline($courseid),
            'quizbreakdown' => self::get_quiz_score_breakdown($courseid),
            'trend'         => self::get_course_trend($courseid),
            'modulefunnel'  => self::get_module_completion_funnel($courseid),
            'generated'     => time(),
        ];
    }

    /**
     * Loads the persisted aggregate detailed-report snapshot for a course.
     *
     * @param int $courseid Course ID.
     * @return array|null Snapshot payload, or null when it has not been built yet.
     */
    public static function get_course_detail_snapshot(int $courseid): ?array {
        global $DB;

        $record = $DB->get_record(
            'local_courseinsights_detail',
            ['courseid' => $courseid],
            'payload',
            IGNORE_MISSING
        );

        if (!$record || empty($record->payload)) {
            return null;
        }

        $payload = json_decode($record->payload, true);
        return is_array($payload) ? $payload : null;
    }

    /**
     * Rebuilds and persists aggregate detailed-report snapshots for visible courses.
     *
     * Called by the overnight build_summary_cache scheduled task. One row is
     * kept per course and updated in place, so repeated task runs do not
     * duplicate snapshots.
     *
     * @return void
     */
    public static function rebuild_course_detail_snapshots(): void {
        global $DB;

        $courses = $DB->get_records_select('course', 'id <> :site AND visible = 1', ['site' => SITEID], 'id ASC', 'id');
        $now = time();

        foreach ($courses as $course) {
            $courseid = (int) $course->id;
            $payload = self::build_course_detail_snapshot_payload($courseid);
            $payloadjson = json_encode($payload) ?: '[]';

            $record = $DB->get_record(
                'local_courseinsights_detail',
                ['courseid' => $courseid],
                'id',
                IGNORE_MISSING
            );

            if ($record) {
                $record->payload = $payloadjson;
                $record->timemodified = $now;
                $DB->update_record('local_courseinsights_detail', $record);
            } else {
                $record = (object) [
                    'courseid' => $courseid,
                    'payload' => $payloadjson,
                    'timecreated' => $now,
                    'timemodified' => $now,
                ];
                $DB->insert_record('local_courseinsights_detail', $record);
            }
        }
    }

    /**
     * Builds the site overview payload.
     *
     * @param int $atriskdays Inactivity threshold used by the at-risk list.
     * @return array Site overview payload.
     */
    private static function build_site_overview_payload(int $atriskdays): array {
        return [
            'kpis'         => self::get_site_kpis(),
            'topenrol'     => self::get_top_courses_by_enrolment(10),
            'topcompl'     => self::get_top_courses_by_completion(10),
            'monthlytrend' => self::get_monthly_active_users(12),
            'generated'    => time(),
        ];
    }

    /**
     * Returns the stable snapshot key for a site overview configuration.
     *
     * @param int $atriskdays Inactivity threshold used by the at-risk list.
     * @return string Snapshot key.
     */
    private static function get_site_overview_snapshot_key(int $atriskdays): string {
        return 'site_overview_' . max(1, $atriskdays);
    }

    /**
     * Loads the persisted site overview snapshot, falling back to MUC if present.
     *
     * @param int $atriskdays Inactivity threshold used by the at-risk list.
     * @return array|null Site overview payload, or null when the overnight task has not built one yet.
     */
    public static function get_site_overview_snapshot(int $atriskdays): ?array {
        global $DB;

        $atriskdays = max(1, $atriskdays);
        $cache = \cache::make('local_courseinsights', 'site_kpis');
        $snapshotkey = self::get_site_overview_snapshot_key($atriskdays);

        $cached = $cache->get($snapshotkey);
        if (is_array($cached)) {
            return $cached;
        }

        $record = $DB->get_record(
            'local_courseinsights_site',
            ['snapshotkey' => $snapshotkey],
            'payload',
            IGNORE_MISSING
        );
        if (!$record || empty($record->payload)) {
            return null;
        }

        $payload = json_decode($record->payload, true);
        if (!is_array($payload)) {
            return null;
        }

        $cache->set($snapshotkey, $payload);
        return $payload;
    }

    /**
     * Rebuilds and persists the Site Overview snapshot.
     *
     * Called by the overnight build_summary_cache scheduled task. The table has
     * one row per snapshot key and is updated in place, so repeated task runs do
     * not duplicate rows.
     *
     * @return void
     */
    public static function rebuild_site_kpis_cache(): void {
        global $DB;

        $atriskdays = max(1, (int) get_config('local_courseinsights', 'studentinactivitydays') ?: 14);
        $snapshotkey = self::get_site_overview_snapshot_key($atriskdays);
        $payload = self::build_site_overview_payload($atriskdays);
        $payloadjson = json_encode($payload);
        $now = time();

        $record = $DB->get_record(
            'local_courseinsights_site',
            ['snapshotkey' => $snapshotkey],
            'id',
            IGNORE_MISSING
        );

        if ($record) {
            $record->payload = $payloadjson;
            $record->timemodified = $now;
            $DB->update_record('local_courseinsights_site', $record);
        } else {
            $record = (object) [
                'snapshotkey' => $snapshotkey,
                'payload' => $payloadjson,
                'timecreated' => $now,
                'timemodified' => $now,
            ];
            $DB->insert_record('local_courseinsights_site', $record);
        }

        \cache::make('local_courseinsights', 'site_kpis')->set($snapshotkey, $payload);
        self::rebuild_atrisk_snapshot($atriskdays);
    }

    /**
     * Rebuilds the at-risk student snapshot for the supplied threshold.
     *
     * @param int $days Inactivity threshold.
     * @return void
     */
    private static function rebuild_atrisk_snapshot(int $days): void {
        global $DB;

        $days = max(1, $days);
        $cutoff = time() - ($days * DAYSECS);
        $now = time();

        $DB->delete_records('local_courseinsights_atrisk', ['threshold' => $days]);

        $rows = $DB->get_recordset_sql(
            "SELECT u.id AS userid,
                    c.id AS courseid,
                    MAX(COALESCE(la.timeaccess, 0)) AS lastaccess,
                    MIN(ue.timecreated) AS enroltime
               FROM {enrol} e
               JOIN {user_enrolments} ue ON ue.enrolid = e.id AND ue.status = 0
               JOIN {user} u
                      ON u.id = ue.userid
                     AND u.deleted = 0 AND u.confirmed = 1 AND u.suspended = 0
               JOIN {course} c ON c.id = e.courseid AND c.id <> :site AND c.visible = 1
               LEFT JOIN {user_lastaccess} la ON la.userid = ue.userid AND la.courseid = e.courseid
               LEFT JOIN (
                   SELECT course AS courseid, userid
                     FROM {course_completions}
                    WHERE timecompleted IS NOT NULL AND timecompleted > 0
               ) cc ON cc.courseid = e.courseid AND cc.userid = ue.userid
              WHERE e.status = 0
                AND cc.userid IS NULL
              GROUP BY u.id, c.id
                HAVING MAX(COALESCE(la.timeaccess, 0)) < :cutoff
              ORDER BY MAX(COALESCE(la.timeaccess, 0)) ASC",
            ['site' => SITEID, 'cutoff' => $cutoff],
            0,
            1000
        );

        foreach ($rows as $row) {
            $lastaccess = (int) $row->lastaccess;
            $basetime = $lastaccess > 0 ? $lastaccess : (int) $row->enroltime;
            $snapshot = (object) [
                'userid' => (int) $row->userid,
                'courseid' => (int) $row->courseid,
                'threshold' => $days,
                'lastaccess' => $lastaccess,
                'daysinactive' => $basetime > 0 ? (int) floor(($now - $basetime) / DAYSECS) : null,
                'timemodified' => $now,
            ];
            $DB->insert_record('local_courseinsights_atrisk', $snapshot);
        }
        $rows->close();
    }


    /**
     * POSTs the full course overview as JSON to the configured webhook URL.
     *
     * Called by the build_summary_cache task after the nightly rebuild.
     * Returns early (success=true, httpcode=0) when no URL is configured.
     *
     * @return array Keys: success (bool), httpcode (int), error (string).
     */
    public static function push_webhook(): array {
        $url    = trim((string) get_config('local_courseinsights', 'webhookurl'));
        $apikey = trim((string) get_config('local_courseinsights', 'webhookapikey'));

        if ($url === '') {
            return ['success' => true, 'httpcode' => 0, 'error' => ''];
        }

        $filters = [
            'courseid'      => 0,
            'startdate'     => '',
            'enddate'       => '',
            'activitytype'  => 'all',
            'studentstatus' => 'active',
            'usecache'      => 0,
            'categoryid'    => 0,
            'cohortid'      => 0,
            'sortby'        => 'course',
            'sortdir'       => 'asc',
        ];

        $records    = self::get_course_overview($filters, 0, 0);
        $now        = time();
        $thirtydays = 30 * DAYSECS;
        $courses    = [];

        foreach ($records as $record) {
            $health       = self::calculate_health_score($record);
            $lastactivity = !empty($record->lastactivity) ? (int) $record->lastactivity : 0;
            $courses[] = [
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

        $payload = json_encode([
            'source'    => 'local_courseinsights',
            'generated' => $now,
            'courses'   => $courses,
        ]);

        $headers = ['Content-Type: application/json'];
        if ($apikey !== '') {
            $headers[] = 'Authorization: Bearer ' . $apikey;
        }

        $ch = new \curl(['ignoresecurity' => true]);
        $ch->setopt(['CURLOPT_CONNECTTIMEOUT' => 10, 'CURLOPT_TIMEOUT' => 30]);
        $ch->setHeader($headers);
        $ch->post($url, $payload);

        $info     = $ch->get_info();
        $httpcode = (int) ($info['http_code'] ?? 0);
        $error    = (string) ($ch->error ?? '');

        if ($httpcode < 200 || $httpcode >= 300) {
            return ['success' => false, 'httpcode' => $httpcode, 'error' => $error];
        }

        return ['success' => true, 'httpcode' => $httpcode, 'error' => ''];
    }

    /**
     * Builds the activity completion bar chart for up to the first 20
     * report records, showing only the series relevant to the active
     * activity type filter.
     *
     * @param array $records
     * @param string $activitytype One of 'all', 'assign', 'quiz', 'exam', 'mini'.
     * @return \core\chart_bar
     */
    public static function build_chart(array $records, string $activitytype = 'all'): \core\chart_bar {
        $records = array_slice($records, 0, 20);

        $labels = [];
        $assignments = [];
        $quizzes = [];
        $exams = [];
        $minis = [];

        foreach ($records as $record) {
            $labels[] = shorten_text(format_string($record->fullname), 35);
            $assignments[] = (int) $record->submittedassignments;
            $quizzes[] = (int) $record->quizattempts;
            $exams[] = (int) $record->examattempts;
            $minis[] = (int) $record->miniquizattempts;
        }

        $chart = new \core\chart_bar();
        $chart->set_title(get_string('charttitle', 'local_courseinsights'));
        $chart->set_labels($labels);

        $seriesmap = [
            'assign' => [
                ['submittedassignments', $assignments],
            ],
            'quiz' => [
                ['quizattempts', $quizzes],
            ],
            'exam' => [
                ['examattempts', $exams],
            ],
            'mini' => [
                ['miniquizattempts', $minis],
            ],
            'all' => [
                ['submittedassignments', $assignments],
                ['quizattempts', $quizzes],
                ['examattempts', $exams],
                ['miniquizattempts', $minis],
            ],
        ];

        $seriestoadd = $seriesmap[$activitytype] ?? $seriesmap['all'];

        foreach ($seriestoadd as [$stringkey, $data]) {
            $chart->add_series(new \core\chart_series(
                get_string($stringkey, 'local_courseinsights'),
                $data
            ));
        }

        return $chart;
    }

    /**
     * Builds the table header context array for the Mustache template, including
     * sort state indicators for the active column.
     *
     * @param string[] $columns Column keys in display order, including 'course' as first.
     * @param string $sortby Active sort column key.
     * @param string $sortdir Active sort direction: 'asc' or 'desc'.
     * @return array
     */
    public static function get_sort_headers(array $columns, string $sortby, string $sortdir): array {
        $headers = [];
        foreach ($columns as $col) {
            $isactive = $sortby === $col;
            $headers[] = [
                'label'    => get_string($col, 'local_courseinsights'),
                'sortkey'  => $col,
                'sortasc'  => $isactive && $sortdir === 'asc',
                'sortdesc' => $isactive && $sortdir === 'desc',
            ];
        }
        return $headers;
    }

    /**
     * Builds the CSV export URL for the given filters.
     *
     * @param array $filters
     * @return \moodle_url
     */
    public static function get_export_url(array $filters): \moodle_url {
        return new \moodle_url('/local/courseinsights/export.php', [
            'cohortid'      => $filters['cohortid'] ?? 0,
            'courseid'      => $filters['courseid'] ?? 0,
            'categoryid'    => $filters['categoryid'] ?? 0,
            'startdate'     => $filters['startdate'] ?? '',
            'enddate'       => $filters['enddate'] ?? '',
            'activitytype'  => $filters['activitytype'] ?? 'all',
            'studentstatus' => $filters['studentstatus'] ?? 'active',
            'usecache'      => $filters['usecache'] ?? 0,
            'sortby'        => $filters['sortby'] ?? 'course',
            'sortdir'       => $filters['sortdir'] ?? 'asc',
        ]);
    }

    /**
     * Returns the given category ID plus the IDs of all its descendant
     * categories, used to build the category IN filter so that selecting
     * a parent category includes courses from all subcategories at any depth.
     *
     * @param int $categoryid
     * @return int[]
     */
    private static function get_category_ids(int $categoryid): array {
        global $DB;

        $path = $DB->get_field('course_categories', 'path', ['id' => $categoryid]);
        if ($path === false) {
            return [$categoryid];
        }

        $ids = $DB->get_fieldset_sql("
            SELECT id FROM {course_categories}
             WHERE id = :catid OR path LIKE :catpath
        ", [
            'catid'   => $categoryid,
            'catpath' => $DB->sql_like_escape($path) . '/%',
        ]);

        return !empty($ids) ? array_map('intval', $ids) : [$categoryid];
    }

    /**
     * Returns the configured student role IDs, falling back to the
     * student archetype role (5) if none are configured.
     *
     * @return int[]
     */
    private static function get_student_role_ids(): array {
        $configured = get_config('local_courseinsights', 'studentroleids');

        if (empty($configured)) {
            return [5];
        }

        $ids = array_filter(array_map('trim', explode(',', $configured)));
        $ids = array_map('intval', $ids);
        $ids = array_filter($ids, fn($id) => $id > 0);

        return !empty($ids) ? array_values($ids) : [5];
    }

    /**
     * Returns the configured keyword list for a given setting, falling
     * back to the given default keyword.
     *
     * @param string $settingname
     * @param string $default
     * @return string[]
     */
    private static function get_keywords(string $settingname, string $default): array {
        $configured = get_config('local_courseinsights', $settingname);

        if (empty($configured)) {
            $configured = $default;
        }

        $keywords = array_filter(array_map('trim', explode(',', strtolower($configured))));

        return !empty($keywords) ? array_values($keywords) : [$default];
    }

    /**
     * Builds a case-insensitive LIKE-OR SQL fragment matching any of the
     * given keywords against the given field, binding each keyword as a
     * parameter with LIKE wildcard characters escaped.
     *
     * @param string $field Fully qualified SQL column, e.g. 'q.name'.
     * @param string[] $keywords Keywords to match.
     * @param string $prefix Unique parameter name prefix.
     * @param array $params Parameters array to append bound values to, by reference.
     * @return string
     */
    private static function get_keyword_like_sql(string $field, array $keywords, string $prefix, array &$params): string {
        global $DB;

        $parts = [];

        foreach ($keywords as $index => $keyword) {
            $paramname = $prefix . $index;
            $parts[] = "LOWER({$field}) LIKE :{$paramname}";
            $params[$paramname] = '%' . $DB->sql_like_escape(strtolower($keyword)) . '%';
        }

        return '(' . implode(' OR ', $parts) . ')';
    }

    /**
     * Converts the given start/end date strings (YYYY-MM-DD) to start and
     * end-of-day timestamps. Returns 0 for any date that is blank or
     * cannot be parsed.
     *
     * @param string $startdate
     * @param string $enddate
     * @return array
     */
    private static function get_date_range_timestamps(string $startdate, string $enddate): array {
        $startts = 0;
        $endts = 0;

        if ($startdate !== '') {
            $parsed = strtotime($startdate . ' 00:00:00');
            if ($parsed !== false) {
                $startts = $parsed;
            }
        }

        if ($enddate !== '') {
            $parsed = strtotime($enddate . ' 23:59:59');
            if ($parsed !== false) {
                $endts = $parsed;
            }
        }

        return [$startts, $endts];
    }

    /**
     * Returns the SQL fragment filtering by student suspension status.
     *
     * @param string $studentstatus
     * @return string
     */
    private static function get_student_status_sql(string $studentstatus): string {
        if ($studentstatus === 'suspended') {
            return ' AND u.suspended = 1';
        }

        if ($studentstatus === 'all') {
            return '';
        }

        return ' AND u.suspended = 0';
    }

    /**
     * Computes a 0–100 health score and A–F letter grade for a single course record.
     *
     * Scoring breakdown (total 100 points):
     *   - Completion rate  40 pts — proportional to completionrate field
     *   - Activity recency 30 pts — based on days since last student access
     *   - Engagement       30 pts — avg of submission rate and quiz attempt rate
     *                               (components omitted when no activities exist;
     *                                replaced with neutral 50 pts when nothing exists)
     *
     * @param \stdClass $record
     * @return array Keys: healthscore (int), healthgrade (string), healthgradeclass (string)
     */
    public static function calculate_health_score(\stdClass $record): array {
        $now = time();
        $score = 0.0;

        // Completion component: 0-40 points.
        $completion = isset($record->completionrate) ? (float)$record->completionrate : 0.0;
        $score += $completion * 0.40;

        // Activity recency component: 0-30 points.
        $lastactivity = !empty($record->lastactivity) ? (int)$record->lastactivity : 0;
        if ($lastactivity > 0) {
            $dayssince = ($now - $lastactivity) / DAYSECS;
            if ($dayssince <= 7) {
                $recency = 100;
            } else if ($dayssince <= 30) {
                $recency = 80;
            } else if ($dayssince <= 60) {
                $recency = 50;
            } else if ($dayssince <= 90) {
                $recency = 20;
            } else {
                $recency = 0;
            }
        } else {
            $recency = 0;
        }
        $score += $recency * 0.30;

        // Engagement component: 0-30 points.
        $assignments = (int)($record->assignments ?? 0);
        $quizzes = (int)($record->quizzes ?? 0);
        $enrolled = max(1, (int)($record->enrolledstudents ?? 0));
        $submissions = (int)($record->submittedassignments ?? 0);
        $quizattempts = (int)($record->quizattempts ?? 0);

        $parts = [];
        if ($assignments > 0) {
            $parts[] = min(1.0, $submissions / $enrolled) * 100;
        }
        if ($quizzes > 0) {
            $parts[] = min(1.0, $quizattempts / $enrolled) * 100;
        }
        $engagementrate = !empty($parts) ? array_sum($parts) / count($parts) : 50;
        $score += $engagementrate * 0.30;

        $finalscore = (int) round($score);

        if ($finalscore >= 80) {
            $grade = 'A';
            $gradeclass = 'ci-health--a';
        } else if ($finalscore >= 65) {
            $grade = 'B';
            $gradeclass = 'ci-health--b';
        } else if ($finalscore >= 50) {
            $grade = 'C';
            $gradeclass = 'ci-health--c';
        } else if ($finalscore >= 35) {
            $grade = 'D';
            $gradeclass = 'ci-health--d';
        } else {
            $grade = 'F';
            $gradeclass = 'ci-health--f';
        }

        return [
            'healthscore'      => $finalscore,
            'healthgrade'      => $grade,
            'healthgradeclass' => $gradeclass,
        ];
    }

    /**
     * Builds structured course card context for the dashboard template.
     *
     * @param array $records Records as returned by get_course_overview().
     * @param string $activitytype Active activity type filter.
     * @return array
     */
    public static function build_course_cards(array $records, string $activitytype): array {
        $cards = [];
        $now = time();
        $thirtydays = 30 * DAYSECS;

        foreach ($records as $record) {
            $completionrate = isset($record->completionrate) ? (float)$record->completionrate : null;
            $lastactivity = !empty($record->lastactivity) ? (int)$record->lastactivity : null;
            $isactive = $lastactivity && ($now - $lastactivity) < $thirtydays;

            $lastdisplay = $lastactivity
                ? userdate($lastactivity, get_string('strftimedate', 'langconfig'))
                : null;

            $detailurl = (new \moodle_url('/local/courseinsights/course_detail.php', [
                'courseid' => $record->id,
            ]))->out(false);

            $health = self::calculate_health_score($record);

            $cards[] = [
                'coursename'            => format_string($record->fullname),
                'courseid'              => $record->id,
                'detailurl'             => $detailurl,
                'isactive'              => $isactive,
                'statuslabel'           => $isactive
                    ? get_string('active', 'local_courseinsights')
                    : get_string('inactive', 'local_courseinsights'),
                'healthgrade'           => $health['healthgrade'],
                'healthgradeclass'      => $health['healthgradeclass'],
                'healthscoretooltip'    => get_string('healthscore', 'local_courseinsights', $health['healthscore']),
                'completionrate'        => $completionrate ?? 0,
                'completionratedisplay' => $completionrate !== null ? $completionrate . '%' : '-',
                'completionratewidth'   => ($completionrate !== null ? (int)$completionrate : 0) . '%',
                'haslastactivity'       => $lastdisplay !== null,
                'lastactivitysubtitle'  => $lastdisplay !== null
                    ? get_string('lastactivitylabel', 'local_courseinsights') . ': ' . $lastdisplay
                    : '',
                'detailedreportlabel'   => get_string('detailedreport', 'local_courseinsights'),
                'meta' => [
                    [
                        'label' => get_string('enrolledstudents', 'local_courseinsights'),
                        'value' => (string)($record->enrolledstudents ?? 0),
                    ],
                    [
                        'label' => get_string('teachers', 'local_courseinsights'),
                        'value' => (string)($record->teachers ?? '-'),
                    ],
                    [
                        'label' => get_string('assignments', 'local_courseinsights'),
                        'value' => (string)($record->assignments ?? 0),
                    ],
                    [
                        'label' => get_string('quizattempts', 'local_courseinsights'),
                        'value' => (string)($record->quizattempts ?? 0),
                    ],
                ],
            ];
        }

        return $cards;
    }

    /**
     * Loads full detail data for a single course, used by the course detail page.
     *
     * @param int $courseid
     * @return \stdClass|null
     */
    public static function get_course_detail(int $courseid): ?\stdClass {
        global $DB;

        if ($courseid <= 0) {
            return null;
        }

        $roleids = self::get_student_role_ids();
        [$roleinsql1, $rp1] = $DB->get_in_or_equal($roleids, SQL_PARAMS_NAMED, 'drid1_');
        [$roleinsql2, $rp2] = $DB->get_in_or_equal($roleids, SQL_PARAMS_NAMED, 'drid2_');
        [$roleinsql3, $rp3] = $DB->get_in_or_equal($roleids, SQL_PARAMS_NAMED, 'drid3_');
        [$roleinsql4, $rp4] = $DB->get_in_or_equal($roleids, SQL_PARAMS_NAMED, 'drid4_');

        $params = array_merge([
            'sitecourse' => SITEID,
            'courseid'   => $courseid,
            'dctx1'      => CONTEXT_COURSE,
            'dctx2'      => CONTEXT_COURSE,
            'dctx3'      => CONTEXT_COURSE,
            'dctx4'      => CONTEXT_COURSE,
        ], $rp1, $rp2, $rp3, $rp4);

        $sql = "
            SELECT
                c.id,
                c.fullname,

                (
                    SELECT COUNT(DISTINCT ue.userid)
                      FROM {enrol} e
                      JOIN {user_enrolments} ue ON ue.enrolid = e.id
                      JOIN {user} u ON u.id = ue.userid
                      JOIN {context} ctx ON ctx.instanceid = e.courseid AND ctx.contextlevel = :dctx1
                      JOIN {role_assignments} ra ON ra.contextid = ctx.id AND ra.userid = u.id AND ra.roleid {$roleinsql1}
                     WHERE e.courseid = c.id
                       AND e.status = 0
                       AND ue.status = 0
                       AND u.deleted = 0
                       AND u.suspended = 0
                ) AS enrolledstudents,

                (
                    SELECT ROUND(
                        COUNT(DISTINCT CASE WHEN cc.timecompleted IS NOT NULL THEN ue.userid END)
                        * 100.0
                        / NULLIF(COUNT(DISTINCT ue.userid), 0),
                        1
                    )
                      FROM {enrol} e
                      JOIN {user_enrolments} ue ON ue.enrolid = e.id
                      JOIN {user} u ON u.id = ue.userid
                      JOIN {context} ctx ON ctx.instanceid = e.courseid AND ctx.contextlevel = :dctx2
                      JOIN {role_assignments} ra ON ra.contextid = ctx.id AND ra.userid = u.id AND ra.roleid {$roleinsql2}
                      LEFT JOIN {course_completions} cc ON cc.course = e.courseid AND cc.userid = ue.userid
                     WHERE e.courseid = c.id
                       AND e.status = 0
                       AND ue.status = 0
                       AND u.deleted = 0
                       AND u.suspended = 0
                ) AS completionrate,

                (
                    SELECT COUNT(DISTINCT s.userid)
                      FROM {assign} a
                      JOIN {assign_submission} s ON s.assignment = a.id
                      JOIN {user} u ON u.id = s.userid
                      JOIN {context} ctx ON ctx.instanceid = a.course AND ctx.contextlevel = :dctx3
                      JOIN {role_assignments} ra ON ra.contextid = ctx.id AND ra.userid = u.id AND ra.roleid {$roleinsql3}
                     WHERE a.course = c.id
                       AND s.latest = 1
                       AND s.status = 'submitted'
                       AND u.deleted = 0
                       AND u.suspended = 0
                ) AS submittedassignments,

                (
                    SELECT COUNT(DISTINCT qa.userid)
                      FROM {quiz} q
                      JOIN {quiz_attempts} qa ON qa.quiz = q.id
                      JOIN {user} u ON u.id = qa.userid
                      JOIN {context} ctx ON ctx.instanceid = q.course AND ctx.contextlevel = :dctx4
                      JOIN {role_assignments} ra ON ra.contextid = ctx.id AND ra.userid = u.id AND ra.roleid {$roleinsql4}
                     WHERE q.course = c.id
                       AND qa.state = 'finished'
                       AND u.deleted = 0
                       AND u.suspended = 0
                ) AS quizattempts,

                (SELECT COUNT(*) FROM {assign} a WHERE a.course = c.id) AS assignments,
                (SELECT COUNT(*) FROM {quiz} q  WHERE q.course = c.id) AS quizzes,
                (SELECT COUNT(*) FROM {forum} f  WHERE f.course = c.id) AS forumactivities,

                (
                    SELECT MAX(la.timeaccess)
                      FROM {user_lastaccess} la
                     WHERE la.courseid = c.id
                ) AS lastactivity,

                NULL AS teachers

            FROM {course} c
            WHERE c.id = :courseid
              AND c.id <> :sitecourse
              AND c.visible = 1
        ";

        $record = $DB->get_record_sql($sql, $params);
        if (!$record) {
            return null;
        }

        $records = self::attach_teacher_names([(int) $record->id => $record]);
        return reset($records) ?: null;
    }

    /**
     * Returns grade distribution buckets (0–9 %, 10–19 %, … 90–100 %) for a course.
     *
     * Aggregates final quiz grades ({quiz_grades}) and the latest assignment
     * grade ({assign_grades}) across all graded activities in the course.
     * Each grade is expressed as a percentage of the activity's maximum grade
     * and placed in one of 10 equal-width buckets.
     *
     * Returns an empty array when no grade data exists.
     *
     * @param int $courseid
     * @return array  Each element: {label, count, pct, heightpct}
     */
    public static function get_grade_distribution(int $courseid): array {
        global $DB;

        $buckets = array_fill(0, 10, 0);

        // Final quiz grades (one row per user per quiz).
        $quizgrades = $DB->get_records_sql('
            SELECT qg.id, qg.grade, q.grade AS maxgrade
              FROM {quiz_grades} qg
              JOIN {quiz} q ON q.id = qg.quiz
             WHERE q.course   = :cid1
               AND q.grade    > 0
               AND qg.grade   IS NOT NULL
               AND qg.grade   >= 0
        ', ['cid1' => $courseid]);

        // Latest assignment grade per user per assignment.
        $assigngrades = $DB->get_records_sql('
            SELECT ag.id, ag.grade, a.grade AS maxgrade
              FROM {assign_grades} ag
              JOIN {assign} a ON a.id = ag.assignment
             WHERE a.course           = :cid2
               AND a.grade            > 0
               AND ag.grade           IS NOT NULL
               AND ag.grade           >= 0
               AND ag.attemptnumber   = (
                   SELECT MAX(ag2.attemptnumber)
                     FROM {assign_grades} ag2
                    WHERE ag2.assignment = ag.assignment
                      AND ag2.userid     = ag.userid
               )
        ', ['cid2' => $courseid]);

        $all = array_merge(array_values($quizgrades), array_values($assigngrades));

        if (empty($all)) {
            return [];
        }

        foreach ($all as $row) {
            $max = (float) $row->maxgrade;
            if ($max <= 0) {
                continue;
            }
            $pct = max(0.0, min(100.0, (float) $row->grade / $max * 100.0));
            $idx = (int) floor($pct / 10);
            if ($idx >= 10) {
                $idx = 9;
            }
            $buckets[$idx]++;
        }

        $total = array_sum($buckets);

        if ($total === 0) {
            return [];
        }

        $maxcount = max($buckets);
        $labels   = ['0–9', '10–19', '20–29', '30–39', '40–49',
                     '50–59', '60–69', '70–79', '80–89', '90–100'];
        $result   = [];

        foreach ($buckets as $i => $count) {
            $result[] = [
                'label'     => $labels[$i] . '%',
                'count'     => $count,
                'pct'       => round($count / $total * 100, 1),
                'heightpct' => $maxcount > 0 ? (int) round($count / $maxcount * 100) : 0,
            ];
        }

        return $result;
    }

    /**
     * Returns a per-student activity summary for a course (admin/manager only).
     *
     * Queries enrolled students by configured student role IDs, their last
     * access to this course, count of submitted assignments, and count of
     * finished quiz attempts. Each user appears exactly once (EXISTS subquery
     * for role check avoids duplicates from multiple role assignments).
     *
     * @param int $courseid
     * @return array Each element: {fullname, lastaccess, hasnoaccess, submissions, quizattempts}
     */
    public static function get_student_activity_table(int $courseid): array {
        global $DB;

        $roleidsraw = get_config('local_courseinsights', 'studentroleids') ?: '5,11,25';
        $roleids    = array_filter(array_map('intval', explode(',', $roleidsraw)));

        if (empty($roleids)) {
            return [];
        }

        [$roleinsql, $roleparams] = $DB->get_in_or_equal($roleids, SQL_PARAMS_NAMED, 'strid_');

        $params = array_merge([
            'enrol_course' => $courseid,
            'la_course'    => $courseid,
            'ctx_course'   => $courseid,
            'ctx_level'    => CONTEXT_COURSE,
            'sub_course'   => $courseid,
            'qa_course'    => $courseid,
        ], $roleparams);

        $rows = $DB->get_records_sql("
            SELECT u.id,
                   u.firstname,
                   u.lastname,
                   u.firstnamephonetic,
                   u.lastnamephonetic,
                   u.middlename,
                   u.alternatename,
                   la.timeaccess                                        AS lastaccess,
                   (
                       SELECT COUNT(*)
                         FROM {assign_submission} asub
                         JOIN {assign} a ON a.id = asub.assignment
                        WHERE a.course      = :sub_course
                          AND asub.userid   = u.id
                          AND asub.status   = 'submitted'
                          AND asub.latest   = 1
                   )                                                    AS submissions,
                   (
                       SELECT COUNT(*)
                         FROM {quiz_attempts} qa2
                         JOIN {quiz} q ON q.id = qa2.quiz
                        WHERE q.course   = :qa_course
                          AND qa2.userid = u.id
                          AND qa2.state  = 'finished'
                   )                                                    AS quizattempts
              FROM {user} u
              JOIN {user_enrolments} ue ON ue.userid = u.id
              JOIN {enrol} e            ON e.id = ue.enrolid AND e.courseid = :enrol_course
              LEFT JOIN {user_lastaccess} la ON la.userid = u.id AND la.courseid = :la_course
             WHERE u.deleted   = 0
               AND u.suspended = 0
               AND ue.status   = 0
               AND EXISTS (
                       SELECT 1
                         FROM {role_assignments} ra
                         JOIN {context} ctx ON ctx.id = ra.contextid
                        WHERE ra.userid        = u.id
                          AND ctx.instanceid   = :ctx_course
                          AND ctx.contextlevel = :ctx_level
                          AND ra.roleid        $roleinsql
               )
             ORDER BY la.timeaccess DESC, u.lastname ASC, u.firstname ASC
        ", $params);

        if (empty($rows)) {
            return [];
        }

        $result = [];
        foreach ($rows as $row) {
            $result[] = [
                'fullname'     => fullname($row),
                'lastaccess'   => !empty($row->lastaccess)
                    ? userdate((int)$row->lastaccess, get_string('strftimedate', 'langconfig'))
                    : '-',
                'hasnoaccess'  => empty($row->lastaccess),
                'submissions'  => (int) $row->submissions,
                'quizattempts' => (int) $row->quizattempts,
            ];
        }

        return $result;
    }

    /**
     * Returns per-quiz grade statistics for a course.
     *
     * Uses quiz_grades (one row per student, best grade) for avg/min/max.
     * Pass rate is derived from grade_items.gradepass; displayed as '—' when
     * no pass mark has been configured for a quiz.
     *
     * @param int $courseid
     * @return array Each element: {quizname, attempts, avgpct, minpct, maxpct, passrate}, or [].
     */
    public static function get_quiz_score_breakdown(int $courseid): array {
        global $DB;

        $rows = $DB->get_records_sql("
            SELECT q.id,
                   q.name,
                   q.grade                                              AS maxgrade,
                   COUNT(qg.userid)                                     AS attempts,
                   AVG(qg.grade)                                        AS avggrade,
                   MIN(qg.grade)                                        AS mingrade,
                   MAX(qg.grade)                                        AS maxachieved,
                   (SELECT MAX(COALESCE(gi.gradepass, 0))
                      FROM {grade_items} gi
                     WHERE gi.itemmodule   = 'quiz'
                       AND gi.iteminstance = q.id
                       AND gi.itemtype     = 'mod')                     AS gradepass,
                   (SELECT COUNT(qg2.userid)
                      FROM {quiz_grades} qg2
                      JOIN {grade_items} gi2
                        ON gi2.itemmodule   = 'quiz'
                       AND gi2.iteminstance = q.id
                       AND gi2.itemtype     = 'mod'
                     WHERE qg2.quiz        = q.id
                       AND gi2.gradepass   > 0
                       AND qg2.grade       >= gi2.gradepass)            AS passcount
              FROM {quiz} q
              JOIN {quiz_grades} qg ON qg.quiz = q.id
             WHERE q.course = :courseid
               AND q.grade  > 0
             GROUP BY q.id, q.name, q.grade
             ORDER BY q.name ASC
        ", ['courseid' => $courseid]);

        if (empty($rows)) {
            return [];
        }

        $result = [];
        foreach ($rows as $row) {
            $maxgrade  = (float) $row->maxgrade;
            $attempts  = (int) $row->attempts;
            $passcount = (int) $row->passcount;
            $gradepass = (float) $row->gradepass;

            $avgpct = $maxgrade > 0 ? round((float) $row->avggrade / $maxgrade * 100, 1) : 0.0;
            $minpct = $maxgrade > 0 ? round((float) $row->mingrade / $maxgrade * 100, 1) : 0.0;
            $maxpct = $maxgrade > 0 ? round((float) $row->maxachieved / $maxgrade * 100, 1) : 0.0;

            $passrate = ($gradepass > 0 && $attempts > 0)
                ? number_format(round($passcount / $attempts * 100, 1), 1) . '%'
                : '—';

            $result[] = [
                'quizname' => format_string($row->name),
                'attempts' => $attempts,
                'avgpct'   => number_format($avgpct, 1) . '%',
                'minpct'   => number_format($minpct, 1) . '%',
                'maxpct'   => number_format($maxpct, 1) . '%',
                'passrate' => $passrate,
            ];
        }

        return $result;
    }

    /**
     * Returns a 30-day assignment submission timeline for a course.
     *
     * Counts final submitted assignments (latest attempt, status = submitted)
     * per day using timemodified as the submission date.
     *
     * @param int $courseid
     * @return array Each element: {label, count, heightpct, showlabel, tooltip}, or [] if no data.
     */
    public static function get_submission_timeline(int $courseid): array {
        global $DB;

        $today = mktime(0, 0, 0);
        $since = $today - 29 * DAYSECS; // 30 days including today.

        $rows = $DB->get_recordset_sql("
            SELECT asub.timemodified
              FROM {assign_submission} asub
              JOIN {assign} a ON a.id = asub.assignment
             WHERE a.course          = :courseid
               AND asub.status       = 'submitted'
               AND asub.latest       = 1
               AND asub.timemodified >= :since
             ORDER BY asub.timemodified ASC
        ", ['courseid' => $courseid, 'since' => $since]);

        $lookup = [];
        foreach ($rows as $row) {
            $subday = date('Y-m-d', (int) $row->timemodified);
            $lookup[$subday] = ($lookup[$subday] ?? 0) + 1;
        }
        $rows->close();

        if (empty($lookup)) {
            return [];
        }

        // Build full 30-day array, filling zeros for days with no submissions.
        $days = [];
        for ($i = 0; $i < 30; $i++) {
            $ts       = $today - (29 - $i) * DAYSECS;
            $datestr  = date('Y-m-d', $ts);
            $count    = $lookup[$datestr] ?? 0;
            $days[]   = ['ts' => $ts, 'datestr' => $datestr, 'count' => $count, 'idx' => $i];
        }

        $maxcount = max(array_column($days, 'count'));
        if ($maxcount === 0) {
            return [];
        }

        $result = [];
        foreach ($days as $day) {
            $count      = $day['count'];
            $showlabel  = ($day['idx'] % 5 === 0) || ($day['idx'] === 29);
            $result[]   = [
                'label'     => date('M j', $day['ts']),
                'count'     => $count,
                'heightpct' => (int) round($count / $maxcount * 100),
                'showlabel' => $showlabel,
                'tooltip'   => $day['datestr'] . ': ' . $count . ($count === 1 ? ' submission' : ' submissions'),
            ];
        }

        return $result;
    }

    /**
     * Returns a 52-week calendar heatmap of daily event counts for a course.
     *
     * Reads bounded log rows and groups them in PHP so the query remains portable.
     *
     * @param int $courseid The course ID.
     * @return array Heatmap data as weekly groups, or empty array if no log data exists.
     */
    public static function get_engagement_heatmap(int $courseid): array {
        global $DB;

        $today     = mktime(0, 0, 0);
        $dow       = (int) date('N', $today); // 1 = Mon, 7 = Sun.
        $weekstart = $today - ($dow - 1) * DAYSECS;
        $startday  = $weekstart - 51 * 7 * DAYSECS; // 52 weeks total.

        $rows = $DB->get_recordset_sql('
            SELECT timecreated
              FROM {logstore_standard_log}
             WHERE courseid = :courseid
               AND timecreated >= :since
               AND userid > 0
             ORDER BY timecreated ASC
        ', ['courseid' => $courseid, 'since' => $startday]);

        $lookup = [];
        foreach ($rows as $row) {
            $logday = date('Y-m-d', (int) $row->timecreated);
            $lookup[$logday] = ($lookup[$logday] ?? 0) + 1;
        }
        $rows->close();

        if (empty($lookup)) {
            return [];
        }

        // Quartile-based colour intensity from non-zero day counts.
        $nonzero = array_values(array_filter(array_values($lookup)));
        sort($nonzero);
        $n  = count($nonzero);
        $q1 = $n > 0 ? $nonzero[(int) floor($n * 0.25)] : 1;
        $q2 = $n > 0 ? $nonzero[(int) floor($n * 0.50)] : 2;
        $q3 = $n > 0 ? $nonzero[(int) floor($n * 0.75)] : 3;

        $weeks  = [];
        $cursor = $startday;

        for ($w = 0; $w < 52; $w++) {
            $days = [];
            for ($d = 0; $d < 7; $d++) {
                $isfuture = $cursor > $today;
                $datestr  = date('Y-m-d', $cursor);
                $count    = !$isfuture ? ($lookup[$datestr] ?? 0) : 0;

                if ($isfuture) {
                    $levelclass = 'ci-heatmap__cell--future';
                } else if ($count === 0) {
                    $levelclass = 'ci-heatmap__cell--level-0';
                } else if ($count <= $q1) {
                    $levelclass = 'ci-heatmap__cell--level-1';
                } else if ($count <= $q2) {
                    $levelclass = 'ci-heatmap__cell--level-2';
                } else if ($count <= $q3) {
                    $levelclass = 'ci-heatmap__cell--level-3';
                } else {
                    $levelclass = 'ci-heatmap__cell--level-4';
                }

                $days[] = [
                    'date'       => $isfuture ? '' : $datestr,
                    'count'      => $count,
                    'tooltip'    => $isfuture ? '' : ($datestr . ': ' . $count),
                    'levelclass' => $levelclass,
                ];
                $cursor += DAYSECS;
            }
            $weeks[] = ['days' => $days];
        }

        return $weeks;
    }

    /**
     * Returns 30-day vs previous-30-day trend data for a single course.
     *
     * @param int $courseid
     * @return array Flat array of trend_* keys ready to merge into template context.
     */
    public static function get_course_trend(int $courseid): array {
        global $DB;

        $now       = time();
        $currstart = $now - 30 * DAYSECS;
        $prevstart = $now - 60 * DAYSECS;
        $prevend   = $currstart;

        $sql = "SELECT COUNT(DISTINCT userid) FROM {logstore_standard_log}
                 WHERE courseid = :cid AND timecreated >= :ts AND timecreated < :te AND userid > 0";
        $activecurr = (int) $DB->count_records_sql($sql, ['cid' => $courseid, 'ts' => $currstart, 'te' => $now]);
        $activeprev = (int) $DB->count_records_sql($sql, ['cid' => $courseid, 'ts' => $prevstart, 'te' => $prevend]);

        $sql = "SELECT COUNT(*) FROM {assign_submission} s
                  JOIN {assign} a ON a.id = s.assignment
                 WHERE a.course = :cid AND s.status = 'submitted'
                   AND s.timemodified >= :ts AND s.timemodified < :te";
        $subcurr = (int) $DB->count_records_sql($sql, ['cid' => $courseid, 'ts' => $currstart, 'te' => $now]);
        $subprev = (int) $DB->count_records_sql($sql, ['cid' => $courseid, 'ts' => $prevstart, 'te' => $prevend]);

        $sql = "SELECT COUNT(*) FROM {quiz_attempts} qa
                  JOIN {quiz} q ON q.id = qa.quiz
                 WHERE q.course = :cid AND qa.state = 'finished'
                   AND qa.timefinish >= :ts AND qa.timefinish < :te";
        $quizcurr = (int) $DB->count_records_sql($sql, ['cid' => $courseid, 'ts' => $currstart, 'te' => $now]);
        $quizprev = (int) $DB->count_records_sql($sql, ['cid' => $courseid, 'ts' => $prevstart, 'te' => $prevend]);

        return [
            'trend_active_curr'  => $activecurr,
            'trend_active_delta' => abs($activecurr - $activeprev),
            'trend_active_up'    => $activecurr > $activeprev,
            'trend_active_flat'  => $activecurr === $activeprev,
            'trend_subs_curr'    => $subcurr,
            'trend_subs_delta'   => abs($subcurr - $subprev),
            'trend_subs_up'      => $subcurr > $subprev,
            'trend_subs_flat'    => $subcurr === $subprev,
            'trend_quiz_curr'    => $quizcurr,
            'trend_quiz_delta'   => abs($quizcurr - $quizprev),
            'trend_quiz_up'      => $quizcurr > $quizprev,
            'trend_quiz_flat'    => $quizcurr === $quizprev,
        ];
    }

    /**
     * Returns site-wide KPI numbers for the Site Overview page.
     *
     * @return array
     */
    public static function get_site_kpis(): array {
        global $DB;

        $cutoff30 = time() - (30 * DAYSECS);

        $totalcourses = (int) $DB->count_records_select('course', 'id <> :site AND visible = 1', ['site' => SITEID]);

        $totalenrolments = (int) $DB->get_field_sql(
            "SELECT COUNT(ue.id)
               FROM {user_enrolments} ue
               JOIN {enrol} e ON e.id = ue.enrolid
               JOIN {user} u ON u.id = ue.userid
              WHERE e.status = 0 AND ue.status = 0 AND u.deleted = 0
                AND e.courseid <> :site",
            ['site' => SITEID]
        );

        $coursecompletions = (int) $DB->count_records_select(
            'course_completions',
            'timecompleted IS NOT NULL AND timecompleted > 0'
        );

        $activitycompletions = (int) $DB->count_records_select(
            'course_modules_completion',
            'completionstate > 0'
        );

        $newusers30 = (int) $DB->count_records_select(
            'user',
            'deleted = 0 AND confirmed = 1 AND timecreated > :cutoff',
            ['cutoff' => $cutoff30]
        );

        $activeusers30 = (int) $DB->get_field_sql(
            "SELECT COUNT(DISTINCT la.userid)
               FROM {user_lastaccess} la
               JOIN {user} u ON u.id = la.userid
              WHERE u.deleted = 0 AND la.timeaccess > :cutoff",
            ['cutoff' => $cutoff30]
        );

        return [
            'kpi_totalcourses'        => $totalcourses,
            'kpi_enrolments'          => $totalenrolments,
            'kpi_coursecompletions'   => $coursecompletions,
            'kpi_activitycompletions' => $activitycompletions,
            'kpi_newusers'            => $newusers30,
            'kpi_activeusers'         => $activeusers30,
        ];
    }

    /**
     * Returns the top N visible courses ordered by active student enrolment count.
     *
     * @param int $limit
     * @return array
     */
    public static function get_top_courses_by_enrolment(int $limit = 10): array {
        global $DB;

        $rows = $DB->get_records_sql(
            "SELECT c.id, c.fullname, COUNT(ue.id) AS enrolcount
               FROM {course} c
               JOIN {enrol} e ON e.courseid = c.id AND e.status = 0
               JOIN {user_enrolments} ue ON ue.enrolid = e.id AND ue.status = 0
               JOIN {user} u ON u.id = ue.userid AND u.deleted = 0
              WHERE c.id <> :site AND c.visible = 1
              GROUP BY c.id, c.fullname
              ORDER BY COUNT(ue.id) DESC",
            ['site' => SITEID],
            0,
            $limit
        );

        $result = [];
        $rank = 1;
        foreach ($rows as $r) {
            $result[] = [
                'rank'       => $rank++,
                'coursename' => format_string($r->fullname),
                'count'      => (int) $r->enrolcount,
            ];
        }
        return $result;
    }

    /**
     * Returns the top N visible courses by course completion rate,
     * considering only courses with at least one enrolled student.
     *
     * @param int $limit
     * @return array
     */
    /**
     * Returns monthly active user counts from the standard log for the last N months.
     *
     * Uses a bounded, streamed log query and groups timestamps in PHP for database portability.
     *
     * @param int $months Number of months to look back.
     * @return array Each element: ['label', 'active_users', 'events'].
     */
    public static function get_monthly_active_users(int $months = 12): array {
        global $DB;

        $months = max(1, min(24, $months));
        $cutoff = strtotime("-{$months} months");

        $rows = $DB->get_recordset_sql(
            "SELECT userid, timecreated
               FROM {logstore_standard_log}
              WHERE courseid <> :site
                AND userid > 0
                AND timecreated > :cutoff
              ORDER BY timecreated ASC",
            ['site' => SITEID, 'cutoff' => $cutoff]
        );

        $buckets = [];
        foreach ($rows as $r) {
            $yearmonth = date('Y-m', (int) $r->timecreated);
            if (!isset($buckets[$yearmonth])) {
                $buckets[$yearmonth] = [
                    'users' => [],
                    'events' => 0,
                ];
            }
            $buckets[$yearmonth]['users'][(int) $r->userid] = true;
            $buckets[$yearmonth]['events']++;
        }
        $rows->close();

        $result = [];
        foreach ($buckets as $yearmonth => $bucket) {
            [$year, $month] = explode('-', $yearmonth);
            $result[] = [
                'label'        => date('M Y', mktime(0, 0, 0, (int) $month, 1, (int) $year)),
                'active_users' => count($bucket['users']),
                'events'       => $bucket['events'],
            ];
        }
        return $result;
    }

    /**
     * Returns enrolled students who have not accessed an enrolled course in $days days
     * and have not completed it. Ordered by longest inactive first. Capped at $limit rows.
     *
     * @param int $days Inactivity threshold.
     * @param int $limit Max rows.
     * @return array Each element: ['userid', 'fullname', 'courseid', 'coursename', 'courseurl', 'lastaccess', 'daysinactive'].
     */
    public static function get_atrisk_students(int $days = 14, int $limit = 25): array {
        global $DB;

        $days   = max(1, $days);
        $cutoff = time() - ($days * DAYSECS);
        $now    = time();

        $rows = $DB->get_recordset_sql(
            "SELECT u.id AS userid, u.firstname, u.lastname,
                    c.id AS courseid, c.fullname AS coursefullname,
                    COALESCE(la.timeaccess, 0) AS lastaccess
               FROM {enrol} e
               JOIN {user_enrolments} ue ON ue.enrolid = e.id AND ue.status = 0
               JOIN {user} u
                      ON u.id = ue.userid
                     AND u.deleted = 0 AND u.confirmed = 1 AND u.suspended = 0
               JOIN {course} c ON c.id = e.courseid AND c.id <> :site AND c.visible = 1
               LEFT JOIN {user_lastaccess} la ON la.userid = ue.userid AND la.courseid = e.courseid
               LEFT JOIN (
                   SELECT course AS courseid, userid
                     FROM {course_completions}
                    WHERE timecompleted IS NOT NULL AND timecompleted > 0
               ) cc ON cc.courseid = e.courseid AND cc.userid = ue.userid
              WHERE e.status = 0
                AND cc.userid IS NULL
                AND (la.timeaccess IS NULL OR la.timeaccess < :cutoff)
              ORDER BY COALESCE(la.timeaccess, 0) ASC",
            ['site' => SITEID, 'cutoff' => $cutoff],
            0,
            $limit
        );

        $result = [];
        foreach ($rows as $r) {
            $daysinactive = $r->lastaccess > 0
                ? (int) floor(($now - $r->lastaccess) / DAYSECS)
                : null;
            $result[] = [
                'userid'       => (int) $r->userid,
                'fullname'     => trim($r->firstname . ' ' . $r->lastname),
                'courseid'     => (int) $r->courseid,
                'coursename'   => format_string($r->coursefullname),
                'courseurl'    => (new \moodle_url('/course/view.php', ['id' => $r->courseid]))->out(false),
                'lastaccess'   => $r->lastaccess > 0 ? userdate((int) $r->lastaccess) : '-',
                'daysinactive' => $daysinactive !== null ? $daysinactive : '-',
            ];
        }
        $rows->close();

        return $result;
    }

    /**
     * Returns at-risk students from the overnight snapshot table.
     *
     * @param int $days Inactivity threshold.
     * @param int $limit Max rows.
     * @return array Each element: ['userid', 'fullname', 'courseid', 'coursename', 'courseurl', 'lastaccess', 'daysinactive'].
     */
    public static function get_atrisk_students_from_snapshot(int $days = 14, int $limit = 25): array {
        global $DB;

        $days = max(1, $days);
        $rows = $DB->get_records_sql(
            "SELECT a.id,
                    a.userid,
                    u.firstname,
                    u.lastname,
                    u.firstnamephonetic,
                    u.lastnamephonetic,
                    u.middlename,
                    u.alternatename,
                    c.id AS courseid,
                    c.fullname AS coursefullname,
                    a.lastaccess,
                    a.daysinactive
               FROM {local_courseinsights_atrisk} a
               JOIN {user} u ON u.id = a.userid AND u.deleted = 0
               JOIN {course} c ON c.id = a.courseid AND c.visible = 1
              WHERE a.threshold = :threshold
              ORDER BY a.daysinactive DESC, a.lastaccess ASC",
            ['threshold' => $days],
            0,
            $limit
        );

        $result = [];
        foreach ($rows as $row) {
            $lastaccess = (int) $row->lastaccess;
            $result[] = [
                'userid' => (int) $row->userid,
                'fullname' => fullname($row),
                'courseid' => (int) $row->courseid,
                'coursename' => format_string($row->coursefullname),
                'courseurl' => (new \moodle_url('/course/view.php', ['id' => $row->courseid]))->out(false),
                'lastaccess' => $lastaccess > 0 ? userdate($lastaccess) : get_string('atrisk_never', 'local_courseinsights'),
                'daysinactive' => $row->daysinactive !== null ? (int) $row->daysinactive : '-',
            ];
        }

        return $result;
    }

    /**
     * Returns per-course progress for a specific user: enrolments, completion, last access, grade.
     *
     * @param int $userid
     * @return array Each element: ['courseid', 'coursename', 'courseurl', 'lastaccessfmt', 'statuslabel', 'grade', ...].
     */
    public static function get_user_progress(int $userid): array {
        global $DB;

        if (!$userid) {
            return [];
        }

        $rows = $DB->get_records_sql(
            "SELECT c.id AS courseid, c.fullname,
                    COALESCE(la.timeaccess, 0) AS lastaccess,
                    CASE WHEN (cc.timecompleted IS NOT NULL AND cc.timecompleted > 0) THEN 1 ELSE 0 END AS completed,
                    gg.finalgrade,
                    gi.grademax
               FROM {enrol} e
               JOIN {user_enrolments} ue ON ue.enrolid = e.id AND ue.userid = :uid AND ue.status = 0
               JOIN {course} c ON c.id = e.courseid AND c.id <> :site AND c.visible = 1
               LEFT JOIN {user_lastaccess} la ON la.userid = :uid2 AND la.courseid = e.courseid
               LEFT JOIN {course_completions} cc ON cc.userid = :uid3 AND cc.course = e.courseid
               LEFT JOIN {grade_items} gi ON gi.courseid = e.courseid AND gi.itemtype = 'course'
               LEFT JOIN {grade_grades} gg ON gg.itemid = gi.id AND gg.userid = :uid4
              WHERE e.status = 0
              GROUP BY c.id, c.fullname, la.timeaccess, cc.timecompleted, gg.finalgrade, gi.grademax
              ORDER BY COALESCE(la.timeaccess, 0) DESC",
            ['uid' => $userid, 'site' => SITEID, 'uid2' => $userid, 'uid3' => $userid, 'uid4' => $userid]
        );

        $result = [];
        foreach ($rows as $r) {
            $grade     = null;
            if ($r->finalgrade !== null && $r->grademax > 0) {
                $grade = round((float) $r->finalgrade / (float) $r->grademax * 100, 1);
            }
            $completed = (bool)(int) $r->completed;
            if ($completed) {
                $status = 'completed';
            } else if ($r->lastaccess > 0) {
                $status = 'inprogress';
            } else {
                $status = 'notstarted';
            }
            $result[] = [
                'courseid'          => (int) $r->courseid,
                'coursename'        => format_string($r->fullname),
                'courseurl'         => (new \moodle_url('/course/view.php', ['id' => $r->courseid]))->out(false),
                'lastaccess'        => (int) $r->lastaccess,
                'lastaccessfmt'     => $r->lastaccess > 0 ? userdate((int) $r->lastaccess) : get_string('never'),
                'completed'         => $completed,
                'status'            => $status,
                'statuslabel'       => get_string('userstatus_' . $status, 'local_courseinsights'),
                'grade'             => $grade !== null ? number_format($grade, 1) . '%' : '-',
                'statuscompleted'   => $status === 'completed',
                'statusinprogress'  => $status === 'inprogress',
                'statusnotstarted'  => $status === 'notstarted',
            ];
        }
        return $result;
    }

    /**
     * Returns the top N courses ranked by completion percentage.
     *
     * @param  int   $limit Maximum number of courses to return.
     * @return array        Array of course rows with completion stats.
     */
    public static function get_top_courses_by_completion(int $limit = 10): array {
        global $DB;

        $rows = $DB->get_records_sql(
            "SELECT c.id, c.fullname,
                    COUNT(DISTINCT ue.userid) AS enrolled,
                    COUNT(DISTINCT cc.userid) AS completed
               FROM {course} c
               JOIN {enrol} e ON e.courseid = c.id AND e.status = 0
               JOIN {user_enrolments} ue ON ue.enrolid = e.id AND ue.status = 0
               JOIN {user} u ON u.id = ue.userid AND u.deleted = 0
               LEFT JOIN (
                   SELECT course AS courseid, userid
                     FROM {course_completions}
                    WHERE timecompleted IS NOT NULL AND timecompleted > 0
               ) cc ON cc.courseid = c.id AND cc.userid = ue.userid
              WHERE c.id <> :site AND c.visible = 1
              GROUP BY c.id, c.fullname
             HAVING COUNT(DISTINCT ue.userid) > 0
              ORDER BY COUNT(DISTINCT cc.userid) * 100 / COUNT(DISTINCT ue.userid) DESC",
            ['site' => SITEID],
            0,
            $limit
        );

        $result = [];
        $rank = 1;
        foreach ($rows as $r) {
            $enrolled  = (int) $r->enrolled;
            $completed = (int) $r->completed;
            $pct       = $enrolled > 0 ? round($completed / $enrolled * 100, 1) : 0;
            $result[]  = [
                'rank'       => $rank++,
                'coursename' => format_string($r->fullname),
                'count'      => number_format($pct, 1) . '%',
            ];
        }
        return $result;
    }

    /**
     * Returns per-activity completion counts for courses that have completion tracking enabled.
     * Uses get_fast_modinfo() for activity names (Moodle-cached).
     *
     * @param int $courseid The course ID.
     * @return array Array of activity completion rows with name, completed, enrolled, and rate.
     */
    public static function get_module_completion_funnel(int $courseid): array {
        global $DB;

        $enrolled = (int) $DB->count_records_sql(
            "SELECT COUNT(DISTINCT ue.userid)
               FROM {enrol} e
               JOIN {user_enrolments} ue ON ue.enrolid = e.id AND ue.status = 0
               JOIN {user} u ON u.id = ue.userid AND u.deleted = 0
              WHERE e.courseid = :courseid AND e.status = 0",
            ['courseid' => $courseid]
        );

        if ($enrolled === 0) {
            return [];
        }

        $rows = $DB->get_records_sql(
            "SELECT cm.id AS cmid,
                    m.name AS modtype,
                    COUNT(DISTINCT cmc.userid) AS completions
               FROM {course_modules} cm
               JOIN {modules} m ON m.id = cm.module
               LEFT JOIN {course_modules_completion} cmc
                      ON cmc.coursemoduleid = cm.id AND cmc.completionstate > 0
              WHERE cm.course = :courseid
                AND cm.completion > 0
                AND cm.visible = 1
              GROUP BY cm.id, m.name
              ORDER BY cm.section ASC, cm.added ASC",
            ['courseid' => $courseid]
        );

        if (empty($rows)) {
            return [];
        }

        $modinfo = get_fast_modinfo($courseid);

        $result = [];
        foreach ($rows as $r) {
            $cmid        = (int) $r->cmid;
            $completions = (int) $r->completions;
            $pct         = round($completions / $enrolled * 100, 1);

            $name = ucfirst($r->modtype);
            if (isset($modinfo->cms[$cmid])) {
                $name = format_string($modinfo->cms[$cmid]->name);
            }

            $result[] = [
                'activityname' => $name,
                'modtype'      => ucfirst($r->modtype),
                'completions'  => $completions,
                'enrolled'     => $enrolled,
                'pct'          => number_format($pct, 1),
                'widthpct'     => (int) $pct,
                'islowpct'     => $pct < 50,
            ];
        }
        return $result;
    }

    /**
     * Returns the top N enrolled students ranked by course total grade (grade_grades + grade_items).
     * Uses an EXISTS subquery to avoid duplicate rows when students have multiple enrolment records.
     *
     * @param  int   $courseid Course ID.
     * @param  int   $limit    Maximum number of students to return (default 20).
     * @return array           Array of student rows with rank, fullname, grade, and percentage.
     */
    public static function get_top_students_by_grade(int $courseid, int $limit = 20): array {
        global $DB;

        $sql = "SELECT u.id AS userid,
                       u.firstname,
                       u.lastname,
                       u.firstnamephonetic,
                       u.lastnamephonetic,
                       u.middlename,
                       u.alternatename,
                       gg.finalgrade,
                       gi.grademax
                  FROM {grade_items} gi
                  JOIN {grade_grades} gg ON gg.itemid = gi.id AND gg.finalgrade IS NOT NULL
                  JOIN {user} u ON u.id = gg.userid AND u.deleted = 0
                 WHERE gi.courseid = :courseid
                   AND gi.itemtype = 'course'
                   AND gi.grademax > 0
                   AND EXISTS (
                       SELECT 1
                         FROM {user_enrolments} ue
                         JOIN {enrol} e ON e.id = ue.enrolid AND e.courseid = :courseid2 AND e.status = 0
                        WHERE ue.userid = u.id AND ue.status = 0
                   )
                 ORDER BY gg.finalgrade DESC";

        $rows = $DB->get_records_sql($sql, ['courseid' => $courseid, 'courseid2' => $courseid], 0, $limit);

        if (empty($rows)) {
            return [];
        }

        $badges = ['ci-rank--gold', 'ci-rank--silver', 'ci-rank--bronze'];
        $result = [];
        $rank   = 1;

        foreach ($rows as $r) {
            $pct = round((float)$r->finalgrade / (float)$r->grademax * 100, 1);
            $result[] = [
                'rank'           => $rank,
                'fullname'       => fullname($r),
                'finalgrade'     => number_format((float)$r->finalgrade, 2),
                'grademax'       => number_format((float)$r->grademax, 2),
                'pct'            => number_format($pct, 1),
                'widthpct'       => (int)$pct,
                'rankbadgeclass' => $badges[$rank - 1] ?? '',
                'rankrowclass'   => $rank <= 3 ? 'ci-lb-toprow' : '',
            ];
            $rank++;
        }

        return $result;
    }
}
