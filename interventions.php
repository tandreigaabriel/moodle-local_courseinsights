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
 * Intervention cases list page for local_courseinsights.
 *
 * @package    local_courseinsights
 * @copyright  2026 Andrei Toma
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');

$context = \core\context\system::instance();
$PAGE->set_context($context);
require_login();
require_capability('local/courseinsights:createintervention', $context);

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

$statusfilter = optional_param('status', '', PARAM_ALPHA);
$page         = optional_param('page', 0, PARAM_INT);
$mine         = optional_param('mine', 0, PARAM_INT);
$perpage      = 25;

$url = new moodle_url('/local/courseinsights/interventions.php');
$PAGE->set_url($url);
$PAGE->set_title(get_string('tab_interventions', 'local_courseinsights'));
$PAGE->set_heading(get_string('tab_interventions', 'local_courseinsights'));
$PAGE->set_pagelayout('report');

// Handle create action (from at-risk table).
$action = optional_param('action', '', PARAM_ALPHA);
if ($action === 'create') {
    require_sesskey();
    $userid    = required_param('userid', PARAM_INT);
    $courseid  = required_param('courseid', PARAM_INT);
    $riskscore = optional_param('riskscore', null, PARAM_INT);
    $risklevel = optional_param('risklevel', null, PARAM_ALPHA);

    // Build a default title from student + course name.
    $student   = $DB->get_record('user', ['id' => $userid, 'deleted' => 0], 'firstname,lastname', IGNORE_MISSING);
    $course    = $DB->get_record('course', ['id' => $courseid], 'fullname', IGNORE_MISSING);
    $title     = '';
    if ($student && $course) {
        $title = trim($student->firstname . ' ' . $student->lastname) . ' — ' . format_string($course->fullname);
    }

    $newid = \local_courseinsights\intervention_service::create(
        $userid,
        $courseid,
        $title,
        $riskscore,
        $risklevel,
        $USER->id
    );
    redirect(
        new moodle_url('/local/courseinsights/intervention_detail.php', ['id' => $newid]),
        get_string('intervention_created', 'local_courseinsights'),
        null,
        \core\output\notification::NOTIFY_SUCCESS
    );
}

// Load paginated list.
$data = \local_courseinsights\intervention_service::get_list($statusfilter, $page, $perpage, $mine ? $USER->id : null);
$rows = $data['rows'];
$total = $data['total'];

$myopencount  = \local_courseinsights\intervention_service::get_my_open_count($USER->id);
$canmanage    = has_capability('local/courseinsights:manageinterventions', $context);
$canviewrisk  = has_capability('local/courseinsights:viewrisk', $context);
$brandaccent  = (string) get_config('local_courseinsights', 'brandaccentcolor');
$validaccent  = $brandaccent && preg_match('/^#[0-9a-fA-F]{3,6}$/', $brandaccent);

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
    'href'  => $url->out(false),
    'class' => 'ci-tab ci-tab--active',
]);
if ($canmanage) {
    echo html_writer::tag('a', get_string('tab_riskrules', 'local_courseinsights'), [
        'href'  => (new moodle_url('/local/courseinsights/risk_rules.php'))->out(false),
        'class' => 'ci-tab',
    ]);
    echo html_writer::tag('a', get_string('tab_interventionreports', 'local_courseinsights'), [
        'href'  => (new moodle_url('/local/courseinsights/intervention_reports.php'))->out(false),
        'class' => 'ci-tab',
    ]);
}
if (has_capability('local/courseinsights:manage', $context)) {
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

// Heading + status filter.
echo html_writer::start_div('ci-interventions-header');
echo html_writer::tag(
    'h2',
    $mine ? get_string('mycases_heading', 'local_courseinsights') : get_string('tab_interventions', 'local_courseinsights'),
    ['class' => 'ci-chart-title']
);

$filterurl = new moodle_url('/local/courseinsights/interventions.php');
echo html_writer::start_tag('form', ['method' => 'get', 'action' => $filterurl->out(false), 'class' => 'ci-interventions-filter']);
echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'mine', 'value' => $mine]);
echo html_writer::start_tag('select', ['name' => 'status', 'class' => 'ci-filter-select', 'onchange' => 'this.form.submit()']);
echo html_writer::tag('option', get_string('intervention_filter_all', 'local_courseinsights'), [
    'value' => '',
    ($statusfilter === '') ? 'selected' : '' => '',
]);
foreach (\local_courseinsights\intervention_service::STATUSES as $s) {
    $attrs = ['value' => $s];
    if ($statusfilter === $s) {
        $attrs['selected'] = 'selected';
    }
    $skey = \local_courseinsights\intervention_service::status_string_key($s);
    echo html_writer::tag('option', get_string($skey, 'local_courseinsights'), $attrs);
}
echo html_writer::end_tag('select');
echo html_writer::end_tag('form');
echo html_writer::end_div();

// My Cases / All Cases sub-tabs.
$allurl  = new moodle_url('/local/courseinsights/interventions.php', ['status' => $statusfilter]);
$mineurl = new moodle_url('/local/courseinsights/interventions.php', ['mine' => 1, 'status' => $statusfilter]);
echo html_writer::start_div('ci-cases-subtabs');
echo html_writer::tag('a', get_string('mycases_tab_all', 'local_courseinsights'), [
    'href'  => $allurl->out(false),
    'class' => 'ci-cases-subtab' . ($mine ? '' : ' ci-cases-subtab--active'),
]);
$minetabtext = get_string('mycases_tab_mine', 'local_courseinsights');
if ($myopencount > 0) {
    $minetabtext .= ' ' . html_writer::span((string) $myopencount, 'ci-cases-subtab-count');
}
echo html_writer::tag('a', $minetabtext, [
    'href'  => $mineurl->out(false),
    'class' => 'ci-cases-subtab' . ($mine ? ' ci-cases-subtab--active' : ''),
]);
echo html_writer::end_div();

