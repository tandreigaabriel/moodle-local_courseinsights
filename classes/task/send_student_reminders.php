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
 * Scheduled task: send inactivity reminder emails to students.
 *
 * @package    local_courseinsights
 * @copyright  2026 Andrei Toma
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_courseinsights\task;

/**
 * Sends one email per student listing all courses they haven't accessed in N days.
 * A student will not receive the same reminder again until another full threshold period passes.
 * Tracks sent reminders in local_courseinsights_reminders (userid+courseid unique index).
 */
class send_student_reminders extends \core\task\scheduled_task {
    /**
     * Returns the human-readable task name.
     *
     * @return string
     */
    public function get_name(): string {
        return get_string('task_send_student_reminders', 'local_courseinsights');
    }

    /**
     * Executes the task: finds inactive students and sends one reminder email per user.
     */
    public function execute(): void {
        global $DB;

        if (!(int) get_config('local_courseinsights', 'studentreminderenabled')) {
            mtrace('Student reminder task: disabled, skipping.');
            return;
        }

        $licstatus = \local_courseinsights\license::get_status();
        if (
            $licstatus === \local_courseinsights\license::STATUS_EXPIRED ||
            $licstatus === \local_courseinsights\license::STATUS_UNLICENSED
        ) {
            mtrace('Student reminder task: no valid licence, skipping.');
            return;
        }

        $days = max(1, (int) get_config('local_courseinsights', 'studentinactivitydays'));
        $cutoff = time() - ($days * DAYSECS);

        // Single query: active enrolment + no completion + inactive since cutoff + not recently reminded.
        // Ordered by userid so we can stream and group without loading everything into memory at once.
        $sql = "SELECT u.id AS userid,
                       c.id AS courseid, c.fullname AS coursefullname,
                       COALESCE(la.timeaccess, 0) AS lastaccess,
                       r.id AS reminderid
                  FROM {enrol} e
                  JOIN {user_enrolments} ue ON ue.enrolid = e.id AND ue.status = 0
                  JOIN {user} u
                         ON u.id = ue.userid
                        AND u.deleted = 0 AND u.confirmed = 1 AND u.suspended = 0
                  JOIN {course} c ON c.id = e.courseid AND c.id <> :site AND c.visible = 1
                  LEFT JOIN {user_lastaccess} la
                         ON la.userid = ue.userid AND la.courseid = e.courseid
                  LEFT JOIN (
                      SELECT course AS courseid, userid
                        FROM {course_completions}
                       WHERE timecompleted IS NOT NULL AND timecompleted > 0
                  ) cc ON cc.courseid = e.courseid AND cc.userid = ue.userid
                  LEFT JOIN {local_courseinsights_reminders} r
                         ON r.userid = ue.userid AND r.courseid = e.courseid
                 WHERE e.status = 0
                   AND cc.userid IS NULL
                   AND (la.timeaccess IS NULL OR la.timeaccess < :cutoff)
                   AND (r.timereminded IS NULL OR r.timereminded < :remindercut)
                 ORDER BY u.id, c.id";

        $params = [
            'site'        => SITEID,
            'cutoff'      => $cutoff,
            'remindercut' => $cutoff,
        ];

        // Stream with recordset so we never load all rows into memory at once.
        $byuser = [];
        $rs = $DB->get_recordset_sql($sql, $params);
        foreach ($rs as $row) {
            $byuser[$row->userid][] = clone $row;
        }
        $rs->close();

        if (empty($byuser)) {
            mtrace('Student reminder task: no students need reminding.');
            return;
        }

        // Fetch full user rows in one query (needed by message_send).
        $userids = array_keys($byuser);
        [$insql, $inparams] = $DB->get_in_or_equal($userids, SQL_PARAMS_NAMED, 'uid');
        $users = $DB->get_records_sql("SELECT * FROM {user} WHERE id $insql", $inparams);

        $fromuser = \core_user::get_noreply_user();
        $now      = time();
        $sent     = 0;
        $failed   = 0;
        $toupdate = [];
        $toinsert = [];

        foreach ($byuser as $userid => $courses) {
            if (empty($users[$userid])) {
                continue;
            }

            $touser = $users[$userid];

            // Plain-text course list.
            $plainlines = [];
            // HTML course list items.
            $htmlitems = [];
            foreach ($courses as $c) {
                $url = (new \moodle_url('/course/view.php', ['id' => $c->courseid]))->out(false);
                $plainlines[] = '• ' . $c->coursefullname . "\n  " . $url;
                $htmlitems[]  = '<li><a href="' . $url . '">' . s($c->coursefullname) . '</a></li>';
            }

            $a               = new \stdClass();
            $a->firstname    = $touser->firstname;
            $a->inactivedays = $days;
            $a->courselist   = implode("\n\n", $plainlines);
            $a->siteurl      = (new \moodle_url('/'))->out(false);

            $subject   = get_string('studentreminder_subject', 'local_courseinsights');
            $plainbody = get_string('studentreminder_body', 'local_courseinsights', $a);
            $htmlbody  = '<p>' . get_string('studentreminder_html_intro', 'local_courseinsights', $a) . '</p>'
                       . '<ul>' . implode('', $htmlitems) . '</ul>'
                       . '<p>' . get_string('studentreminder_html_cta', 'local_courseinsights') . '</p>'
                       . '<p><a href="' . s($a->siteurl) . '">' . s($a->siteurl) . '</a></p>';

            $message                     = new \core\message\message();
            $message->component          = 'local_courseinsights';
            $message->name               = 'student_reminder';
            $message->userfrom           = $fromuser;
            $message->userto             = $touser;
            $message->subject            = $subject;
            $message->fullmessage        = $plainbody;
            $message->fullmessageformat  = FORMAT_PLAIN;
            $message->fullmessagehtml    = $htmlbody;
            $message->smallmessage       = $subject;
            $message->notification       = 1;
            $message->contexturl         = (new \moodle_url('/local/courseinsights/index.php'))->out(false);
            $message->contexturlname     = get_string('pluginname', 'local_courseinsights');

            $result = message_send($message);

            if ($result) {
                $sent++;
                foreach ($courses as $c) {
                    if (!empty($c->reminderid)) {
                        $toupdate[] = (object)['id' => (int) $c->reminderid, 'timereminded' => $now];
                    } else {
                        $toinsert[] = (object)[
                            'userid'       => (int) $userid,
                            'courseid'     => (int) $c->courseid,
                            'timereminded' => $now,
                        ];
                    }
                }
            } else {
                $failed++;
                mtrace("  WARN: could not send reminder to userid=$userid");
            }
        }

        // Persist reminder records in bulk.
        foreach ($toupdate as $rec) {
            $DB->update_record('local_courseinsights_reminders', $rec);
        }
        if (!empty($toinsert)) {
            $DB->insert_records('local_courseinsights_reminders', $toinsert);
        }

        mtrace("Student reminder task complete: sent=$sent, failed=$failed, threshold={$days}d.");
    }
}
