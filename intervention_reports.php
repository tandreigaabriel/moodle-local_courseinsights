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
 * Intervention outcome reports page for local_courseinsights.
 *
 * @package    local_courseinsights
 * @copyright  2026 Andrei Toma
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');

$context = \core\context\system::instance();
$PAGE->set_context($context);
require_login();
require_capability('local/courseinsights:manageinterventions', $context);

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

$days = optional_param('days', 90, PARAM_INT);
if (!in_array($days, [30, 90, 365, 0], true)) {
    $days = 90;
}
$since = ($days > 0) ? (time() - ($days * DAYSECS)) : 0;

$url = new moodle_url('/local/courseinsights/intervention_reports.php');
$PAGE->set_url($url);
$PAGE->set_title(get_string('tab_interventionreports', 'local_courseinsights'));
$PAGE->set_heading(get_string('tab_interventionreports', 'local_courseinsights'));
$PAGE->set_pagelayout('report');

$report = \local_courseinsights\intervention_service::get_report_data($since);

$canmanage   = has_capability('local/courseinsights:manage', $context);
$brandaccent = (string) get_config('local_courseinsights', 'brandaccentcolor');
$validaccent = $brandaccent && preg_match('/^#[0-9a-fA-F]{3,6}$/', $brandaccent);

echo $OUTPUT->header();

if ($validaccent) {
    echo '<style>.local-courseinsights-dashboard,.ci-page-layout{'
        . '--ci-primary:' . s($brandaccent) . ';}</style>';
}

echo html_writer::start_div('ci-page-layout ci-page-layout--nosidebar');
echo html_writer::start_div('ci-page-main');

// Tab bar.
echo html_writer::start_div('ci-page-tabs');
echo html_writer::tag('a', get_string('tab_dashboard', 'local_courseinsights'), [
    'href'  => (new moodle_url('/local/courseinsights/index.php'))->out(false),
    'class' => 'ci-tab',
]);
echo html_writer::tag('a', get_string('tab_sitekpis', 'local_courseinsights'), [
    'href'  => (new moodle_url('/local/courseinsights/site.php'))->out(false),
    'class' => 'ci-tab',
]);
echo html_writer::tag('a', get_string('tab_userreport', 'local_courseinsights'), [
    'href'  => (new moodle_url('/local/courseinsights/user_report.php'))->out(false),
    'class' => 'ci-tab',
]);
echo html_writer::tag('a', get_string('tab_interventions', 'local_courseinsights'), [
    'href'  => (new moodle_url('/local/courseinsights/interventions.php'))->out(false),
    'class' => 'ci-tab',
]);
if ($canmanage) {
    echo html_writer::tag('a', get_string('tab_riskrules', 'local_courseinsights'), [
        'href'  => (new moodle_url('/local/courseinsights/risk_rules.php'))->out(false),
        'class' => 'ci-tab',
    ]);
}
echo html_writer::tag('a', get_string('tab_interventionreports', 'local_courseinsights'), [
    'href'  => $url->out(false),
    'class' => 'ci-tab ci-tab--active',
]);
if ($canmanage) {
    echo html_writer::tag('a', get_string('tab_taskstatus', 'local_courseinsights'), [
        'href'  => (new moodle_url('/local/courseinsights/admin_tasks.php'))->out(false),
        'class' => 'ci-tab',
    ]);
    echo html_writer::tag('a', get_string('tab_msgtemplates', 'local_courseinsights'), [
        'href'  => (new moodle_url('/local/courseinsights/message_templates.php'))->out(false),
        'class' => 'ci-tab',
    ]);
    echo html_writer::tag('a', get_string('tab_setupguide', 'local_courseinsights'), [
        'href'  => (new moodle_url('/local/courseinsights/help.php'))->out(false),
        'class' => 'ci-tab',
    ]);
}
echo html_writer::end_div();

echo html_writer::start_div('local-courseinsights-dashboard');
echo html_writer::start_div('ci-chart-card ci-chart-card--wide');

// Heading + period filter.
echo html_writer::start_div('ci-interventions-header');
echo html_writer::tag('h2', get_string('reports_heading', 'local_courseinsights'), ['class' => 'ci-chart-title']);

$periods = [
    30  => get_string('reports_period_30', 'local_courseinsights'),
    90  => get_string('reports_period_90', 'local_courseinsights'),
    365 => get_string('reports_period_365', 'local_courseinsights'),
    0   => get_string('reports_period_all', 'local_courseinsights'),
];
echo html_writer::start_tag('form', [
    'method'  => 'get',
    'action'  => $url->out(false),
    'class'   => 'ci-interventions-filter',
]);
echo html_writer::start_tag('select', [
    'name'     => 'days',
    'class'    => 'ci-filter-select',
    'onchange' => 'this.form.submit()',
]);
foreach ($periods as $val => $label) {
    $attrs = ['value' => (string) $val];
    if ($days === $val) {
        $attrs['selected'] = 'selected';
    }
    echo html_writer::tag('option', $label, $attrs);
}
echo html_writer::end_tag('select');
echo html_writer::end_tag('form');
echo html_writer::end_div();

