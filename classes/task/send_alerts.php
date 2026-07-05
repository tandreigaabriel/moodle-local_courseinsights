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
 * Scheduled task to send course health alerts.
 *
 * @package    local_courseinsights
 * @copyright  2026 Andrei Toma
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_courseinsights\task;

/**
 * Notifies editing teachers when a course drops below configured thresholds.
 */
class send_alerts extends \core\task\scheduled_task {
    /**
     * Returns the task name shown on the scheduled tasks admin page.
     *
     * @return string
     */
    public function get_name(): string {
        return get_string('task_send_alerts', 'local_courseinsights');
    }

    /**
     * Executes the alert task.
     *
     * Checks every visible course against the configured completion-rate
     * threshold and inactivity-days threshold. Sends a Moodle message to
     * each editing teacher whose course triggers an alert, subject to a
     * 24-hour per-course cooldown stored in plugin config.
     *
     * @return void
     */
    public function execute(): void {
        global $DB;

        if (!get_config('local_courseinsights', 'alertsenabled')) {
            mtrace('Course Insights alerts: disabled, skipping.');
            return;
        }

        $threshold   = (int) get_config('local_courseinsights', 'alertcompletionthreshold');
        $inactivedays = (int) get_config('local_courseinsights', 'alertinactivedays');
        $now         = time();
        $cooldown    = DAYSECS;

        // Load per-course last-sent timestamps (JSON map: {courseid: timestamp}).
        $lastsent = [];
        $saved = get_config('local_courseinsights', 'alertlastsent');
        if (!empty($saved)) {
            $lastsent = json_decode($saved, true) ?: [];
        }

        // Fetch all visible courses with a simple completion rate and last access.
        $courses = $DB->get_records_sql("
            SELECT
                c.id,
                c.fullname,
                ROUND(
                    (SELECT COUNT(*)
                       FROM {course_completions} cc
                      WHERE cc.course = c.id
                        AND cc.timecompleted IS NOT NULL) * 100.0
                    / NULLIF(
                        (SELECT COUNT(*)
                           FROM {course_completions} cc2
                          WHERE cc2.course = c.id),
                        0),
                    1
                ) AS completionrate,
                (SELECT MAX(timeaccess)
                   FROM {user_lastaccess} ula
                  WHERE ula.courseid = c.id) AS lastactivity
              FROM {course} c
             WHERE c.id <> :siteid
               AND c.visible = 1
        ", ['siteid' => SITEID]);

        if (empty($courses)) {
            mtrace('Course Insights alerts: no visible courses found.');
            return;
        }

        // Editing teacher role ID — used to find alert recipients.
        $teacherroleid = $DB->get_field('role', 'id', ['shortname' => 'editingteacher']);
        if (!$teacherroleid) {
            mtrace('Course Insights alerts: editingteacher role not found, aborting.');
            return;
        }

        $courseids = array_map('intval', array_keys($courses));
        [$courseinsql, $courseparams] = $DB->get_in_or_equal($courseids, SQL_PARAMS_NAMED, 'alertcourse');
        $teacherparams = array_merge($courseparams, [
            'contextlevel' => CONTEXT_COURSE,
            'roleid' => $teacherroleid,
        ]);
        $teacherrows = $DB->get_recordset_sql("
            SELECT ra.id AS assignmentid,
                   ctx.instanceid AS courseid,
                   u.id AS userid,
                   u.*
              FROM {context} ctx
              JOIN {role_assignments} ra ON ra.contextid = ctx.id
              JOIN {user} u ON u.id = ra.userid
             WHERE ctx.contextlevel = :contextlevel
               AND ctx.instanceid {$courseinsql}
               AND ra.roleid = :roleid
               AND u.deleted = 0
               AND u.suspended = 0
             ORDER BY ctx.instanceid ASC, u.lastname ASC, u.firstname ASC
        ", $teacherparams);

        $teachersbycourse = [];
        foreach ($teacherrows as $row) {
            $courseid = (int) $row->courseid;
            $teacher = clone($row);
            $teacher->id = (int) $row->userid;
            $teachersbycourse[$courseid][$teacher->id] = $teacher;
        }
        $teacherrows->close();

        $noreply     = \core_user::get_noreply_user();
        $dashboardurl = (new \moodle_url('/local/courseinsights/index.php'))->out(false);
        $inactivecutoff = $now - ($inactivedays * DAYSECS);
        $updated     = false;
        $alertcount  = 0;

        foreach ($courses as $course) {
            $courseid       = (int) $course->id;
            $completionrate = $course->completionrate !== null ? (float) $course->completionrate : null;
            $lastactivity   = !empty($course->lastactivity) ? (int) $course->lastactivity : 0;

            // Determine which thresholds are exceeded.
            $reasons = [];

            if ($threshold > 0 && $completionrate !== null && $completionrate < $threshold) {
                $reasons[] = get_string('alert_reason_lowcompletion', 'local_courseinsights', (object)[
                    'completionrate' => round($completionrate, 1),
                    'threshold'      => $threshold,
                ]);
            }

            if ($inactivedays > 0 && $lastactivity > 0 && $lastactivity < $inactivecutoff) {
                $dayssince = (int) ceil(($now - $lastactivity) / DAYSECS);
                $reasons[] = get_string('alert_reason_inactive', 'local_courseinsights', (object)[
                    'dayssince' => $dayssince,
                    'threshold' => $inactivedays,
                ]);
            }

            if (empty($reasons)) {
                continue;
            }

            // Respect per-course cooldown (one alert per course per day).
            $lasttime = isset($lastsent[$courseid]) ? (int) $lastsent[$courseid] : 0;
            if (($now - $lasttime) < $cooldown) {
                continue;
            }

            $teachers = $teachersbycourse[$courseid] ?? [];

            if (empty($teachers)) {
                continue;
            }

            $lastactivitydate = $lastactivity > 0
                ? userdate($lastactivity, get_string('strftimedatefullshort', 'langconfig'))
                : get_string('never');

            $msgcontext = (object)[
                'coursename'       => format_string($course->fullname),
                'completionrate'   => $completionrate !== null ? round($completionrate, 1) : '-',
                'lastactivitydate' => $lastactivitydate,
                'reasons'          => implode("\n", $reasons),
                'dashboardurl'     => $dashboardurl,
            ];

            $subject = get_string(
                'alert_subject',
                'local_courseinsights',
                format_string($course->fullname)
            );
            $body    = get_string('alert_body', 'local_courseinsights', $msgcontext);

            foreach ($teachers as $teacher) {
                $message                     = new \core\message\message();
                $message->component          = 'local_courseinsights';
                $message->name               = 'alert';
                $message->userfrom           = $noreply;
                $message->userto             = $teacher;
                $message->subject            = $subject;
                $message->fullmessage        = $body;
                $message->fullmessageformat  = FORMAT_PLAIN;
                $message->fullmessagehtml    = '';
                $message->smallmessage       = $subject;
                $message->notification       = 1;
                $message->contexturl         = $dashboardurl;
                $message->contexturlname     = get_string('pluginname', 'local_courseinsights');
                message_send($message);

                mtrace("  Sent alert: [{$courseid}] {$course->fullname} → {$teacher->username}");
                $alertcount++;
            }

            $lastsent[$courseid] = $now;
            $updated = true;
        }

        if ($updated) {
            set_config('alertlastsent', json_encode($lastsent), 'local_courseinsights');
        }

        mtrace("Course Insights alerts: {$alertcount} message(s) sent.");
    }
}
