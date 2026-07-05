# Course Insights — Plugin for Moodle

**Component:** `local_courseinsights`  
**Developer:** TAG Web Design — Andrei Toma  
**License:** GNU GPL v3 or later  
**Requires:** Moodle 4.5+ and PHP 8.1+  

---

## What is Course Insights?

Course Insights is a Moodle reporting plugin that gives administrators, managers, and authorised teaching staff a clear view of course engagement across the site.

Instead of opening each course separately, users can review enrolments, submissions, quiz activity, completion, grades, last access, course health, at-risk students, and detailed per-course charts from one reporting area.

The plugin does not change Moodle course content, enrolments, grades, completions, or logs. It reads existing Moodle data and presents it through dashboards, exports, scheduled summaries, and optional notifications.

---

## Main features

- Site-wide course dashboard with filters, saved presets, pagination, health scores, and CSV/xlsx export
- Site Overview with KPI snapshots, top courses, monthly trend data, and at-risk students
- Detailed Report page for each course with grade distribution, engagement heatmap, submission timeline, quiz breakdown, completion funnel, student activity, and leaderboard
- User Progress Report to review one user's enrolled courses, completion status, grades, and last access
- Scheduled summary snapshots so large sites can load reports faster
- Automated alerts for low completion or inactive courses
- Optional student inactivity reminder emails
- Weekly or monthly digest emails
- REST API and optional webhook delivery
- White-label branding with custom name, logo, and accent colour

---

## Performance model

Course Insights uses Moodle scheduled tasks for heavy work.

The main cache task runs daily by default and rebuilds:

- dashboard summary rows
- Site Overview KPI/chart snapshots
- at-risk student snapshots
- aggregate Detailed Report chart snapshots

Snapshots are updated in place or rebuilt for the configured threshold, so normal nightly runs do not duplicate report rows.

The Detailed Report cache stores aggregate chart data only. Student Activity and Top Students by Grade are read live because they display identifiable student information.

---

## Student reminders and at-risk students

When enabled, the student inactivity reminder task emails active students who are enrolled in visible, incomplete courses and have not accessed those courses within the configured threshold.

The plugin stores a student/course reminder history so the same reminder is not sent again until another full threshold period has passed.

The Site Overview at-risk table uses the same inactivity threshold. If a student has never opened a course, the report displays `Never` for last access and calculates inactive days from the enrolment time.

---

## Licensing

Course Insights is a commercial GPL plugin purchased through Moodle Marketplace.

A licence key is required to unlock all features. After a Moodle Marketplace purchase, the customer submits the post-purchase licence delivery form at:

`https://tandreig.com/request-key`

The licence key is issued after the Marketplace payment has been verified, then entered in:

**Site administration → Plugins → Local plugins → Course Insights settings → Licence Key**

---

## Privacy summary

Course Insights reads existing Moodle data, including courses, users, enrolments, completions, grades, submissions, quiz activity, and access logs.

It stores aggregate report snapshots for performance. It also stores limited personal tracking data for:

- at-risk student snapshots
- student inactivity reminder history

These records are declared in the Moodle Privacy API provider and can be exported or deleted through Moodle's standard privacy tools.

See [PRIVACY.md](PRIVACY.md) for the full privacy summary.

---

## Access control

Course Insights uses Moodle capabilities:

- `local/courseinsights:view`
- `local/courseinsights:export`
- `local/courseinsights:manage`

Access can be managed through Moodle's standard role and capability system.

---

## Support

Developed and maintained by **TAG Web Design — Andrei Toma**.

For support, bug reports, and feature requests, use the plugin repository issue tracker.

---

## License

GNU General Public License v3 or later. See [LICENSE](LICENSE).

