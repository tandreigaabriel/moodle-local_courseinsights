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
 * Intervention case service for local_courseinsights.
 *
 * @package    local_courseinsights
 * @copyright  2026 Andrei Toma
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_courseinsights;

/**
 * Service class for managing student intervention cases and notes.
 */
class intervention_service {
    /** Workflow status: new case, not yet reviewed. */
    public const STATUS_NEW        = 'new';
    /** Workflow status: in progress. */
    public const STATUS_INPROGRESS = 'inprogress';
    /** Workflow status: student has been contacted. */
    public const STATUS_CONTACTED  = 'contacted';
    /** Workflow status: monitoring the student's progress. */
    public const STATUS_MONITORING = 'monitoring';
    /** Workflow status: case resolved with a positive outcome. */
    public const STATUS_RESOLVED   = 'resolved';
    /** Workflow status: case closed. */
    public const STATUS_CLOSED     = 'closed';

    /** Ordered list of all valid statuses. */
    public const STATUSES = [
        self::STATUS_NEW,
        self::STATUS_INPROGRESS,
        self::STATUS_CONTACTED,
        self::STATUS_MONITORING,
        self::STATUS_RESOLVED,
        self::STATUS_CLOSED,
    ];

    /**
     * Creates a new intervention case and returns its ID.
     *
     * @param int         $userid     Student user ID.
     * @param int         $courseid   Course ID.
     * @param string      $title      Case title.
     * @param int|null    $riskscore  Risk score at time of creation.
     * @param string|null $risklevel  Risk level string.
     * @param int         $createdby  Staff user ID creating the case.
     * @return int New record ID.
     */
    public static function create(
        int $userid,
        int $courseid,
        string $title,
        ?int $riskscore,
        ?string $risklevel,
        int $createdby
    ): int {
        global $DB;

        $now = time();
        return $DB->insert_record('local_courseinsights_interventions', (object) [
            'userid'       => $userid,
            'courseid'     => $courseid,
            'createdby'    => $createdby,
            'assignedto'   => null,
            'title'        => $title,
            'status'       => self::STATUS_NEW,
            'riskscore'    => $riskscore,
            'risklevel'    => $risklevel,
            'followupdate' => null,
            'timecreated'  => $now,
            'timemodified' => $now,
        ]);
    }

    /**
     * Returns a single intervention record, or false.
     *
     * @param int $id Intervention ID.
     * @return \stdClass|false
     */
    public static function get(int $id) {
        global $DB;
        return $DB->get_record('local_courseinsights_interventions', ['id' => $id]);
    }

    /**
     * Updates status (and optionally assignedto and followupdate) on an intervention.
     *
     * @param int         $id            Intervention ID.
     * @param string      $status        New status.
     * @param int|null    $assignedto    Assigned staff user ID.
     * @param int|null    $followupdate  Follow-up date (Unix timestamp) or null.
     */
    public static function update(int $id, string $status, ?int $assignedto, ?int $followupdate): void {
        global $DB;

        if (!in_array($status, self::STATUSES, true)) {
            throw new \coding_exception('Invalid intervention status: ' . $status);
        }

        $DB->update_record('local_courseinsights_interventions', (object) [
            'id'           => $id,
            'status'       => $status,
            'assignedto'   => $assignedto,
            'followupdate' => $followupdate,
            'timemodified' => time(),
        ]);
    }

