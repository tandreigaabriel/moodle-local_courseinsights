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
 * Language strings for local_courseinsights.
 *
 * @package    local_courseinsights
 * @copyright  2026 Andrei Toma
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

$string['active'] = 'Active';
$string['activityoverview'] = 'Activity Overview';
$string['activitytype'] = 'Activity type';
$string['activitytype_all'] = 'All activity types';
$string['activitytype_assign'] = 'Assignments';
$string['activitytype_exam'] = 'Exams';
$string['activitytype_mini'] = 'Mini exams';
$string['activitytype_quiz'] = 'Quizzes / exams';
$string['advanced_options'] = 'Advanced options';
$string['alert_body'] = 'This is an automated alert from Course Insights.

Course: {$a->coursename}

Reason(s):
{$a->reasons}

Completion rate: {$a->completionrate}%
Last student activity: {$a->lastactivitydate}

View the dashboard: {$a->dashboardurl}

---
To adjust these alerts, go to Site administration > Local plugins > Course Insights settings.';
$string['alert_reason_inactive'] = 'No student activity recorded for {$a->dayssince} days (threshold: {$a->threshold} days)';
$string['alert_reason_lowcompletion'] = 'Completion rate is {$a->completionrate}% (below the {$a->threshold}% threshold)';
$string['alert_subject'] = 'Course Insights alert: {$a}';
$string['alertcompletionthreshold'] = 'Completion rate alert threshold (%)';
$string['alertcompletionthreshold_desc'] = 'Send an alert when a course completion rate drops below this percentage. Set to 0 to disable completion alerts.';
$string['alertinactivedays'] = 'Inactivity alert threshold (days)';
$string['alertinactivedays_desc'] = 'Send an alert when a course has had no student activity for this many days. Set to 0 to disable inactivity alerts.';
$string['alertsenabled'] = 'Enable automated alerts';
$string['alertsenabled_desc'] = 'When enabled, the daily alert task will notify editing teachers of courses that fall below the configured thresholds.';
$string['alertsheading'] = 'Automated alerts';
$string['allcategories'] = 'All categories';
$string['allcohorts'] = 'All cohorts';
$string['allcourses'] = 'All courses';
$string['assignments'] = 'Assignments';
$string['atrisk_col_action'] = 'Action';
$string['atrisk_col_course'] = 'Course';
$string['atrisk_col_days'] = 'Days Inactive';
$string['atrisk_col_lastaccess'] = 'Last Access';
$string['atrisk_col_riskscore'] = 'Risk';
$string['atrisk_col_student'] = 'Student';
$string['atrisk_heading'] = 'At-Risk Students';
$string['atrisk_never'] = 'Never';
$string['atrisk_nodata'] = 'No at-risk students found for this threshold.';
$string['avgquizgrade'] = 'Average quiz grade %';
$string['backtodashboard'] = 'Back to dashboard';
$string['brandaccentcolor'] = 'Accent colour';
$string['brandaccentcolor_desc'] = 'Hex colour code (e.g. #6c5ce7) used as the primary colour for headings and highlights. Leave blank to use the default.';
$string['brandheading'] = 'White-label / Branding';
$string['brandlogourl'] = 'Logo URL';
$string['brandlogourl_desc'] = 'URL of an image to display in the dashboard header. Leave blank to hide the logo.';
$string['brandname'] = 'Site name override';
$string['brandname_desc'] = 'Custom name shown in the dashboard header. Leave blank to use the default plugin name.';
$string['buildcoursedetailsnapshot'] = 'Course Insights: rebuild detail snapshot for one course';
$string['buildsummarycache'] = 'Build Course Insights summary cache';
$string['cachebuilt'] = 'Course Insights summary cache has been rebuilt.';
$string['cachedef_dropdown_options'] = 'Cached dropdown option lists';
$string['cachedef_site_kpis'] = 'Cached site KPI summaries';
$string['category'] = 'Category';
$string['charttitle'] = 'Activity completion overview';
$string['charttruncated'] = 'Chart shows the first 20 courses. Use the course filter to narrow results.';
$string['cohort'] = 'Cohort';
$string['col_activity'] = 'Activity';
$string['col_avg'] = 'Avg';
$string['col_completionpct'] = 'Completion %';
$string['col_completions'] = 'Completions';
$string['col_forumposts'] = 'Forum Posts';
$string['col_grade'] = 'Grade / Max';
$string['col_lastaccess'] = 'Last Access';
$string['col_max'] = 'Max';
$string['col_min'] = 'Min';
$string['col_modtype'] = 'Type';
$string['col_passrate'] = 'Pass Rate';
$string['col_quiz'] = 'Quiz';
$string['col_quizattempts'] = 'Quiz Attempts';
$string['col_quizstudents'] = 'Students';
$string['col_rank'] = 'Rank';
$string['col_score_pct'] = 'Score %';
$string['col_student'] = 'Student';
$string['col_submissions'] = 'Submissions';
$string['col_visits'] = 'Course Visits';
$string['compareperiod_desc'] = 'Use this only when you want to compare the main results against another date range.';
$string['compareperiod_end'] = 'Baseline end';
$string['compareperiod_heading'] = 'Compare with another period';
$string['compareperiod_start'] = 'Baseline start';
$string['compareperiod_summary'] = 'Optional baseline dates';
$string['comparepreviousvalue'] = 'Baseline: {$a}';
$string['completionrate'] = 'Completion rate %';
$string['contentbreakdown'] = 'Content Breakdown';
$string['course'] = 'Course';
$string['coursedetail'] = 'Course Detail';
$string['courseinsights:createintervention'] = 'Create student intervention cases';
$string['courseinsights:export'] = 'Export Course Insights report';
$string['courseinsights:manage'] = 'Manage Course Insights';
$string['courseinsights:manageinterventions'] = 'Manage and update intervention cases';
$string['courseinsights:view'] = 'View Course Insights report';
$string['courseinsights:viewprivatenotes'] = 'View private intervention notes';
$string['courseinsights:viewrisk'] = 'View student risk scores';
$string['coursesperpage'] = 'Courses per page';
$string['coursesperpage_desc'] = 'Number of course cards shown per page on the dashboard. Default is 12. Set to 0 to use the default.';
$string['dashboard'] = 'Course Insights Dashboard';
$string['dashboardoverview'] = 'Dashboard Overview';
$string['dateformathelp'] = 'Use YYYY-MM-DD, for example 2026-06-01';
$string['dateformathelp_help'] = 'Enter the date in YYYY-MM-DD format, for example 2026-06-01. Leave blank to not filter by this date.';
$string['datepreset_30days'] = 'Last 30 days';
$string['datepreset_7days'] = 'Last 7 days';
$string['datepreset_clear'] = 'Clear dates';
$string['datepreset_thismonth'] = 'This month';
$string['detailcachebuilt'] = 'Course Insights detail snapshot tasks queued for background processing.';
$string['detailcachepending'] = 'The detailed report snapshot for this course has not been built yet. Live data is being used until the overnight task runs.';
$string['detailedreport'] = 'Detailed Report';
$string['digest_subject'] = 'Course Insights — {$a} digest';
$string['digestenabled'] = 'Enable scheduled digest emails';
$string['digestenabled_desc'] = 'When enabled, a course summary digest will be sent to all users with the Course Insights manage capability at the configured frequency.';
$string['digestfrequency'] = 'Digest frequency';
$string['digestfrequency_desc'] = 'How often to send the digest email. The task runs daily and checks whether enough time has passed since the last send.';
$string['digestfrequency_monthly'] = 'Monthly';
$string['digestfrequency_weekly'] = 'Weekly';
$string['digestheading'] = 'Scheduled digest emails';
$string['enablecache'] = 'Enable summary cache';
$string['enablecache_desc'] = 'When enabled, the dashboard can use cached all-time summary data for faster loading.';
$string['enddate'] = 'Main period end';
$string['engagement_col_after'] = 'Since intervention ({$a} days)';
$string['engagement_col_before'] = '30 days before';
$string['engagement_col_metric'] = 'Metric';
$string['engagement_col_trend'] = 'Trend';
$string['engagement_desc'] = 'Compares activity in the 30 days before this case was opened against the {$a} days since (per-day rate used for trend).';
$string['engagement_heading'] = 'Student Engagement Since Intervention';
$string['engagement_metric_forumposts'] = 'Forum posts';
$string['engagement_metric_quizattempts'] = 'Quiz attempts';
$string['engagement_metric_submissions'] = 'Assignment submissions';
$string['engagement_metric_visits'] = 'Course visits';
$string['engagement_trend_down'] = '↓ Declining';
$string['engagement_trend_flat'] = '→ Stable';
$string['engagement_trend_up'] = '↑ Improving';
$string['engagementheatmap'] = 'Engagement Heatmap';
$string['engagementheatmap_nodata'] = 'No activity log data available for this course.';
$string['enrolledstudents'] = 'Enrolled students';
$string['examattempts'] = 'Students with completed exams';
$string['examkeywords'] = 'Exam keywords';
$string['examkeywords_default'] = 'exam,final';
$string['examkeywords_desc'] = 'Comma-separated words used to identify exams from quiz names. Example: exam,final';
$string['exams'] = 'Exams';
$string['exportcsv'] = 'Export CSV';
$string['exportxlsx'] = 'Export Excel (.xlsx)';
$string['filter'] = 'Apply filters';
$string['filters'] = 'Filters';
$string['filterstep_courses'] = 'Choose courses';
$string['filterstep_courses_desc'] = 'Limit the report by cohort, category, or one course.';
$string['filterstep_dates'] = 'Choose the main period';
$string['filterstep_dates_desc'] = 'These dates control the main dashboard numbers.';
$string['filterstep_scope'] = 'Choose what to count';
$string['filterstep_scope_desc'] = 'Pick the activity type and student status used in the report.';
$string['followup_reminder_body'] = 'The follow-up date for an intervention case has passed.

