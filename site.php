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
 * Course Insights — site-wide KPI overview page.
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

$url = new moodle_url('/local/courseinsights/site.php');
$PAGE->set_url($url);
$PAGE->set_title(get_string('tab_sitekpis', 'local_courseinsights'));
$PAGE->set_heading(get_string('tab_sitekpis', 'local_courseinsights'));
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

$atriskdays = max(1, (int) get_config('local_courseinsights', 'studentinactivitydays') ?: 14);

$cached = \local_courseinsights\report_service::get_site_overview_snapshot($atriskdays);
$snapshotmissing = $cached === null;

if ($snapshotmissing) {
    $cached = [
        'kpis' => [
            'kpi_totalcourses' => 0,
            'kpi_enrolments' => 0,
            'kpi_coursecompletions' => 0,
            'kpi_activitycompletions' => 0,
            'kpi_newusers' => 0,
            'kpi_activeusers' => 0,
        ],
        'topenrol' => [],
        'topcompl' => [],
        'monthlytrend' => [],
    ];
}

$kpis         = $cached['kpis'];
$topenrol     = $cached['topenrol'];
$topcompl     = $cached['topcompl'];
$monthlytrend = $cached['monthlytrend'];
$atrisk       = $snapshotmissing ? [] : \local_courseinsights\report_service::get_atrisk_students_from_snapshot($atriskdays, 25);

$cancreateintervention = has_capability('local/courseinsights:createintervention', $context);
$canmanage             = has_capability('local/courseinsights:manage', $context);

// Enrich at-risk students with risk scores (bulk fetch — one batch DB read).
if (!empty($atrisk)) {
    $usercourses = [];
    foreach ($atrisk as $s) {
        $usercourses[] = ['userid' => (int) $s['userid'], 'courseid' => (int) $s['courseid']];
    }
    $scoredata = \local_courseinsights\risk_service::get_scores_for_usercourses($usercourses);
    foreach ($atrisk as &$student) {
        $key = $student['userid'] . '_' . $student['courseid'];
        $sd  = $scoredata[$key] ?? ['score' => 0, 'risklevel' => 'low', 'reasons' => []];
        $student['riskscore']      = $sd['score'];
        $student['risklevellabel'] = get_string('risk_level_' . $sd['risklevel'], 'local_courseinsights');
        $student['riskbadgeclass'] = 'ci-risk-badge ci-risk-badge--' . $sd['risklevel'];
        $reasonstrings = [];
        foreach ($sd['reasons'] as $r) {
            if (!empty($r['key'])) {
                $reasonstrings[] = get_string($r['key'], 'local_courseinsights', $r['a'] ?? null);
            }
        }
        $student['riskreasons'] = implode('; ', $reasonstrings);
        if ($cancreateintervention) {
            $student['createintervention_url'] = (new moodle_url(
                '/local/courseinsights/interventions.php',
                [
                    'action'    => 'create',
                    'userid'    => $student['userid'],
                    'courseid'  => $student['courseid'],
                    'riskscore' => $sd['score'],
                    'risklevel' => $sd['risklevel'],
                    'sesskey'   => sesskey(),
                ]
            ))->out(false);
        } else {
            $student['createintervention_url'] = '';
        }
    }
    unset($student);
}

$brandaccent  = (string) get_config('local_courseinsights', 'brandaccentcolor');
$brandlogourl = (string) get_config('local_courseinsights', 'brandlogourl');
$brandname    = (string) get_config('local_courseinsights', 'brandname');
$validaccent  = $brandaccent && preg_match('/^#[0-9a-fA-F]{3,6}$/', $brandaccent);

