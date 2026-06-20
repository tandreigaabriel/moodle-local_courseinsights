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
$PAGE->requires->js_call_amd('local_courseinsights/filter', 'init', [$context->id]);

$filters = \local_courseinsights\report_service::get_filters_from_request();
$page = max(0, optional_param('page', 0, PARAM_INT));

$courses = \local_courseinsights\report_service::get_course_options();
$categories = \local_courseinsights\report_service::get_category_options();

$mform = new \local_courseinsights\form\filter_form($url, [
    'courses' => $courses,
    'categories' => $categories,
], 'get');

$mform->set_data($filters);

$totalcount = \local_courseinsights\report_service::get_course_count($filters);
$records = \local_courseinsights\report_service::get_course_overview($filters, $page);
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

// Build export URL.
$exporturl = \local_courseinsights\report_service::get_export_url($filters);

$stats = \local_courseinsights\report_service::get_stats($records, $filters['activitytype']);
$pagination = \local_courseinsights\report_service::get_pagination_context($page, $totalcount);

$templatecontext = array_merge([
    'hasexport' => has_capability('local/courseinsights:export', $context),
    'exporturl' => $exporturl->out(false),
    'exportlabel' => get_string('exportcsv', 'local_courseinsights'),
    'haschart' => !empty($records),
    'chart' => $charthtml,
    'charttruncated' => $charttruncated,
    'charttruncatenote' => get_string('charttruncated', 'local_courseinsights'),
    'hasrecords' => !empty($records),
    'emptystate' => get_string('norecords', 'local_courseinsights'),
    'headers' => $headers,
    'rows' => $rows,
    'totalcourses' => $totalcount,
    'label_statcourses'    => get_string('stat_courses', 'local_courseinsights'),
    'label_statenrolled'   => get_string('stat_students', 'local_courseinsights'),
    'label_statsubmissions' => get_string('stat_submissions', 'local_courseinsights'),
    'label_statattempts'   => get_string('stat_attempts', 'local_courseinsights'),
    'hascoursecards'       => !empty($records),
    'courseinsightslabel'  => get_string('pluginname', 'local_courseinsights'),
    'completionratelabel'  => get_string('completionrate', 'local_courseinsights'),
    'coursecards'          => \local_courseinsights\report_service::build_course_cards($records, $filters['activitytype']),
], $stats, $pagination);

echo $OUTPUT->header();

echo html_writer::start_div('local-courseinsights-filter-wrap');
$mform->display();
echo html_writer::end_div();

echo html_writer::start_div('', ['data-region' => 'local-courseinsights-dashboard']);
echo $OUTPUT->render_from_template('local_courseinsights/dashboard', $templatecontext);
echo html_writer::end_div();

echo $OUTPUT->footer();
