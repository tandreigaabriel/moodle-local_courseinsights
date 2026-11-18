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
 * Licence enforcement tests.
 *
 * @package local_courseinsights
 * @copyright 2026 Andrei Toma
 * @license https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
namespace local_courseinsights;

/**
 * Licence enforcement tests.
 *
 * @coversNothing
 */
final class license_gate_test extends \advanced_testcase {
    /**
     * Test search rejects an absent licence.
     */
    public function test_search_requires_licence(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        set_config('license_token', '', 'local_courseinsights');
        $this->expectException(\moodle_exception::class);
        \local_courseinsights\external\search_users::execute('test');
    }

    /**
     * Course search rejects an absent licence.
     */
    public function test_course_search_requires_licence(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        set_config('license_token', '', 'local_courseinsights');
        $this->expectException(\moodle_exception::class);
        \local_courseinsights\external\search_courses::execute('test');
    }

    /**
     * The course API rejects an absent licence.
     */
    public function test_course_api_requires_licence(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        set_config('license_token', '', 'local_courseinsights');
        $this->expectException(\moodle_exception::class);
        \local_courseinsights\external\get_courses::execute();
    }

    /**
     * Dashboard fragments reject an absent licence.
     */
    public function test_fragment_requires_licence(): void {
        global $CFG;
        $this->resetAfterTest();
        $this->setAdminUser();
        set_config('license_token', '', 'local_courseinsights');
        require_once($CFG->dirroot . '/local/courseinsights/lib.php');
        $this->expectException(\moodle_exception::class);
        local_courseinsights_output_fragment_dashboard([]);
    }
}
