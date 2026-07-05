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

$licstatus = \local_courseinsights\license::get_status();
if (
    $licstatus === \local_courseinsights\license::STATUS_EXPIRED ||
        $licstatus === \local_courseinsights\license::STATUS_UNLICENSED
) {
    echo $OUTPUT->header();
    echo $OUTPUT->notification(get_string('license_required', 'local_courseinsights'), 'error');
    echo $OUTPUT->footer();
    die;
}

$courseid = required_param('courseid', PARAM_INT);
$print    = optional_param('print', 0, PARAM_INT);

$record = \local_courseinsights\report_service::get_course_detail($courseid);

if (!$record) {
    redirect(new moodle_url('/local/courseinsights/index.php'));
}

$url      = new moodle_url('/local/courseinsights/course_detail.php', ['courseid' => $courseid]);
$dashurl  = new moodle_url('/local/courseinsights/index.php');
$printurl = new moodle_url('/local/courseinsights/course_detail.php', ['courseid' => $courseid, 'print' => 1]);

$PAGE->set_url($url);
$PAGE->set_title(format_string($record->fullname) . ' — ' . get_string('pluginname', 'local_courseinsights'));
$PAGE->set_heading(get_string('dashboard', 'local_courseinsights'));
$PAGE->set_pagelayout($print ? 'print' : 'report');

if ($print) {
    $PAGE->requires->js_init_code('window.print();');
}

$now          = time();
$thirtydays   = 30 * DAYSECS;
$lastactivity = !empty($record->lastactivity) ? (int)$record->lastactivity : null;
$isactive     = $lastactivity && ($now - $lastactivity) < $thirtydays;
$completionrate = isset($record->completionrate) ? (float)$record->completionrate : null;

$detailsnapshot = \local_courseinsights\report_service::get_course_detail_snapshot($courseid);
$usesnapshot = is_array($detailsnapshot);

$gradedist = $usesnapshot
    ? ($detailsnapshot['gradedist'] ?? [])
    : \local_courseinsights\report_service::get_grade_distribution($courseid);
$heatmap = $usesnapshot
    ? ($detailsnapshot['heatmap'] ?? [])
    : \local_courseinsights\report_service::get_engagement_heatmap($courseid);

$timeline = $usesnapshot
    ? ($detailsnapshot['timeline'] ?? [])
    : \local_courseinsights\report_service::get_submission_timeline($courseid);

$hasstudentaccess = has_capability('local/courseinsights:manage', $context);
$studenttable     = $hasstudentaccess
    ? \local_courseinsights\report_service::get_student_activity_table($courseid)
    : [];

$quizbreakdown = $usesnapshot
    ? ($detailsnapshot['quizbreakdown'] ?? [])
    : \local_courseinsights\report_service::get_quiz_score_breakdown($courseid);
$trend = $usesnapshot
    ? ($detailsnapshot['trend'] ?? [])
    : \local_courseinsights\report_service::get_course_trend($courseid);
$modulefunnel = $usesnapshot
    ? ($detailsnapshot['modulefunnel'] ?? [])
    : \local_courseinsights\report_service::get_module_completion_funnel($courseid);
$leaderboard    = \local_courseinsights\report_service::get_top_students_by_grade($courseid);

