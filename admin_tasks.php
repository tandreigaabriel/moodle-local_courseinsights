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
 * Admin task status page for local_courseinsights.
 *
 * @package    local_courseinsights
 * @copyright  2026 Andrei Toma
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');

$context = \core\context\system::instance();
$PAGE->set_context($context);
require_login();
require_capability('local/courseinsights:manage', $context);

$url = new moodle_url('/local/courseinsights/admin_tasks.php');
$PAGE->set_url($url);
$PAGE->set_title(get_string('tab_taskstatus', 'local_courseinsights'));
$PAGE->set_heading(get_string('tab_taskstatus', 'local_courseinsights'));
$PAGE->set_pagelayout('report');

$brandaccent = (string) get_config('local_courseinsights', 'brandaccentcolor');
$validaccent = $brandaccent && preg_match('/^#[0-9a-fA-F]{3,6}$/', $brandaccent);

$now = time();

// Scheduled tasks.
$scheduled = $DB->get_records_sql(
    "SELECT classname, lastruntime, nextruntime, faildelay, disabled
       FROM {task_scheduled}
      WHERE component = 'local_courseinsights'
      ORDER BY classname ASC"
);

// Ad-hoc queue grouped by class.
$adhoc = $DB->get_records_sql(
    "SELECT classname,
            COUNT(*) AS total,
            SUM(CASE WHEN faildelay > 0 THEN 1 ELSE 0 END) AS failed,
            MIN(nextruntime) AS oldest
       FROM {task_adhoc}
      WHERE component = 'local_courseinsights'
      GROUP BY classname
      ORDER BY classname ASC"
);

// Snapshot freshness.
$snapcount = (int) $DB->count_records('local_courseinsights_detail');
$snapnewest = 0;
$snapoldest = 0;
$snapstale  = 0;
if ($snapcount > 0) {
    $snapnewest = (int) $DB->get_field_sql(
        'SELECT MAX(timemodified) FROM {local_courseinsights_detail}'
    );
    $snapoldest = (int) $DB->get_field_sql(
        'SELECT MIN(timemodified) FROM {local_courseinsights_detail}'
    );
    $stale24h = $now - DAYSECS;
    $snapstale = (int) $DB->count_records_sql(
        'SELECT COUNT(*) FROM {local_courseinsights_detail} WHERE timemodified < :cutoff',
        ['cutoff' => $stale24h]
    );
}

// Rollup coverage.
$rollupcount = (int) $DB->count_records('local_courseinsights_log_rollup');
$rolluplast  = 0;
if ($rollupcount > 0) {
    $rolluplast = (int) $DB->get_field_sql(
        'SELECT MAX(logdate) FROM {local_courseinsights_log_rollup}'
    );
}

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
echo html_writer::tag('a', get_string('tab_riskrules', 'local_courseinsights'), [
    'href'  => (new moodle_url('/local/courseinsights/risk_rules.php'))->out(false),
    'class' => 'ci-tab',
]);
echo html_writer::tag('a', get_string('tab_interventionreports', 'local_courseinsights'), [
    'href'  => (new moodle_url('/local/courseinsights/intervention_reports.php'))->out(false),
    'class' => 'ci-tab',
]);
echo html_writer::tag('a', get_string('tab_taskstatus', 'local_courseinsights'), [
    'href'  => $url->out(false),
    'class' => 'ci-tab ci-tab--active',
]);
echo html_writer::tag('a', get_string('tab_msgtemplates', 'local_courseinsights'), [
    'href'  => (new moodle_url('/local/courseinsights/message_templates.php'))->out(false),
    'class' => 'ci-tab',
]);
echo html_writer::tag('a', get_string('tab_setupguide', 'local_courseinsights'), [
    'href'  => (new moodle_url('/local/courseinsights/help.php'))->out(false),
    'class' => 'ci-tab',
]);
echo html_writer::end_div();

echo html_writer::start_div('local-courseinsights-dashboard');

