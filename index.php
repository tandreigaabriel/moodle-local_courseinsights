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
 * Course Insights dashboard page.
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

$url = new moodle_url('/local/courseinsights/index.php');

$PAGE->set_url($url);
$PAGE->set_title(get_string('dashboard', 'local_courseinsights'));
$PAGE->set_heading(get_string('dashboard', 'local_courseinsights'));
$PAGE->set_pagelayout('report');

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

$PAGE->requires->js_call_amd('local_courseinsights/filter', 'init', [$context->id]);

$filters = \local_courseinsights\report_service::get_filters_from_request();
$page = max(0, optional_param('page', 0, PARAM_INT));

// Load saved presets for this user.
$rawpresets = json_decode(
    get_user_preferences('local_courseinsights_presets', '[]'),
    true
);
if (!is_array($rawpresets)) {
    $rawpresets = [];
}
$presetsurl = new moodle_url('/local/courseinsights/presets.php');
$presetitems = [];
foreach ($rawpresets as $p) {
    $applyurl = new moodle_url('/local/courseinsights/index.php', $p['filters']);
    $delurl = new moodle_url('/local/courseinsights/presets.php', [
        'action' => 'delete',
        'presetname' => $p['name'],
        'sesskey' => sesskey(),
    ]);
    $presetitems[] = [
        'presetname' => $p['name'],
        'presetapply' => $applyurl->out(false),
        'presetdelete' => $delurl->out(false),
    ];
}

$categories = \local_courseinsights\report_service::get_category_options();
$cohorts = \local_courseinsights\report_service::get_cohort_options();

// Pass only the pre-selected course (if any) so the autocomplete shows the right label on load.
// All course searching is done via AJAX — no full course list needed.
$courseoption = [];
$filtercourseid = (int) ($filters['courseid'] ?? 0);
if ($filtercourseid > 0) {
    $course = $DB->get_record('course', ['id' => $filtercourseid], 'id, fullname', IGNORE_MISSING);
    if ($course) {
        $courseoption = [(int) $course->id => format_string($course->fullname)];
    }
}

$mform = new \local_courseinsights\form\filter_form($url, [
    'cohorts'      => $cohorts,
    'courseoption' => $courseoption,
    'categories'   => $categories,
    'compareopen'  => trim($filters['compare_startdate'] ?? '') !== '' || trim($filters['compare_enddate'] ?? '') !== '',
], 'get');

$formdata = $filters;
if (($formdata['courseid'] ?? 0) == 0) {
    $formdata['courseid'] = '';
}
$mform->set_data($formdata);

$records = \local_courseinsights\report_service::get_course_overview($filters, $page);
$totalcount = \local_courseinsights\report_service::get_last_total_count();
$columns = \local_courseinsights\report_service::get_visible_columns($filters['activitytype']);

// Build table headers with sort state.
$headers = \local_courseinsights\report_service::get_sort_headers(
    array_merge(['course'], $columns),
    $filters['sortby'] ?? 'course',
    $filters['sortdir'] ?? 'asc'
);

// Build table rows.
$rows = [];
foreach ($records as $record) {
    $cells = [
        ['value' => format_string($record->fullname), 'isheader' => true],
    ];

    foreach ($columns as $column) {
        $value = \local_courseinsights\report_service::get_column_value($column, $record);

        if ($column === 'completionrate') {
            $cells[] = ['value' => $value !== null ? $value . '%' : '-', 'isheader' => false];
        } else if ($column === 'avgquizgrade') {
            $cells[] = ['value' => $value !== null ? $value . '%' : '-', 'isheader' => false];
        } else if ($column === 'lastactivity') {
            $cells[] = ['value' => $value ? userdate($value, get_string('strftimedate', 'langconfig')) : '-', 'isheader' => false];
        } else if ($column === 'teachers') {
            $cells[] = ['value' => $value !== null ? (string) $value : '-', 'isheader' => false];
        } else {
            $cells[] = ['value' => (string) $value, 'isheader' => false];
        }
    }

    $rows[] = ['cells' => $cells];
}

// Build chart if there are records.
$charthtml = '';
$charttruncated = false;
if (!empty($records)) {
    $chart = \local_courseinsights\report_service::build_chart(
        array_values($records),
        $filters['activitytype']
    );
    $charthtml = $OUTPUT->render($chart);
    $charttruncated = count($records) > 20;
}

