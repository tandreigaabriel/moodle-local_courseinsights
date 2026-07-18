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
 * Multi-signal risk score computation for local_courseinsights.
 *
 * @package    local_courseinsights
 * @copyright  2026 Andrei Toma
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_courseinsights;

/**
 * Computes and caches 0–100 risk scores per student per course.
 *
 * Each enabled rule in local_courseinsights_risk_rules contributes its weight
 * to the score when its condition is met. Scores are cached in
 * local_courseinsights_risk_scores for SCORE_TTL seconds.
 */
class risk_service {
    /** Risk level: low (0–24). */
    public const LEVEL_LOW      = 'low';
    /** Risk level: medium (25–49). */
    public const LEVEL_MEDIUM   = 'medium';
    /** Risk level: high (50–74). */
    public const LEVEL_HIGH     = 'high';
    /** Risk level: critical (75–100). */
    public const LEVEL_CRITICAL = 'critical';

    /** Cached score TTL in seconds (24 hours). */
    public const SCORE_TTL = 86400;

    /**
     * Returns the risk level for a given numeric score.
     *
     * @param int $score 0–100.
     * @return string One of low|medium|high|critical.
     */
    public static function get_level(int $score): string {
        if ($score >= 80) {
            return self::LEVEL_CRITICAL;
        }
        if ($score >= 60) {
            return self::LEVEL_HIGH;
        }
        if ($score >= 30) {
            return self::LEVEL_MEDIUM;
        }
        return self::LEVEL_LOW;
    }

    /**
     * Default rules seeded during install/upgrade.
     *
     * @return array Each row: [ruletype, label, threshold, weight, enabled, sortorder].
     */
    public static function get_default_rules(): array {
        return [
            ['inactivity_days', 'No login', 14, 30, 1, 1],
            ['missed_assignments', 'Missed assignments', 2, 25, 1, 2],
            ['grade_below', 'Low grade', 50, 20, 1, 3],
            ['completion_below', 'Low completion', 40, 20, 1, 4],
            ['no_course_activity', 'No course activity', 14, 25, 1, 5],
        ];
    }

    /**
     * Bulk-fetches cached scores for a list of userid+courseid pairs, computing
     * only the ones that are missing or stale. One batch DB read replaces the
     * per-student cache-check loop, keeping page queries bounded.
     *
     * @param array $usercourses Array of ['userid' => int, 'courseid' => int].
     * @return array Keyed by "{userid}_{courseid}" => ['score', 'risklevel', 'reasons'].
     */
    public static function get_scores_for_usercourses(array $usercourses): array {
        global $DB;

        if (empty($usercourses)) {
            return [];
        }

        $userids = array_unique(array_column($usercourses, 'userid'));
        [$insql, $inparams] = $DB->get_in_or_equal($userids, SQL_PARAMS_NAMED, 'uid');
        $cutoff = time() - self::SCORE_TTL;

        $cachedrows = $DB->get_records_sql(
            "SELECT * FROM {local_courseinsights_risk_scores}
              WHERE userid {$insql} AND timecalculated > :cutoff",
            array_merge($inparams, ['cutoff' => $cutoff])
        );

        $cachedmap = [];
        foreach ($cachedrows as $row) {
            $cachedmap["{$row->userid}_{$row->courseid}"] = $row;
        }

        // Fetch rules once for the whole batch — avoids one query per uncached student.
        $rules = null;

        $results = [];
        foreach ($usercourses as $uc) {
            $key = "{$uc['userid']}_{$uc['courseid']}";
            if (isset($cachedmap[$key])) {
                $row = $cachedmap[$key];
                $results[$key] = [
                    'score'     => (int) $row->score,
                    'risklevel' => $row->risklevel,
                    'reasons'   => json_decode($row->reasons, true) ?: [],
                ];
            } else {
                if ($rules === null) {
                    $rules = $DB->get_records('local_courseinsights_risk_rules', ['enabled' => 1], 'sortorder ASC');
                }
                $results[$key] = self::compute_and_store((int) $uc['userid'], (int) $uc['courseid'], $rules);
            }
        }

        return $results;
    }

    /**
     * Returns a cached score or computes and caches a fresh one.
     *
     * @param int $userid
     * @param int $courseid
     * @return array ['score' => int, 'risklevel' => string, 'reasons' => array]
     */
    public static function get_or_compute_score(int $userid, int $courseid): array {
        global $DB;

        $cached = $DB->get_record('local_courseinsights_risk_scores', [
            'userid'   => $userid,
            'courseid' => $courseid,
        ]);

        if ($cached && (time() - (int) $cached->timecalculated) < self::SCORE_TTL) {
            return [
                'score'     => (int) $cached->score,
                'risklevel' => $cached->risklevel,
                'reasons'   => json_decode($cached->reasons, true) ?: [],
            ];
        }

        return self::compute_and_store($userid, $courseid);
    }

