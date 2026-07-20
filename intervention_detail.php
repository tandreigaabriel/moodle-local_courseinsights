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
 * Intervention case detail page for local_courseinsights.
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

$id = required_param('id', PARAM_INT);

$intervention = \local_courseinsights\intervention_service::get($id);
if (!$intervention) {
    throw new \moodle_exception('invalidrecordid', 'error');
}

$canmanage        = has_capability('local/courseinsights:manageinterventions', $context);
$canviewprivate   = has_capability('local/courseinsights:viewprivatenotes', $context);
$canviewrisk      = has_capability('local/courseinsights:viewrisk', $context);

$url  = new moodle_url('/local/courseinsights/intervention_detail.php', ['id' => $id]);
$PAGE->set_url($url);
$PAGE->set_title(get_string('intervention_detail_heading', 'local_courseinsights'));
$PAGE->set_heading(get_string('intervention_detail_heading', 'local_courseinsights'));
$PAGE->set_pagelayout('report');

$action = optional_param('action', '', PARAM_ALPHA);

// Handle status/assignment update.
if ($action === 'update') {
    require_sesskey();
    require_capability('local/courseinsights:manageinterventions', $context);

    $newstatus    = required_param('status', PARAM_ALPHA);
    $assignedto   = optional_param('assignedto', null, PARAM_INT) ?: null;
    $followupraw  = optional_param('followupdate', '', PARAM_RAW);
    $followupdate = null;
    if ($followupraw !== '') {
        $followupdate = strtotime($followupraw) ?: null;
    }
    \local_courseinsights\intervention_service::update($id, $newstatus, $assignedto, $followupdate);
    redirect($url, get_string('intervention_updated', 'local_courseinsights'), null, \core\output\notification::NOTIFY_SUCCESS);
}

// Handle add note.
if ($action === 'addnote') {
    require_sesskey();
    $notetext  = required_param('note', PARAM_TEXT);
    $isprivate = optional_param('isprivate', 0, PARAM_INT) && $canviewprivate;
    \local_courseinsights\intervention_service::add_note($id, $USER->id, $notetext, (bool) $isprivate);
    redirect($url, get_string('intervention_note_added', 'local_courseinsights'), null, \core\output\notification::NOTIFY_SUCCESS);
}

// Handle send message to student.
if ($action === 'sendmessage') {
    require_sesskey();
    $msgsubject = required_param('msgsubject', PARAM_TEXT);
    $msgbody    = required_param('msgbody', PARAM_TEXT);

    $stu = $DB->get_record('user', ['id' => $intervention->userid], 'id,firstname,lastname', IGNORE_MISSING);
    if (!$stu) {
        redirect($url, get_string('error'), null, \core\output\notification::NOTIFY_ERROR);
    }

    $advisername  = trim($USER->firstname . ' ' . $USER->lastname);
    $msgcoursename = format_string(
        $DB->get_field('course', 'fullname', ['id' => $intervention->courseid]) ?: ''
    );
    $find    = ['{firstname}', '{lastname}', '{course}', '{adviser}'];
    $replace = [$stu->firstname, $stu->lastname, $msgcoursename, $advisername];
    $msgsubject = str_replace($find, $replace, $msgsubject);
    $msgbody    = str_replace($find, $replace, $msgbody);

    $msg = new \core\message\message();
    $msg->component         = 'local_courseinsights';
    $msg->name              = 'intervention_contact';
    $msg->userfrom          = $USER;
    $msg->userto            = $stu;
    $msg->subject           = $msgsubject;
    $msg->fullmessage       = $msgbody;
    $msg->fullmessageformat = FORMAT_PLAIN;
    $msg->fullmessagehtml   = '<p>' . nl2br(s($msgbody)) . '</p>';
    $msg->smallmessage      = shorten_text($msgbody, 100);
    $msg->notification      = 1;
    $msg->courseid          = $intervention->courseid;
    $msgid = message_send($msg);

    if (!$msgid) {
        redirect(
            $url,
            get_string('intervention_msg_failed', 'local_courseinsights'),
            null,
            \core\output\notification::NOTIFY_ERROR
        );
    }

    \local_courseinsights\intervention_service::add_note(
        $id,
        $USER->id,
        get_string('intervention_msg_sent_note', 'local_courseinsights', $msgsubject),
        false
    );

    $stuname = trim($stu->firstname . ' ' . $stu->lastname);
    redirect(
        $url,
        get_string('intervention_msg_sent', 'local_courseinsights', $stuname),
        null,
        \core\output\notification::NOTIFY_SUCCESS
    );
}

