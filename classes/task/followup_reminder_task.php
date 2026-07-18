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
 * Scheduled task: send follow-up reminder notifications for overdue intervention cases.
 *
 * @package    local_courseinsights
 * @copyright  2026 Andrei Toma
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_courseinsights\task;

/**
 * Sends a Moodle notification to the assigned staff member (or case creator) when an
 * intervention follow-up date has passed and the case is not yet resolved or closed.
 *
 * A reminder is sent at most once per follow-up date: the lastreminder timestamp is
 * written back after sending, so resetting the follow-up date to a later value
 * triggers a new reminder.
 */
class followup_reminder_task extends \core\task\scheduled_task {
    /**
     * Returns the human-readable task name shown in Moodle admin.
     *
     * @return string
     */
    public function get_name(): string {
        return get_string('task_followup_reminder', 'local_courseinsights');
    }

    /**
     * Executes the task.
     *
     * @return void
     */
    public function execute(): void {
        global $DB;

        $now = time();

        // Single JOIN query: fetch all overdue open cases that have not yet been reminded
        // for the current follow-up date value.
        $sql = "SELECT i.id, i.userid, i.courseid, i.createdby, i.assignedto,
                       i.title, i.followupdate,
                       " . $DB->sql_concat('u.firstname', "' '", 'u.lastname') . " AS studentname,
                       c.fullname AS coursename
                  FROM {local_courseinsights_interventions} i
                  JOIN {user} u ON u.id = i.userid
                  JOIN {course} c ON c.id = i.courseid
                 WHERE i.followupdate IS NOT NULL
                   AND i.followupdate <= :now
                   AND i.status NOT IN ('resolved', 'closed')
                   AND (i.lastreminder IS NULL OR i.lastreminder < i.followupdate)";

        $interventions = $DB->get_records_sql($sql, ['now' => $now]);

        if (empty($interventions)) {
            mtrace('Course Insights: no overdue follow-up interventions found.');
            return;
        }

        // Collect unique recipient IDs in one pass; assignedto falls back to createdby.
        $recipientids = [];
        foreach ($interventions as $row) {
            $recipientids[!empty($row->assignedto) ? (int)$row->assignedto : (int)$row->createdby] = true;
        }
        $users = $DB->get_records_list('user', 'id', array_keys($recipientids));

        $noreply = \core_user::get_noreply_user();
        $sent    = [];

        foreach ($interventions as $row) {
            $recipientid = !empty($row->assignedto) ? (int)$row->assignedto : (int)$row->createdby;

            if (!isset($users[$recipientid])) {
                mtrace("Course Insights: skipping intervention {$row->id} — recipient {$recipientid} not found.");
                continue;
            }

            $url  = (new \moodle_url('/local/courseinsights/intervention_detail.php', ['id' => $row->id]))->out(false);
            $data = (object) [
                'title'        => $row->title,
                'studentname'  => $row->studentname,
                'coursename'   => $row->coursename,
                'followupdate' => userdate($row->followupdate, get_string('strftimedatefullshort', 'langconfig')),
                'url'          => $url,
            ];

            $message                    = new \core\message\message();
            $message->component         = 'local_courseinsights';
            $message->name              = 'followup_reminder';
            $message->userfrom          = $noreply;
            $message->userto            = $users[$recipientid];
            $message->subject           = get_string('followup_reminder_subject', 'local_courseinsights', $data);
            $message->fullmessage       = get_string('followup_reminder_body', 'local_courseinsights', $data);
            $message->fullmessageformat = FORMAT_PLAIN;
            $message->fullmessagehtml   = self::build_html($data);
            $message->smallmessage      = get_string('followup_reminder_smallmessage', 'local_courseinsights', $data);
            $message->notification      = 1;
            $message->contexturl        = $url;
            $message->contexturlname    = get_string('intervention_detail_heading', 'local_courseinsights');

            message_send($message);
            $sent[] = $row->id;
            mtrace("Course Insights: sent follow-up reminder for intervention {$row->id} to user {$recipientid}.");
        }

        // Bulk-stamp lastreminder so these cases are not re-notified today.
        if (!empty($sent)) {
            [$insql, $inparams] = $DB->get_in_or_equal($sent, SQL_PARAMS_NAMED, 'iid');
            $inparams['ts'] = time();
            $DB->execute(
                "UPDATE {local_courseinsights_interventions} SET lastreminder = :ts WHERE id $insql",
                $inparams
            );
        }

        mtrace('Course Insights follow-up reminder task complete. Sent: ' . count($sent) . '.');
    }

    /**
     * Builds the HTML body for the follow-up reminder notification.
     *
     * @param \stdClass $data
     * @return string
     */
    private static function build_html(\stdClass $data): string {
        $url      = s($data->url);
        $title    = s($data->title);
        $student  = s($data->studentname);
        $course   = s($data->coursename);
        $duedate  = s($data->followupdate);

        return '<p>The follow-up date for an intervention case has passed. '
             . 'Please review and update the case status.</p>'
             . '<table style="border-collapse:collapse;margin:12px 0;font-size:0.95em;">'
             . '<tr><th style="text-align:left;padding:4px 16px 4px 0;color:#555;">Case</th>'
             . '<td style="padding:4px 0;">' . $title . '</td></tr>'
             . '<tr><th style="text-align:left;padding:4px 16px 4px 0;color:#555;">Student</th>'
             . '<td style="padding:4px 0;">' . $student . '</td></tr>'
             . '<tr><th style="text-align:left;padding:4px 16px 4px 0;color:#555;">Course</th>'
             . '<td style="padding:4px 0;">' . $course . '</td></tr>'
             . '<tr><th style="text-align:left;padding:4px 16px 4px 0;color:#555;">Follow-up due</th>'
             . '<td style="padding:4px 0;color:#c0392b;font-weight:bold;">' . $duedate . '</td></tr>'
             . '</table>'
             . '<p><a href="' . $url . '" style="display:inline-block;padding:8px 16px;'
             . 'background:#0073aa;color:#fff;text-decoration:none;border-radius:4px;">'
             . 'View Intervention Case &rarr;</a></p>';
    }
}