// Build export URLs.
$exporturl = \local_courseinsights\report_service::get_export_url($filters);
$exportxlsxurl = \local_courseinsights\report_service::get_export_url($filters);
$exportxlsxurl->param('format', 'xlsx');

$stats = \local_courseinsights\report_service::get_stats($records, $filters['activitytype']);

$comparesd = trim($filters['compare_startdate'] ?? '');
$compareed = trim($filters['compare_enddate'] ?? '');
$hascompare = ($comparesd !== '' || $compareed !== '');
$deltasubmissions = null;
$deltaattempts = null;

if ($hascompare) {
    $comparefilters = array_merge($filters, [
        'startdate' => $comparesd,
        'enddate'   => $compareed,
        'usecache'  => 0,
    ]);
    $comparerecords = \local_courseinsights\report_service::get_course_overview($comparefilters, $page);
    $cmpstats = \local_courseinsights\report_service::get_stats($comparerecords, $filters['activitytype']);

    $mkdelta = function (int $current, int $compare): array {
        $pct = $compare > 0 ? (int) round(($current - $compare) / $compare * 100) : 0;
        return [
            'hasdelta'     => $compare > 0,
            'comparevalue' => $compare,
            'comparetext'  => get_string('comparepreviousvalue', 'local_courseinsights', $compare),
            'pct'          => abs($pct),
            'isup'         => $pct > 0,
            'isdown'       => $pct < 0,
            'isneutral'    => $pct === 0,
        ];
    };

    $deltasubmissions = $mkdelta($stats['statsubmissions'], $cmpstats['statsubmissions']);
    $deltaattempts    = $mkdelta($stats['statattempts'], $cmpstats['statattempts']);
}

$pagination = \local_courseinsights\report_service::get_pagination_context($page, $totalcount);

$brandaccent = (string) get_config('local_courseinsights', 'brandaccentcolor');
$brandlogourl = (string) get_config('local_courseinsights', 'brandlogourl');
$brandname = (string) get_config('local_courseinsights', 'brandname');
$validaccent = $brandaccent && preg_match('/^#[0-9a-fA-F]{3,6}$/', $brandaccent);

$templatecontext = array_merge([
    'hasexport' => has_capability('local/courseinsights:export', $context),
    'exporturl' => $exporturl->out(false),
    'exportlabel' => get_string('exportcsv', 'local_courseinsights'),
    'exportxlsxurl' => $exportxlsxurl->out(false),
    'exportxlsxlabel' => get_string('exportxlsx', 'local_courseinsights'),
    'haschart' => !empty($records),
    'chart' => $charthtml,
    'charttruncated' => $charttruncated,
    'charttruncatenote' => get_string('charttruncated', 'local_courseinsights'),
    'hasrecords' => !empty($records),
    'emptystate' => get_string('norecords', 'local_courseinsights'),
    'headers' => $headers,
    'rows' => $rows,
    'totalcourses' => $totalcount,
    'label_statcourses' => get_string('stat_courses', 'local_courseinsights'),
    'label_statenrolled' => get_string('stat_students', 'local_courseinsights'),
    'label_statsubmissions' => get_string('stat_submissions', 'local_courseinsights'),
    'label_statattempts' => get_string('stat_attempts', 'local_courseinsights'),
    'hascompare'       => $hascompare,
    'deltasubmissions' => $deltasubmissions,
    'deltaattempts'    => $deltaattempts,
    'compare_label'    => ($comparesd && $compareed) ? $comparesd . ' – ' . $compareed : ($comparesd ?: $compareed),
    'hascoursecards' => !empty($records),
    'courseinsightslabel' => get_string('pluginname', 'local_courseinsights'),
    'completionratelabel' => get_string('completionrate', 'local_courseinsights'),
    'coursecards' => \local_courseinsights\report_service::build_course_cards($records, $filters['activitytype']),
    'haspresets' => !empty($presetitems),
    'presets' => $presetitems,
    'presetsaveurl' => $presetsurl->out(false),
    'presetsesskey' => sesskey(),
    'label_presets' => get_string('presets', 'local_courseinsights'),
    'label_presetsave' => get_string('presetsave', 'local_courseinsights'),
    'label_presetdelete' => get_string('presetdelete', 'local_courseinsights'),
    'label_presetname' => get_string('presetname', 'local_courseinsights'),
    'filter_cohortid' => (int) ($filters['cohortid'] ?? 0),
    'filter_categoryid' => (int) ($filters['categoryid'] ?? 0),
    'filter_courseid' => (int) ($filters['courseid'] ?? 0),
    'filter_startdate' => $filters['startdate'] ?? '',
    'filter_enddate' => $filters['enddate'] ?? '',
    'filter_activitytype' => $filters['activitytype'] ?? 'all',
    'filter_studentstatus' => $filters['studentstatus'] ?? 'active',
    'hasbrandlogo' => $brandlogourl !== '',
    'brandlogourl' => $brandlogourl,
    'brandlabel' => $brandname !== '' ? $brandname : get_string('pluginname', 'local_courseinsights'),
], $stats, $pagination);

