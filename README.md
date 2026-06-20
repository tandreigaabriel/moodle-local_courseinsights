# Course Insights for Moodle

`local_courseinsights` — Maintained by Andrei Toma

## Description

Course Insights is a Moodle local plugin that gives administrators and managers a fast, clear overview of student activity across all courses — in one place. It aggregates enrolment, assignment, quiz, and grade data from existing Moodle tables and presents it as a filterable, exportable dashboard. No configuration required after installation. No impact on student-facing pages.

Whether you manage a dozen courses or hundreds, Course Insights lets you spot low engagement, track submission rates, and compare quiz performance without digging through individual course gradebooks.

## Features

- **Course completion rate** — per-course percentage of enrolled students who have completed the course, sourced from Moodle's `{course_completions}` table; always visible alongside enrolled students
- **Live AJAX dashboard** — filter by category, course, date range, activity type, or student status; results update instantly without a full page reload
- **Server-side pagination** — 50 courses per page; page changes via AJAX without losing filter state; safe on sites with hundreds of courses
- **Course overview table** — per-course counts for enrolments, assignment submissions, quiz attempts, exam attempts, and mini exam attempts
- **Average quiz grade column** — percentage average per course, across all matching quizzes
- **Smart activity type filter** — view all activity or drill down to assignments, quizzes, exams, or mini exams; columns adjust automatically
- **Student status filter** — report on active, suspended, or all enrolled students
- **Teacher column** — comma-separated list of editing teachers assigned to each course
- **Last student activity date** — per-course date of the most recent student access, always visible regardless of the active activity type filter
- **Date range filter** — scope data to any time window by start and end date
- **Bar chart overview** — visual comparison across courses (auto-truncated to top 20 for readability)
- **CSV export** — download the full filtered dataset with one click
- **Summary cache** — optional nightly pre-aggregation task for fast loading on large sites
- **Navigation node** — accessible from Site administration → Reports → Course Insights
- **Role-aware** — separate `view`, `export`, and `manage` capabilities; works for managers and editing teachers out of the box

## Requirements

- Moodle 4.5 or later (tested on 4.5 and 5.0)
- PHP 8.1 or later
- MariaDB or MySQL

## Installation

### Option A — Moodle admin interface

1. Download or build the plugin ZIP.
2. Log in as site administrator.
3. Go to **Site administration → Plugins → Install plugins**.
4. Upload the ZIP file and follow the upgrade prompts.

### Option B — Manual installation

1. Copy the `courseinsights` folder into your Moodle installation at:
   ```
   /path/to/moodle/local/courseinsights/
   ```
2. Log in as site administrator.
3. Go to **Site administration → Notifications**.
4. Click **Upgrade Moodle database now**.

## Access

Once installed, the dashboard is available from:

```
Site administration → Reports → Course Insights
```

Direct URL: `/local/courseinsights/index.php`

## Capabilities

| Capability                    | Default roles             | Description            |
| ----------------------------- | ------------------------- | ---------------------- |
| `local/courseinsights:view`   | Manager, Editing teacher  | View the dashboard     |
| `local/courseinsights:export` | Manager, Editing teacher  | Export report as CSV   |
| `local/courseinsights:manage` | Manager                   | Manage plugin settings |

Capabilities are assigned at system context. Grant them to additional roles via **Site administration → Users → Permissions → Define roles**.

## Configuration

Plugin settings are available from:

```
Site administration → Plugins → Local plugins → Course Insights settings
```

| Setting              | Default          | Description                                                                       |
| -------------------- | ---------------- | --------------------------------------------------------------------------------- |
| Mini exam keywords   | `mini,mini exam` | Comma-separated words used to identify mini exams from quiz names                 |
| Exam keywords        | `exam,final`     | Comma-separated words used to identify exams from quiz names                      |
| Student role IDs     | `5,11,25`        | Comma-separated Moodle role IDs counted as students in reports                    |
| Enable summary cache | Off              | When enabled, allows the dashboard to use cached all-time data for faster loading |

## Summary Cache

The plugin includes an optional scheduled task — **Build Course Insights summary cache** — that runs nightly at 02:00 and pre-aggregates all-time report data into the `local_courseinsights_summary` table.

The cache is used only when:

- **Enable summary cache** is turned on in settings
- **Use summary cache** is checked on the dashboard
- No date range filter is active
- Activity type filter is set to **All**
- Student status filter is set to **Active**

Manage the task schedule under **Site administration → Server → Scheduled tasks**.

## Troubleshooting

**Dashboard not visible in Site administration → Reports**
Ensure the plugin is installed and the logged-in user has the `local/courseinsights:view` capability. Purge caches after installation: Site administration → Development → Purge all caches.

**No data shown**
Confirm that the Student role IDs setting contains the correct Moodle role IDs for your site. The default values (5, 11, 25) may not match your Moodle instance.

**Exams or mini exams show zero**
Check the Exam keywords and Mini exam keywords settings. The plugin matches quiz names against these keywords (case-insensitive, partial match).

## Privacy

This plugin reads existing Moodle course, enrolment, assignment, quiz, and grade data. It stores only aggregated course-level summary data in its own table (`local_courseinsights_summary`). No personal data is stored by this plugin.

## License

GNU General Public License v3 or later — see [LICENSE](LICENSE) or https://www.gnu.org/licenses/gpl-3.0.html

## Changelog

### 0.15.0

