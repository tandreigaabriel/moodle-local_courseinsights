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
 * CSV export for the Course Insights dashboard.
 *
 * @package    local_courseinsights
 * @copyright  2026 Andrei Toma
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');
require_once($CFG->libdir . '/csvlib.class.php');

$context = \core\context\system::instance();
$PAGE->set_context($context);
require_login();
require_capability('local/courseinsights:export', $context);

$licstatus = \local_courseinsights\license::get_status();
if (
    $licstatus === \local_courseinsights\license::STATUS_EXPIRED ||
        $licstatus === \local_courseinsights\license::STATUS_UNLICENSED
) {
    header('HTTP/1.1 403 Forbidden');
    die('A valid Course Insights licence is required.');
}

$format  = optional_param('format', 'csv', PARAM_ALPHA);
$filters = \local_courseinsights\report_service::get_filters_from_request();
$records = \local_courseinsights\report_service::get_course_overview($filters, 0, 0);
$columns = \local_courseinsights\report_service::get_visible_columns($filters['activitytype']);

if ($format === 'xlsx') {
    require_once($CFG->libdir . '/excellib.class.php');

    $workbook  = new \MoodleExcelWorkbook('course-insights-' . date('Ymd-His'));
    $worksheet = $workbook->add_worksheet(get_string('pluginname', 'local_courseinsights'));
    $hdrfmt    = $workbook->add_format(['bold' => 1]);

    $col = 0;
    $worksheet->write(0, $col++, get_string('course', 'local_courseinsights'), $hdrfmt, 'string');
    foreach ($columns as $column) {
        $worksheet->write(0, $col++, get_string($column, 'local_courseinsights'), $hdrfmt, 'string');
    }

    $row = 1;
    foreach ($records as $record) {
        $col = 0;
        $worksheet->write($row, $col++, format_string($record->fullname), null, 'string');

        foreach ($columns as $column) {
            $value = \local_courseinsights\report_service::get_column_value($column, $record);

            if ($column === 'completionrate' || $column === 'avgquizgrade') {
                $worksheet->write(
                    $row,
                    $col++,
                    $value !== null ? (float) $value : '',
                    null,
                    $value !== null ? 'number' : 'string'
                );
            } else if ($column === 'lastactivity') {
                $worksheet->write(
                    $row,
                    $col++,
                    $value ? userdate($value, get_string('strftimedate', 'langconfig')) : '',
                    null,
                    'string'
                );
            } else if ($column === 'teachers') {
                $worksheet->write($row, $col++, $value !== null ? (string) $value : '', null, 'string');
            } else {
                $worksheet->write($row, $col++, $value, null, is_numeric($value) ? 'number' : 'string');
            }
        }
        $row++;
    }

    $workbook->close();
    exit;
}

$csv = new csv_export_writer();
$csv->set_filename('course-insights-' . date('Ymd-His'));

$header = [get_string('course', 'local_courseinsights')];
foreach ($columns as $column) {
    $header[] = get_string($column, 'local_courseinsights');
}
$csv->add_data($header);

foreach ($records as $record) {
    $row = [\local_courseinsights\report_service::csv_safe_value(format_string($record->fullname))];

    foreach ($columns as $column) {
        $value = \local_courseinsights\report_service::get_column_value($column, $record);

        if ($column === 'completionrate') {
            $row[] = $value !== null ? $value : '';
        } else if ($column === 'avgquizgrade') {
            $row[] = $value !== null ? $value : '';
        } else if ($column === 'lastactivity') {
            $row[] = $value ? userdate($value, get_string('strftimedate', 'langconfig')) : '';
        } else if ($column === 'teachers') {
            $row[] = $value !== null ? (string) $value : '';
        } else {
            $row[] = $value;
        }
    }

    $csv->add_data($row);
}

$csv->download_file();
exit;
