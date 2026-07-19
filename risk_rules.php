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
 * Risk rules configuration page for local_courseinsights.
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

$url = new moodle_url('/local/courseinsights/risk_rules.php');
$PAGE->set_url($url);
$PAGE->set_title(get_string('tab_riskrules', 'local_courseinsights'));
$PAGE->set_heading(get_string('tab_riskrules', 'local_courseinsights'));
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

$action = optional_param('action', '', PARAM_ALPHA);

if ($action === 'save') {
    require_sesskey();
    $rules = $DB->get_records('local_courseinsights_risk_rules', null, 'sortorder ASC');
    foreach ($rules as $rule) {
        $threshold = optional_param('threshold_' . $rule->id, (float) $rule->threshold, PARAM_FLOAT);
        $weight    = optional_param('weight_' . $rule->id, (int) $rule->weight, PARAM_INT);
        $enabled   = optional_param('enabled_' . $rule->id, 0, PARAM_INT);
        $DB->update_record('local_courseinsights_risk_rules', (object) [
            'id'           => $rule->id,
            'threshold'    => max(0, (float) $threshold),
            'weight'       => min(100, max(0, (int) $weight)),
            'enabled'      => $enabled ? 1 : 0,
            'timemodified' => time(),
        ]);
    }
    redirect($url, get_string('risk_rules_saved', 'local_courseinsights'), null, \core\output\notification::NOTIFY_SUCCESS);
}

$rules = $DB->get_records('local_courseinsights_risk_rules', null, 'sortorder ASC');

if (empty($rules)) {
    $now = time();
    foreach (\local_courseinsights\risk_service::get_default_rules() as $row) {
        [$ruletype, $label, $threshold, $weight, $enabled, $sortorder] = $row;
        $DB->insert_record('local_courseinsights_risk_rules', (object) [
            'ruletype'     => $ruletype,
            'label'        => $label,
            'threshold'    => $threshold,
            'weight'       => $weight,
            'enabled'      => $enabled,
            'sortorder'    => $sortorder,
            'timecreated'  => $now,
            'timemodified' => $now,
        ]);
    }
    $rules = $DB->get_records('local_courseinsights_risk_rules', null, 'sortorder ASC');
}

$brandaccent = (string) get_config('local_courseinsights', 'brandaccentcolor');
$validaccent = $brandaccent && preg_match('/^#[0-9a-fA-F]{3,6}$/', $brandaccent);

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
    'class' => 'ci-tab',
]);
echo html_writer::tag('a', get_string('tab_interventions', 'local_courseinsights'), [
    'href'  => (new moodle_url('/local/courseinsights/interventions.php'))->out(false),
    'class' => 'ci-tab',
]);
echo html_writer::tag('a', get_string('tab_riskrules', 'local_courseinsights'), [
    'href'  => $url->out(false),
    'class' => 'ci-tab ci-tab--active',
]);
if (has_capability('local/courseinsights:manageinterventions', $context)) {
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
}
echo html_writer::end_div();

echo html_writer::start_div('local-courseinsights-dashboard');
echo html_writer::start_div('ci-chart-card ci-chart-card--wide');
echo html_writer::tag('h2', get_string('risk_rules_heading', 'local_courseinsights'), ['class' => 'ci-chart-title']);
echo html_writer::tag('p', get_string('risk_rules_desc', 'local_courseinsights'));

if (empty($rules)) {
    echo $OUTPUT->notification(get_string('risk_rules_nodata', 'local_courseinsights'), 'info');
} else {
    echo html_writer::start_tag('form', [
        'method' => 'post',
        'action' => $url->out(false),
    ]);
    echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'action', 'value' => 'save']);
    echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'sesskey', 'value' => sesskey()]);

    echo html_writer::start_tag('table', ['class' => 'ci-top10-table ci-risk-rules-table']);
    echo html_writer::start_tag('thead');
    echo html_writer::start_tag('tr');
    echo html_writer::tag('th', get_string('risk_rules_label', 'local_courseinsights'));
    echo html_writer::tag('th', get_string('risk_rules_threshold', 'local_courseinsights'), ['class' => 'ci-top10-value']);
    echo html_writer::tag('th', get_string('risk_rules_weight', 'local_courseinsights'), ['class' => 'ci-top10-value']);
    echo html_writer::tag('th', get_string('risk_rules_enabled', 'local_courseinsights'), ['class' => 'ci-top10-value']);
    echo html_writer::end_tag('tr');
    echo html_writer::end_tag('thead');
    echo html_writer::start_tag('tbody');

    foreach ($rules as $rule) {
        $labelkey = 'risk_rule_' . $rule->ruletype;
        $label    = get_string_manager()->string_exists($labelkey, 'local_courseinsights')
            ? get_string($labelkey, 'local_courseinsights')
            : s($rule->label);

        $checkedattr = $rule->enabled ? ['checked' => 'checked'] : [];

        echo html_writer::start_tag('tr');
        echo html_writer::tag('td', $label);
        echo html_writer::tag(
            'td',
            html_writer::empty_tag('input', [
                'type'  => 'number',
                'name'  => 'threshold_' . $rule->id,
                'value' => (float) $rule->threshold,
                'min'   => '0',
                'step'  => '1',
                'class' => 'ci-risk-input',
            ]),
            ['class' => 'ci-top10-value']
        );
        echo html_writer::tag(
            'td',
            html_writer::empty_tag('input', [
                'type'  => 'number',
                'name'  => 'weight_' . $rule->id,
                'value' => (int) $rule->weight,
                'min'   => '0',
                'max'   => '100',
                'step'  => '1',
                'class' => 'ci-risk-input',
            ]),
            ['class' => 'ci-top10-value']
        );
        echo html_writer::tag(
            'td',
            html_writer::empty_tag('input', array_merge([
                'type'  => 'checkbox',
                'name'  => 'enabled_' . $rule->id,
                'value' => '1',
            ], $checkedattr)),
            ['class' => 'ci-top10-value']
        );
        echo html_writer::end_tag('tr');
    }

    echo html_writer::end_tag('tbody');
    echo html_writer::end_tag('table');

    echo html_writer::start_div('ci-risk-levelguide mt-3');
    echo html_writer::tag('h3', get_string('risk_rules_levelguide', 'local_courseinsights'), ['class' => 'ci-chart-title']);
    echo html_writer::start_tag('ul', ['class' => 'ci-risk-levellist']);
    foreach (
        [
        'low'      => '0–29',
        'medium'   => '30–59',
        'high'     => '60–79',
        'critical' => '80–100',
        ] as $level => $range
    ) {
        echo html_writer::tag(
            'li',
            html_writer::span(
                get_string('risk_level_' . $level, 'local_courseinsights'),
                'ci-risk-badge ci-risk-badge--' . $level
            ) . ' ' . $range . ' points'
        );
    }
    echo html_writer::end_tag('ul');
    echo html_writer::end_div();

    echo html_writer::tag('button', get_string('savechanges'), [
        'type'  => 'submit',
        'class' => 'btn btn-primary mt-3',
    ]);
    echo html_writer::end_tag('form');
}

echo html_writer::end_div();
echo html_writer::end_div();
echo html_writer::end_div();
echo html_writer::end_div();

echo $OUTPUT->footer();
