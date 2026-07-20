# Course Insights for Moodle

`local_courseinsights` — Developed by TAG Web Design

---

## What is Course Insights?

**Course Insights** gives Moodle administrators and managers a single place to see how every course on their site is performing, identify at-risk students, and track intervention cases — without digging through individual gradebooks or running manual reports.

Install it once, point your browser to **Site administration → Reports → Course Insights**, and immediately see enrolment numbers, submission rates, quiz activity, student health scores, and last activity dates across all your courses — filtered, sorted, and ready to export. A configurable risk scoring engine flags students who are falling behind, and a built-in intervention tracker lets staff open, manage, and resolve cases with a full audit trail.

After licence activation, the core reports work without changing student-facing pages. Optional features such as reminders, alerts, digest emails, branding, and webhooks can be enabled from the plugin settings.

---

## Features

### Dashboard

- **Course cards** — each course shows completion rate, enrolled students, teachers, assignments, last student activity, and an Active / Inactive badge at a glance
- **Health score** — every course is graded A to F based on completion rate, recent activity, and engagement; spot struggling courses instantly
- **Filter by** category, course, cohort, date range, activity type (assignments / quizzes / exams), and student status — all filters update the dashboard instantly via AJAX
- **Filter presets** — save your most-used filter combinations and restore them in one click
- **Compare period** — optionally compare the main date range against a separate baseline date range; trend arrows show whether submissions and attempts are improving or declining
- **Bar chart overview** — visual comparison of activity across your top courses
- **Pagination** — handles hundreds of courses without slowing down

### Per-course detail page

Click "Detailed Report" on any course card to open a full breakdown:

- Completion rate hero with enrolled students and teachers
- **Grade distribution** histogram across all assignments and quizzes
- **52-week engagement heatmap** — see when students are active throughout the year
- **Student activity table** — per-student last access, course visits, forum posts, submissions, and quiz attempts
- **Submission timeline** — 30-day daily submission bar chart
- **Quiz score breakdown** — average, min, max, and pass rate per quiz
- **Print / PDF view** — print-ready layout with one click
- **Cached aggregate charts** — grade distribution, heatmap, submission timeline, quiz breakdown, trend, and completion funnel are rebuilt by the scheduled cache task so course detail pages load faster on large sites

### Exports

- **CSV export** — download the full filtered dataset
- **xlsx export** — download as an Excel spreadsheet

### Automated alerts

- Get notified when a course falls below a completion threshold or goes inactive for too many days
- Alerts are sent to course editing teachers via Moodle messaging
- Configurable thresholds; enabled per site

### Student inactivity reminders

- Nightly reminder task can email students who have not accessed an enrolled, incomplete course for the configured number of days
- One email is sent per student and can list several inactive courses
- Reminder tracking prevents the same student/course reminder being sent again until another full inactivity period has passed

### Digest emails

- Weekly or monthly summary emails listing all courses with their health score, completion rate, and submission counts
- Sent automatically to site managers

### REST API & webhook

- **REST API** — query course data programmatically via Moodle's external functions
- **Webhook** — push nightly course data to any external LMS, analytics platform, or dashboard as JSON

### White-label branding

- Add your institution's name, logo, and accent colour to replace the default Course Insights header on every page

### Student risk scoring

- **Configurable rules** — enable or disable individual risk rules (inactivity, low quiz scores, low submission rate, low completion, never accessed) and set the threshold and weight for each
- **Composite risk score** — a 0–100 score is calculated nightly per student per course from the weighted rules; students are banded into Low (0–29), Medium (30–59), High (60–79), and Critical (80–100) risk levels
- **Risk badges** — colour-coded badges appear on the at-risk student list and on intervention detail pages so risk level is visible at a glance
- **Risk Rules admin page** — managers can adjust thresholds and weights without touching code; a level guide shows the score ranges for each level
- **Nightly recalculation** — the existing cache task rebuilds all risk scores so the data is always fresh when staff arrive in the morning

### Intervention case management

