Submission Report

Submission sub_5b735eb2214745febf34 is currently succeeded.

Source: tandreigaabriel/moodle-local_courseinsights
Support the reviewer

Plugin reviews take server time and ongoing maintenance. The service stays free, but small donations help keep it running and improve the checks.
Support on Ko-fi
Plugin Review Report: courseinsights

    Plugin type: local

Summary

Found 5 issue(s): 2 HIGH, 3 MEDIUM. Focus on HIGH priority issues first.
Category Statistics
Category Total Occurrences Blocker High Medium Low Distinct Issues
repository_setup 2 0 0 2 0 1
security 2 0 2 0 0 1
internationalization 1 0 0 1 0 1
Grouped Findings
[SEC011] PARAM_RAW Security Risk

    Severity: HIGH
    Category: security
    Occurrences: 2
    Files: classes/report_service.php
    Lines: 149, 150
    Recommendation: Plugin uses PARAM_RAW for parameter validation. PARAM_RAW should only be used when there is no other option and when strict validation/cleaning is performed elsewhere. In most cases, use specific parameter types (for example: PARAM_INT for integers, PARAM_TEXT for plain text, PARAM_ALPHA/PARAM_ALPHANUM for constrained formats) to reduce XSS and injection risk.
    Documentation:
        Security - Don't Trust User Input
    Real code excerpts:
        Example 1:
            File: classes/report_service.php
            Line: 149

147: 'courseid' => optional_param('courseid', 0, PARAM_INT),
148: 'categoryid' => optional_param('categoryid', 0, PARAM_INT),
149: 'startdate' => optional_param('startdate', '', PARAM_RAW_TRIMMED),
150: 'enddate' => optional_param('enddate', '', PARAM_RAW_TRIMMED),
151: 'activitytype' => optional_param('activitytype', 'all', PARAM_ALPHA),

[FILE003] Missing File Boilerplate Headers

    Severity: MEDIUM
    Category: repository_setup
    Occurrences: 2
    Files: styles.css, templates/dashboard.mustache
    Recommendation: Plugin source files (PHP, templates, CSS, JS including AMD source) are missing Moodle boilerplate license/header markers expected for plugin submissions.
    How to fix:
        Add the standard Moodle boilerplate header at the top of each flagged source file.
        When the header is fully absent, copy a complete boilerplate block instead of patching individual tags.
        For all source files, include explicit @copyright and GPL @license markers.
    Documentation:
        Boilerplate Header Requirements
    Real code excerpts:
        Example 1:
            File: styles.css

Missing required markers: This file is part of Moodle banner, Moodle is free software preamble

    Example 2:
        File: templates/dashboard.mustache

Missing standard Moodle boilerplate header; add the full boilerplate block.

[I18N001] Hard-coded language strings

    Severity: MEDIUM
    Category: internationalization
    Occurrences: 1
    Files: settings.php
    Lines: 44
    Recommendation: User-facing text is hard-coded in the source code instead of using Moodle's string API (get_string()). This prevents translation.
    Documentation:
        Language Strings - Plugin Contribution Checklist
    Real code excerpts:
        Example 1:
            File: settings.php
            Line: 44

43: get_string('miniexamkeywords_desc', 'local_courseinsights'),
44: 'mini,mini exam',
45: PARAM_TEXT

Prioritized Recommendations

    Address HIGH issues next, especially API/security compliance findings.
    Use documentation links in each issue group for implementation-ready fix guidance.