    /**
     * Computes a fresh risk score and persists it to the DB.
     *
     * @param int        $userid
     * @param int        $courseid
     * @param array|null $rules   Pre-fetched rules (pass from batch callers to avoid repeat queries).
     * @return array ['score' => int, 'risklevel' => string, 'reasons' => array]
     */
    public static function compute_and_store(int $userid, int $courseid, ?array $rules = null): array {
        global $DB;

        if ($rules === null) {
            $rules = $DB->get_records(
                'local_courseinsights_risk_rules',
                ['enabled' => 1],
                'sortorder ASC'
            );
        }

        $score   = 0;
        $reasons = [];

        foreach ($rules as $rule) {
            $reason = self::evaluate_rule($rule, $userid, $courseid);
            if ($reason !== null) {
                $score    += (int) $rule->weight;
                $reasons[] = $reason;
            }
        }

        $score     = min(100, $score);
        $risklevel = self::get_level($score);

        $existing = $DB->get_record('local_courseinsights_risk_scores', [
            'userid'   => $userid,
            'courseid' => $courseid,
        ]);

        $record = (object) [
            'userid'         => $userid,
            'courseid'       => $courseid,
            'score'          => $score,
            'risklevel'      => $risklevel,
            'reasons'        => json_encode($reasons),
            'timecalculated' => time(),
        ];

        if ($existing) {
            $record->id = $existing->id;
            $DB->update_record('local_courseinsights_risk_scores', $record);
        } else {
            $DB->insert_record('local_courseinsights_risk_scores', $record);
        }

        return ['score' => $score, 'risklevel' => $risklevel, 'reasons' => $reasons];
    }

    /**
     * Evaluates a single risk rule for one student in one course.
     *
     * @param object $rule Row from local_courseinsights_risk_rules.
     * @param int    $userid
     * @param int    $courseid
     * @return array|null Reason descriptor ['key', 'a'] if triggered, null otherwise.
     */
    private static function evaluate_rule(object $rule, int $userid, int $courseid): ?array {
        global $DB;

        $threshold = (float) $rule->threshold;

        switch ($rule->ruletype) {
            case 'inactivity_days':
                $la = (int) $DB->get_field('user_lastaccess', 'timeaccess', [
                    'userid'   => $userid,
                    'courseid' => $courseid,
                ]);
                $triggered = !$la || (time() - $la) / DAYSECS >= $threshold;
                return $triggered
                    ? ['key' => 'risk_reason_inactivity_days', 'a' => (int) $threshold]
                    : null;

            case 'no_course_activity':
                try {
                    $last = (int) $DB->get_field_sql(
                        "SELECT MAX(timecreated)
                           FROM {logstore_standard_log}
                          WHERE userid = :uid AND courseid = :cid AND anonymous = 0",
                        ['uid' => $userid, 'cid' => $courseid]
                    );
                } catch (\Exception $e) {
                    return null;
                }
                $triggered = !$last || (time() - $last) / DAYSECS >= $threshold;
                return $triggered
                    ? ['key' => 'risk_reason_no_course_activity', 'a' => (int) $threshold]
                    : null;

            case 'missed_assignments':
                $missed = (int) $DB->count_records_sql(
                    "SELECT COUNT(*)
                       FROM {assign} a
                      WHERE a.course = :cid
                        AND NOT EXISTS (
                            SELECT 1
                              FROM {assign_submission} s
                             WHERE s.assignment = a.id
                               AND s.userid = :uid
                               AND s.status = 'submitted'
                        )",
                    ['cid' => $courseid, 'uid' => $userid]
                );
                return $missed >= (int) $threshold
                    ? ['key' => 'risk_reason_missed_assignments', 'a' => $missed]
                    : null;

            case 'grade_below':
                $gradeitem = $DB->get_record(
                    'grade_items',
                    ['courseid' => $courseid, 'itemtype' => 'course'],
                    'id, grademax',
                    IGNORE_MISSING
                );
                if (!$gradeitem) {
                    return null;
                }
                $grade = $DB->get_record(
                    'grade_grades',
                    ['itemid' => $gradeitem->id, 'userid' => $userid],
                    'finalgrade',
                    IGNORE_MISSING
                );
                if (!$grade || $grade->finalgrade === null) {
                    return null;
                }
                $grademax = (float) $gradeitem->grademax ?: 100.0;
                $pct = ((float) $grade->finalgrade / $grademax) * 100.0;
                return $pct < $threshold
                    ? ['key' => 'risk_reason_grade_below', 'a' => (int) $threshold]
                    : null;

            case 'completion_below':
                $total = (int) $DB->count_records_sql(
                    "SELECT COUNT(*) FROM {course_modules} WHERE course = :cid AND completion > 0",
                    ['cid' => $courseid]
                );
                if ($total === 0) {
                    return null;
                }
                $done = (int) $DB->count_records_sql(
                    "SELECT COUNT(*)
                       FROM {course_modules_completion} cmc
                       JOIN {course_modules} cm ON cm.id = cmc.coursemoduleid
                      WHERE cm.course = :cid
                        AND cmc.userid = :uid
                        AND cmc.completionstate >= 1
                        AND cm.completion > 0",
                    ['cid' => $courseid, 'uid' => $userid]
                );
                $pct = ($done / $total) * 100.0;
                return $pct < $threshold
                    ? ['key' => 'risk_reason_completion_below', 'a' => (int) $threshold]
                    : null;

            default:
                return null;
        }
    }
}