$brandaccent  = (string) get_config('local_courseinsights', 'brandaccentcolor');
$brandlogourl = (string) get_config('local_courseinsights', 'brandlogourl');
$brandname    = (string) get_config('local_courseinsights', 'brandname');
$validaccent  = $brandaccent && preg_match('/^#[0-9a-fA-F]{3,6}$/', $brandaccent);

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
    'label_backtodashboard'          => get_string('backtodashboard', 'local_courseinsights'),
    'dashboardlabel'                 => get_string('dashboard', 'local_courseinsights'),
    'hasgradedata'                   => !empty($gradedist),
    'gradedistribution'              => $gradedist,
    'label_gradedistribution'        => get_string('gradedistribution', 'local_courseinsights'),
    'label_gradedistribution_nodata' => get_string('gradedistribution_nodata', 'local_courseinsights'),
    'hastimelinedata'                    => !empty($timeline),
    'submissiontimeline'                 => $timeline,
    'label_submissiontimeline'           => get_string('submissiontimeline', 'local_courseinsights'),
    'label_submissiontimeline_nodata'    => get_string('submissiontimeline_nodata', 'local_courseinsights'),
    'hasheatmapdata'                     => !empty($heatmap),
    'heatmapweeks'                       => $heatmap,
    'label_engagementheatmap'            => get_string('engagementheatmap', 'local_courseinsights'),
    'label_engagementheatmap_nodata'     => get_string('engagementheatmap_nodata', 'local_courseinsights'),
    'hasstudentaccess'                   => $hasstudentaccess,
    'hasstudents'                        => !empty($studenttable),
    'students'                           => $studenttable,
    'label_studentactivitytable'         => get_string('studentactivitytable', 'local_courseinsights'),
    'label_studentactivitytable_nodata'  => get_string('studentactivitytable_nodata', 'local_courseinsights'),
    'label_col_student'                  => get_string('col_student', 'local_courseinsights'),
    'label_col_lastaccess'               => get_string('col_lastaccess', 'local_courseinsights'),
    'label_col_submissions'              => get_string('col_submissions', 'local_courseinsights'),
    'label_col_quizattempts'             => get_string('col_quizattempts', 'local_courseinsights'),
    'hasquizbreakdown'                   => !empty($quizbreakdown),
    'quizbreakdown'                      => $quizbreakdown,
    'label_quizbreakdown'                => get_string('quizbreakdown', 'local_courseinsights'),
    'label_quizbreakdown_nodata'         => get_string('quizbreakdown_nodata', 'local_courseinsights'),
    'label_col_quiz'                     => get_string('col_quiz', 'local_courseinsights'),
    'label_col_quizstudents'             => get_string('col_quizstudents', 'local_courseinsights'),
    'label_col_avg'                      => get_string('col_avg', 'local_courseinsights'),
    'label_col_min'                      => get_string('col_min', 'local_courseinsights'),
    'label_col_max'                      => get_string('col_max', 'local_courseinsights'),
    'label_col_passrate'                 => get_string('col_passrate', 'local_courseinsights'),
    'printurl'                           => $printurl->out(false),
    'printlabel'                         => get_string('printreport', 'local_courseinsights'),
    'isprint'                            => (bool) $print,
    'hasbrandlogo'                       => $brandlogourl !== '',
    'brandlogourl'                       => $brandlogourl,
    'brandlabel'                         => $brandname !== ''
        ? $brandname
        : get_string('pluginname', 'local_courseinsights'),
    'hasmodulefunnel'              => !empty($modulefunnel),
    'modulefunnel'                 => $modulefunnel,
    'label_modulefunnel'           => get_string('modulefunnel', 'local_courseinsights'),
    'label_modulefunnel_nodata'    => get_string('modulefunnel_nodata', 'local_courseinsights'),
    'label_col_activity'           => get_string('col_activity', 'local_courseinsights'),
    'label_col_modtype'            => get_string('col_modtype', 'local_courseinsights'),
    'label_col_completions'        => get_string('col_completions', 'local_courseinsights'),
    'label_col_completionpct'      => get_string('col_completionpct', 'local_courseinsights'),
    'hasleaderboard'               => !empty($leaderboard),
    'leaderboard'                  => $leaderboard,
    'label_leaderboard'            => get_string('leaderboard', 'local_courseinsights'),
    'label_leaderboard_nodata'     => get_string('leaderboard_nodata', 'local_courseinsights'),
    'label_col_rank'               => get_string('col_rank', 'local_courseinsights'),
    'label_col_grade'              => get_string('col_grade', 'local_courseinsights'),
    'label_col_score_pct'          => get_string('col_score_pct', 'local_courseinsights'),
    'label_trend'        => get_string('trend_heading', 'local_courseinsights'),
    'label_trend_active' => get_string('trend_active', 'local_courseinsights'),
    'label_trend_subs'   => get_string('trend_subs', 'local_courseinsights'),
    'label_trend_quiz'   => get_string('trend_quiz', 'local_courseinsights'),
    'label_trend_vs'     => get_string('trend_vs', 'local_courseinsights'),
] + $trend;

