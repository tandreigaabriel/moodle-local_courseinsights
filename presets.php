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
 * Save / delete filter presets for Course Insights.
 *
 * @package    local_courseinsights
 * @copyright  2026 Andrei Toma
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');

$context = \core\context\system::instance();
$PAGE->set_context($context);
require_login();
require_capability('local/courseinsights:view', $context);
require_sesskey();

$action = required_param('action', PARAM_ALPHA);
$dashurl = new moodle_url('/local/courseinsights/index.php');

$presets = json_decode(get_user_preferences('local_courseinsights_presets', '[]'), true);
if (!is_array($presets)) {
    $presets = [];
}

if ($action === 'save') {
    $name = trim(required_param('presetname', PARAM_TEXT));

    if ($name !== '') {
        $filters = [
            'cohortid'      => optional_param('cohortid', 0, PARAM_INT),
            'categoryid'    => optional_param('categoryid', 0, PARAM_INT),
            'courseid'      => optional_param('courseid', 0, PARAM_INT),
            'startdate'     => optional_param('startdate', '', PARAM_TEXT),
            'enddate'       => optional_param('enddate', '', PARAM_TEXT),
            'activitytype'  => optional_param('activitytype', 'all', PARAM_ALPHA),
            'studentstatus' => optional_param('studentstatus', 'active', PARAM_ALPHA),
        ];

        // Replace existing preset with same name, otherwise append (cap at 10).
        $found = false;
        foreach ($presets as $i => $p) {
            if ($p['name'] === $name) {
                $presets[$i]['filters'] = $filters;
                $found = true;
                break;
            }
        }
        if (!$found) {
            if (count($presets) < 10) {
                $presets[] = ['name' => $name, 'filters' => $filters];
            }
        }
        set_user_preference('local_courseinsights_presets', json_encode($presets));
    }
} else if ($action === 'delete') {
    $name = required_param('presetname', PARAM_TEXT);
    $presets = array_values(array_filter($presets, function ($p) use ($name) {
        return $p['name'] !== $name;
    }));
    set_user_preference('local_courseinsights_presets', json_encode($presets));
}

redirect($dashurl);
