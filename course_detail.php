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
 * Course Insights - single-course detail page.
 *
 * @package    local_courseinsights
 * @copyright  2026 Andrei Toma
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');

$context = \core\context\system::instance();
$PAGE->set_context($context);
require_login();
require_capability('local/courseinsights:view', $context);

$courseid = required_param('courseid', PARAM_INT);

$record = \local_courseinsights\report_service::get_course_detail($courseid);

if (!$record) {
    throw new \moodle_exception('invalidcourseid', 'error');
}

$url     = new moodle_url('/local/courseinsights/course_detail.php', ['courseid' => $courseid]);
$dashurl = new moodle_url('/local/courseinsights/index.php');

$PAGE->set_url($url);
$PAGE->set_title(format_string($record->fullname) . ' — ' . get_string('pluginname', 'local_courseinsights'));
$PAGE->set_heading(get_string('dashboard', 'local_courseinsights'));
$PAGE->set_pagelayout('report');

$now          = time();
$thirtydays   = 30 * DAYSECS;
$lastactivity = !empty($record->lastactivity) ? (int)$record->lastactivity : null;
$isactive     = $lastactivity && ($now - $lastactivity) < $thirtydays;
$completionrate = isset($record->completionrate) ? (float)$record->completionrate : null;

$exporturl = \local_courseinsights\report_service::get_export_url([
    'courseid'      => $courseid,
    'categoryid'    => 0,
    'startdate'     => '',
    'enddate'       => '',
    'activitytype'  => 'all',
    'studentstatus' => 'active',
    'usecache'      => 0,
    'sortby'        => 'course',
    'sortdir'       => 'asc',
]);

$templatecontext = [
    'coursename'              => format_string($record->fullname),
    'dashboardurl'            => $dashurl->out(false),
    'hasexport'               => has_capability('local/courseinsights:export', $context),
    'exporturl'               => $exporturl->out(false),
    'exportlabel'             => get_string('exportcsv', 'local_courseinsights'),
    'isactive'                => $isactive,
    'statuslabel'             => $isactive
        ? get_string('active', 'local_courseinsights')
        : get_string('inactive', 'local_courseinsights'),
    'completionratedisplay'   => $completionrate !== null ? $completionrate . '%' : '-',
    'completionratewidth'     => ($completionrate !== null ? (int)$completionrate : 0) . '%',
    'enrolledstudents'        => (int)($record->enrolledstudents ?? 0),
    'submittedassignments'    => (int)($record->submittedassignments ?? 0),
    'quizattempts'            => (int)($record->quizattempts ?? 0),
    'assignments'             => (int)($record->assignments ?? 0),
    'quizzes'                 => (int)($record->quizzes ?? 0),
    'forumactivities'         => (int)($record->forumactivities ?? 0),
    'teachers'                => !empty($record->teachers) ? $record->teachers : '-',
    'lastactivity'            => $lastactivity
        ? userdate($lastactivity, get_string('strftimedate', 'langconfig'))
        : '-',
    // Labels.
    'label_completionrate'    => get_string('completionrate', 'local_courseinsights'),
    'label_enrolled'          => get_string('enrolledstudents', 'local_courseinsights'),
    'label_submissions'       => get_string('submittedassignments', 'local_courseinsights'),
    'label_quizattempts'      => get_string('quizattempts', 'local_courseinsights'),
    'label_contentbreakdown'  => get_string('contentbreakdown', 'local_courseinsights'),
    'label_totalassignments'  => get_string('totalassignments', 'local_courseinsights'),
    'label_totalquizzes'      => get_string('totalquizzes', 'local_courseinsights'),
    'label_forumactivities'   => get_string('forumactivities', 'local_courseinsights'),
    'label_teachers'          => get_string('teachers', 'local_courseinsights'),
    'label_lastactivity'      => get_string('lastactivity', 'local_courseinsights'),
    'label_backtodashboard'   => get_string('backtodashboard', 'local_courseinsights'),
    'dashboardlabel'          => get_string('dashboard', 'local_courseinsights'),
];

echo $OUTPUT->header();
echo $OUTPUT->render_from_template('local_courseinsights/course_detail', $templatecontext);
echo $OUTPUT->footer();