Case:         {$a->title}
Student:      {$a->studentname}
Course:       {$a->coursename}
Follow-up due: {$a->followupdate}

View the case and update its status:
{$a->url}';
$string['followup_reminder_smallmessage'] = 'Follow-up overdue for {$a->studentname} in {$a->coursename}';
$string['followup_reminder_subject'] = 'Follow-up overdue: {$a->title}';
$string['forumactivities'] = 'Forum Activities';
$string['gradedistribution'] = 'Grade Distribution';
$string['gradedistribution_nodata'] = 'No grade data available for this course.';
$string['healthscore'] = 'Health score: {$a}/100';
$string['inactive'] = 'Inactive';
$string['intervention_col_assignedto'] = 'Assigned to';
$string['intervention_col_course'] = 'Course';
$string['intervention_col_created'] = 'Created';
$string['intervention_col_followup'] = 'Follow-up date';
$string['intervention_col_status'] = 'Status';
$string['intervention_col_student'] = 'Student';
$string['intervention_col_title'] = 'Case title';
$string['intervention_col_urgency'] = 'Urgency';
$string['intervention_create'] = 'Intervene';
$string['intervention_created'] = 'Intervention case created.';
$string['intervention_detail_heading'] = 'Intervention Case';
$string['intervention_filter_all'] = 'All statuses';
$string['intervention_msg_body'] = 'Message';
$string['intervention_msg_send'] = 'Send Message';
$string['intervention_msg_sent'] = 'Message sent to {$a}.';
$string['intervention_msg_sent_note'] = 'Message sent to student. Subject: {$a}';
$string['intervention_msg_subject'] = 'Subject';
$string['intervention_msg_template'] = 'Template';
$string['intervention_msg_tmpl1'] = 'Initial contact';
$string['intervention_msg_tmpl2'] = 'Follow-up';
$string['intervention_msg_tmpl_none'] = 'Select a template...';
$string['intervention_nodata'] = 'No intervention cases found.';
$string['intervention_note_add'] = 'Add a note';
$string['intervention_note_added'] = 'Note added.';
$string['intervention_note_placeholder'] = 'Record what happened, what was communicated, or what action was taken...';
$string['intervention_note_private'] = 'Private (managers only)';
$string['intervention_note_submit'] = 'Save note';
$string['intervention_notes_empty'] = 'No notes yet. Add the first note below.';
$string['intervention_notes_heading'] = 'Case Notes';
$string['intervention_sendmsg_heading'] = 'Send Message to Student';
$string['intervention_status_closed'] = 'Closed';
$string['intervention_status_contacted'] = 'Contacted';
$string['intervention_status_inprogress'] = 'In Progress';
$string['intervention_status_monitoring'] = 'Monitoring';
$string['intervention_status_new'] = 'New';
$string['intervention_status_resolved'] = 'Resolved';
$string['intervention_unassigned'] = '— Unassigned —';
$string['intervention_update_heading'] = 'Update Case';
$string['intervention_updated'] = 'Intervention updated.';
$string['intervention_view'] = 'View';
$string['kpi_activeusers'] = 'Active Users (30 days)';
$string['kpi_activitycompletions'] = 'Activity Completions';
$string['kpi_coursecompletions'] = 'Course Completions';
$string['kpi_enrolments'] = 'Active Enrolments';
$string['kpi_newusers'] = 'New Registrations (30 days)';
$string['kpi_totalcourses'] = 'Total Courses';
$string['lastactivity'] = 'Last student activity';
$string['lastactivitylabel'] = 'Last activity';
$string['leaderboard'] = 'Top Students by Grade';
$string['leaderboard_nodata'] = 'No grade data available for this course.';
$string['license_activated'] = 'Licence activated successfully.';
$string['license_domain'] = 'Domain';
$string['license_expires'] = 'Expires';
$string['license_grace_warning'] = 'Your Course Insights licence has expired. The plugin will stop working in {$a} days. Please renew through the channel where you purchased the plugin.';
$string['license_invalid_key'] = 'Invalid licence key. Please check and try again.';
$string['license_local_note'] = 'Activated locally — contact support if this persists.';
$string['license_plan'] = 'Plan';
$string['license_required'] = 'A valid Course Insights licence is required. Purchase the plugin on Moodle Marketplace and enter your licence key in Site administration → Local plugins → Course Insights settings.';
$string['license_server_unreachable'] = 'Could not reach the licence server. Please check your internet connection and try again.';
$string['license_status_expired'] = 'Licence expired';
$string['license_status_grace'] = 'Licence expired — grace period active';
$string['license_status_unlicensed'] = 'No licence';
$string['license_status_valid'] = 'Licence valid';
$string['license_trial_local'] = 'Licence key saved (server unreachable — local fallback active).';
$string['marketplace_notice'] = 'A licence key is required to unlock all features. Course Insights is purchased through Moodle Marketplace; licence keys are issued after the Marketplace purchase has been verified. By installing and using this plugin you agree to our Terms & Conditions and Privacy Policy.';
$string['marketplace_privacy_link'] = 'Privacy Policy';
$string['marketplace_terms_link'] = 'Terms & Conditions';
$string['messageprovider:alert'] = 'Course health alerts';
$string['messageprovider:digest'] = 'Course Insights digest';
$string['messageprovider:followup_reminder'] = 'Intervention follow-up reminder';
$string['messageprovider:intervention_contact'] = 'Student contact messages sent from Course Insights intervention cases';
$string['messageprovider:student_reminder'] = 'Student inactivity reminders';
$string['miniexamkeywords'] = 'Mini exam keywords';
$string['miniexamkeywords_default'] = 'mini,mini exam';
$string['miniexamkeywords_desc'] = 'Comma-separated words used to identify mini exams from quiz names. Example: mini,mini exam';
$string['miniquizattempts'] = 'Students with completed mini exams';
$string['miniquizzes'] = 'Mini exams';
$string['modulefunnel'] = 'Activity Completion Funnel';
$string['modulefunnel_nodata'] = 'No activities with completion tracking are configured in this course.';
$string['monthly_col_activeusers'] = 'Active Users';
$string['monthly_col_events'] = 'Total Events';
$string['monthly_col_month'] = 'Month';
$string['monthly_trend_label'] = 'Monthly Activity Trend (12 months)';
$string['monthlytrendmonths'] = 'Monthly trend window';
$string['monthlytrendmonths_12'] = '12 months';
$string['monthlytrendmonths_3'] = '3 months';
$string['monthlytrendmonths_6'] = '6 months';
$string['monthlytrendmonths_9'] = '9 months';
$string['monthlytrendmonths_desc'] = 'Number of months to include in the site monthly active user trend. Larger values require more database time during the nightly rebuild task.';
$string['msgtemplatesheading'] = 'Message templates';
$string['msgtemplatesheading_desc'] = 'Customise the templates used when messaging students from an intervention case. Available placeholders: {firstname}, {lastname}, {course}, {adviser}.';
$string['mycases_duesoon'] = 'Due soon';
$string['mycases_empty'] = 'No cases are currently assigned to you.';
$string['mycases_heading'] = 'My Cases';
$string['mycases_overdue'] = 'Overdue';
$string['mycases_tab_all'] = 'All Cases';
$string['mycases_tab_mine'] = 'My Cases';
$string['norecords'] = 'No courses found matching the selected filters.';
$string['pagination_info'] = 'Showing {$a->from} to {$a->to} of {$a->total} courses';
$string['pluginname'] = 'Course Insights';
$string['presetdelete'] = 'Delete';
$string['presetname'] = 'Preset name';
$string['presets'] = 'Saved presets';
$string['presetsave'] = 'Save preset';
$string['printreport'] = 'Print / PDF';
$string['privacy:atrisk'] = 'At-risk student snapshot';
$string['privacy:intervention_notes'] = 'Intervention case notes';
$string['privacy:interventions'] = 'Student intervention cases';
$string['privacy:metadata'] = 'The Course Insights plugin reads existing Moodle data and stores course-level summary data plus student reminder tracking records.';
$string['privacy:metadata:atrisk'] = 'Stores an overnight snapshot of students marked at risk for the Site Overview page.';
$string['privacy:metadata:atrisk:courseid'] = 'The course ID for the at-risk snapshot row.';
$string['privacy:metadata:atrisk:daysinactive'] = 'The calculated number of inactive days.';
$string['privacy:metadata:atrisk:lastaccess'] = 'The last recorded course access time.';
$string['privacy:metadata:atrisk:threshold'] = 'The inactivity threshold used when building the snapshot.';
$string['privacy:metadata:atrisk:timemodified'] = 'The time when the at-risk snapshot row was rebuilt.';
$string['privacy:metadata:atrisk:userid'] = 'The user ID of the student in the at-risk snapshot.';
$string['privacy:metadata:intervention_notes'] = 'Course Insights stores notes written by staff on intervention cases.';
$string['privacy:metadata:intervention_notes:isprivate'] = 'Whether this note is private (managers only).';
$string['privacy:metadata:intervention_notes:note'] = 'The text content of the note.';
$string['privacy:metadata:intervention_notes:timecreated'] = 'When the note was written.';
$string['privacy:metadata:intervention_notes:userid'] = 'The staff member who wrote the note.';
$string['privacy:metadata:interventions'] = 'Course Insights stores intervention cases created by staff for at-risk students.';
$string['privacy:metadata:interventions:courseid'] = 'The course this intervention relates to.';
$string['privacy:metadata:interventions:createdby'] = 'The staff member who created this case.';
$string['privacy:metadata:interventions:riskscore'] = 'The risk score at the time of case creation.';
$string['privacy:metadata:interventions:status'] = 'The current workflow status of the case.';
$string['privacy:metadata:interventions:timecreated'] = 'When the case was created.';
$string['privacy:metadata:interventions:title'] = 'The intervention case title.';
$string['privacy:metadata:interventions:userid'] = 'The student user ID this intervention concerns.';
$string['privacy:metadata:reminders'] = 'Tracks student inactivity reminder emails sent by Course Insights.';
$string['privacy:metadata:reminders:courseid'] = 'The course ID for the reminder.';
$string['privacy:metadata:reminders:timereminded'] = 'The time when the reminder was sent.';
$string['privacy:metadata:reminders:userid'] = 'The user ID of the student who received the reminder.';
$string['privacy:metadata:risk_rules'] = 'Stores administrator-configured risk rules. Contains no personal data.';
$string['privacy:metadata:risk_scores'] = 'Stores computed risk scores for students in each enrolled course.';
$string['privacy:metadata:risk_scores:courseid'] = 'The course ID for the risk score.';
$string['privacy:metadata:risk_scores:reasons'] = 'JSON-encoded list of triggered risk rule reasons.';
$string['privacy:metadata:risk_scores:risklevel'] = 'The computed risk level (low, medium, high, or critical).';
$string['privacy:metadata:risk_scores:score'] = 'The computed risk score (0–100).';
$string['privacy:metadata:risk_scores:timecalculated'] = 'When the score was last computed.';
$string['privacy:metadata:risk_scores:userid'] = 'The user ID of the student.';
$string['privacy:metadata:summary'] = 'Caches aggregated course metrics and the full names of editing teachers for the course overview list.';
$string['privacy:metadata:summary:teachers'] = 'Cached full names of editing teachers enrolled in the course, derived from Moodle user profiles.';
$string['privacy:reminders'] = 'Student inactivity reminders';
$string['privacy:risk_scores'] = 'Student risk scores';
$string['privacy:summary'] = 'Course summary cache';
$string['quizattempts'] = 'Students with completed quiz attempts';
$string['quizbreakdown'] = 'Quiz Score Breakdown';
$string['quizbreakdown_nodata'] = 'No quiz grade data available for this course.';
$string['quizzes'] = 'Quizzes / exams';
$string['reports_bystatus_heading'] = 'Cases by Status';
$string['reports_col_active'] = 'Active';
$string['reports_col_avgdays'] = 'Avg. Days';
$string['reports_col_count'] = 'Count';
$string['reports_col_resolved'] = 'Resolved / Closed';
$string['reports_col_staffname'] = 'Staff Member';
$string['reports_col_total'] = 'Total';
$string['reports_heading'] = 'Intervention Reports';
$string['reports_nodata'] = 'No intervention data for the selected period.';
$string['reports_period_30'] = 'Last 30 days';
$string['reports_period_365'] = 'Last 365 days';
$string['reports_period_90'] = 'Last 90 days';
$string['reports_period_all'] = 'All time';
$string['reports_staff_heading'] = 'Staff Caseload';
$string['reports_stat_avgdays'] = 'Avg. Days to Resolve';
$string['reports_stat_open'] = 'Open Cases';
$string['reports_stat_rate'] = 'Resolution Rate';
$string['reports_stat_resolved'] = 'Resolved / Closed';
$string['reports_stat_total'] = 'Total Cases';
$string['resetfilters'] = 'Reset all filters';
$string['risk_level_critical'] = 'Critical';
$string['risk_level_high'] = 'High';
$string['risk_level_low'] = 'Low';
$string['risk_level_medium'] = 'Medium';
$string['risk_reason_completion_below'] = 'Course completion below {$a}%';
$string['risk_reason_grade_below'] = 'Grade average below {$a}%';
$string['risk_reason_inactivity_days'] = 'No login for {$a} or more days';
$string['risk_reason_missed_assignments'] = '{$a} or more assignments not submitted';
$string['risk_reason_no_course_activity'] = 'No course activity for {$a} or more days';
$string['risk_rule_completion_below'] = 'Completion below (%)';
$string['risk_rule_grade_below'] = 'Grade below (%)';
$string['risk_rule_inactivity_days'] = 'No login (days)';
$string['risk_rule_missed_assignments'] = 'Missed assignments';
$string['risk_rule_no_course_activity'] = 'No course activity (days)';
$string['risk_rules_desc'] = 'Configure the threshold and point value for each risk indicator. Points from triggered rules are summed and capped at 100 to produce the final risk score.';
$string['risk_rules_enabled'] = 'Enabled';
$string['risk_rules_heading'] = 'Risk Rules';
$string['risk_rules_label'] = 'Rule';
$string['risk_rules_levelguide'] = 'Risk Level Guide';
$string['risk_rules_nodata'] = 'No risk rules found. Run the plugin upgrade to seed the defaults.';
$string['risk_rules_saved'] = 'Risk rules updated.';
$string['risk_rules_threshold'] = 'Threshold';
$string['risk_rules_weight'] = 'Points';
$string['risk_score'] = 'Risk Score';
$string['sendwebhook'] = 'Course Insights: Push webhook';
$string['settings_license'] = 'Licence';
$string['settings_license_desc'] = 'Enter the licence key issued after Moodle Marketplace purchase verification. The key is validated by the Course Insights licence service and tied to this site\'s domain.';
$string['settings_license_key'] = 'Licence key';
$string['settings_license_key_desc'] = 'Paste the key exactly as received. Saving this field triggers immediate activation.';
$string['settingspage'] = 'Course Insights settings';
$string['sitecachebuilt'] = 'Course Insights site overview snapshot has been rebuilt.';
$string['sitecachepending'] = 'The Site Overview snapshot has not been built yet. It will be generated by the overnight Course Insights summary cache task.';
$string['startdate'] = 'Main period start';
$string['stat_attempts'] = 'Attempts';
$string['stat_courses'] = 'Courses';
$string['stat_students'] = 'Students';
$string['stat_submissions'] = 'Submissions';
$string['studentactivitytable'] = 'Student Activity';
$string['studentactivitytable_nodata'] = 'No students enrolled in this course.';
$string['studentinactivitydays'] = 'Student inactivity threshold (days)';
$string['studentinactivitydays_desc'] = 'Send a reminder to students who have not accessed an enrolled course for this many days. The same student+course pair will not be reminded again until another full period passes. Set to 0 to disable.';
$string['studentreminder_body'] = 'Hi {$a->firstname},