    /**
     * Returns paginated list of interventions, optionally filtered by status and/or assignee.
     *
     * Uses a single JOIN query to pull student name and course name in one shot —
     * avoids N+1 lookups per row. When $assignedto is set, rows are sorted by
     * urgency (overdue → due soon → active → future → closed) instead of creation date.
     *
     * @param string   $status     Filter by status, or '' for all.
     * @param int      $page       Zero-based page number.
     * @param int      $perpage    Rows per page.
     * @param int|null $assignedto When set, only cases assigned to or created by this user ID.
     * @return array{rows: array, total: int}
     */
    public static function get_list(string $status = '', int $page = 0, int $perpage = 25, ?int $assignedto = null): array {
        global $DB;

        $params     = [];
        $conditions = [];

        if ($status !== '' && in_array($status, self::STATUSES, true)) {
            $conditions[]     = 'ci.status = :status';
            $params['status'] = $status;
        }

        if ($assignedto !== null) {
            $conditions[]          = '(ci.assignedto = :assignedto OR (ci.assignedto IS NULL AND ci.createdby = :createdby))';
            $params['assignedto']  = $assignedto;
            $params['createdby']   = $assignedto;
        }

        $where = $conditions ? 'WHERE ' . implode(' AND ', $conditions) : '';

        if ($assignedto !== null) {
            $now  = time();
            $soon = $now + 7 * DAYSECS;
            $params['now1']  = $now;
            $params['soon1'] = $soon;
            $orderby = "ORDER BY
                CASE WHEN ci.status IN ('resolved','closed') THEN 1 ELSE 0 END ASC,
                CASE WHEN ci.followupdate IS NOT NULL AND ci.followupdate < :now1  THEN 0
                     WHEN ci.followupdate IS NOT NULL AND ci.followupdate < :soon1 THEN 1
                     WHEN ci.followupdate IS NULL                                  THEN 3
                     ELSE 2
                END ASC,
                ci.followupdate ASC,
                ci.timecreated DESC";
        } else {
            $orderby = "ORDER BY ci.timecreated DESC";
        }

        $sql = "SELECT ci.id, ci.userid, ci.courseid, ci.title, ci.status,
                       ci.riskscore, ci.risklevel, ci.followupdate,
                       ci.timecreated, ci.timemodified,
                       " . $DB->sql_concat('u.firstname', "' '", 'u.lastname') . " AS studentname,
                       c.fullname AS coursename,
                       " . $DB->sql_concat('a.firstname', "' '", 'a.lastname') . " AS assignedname
                  FROM {local_courseinsights_interventions} ci
                  JOIN {user} u ON u.id = ci.userid
                  JOIN {course} c ON c.id = ci.courseid
             LEFT JOIN {user} a ON a.id = ci.assignedto
                $where
            $orderby";

        // Count query does not use the urgency sort params.
        $countparams = array_diff_key($params, array_flip(['now1', 'soon1']));
        $countsql    = "SELECT COUNT(ci.id)
                          FROM {local_courseinsights_interventions} ci
                          $where";

        $total = (int) $DB->count_records_sql($countsql, $countparams);
        $rows  = [];

        if ($total > 0) {
            $rs = $DB->get_recordset_sql($sql, $params, $page * $perpage, $perpage);
            foreach ($rs as $row) {
                $rows[] = $row;
            }
            $rs->close();
        }

        return ['rows' => $rows, 'total' => $total];
    }

    /**
     * Returns the count of open cases assigned to or created by a given staff member.
     *
     * @param int $userid Staff user ID.
     * @return int
     */
    public static function get_my_open_count(int $userid): int {
        global $DB;

        $closed = [self::STATUS_RESOLVED, self::STATUS_CLOSED];
        [$insql, $inparams] = $DB->get_in_or_equal($closed, SQL_PARAMS_NAMED, 'st');

        $sql = "SELECT COUNT(id)
                  FROM {local_courseinsights_interventions}
                 WHERE (assignedto = :assignedto OR (assignedto IS NULL AND createdby = :createdby))
                   AND status NOT $insql";

        return (int) $DB->count_records_sql($sql, array_merge(
            ['assignedto' => $userid, 'createdby' => $userid],
            $inparams
        ));
    }

    /**
     * Adds a note to an intervention case.
     *
     * @param int    $interventionid Intervention ID.
     * @param int    $userid         Author user ID.
     * @param string $note           Note text.
     * @param bool   $isprivate      Whether this note is private (managers-only).
     * @return int New note record ID.
     */
    public static function add_note(int $interventionid, int $userid, string $note, bool $isprivate = false): int {
        global $DB;

        $now = time();
        return $DB->insert_record('local_courseinsights_intervention_notes', (object) [
            'interventionid' => $interventionid,
            'userid'         => $userid,
            'note'           => $note,
            'isprivate'      => $isprivate ? 1 : 0,
            'timecreated'    => $now,
            'timemodified'   => $now,
        ]);
    }

    /**
     * Returns all notes for an intervention, optionally excluding private ones.
     *
     * @param int  $interventionid Intervention ID.
     * @param bool $includeprivate Whether to include private notes.
     * @return array
     */
    public static function get_notes(int $interventionid, bool $includeprivate = true): array {
        global $DB;

        $params = ['interventionid' => $interventionid];
        $where  = 'n.interventionid = :interventionid';
        if (!$includeprivate) {
            $where .= ' AND n.isprivate = 0';
        }

        $sql = "SELECT n.id, n.note, n.isprivate, n.timecreated,
                       " . $DB->sql_concat('u.firstname', "' '", 'u.lastname') . " AS authorname
                  FROM {local_courseinsights_intervention_notes} n
                  JOIN {user} u ON u.id = n.userid
                 WHERE $where
              ORDER BY n.timecreated ASC";

        return array_values($DB->get_records_sql($sql, $params));
    }