if (empty($rows)) {
    $emptymsg = $mine
        ? get_string('mycases_empty', 'local_courseinsights')
        : get_string('intervention_nodata', 'local_courseinsights');
    echo $OUTPUT->notification($emptymsg, 'info');
} else {
    echo html_writer::start_tag('table', ['class' => 'ci-top10-table ci-interventions-table']);
    echo html_writer::start_tag('thead');
    echo html_writer::start_tag('tr');
    echo html_writer::tag('th', get_string('intervention_col_student', 'local_courseinsights'));
    echo html_writer::tag('th', get_string('intervention_col_course', 'local_courseinsights'));
    echo html_writer::tag('th', get_string('intervention_col_title', 'local_courseinsights'));
    echo html_writer::tag('th', get_string('intervention_col_status', 'local_courseinsights'), ['class' => 'ci-top10-value']);
    if ($canviewrisk) {
        echo html_writer::tag('th', get_string('atrisk_col_riskscore', 'local_courseinsights'), ['class' => 'ci-top10-value']);
    }
    if ($mine) {
        echo html_writer::tag('th', get_string('intervention_col_urgency', 'local_courseinsights'), ['class' => 'ci-top10-value']);
    }
    echo html_writer::tag('th', get_string('intervention_col_followup', 'local_courseinsights'), ['class' => 'ci-top10-value']);
    echo html_writer::tag('th', get_string('intervention_col_created', 'local_courseinsights'), ['class' => 'ci-top10-value']);
    echo html_writer::tag('th', '', ['class' => 'ci-top10-value']);
    echo html_writer::end_tag('tr');
    echo html_writer::end_tag('thead');
    echo html_writer::start_tag('tbody');

    $now  = time();
    $soon = $now + 7 * DAYSECS;

    foreach ($rows as $row) {
        $detailurl = new moodle_url('/local/courseinsights/intervention_detail.php', ['id' => $row->id]);
        $statuskey = \local_courseinsights\intervention_service::status_string_key($row->status);
        $badgeclass = \local_courseinsights\intervention_service::status_badge_class($row->status);

        echo html_writer::start_tag('tr');
        echo html_writer::tag('td', s($row->studentname));
        echo html_writer::tag('td', s($row->coursename));
        echo html_writer::tag('td', html_writer::tag('a', s($row->title), ['href' => $detailurl->out(false)]));
        echo html_writer::tag(
            'td',
            html_writer::span(get_string($statuskey, 'local_courseinsights'), $badgeclass),
            ['class' => 'ci-top10-value']
        );
        if ($canviewrisk) {
            $riskbadge = '';
            if (!empty($row->risklevel)) {
                $riskbadge = html_writer::span(
                    s($row->risklevel) . ' (' . (int) $row->riskscore . ')',
                    'ci-risk-badge ci-risk-badge--' . s($row->risklevel)
                );
            }
            echo html_writer::tag('td', $riskbadge, ['class' => 'ci-top10-value']);
        }
        if ($mine) {
            $urgency = '';
            if (!empty($row->followupdate)) {
                $fu = (int) $row->followupdate;
                if ($fu < $now) {
                    $urgency = html_writer::span(
                        get_string('mycases_overdue', 'local_courseinsights'),
                        'ci-urgency-badge ci-urgency-badge--overdue'
                    );
                } else if ($fu < $soon) {
                    $urgency = html_writer::span(
                        get_string('mycases_duesoon', 'local_courseinsights'),
                        'ci-urgency-badge ci-urgency-badge--duesoon'
                    );
                }
            }
            echo html_writer::tag('td', $urgency, ['class' => 'ci-top10-value']);
        }
        $followup = !empty($row->followupdate) ? userdate((int) $row->followupdate, get_string('strftimedatefullshort')) : '—';
        echo html_writer::tag('td', $followup, ['class' => 'ci-top10-value']);
        $created = userdate((int) $row->timecreated, get_string('strftimedatefullshort'));
        echo html_writer::tag('td', $created, ['class' => 'ci-top10-value']);
        echo html_writer::tag(
            'td',
            html_writer::tag('a', get_string('intervention_view', 'local_courseinsights'), [
                'href'  => $detailurl->out(false),
                'class' => 'btn btn-sm btn-outline-secondary',
            ]),
            ['class' => 'ci-top10-value']
        );
        echo html_writer::end_tag('tr');
    }

    echo html_writer::end_tag('tbody');
    echo html_writer::end_tag('table');

    // Pagination.
    if ($total > $perpage) {
        $pageurl = new moodle_url('/local/courseinsights/interventions.php', ['status' => $statusfilter, 'mine' => $mine]);
        echo $OUTPUT->paging_bar($total, $page, $perpage, $pageurl);
    }
}

echo html_writer::end_div();
echo html_writer::end_div();
echo html_writer::end_div();
echo html_writer::end_div();

echo $OUTPUT->footer();
