# Privacy information for Course Insights

`local_courseinsights`

Course Insights is a Moodle reporting plugin. It reads existing Moodle data so authorised users can review course engagement, completion, submissions, quiz activity, grades, and last access information.

The plugin does not modify course content, enrolments, grades, completions, or Moodle logs.

---

## Moodle data read by the plugin

Course Insights reads data already stored by Moodle, including:

- courses and course categories
- enrolments and user enrolments
- users and role assignments
- course completions and activity completions
- assignments, assignment submissions, quizzes, quiz attempts, and quiz grades
- course total grades
- course access records
- standard Moodle log records for bounded reporting windows

Access to the reports is controlled by Moodle capabilities.

---

## Data stored by the plugin

Course Insights stores report snapshots and limited tracking data in plugin-owned tables.

| Table | Type of data | Purpose |
|---|---|---|
| `local_courseinsights_summary` | Aggregate course summary data | Speeds up the dashboard for all-time reports |
| `local_courseinsights_site` | Aggregate Site Overview payloads | Speeds up Site Overview KPI and chart loading |
| `local_courseinsights_detail` | Aggregate Detailed Report chart payloads | Speeds up per-course detail charts |
| `local_courseinsights_atrisk` | User/course inactivity snapshot | Shows at-risk students on Site Overview |
| `local_courseinsights_reminders` | User/course reminder history | Prevents duplicate student inactivity reminders |

The aggregate snapshot tables do not store student names or email addresses.

The at-risk and reminder tables store limited personal data because they need to identify a student/course pair:

- `userid`
- `courseid`
- inactivity threshold
- last access time
- calculated inactive days
- reminder send time

---

## Student inactivity reminders

When student reminders are enabled, the scheduled task checks visible, incomplete courses for active enrolled students who have not accessed the course within the configured threshold.

One Moodle notification/email is sent per student and can include multiple course links. The plugin records the student/course reminder in `local_courseinsights_reminders` so the same reminder is not sent again until another full threshold period has passed.

---

## At-risk student snapshots

The Site Overview at-risk table is rebuilt by the scheduled cache task. It lists active, enrolled, incomplete students whose course access is older than the configured inactivity threshold.

If a student has never accessed a course, Course Insights displays `Never` as the last access value and calculates inactive days from the enrolment time.

---

## External services

Course Insights can contact external services only for configured features:

- licence activation and renewal with the Course Insights licence service
- optional webhook delivery when a webhook URL is configured by the site administrator

No webhook is sent unless a webhook URL is configured.

---

## Privacy API support

Course Insights implements Moodle's Privacy API for personal data stored in:

- `local_courseinsights_atrisk`
- `local_courseinsights_reminders`

The plugin declares metadata for those tables and supports user data export and deletion through Moodle's standard privacy tools.

---

## Data removal

Uninstalling the plugin removes plugin-owned database tables through Moodle's plugin uninstall process.

Privacy deletion requests handled through Moodle remove matching reminder and at-risk records for the approved user/context.