$templatecontext = array_merge($kpis, [
    'label_kpi_totalcourses'        => get_string('kpi_totalcourses', 'local_courseinsights'),
    'label_kpi_enrolments'          => get_string('kpi_enrolments', 'local_courseinsights'),
    'label_kpi_coursecompletions'   => get_string('kpi_coursecompletions', 'local_courseinsights'),
    'label_kpi_activitycompletions' => get_string('kpi_activitycompletions', 'local_courseinsights'),
    'label_kpi_newusers'            => get_string('kpi_newusers', 'local_courseinsights'),
    'label_kpi_activeusers'         => get_string('kpi_activeusers', 'local_courseinsights'),
    'top10_enrolment_label'         => get_string('top10_enrolment', 'local_courseinsights'),
    'top10_completion_label'        => get_string('top10_completion', 'local_courseinsights'),
    'top10_col_course'              => get_string('course', 'local_courseinsights'),
    'top10_col_value'               => get_string('enrolledstudents', 'local_courseinsights'),
    'top10_col_pct'                 => get_string('completionrate', 'local_courseinsights'),
    'top_enrolments'                => $topenrol,
    'top_completions'               => $topcompl,
    // Monthly active users trend.
    'monthly_trend'                 => $monthlytrend,
    'has_monthly_trend'             => !empty($monthlytrend),
    'monthly_trend_label'           => get_string('monthly_trend_label', 'local_courseinsights'),
    'monthly_col_month'             => get_string('monthly_col_month', 'local_courseinsights'),
    'monthly_col_activeusers'       => get_string('monthly_col_activeusers', 'local_courseinsights'),
    'monthly_col_events'            => get_string('monthly_col_events', 'local_courseinsights'),
    // At-risk students.
    'atrisk_students'               => $atrisk,
    'has_atrisk'                    => !empty($atrisk),
    'atrisk_heading'                => get_string('atrisk_heading', 'local_courseinsights'),
    'atrisk_days'                   => $atriskdays,
    'atrisk_col_student'            => get_string('atrisk_col_student', 'local_courseinsights'),
    'atrisk_col_course'             => get_string('atrisk_col_course', 'local_courseinsights'),
    'atrisk_col_lastaccess'         => get_string('atrisk_col_lastaccess', 'local_courseinsights'),
    'atrisk_col_days'               => get_string('atrisk_col_days', 'local_courseinsights'),
    'atrisk_col_riskscore'          => get_string('atrisk_col_riskscore', 'local_courseinsights'),
    'atrisk_col_action'             => get_string('atrisk_col_action', 'local_courseinsights'),
    'atrisk_intervention_label'     => get_string('intervention_create', 'local_courseinsights'),
    'has_createintervention'        => $cancreateintervention,
    'atrisk_nodata'                 => get_string('atrisk_nodata', 'local_courseinsights'),
    // Branding.
    'hasbrandlogo'                  => $brandlogourl !== '',
    'brandlogourl'                  => $brandlogourl,
    'brandlabel'                    => $brandname !== '' ? $brandname : get_string('pluginname', 'local_courseinsights'),
]);

echo $OUTPUT->header();

if ($validaccent) {
    echo '<style>.local-courseinsights-dashboard,.ci-page-layout{'
        . '--ci-primary:' . s($brandaccent) . ';}</style>';
}

echo html_writer::start_div('ci-page-layout ci-page-layout--nosidebar');
echo html_writer::start_div('ci-page-main');

echo html_writer::start_div('ci-page-tabs');
echo html_writer::tag('a', get_string('tab_dashboard', 'local_courseinsights'), [
    'href'  => (new moodle_url('/local/courseinsights/index.php'))->out(false),
    'class' => 'ci-tab',
]);
echo html_writer::tag('a', get_string('tab_sitekpis', 'local_courseinsights'), [
    'href'  => (new moodle_url('/local/courseinsights/site.php'))->out(false),
    'class' => 'ci-tab ci-tab--active',
]);
echo html_writer::tag('a', get_string('tab_userreport', 'local_courseinsights'), [
    'href'  => (new moodle_url('/local/courseinsights/user_report.php'))->out(false),
    'class' => 'ci-tab',
]);
if ($cancreateintervention) {
    echo html_writer::tag('a', get_string('tab_interventions', 'local_courseinsights'), [
        'href'  => (new moodle_url('/local/courseinsights/interventions.php'))->out(false),
        'class' => 'ci-tab',
    ]);
}
if ($canmanage) {
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

if ($snapshotmissing) {
    echo $OUTPUT->notification(get_string('sitecachepending', 'local_courseinsights'), 'info');
}

echo $OUTPUT->render_from_template('local_courseinsights/site_kpis', $templatecontext);

echo html_writer::end_div();
echo html_writer::end_div();

echo $OUTPUT->footer();