if ($report['total'] === 0) {
    echo $OUTPUT->notification(get_string('reports_nodata', 'local_courseinsights'), 'info');
} else {
    // Summary stat cards.
    $avgdisplay = ($report['avgdays'] !== null) ? (string) $report['avgdays'] : '—';
    echo html_writer::start_div('ci-reports-stats');
    echo html_writer::tag(
        'div',
        html_writer::tag('div', (string) $report['total'], ['class' => 'ci-reports-stat-value'])
        . html_writer::tag('div', get_string('reports_stat_total', 'local_courseinsights'), ['class' => 'ci-reports-stat-label']),
        ['class' => 'ci-reports-stat-card']
    );
    echo html_writer::tag(
        'div',
        html_writer::tag('div', (string) $report['opencnt'], ['class' => 'ci-reports-stat-value'])
        . html_writer::tag('div', get_string('reports_stat_open', 'local_courseinsights'), ['class' => 'ci-reports-stat-label']),
        ['class' => 'ci-reports-stat-card']
    );
    $resolvedlabel = get_string('reports_stat_resolved', 'local_courseinsights');
    echo html_writer::tag(
        'div',
        html_writer::tag('div', (string) $report['resolvedcnt'], ['class' => 'ci-reports-stat-value'])
        . html_writer::tag('div', $resolvedlabel, ['class' => 'ci-reports-stat-label']),
        ['class' => 'ci-reports-stat-card']
    );
    echo html_writer::tag(
        'div',
        html_writer::tag('div', $report['resolutionrate'] . '%', ['class' => 'ci-reports-stat-value'])
        . html_writer::tag('div', get_string('reports_stat_rate', 'local_courseinsights'), ['class' => 'ci-reports-stat-label']),
        ['class' => 'ci-reports-stat-card']
    );
    echo html_writer::tag(
        'div',
        html_writer::tag('div', $avgdisplay, ['class' => 'ci-reports-stat-value'])
        . html_writer::tag('div', get_string('reports_stat_avgdays', 'local_courseinsights'), ['class' => 'ci-reports-stat-label']),
        ['class' => 'ci-reports-stat-card']
    );
    echo html_writer::end_div();

    // Cases by status table.
    echo html_writer::tag(
        'h3',
        get_string('reports_bystatus_heading', 'local_courseinsights'),
        ['class' => 'ci-section-heading']
    );
    echo html_writer::start_tag('table', ['class' => 'ci-top10-table ci-reports-table']);
    echo html_writer::start_tag('thead');
    echo html_writer::start_tag('tr');
    echo html_writer::tag('th', get_string('intervention_col_status', 'local_courseinsights'));
    echo html_writer::tag('th', get_string('reports_col_count', 'local_courseinsights'), ['class' => 'ci-top10-value']);
    echo html_writer::end_tag('tr');
    echo html_writer::end_tag('thead');
    echo html_writer::start_tag('tbody');
    foreach (\local_courseinsights\intervention_service::STATUSES as $s) {
        $cnt      = $report['bystatus'][$s] ?? 0;
        $key      = \local_courseinsights\intervention_service::status_string_key($s);
        $badge    = \local_courseinsights\intervention_service::status_badge_class($s);
        echo html_writer::start_tag('tr');
        echo html_writer::tag('td', html_writer::span(get_string($key, 'local_courseinsights'), $badge));
        echo html_writer::tag('td', (string) $cnt, ['class' => 'ci-top10-value']);
        echo html_writer::end_tag('tr');
    }
    echo html_writer::end_tag('tbody');
    echo html_writer::end_tag('table');

    // Staff caseload table.
    echo html_writer::tag(
        'h3',
        get_string('reports_staff_heading', 'local_courseinsights'),
        ['class' => 'ci-section-heading']
    );
    if (empty($report['staff'])) {
        echo $OUTPUT->notification(get_string('reports_nodata', 'local_courseinsights'), 'info');
    } else {
        echo html_writer::start_tag('table', ['class' => 'ci-top10-table ci-reports-table']);
        echo html_writer::start_tag('thead');
        echo html_writer::start_tag('tr');
        echo html_writer::tag('th', get_string('reports_col_staffname', 'local_courseinsights'));
        echo html_writer::tag('th', get_string('reports_col_total', 'local_courseinsights'), ['class' => 'ci-top10-value']);
        echo html_writer::tag('th', get_string('reports_col_active', 'local_courseinsights'), ['class' => 'ci-top10-value']);
        echo html_writer::tag('th', get_string('reports_col_resolved', 'local_courseinsights'), ['class' => 'ci-top10-value']);
        echo html_writer::tag('th', get_string('reports_col_avgdays', 'local_courseinsights'), ['class' => 'ci-top10-value']);
        echo html_writer::end_tag('tr');
        echo html_writer::end_tag('thead');
        echo html_writer::start_tag('tbody');
        foreach ($report['staff'] as $staffrow) {
            $avgd = ($staffrow->avg_days !== null) ? (string) $staffrow->avg_days : '—';
            echo html_writer::start_tag('tr');
            echo html_writer::tag('td', s($staffrow->staffname));
            echo html_writer::tag('td', (string) $staffrow->total_cases, ['class' => 'ci-top10-value']);
            echo html_writer::tag('td', (string) $staffrow->active_cases, ['class' => 'ci-top10-value']);
            echo html_writer::tag('td', (string) $staffrow->resolved_cases, ['class' => 'ci-top10-value']);
            echo html_writer::tag('td', $avgd, ['class' => 'ci-top10-value']);
            echo html_writer::end_tag('tr');
        }
        echo html_writer::end_tag('tbody');
        echo html_writer::end_tag('table');
    }
}

echo html_writer::end_div();
echo html_writer::end_div();
echo html_writer::end_div();
echo html_writer::end_div();

echo $OUTPUT->footer();