- **Create interventions** from any at-risk student row — one click opens a new case pre-filled with the student, course, and current risk score
- **Case list** — filter open interventions by status, course, or time period; status badges make progress visible at a glance
- **Status workflow** — cases move through New → In Progress → On Hold → Resolved → Closed; managers can update status and assign cases to other staff members
- **Follow-up dates** — set a target follow-up date on any case; the scheduled task sends a Moodle notification to the assignee (or creator) when the date arrives
- **Notes timeline** — add timestamped notes to any case; managers can mark notes private so only users with the `viewprivatenotes` capability can see them
- **Manager dashboard** — the Interventions tab shows all cases site-wide to managers; teaching staff see only cases they created or are assigned to
- **Intervention Reports** — a manager-only reports page shows resolution rates, average days to resolve, cases by status, and a per-staff caseload breakdown, filterable by last 30, 90, or 365 days or all time
- **Adviser caseload ("My Cases")** — staff see a personal view of only their assigned or created cases; cases are sorted by urgency (overdue follow-ups first, then due within 7 days, then active, then no date set); overdue and due-soon badges are shown inline
- **Student Engagement Since Intervention** — every case detail page shows a before/after comparison of course visits, forum posts, assignment submissions, and quiz attempts for the 30 days before the case was opened vs. since it was opened; trend badges (↑ Improving / ↓ Declining / → Stable) use per-day rates so short windows compare fairly; visible after the case is 3 days old
- **Send Message to Student** — send a Moodle message directly to the student from inside any intervention case; choose from two configurable templates (Initial Contact and Follow-up) with `{firstname}`, `{lastname}`, `{course}`, and `{adviser}` placeholders; the send is automatically logged as a note on the case timeline
- **Message Templates page** — admins can customise the Initial Contact and Follow-up template subject and body from a dedicated tab bar page; changes apply immediately site-wide
- **Setup Guide** — a built-in 5-step onboarding guide for administrators covering scheduled task setup, plugin settings, risk rules, message templates, and starting interventions; accessible from the tab bar

---

## Requirements

- Moodle 4.5 or later
- PHP 8.1 or later
- A Moodle-supported database engine, including MariaDB, MySQL, or PostgreSQL

---

## Licensing

Course Insights is a **commercial plugin** distributed under the GNU GPL v3.

A licence key is required to use the plugin. Course Insights is purchased through Moodle Marketplace; licence keys are issued after the Marketplace purchase has been verified.

After purchasing through Moodle Marketplace, your licence key will be delivered to the email address used at checkout. Enter the key in **Site administration → Plugins → Local plugins → Course Insights settings → Licence Key** and save. Activation is automatic; no further steps are needed.

---

## Installation

### Option A — Moodle admin interface

1. Download the plugin ZIP.
2. Log in as site administrator.
3. Go to **Site administration → Plugins → Install plugins**.
4. Upload the ZIP and follow the prompts.

### Option B — Manual

1. Extract the ZIP and copy the `courseinsights` folder to:
   ```
   /path/to/moodle/local/courseinsights/
   ```
2. Log in as site administrator.
3. Go to **Site administration → Notifications** and click **Upgrade Moodle database now**.

---

## Access

After installation the dashboard is available from:

**Site administration → Reports → Course Insights**

For a shorter product overview, see [about.md](about.md).

---

## How the plugin works

Course Insights reads existing Moodle course, enrolment, activity, completion, grade, and access-log data. It does not change courses, enrolments, grades, or student activity records.

### Scheduled processing

The plugin uses Moodle scheduled tasks to keep heavy reports fast:

| Task | Default time | What it does |
|---|---:|---|
| Build Course Insights summary cache | 02:00 daily | Rebuilds dashboard summaries, Site Overview snapshots, at-risk student snapshots, and aggregate Detailed Report snapshots |
| Renew licence token | 03:00 Monday | Refreshes the local licence status from the licence service |
| Send course alerts | 07:00 daily | Sends alerts to editing teachers when configured thresholds are breached |
| Send digest email | 08:00 daily | Checks whether weekly/monthly digest emails are due |
| Send student inactivity reminders | 09:00 daily | Emails inactive students when enabled |

