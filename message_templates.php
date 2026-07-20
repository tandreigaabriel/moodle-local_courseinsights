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
 * Message templates management page for Course Insights.
 *
 * @package    local_courseinsights
 * @copyright  2026 Andrei Toma
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');

$context = context_system::instance();
require_login();
require_capability('local/courseinsights:manage', $context);

$PAGE->set_context($context);
$PAGE->set_url(new moodle_url('/local/courseinsights/message_templates.php'));
$PAGE->set_title(get_string('tab_msgtemplates', 'local_courseinsights'));
$PAGE->set_heading(get_string('tab_msgtemplates', 'local_courseinsights'));
$PAGE->set_pagelayout('admin');

if (data_submitted() && confirm_sesskey()) {
    set_config('tmpl1_subject', required_param('tmpl1_subject', PARAM_TEXT), 'local_courseinsights');
    set_config('tmpl1_body', required_param('tmpl1_body', PARAM_TEXT), 'local_courseinsights');
    set_config('tmpl2_subject', required_param('tmpl2_subject', PARAM_TEXT), 'local_courseinsights');
    set_config('tmpl2_body', required_param('tmpl2_body', PARAM_TEXT), 'local_courseinsights');
    redirect(
        new moodle_url('/local/courseinsights/message_templates.php'),
        get_string('msgtemplates_saved', 'local_courseinsights'),
        null,
        \core\output\notification::NOTIFY_SUCCESS
    );
}

$tmpl1subject = (string) (get_config('local_courseinsights', 'tmpl1_subject')
    ?: get_string('tmpl1_subject_default', 'local_courseinsights'));
$tmpl1body    = (string) (get_config('local_courseinsights', 'tmpl1_body')
    ?: get_string('tmpl1_body_default', 'local_courseinsights'));
$tmpl2subject = (string) (get_config('local_courseinsights', 'tmpl2_subject')
    ?: get_string('tmpl2_subject_default', 'local_courseinsights'));
$tmpl2body    = (string) (get_config('local_courseinsights', 'tmpl2_body')
    ?: get_string('tmpl2_body_default', 'local_courseinsights'));

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
if (has_capability('local/courseinsights:createintervention', $context)) {
    echo html_writer::tag('a', get_string('tab_interventions', 'local_courseinsights'), [
        'href'  => (new moodle_url('/local/courseinsights/interventions.php'))->out(false),
        'class' => 'ci-tab',
    ]);
}
echo html_writer::tag('a', get_string('tab_riskrules', 'local_courseinsights'), [
    'href'  => (new moodle_url('/local/courseinsights/risk_rules.php'))->out(false),
    'class' => 'ci-tab',
]);
if (has_capability('local/courseinsights:manageinterventions', $context)) {
    echo html_writer::tag('a', get_string('tab_interventionreports', 'local_courseinsights'), [
        'href'  => (new moodle_url('/local/courseinsights/intervention_reports.php'))->out(false),
        'class' => 'ci-tab',
    ]);
}
echo html_writer::tag('a', get_string('tab_msgtemplates', 'local_courseinsights'), [
    'href'  => (new moodle_url('/local/courseinsights/message_templates.php'))->out(false),
    'class' => 'ci-tab ci-tab--active',
]);
echo html_writer::tag('a', get_string('tab_taskstatus', 'local_courseinsights'), [
    'href'  => (new moodle_url('/local/courseinsights/admin_tasks.php'))->out(false),
    'class' => 'ci-tab',
]);
echo html_writer::tag('a', get_string('tab_setupguide', 'local_courseinsights'), [
    'href'  => (new moodle_url('/local/courseinsights/help.php'))->out(false),
    'class' => 'ci-tab',
]);
echo html_writer::end_div();

echo html_writer::start_div('local-courseinsights-dashboard');
echo html_writer::start_div('ci-chart-card ci-chart-card--wide');

echo html_writer::tag('h2', get_string('msgtemplates_heading', 'local_courseinsights'), ['class' => 'ci-chart-title']);
echo html_writer::tag('p', get_string('msgtemplates_desc', 'local_courseinsights'));

echo html_writer::start_div('alert alert-info');
echo get_string('msgtemplates_placeholders', 'local_courseinsights');
echo html_writer::end_div();

$formurl = new moodle_url('/local/courseinsights/message_templates.php');
echo html_writer::start_tag('form', ['method' => 'post', 'action' => $formurl->out(false)]);
echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'sesskey', 'value' => sesskey()]);

// Template 1.
echo html_writer::tag('h3', get_string('msgtemplates_tmpl1_heading', 'local_courseinsights'), ['class' => 'ci-chart-title mt-3']);
echo html_writer::start_div('ci-form-row');
echo html_writer::tag(
    'label',
    get_string('msgtemplates_subject', 'local_courseinsights'),
    ['for' => 'tmpl1_subject', 'class' => 'ci-form-label']
);
echo html_writer::empty_tag('input', [
    'type'  => 'text',
    'id'    => 'tmpl1_subject',
    'name'  => 'tmpl1_subject',
    'value' => s($tmpl1subject),
    'class' => 'ci-date-input w-100',
]);
echo html_writer::end_div();
echo html_writer::start_div('ci-form-row');
echo html_writer::tag(
    'label',
    get_string('msgtemplates_body', 'local_courseinsights'),
    ['for' => 'tmpl1_body', 'class' => 'ci-form-label']
);
echo html_writer::tag('textarea', s($tmpl1body), [
    'id'    => 'tmpl1_body',
    'name'  => 'tmpl1_body',
    'rows'  => '6',
    'class' => 'ci-textarea form-control',
]);
echo html_writer::end_div();

// Template 2.
echo html_writer::tag('h3', get_string('msgtemplates_tmpl2_heading', 'local_courseinsights'), ['class' => 'ci-chart-title mt-3']);
echo html_writer::start_div('ci-form-row');
echo html_writer::tag(
    'label',
    get_string('msgtemplates_subject', 'local_courseinsights'),
    ['for' => 'tmpl2_subject', 'class' => 'ci-form-label']
);
echo html_writer::empty_tag('input', [
    'type'  => 'text',
    'id'    => 'tmpl2_subject',
    'name'  => 'tmpl2_subject',
    'value' => s($tmpl2subject),
    'class' => 'ci-date-input w-100',
]);
echo html_writer::end_div();
echo html_writer::start_div('ci-form-row');
echo html_writer::tag(
    'label',
    get_string('msgtemplates_body', 'local_courseinsights'),
    ['for' => 'tmpl2_body', 'class' => 'ci-form-label']
);
echo html_writer::tag('textarea', s($tmpl2body), [
    'id'    => 'tmpl2_body',
    'name'  => 'tmpl2_body',
    'rows'  => '6',
    'class' => 'ci-textarea form-control',
]);
echo html_writer::end_div();

echo html_writer::tag('button', get_string('savechanges'), [
    'type'  => 'submit',
    'class' => 'btn btn-primary mt-3',
]);
echo html_writer::end_tag('form');

echo html_writer::end_div();
echo html_writer::end_div();
echo html_writer::end_div();
echo html_writer::end_div();

echo $OUTPUT->footer();
