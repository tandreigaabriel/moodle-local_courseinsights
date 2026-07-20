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
 * Setup guide for Course Insights administrators.
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
$PAGE->set_url(new moodle_url('/local/courseinsights/help.php'));
$PAGE->set_title(get_string('tab_setupguide', 'local_courseinsights'));
$PAGE->set_heading(get_string('tab_setupguide', 'local_courseinsights'));
$PAGE->set_pagelayout('admin');

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
    'class' => 'ci-tab',
]);
echo html_writer::tag('a', get_string('tab_taskstatus', 'local_courseinsights'), [
    'href'  => (new moodle_url('/local/courseinsights/admin_tasks.php'))->out(false),
    'class' => 'ci-tab',
]);
echo html_writer::tag('a', get_string('tab_setupguide', 'local_courseinsights'), [
    'href'  => (new moodle_url('/local/courseinsights/help.php'))->out(false),
    'class' => 'ci-tab ci-tab--active',
]);
echo html_writer::end_div();

echo html_writer::start_div('local-courseinsights-dashboard');

echo html_writer::start_div('ci-chart-card ci-chart-card--wide');
echo html_writer::tag('h2', get_string('setup_guide_heading', 'local_courseinsights'), ['class' => 'ci-chart-title']);
echo html_writer::tag('p', get_string('setup_guide_intro', 'local_courseinsights'));

$steps = [
    ['heading' => 'help_step1_heading', 'body' => 'help_step1_body'],
    ['heading' => 'help_step2_heading', 'body' => 'help_step2_body'],
    ['heading' => 'help_step3_heading', 'body' => 'help_step3_body'],
    ['heading' => 'help_step4_heading', 'body' => 'help_step4_body'],
    ['heading' => 'help_step5_heading', 'body' => 'help_step5_body'],
];

foreach ($steps as $step) {
    echo html_writer::start_div('mb-4');
    echo html_writer::tag('h3', get_string($step['heading'], 'local_courseinsights'), ['class' => 'ci-chart-title']);
    echo html_writer::tag('p', get_string($step['body'], 'local_courseinsights'));
    echo html_writer::end_div();
}

echo html_writer::end_div();

echo html_writer::start_div('ci-chart-card ci-chart-card--wide');
echo html_writer::tag('h3', get_string('help_note_heading', 'local_courseinsights'), ['class' => 'ci-chart-title']);
echo html_writer::tag('p', get_string('help_taskstatus_note', 'local_courseinsights'));
echo html_writer::tag('p', get_string('help_messages_note', 'local_courseinsights'));
echo html_writer::end_div();

echo html_writer::end_div();
echo html_writer::end_div();
echo html_writer::end_div();

echo $OUTPUT->footer();