    /**
     * Returns the lang string key for a status value.
     *
     * @param string $status Status constant.
     * @return string Lang string identifier.
     */
    public static function status_string_key(string $status): string {
        return 'intervention_status_' . $status;
    }

    /**
     * Returns a CSS class for the status badge.
     *
     * @param string $status
     * @return string
     */
    public static function status_badge_class(string $status): string {
        $map = [
            self::STATUS_NEW        => 'ci-status-badge--new',
            self::STATUS_INPROGRESS => 'ci-status-badge--inprogress',
            self::STATUS_CONTACTED  => 'ci-status-badge--contacted',
            self::STATUS_MONITORING => 'ci-status-badge--monitoring',
            self::STATUS_RESOLVED   => 'ci-status-badge--resolved',
            self::STATUS_CLOSED     => 'ci-status-badge--closed',
        ];
        return 'ci-status-badge ' . ($map[$status] ?? '');
    }

    /**
     * Compares student engagement before and after an intervention case was opened.
     *
     * Two time windows are compared:
     *   before: 30 days preceding $since
     *   after:  from $since until now (or 30 days after, whichever is sooner)
     *
     * Four metrics are measured: course visits, forum posts, assignment submissions,
     * and finished quiz attempts. Each metric returns a before/after count using
     * a single aggregate query per metric (no N+1).
     *
     * @param int $userid   Student user ID.
     * @param int $courseid Course ID.
     * @param int $since    Unix timestamp of when the intervention was created.
     * @return array{afterdays:int,metrics:array}
     */
    public static function get_engagement_comparison(int $userid, int $courseid, int $since): array {
        global $DB;

        $beforestart = $since - 30 * DAYSECS;
        $afterend    = min(time(), $since + 30 * DAYSECS);
        $afterdays   = max(1, (int) ceil(($afterend - $since) / DAYSECS));

        // Named params are shared across all queries; split/split2 have the same value
        // but different names to satisfy drivers that disallow repeated named params.
        $base = [
            'uid'    => $userid,
            'cid'    => $courseid,
            'rstart' => $beforestart,
            'rend'   => $afterend,
            'split'  => $since,
            'split2' => $since,
        ];

        // Course visits (logstore_standard_log).
        $row = $DB->get_record_sql(
            "SELECT COUNT(CASE WHEN timecreated <  :split  THEN 1 END) AS before_cnt,
                    COUNT(CASE WHEN timecreated >= :split2 THEN 1 END) AS after_cnt
               FROM {logstore_standard_log}
              WHERE userid = :uid AND courseid = :cid
                AND timecreated >= :rstart AND timecreated < :rend",
            $base
        );
        $visitsbefore = (int) ($row->before_cnt ?? 0);
        $visitsafter  = (int) ($row->after_cnt ?? 0);

        // Forum posts (p.created is the timestamp field in mdl_forum_posts).
        $row = $DB->get_record_sql(
            "SELECT COUNT(CASE WHEN p.created <  :split  THEN 1 END) AS before_cnt,
                    COUNT(CASE WHEN p.created >= :split2 THEN 1 END) AS after_cnt
               FROM {forum_posts} p
               JOIN {forum_discussions} d ON d.id = p.discussion
              WHERE p.userid = :uid AND d.course = :cid
                AND p.created >= :rstart AND p.created < :rend",
            $base
        );
        $forumbefore = (int) ($row->before_cnt ?? 0);
        $forumafter  = (int) ($row->after_cnt ?? 0);

        // Assignment submissions.
        $row = $DB->get_record_sql(
            "SELECT COUNT(CASE WHEN s.timemodified <  :split  THEN 1 END) AS before_cnt,
                    COUNT(CASE WHEN s.timemodified >= :split2 THEN 1 END) AS after_cnt
               FROM {assign_submission} s
               JOIN {assign} a ON a.id = s.assignment
              WHERE s.userid = :uid AND a.course = :cid AND s.status = 'submitted'
                AND s.timemodified >= :rstart AND s.timemodified < :rend",
            $base
        );
        $submitbefore = (int) ($row->before_cnt ?? 0);
        $submitafter  = (int) ($row->after_cnt ?? 0);

        // Finished quiz attempts.
        $row = $DB->get_record_sql(
            "SELECT COUNT(CASE WHEN qa.timefinish <  :split  THEN 1 END) AS before_cnt,
                    COUNT(CASE WHEN qa.timefinish >= :split2 THEN 1 END) AS after_cnt
               FROM {quiz_attempts} qa
               JOIN {quiz} q ON q.id = qa.quiz
              WHERE qa.userid = :uid AND q.course = :cid AND qa.state = 'finished'
                AND qa.timefinish >= :rstart AND qa.timefinish < :rend",
            $base
        );
        $quizbefore = (int) ($row->before_cnt ?? 0);
        $quizafter  = (int) ($row->after_cnt ?? 0);

        return [
            'afterdays' => $afterdays,
            'metrics'   => [
                ['key' => 'visits', 'before' => $visitsbefore, 'after' => $visitsafter],
                ['key' => 'forumposts', 'before' => $forumbefore, 'after' => $forumafter],
                ['key' => 'submissions', 'before' => $submitbefore, 'after' => $submitafter],
                ['key' => 'quizattempts', 'before' => $quizbefore, 'after' => $quizafter],
            ],
        ];
    }

