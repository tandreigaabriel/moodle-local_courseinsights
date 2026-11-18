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
 * PHPUnit tests for capability/permission enforcement.
 *
 * Capability defaults (from db/access.php):
 *   local/courseinsights:view    → manager, editingteacher
 *   local/courseinsights:export  → manager, editingteacher
 *   local/courseinsights:manage  → manager only
 *
 * @package    local_courseinsights
 * @copyright  2026 Andrei Toma
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_courseinsights;

/**
 * Tests for local_courseinsights capability assignments.
 *
 * @coversNothing
 */
final class permissions_test extends \advanced_testcase {
    /** @var \context_system */
    private \context_system $ctx;

    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
        $this->ctx = \context_system::instance();
    }

    /**
     * A teacher assigned only within a course has no site-wide access.
     */
    public function test_course_teacher_cannot_access_site_services(): void {
        $course = $this->getDataGenerator()->create_course();
        $teacher = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user($teacher->id, $course->id, 'editingteacher');
        $this->setUser($teacher);
        $this->assertFalse(has_capability('local/courseinsights:createintervention', $this->ctx));
        $this->assertFalse(has_capability('local/courseinsights:view', $this->ctx));
        $this->expectException(\required_capability_exception::class);
        \local_courseinsights\external\search_users::execute('test');
    }

    /**
     * Manager role has view capability by default.
     */
    public function test_manager_has_view_capability(): void {
        $user = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->role_assign('manager', $user->id);
        $this->assertTrue(
            has_capability('local/courseinsights:view', $this->ctx, $user),
            'Manager must have local/courseinsights:view by default.'
        );
    }

    /**
     * Editing teacher has view capability by default.
     */
    public function test_editingteacher_has_view_capability(): void {
        $user = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->role_assign('editingteacher', $user->id);
        $this->assertTrue(
            has_capability('local/courseinsights:view', $this->ctx, $user),
            'Editing teacher must have local/courseinsights:view by default.'
        );
    }

    /**
     * Plain student does not have view capability by default.
     */
    public function test_student_lacks_view_capability(): void {
        $user = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->role_assign('student', $user->id);
        $this->assertFalse(
            has_capability('local/courseinsights:view', $this->ctx, $user),
            'Student must NOT have local/courseinsights:view by default.'
        );
    }

    /**
     * Authenticated user without any extra role does not have view capability.
     */
    public function test_authenticated_user_lacks_view_capability(): void {
        $user = $this->getDataGenerator()->create_user();
        $this->assertFalse(
            has_capability('local/courseinsights:view', $this->ctx, $user),
            'Plain authenticated user must NOT have view capability.'
        );
    }

    /**
     * Manager role has manage capability by default.
     */
    public function test_manager_has_manage_capability(): void {
        $user = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->role_assign('manager', $user->id);
        $this->assertTrue(
            has_capability('local/courseinsights:manage', $this->ctx, $user),
            'Manager must have local/courseinsights:manage by default.'
        );
    }

    /**
     * Editing teacher does NOT have manage capability by default.
     */
    public function test_editingteacher_lacks_manage_capability(): void {
        $user = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->role_assign('editingteacher', $user->id);
        $this->assertFalse(
            has_capability('local/courseinsights:manage', $this->ctx, $user),
            'Editing teacher must NOT have local/courseinsights:manage by default.'
        );
    }

    /**
     * Student does NOT have manage capability.
     */
    public function test_student_lacks_manage_capability(): void {
        $user = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->role_assign('student', $user->id);
        $this->assertFalse(
            has_capability('local/courseinsights:manage', $this->ctx, $user),
            'Student must NOT have local/courseinsights:manage.'
        );
    }

    /**
     * Manager has export capability by default.
     */
    public function test_manager_has_export_capability(): void {
        $user = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->role_assign('manager', $user->id);
        $this->assertTrue(
            has_capability('local/courseinsights:export', $this->ctx, $user),
            'Manager must have local/courseinsights:export by default.'
        );
    }

    /**
     * Editing teacher has export capability by default.
     */
    public function test_editingteacher_has_export_capability(): void {
        $user = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->role_assign('editingteacher', $user->id);
        $this->assertTrue(
            has_capability('local/courseinsights:export', $this->ctx, $user),
            'Editing teacher must have local/courseinsights:export by default.'
        );
    }

    /**
     * Student does not have export capability by default.
     */
    public function test_student_lacks_export_capability(): void {
        $user = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->role_assign('student', $user->id);
        $this->assertFalse(
            has_capability('local/courseinsights:export', $this->ctx, $user),
            'Student must NOT have local/courseinsights:export by default.'
        );
    }

    /**
     * A student granted the view capability explicitly can access the plugin.
     */
    public function test_view_capability_can_be_explicitly_granted(): void {
        $user   = $this->getDataGenerator()->create_user();
        $roleid = $this->getDataGenerator()->create_role();
        assign_capability('local/courseinsights:view', CAP_ALLOW, $roleid, $this->ctx->id);
        role_assign($roleid, $user->id, $this->ctx->id);

        $this->assertTrue(
            has_capability('local/courseinsights:view', $this->ctx, $user),
            'Explicitly granted view capability must allow access.'
        );
    }

    /**
     * A manager with view explicitly prohibited cannot access the plugin.
     */
    public function test_view_capability_can_be_explicitly_prohibited(): void {
        $user = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->role_assign('manager', $user->id);

        // Verify they have it first.
        $this->assertTrue(has_capability('local/courseinsights:view', $this->ctx, $user));

        // Create an override role that prohibits the capability.
        $roleid = $this->getDataGenerator()->create_role();
        assign_capability('local/courseinsights:view', CAP_PROHIBIT, $roleid, $this->ctx->id);
        role_assign($roleid, $user->id, $this->ctx->id);

        // Must reload the context accessinfo.
        accesslib_clear_all_caches_for_unit_testing();

        $this->assertFalse(
            has_capability('local/courseinsights:view', $this->ctx, $user),
            'PROHIBIT override must deny view capability even for managers.'
        );
    }
}
