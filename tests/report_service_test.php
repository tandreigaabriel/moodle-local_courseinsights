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
 * PHPUnit tests for report_service.
 *
 * @package    local_courseinsights
 * @copyright  2026 Andrei Toma
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_courseinsights;

/**
 * Tests for \local_courseinsights\report_service.
 *
 * @covers \local_courseinsights\report_service
 */
final class report_service_test extends \advanced_testcase {
    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
    }

    /**
     * Insert a course-level grade_item row and return its id.
     *
     * @param int $courseid Course id.
     * @param float $grademax Maximum grade.
     * @return int Grade item id.
     */
    private function create_course_grade_item(int $courseid, float $grademax = 100.0): int {
        global $DB;
        return $DB->insert_record('grade_items', (object)[
            'courseid'       => $courseid,
            'itemtype'       => 'course',
            'itemname'       => 'Course total',
            'grademax'       => $grademax,
            'grademin'       => 0.0,
            'gradepass'      => 50.0,
            'sortorder'      => 1,
            'gradetype'      => 1,
            'display'        => 0,
            'hidden'         => 0,
            'locked'         => 0,
            'needsupdate'    => 0,
            'weightoverride' => 0,
            'timecreated'    => time(),
            'timemodified'   => time(),
        ]);
    }

    /**
     * Insert a grade_grades row for a student.
     *
     * @param int $itemid Grade item id.
     * @param int $userid Student id.
     * @param float $grade Final grade.
     * @param float $grademax Maximum grade.
     * @return void
     */
    private function set_grade(int $itemid, int $userid, float $grade, float $grademax = 100.0): void {
        global $DB;
        $DB->insert_record('grade_grades', (object)[
            'itemid'         => $itemid,
            'userid'         => $userid,
            'finalgrade'     => $grade,
            'rawgrade'       => $grade,
            'rawgrademax'    => $grademax,
            'rawgrademin'    => 0.0,
            'hidden'         => 0,
            'locked'         => 0,
            'exported'       => 0,
            'overridden'     => 0,
            'excluded'       => 0,
            'feedbackformat' => 0,
            'timecreated'    => time(),
            'timemodified'   => time(),
        ]);
    }

    /**
     * Returns empty array when course has no grade items.
     */
    public function test_get_top_students_by_grade_empty_no_grade_items(): void {
        $course = $this->getDataGenerator()->create_course();
        $result = \local_courseinsights\report_service::get_top_students_by_grade($course->id);
        $this->assertIsArray($result);
        $this->assertEmpty($result);
    }

    /**
     * Returns empty array when grade_item exists but no students have grades.
     */
    public function test_get_top_students_by_grade_empty_no_grades(): void {
        $course = $this->getDataGenerator()->create_course();
        $this->create_course_grade_item($course->id);
        $result = \local_courseinsights\report_service::get_top_students_by_grade($course->id);
        $this->assertIsArray($result);
        $this->assertEmpty($result);
    }

    /**
     * Excludes users who have a grade but are not enrolled.
     */
    public function test_get_top_students_by_grade_excludes_unenrolled(): void {
        $course = $this->getDataGenerator()->create_course();
        $itemid = $this->create_course_grade_item($course->id);

        $user = $this->getDataGenerator()->create_user();
        // Give grade but do NOT enrol.
        $this->set_grade($itemid, $user->id, 95.0);

        $result = \local_courseinsights\report_service::get_top_students_by_grade($course->id);
        $this->assertEmpty($result, 'Unenrolled user with a grade must not appear in the leaderboard.');
    }

    /**
     * Returns enrolled students ranked by grade, highest first.
     */
    public function test_get_top_students_by_grade_ranked_highest_first(): void {
        $course  = $this->getDataGenerator()->create_course();
        $itemid  = $this->create_course_grade_item($course->id);
        $grades  = [70.0, 90.0, 80.0]; // Insert out of order deliberately.
        $students = [];

        foreach ($grades as $g) {
            $u = $this->getDataGenerator()->create_user();
            $this->getDataGenerator()->enrol_user($u->id, $course->id, 'student');
            $this->set_grade($itemid, $u->id, $g);
            $students[$g] = $u;
        }

        $result = \local_courseinsights\report_service::get_top_students_by_grade($course->id);
        $this->assertCount(3, $result);
        $this->assertEquals('90.00', $result[0]['finalgrade'], 'Highest grade should be rank 1.');
        $this->assertEquals('80.00', $result[1]['finalgrade']);
        $this->assertEquals('70.00', $result[2]['finalgrade']);
    }

    /**
     * Rank badges are gold / silver / bronze for top 3.
     */
    public function test_get_top_students_by_grade_rank_badges(): void {
        $course = $this->getDataGenerator()->create_course();
        $itemid = $this->create_course_grade_item($course->id);

        foreach ([90.0, 80.0, 70.0] as $g) {
            $u = $this->getDataGenerator()->create_user();
            $this->getDataGenerator()->enrol_user($u->id, $course->id, 'student');
            $this->set_grade($itemid, $u->id, $g);
        }

        $result = \local_courseinsights\report_service::get_top_students_by_grade($course->id);
        $this->assertEquals(1, $result[0]['rank']);
        $this->assertEquals('ci-rank--gold', $result[0]['rankbadgeclass']);
        $this->assertEquals('ci-rank--silver', $result[1]['rankbadgeclass']);
        $this->assertEquals('ci-rank--bronze', $result[2]['rankbadgeclass']);
    }

    /**
     * Respects the $limit parameter.
     */
    public function test_get_top_students_by_grade_respects_limit(): void {
        $course = $this->getDataGenerator()->create_course();
        $itemid = $this->create_course_grade_item($course->id);

        for ($i = 1; $i <= 5; $i++) {
            $u = $this->getDataGenerator()->create_user();
            $this->getDataGenerator()->enrol_user($u->id, $course->id, 'student');
            $this->set_grade($itemid, $u->id, (float)($i * 10));
        }

        $result = \local_courseinsights\report_service::get_top_students_by_grade($course->id, 3);
        $this->assertCount(3, $result, 'Limit parameter must be respected.');
    }

    /**
     * De-duplicates students with multiple enrolment records (manual + self-enrol).
     */
    public function test_get_top_students_by_grade_no_duplicates_multi_enrol(): void {
        global $DB;

        $course  = $this->getDataGenerator()->create_course();
        $itemid  = $this->create_course_grade_item($course->id);
        $student = $this->getDataGenerator()->create_user();

        // Enrol via manual method.
        $this->getDataGenerator()->enrol_user($student->id, $course->id, 'student', 'manual');

        // Add a second active enrol record (simulates self-enrol alongside manual).
        $enrolid2 = $DB->insert_record('enrol', (object)[
            'courseid'  => $course->id,
            'enrol'     => 'self',
            'status'    => 0,
            'roleid'    => 5,
            'timecreated'  => time(),
            'timemodified' => time(),
        ]);
        $DB->insert_record('user_enrolments', (object)[
            'enrolid'      => $enrolid2,
            'userid'       => $student->id,
            'status'       => 0,
            'timecreated'  => time(),
            'timemodified' => time(),
        ]);

        $this->set_grade($itemid, $student->id, 85.0);

        $result = \local_courseinsights\report_service::get_top_students_by_grade($course->id);
        $this->assertCount(1, $result, 'Student enrolled twice must appear only once in the leaderboard.');
    }

    /**
     * Score percentage calculation is correct.
     */
    public function test_get_top_students_by_grade_pct_calculation(): void {
        $course = $this->getDataGenerator()->create_course();
        $itemid = $this->create_course_grade_item($course->id, 200.0);

        $u = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($u->id, $course->id, 'student');
        $this->set_grade($itemid, $u->id, 150.0, 200.0);

        $result = \local_courseinsights\report_service::get_top_students_by_grade($course->id);
        $this->assertCount(1, $result);
        $this->assertEquals('75.0', $result[0]['pct'], 'Score percentage should be 150/200 = 75%.');
        $this->assertEquals(75, $result[0]['widthpct']);
    }

    /**
     * Returns empty array for a course with no activities.
     */
    public function test_get_module_completion_funnel_empty_no_activities(): void {
        $course = $this->getDataGenerator()->create_course(['enablecompletion' => 1]);
        $result = \local_courseinsights\report_service::get_module_completion_funnel($course->id);
        $this->assertIsArray($result);
        $this->assertEmpty($result);
    }

    /**
     * Returns only activities that have completion tracking enabled.
     */
    public function test_get_module_completion_funnel_only_tracked_activities(): void {
        $course = $this->getDataGenerator()->create_course(['enablecompletion' => 1]);
        $student = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($student->id, $course->id, 'student');

        // Activity WITH completion tracking.
        $this->getDataGenerator()->create_module('page', [
            'course'     => $course->id,
            'completion' => COMPLETION_TRACKING_MANUAL,
        ]);
        // Activity WITHOUT completion tracking.
        $this->getDataGenerator()->create_module('page', [
            'course'     => $course->id,
            'completion' => COMPLETION_TRACKING_NONE,
        ]);

        $result = \local_courseinsights\report_service::get_module_completion_funnel($course->id);
        $this->assertCount(1, $result, 'Only the activity with completion tracking should appear.');
    }

    /**
     * CSV rows protect text cells including course and teacher names.
     */
    public function test_csv_safe_row_protects_text(): void {
        $this->assertSame(
            ["'=Course", "'=Teacher", "'+Student", "'-Name", "'@Name", 12],
            \local_courseinsights\report_service::csv_safe_row(['=Course', '=Teacher', '+Student', '-Name', '@Name', 12])
        );
    }

    /**
     * Returns an array (even when empty) — guards against SQL errors.
     */
    public function test_get_top_courses_by_completion_returns_array(): void {
        $result = \local_courseinsights\report_service::get_top_courses_by_completion(10);
        $this->assertIsArray($result);
    }

    /**
     * Respects the limit parameter.
     */
    public function test_get_top_courses_by_completion_respects_limit(): void {
        for ($i = 0; $i < 5; $i++) {
            $this->getDataGenerator()->create_course();
        }
        $result = \local_courseinsights\report_service::get_top_courses_by_completion(3);
        $this->assertLessThanOrEqual(3, count($result));
    }
}