echo $OUTPUT->header();

if (!$usesnapshot) {
    echo $OUTPUT->notification(get_string('detailcachepending', 'local_courseinsights'), 'info');
}

if ($validaccent) {
    echo '<style>.local-courseinsights-detail{'
        . '--ci-primary:' . s($brandaccent) . ';}</style>';
}

if (!$print) {
    echo html_writer::start_div('ci-page-layout');

    // Sidebar — course quick-info panel.
    echo html_writer::start_div('ci-sidebar', ['id' => 'ci-filter-sidebar']);
    echo html_writer::start_div('ci-sidebar__header');
    echo html_writer::tag('span', get_string('coursedetail', 'local_courseinsights'), ['class' => 'ci-sidebar__title']);
    echo html_writer::tag('button', '&times;', [
        'class'      => 'ci-sidebar-close',
        'id'         => 'ci-sidebar-close',
        'aria-label' => get_string('closebuttontitle', 'moodle'),
    ]);
    echo html_writer::end_div();

    echo html_writer::start_div('ci-detail-sidebar-body');

    $badgecls = $isactive ? 'ci-badge ci-badge--active' : 'ci-badge ci-badge--inactive';
    echo html_writer::tag(
        'div',
        html_writer::tag('span', $templatecontext['statuslabel'], ['class' => $badgecls]),
        ['class' => 'ci-sidebar-stat']
    );

    echo html_writer::start_div('ci-sidebar-stat');
    echo html_writer::tag('span', $templatecontext['label_completionrate'], ['class' => 'ci-sidebar-stat__label']);
    echo html_writer::start_div('ci-sidebar-progress');
    echo html_writer::tag('div', '', [
        'class' => 'ci-sidebar-progress__fill',
        'style' => 'width:' . $templatecontext['completionratewidth'],
    ]);
    echo html_writer::end_div();
    echo html_writer::tag('span', $templatecontext['completionratedisplay'], ['class' => 'ci-sidebar-stat__value']);
    echo html_writer::end_div();

    echo html_writer::start_div('ci-sidebar-stat');
    echo html_writer::tag('span', $templatecontext['label_enrolled'], ['class' => 'ci-sidebar-stat__label']);
    echo html_writer::tag(
        'span',
        (string)$templatecontext['enrolledstudents'],
        ['class' => 'ci-sidebar-stat__value ci-sidebar-stat__value--big']
    );
    echo html_writer::end_div();

    echo html_writer::start_div('ci-sidebar-stat');
    echo html_writer::tag('span', $templatecontext['label_teachers'], ['class' => 'ci-sidebar-stat__label']);
    echo html_writer::tag(
        'span',
        $templatecontext['teachers'],
        ['class' => 'ci-sidebar-stat__value ci-sidebar-stat__value--sm']
    );
    echo html_writer::end_div();

    echo html_writer::start_div('ci-sidebar-stat');
    echo html_writer::tag('span', $templatecontext['label_lastactivity'], ['class' => 'ci-sidebar-stat__label']);
    echo html_writer::tag('span', $templatecontext['lastactivity'], ['class' => 'ci-sidebar-stat__value']);
    echo html_writer::end_div();

    echo html_writer::end_div();
    echo html_writer::end_div();
    echo html_writer::start_div('ci-page-main');
    echo html_writer::tag('button', '&#9776;', [
        'class'          => 'ci-sidebar-toggle',
        'id'             => 'ci-sidebar-toggle',
        'aria-label'     => get_string('filters', 'local_courseinsights'),
        'aria-expanded'  => 'true',
        'aria-controls'  => 'ci-filter-sidebar',
    ]);
}

echo $OUTPUT->render_from_template('local_courseinsights/course_detail', $templatecontext);

if (!$print) {
    echo html_writer::end_div();
    echo html_writer::end_div();
    $PAGE->requires->js_call_amd('local_courseinsights/filter', 'init', [$context->id]);
}

echo $OUTPUT->footer();
