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
 * Ad-hoc task to push the course overview JSON to the configured webhook URL.
 *
 * @package    local_courseinsights
 * @copyright  2026 Andrei Toma
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_courseinsights\task;

/**
 * Sends the nightly course-overview payload to the webhook endpoint.
 *
 * Runs after the summary cache is built. Failure here does not affect
 * the scheduled task's success status.
 */
class send_webhook extends \core\task\adhoc_task {
    /**
     * Returns the task name shown in the admin task runner.
     *
     * @return string
     */
    public function get_name(): string {
        return get_string('sendwebhook', 'local_courseinsights');
    }

    /**
     * Executes the webhook push.
     *
     * @return void
     */
    public function execute(): void {
        mtrace('send_webhook: pushing course overview to webhook…');

        $result = \local_courseinsights\report_service::push_webhook();

        if ($result['httpcode'] === 0 && $result['success']) {
            mtrace(get_string('webhookskipped', 'local_courseinsights'));
        } else if ($result['success']) {
            mtrace(get_string('webhooksent', 'local_courseinsights', $result['httpcode']));
        } else {
            mtrace(get_string('webhookfailed', 'local_courseinsights', (object)[
                'httpcode' => $result['httpcode'],
                'error'    => $result['error'],
            ]));
            // Throw so Moodle marks this individual task as failed, not the scheduler.
            throw new \moodle_exception('webhookfailed', 'local_courseinsights', '', (object)[
                'httpcode' => $result['httpcode'],
                'error'    => $result['error'],
            ]);
        }
    }
}