These tasks run when Moodle cron runs. If Moodle cron is not running, caches and emails will not update.

### Cache and snapshot tables

The cache task is designed to be repeatable. Running it every night does not duplicate normal report rows:

| Table | Purpose | Duplicate protection |
|---|---|---|
| `local_courseinsights_summary` | Dashboard all-time course summaries | The task deletes and rebuilds the all-time summary set |
| `local_courseinsights_site` | Site Overview KPI/chart payloads | One row per snapshot key; updated in place |
| `local_courseinsights_atrisk` | At-risk student snapshot for the configured inactivity threshold | The task deletes and rebuilds rows for that threshold |
| `local_courseinsights_detail` | Aggregate Detailed Report chart payloads | One row per course; updated in place |
| `local_courseinsights_reminders` | Student reminder send history | Unique student+course row; updated after later reminders |

The Detailed Report cache stores aggregate chart data only. Student Activity and Top Students by Grade are still read live because they display identifiable student information.

### At-risk students

The Site Overview at-risk table lists active, enrolled, incomplete students whose course access is older than the configured Student inactivity threshold. If a student has never accessed the course, the plugin displays `Never` for last access and calculates days inactive from the enrolment time.

### Student reminder emails

Student reminders work like this:

1. Enable **Student inactivity reminders** in Course Insights settings.
2. Set **Student inactivity threshold (days)**.
3. Moodle cron runs the `send_student_reminders` scheduled task.
4. The task finds active, confirmed, unsuspended students enrolled in visible, incomplete courses.
5. A student is included when their last course access is older than the threshold, or they have never accessed the course.
6. The task sends one Moodle notification/email per student with links to the inactive course(s).
7. The plugin records the send in `local_courseinsights_reminders` so the same student/course is not reminded again until another full threshold period has passed.

Example: if the threshold is 14 days and a reminder is sent today, the same student/course will not be reminded again until at least 14 more days have passed.

---

## Capabilities

| Capability | Default roles | Description |
|---|---|---|
| `local/courseinsights:view` | Manager, Editing teacher | View the dashboard and course detail pages |
| `local/courseinsights:export` | Manager, Editing teacher | Export reports as CSV or xlsx |
| `local/courseinsights:manage` | Manager | Manage plugin settings and risk rules |
| `local/courseinsights:viewrisk` | Manager, Editing teacher | See risk score and risk level badges |
| `local/courseinsights:createintervention` | Manager, Editing teacher | Create and view intervention cases |
| `local/courseinsights:manageinterventions` | Manager | Update case status, assign cases, and access the Intervention Reports page |
| `local/courseinsights:viewprivatenotes` | Manager | Read and write private notes on intervention cases |

---

## Configuration

All settings are at **Site administration → Plugins → Local plugins → Course Insights settings**.

| Setting | Default | Purpose |
|---|---|---|
| Licence Key | — | Enter the licence key issued after Moodle Marketplace purchase verification |
| Mini exam keywords | `mini,mini exam` | Keywords used to identify mini exams from quiz names |
| Exam keywords | `exam,final` | Keywords used to identify exams from quiz names |
| Student role IDs | `5,11,25` | Comma-separated Moodle role IDs counted as students |
| Enable summary cache | Off | Pre-aggregate data nightly for faster loading on large sites |
| Alerts enabled | Off | Enable automated low-engagement alerts to course teachers |
| Alert completion threshold | 50 | Alert when course completion drops below this % |
| Alert inactive days | 30 | Alert when no student activity for this many days |
| Student inactivity reminders | Off | Email inactive students from the scheduled reminder task |
| Student inactivity threshold | 14 | Number of inactive days used by student reminders and the Site Overview at-risk snapshot |
| Digest emails | Off | Send weekly or monthly summary emails to managers |
| Webhook URL | — | POST course data nightly to an external URL |
| Initial contact — subject | `Checking in about your progress in {course}` | Subject for the initial contact message template |
| Initial contact — message | *(default body)* | Body for the initial contact message template; supports `{firstname}`, `{lastname}`, `{course}`, `{adviser}` |
| Follow-up — subject | `Follow-up: your progress in {course}` | Subject for the follow-up message template |
| Follow-up — message | *(default body)* | Body for the follow-up message template |

