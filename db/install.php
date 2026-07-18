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
 * Post-install hook for local_courseinsights.
 *
 * @package    local_courseinsights
 * @copyright  2026 Andrei Toma
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Seeds default data after a fresh plugin installation.
 *
 * upgrade.php seeding only runs on upgrades; fresh installs skip all upgrade
 * steps and go straight to the current version, so seeding must live here too.
 */
function xmldb_local_courseinsights_install(): void {
    global $DB;

    // Add composite index on core logstore table to speed up per-course visitor count queries.
    $dbman = $DB->get_manager();
    $table = new xmldb_table('logstore_standard_log');
    $index = new xmldb_index('logsstanlog_coutim_ix', XMLDB_INDEX_NOTUNIQUE, ['courseid', 'timecreated']);
    if (!$dbman->index_exists($table, $index)) {
        $dbman->add_index($table, $index);
    }

    if ($DB->record_exists('local_courseinsights_risk_rules', [])) {
        return;
    }

    $now = time();
    foreach (\local_courseinsights\risk_service::get_default_rules() as $row) {
        [$ruletype, $label, $threshold, $weight, $enabled, $sortorder] = $row;
        $DB->insert_record('local_courseinsights_risk_rules', (object) [
            'ruletype'     => $ruletype,
            'label'        => $label,
            'threshold'    => $threshold,
            'weight'       => $weight,
            'enabled'      => $enabled,
            'sortorder'    => $sortorder,
            'timecreated'  => $now,
            'timemodified' => $now,
        ]);
    }
}