// Load data.
$student    = $DB->get_record('user', ['id' => $intervention->userid], 'id,firstname,lastname', IGNORE_MISSING);
$course     = $DB->get_record('course', ['id' => $intervention->courseid], 'id,fullname', IGNORE_MISSING);
$notes      = \local_courseinsights\intervention_service::get_notes($id, $canviewprivate);
$staffusers = get_users_by_capability($context, 'local/courseinsights:createintervention');
// Get_users_by_capability never returns site admins (admin bypass is invisible to it).
foreach (get_admins() as $admin) {
    if (!isset($staffusers[$admin->id])) {
        $staffusers[$admin->id] = $admin;
    }
}

$studentname = $student ? trim($student->firstname . ' ' . $student->lastname) : '?';
$coursename  = $course ? format_string($course->fullname) : '?';

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
echo html_writer::tag('h2', get_string('intervention_detail_heading', 'local_courseinsights'), ['class' => 'ci-chart-title']);

$statusbadge = html_writer::span(
    get_string(\local_courseinsights\intervention_service::status_string_key($intervention->status), 'local_courseinsights'),
    \local_courseinsights\intervention_service::status_badge_class($intervention->status)
);

echo html_writer::start_div('ci-intervention-meta');
echo html_writer::tag(
    'p',
    html_writer::tag('strong', get_string('intervention_col_student', 'local_courseinsights')) . ': ' . s($studentname)
);
echo html_writer::tag(
    'p',
    html_writer::tag('strong', get_string('intervention_col_course', 'local_courseinsights')) . ': ' . s($coursename)
);
echo html_writer::tag(
    'p',
    html_writer::tag('strong', get_string('intervention_col_title', 'local_courseinsights')) . ': ' . s($intervention->title)
);
echo html_writer::tag(
    'p',
    html_writer::tag('strong', get_string('intervention_col_status', 'local_courseinsights')) . ': ' . $statusbadge
);
if ($canviewrisk && !empty($intervention->risklevel)) {
    $riskbadge = html_writer::span(
        s($intervention->risklevel) . ' (' . (int) $intervention->riskscore . ')',
        'ci-risk-badge ci-risk-badge--' . s($intervention->risklevel)
    );
    echo html_writer::tag(
        'p',
        html_writer::tag('strong', get_string('atrisk_col_riskscore', 'local_courseinsights')) . ': ' . $riskbadge
    );
}
if (!empty($intervention->followupdate)) {
    echo html_writer::tag(
        'p',
        html_writer::tag('strong', get_string('intervention_col_followup', 'local_courseinsights')) . ': '
        . userdate((int) $intervention->followupdate, get_string('strftimedatefullshort'))
    );
}
echo html_writer::tag(
    'p',
    html_writer::tag('strong', get_string('intervention_col_created', 'local_courseinsights')) . ': '
    . userdate((int) $intervention->timecreated)
);
echo html_writer::end_div();

echo html_writer::start_tag('p');
echo html_writer::tag('a', '&laquo; ' . get_string('tab_interventions', 'local_courseinsights'), [
    'href'  => (new moodle_url('/local/courseinsights/interventions.php'))->out(false),
    'class' => 'btn btn-sm btn-outline-secondary',
]);
echo html_writer::end_tag('p');
echo html_writer::end_div();

