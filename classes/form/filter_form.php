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
 * Report filter form.
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
 * Filter form for the Course Insights dashboard.
 */
class filter_form extends \moodleform {
    /**
     * Defines the filter form fields.
     *
     * @return void
     */
    public function definition(): void {
        $mform = $this->_form;

        $courseoption = $this->_customdata['courseoption'] ?? [];
        $categories = $this->_customdata['categories'] ?? [];
        $cohorts = $this->_customdata['cohorts'] ?? [];

        if (count($cohorts) > 1) {
            $mform->addElement('select', 'cohortid', get_string('cohort', 'local_courseinsights'), $cohorts);
            $mform->setType('cohortid', PARAM_INT);
            $mform->setDefault('cohortid', 0);
        }

        $mform->addElement('select', 'categoryid', get_string('category', 'local_courseinsights'), $categories);
        $mform->setType('categoryid', PARAM_INT);
        $mform->setDefault('categoryid', 0);

        $mform->addElement('autocomplete', 'courseid', get_string('course', 'local_courseinsights'), $courseoption, [
            'ajax'              => 'local_courseinsights/course_selector',
            'noselectionstring' => '',
            'placeholder'       => get_string('allcourses', 'local_courseinsights'),
        ]);
        $mform->setType('courseid', PARAM_INT);
        $mform->setDefault('courseid', 0);

        $mform->addElement(
            'text',
            'startdate',
            get_string('startdate', 'local_courseinsights'),
            ['placeholder' => 'YYYY-MM-DD', 'class' => 'ci-date-input']
        );
        $mform->setType('startdate', PARAM_TEXT);
        $mform->addHelpButton('startdate', 'dateformathelp', 'local_courseinsights');

        $mform->addElement(
            'text',
            'enddate',
            get_string('enddate', 'local_courseinsights'),
            ['placeholder' => 'YYYY-MM-DD', 'class' => 'ci-date-input']
        );
        $mform->setType('enddate', PARAM_TEXT);
        $mform->addHelpButton('enddate', 'dateformathelp', 'local_courseinsights');

        $presetbuttons =
            \html_writer::tag('button', get_string('datepreset_7days', 'local_courseinsights'), [
                'type' => 'button',
                'class' => 'btn btn-sm btn-outline-secondary local-courseinsights-date-preset',
                'data-ci-preset' => '7days',
            ]) .
            \html_writer::tag('button', get_string('datepreset_30days', 'local_courseinsights'), [
                'type' => 'button',
                'class' => 'btn btn-sm btn-outline-secondary local-courseinsights-date-preset',
                'data-ci-preset' => '30days',
            ]) .
            \html_writer::tag('button', get_string('datepreset_thismonth', 'local_courseinsights'), [
                'type' => 'button',
                'class' => 'btn btn-sm btn-outline-secondary local-courseinsights-date-preset',
                'data-ci-preset' => 'thismonth',
            ]) .
            \html_writer::tag('button', get_string('datepreset_clear', 'local_courseinsights'), [
                'type' => 'button',
                'class' => 'btn btn-sm btn-outline-danger local-courseinsights-date-preset',
                'data-ci-preset' => 'clear',
            ]);
        $mform->addElement('html', \html_writer::div(
            \html_writer::div($presetbuttons, 'ci-presets-grid'),
            'local-courseinsights-date-presets mb-2'
        ));

        $mform->addElement('header', 'compareheader', get_string('compareperiod_heading', 'local_courseinsights'));
        $mform->setExpanded('compareheader', false);

        $mform->addElement(
            'text',
            'compare_startdate',
            get_string('compareperiod_start', 'local_courseinsights'),
            ['placeholder' => 'YYYY-MM-DD', 'class' => 'ci-date-input']
        );
        $mform->setType('compare_startdate', PARAM_TEXT);
        $mform->addHelpButton('compare_startdate', 'dateformathelp', 'local_courseinsights');

        $mform->addElement(
            'text',
            'compare_enddate',
            get_string('compareperiod_end', 'local_courseinsights'),
            ['placeholder' => 'YYYY-MM-DD', 'class' => 'ci-date-input']
        );
        $mform->setType('compare_enddate', PARAM_TEXT);
        $mform->addHelpButton('compare_enddate', 'dateformathelp', 'local_courseinsights');

        $activitytypes = [
            'all' => get_string('activitytype_all', 'local_courseinsights'),
            'assign' => get_string('activitytype_assign', 'local_courseinsights'),
            'quiz' => get_string('activitytype_quiz', 'local_courseinsights'),
            'exam' => get_string('activitytype_exam', 'local_courseinsights'),
            'mini' => get_string('activitytype_mini', 'local_courseinsights'),
        ];

        $mform->addElement('select', 'activitytype', get_string('activitytype', 'local_courseinsights'), $activitytypes);
        $mform->setType('activitytype', PARAM_ALPHA);
        $mform->setDefault('activitytype', 'all');

        $studentstatuses = [
            'active' => get_string('studentstatus_active', 'local_courseinsights'),
            'suspended' => get_string('studentstatus_suspended', 'local_courseinsights'),
            'all' => get_string('studentstatus_all', 'local_courseinsights'),
        ];

        $mform->addElement('select', 'studentstatus', get_string('studentstatus', 'local_courseinsights'), $studentstatuses);
        $mform->setType('studentstatus', PARAM_ALPHA);
        $mform->setDefault('studentstatus', 'active');

        if (get_config('local_courseinsights', 'enablecache')) {
            $mform->addElement('header', 'advancedheader', get_string('advanced_options', 'local_courseinsights'));
            $mform->setExpanded('advancedheader', false);
            $mform->addElement('advcheckbox', 'usecache', get_string('usecache', 'local_courseinsights'));
            $mform->setType('usecache', PARAM_BOOL);
            $mform->setDefault('usecache', 0);
        }

        $mform->addElement('hidden', 'sortby', 'course');
        $mform->setType('sortby', PARAM_ALPHA);

        $mform->addElement('hidden', 'sortdir', 'asc');
        $mform->setType('sortdir', PARAM_ALPHA);

        $this->add_action_buttons(false, get_string('filter', 'local_courseinsights'));

        $reseturl = $this->_form->getAttribute('action');
        $mform->addElement('html', \html_writer::tag(
            'a',
            get_string('resetfilters', 'local_courseinsights'),
            [
                'href'  => $reseturl ?: '#',
                'class' => 'ci-reset-btn',
            ]
        ));
    }
}
