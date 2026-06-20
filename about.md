# Course Insights — Plugin for Moodle

**Component:** `local_courseinsights`  
**Author:** Andrei Toma  
**License:** GNU GPL v3 or later  
**Requires:** Moodle 4.5+ · PHP 8.1+  

---

## What is Course Insights?

Course Insights is a reporting dashboard plugin for Moodle that gives administrators and managers a complete, real-time view of student engagement across every course on their site — from a single page.

Most Moodle sites accumulate dozens or hundreds of courses over time. Tracking who is active, how many assignments have been submitted, which quizzes students are attempting, and how grades compare across courses normally means opening each course individually, visiting its gradebook, and cross-referencing reports manually. Course Insights eliminates that entirely.

---

## Who is it for?

- **Moodle administrators** who need a site-wide activity overview without writing custom SQL
- **Academic managers** who monitor student engagement across multiple courses or programmes
- **Department heads** tracking assignment submission rates and quiz performance at a glance
- **Training coordinators** running compliance or professional development courses who need to confirm participation

---

## Key Benefits

### One dashboard, every course

All courses appear in a single sortable table. Enrolments, submissions, quiz attempts, exam attempts, and average grades are shown side by side. No switching between courses, no exporting from multiple places.

### Live AJAX filtering

Changing a filter — course, activity type, student status — updates the results instantly without reloading the page. Moodle's Fragment API handles the request server-side; only the dashboard section of the page is replaced.

### Flexible activity breakdown

The plugin distinguishes between assignments, quizzes, exams, and mini exams. Which counts as an "exam" or "mini exam" is controlled by keyword matching on quiz names — configurable in settings. Columns adjust automatically when the activity type filter changes.

### Fast on large sites

An optional summary cache pre-aggregates all-time data nightly via a scheduled task. Administrators can enable "Use summary cache" on the dashboard for near-instant load times on sites with hundreds of courses and thousands of students.

### Export to CSV

Every filtered view can be exported to CSV with one click. The export respects all active filters and includes formula-injection protection for safe opening in Excel or Google Sheets.

### Role-aware access control

Three separate capabilities — `view`, `export`, and `manage` — let administrators give teachers dashboard access without giving them export or configuration permissions. Capabilities are set at system context and use Moodle's standard roles UI.

---

## Feature Summary

| Feature | Detail |
|---|---|
| Course overview table | Enrolments, submissions, quiz/exam/mini-exam attempts, avg grade |
| AJAX filter refresh | Instant results using Moodle's Fragment API |
| Activity type filter | All · Assignments · Quizzes · Exams · Mini Exams |
| Student status filter | Active · Suspended · All |
| Date range filter | Start and end date inputs |
| Course filter | Dropdown of all visible courses |
| Bar chart | Visual comparison across courses (top 20) |
| CSV export | Full filtered dataset download |
| Summary cache | Nightly scheduled task for fast all-time reports |
| Navigation node | Site administration → Reports → Course Insights |
| Capabilities | `view` · `export` · `manage` |
| Privacy compliant | No personal data stored (Moodle Privacy API) |

---

## Technical Standards

- Built for Moodle 4.5 (compatible with Moodle 5.0)
- Follows Moodle coding standards (PHPCS-clean)
- Uses Moodle APIs throughout: `$DB`, `$OUTPUT`, Fragment API, AMD modules, Mustache templates, Moodle forms, Moodle charts
- Namespaced PHP classes in `classes/` following PSR-4
- No external dependencies — no third-party libraries, no external API calls
- Privacy provider implemented: plugin stores only aggregated, non-personal summary data
- GPL v3 licensed — compatible with Moodle Plugin Directory submission

---

## Installation

1. Download the plugin ZIP.
2. In Moodle: **Site administration → Plugins → Install plugins** → upload ZIP.
3. Complete the upgrade prompts.
4. Go to **Site administration → Reports → Course Insights**.

No database schema beyond the summary cache table. No configuration required to see data.

---

## Configuration (Optional)

| Setting | Purpose |
|---|---|
| Mini exam keywords | Words used to identify mini exams from quiz names (e.g. `mini,mini exam`) |
| Exam keywords | Words used to identify exams from quiz names (e.g. `exam,final`) |
| Student role IDs | Moodle role IDs counted as students (default: 5, 11, 25) |
| Enable summary cache | Enables the scheduled task and the "Use summary cache" checkbox |

---

## Support & Development

Developed and maintained by **Andrei Toma**.

For bug reports, feature requests, or support, please open an issue on the plugin repository.

---

## License

GNU General Public License v3 or later.  
This plugin is free software. You may use, modify, and distribute it under the terms of the GPL.  
See [LICENSE](LICENSE) or https://www.gnu.org/licenses/gpl-3.0.html
