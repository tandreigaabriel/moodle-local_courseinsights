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
 * Scheduled task to rebuild the Course Insights summary cache.
 *
 * @package    local_courseinsights
 * @copyright  2026 Andrei Toma
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_courseinsights\task;

/**
 * Rebuilds the aggregated course summary cache used by the dashboard.
 */
class build_summary_cache extends \core\task\scheduled_task {
    /**
     * Returns the task name shown on the scheduled tasks admin page.
     *
     * @return string
     */
    public function get_name(): string {
        return get_string('buildsummarycache', 'local_courseinsights');
    }

    /**
     * Executes the task.
     *
     * @return void
     */
    public function execute(): void {
        \local_courseinsights\report_service::rebuild_summary_cache();
        mtrace(get_string('cachebuilt', 'local_courseinsights'));

        \local_courseinsights\report_service::rebuild_site_kpis_cache();
        mtrace(get_string('sitecachebuilt', 'local_courseinsights'));

        \local_courseinsights\report_service::rebuild_course_detail_snapshots();
        mtrace(get_string('detailcachebuilt', 'local_courseinsights'));

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
        }
    }
}
