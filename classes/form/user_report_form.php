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
 * User report search form.
 *
 * @package    local_courseinsights
 * @copyright  2026 Andrei Toma
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_courseinsights\form;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->libdir . '/formslib.php');

/**
 * Single-field autocomplete form for selecting a user on the user report page.
 */
class user_report_form extends \moodleform {
    /**
     * Defines the form elements: a user autocomplete field and a submit button.
     */
    public function definition(): void {
        $mform = $this->_form;

        $useroption = $this->_customdata['useroption'] ?? [];

        $mform->addElement(
            'autocomplete',
            'userid',
            get_string('userreport_searchlabel', 'local_courseinsights'),
            $useroption,
            [
                'ajax'            => 'local_courseinsights/user_selector',
                'noselectionstring' => get_string('userreport_noselection', 'local_courseinsights'),
                'placeholder'     => get_string('userreport_searchplaceholder', 'local_courseinsights'),
            ]
        );
        $mform->setType('userid', PARAM_INT);

        $this->add_action_buttons(false, get_string('filter', 'local_courseinsights'));
    }
}