if ($canmanage) {
    echo html_writer::start_div('ci-chart-card ci-chart-card--wide');
    echo html_writer::tag('h3', get_string('intervention_update_heading', 'local_courseinsights'), ['class' => 'ci-chart-title']);

    $updateurl = new moodle_url('/local/courseinsights/intervention_detail.php', ['id' => $id]);
    echo html_writer::start_tag('form', [
        'method' => 'post',
        'action' => $updateurl->out(false),
        'class'  => 'ci-intervention-form',
    ]);
    echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'action', 'value' => 'update']);
    echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'sesskey', 'value' => sesskey()]);

    // Status select.
    echo html_writer::start_div('ci-form-row');
    echo html_writer::tag(
        'label',
        get_string('intervention_col_status', 'local_courseinsights'),
        ['for' => 'ci_status', 'class' => 'ci-form-label']
    );
    echo html_writer::start_tag('select', ['id' => 'ci_status', 'name' => 'status', 'class' => 'ci-filter-select']);
    foreach (\local_courseinsights\intervention_service::STATUSES as $s) {
        $attrs = ['value' => $s];
        if ($intervention->status === $s) {
            $attrs['selected'] = 'selected';
        }
        $skey = \local_courseinsights\intervention_service::status_string_key($s);
        echo html_writer::tag('option', get_string($skey, 'local_courseinsights'), $attrs);
    }
    echo html_writer::end_tag('select');
    echo html_writer::end_div();

    // Assignedto select.
    echo html_writer::start_div('ci-form-row');
    echo html_writer::tag(
        'label',
        get_string('intervention_col_assignedto', 'local_courseinsights'),
        ['for' => 'ci_assignedto', 'class' => 'ci-form-label']
    );
    echo html_writer::start_tag('select', ['id' => 'ci_assignedto', 'name' => 'assignedto', 'class' => 'ci-filter-select']);
    echo html_writer::tag('option', get_string('intervention_unassigned', 'local_courseinsights'), ['value' => '']);
    foreach ($staffusers as $su) {
        $attrs = ['value' => $su->id];
        if (!empty($intervention->assignedto) && (int) $intervention->assignedto === (int) $su->id) {
            $attrs['selected'] = 'selected';
        }
        echo html_writer::tag('option', fullname($su), $attrs);
    }
    echo html_writer::end_tag('select');
    echo html_writer::end_div();

    // Follow-up date.
    $followupval = !empty($intervention->followupdate) ? date('Y-m-d', (int) $intervention->followupdate) : '';
    echo html_writer::start_div('ci-form-row');
    echo html_writer::tag(
        'label',
        get_string('intervention_col_followup', 'local_courseinsights'),
        ['for' => 'ci_followupdate', 'class' => 'ci-form-label']
    );
    echo html_writer::empty_tag('input', [
        'type'  => 'date',
        'id'    => 'ci_followupdate',
        'name'  => 'followupdate',
        'value' => $followupval,
        'class' => 'ci-date-input',
    ]);
    echo html_writer::end_div();

    echo html_writer::tag('button', get_string('savechanges'), ['type' => 'submit', 'class' => 'btn btn-primary mt-2']);
    echo html_writer::end_tag('form');
    echo html_writer::end_div();
}

$dayssince = (int) floor((time() - $intervention->timecreated) / DAYSECS);
if ($dayssince >= 3 && $student && $course) {
    $engagement = \local_courseinsights\intervention_service::get_engagement_comparison(
        $intervention->userid,
        $intervention->courseid,
        (int) $intervention->timecreated
    );
    $afterdays = $engagement['afterdays'];

    $trendclass = function (int $before, int $after, int $afdays): string {
        $bpd = $before / 30.0;
        $apd = $afdays > 0 ? $after / (float) $afdays : 0.0;
        if ($bpd == 0 && $apd == 0) {
            return 'flat';
        }
        if ($apd > $bpd * 1.1 || ($bpd == 0 && $apd > 0)) {
            return 'up';
        }
        if ($bpd > 0 && $apd < $bpd * 0.9) {
            return 'down';
        }
        return 'flat';
    };

    echo html_writer::start_div('ci-chart-card ci-chart-card--wide');
    echo html_writer::tag('h3', get_string('engagement_heading', 'local_courseinsights'), ['class' => 'ci-chart-title']);

    echo html_writer::tag(
        'p',
        get_string('engagement_desc', 'local_courseinsights', $afterdays),
        ['class' => 'ci-muted-text']
    );

    echo html_writer::start_tag('table', ['class' => 'ci-top10-table ci-engagement-table']);
    echo html_writer::start_tag('thead');
    echo html_writer::start_tag('tr');
    echo html_writer::tag('th', get_string('engagement_col_metric', 'local_courseinsights'));
    echo html_writer::tag('th', get_string('engagement_col_before', 'local_courseinsights'), ['class' => 'ci-top10-value']);
    echo html_writer::tag(
        'th',
        get_string('engagement_col_after', 'local_courseinsights', $afterdays),
        ['class' => 'ci-top10-value']
    );
    echo html_writer::tag('th', get_string('engagement_col_trend', 'local_courseinsights'), ['class' => 'ci-top10-value']);
    echo html_writer::end_tag('tr');
    echo html_writer::end_tag('thead');
    echo html_writer::start_tag('tbody');

    foreach ($engagement['metrics'] as $metric) {
        $tc   = $trendclass($metric['before'], $metric['after'], $afterdays);
        $tcls = 'ci-trend-badge ci-trend-badge--' . $tc;
        $tlbl = get_string('engagement_trend_' . $tc, 'local_courseinsights');
        echo html_writer::start_tag('tr');
        echo html_writer::tag('td', get_string('engagement_metric_' . $metric['key'], 'local_courseinsights'));
        echo html_writer::tag('td', (string) $metric['before'], ['class' => 'ci-top10-value']);
        echo html_writer::tag('td', (string) $metric['after'], ['class' => 'ci-top10-value']);
        echo html_writer::tag('td', html_writer::span($tlbl, $tcls), ['class' => 'ci-top10-value']);
        echo html_writer::end_tag('tr');
    }

    echo html_writer::end_tag('tbody');
    echo html_writer::end_tag('table');
    echo html_writer::end_div();
}

