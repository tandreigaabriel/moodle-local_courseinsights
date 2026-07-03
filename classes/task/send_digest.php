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
 * Scheduled task to send a course summary digest to managers.
 *
 * @package    local_courseinsights
 * @copyright  2026 Andrei Toma
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_courseinsights\task;

/**
 * Sends a weekly or monthly HTML email digest to all users with the manage capability.
 */
class send_digest extends \core\task\scheduled_task {
    /**
     * Returns the task name shown on the scheduled tasks admin page.
     *
     * @return string
     */
    public function get_name(): string {
        return get_string('task_send_digest', 'local_courseinsights');
    }

    /**
     * Executes the digest task.
     *
     * Checks whether the digest is enabled and whether enough time has passed
     * since the last send (6+ days for weekly, 27+ days for monthly). Fetches
     * all users with local/courseinsights:manage at system context and sends
     * each one an HTML + plain-text email via message_send().
     *
     * @return void
     */
    public function execute(): void {
        if (!get_config('local_courseinsights', 'digestenabled')) {
            mtrace('Course Insights digest: disabled, skipping.');
            return;
        }

        $frequency   = (string) get_config('local_courseinsights', 'digestfrequency');
        $mininterval = $frequency === 'monthly' ? 27 * DAYSECS : 6 * DAYSECS;
        $now         = time();
        $lastsent    = (int) get_config('local_courseinsights', 'digestlastsent');

        if ($lastsent > 0 && ($now - $lastsent) < $mininterval) {
            $nextdue = userdate($lastsent + $mininterval, get_string('strftimedatefullshort', 'langconfig'));
            mtrace("Course Insights digest: not due yet (next: {$nextdue}), skipping.");
            return;
        }

        // Recipients: all non-deleted, non-suspended users with manage capability at system context.
        $recipients = get_users_by_capability(
            \context_system::instance(),
            'local/courseinsights:manage',
            'u.*',
            'u.lastname ASC',
            '',
            '',
            '',
            '',
            false
        );

        if (empty($recipients)) {
            mtrace('Course Insights digest: no recipients with manage capability found.');
            return;
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

        $records = \local_courseinsights\report_service::get_course_overview($filters, 0, 0);

        if (empty($records)) {
            mtrace('Course Insights digest: no course data available, skipping.');
            return;
        }

        $dashboardurl  = (new \moodle_url('/local/courseinsights/index.php'))->out(false);
        $generateddate = userdate($now, get_string('strftimedate', 'langconfig'));
        $periodlabel   = $frequency === 'monthly'
            ? get_string('digestfrequency_monthly', 'local_courseinsights')
            : get_string('digestfrequency_weekly', 'local_courseinsights');

        [$html, $plain] = self::build_body($records, $generateddate, $periodlabel, $dashboardurl);

        $subject = get_string('digest_subject', 'local_courseinsights', $periodlabel);
        $noreply = \core_user::get_noreply_user();
        $sent    = 0;

        foreach ($recipients as $recipient) {
            $message                    = new \core\message\message();
            $message->component         = 'local_courseinsights';
            $message->name              = 'digest';
            $message->userfrom          = $noreply;
            $message->userto            = $recipient;
            $message->subject           = $subject;
            $message->fullmessage       = $plain;
            $message->fullmessageformat = FORMAT_PLAIN;
            $message->fullmessagehtml   = $html;
            $message->smallmessage      = $subject;
            $message->notification      = 1;
            $message->contexturl        = $dashboardurl;
            $message->contexturlname    = get_string('pluginname', 'local_courseinsights');
            message_send($message);
            mtrace("  Digest sent to {$recipient->username}");
            $sent++;
        }

        set_config('digestlastsent', $now, 'local_courseinsights');
        mtrace("Course Insights digest: {$sent} message(s) sent.");
    }

    /**
     * Builds the HTML and plain-text email body for the digest.
     *
     * @param array  $records       Course records from get_course_overview().
     * @param string $generateddate Formatted date string.
     * @param string $periodlabel   "Weekly" or "Monthly".
     * @param string $dashboardurl  URL to the dashboard.
     * @return array [string $html, string $plain]
     */
    private static function build_body(
        array $records,
        string $generateddate,
        string $periodlabel,
        string $dashboardurl
    ): array {
        $rowshtml  = '';
        $rowsplain = '';
        $alt       = false;

        foreach ($records as $record) {
            $health         = \local_courseinsights\report_service::calculate_health_score($record);
            $grade          = $health['healthgrade'];
            $score          = (int) $health['healthscore'];
            $completion     = isset($record->completionrate)
                ? round((float) $record->completionrate, 1) . '%'
                : '-';
            $enrolled       = (int) ($record->enrolledstudents ?? 0);
            $lastactivity   = !empty($record->lastactivity) ? (int) $record->lastactivity : 0;
            $lastactivitystr = $lastactivity > 0
                ? userdate($lastactivity, get_string('strftimedatefullshort', 'langconfig'))
                : '-';
            $coursename = format_string($record->fullname);

            $bg = $alt ? 'background:#f9f9f9;' : '';
            $alt = !$alt;

            $rowshtml .= "<tr style=\"{$bg}\">"
                . "<td style=\"padding:6px 10px;border-bottom:1px solid #eee;\">{$coursename}</td>"
                . "<td style=\"padding:6px 10px;border-bottom:1px solid #eee;text-align:center;\">{$grade} ({$score}/100)</td>"
                . "<td style=\"padding:6px 10px;border-bottom:1px solid #eee;text-align:center;\">{$completion}</td>"
                . "<td style=\"padding:6px 10px;border-bottom:1px solid #eee;text-align:center;\">{$enrolled}</td>"
                . "<td style=\"padding:6px 10px;border-bottom:1px solid #eee;\">{$lastactivitystr}</td>"
                . "</tr>\n";

            $rowsplain .= sprintf(
                "%-40s | %-10s | %-10s | %8d | %s\n",
                mb_strimwidth($coursename, 0, 40),
                "{$grade} ({$score}/100)",
                $completion,
                $enrolled,
                $lastactivitystr
            );
        }

        $total = count($records);

        $bodystyle = 'font-family:Arial,sans-serif;color:#333;max-width:820px;margin:0 auto;padding:20px;';
        $html = "<!DOCTYPE html><html><body style=\"{$bodystyle}\">"
            . "<h2 style=\"color:#6c5ce7;margin-bottom:4px;\">Course Insights &mdash; {$periodlabel} Digest</h2>"
            . "<p style=\"color:#666;margin-top:0;\">Generated: {$generateddate} &nbsp;&bull;&nbsp; {$total} course(s)</p>"
            . '<table style="width:100%;border-collapse:collapse;font-size:13px;">'
            . '<thead><tr style="background:#6c5ce7;color:#fff;">'
            . '<th style="padding:8px 10px;text-align:left;">Course</th>'
            . '<th style="padding:8px 10px;text-align:center;">Health</th>'
            . '<th style="padding:8px 10px;text-align:center;">Completion</th>'
            . '<th style="padding:8px 10px;text-align:center;">Students</th>'
            . '<th style="padding:8px 10px;text-align:left;">Last Activity</th>'
            . '</tr></thead>'
            . "<tbody>{$rowshtml}</tbody>"
            . '</table>'
            . "<p style=\"margin-top:20px;\">"
            . "<a href=\"{$dashboardurl}\" style=\"color:#6c5ce7;text-decoration:none;font-weight:bold;\">"
            . 'View full dashboard &rarr;</a>'
            . '</p>'
            . '</body></html>';

        $plain = "Course Insights \u{2014} {$periodlabel} Digest\n"
            . "Generated: {$generateddate} | {$total} course(s)\n\n"
            . sprintf("%-40s | %-10s | %-10s | %8s | %s\n", 'Course', 'Health', 'Completion', 'Students', 'Last Activity')
            . str_repeat('-', 95) . "\n"
            . $rowsplain
            . "\nView full dashboard: {$dashboardurl}\n";

        return [$html, $plain];
    }
}