// Scheduled tasks.
echo html_writer::start_div('ci-chart-card ci-chart-card--wide');
echo html_writer::tag('h2', get_string('taskstatus_scheduled_heading', 'local_courseinsights'), ['class' => 'ci-chart-title']);

if (empty($scheduled)) {
    echo $OUTPUT->notification(get_string('taskstatus_none', 'local_courseinsights'), 'info');
} else {
    echo html_writer::start_tag('table', ['class' => 'ci-top10-table']);
    echo html_writer::start_tag('thead');
    echo html_writer::start_tag('tr');
    echo html_writer::tag('th', get_string('taskstatus_taskname', 'local_courseinsights'));
    echo html_writer::tag('th', get_string('taskstatus_lastrun', 'local_courseinsights'), ['class' => 'ci-top10-value']);
    echo html_writer::tag('th', get_string('taskstatus_nextrun', 'local_courseinsights'), ['class' => 'ci-top10-value']);
    echo html_writer::tag('th', get_string('taskstatus_status', 'local_courseinsights'), ['class' => 'ci-top10-value']);
    echo html_writer::end_tag('tr');
    echo html_writer::end_tag('thead');
    echo html_writer::start_tag('tbody');

    foreach ($scheduled as $task) {
        $shortname = substr(strrchr($task->classname, '\\'), 1) ?: $task->classname;
        $lastrun   = $task->lastruntime > 0
            ? userdate((int) $task->lastruntime, get_string('strftimedatefullshort', 'langconfig'))
            : get_string('never');
        $nextrun   = $task->nextruntime > 0
            ? userdate((int) $task->nextruntime, get_string('strftimedatefullshort', 'langconfig'))
            : '—';

        if ($task->disabled) {
            $badge = html_writer::span(
                get_string('taskstatus_disabled', 'local_courseinsights'),
                'ci-risk-badge ci-risk-badge--low'
            );
        } else if ($task->faildelay > 0) {
            $badge = html_writer::span(
                get_string('taskstatus_failed', 'local_courseinsights') . ' (' . format_time((int) $task->faildelay) . ')',
                'ci-risk-badge ci-risk-badge--critical'
            );
        } else {
            $badge = html_writer::span(get_string('taskstatus_ok', 'local_courseinsights'), 'ci-risk-badge ci-risk-badge--low');
        }

        echo html_writer::start_tag('tr');
        echo html_writer::tag('td', s($shortname));
        echo html_writer::tag('td', $lastrun, ['class' => 'ci-top10-value']);
        echo html_writer::tag('td', $nextrun, ['class' => 'ci-top10-value']);
        echo html_writer::tag('td', $badge, ['class' => 'ci-top10-value']);
        echo html_writer::end_tag('tr');
    }

    echo html_writer::end_tag('tbody');
    echo html_writer::end_tag('table');
}
echo html_writer::end_div();

// Ad-hoc queue.
echo html_writer::start_div('ci-chart-card ci-chart-card--wide');
echo html_writer::tag('h2', get_string('taskstatus_adhoc_heading', 'local_courseinsights'), ['class' => 'ci-chart-title']);