We noticed you haven\'t visited the following course(s) in the last {$a->inactivedays} days:

{$a->courselist}

Jump back in and keep up the great work!

{$a->siteurl}';
$string['studentreminder_html_cta'] = 'Jump back in and keep up the great work!';
$string['studentreminder_html_intro'] = 'Hi {$a->firstname}, we noticed you haven\'t visited the following course(s) in the last {$a->inactivedays} days:';
$string['studentreminder_subject'] = 'Don\'t forget to continue your learning!';
$string['studentreminderenabled'] = 'Enable student inactivity reminders';
$string['studentreminderenabled_desc'] = 'When enabled, the nightly task will email students who have not accessed an enrolled course for the configured number of days.';
$string['studentreminderheading'] = 'Student inactivity reminders';
$string['studentroleids'] = 'Student role IDs';
$string['studentroleids_default'] = '5,11,25';
$string['studentroleids_desc'] = 'Comma-separated Moodle role IDs to include in reports. Example: 5,11,25';
$string['studentstatus'] = 'Student status';
$string['studentstatus_active'] = 'Active students';
$string['studentstatus_all'] = 'All students';
$string['studentstatus_suspended'] = 'Suspended students';
$string['submissiontimeline'] = 'Assignment Submission Timeline';
$string['submissiontimeline_nodata'] = 'No assignment submissions in the last 30 days.';
$string['submittedassignments'] = 'Students with assignment submissions';
$string['tab_dashboard'] = 'Dashboard';
$string['tab_interventionreports'] = 'Reports';
$string['tab_interventions'] = 'Interventions';
$string['tab_riskrules'] = 'Risk Rules';
$string['tab_sitekpis'] = 'Site Overview';
$string['tab_taskstatus'] = 'Task Status';
$string['tab_userreport'] = 'User Report';
$string['task_followup_reminder'] = 'Course Insights: send follow-up reminders for overdue interventions';
$string['task_renew_license'] = 'Course Insights: renew licence token';
$string['task_send_alerts'] = 'Send Course Insights alerts';
$string['task_send_digest'] = 'Send Course Insights digest email';
$string['task_send_student_reminders'] = 'Course Insights: send student inactivity reminders';
$string['taskstatus_adhoc_empty'] = 'No Course Insights ad-hoc tasks in the queue.';
$string['taskstatus_adhoc_heading'] = 'Ad-hoc task queue';
$string['taskstatus_disabled'] = 'Disabled';
$string['taskstatus_failed'] = 'Failed';
$string['taskstatus_lastrun'] = 'Last run';
$string['taskstatus_nextrun'] = 'Next run';
$string['taskstatus_none'] = 'No scheduled tasks found for this plugin.';
$string['taskstatus_ok'] = 'OK';
$string['taskstatus_oldest'] = 'Oldest item';
$string['taskstatus_queued'] = 'Queued';
$string['taskstatus_rollup_last'] = 'Rollup latest day';
$string['taskstatus_rollup_rows'] = 'Rollup rows total';
$string['taskstatus_scheduled_heading'] = 'Scheduled tasks';
$string['taskstatus_snap_newest'] = 'Newest snapshot';
$string['taskstatus_snap_stale'] = 'Stale (>24 h)';
$string['taskstatus_snap_total'] = 'Snapshots built';
$string['taskstatus_snapshot_heading'] = 'Snapshot & rollup';
$string['taskstatus_status'] = 'Status';
$string['taskstatus_taskname'] = 'Task';
$string['teachers'] = 'Teachers';
$string['tmpl1_body'] = 'Initial contact — message';
$string['tmpl1_body_default'] = 'Dear {firstname},