echo $OUTPUT->header();

if ($validaccent) {
    echo '<style>.local-courseinsights-filter-wrap,.local-courseinsights-dashboard,.ci-page-layout{'
        . '--ci-primary:' . s($brandaccent) . ';}</style>';
}

echo html_writer::start_div('ci-page-layout');

echo html_writer::start_div('ci-sidebar', ['id' => 'ci-filter-sidebar']);
echo html_writer::start_div('ci-sidebar__header');
echo html_writer::tag('span', get_string('filters', 'local_courseinsights'), ['class' => 'ci-sidebar__title']);
echo html_writer::tag('button', '&#10005;', [
    'class'      => 'ci-sidebar-close',
    'id'         => 'ci-sidebar-close',
    'aria-label' => get_string('filters', 'local_courseinsights'),
]);
echo html_writer::end_div();
echo html_writer::start_div('local-courseinsights-filter-wrap');
$mform->display();
echo html_writer::end_div();
echo html_writer::end_div();

echo html_writer::start_div('ci-page-main');
echo html_writer::tag(
    'button',
    html_writer::tag('span', '&#9776;', ['aria-hidden' => 'true']) .
    html_writer::tag('span', get_string('filters', 'local_courseinsights')),
    [
        'class'         => 'ci-sidebar-toggle',
        'id'            => 'ci-sidebar-toggle',
        'aria-label'    => get_string('filters', 'local_courseinsights'),
        'aria-expanded' => 'true',
        'aria-controls' => 'ci-filter-sidebar',
    ]
);

echo html_writer::start_div('ci-page-tabs');
echo html_writer::tag('a', get_string('tab_dashboard', 'local_courseinsights'), [
    'href'  => (new moodle_url('/local/courseinsights/index.php'))->out(false),
    'class' => 'ci-tab ci-tab--active',
]);
echo html_writer::tag('a', get_string('tab_sitekpis', 'local_courseinsights'), [
    'href'  => (new moodle_url('/local/courseinsights/site.php'))->out(false),
    'class' => 'ci-tab',
]);
echo html_writer::tag('a', get_string('tab_userreport', 'local_courseinsights'), [
    'href'  => (new moodle_url('/local/courseinsights/user_report.php'))->out(false),
    'class' => 'ci-tab',
]);
if (has_capability('local/courseinsights:createintervention', $context)) {
    echo html_writer::tag('a', get_string('tab_interventions', 'local_courseinsights'), [
        'href'  => (new moodle_url('/local/courseinsights/interventions.php'))->out(false),
        'class' => 'ci-tab',
    ]);
}
if (has_capability('local/courseinsights:manage', $context)) {
    echo html_writer::tag('a', get_string('tab_riskrules', 'local_courseinsights'), [
        'href'  => (new moodle_url('/local/courseinsights/risk_rules.php'))->out(false),
        'class' => 'ci-tab',
    ]);
    echo html_writer::tag('a', get_string('tab_taskstatus', 'local_courseinsights'), [
        'href'  => (new moodle_url('/local/courseinsights/admin_tasks.php'))->out(false),
        'class' => 'ci-tab',
    ]);
}
echo html_writer::end_div();

echo html_writer::start_div('', ['data-region' => 'local-courseinsights-dashboard']);
echo $OUTPUT->render_from_template('local_courseinsights/dashboard', $templatecontext);
echo html_writer::end_div();

echo html_writer::end_div();
echo html_writer::end_div();

echo $OUTPUT->footer();
