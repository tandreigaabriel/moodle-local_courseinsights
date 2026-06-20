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
    private const DEFAULT_PER_PAGE = 50;

    /**
     * Map of sortable column keys to their SQL expressions used in ORDER BY.
     *
     * @var array<string, string>
     */
    private const SORT_COLUMNS = [
        'course'               => 'c.fullname',
        'enrolledstudents'     => 'enrolledstudents',
        'completionrate'       => 'completionrate',
        'teachers'             => 'teachers',
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
     * Returns the course options for the course filter, including the
     * "all courses" option.
     *
     * @return array
     */
    public static function get_course_options(): array {
        global $DB;

        $courses = [0 => get_string('allcourses', 'local_courseinsights')];

        $records = $DB->get_records_sql_menu("
            SELECT id, fullname
              FROM {course}
             WHERE id <> :siteid
               AND visible = 1
             ORDER BY fullname ASC
        ", [
            'siteid' => SITEID,
        ]);

        foreach ($records as $id => $fullname) {
            $courses[$id] = format_string($fullname);
        }

        return $courses;
    }

    /**
     * Returns the category options for the category filter, including the
     * "all categories" option. Categories are ordered by tree path and
     * indented to reflect their hierarchy depth.
     *
     * @return array
     */
    public static function get_category_options(): array {
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

        return $categories;
    }

    /**
     * Reads and validates the report filters from the current request.
     *
     * @return array
     */
    public static function get_filters_from_request(): array {
        return [
            'courseid'      => optional_param('courseid', 0, PARAM_INT),
            'categoryid'    => optional_param('categoryid', 0, PARAM_INT),
            'startdate'     => optional_param('startdate', '', PARAM_RAW_TRIMMED),
            'enddate'       => optional_param('enddate', '', PARAM_RAW_TRIMMED),
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
    public static function get_course_overview(array $filters, int $page = 0, int $perpage = self::DEFAULT_PER_PAGE): array {
        global $DB;

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
                && $sortbyraw === 'course' && $sortdirraw === 'asc'
        ) {
            $all = self::get_cached_course_overview($courseid);
            return $perpage > 0 ? array_slice($all, $page * $perpage, $perpage, true) : $all;
        }

        $params = [
            'sitecourse' => SITEID,
        ];

        // Moodle named params must be unique across the whole query; each subquery
        // that filters by context level gets its own alias (ctx1..ctx7).
        for ($i = 1; $i <= 8; $i++) {
            $params["ctx{$i}"] = CONTEXT_COURSE;
        }
        $params['ctxteacher'] = CONTEXT_COURSE;

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

        $sql = "
            SELECT
                c.id,
                c.fullname,

                (
                    SELECT COUNT(DISTINCT ue.userid)
                      FROM {enrol} e
                      JOIN {user_enrolments} ue ON ue.enrolid = e.id
                      JOIN {user} u ON u.id = ue.userid
                      JOIN {context} ctx ON ctx.instanceid = e.courseid AND ctx.contextlevel = :ctx1
                      JOIN {role_assignments} ra ON ra.contextid = ctx.id AND ra.userid = u.id AND ra.roleid {$roleinsql1}
                     WHERE e.courseid = c.id
                       AND e.status = 0
                       AND ue.status = 0
                       AND u.deleted = 0
                       {$statussql}
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
                      JOIN {context} ctx8 ON ctx8.instanceid = e.courseid AND ctx8.contextlevel = :ctx8
                      JOIN {role_assignments} ra8 ON ra8.contextid = ctx8.id AND ra8.userid = u.id AND ra8.roleid {$roleinsql8}
                      LEFT JOIN {course_completions} cc ON cc.course = e.courseid AND cc.userid = ue.userid
                     WHERE e.courseid = c.id
                       AND e.status = 0
                       AND ue.status = 0
                       AND u.deleted = 0
                       {$statussql}
                ) AS completionrate,

                (
                    SELECT COUNT(*)
                      FROM {assign} a
                     WHERE a.course = c.id
                ) AS assignments,

                (
                    SELECT COUNT(DISTINCT s.userid)
                      FROM {assign} a
                      JOIN {assign_submission} s ON s.assignment = a.id
                      JOIN {user} u ON u.id = s.userid
                      JOIN {context} ctx ON ctx.instanceid = a.course AND ctx.contextlevel = :ctx2
                      JOIN {role_assignments} ra ON ra.contextid = ctx.id AND ra.userid = u.id AND ra.roleid {$roleinsql2}
                     WHERE a.course = c.id
                       AND s.latest = 1
                       AND s.status = 'submitted'
                       AND u.deleted = 0
                       {$statussql}
                       {$submissiondatesql}
                ) AS submittedassignments,

                (
                    SELECT COUNT(*)
                      FROM {quiz} q
                     WHERE q.course = c.id
                ) AS quizzes,

                (
                    SELECT COUNT(DISTINCT qa.userid)
                      FROM {quiz} q
                      JOIN {quiz_attempts} qa ON qa.quiz = q.id
                      JOIN {user} u ON u.id = qa.userid
                      JOIN {context} ctx ON ctx.instanceid = q.course AND ctx.contextlevel = :ctx3
                      JOIN {role_assignments} ra ON ra.contextid = ctx.id AND ra.userid = u.id AND ra.roleid {$roleinsql3}
                     WHERE q.course = c.id
                       AND qa.state = 'finished'
                       AND u.deleted = 0
                       {$statussql}
                       {$attemptdatesql1}
                ) AS quizattempts,

                (
                    SELECT COUNT(*)
                      FROM {quiz} q
                     WHERE q.course = c.id
                       AND {$examsql1}
                ) AS exams,

                (
                    SELECT COUNT(DISTINCT qa.userid)
                      FROM {quiz} q
                      JOIN {quiz_attempts} qa ON qa.quiz = q.id
                      JOIN {user} u ON u.id = qa.userid
                      JOIN {context} ctx ON ctx.instanceid = q.course AND ctx.contextlevel = :ctx4
                      JOIN {role_assignments} ra ON ra.contextid = ctx.id AND ra.userid = u.id AND ra.roleid {$roleinsql4}
                     WHERE q.course = c.id
                       AND qa.state = 'finished'
                       AND {$examsql2}
                       AND u.deleted = 0
                       {$statussql}
                       {$attemptdatesql2}
                ) AS examattempts,

                (
                    SELECT COUNT(*)
                      FROM {quiz} q
                     WHERE q.course = c.id
                       AND {$miniexamsql1}
                ) AS miniquizzes,

                (
                    SELECT COUNT(DISTINCT qa.userid)
                      FROM {quiz} q
                      JOIN {quiz_attempts} qa ON qa.quiz = q.id
                      JOIN {user} u ON u.id = qa.userid
                      JOIN {context} ctx ON ctx.instanceid = q.course AND ctx.contextlevel = :ctx5
                      JOIN {role_assignments} ra ON ra.contextid = ctx.id AND ra.userid = u.id AND ra.roleid {$roleinsql5}
                     WHERE q.course = c.id
                       AND qa.state = 'finished'
                       AND {$miniexamsql2}
                       AND u.deleted = 0
                       {$statussql}
                       {$attemptdatesql3}
                ) AS miniquizattempts,

                (
                    SELECT ROUND(AVG(
                        CASE
                            WHEN q.grade > 0 THEN (qg.grade / q.grade) * 100
                            ELSE NULL
                        END
                    ), 2)
                      FROM {quiz} q
                      JOIN {quiz_grades} qg ON qg.quiz = q.id
                      JOIN {user} u ON u.id = qg.userid
                      JOIN {context} ctx ON ctx.instanceid = q.course AND ctx.contextlevel = :ctx6
                      JOIN {role_assignments} ra ON ra.contextid = ctx.id AND ra.userid = u.id AND ra.roleid {$roleinsql6}
                     WHERE q.course = c.id
                       AND u.deleted = 0
                       {$statussql}
                ) AS avgquizgrade,

                (
                    SELECT MAX(la.timeaccess)
                      FROM {user_lastaccess} la
                      JOIN {user} u ON u.id = la.userid
                      JOIN {context} ctx ON ctx.instanceid = la.courseid AND ctx.contextlevel = :ctx7
                      JOIN {role_assignments} ra ON ra.contextid = ctx.id AND ra.userid = u.id AND ra.roleid {$roleinsql7}
                     WHERE la.courseid = c.id
                       AND u.deleted = 0
                       {$statussql}
                ) AS lastactivity,

                (
                    SELECT GROUP_CONCAT(DISTINCT CONCAT(u.firstname, ' ', u.lastname)
                                        ORDER BY u.lastname SEPARATOR ', ')
                      FROM {role_assignments} ra
                      JOIN {user} u ON u.id = ra.userid
                      JOIN {context} ctx ON ctx.id = ra.contextid
                           AND ctx.instanceid = c.id AND ctx.contextlevel = :ctxteacher
                      JOIN {role} r ON r.id = ra.roleid AND r.shortname = 'editingteacher'
                     WHERE u.deleted = 0
                ) AS teachers

            FROM {course} c
            WHERE {$where}
            {$ordersql}
        ";

        $limitfrom = $perpage > 0 ? $page * $perpage : 0;
        $limitnum  = $perpage > 0 ? $perpage : 0;
        return $DB->get_records_sql($sql, $params, $limitfrom, $limitnum);
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
        $perpage = self::DEFAULT_PER_PAGE;

        if ($totalcount <= $perpage) {
            return ['haspagination' => false];
        }

        $totalpages = (int) ceil($totalcount / $perpage);
        $from = $page * $perpage + 1;
        $to = min(($page + 1) * $perpage, $totalcount);

        $paginationinfo = get_string('pagination_info', 'local_courseinsights', (object)[
            'from'  => $from,
            'to'    => $to,
            'total' => $totalcount,
        ]);

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
     * to a single course.
     *
     * @param int $courseid
     * @return array
     */
    public static function get_cached_course_overview(int $courseid = 0): array {
        global $DB;

        $params = [
            'periodstart' => 0,
            'periodend' => 0,
        ];

        $where = 'periodstart = :periodstart AND periodend = :periodend';

        if ($courseid > 0) {
            $where .= ' AND courseid = :courseid';
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
                s.avgquizgrade
              FROM {local_courseinsights_summary} s
              JOIN {course} c ON c.id = s.courseid
             WHERE {$where}
             ORDER BY c.fullname ASC
        ";

        return $DB->get_records_sql($sql, $params);
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
            $summary->timecreated = $now;
            $summary->timemodified = $now;

            $DB->insert_record('local_courseinsights_summary', $summary);
        }

        $transaction->allow_commit();
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
}