I am writing to check in with you about your progress in {course}. I noticed you may benefit from some additional support.

Please do not hesitate to get in touch if there is anything I can help with.

Kind regards,
{adviser}';
$string['tmpl1_body_desc'] = 'Body text for the initial contact message.';
$string['tmpl1_subject'] = 'Initial contact — subject';
$string['tmpl1_subject_default'] = 'Checking in about your progress in {course}';
$string['tmpl1_subject_desc'] = 'Subject line for the initial contact message.';
$string['tmpl2_body'] = 'Follow-up — message';
$string['tmpl2_body_default'] = 'Dear {firstname},

I am following up on my earlier message regarding your progress in {course}. I would welcome the opportunity to discuss how we can support you.

Please feel free to contact me at your earliest convenience.

Kind regards,
{adviser}';
$string['tmpl2_body_desc'] = 'Body text for the follow-up message.';
$string['tmpl2_subject'] = 'Follow-up — subject';
$string['tmpl2_subject_default'] = 'Follow-up: your progress in {course}';
$string['tmpl2_subject_desc'] = 'Subject line for the follow-up message.';
$string['top10_completion'] = 'Top 10 by Completion Rate';
$string['top10_enrolment'] = 'Top 10 by Enrolment';
$string['totalassignments'] = 'Total Assignments';
$string['totalquizzes'] = 'Total Quizzes';
$string['trend_active'] = 'Active students';
$string['trend_heading'] = '30-day Trend Comparison';
$string['trend_quiz'] = 'Quiz attempts';
$string['trend_subs'] = 'Submissions';
$string['trend_vs'] = 'vs previous period';
$string['usecache'] = 'Use summary cache where possible';
$string['userreport_col_course'] = 'Course';
$string['userreport_col_grade'] = 'Grade';
$string['userreport_col_lastaccess'] = 'Last Access';
$string['userreport_col_status'] = 'Status';
$string['userreport_heading'] = 'User Progress Report';
$string['userreport_nodata'] = 'No enrolled courses found for this user.';
$string['userreport_noselection'] = 'Select a user...';
$string['userreport_searchlabel'] = 'Search user';
$string['userreport_searchplaceholder'] = 'Type a name or email address...';
$string['userreport_selectprompt'] = 'Search for a user above to view their course progress.';
$string['userstatus_completed'] = 'Completed';
$string['userstatus_inprogress'] = 'In progress';
$string['userstatus_notstarted'] = 'Not started';
$string['webhookapikey'] = 'Webhook API key';
$string['webhookapikey_desc'] = 'Optional Bearer token sent in the Authorization header. Leave blank to send unauthenticated requests.';
$string['webhookfailed'] = 'Webhook failed (HTTP {$a->httpcode}): {$a->error}';
$string['webhookheading'] = 'LMS data push (webhook)';
$string['webhooksent'] = 'Webhook sent successfully (HTTP {$a}).';
$string['webhookskipped'] = 'Webhook skipped: no URL configured.';
$string['webhookurl'] = 'Webhook URL';
$string['webhookurl_desc'] = 'URL to receive a POST request with JSON course overview data after each nightly cache rebuild. Leave blank to disable.';
