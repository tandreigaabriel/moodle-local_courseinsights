# Course Insights for Moodle

`local_courseinsights` — Developed by [TAG Web Design](https://www.tandreig.com/plugins)

---

## What is Course Insights?

**Course Insights** gives Moodle administrators and managers a single place to see how every course on their site is performing — without digging through individual gradebooks or running manual reports.

Install it once, point your browser to **Site administration → Reports → Course Insights**, and immediately see enrolment numbers, submission rates, quiz activity, student health scores, and last activity dates across all your courses — filtered, sorted, and ready to export.

No configuration required. No changes to student-facing pages.

---

## Features

### Dashboard

- **Course cards** — each course shows completion rate, enrolled students, teachers, assignments, last student activity, and an Active / Inactive badge at a glance
- **Health score** — every course is graded A to F based on completion rate, recent activity, and engagement; spot struggling courses instantly
- **Filter by** category, course, cohort, date range, activity type (assignments / quizzes / exams), and student status — all filters update the dashboard instantly via AJAX
- **Filter presets** — save your most-used filter combinations and restore them in one click
- **Compare period** — see how the last 30 days compare to the previous 30 days; trend arrows on key metrics show whether things are improving or declining
- **Bar chart overview** — visual comparison of activity across your top courses
- **Pagination** — handles hundreds of courses without slowing down

### Per-course detail page

Click "Detailed Report" on any course card to open a full breakdown:

- Completion rate hero with enrolled students and teachers
- **Grade distribution** histogram across all assignments and quizzes
- **52-week engagement heatmap** — see when students are active throughout the year
- **Student activity table** — per-student last access, submissions, and quiz attempts
- **Submission timeline** — 30-day daily submission bar chart
- **Quiz score breakdown** — average, min, max, and pass rate per quiz
- **Print / PDF view** — print-ready layout with one click

### Exports

- **CSV export** — download the full filtered dataset
- **xlsx export** — download as an Excel spreadsheet

### Automated alerts

- Get notified when a course falls below a completion threshold or goes inactive for too many days
- Alerts are sent to course editing teachers via Moodle messaging
- Configurable thresholds; enabled per site

### Digest emails

- Weekly or monthly summary emails listing all courses with their health score, completion rate, and submission counts
- Sent automatically to site managers

### REST API & webhook

- **REST API** — query course data programmatically via Moodle's external functions
- **Webhook** — push nightly course data to any external LMS, analytics platform, or dashboard as JSON

### White-label branding

- Add your institution's name, logo, and accent colour to replace the default Course Insights header on every page

---

## Requirements

- Moodle 4.5 or later
- PHP 8.1 or later
- MariaDB or MySQL

---

## Licensing

Course Insights is a **commercial plugin** distributed under the GNU GPL v3.

A licence key is required to use the plugin. You can request a key at [tandreig.com/plugins](https://www.tandreig.com/plugins).

Once you have your key, enter it in **Site administration → Plugins → Local plugins → Course Insights settings → Licence Key** and save. Activation is automatic — no manual steps needed after that.

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

---

## Capabilities

| Capability | Default roles | Description |
|---|---|---|
| `local/courseinsights:view` | Manager, Editing teacher | View the dashboard and course detail pages |
| `local/courseinsights:export` | Manager, Editing teacher | Export reports as CSV or xlsx |
| `local/courseinsights:manage` | Manager | Manage plugin settings |

---

## Configuration

All settings are at **Site administration → Plugins → Local plugins → Course Insights settings**.

| Setting | Default | Purpose |
|---|---|---|
| Licence Key | — | Enter your licence key here to activate the plugin |
| Mini exam keywords | `mini,mini exam` | Keywords used to identify mini exams from quiz names |
| Exam keywords | `exam,final` | Keywords used to identify exams from quiz names |
| Student role IDs | `5` | Comma-separated Moodle role IDs counted as students |
| Enable summary cache | Off | Pre-aggregate data nightly for faster loading on large sites |
| Alerts enabled | Off | Enable automated low-engagement alerts to course teachers |
| Alert completion threshold | 50 | Alert when course completion drops below this % |
| Alert inactive days | 30 | Alert when no student activity for this many days |
| Digest emails | Off | Send weekly or monthly summary emails to managers |
| Webhook URL | — | POST course data nightly to an external URL |

---

## Troubleshooting

**Dashboard not visible**
Make sure the plugin is installed and the user has `local/courseinsights:view`. Purge all caches after installation.

**No data shown**
Check that the Student role IDs setting matches the role IDs on your site. The default (`5`) is the standard Student role but may differ on your installation.

**Licence not activating**
Make sure your server can make outbound HTTPS requests to `tandreig.com`. Check **Site administration → Server → HTTP** for any proxy settings that may be blocking outbound connections.

---

## Privacy

Course Insights reads existing Moodle data (courses, enrolments, assignments, quizzes, grades, access logs). It stores only aggregated course-level summaries — no personal data is stored by this plugin. See [PRIVACY.md](PRIVACY.md) or the plugin's privacy provider for full details.

---

## Support

- **Bug reports & feature requests:** [github.com/tandreigaabriel/moodle-local_courseinsights/issues](https://github.com/tandreigaabriel/moodle-local_courseinsights/issues)
- **Developed and maintained by:** [TAG Web Design](https://www.tandreig.com/plugins) — Andrei Toma

---

## License

GNU General Public License v3 or later — see [LICENSE](LICENSE) or [gnu.org/licenses/gpl-3.0.html](https://www.gnu.org/licenses/gpl-3.0.html)

---

## Changelog

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
- Licence gate — activate via key from tandreig.com/plugins; 7-day grace period; weekly auto-renewal

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