echo html_writer::start_div('ci-chart-card ci-chart-card--wide');
echo html_writer::tag('h3', get_string('intervention_notes_heading', 'local_courseinsights'), ['class' => 'ci-chart-title']);

if (empty($notes)) {
    echo html_writer::tag('p', get_string('intervention_notes_empty', 'local_courseinsights'), ['class' => 'ci-nodata']);
} else {
    echo html_writer::start_div('ci-notes-timeline');
    foreach ($notes as $note) {
        $privatelabel = $note->isprivate
            ? html_writer::span(' ' . get_string('intervention_note_private', 'local_courseinsights'), 'ci-private-badge')
            : '';
        echo html_writer::start_div('ci-note' . ($note->isprivate ? ' ci-note--private' : ''));
        echo html_writer::tag(
            'div',
            html_writer::tag('strong', s($note->authorname)) . ' &mdash; ' . userdate((int) $note->timecreated) . $privatelabel,
            ['class' => 'ci-note-meta']
        );
        echo html_writer::tag('div', format_text(s($note->note), FORMAT_PLAIN), ['class' => 'ci-note-body']);
        echo html_writer::end_div();
    }
    echo html_writer::end_div();
}

// Add note form.
$noteurl = new moodle_url('/local/courseinsights/intervention_detail.php', ['id' => $id]);
echo html_writer::start_tag('form', ['method' => 'post', 'action' => $noteurl->out(false), 'class' => 'ci-add-note-form mt-3']);
echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'action', 'value' => 'addnote']);
echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'sesskey', 'value' => sesskey()]);
echo html_writer::tag(
    'label',
    get_string('intervention_note_add', 'local_courseinsights'),
    ['for' => 'ci_note', 'class' => 'ci-form-label d-block mb-1']
);
echo html_writer::tag('textarea', '', [
    'id'          => 'ci_note',
    'name'        => 'note',
    'rows'        => '4',
    'class'       => 'ci-textarea form-control mb-2',
    'required'    => 'required',
    'placeholder' => get_string('intervention_note_placeholder', 'local_courseinsights'),
]);
if ($canviewprivate) {
    echo html_writer::start_div('ci-form-check mb-2');
    echo html_writer::empty_tag('input', [
        'type'  => 'checkbox',
        'id'    => 'ci_isprivate',
        'name'  => 'isprivate',
        'value' => '1',
        'class' => 'form-check-input me-1',
    ]);
    echo html_writer::tag('label', get_string('intervention_note_private', 'local_courseinsights'), [
        'for'   => 'ci_isprivate',
        'class' => 'form-check-label',
    ]);
    echo html_writer::end_div();
}
echo html_writer::tag('button', get_string('intervention_note_submit', 'local_courseinsights'), [
    'type'  => 'submit',
    'class' => 'btn btn-primary',
]);
echo html_writer::end_tag('form');
echo html_writer::end_div();

$tmpl1subject = (string) (get_config('local_courseinsights', 'tmpl1_subject')
    ?: get_string('tmpl1_subject_default', 'local_courseinsights'));