---

## Troubleshooting

**Dashboard not visible**
Make sure the plugin is installed and the user has `local/courseinsights:view`. Purge all caches after installation.

**No data shown**
Check that the Student role IDs setting matches the role IDs on your site. The default (`5`) is the standard Student role but may differ on your installation.

**Licence not activating**
Make sure your server can make outbound HTTPS requests to `tandreig.com`. Check **Site administration → Server → HTTP** for any proxy settings that may be blocking outbound connections.

If the key was requested but has not arrived, use the post-purchase licence delivery form at [tandreig.com/request-key](https://tandreig.com/request-key) and include the same email address and Moodle site URL used for the Marketplace purchase verification.

For installation, configuration, or plugin usage help, use the plugin support form at [tandreig.com/plugins/support](https://tandreig.com/plugins/support).

---

## Privacy

Course Insights reads existing Moodle data (courses, enrolments, assignments, quizzes, grades, completions, and access logs). It stores aggregate course/report snapshots for performance.

The plugin also stores limited personal tracking data for:

- student inactivity reminder history (`userid`, `courseid`, `timereminded`)
- Site Overview at-risk snapshots (`userid`, `courseid`, inactivity threshold, last access time, calculated inactive days)

These records are used to prevent duplicate reminders and to make the Site Overview load quickly. See [PRIVACY.md](PRIVACY.md) or the plugin's privacy provider for full details.

---

## Moodle review notes addressed

The plugin has been adjusted for Moodle review feedback:

- Log aggregation avoids MySQL-only date functions and groups bounded timestamp rows in PHP or uses scheduled snapshots.
- Site Overview and Detailed Report heavy views use scheduled snapshot tables so large sites do not run the most expensive reports on every page load.
- Licence wording is Marketplace-first; the external form is documented only as post-purchase licence delivery and verification.
- The admin licence notice uses language strings for visible text and no longer links to an off-Marketplace purchase/request page.
- Licence activation uses Moodle's `\curl` wrapper instead of direct `curl_init()`.
- The Privacy API declares and exports/deletes reminder and at-risk student snapshot records.
- Alert recipients are preloaded for affected courses to avoid querying teachers inside the per-course send loop.
- Cache and message-provider language strings are defined in `lang/en/local_courseinsights.php`.

---

## Support

- **Plugin support form:** [tandreig.com/plugins/support](https://tandreig.com/plugins/support)
- **Licence key delivery form:** [tandreig.com/request-key](https://tandreig.com/request-key) — use this only after a Moodle Marketplace purchase so the purchase can be verified and matched to the Moodle site URL
- **Bug reports & feature requests:** [github.com/tandreigaabriel/moodle-local_courseinsights/issues](https://github.com/tandreigaabriel/moodle-local_courseinsights/issues)
- **Developed and maintained by:** TAG Web Design — Andrei Toma

---

## License

GNU General Public License v3 or later — see [LICENSE](LICENSE) or [gnu.org/licenses/gpl-3.0.html](https://www.gnu.org/licenses/gpl-3.0.html)

---

## Changelog

### 1.0.0
- Version milestone: first stable 1.0 release after 65 iterations of development, testing, and refinement

### 0.65.0
- Fixed: intervention messages now delivered correctly to students — the `intervention_contact` message provider had a staff-only capability restriction that silently blocked student receipt; removed so any authenticated user can receive messages from staff
- Fixed: error notice now shown when message delivery fails, with guidance to check site messaging settings
- Improved: staff-to-student messages now use system notifications so delivery is not affected by site messaging policies or user block lists

### 0.64.0
- Message Templates management page: admins can edit the subject and body of the Initial Contact and Follow-up templates from a dedicated tab bar page (`message_templates.php`) instead of navigating to plugin settings; changes apply to all staff immediately
- Setup Guide page: a 5-step admin onboarding guide (`help.php`) is now accessible from the tab bar; covers running the cache task, reviewing settings, configuring risk rules, customising message templates, and starting interventions
- Both pages are accessible from the tab bar on every plugin page (requires `manage` capability)

### 0.63.0
- Site admins are now included in the "Assign To" dropdown on intervention case detail pages; previously only users with `createintervention` capability appeared due to `get_users_by_capability()` silently excluding admin bypass users

### 0.60.4
- Schema fix: removed invalid empty-string DEFAULT from CHAR NOT NULL columns (`ruletype`, `label`, `title`) that triggered XMLDB debug warnings during upgrade and uninstall

### 0.60.3
- Performance: composite index `(courseid, timecreated)` added to `mdl_logstore_standard_log`; reduces the nightly cache build task from 80+ minutes to seconds on sites with large logstore tables

### 0.60.2
- Performance: 52-week engagement heatmap now reads from a pre-aggregated daily rollup table (`local_courseinsights_log_rollup`) instead of scanning logstore directly; heatmap data is built incrementally by the nightly cache task

### 0.57.0
- Student Engagement Since Intervention: every intervention case detail page now shows a before/after comparison table for course visits, forum posts, assignment submissions, and quiz attempts; trend badges use per-day rates for fair comparison; visible after 3 days from case creation

### 0.56.0
- Adviser caseload dashboard (My Cases): staff see a personal filtered view of their assigned or created cases; sorted by urgency with overdue and due-soon badges; count badge on the My Cases tab shows open cases at a glance

### 0.55.0
- Send Message to Student: staff can message a student directly from an intervention case using configurable Initial Contact and Follow-up templates; sends are automatically logged as case notes

### 0.54.0
- Forum Posts column added to the Student Activity table on the course detail page — shows the total number of forum posts per student in that course (from `forum_posts` + `forum_discussions`)

### 0.53.0
- Course Visits column added to the Student Activity table on the course detail page — shows the total number of log events per student in that course (from `logstore_standard_log`)

### 0.52.0
- Intervention Reports page: managers can view resolution rates, average days to resolve, cases by status, and a per-staff caseload breakdown, filterable by last 30, 90, or 365 days or all time
- Reports tab added to the Interventions tab bar, visible only to users with `manageinterventions`

### 0.51.0
- Follow-up date reminders: the nightly cache task sends a Moodle notification to the case assignee (or creator) when an intervention's follow-up date is reached; each follow-up date fires at most once

### 0.50.0
- Intervention case management: create cases from at-risk students, assign to staff, track status (New / In Progress / On Hold / Resolved / Closed), and set follow-up dates
- Notes timeline on every case with timestamps and author names; private notes visible only to users with the `viewprivatenotes` capability
- Manager view shows all cases site-wide; staff see only cases they created or are assigned to

### 0.49.0
- Student risk scoring: nightly task calculates a 0–100 composite score per student per course from five configurable rules — inactivity, low quiz grade, low submission rate, low completion, and never accessed
- Risk level bands: Low (0–29), Medium (30–59), High (60–79), Critical (80–100)
- Risk Rules admin page (`manage` capability): enable/disable rules, set threshold and weight per rule without code changes
- Risk badges displayed on at-risk student rows and intervention detail pages

### 0.48.0
- Privacy API extended to cover `local_courseinsights_summary.teachers` (cached editing teacher names)
- Licence activation uses Moodle `\curl` wrapper with string-keyed options throughout
- Moodle Marketplace review passed; maturity set to Stable

### 0.47.0
- Filter sidebar is now organised into numbered steps: courses, main date range, optional comparison period, and activity/student scope
- Main and comparison date labels were renamed to make it clearer which period controls the dashboard and which period is only the baseline
- README now documents the Moodle review fixes, including portable log aggregation, Marketplace-first licence delivery, Privacy API coverage, Moodle curl usage, and missing language strings
- README now separates the plugin support form from the post-purchase licence key delivery form

### 0.46.9
- Compare Period filter section now uses plugin-owned `<details>/<summary>` markup instead of Moodle's default collapsible form header, so the visible button style changes consistently across themes

### 0.46.8
- At-risk snapshot now calculates inactive days for never-accessed students from enrolment time and displays `Never` for last access
- Student reminder query now groups by student/course to avoid duplicate course entries when multiple enrolment methods exist
- Compare period UI tightened so Moodle's built-in collapse arrow does not leak into the filter header
- README expanded with licence, scheduled-task, cache, at-risk, and student-reminder workflow details
- Added `PRIVACY.md` and refreshed `about.md` so public documentation matches the current privacy and licence behaviour

### 0.46.7
- Detailed Report performance: aggregate chart data is rebuilt into per-course snapshots by the scheduled cache task
- Site Overview and Detailed Report caches update existing rows instead of duplicating snapshot rows

### 0.46.2
- Filter panel UI overhaul: accordion headers now full-row clickable buttons with chevron on the right and no grey square icon; course autocomplete unified into a single searchable control; all filter fields now consistent full width; compare period labels left-aligned
- PHPCBF code-style cleanup on filter form and language file

### 0.46.1
- Site Overview performance: MUC cache for site KPIs (1800 s TTL); upgrade step pre-warms the cache on install/upgrade

### 0.46.0
- Top students leaderboard on the course detail page — top 20 students by course total grade, with gold / silver / bronze badges

### 0.45.0
- Activity completion funnel on the course detail page — per-activity completion rate, bar turns red below 50 %
- User Report form rewritten as a proper Moodle form (moodleform) for reliable autocomplete behaviour
- Bug fixes: `{course_completions}` column is `course` not `courseid` — fixed in top-courses, at-risk, and student-reminders queries

### 0.44.0
- User Progress Report (third tab) — search any user to see their enrolled courses with completion status, grade, and last access
- Monthly active users trend table on Site Overview (12-month history from logstore)
- At-risk students table on Site Overview (enrolled, not completed, inactive beyond the configured threshold)
- User autocomplete AMD module and `search_users` external function

### 0.43.0
- Student inactivity reminders — nightly task emails students who have not accessed an enrolled course for the configured number of days; deduplication table prevents repeat sends
- Bug fix: top-courses-by-completion query fixed for MariaDB (derived-table alias)

### 0.41.0
- Summary cache — pre-aggregated course data stored nightly; `completionrate`, `lastactivity`, and `teachers` columns added to the summary table for faster dashboard loads

### 0.40.0
- Dashboard print / PDF view
- 30-day comparison period with trend arrows on stat cards
- xlsx export button added to dashboard

### 0.39.0
- Licence gate — activate via the key delivered for the purchase channel; 7-day grace period; weekly auto-renewal

### 0.37.0
- Major SQL performance refactor — 13 correlated subqueries replaced with JOIN-based aggregations

### 0.36.0
- MUC caching for filter dropdowns; window-function COUNT eliminates separate count query

### 0.35.0
- Bug fixes: user preference API, invisible course redirect, cache query visibility filter

### 0.33.0
- White-label branding — custom name, logo, and accent colour

### 0.32.0
- 30-day trend comparison on course detail page

### 0.31.0
- Filter presets — save and restore filter combinations per user

### 0.30.0
- xlsx export

### 0.29.0
- Scheduled digest emails (weekly / monthly) to site managers

### 0.28.0
- PDF / print view on course detail page

### 0.27.0
- Webhook — push nightly course JSON to an external URL

### 0.26.0
- REST API — `local_courseinsights_get_courses` external function

### 0.25.0
- Quiz score breakdown on course detail (avg / min / max / pass rate per quiz)

### 0.24.0
- Assignment submission timeline — 30-day bar chart on course detail

### 0.23.0
- Student activity table on course detail (last access, submissions, quiz attempts)

### 0.22.0
- 52-week engagement heatmap on course detail

### 0.21.0
- Grade distribution histogram on course detail

### 0.20.0
- Automated alerts — daily task sends Moodle messages to editing teachers when thresholds are breached

### 0.19.0
- Course health score — A to F grade on every dashboard card

### 0.18.0
- Cohort filter on dashboard and export

### 0.17.0
- Per-course detail page with completion hero, content breakdown, and info grid

### 0.16.0
- Card-based dashboard redesign with completion rate progress bar

### 0.1.0
- Initial release
