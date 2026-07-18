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
 * Ad-hoc task: rebuild the cached chart payload for a single course.
 *
 * @package    local_courseinsights
 * @copyright  2026 Andrei Toma
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_courseinsights\task;

/**
 * Rebuilds the cached chart payload for one course.
 *
 * Queued by report_service::rebuild_course_detail_snapshots() — one task per
 * visible course that does not have a fresh snapshot. Moodle's cron runner
 * processes the queue in the background, keeping the main scheduled task fast.
 */
class build_course_detail_snapshot extends \core\task\adhoc_task {
    /**
     * Returns the task display name shown in the ad-hoc task queue.
     *
     * @return string
     */
    public function get_name(): string {
        return get_string('buildcoursedetailsnapshot', 'local_courseinsights');
    }

    /**
     * Rebuilds the chart payload snapshot for the course stored in custom data.
     *
     * @return void
     */
    public function execute(): void {
        $data     = $this->get_custom_data();
        $courseid = (int) ($data->courseid ?? 0);

        if ($courseid <= 0) {
            mtrace('build_course_detail_snapshot: missing courseid in custom data — skipping.');
            return;
        }

        $start = microtime(true);
        mtrace("build_course_detail_snapshot: rebuilding course {$courseid}…");
        \local_courseinsights\report_service::rebuild_single_course_detail_snapshot($courseid);
        $elapsed = round(microtime(true) - $start, 2);
        mtrace("build_course_detail_snapshot: course {$courseid} complete in {$elapsed}s.");
    }
}
