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
 * Course Insights — individual user progress report.
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

$userid = optional_param('userid', 0, PARAM_INT);

$url = new moodle_url('/local/courseinsights/user_report.php', $userid ? ['userid' => $userid] : []);
$PAGE->set_url($url);
$PAGE->set_title(get_string('tab_userreport', 'local_courseinsights'));
$PAGE->set_heading(get_string('tab_userreport', 'local_courseinsights'));
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

// Build option list for the autocomplete — only the currently selected user, if any.
$useroption = [];
if ($userid > 0) {
    $selecteduser = $DB->get_record('user', ['id' => $userid, 'deleted' => 0, 'confirmed' => 1]);
    if ($selecteduser) {
        $useroption = [(int) $selecteduser->id => trim($selecteduser->firstname . ' ' . $selecteduser->lastname)];
    }
}

$mform = new \local_courseinsights\form\user_report_form(
    new moodle_url('/local/courseinsights/user_report.php'),
    ['useroption' => $useroption],
    'get'
);

// Pre-select the current user in the form (convert 0 → '' per autocomplete convention).
$mform->set_data(['userid' => $userid > 0 ? $userid : '']);

// Load progress data for the selected user.
$selectedfullname = '';
$userprogress = [];
if ($userid > 0 && !empty($selecteduser)) {
    $selectedfullname = trim($selecteduser->firstname . ' ' . $selecteduser->lastname);
    $userprogress = \local_courseinsights\report_service::get_user_progress($userid);
}

$brandaccent  = (string) get_config('local_courseinsights', 'brandaccentcolor');
$brandlogourl = (string) get_config('local_courseinsights', 'brandlogourl');
$brandname    = (string) get_config('local_courseinsights', 'brandname');
$validaccent  = $brandaccent && preg_match('/^#[0-9a-fA-F]{3,6}$/', $brandaccent);

$templatecontext = [
    'userid'           => $userid,
    'selectedfullname' => $selectedfullname,
    'hasuserselected'  => $userid > 0 && !empty($selecteduser),
    'userprogress'     => $userprogress,
    'hasprogress'      => !empty($userprogress),
    'heading'          => get_string('userreport_heading', 'local_courseinsights'),
    'selectprompt'     => get_string('userreport_selectprompt', 'local_courseinsights'),
    'nodata'           => get_string('userreport_nodata', 'local_courseinsights'),
    'col_course'       => get_string('userreport_col_course', 'local_courseinsights'),
    'col_status'       => get_string('userreport_col_status', 'local_courseinsights'),
    'col_lastaccess'   => get_string('userreport_col_lastaccess', 'local_courseinsights'),
    'col_grade'        => get_string('userreport_col_grade', 'local_courseinsights'),
    'hasbrandlogo'     => $brandlogourl !== '',
    'brandlogourl'     => $brandlogourl,
    'brandlabel'       => $brandname !== '' ? $brandname : get_string('pluginname', 'local_courseinsights'),
];

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
    'class' => 'ci-tab',
]);
echo html_writer::tag('a', get_string('tab_userreport', 'local_courseinsights'), [
    'href'  => (new moodle_url('/local/courseinsights/user_report.php'))->out(false),
    'class' => 'ci-tab ci-tab--active',
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

// Render the search form inside a card.
echo html_writer::start_div('local-courseinsights-dashboard');
echo html_writer::start_div('ci-chart-card ci-chart-card--wide');
echo html_writer::tag('h3', get_string('userreport_heading', 'local_courseinsights'), ['class' => 'ci-chart-title']);
$mform->display();
echo html_writer::end_div();

// Render the progress results via template.
echo $OUTPUT->render_from_template('local_courseinsights/user_report', $templatecontext);
echo html_writer::end_div();

echo html_writer::end_div();
echo html_writer::end_div();

echo $OUTPUT->footer();