- Nested category filter — selecting a category now includes courses from all subcategories at any depth, not just direct children
- Category dropdown shows the tree hierarchy with indentation (ordered by path) for easier navigation

### 0.14.0

- Persistent server-side sort — clicking any column header reloads the dashboard sorted by that column; clicking again reverses direction
- Sort state is stored in hidden form fields (`sortby`/`sortdir`) so it survives filter changes, AJAX reloads, and page navigation; sort is also included in the CSV export URL
- NULLs (e.g. no completion rate, no last activity) always sort to the bottom in both directions
- Cache is bypassed when sort is not the default (course name ascending), consistent with other filter bypass rules
- Removed client-side DOM sort (which reset on every AJAX filter change) in favour of the server-side ORDER BY

### 0.13.0

- Quick date presets added — "Last 7 days", "Last 30 days", "This month", and "Clear dates" buttons appear below the date range inputs
- Clicking a preset populates `startdate`/`enddate` fields and immediately triggers an AJAX dashboard reload at page 0
- Dates are computed in the user's local timezone
- No server-side changes — JS-only feature

### 0.12.0

- Course completion rate column added — shows the percentage of enrolled students who have completed each course
- Sourced from `{course_completions}` (`timecompleted IS NOT NULL`); denominator is enrolled students matching the active student status filter
- Visible in all activity type modes (always alongside enrolled students count)
- Displays `-` when no enrolled students or when using cached data (cache pre-dates this column)
- Included in CSV export

### 0.11.0

- Course category filter added — filter the dashboard by Moodle course category using a new select dropdown
- `get_category_options()` fetches all visible categories from `{course_categories}` ordered by name
- `categoryid` added to all filter pipelines: `get_filters_from_request()`, `get_course_overview()`, `get_course_count()`, `get_export_url()`, fragment callback, and AMD module
- Category filter triggers AJAX dashboard reload and resets to page 0
- Summary cache bypassed when a category filter is active (cache is all-course, category-agnostic)
- Trailing blank lines in lang file cleaned up

### 0.10.0

- Server-side pagination added — 50 courses per page, controlled by `DEFAULT_PER_PAGE` in `report_service.php`
- `get_course_count()` runs a fast `COUNT(*)` query (no subqueries) to compute total pages without loading all rows
- `get_course_overview()` now accepts `$page` and `$perpage`; passing `$perpage = 0` returns all rows (used by export and cache rebuild)
- `get_pagination_context()` builds Bootstrap 5 pagination context with smart ellipsis for large page counts
- AJAX filter changes reset to page 0; pagination button clicks reload the fragment with the new page, preserving current filters
- `export.php` and `rebuild_summary_cache()` always fetch all records regardless of pagination
- Courses stat card now always shows the total course count across all pages
- Table upgraded to `table-hover` for improved readability

### 0.9.0

- Teachers column added — shows the comma-separated names of editing teachers assigned to each course, always visible regardless of the active activity type filter
- Uses `GROUP_CONCAT` on `{role_assignments}` joined to the `editingteacher` role shortname
- Included in CSV export

### 0.8.0

- Last student activity date column added — shows the most recent date any student accessed each course, sourced from `user_lastaccess`
- Column is always visible regardless of the active activity type filter
- Displays `-` when no student has accessed the course; formatted as a human-readable date otherwise
- Date included in CSV export

### 0.7.0

- Summary stat cards added above the chart — always shows Courses and Students totals; Submissions shown when assignments filter is active; Attempts shown when any quiz/exam filter is active
- `report_service::get_stats()` helper added to compute totals from report records
- Four new lang strings: `stat_courses`, `stat_students`, `stat_submissions`, `stat_attempts`

### 0.6.0

- Sortable columns — click any table header to sort ascending/descending; click again to reverse
- Dash (`-`) values always sort to the bottom regardless of direction
- Sort resets cleanly when a filter change triggers an AJAX dashboard refresh
- Keyboard accessible: column headers are focusable and respond to Enter/Space
- Sort direction indicator (▲ / ▼) shown on the active column

### 0.5.0

- AJAX filter refresh — changing a filter select now updates only the dashboard section in-place using Moodle's Fragment API; no full page reload
- Fragment callback `local_courseinsights_output_fragment_dashboard()` added to `lib.php`
- AMD module updated to use `core/fragment`, `core/templates`, and `core/notification`
- Dashboard wrapped in `[data-region]` target for partial DOM replacement
- Context ID passed to AMD `init()` for secure fragment requests

### 0.4.0

- Refactored dashboard to Mustache template (`templates/dashboard.mustache`)
- Added AMD module (`amd/src/filter.js`) — filter selects auto-submit on change
- Added `styles.css` for plugin-scoped CSS
- Added `lib.php` — navigation node for editing teachers
- Chart now respects the active activity type filter (only shows relevant series)
- Hidden courses excluded from report and course filter dropdown
- Empty-state message shown when no courses match the filters
- Chart truncation noted when more than 20 courses exist
- `usecache` checkbox hidden when summary cache is disabled in settings
- `rebuild_summary_cache()` wrapped in a database transaction

### 0.3.1

- Granted `view` and `export` capabilities to the `editingteacher` archetype by default
- Moved privacy provider to correct PSR-4 path (`classes/privacy/provider.php`)
- Minor PHPDoc corrections

### 0.3.0

- Renamed from `local_cnmreports` to `local_courseinsights`
- Added activity type column filtering
- Added CSV formula-injection guard
- Added `get_visible_columns()` and `get_column_value()` helpers

### 0.1.0

- Initial release