$tmpl1body    = (string) (get_config('local_courseinsights', 'tmpl1_body')
    ?: get_string('tmpl1_body_default', 'local_courseinsights'));
$tmpl2subject = (string) (get_config('local_courseinsights', 'tmpl2_subject')
    ?: get_string('tmpl2_subject_default', 'local_courseinsights'));
$tmpl2body    = (string) (get_config('local_courseinsights', 'tmpl2_body')
    ?: get_string('tmpl2_body_default', 'local_courseinsights'));

$templatesjson = json_encode([
    'tmpl1' => ['subject' => $tmpl1subject, 'body' => $tmpl1body],
    'tmpl2' => ['subject' => $tmpl2subject, 'body' => $tmpl2body],
]);
$PAGE->requires->js_init_code(
    'var ciMsgTpl=' . $templatesjson . ';' .
    'var ciTplSel=document.getElementById("ci_msg_tmpl");' .
    'if(ciTplSel){ciTplSel.addEventListener("change",function(){' .
    'var t=ciMsgTpl[this.value];' .
    'if(t){document.getElementById("ci_msg_subject").value=t.subject;' .
    'document.getElementById("ci_msg_body").value=t.body;}' .
    '});}',
    true
);

echo html_writer::start_div('ci-chart-card ci-chart-card--wide');
echo html_writer::tag(
    'h3',
    get_string('intervention_sendmsg_heading', 'local_courseinsights'),
    ['class' => 'ci-chart-title']
);

$msgurl = new moodle_url('/local/courseinsights/intervention_detail.php', ['id' => $id]);
echo html_writer::start_tag('form', [
    'method' => 'post',
    'action' => $msgurl->out(false),
    'class'  => 'ci-send-msg-form',
]);
echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'action', 'value' => 'sendmessage']);
echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'sesskey', 'value' => sesskey()]);

// Template selector.
echo html_writer::start_div('ci-form-row');
echo html_writer::tag(
    'label',
    get_string('intervention_msg_template', 'local_courseinsights'),
    ['for' => 'ci_msg_tmpl', 'class' => 'ci-form-label']
);
echo html_writer::start_tag('select', ['id' => 'ci_msg_tmpl', 'name' => 'msgtmpl', 'class' => 'ci-filter-select']);
echo html_writer::tag(
    'option',
    get_string('intervention_msg_tmpl_none', 'local_courseinsights'),
    ['value' => '']
);
echo html_writer::tag(
    'option',
    get_string('intervention_msg_tmpl1', 'local_courseinsights'),
    ['value' => 'tmpl1']
);
echo html_writer::tag(
    'option',
    get_string('intervention_msg_tmpl2', 'local_courseinsights'),
    ['value' => 'tmpl2']
);
echo html_writer::end_tag('select');
echo html_writer::end_div();

// Subject.
echo html_writer::start_div('ci-form-row');
echo html_writer::tag(
    'label',
    get_string('intervention_msg_subject', 'local_courseinsights'),
    ['for' => 'ci_msg_subject', 'class' => 'ci-form-label']
);
echo html_writer::empty_tag('input', [
    'type'     => 'text',
    'id'       => 'ci_msg_subject',
    'name'     => 'msgsubject',
    'value'    => '',
    'class'    => 'ci-date-input w-100',
    'required' => 'required',
]);
echo html_writer::end_div();

// Body.
echo html_writer::start_div('ci-form-row');
echo html_writer::tag(
    'label',
    get_string('intervention_msg_body', 'local_courseinsights'),
    ['for' => 'ci_msg_body', 'class' => 'ci-form-label']
);
echo html_writer::tag('textarea', '', [
    'id'       => 'ci_msg_body',
    'name'     => 'msgbody',
    'rows'     => '8',
    'class'    => 'ci-textarea form-control',
    'required' => 'required',
]);
echo html_writer::end_div();

echo html_writer::tag(
    'button',
    get_string('intervention_msg_send', 'local_courseinsights'),
    ['type' => 'submit', 'class' => 'btn btn-primary mt-2']
);
echo html_writer::end_tag('form');
echo html_writer::end_div();

echo html_writer::end_div();
echo html_writer::end_div();
echo html_writer::end_div();

echo $OUTPUT->footer();