    /**
     * Returns aggregated report data for the given time window.
     *
     * Two bulk queries (status counts, staff caseload) plus one user name lookup. No N+1.
     *
     * @param int $since Unix timestamp — only rows with timecreated >= this are included (0 = all).
     * @return array{total:int,opencnt:int,resolvedcnt:int,resolutionrate:int,
     *               avgdays:float|null,bystatus:array<string,int>,staff:array}
     */
    public static function get_report_data(int $since): array {
        global $DB;

        $params = ['since' => $since];

        // Counts and weighted average resolve time grouped by status — one query.
        $statussql = "SELECT status,
                             COUNT(*) AS cnt,
                             AVG(CASE WHEN status IN ('resolved','closed')
                                      THEN (timemodified - timecreated)
                                      ELSE NULL
                                  END) AS avg_secs
                        FROM {local_courseinsights_interventions}
                       WHERE timecreated >= :since
                       GROUP BY status";
        $statusrows = $DB->get_records_sql($statussql, $params);

        $bystatus     = [];
        $total        = 0;
        $opencnt      = 0;
        $resolvedcnt  = 0;
        $weightedsecs = 0.0;
        $weightedn    = 0;

        foreach ($statusrows as $row) {
            $cnt = (int) $row->cnt;
            $bystatus[$row->status] = $cnt;
            $total += $cnt;
            if (in_array($row->status, [self::STATUS_RESOLVED, self::STATUS_CLOSED], true)) {
                $resolvedcnt += $cnt;
                if ($row->avg_secs !== null) {
                    $weightedsecs += (float) $row->avg_secs * $cnt;
                    $weightedn    += $cnt;
                }
            } else {
                $opencnt += $cnt;
            }
        }

        $avgdays        = ($weightedn > 0) ? round($weightedsecs / $weightedn / 86400, 1) : null;
        $resolutionrate = ($total > 0) ? (int) round($resolvedcnt / $total * 100) : 0;

        // Staff caseload grouped by assignee (fallback to creator) — one query.
        $staffsql = "SELECT COALESCE(i.assignedto, i.createdby) AS staffid,
                            COUNT(*) AS total_cases,
                            SUM(CASE WHEN i.status NOT IN ('resolved','closed') THEN 1 ELSE 0 END) AS active_cases,
                            SUM(CASE WHEN i.status IN ('resolved','closed') THEN 1 ELSE 0 END) AS resolved_cases,
                            AVG(CASE WHEN i.status IN ('resolved','closed')
                                     THEN (i.timemodified - i.timecreated)
                                     ELSE NULL
                                 END) AS avg_secs
                       FROM {local_courseinsights_interventions} i
                      WHERE i.timecreated >= :since
                      GROUP BY COALESCE(i.assignedto, i.createdby)
                      ORDER BY total_cases DESC";
        $staffrows = array_values($DB->get_records_sql($staffsql, $params));

        // Bulk-fetch staff display names — one query.
        $staffids   = array_filter(array_column($staffrows, 'staffid'));
        $staffusers = [];
        if (!empty($staffids)) {
            [$insql, $inparams] = $DB->get_in_or_equal($staffids, SQL_PARAMS_NAMED, 'uid');
            $namesql = "SELECT id, " . $DB->sql_concat('firstname', "' '", 'lastname') . " AS fullname
                          FROM {user}
                         WHERE id $insql";
            $staffusers = $DB->get_records_sql($namesql, $inparams);
        }

        foreach ($staffrows as $row) {
            $row->staffname = isset($staffusers[$row->staffid])
                ? $staffusers[$row->staffid]->fullname
                : get_string('unknownuser', 'core');
            $row->avg_days  = ($row->avg_secs !== null)
                ? round((float) $row->avg_secs / 86400, 1)
                : null;
        }

        return [
            'total'          => $total,
            'opencnt'        => $opencnt,
            'resolvedcnt'    => $resolvedcnt,
            'resolutionrate' => $resolutionrate,
            'avgdays'        => $avgdays,
            'bystatus'       => $bystatus,
            'staff'          => $staffrows,
        ];
    }
}
