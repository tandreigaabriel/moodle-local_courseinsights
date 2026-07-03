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
 * Library functions for local_courseinsights.
 *
 * @package    local_courseinsights
 * @copyright  2026 Andrei Toma
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Adds a Course Insights link to the global navigation for users who have
 * the view capability, making the report discoverable for editing teachers.
 *
 * @param global_navigation $nav
 * @return void
 */
function local_courseinsights_extend_navigation(global_navigation $nav): void {
    if (!is_siteadmin() && has_capability('local/courseinsights:view', \core\context\system::instance())) {
        $node = navigation_node::create(
            get_string('pluginname', 'local_courseinsights'),
            new moodle_url('/local/courseinsights/index.php'),
            navigation_node::TYPE_CUSTOM,
            null,
            'courseinsights',
            new pix_icon('i/report', '')
        );
        $node->showinflatnavigation = true;
        $nav->add_node($node);
    }
}

/**
 * Renders the Course Insights dashboard content as an HTML fragment for AJAX requests.
 *
 * Called automatically by Moodle's fragment.php when JS requests
 * the 'dashboard' fragment from this component.
 *
 * @param array $args Filter parameters sent from the browser.
 * @return string Rendered dashboard HTML.
 */
function local_courseinsights_output_fragment_dashboard(array $args): string {
    global $OUTPUT;

    require_capability('local/courseinsights:view', \core\context\system::instance());

    $filters = [
        'cohortid'      => (int) ($args['cohortid'] ?? 0),
        'courseid'      => (int) ($args['courseid'] ?? 0),
        'categoryid'    => (int) ($args['categoryid'] ?? 0),
        'startdate'     => clean_param($args['startdate'] ?? '', PARAM_TEXT),
        'enddate'       => clean_param($args['enddate'] ?? '', PARAM_TEXT),
        'activitytype'  => clean_param($args['activitytype'] ?? 'all', PARAM_ALPHA),
        'studentstatus' => clean_param($args['studentstatus'] ?? 'active', PARAM_ALPHA),
        'usecache'      => (bool) ($args['usecache'] ?? false),
        'sortby'        => clean_param($args['sortby'] ?? 'course', PARAM_ALPHA),
        'sortdir'       => clean_param($args['sortdir'] ?? 'asc', PARAM_ALPHA),
    ];
    $page = max(0, (int) ($args['page'] ?? 0));

    $totalcount = \local_courseinsights\report_service::get_course_count($filters);
    $records = \local_courseinsights\report_service::get_course_overview($filters, $page);
    $columns = \local_courseinsights\report_service::get_visible_columns($filters['activitytype']);

    $headers = \local_courseinsights\report_service::get_sort_headers(
        array_merge(['course'], $columns),
        $filters['sortby'],
        $filters['sortdir']
    );

    $rows = [];
    foreach ($records as $record) {
        $cells = [['value' => format_string($record->fullname), 'isheader' => true]];
        foreach ($columns as $column) {
            $value = \local_courseinsights\report_service::get_column_value($column, $record);
            if ($column === 'completionrate') {
                $display = $value !== null ? $value . '%' : '-';
            } else if ($column === 'avgquizgrade') {
                $display = $value !== null ? $value . '%' : '-';
            } else if ($column === 'lastactivity') {
                $display = $value ? userdate($value, get_string('strftimedate', 'langconfig')) : '-';
            } else if ($column === 'teachers') {
                $display = $value !== null ? (string) $value : '-';
            } else {
                $display = (string) $value;
            }
            $cells[] = ['value' => $display, 'isheader' => false];
        }
        $rows[] = ['cells' => $cells];
    }

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

    $exporturl = \local_courseinsights\report_service::get_export_url($filters);
    $context = \core\context\system::instance();
    $stats = \local_courseinsights\report_service::get_stats($records, $filters['activitytype']);
    $pagination = \local_courseinsights\report_service::get_pagination_context($page, $totalcount);

    $templatecontext = array_merge([
        'hasexport'         => has_capability('local/courseinsights:export', $context),
        'exporturl'         => $exporturl->out(false),
        'exportlabel'       => get_string('exportcsv', 'local_courseinsights'),
        'haschart'          => !empty($records),
        'chart'             => $charthtml,
        'charttruncated'    => $charttruncated,
        'charttruncatenote' => get_string('charttruncated', 'local_courseinsights'),
        'hasrecords'        => !empty($records),
        'emptystate'        => get_string('norecords', 'local_courseinsights'),
        'headers'           => $headers,
        'rows'              => $rows,
        'totalcourses'          => $totalcount,
        'label_statcourses'    => get_string('stat_courses', 'local_courseinsights'),
        'label_statenrolled'   => get_string('stat_students', 'local_courseinsights'),
        'label_statsubmissions' => get_string('stat_submissions', 'local_courseinsights'),
        'label_statattempts'   => get_string('stat_attempts', 'local_courseinsights'),
        'hascoursecards'       => !empty($records),
        'courseinsightslabel'  => get_string('pluginname', 'local_courseinsights'),
        'completionratelabel'  => get_string('completionrate', 'local_courseinsights'),
        'coursecards'          => \local_courseinsights\report_service::build_course_cards($records, $filters['activitytype']),
    ], $stats, $pagination);

    return $OUTPUT->render_from_template('local_courseinsights/dashboard', $templatecontext);
}
