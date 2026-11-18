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

namespace local_courseinsights\privacy;

use core_privacy\local\request\approved_contextlist;
use core_privacy\local\request\userlist;
use core_privacy\local\request\writer;

/**
 * Intervention privacy coverage.
 *
 * @package local_courseinsights
 * @copyright 2026 Andrei Toma
 * @license https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers \local_courseinsights\privacy\provider
 */
final class provider_test extends \core_privacy\tests\provider_testcase {
    /**
     * The student and staff participants can be found, exported, and erased.
     */
    public function test_intervention_participants(): void {
        global $DB;
        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course();
        $student = $this->getDataGenerator()->create_user();
        $author = $this->getDataGenerator()->create_user();
        $assignee = $this->getDataGenerator()->create_user();
        $context = \context_course::instance($course->id);
        $id = $DB->insert_record('local_courseinsights_interventions', (object) [
            'userid' => $student->id, 'courseid' => $course->id, 'createdby' => $author->id,
            'assignedto' => $assignee->id, 'title' => 'Synthetic case', 'status' => 'new',
            'timecreated' => time(), 'timemodified' => time(),
        ]);
        $DB->insert_record('local_courseinsights_intervention_notes', (object) [
            'interventionid' => $id, 'userid' => $author->id, 'note' => 'Synthetic note',
            'isprivate' => 0, 'timecreated' => time(), 'timemodified' => time(),
        ]);
        $users = new userlist($context, 'local_courseinsights');
        provider::get_users_in_context($users);
        foreach ([$student, $author, $assignee] as $user) {
            $this->assertContainsEquals($context->id, provider::get_contexts_for_userid($user->id)->get_contextids());
            $this->assertContainsEquals($user->id, $users->get_userids());
            writer::reset();
            provider::export_user_data(new approved_contextlist($user, 'local_courseinsights', [$context->id]));
            $this->assertTrue(writer::with_context($context)->has_any_data());
        }
        provider::delete_data_for_user(new approved_contextlist($author, 'local_courseinsights', [$context->id]));
        $this->assertEquals(0, $DB->get_field('local_courseinsights_interventions', 'createdby', ['id' => $id]));
        $this->assertFalse($DB->record_exists('local_courseinsights_intervention_notes', ['interventionid' => $id]));
        provider::delete_data_for_user(new approved_contextlist($assignee, 'local_courseinsights', [$context->id]));
        $this->assertNull($DB->get_field('local_courseinsights_interventions', 'assignedto', ['id' => $id]));
        provider::delete_data_for_user(new approved_contextlist($student, 'local_courseinsights', [$context->id]));
        $this->assertFalse($DB->record_exists('local_courseinsights_interventions', ['id' => $id]));
    }
}