if (empty($adhoc)) {
    echo html_writer::tag('p', get_string('taskstatus_adhoc_empty', 'local_courseinsights'), ['class' => 'ci-nodata']);
} else {
    echo html_writer::start_tag('table', ['class' => 'ci-top10-table']);
    echo html_writer::start_tag('thead');
    echo html_writer::start_tag('tr');
    echo html_writer::tag('th', get_string('taskstatus_taskname', 'local_courseinsights'));
    echo html_writer::tag('th', get_string('taskstatus_queued', 'local_courseinsights'), ['class' => 'ci-top10-value']);
    echo html_writer::tag('th', get_string('taskstatus_failed', 'local_courseinsights'), ['class' => 'ci-top10-value']);
    echo html_writer::tag('th', get_string('taskstatus_oldest', 'local_courseinsights'), ['class' => 'ci-top10-value']);
    echo html_writer::end_tag('tr');
    echo html_writer::end_tag('thead');
    echo html_writer::start_tag('tbody');

    foreach ($adhoc as $row) {
        $shortname = substr(strrchr($row->classname, '\\'), 1) ?: $row->classname;
        $queued    = (int) $row->total - (int) $row->failed;
        $failed    = (int) $row->failed;
        $oldestage = $row->oldest > 0
            ? format_time($now - (int) $row->oldest)
            : '—';

        $failedcell = $failed > 0
            ? html_writer::span((string) $failed, 'ci-risk-badge ci-risk-badge--critical')
            : '0';

        echo html_writer::start_tag('tr');
        echo html_writer::tag('td', s($shortname));
        echo html_writer::tag('td', (string) $queued, ['class' => 'ci-top10-value']);
        echo html_writer::tag('td', $failedcell, ['class' => 'ci-top10-value']);
        echo html_writer::tag('td', $oldestage, ['class' => 'ci-top10-value']);
        echo html_writer::end_tag('tr');
    }

    echo html_writer::end_tag('tbody');
    echo html_writer::end_tag('table');
}
echo html_writer::end_div();

// Snapshot and rollup status.
echo html_writer::start_div('ci-chart-card ci-chart-card--wide');
echo html_writer::tag('h2', get_string('taskstatus_snapshot_heading', 'local_courseinsights'), ['class' => 'ci-chart-title']);

echo html_writer::start_div('ci-reports-stats');

echo html_writer::tag(
    'div',
    html_writer::tag('div', (string) $snapcount, ['class' => 'ci-reports-stat-value'])
    . html_writer::tag('div', get_string('taskstatus_snap_total', 'local_courseinsights'), ['class' => 'ci-reports-stat-label']),
    ['class' => 'ci-reports-stat-card']
);

$neweststr = $snapnewest > 0
    ? userdate($snapnewest, get_string('strftimedatefullshort', 'langconfig'))
    : '—';
echo html_writer::tag(
    'div',
    html_writer::tag('div', $neweststr, ['class' => 'ci-reports-stat-value'])
    . html_writer::tag('div', get_string('taskstatus_snap_newest', 'local_courseinsights'), ['class' => 'ci-reports-stat-label']),
    ['class' => 'ci-reports-stat-card']
);

$stalecls = $snapstale > 0 ? 'ci-reports-stat-value' : 'ci-reports-stat-value';
echo html_writer::tag(
    'div',
    html_writer::tag('div', (string) $snapstale, ['class' => $stalecls])
    . html_writer::tag('div', get_string('taskstatus_snap_stale', 'local_courseinsights'), ['class' => 'ci-reports-stat-label']),
    ['class' => 'ci-reports-stat-card' . ($snapstale > 0 ? ' ci-reports-stat-card--warn' : '')]
);

$rollupstr = $rolluplast > 0
    ? date('Y-m-d', $rolluplast * 86400)
    : '—';
echo html_writer::tag(
    'div',
    html_writer::tag('div', $rollupstr, ['class' => 'ci-reports-stat-value'])
    . html_writer::tag('div', get_string('taskstatus_rollup_last', 'local_courseinsights'), ['class' => 'ci-reports-stat-label']),
    ['class' => 'ci-reports-stat-card']
);

echo html_writer::tag(
    'div',
    html_writer::tag('div', number_format($rollupcount), ['class' => 'ci-reports-stat-value'])
    . html_writer::tag('div', get_string('taskstatus_rollup_rows', 'local_courseinsights'), ['class' => 'ci-reports-stat-label']),
    ['class' => 'ci-reports-stat-card']
);

echo html_writer::end_div();
echo html_writer::end_div();

echo html_writer::end_div();
echo html_writer::end_div();
echo html_writer::end_div();

echo $OUTPUT->footer();
