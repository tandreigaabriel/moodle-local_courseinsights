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
 * Upgrade steps for local_courseinsights.
 *
 * @package    local_courseinsights
 * @copyright  2026 Andrei Toma
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Runs upgrade steps from the given old version to the current version.
 *
 * @param int $oldversion
 * @return bool
 */
function xmldb_local_courseinsights_upgrade(int $oldversion): bool {
    global $DB;

    $dbman = $DB->get_manager();

    if ($oldversion < 2026070100) {
        $table = new xmldb_table('local_courseinsights_summary');

        $completionratefield = new xmldb_field(
            'completionrate',
            XMLDB_TYPE_NUMBER,
            '5, 1',
            null,
            null,
            null,
            null,
            'avgquizgrade'
        );
        if (!$dbman->field_exists($table, $completionratefield)) {
            $dbman->add_field($table, $completionratefield);
        }

        $lastactivityfield = new xmldb_field(
            'lastactivity',
            XMLDB_TYPE_INTEGER,
            '10',
            null,
            null,
            null,
            null,
            'completionrate'
        );
        if (!$dbman->field_exists($table, $lastactivityfield)) {
            $dbman->add_field($table, $lastactivityfield);
        }

        $teachersfield = new xmldb_field(
            'teachers',
            XMLDB_TYPE_TEXT,
            null,
            null,
            null,
            null,
            null,
            'lastactivity'
        );
        if (!$dbman->field_exists($table, $teachersfield)) {
            $dbman->add_field($table, $teachersfield);
        }

        upgrade_plugin_savepoint(true, 2026070100, 'local', 'courseinsights');
    }

    if ($oldversion < 2026070300) {
        $table = new xmldb_table('local_courseinsights_reminders');
        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE);
        $table->add_field('userid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null, '0');
        $table->add_field('courseid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null, '0');
        $table->add_field('timereminded', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null, '0');
        $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
        $table->add_index('useridcourseid', XMLDB_INDEX_UNIQUE, ['userid', 'courseid']);
        $table->add_index('courseid', XMLDB_INDEX_NOTUNIQUE, ['courseid']);
        if (!$dbman->table_exists($table)) {
            $dbman->create_table($table);
        }
        upgrade_plugin_savepoint(true, 2026070300, 'local', 'courseinsights');
    }

    if ($oldversion < 2026070502) {
        $table = new xmldb_table('local_courseinsights_site');
        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE);
        $table->add_field('snapshotkey', XMLDB_TYPE_CHAR, '100', null, XMLDB_NOTNULL);
        $table->add_field('payload', XMLDB_TYPE_TEXT, null, null, XMLDB_NOTNULL);
        $table->add_field('timecreated', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null, '0');
        $table->add_field('timemodified', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null, '0');
        $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
        $table->add_index('snapshotkey', XMLDB_INDEX_UNIQUE, ['snapshotkey']);

        if (!$dbman->table_exists($table)) {
            $dbman->create_table($table);
        }

        $table = new xmldb_table('local_courseinsights_atrisk');
        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE);
        $table->add_field('userid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null, '0');
        $table->add_field('courseid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null, '0');
        $table->add_field('threshold', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null, '0');
        $table->add_field('lastaccess', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null, '0');
        $table->add_field('daysinactive', XMLDB_TYPE_INTEGER, '10');
        $table->add_field('timemodified', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null, '0');
        $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
        $table->add_index('usercoursethreshold', XMLDB_INDEX_UNIQUE, ['userid', 'courseid', 'threshold']);
        $table->add_index('threshold', XMLDB_INDEX_NOTUNIQUE, ['threshold']);
        $table->add_index('courseid', XMLDB_INDEX_NOTUNIQUE, ['courseid']);

        if (!$dbman->table_exists($table)) {
            $dbman->create_table($table);
        }

        upgrade_plugin_savepoint(true, 2026070502, 'local', 'courseinsights');
    }

    if ($oldversion < 2026070504) {
        $table = new xmldb_table('local_courseinsights_detail');
        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE);
        $table->add_field('courseid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null, '0');
        $table->add_field('payload', XMLDB_TYPE_TEXT, null, null, XMLDB_NOTNULL);
        $table->add_field('timecreated', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null, '0');
        $table->add_field('timemodified', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null, '0');
        $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
        $table->add_index('courseid', XMLDB_INDEX_UNIQUE, ['courseid']);

        if (!$dbman->table_exists($table)) {
            $dbman->create_table($table);
        }

        upgrade_plugin_savepoint(true, 2026070504, 'local', 'courseinsights');
    }

    if ($oldversion < 2026071500) {
        // Create risk_rules table.
        $table = new xmldb_table('local_courseinsights_risk_rules');
        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE);
        $table->add_field('ruletype', XMLDB_TYPE_CHAR, '50', null, XMLDB_NOTNULL, null, null, '');
        $table->add_field('label', XMLDB_TYPE_CHAR, '255', null, XMLDB_NOTNULL, null, null, '');
        $table->add_field('threshold', XMLDB_TYPE_NUMBER, '10, 2', null, XMLDB_NOTNULL, null, null, '0');
        $table->add_field('weight', XMLDB_TYPE_INTEGER, '3', null, XMLDB_NOTNULL, null, null, '0');
        $table->add_field('enabled', XMLDB_TYPE_INTEGER, '1', null, XMLDB_NOTNULL, null, null, '1');
        $table->add_field('sortorder', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null, '0');
        $table->add_field('timecreated', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null, '0');
        $table->add_field('timemodified', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null, '0');
        $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
        $table->add_index('ruletype', XMLDB_INDEX_UNIQUE, ['ruletype']);
        $table->add_index('enabled', XMLDB_INDEX_NOTUNIQUE, ['enabled']);
        if (!$dbman->table_exists($table)) {
            $dbman->create_table($table);
        }

        // Create risk_scores table.
        $table = new xmldb_table('local_courseinsights_risk_scores');
        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE);
        $table->add_field('userid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null, '0');
        $table->add_field('courseid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null, '0');
        $table->add_field('score', XMLDB_TYPE_INTEGER, '3', null, XMLDB_NOTNULL, null, null, '0');
        $table->add_field('risklevel', XMLDB_TYPE_CHAR, '10', null, XMLDB_NOTNULL, null, null, 'low');
        $table->add_field('reasons', XMLDB_TYPE_TEXT, null, null, null);
        $table->add_field('timecalculated', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null, '0');
        $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
        $table->add_index('usercourse', XMLDB_INDEX_UNIQUE, ['userid', 'courseid']);
        $table->add_index('userid', XMLDB_INDEX_NOTUNIQUE, ['userid']);
        $table->add_index('courseid', XMLDB_INDEX_NOTUNIQUE, ['courseid']);
        if (!$dbman->table_exists($table)) {
            $dbman->create_table($table);
        }

        // Seed default risk rules if none exist.
        if (!$DB->record_exists('local_courseinsights_risk_rules', [])) {
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

        upgrade_plugin_savepoint(true, 2026071500, 'local', 'courseinsights');
    }

    if ($oldversion < 2026071501) {
        // Create interventions table.
        $table = new xmldb_table('local_courseinsights_interventions');
        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE);
        $table->add_field('userid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null, '0');
        $table->add_field('courseid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null, '0');
        $table->add_field('createdby', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null, '0');
        $table->add_field('assignedto', XMLDB_TYPE_INTEGER, '10', null, null);
        $table->add_field('title', XMLDB_TYPE_CHAR, '255', null, XMLDB_NOTNULL, null, null, '');
        $table->add_field('status', XMLDB_TYPE_CHAR, '30', null, XMLDB_NOTNULL, null, null, 'new');
        $table->add_field('riskscore', XMLDB_TYPE_INTEGER, '3', null, null);
        $table->add_field('risklevel', XMLDB_TYPE_CHAR, '10', null, null);
        $table->add_field('followupdate', XMLDB_TYPE_INTEGER, '10', null, null);
        $table->add_field('timecreated', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null, '0');
        $table->add_field('timemodified', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null, '0');
        $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
        $table->add_index('userid', XMLDB_INDEX_NOTUNIQUE, ['userid']);
        $table->add_index('courseid', XMLDB_INDEX_NOTUNIQUE, ['courseid']);
        $table->add_index('createdby', XMLDB_INDEX_NOTUNIQUE, ['createdby']);
        $table->add_index('status', XMLDB_INDEX_NOTUNIQUE, ['status']);
        if (!$dbman->table_exists($table)) {
            $dbman->create_table($table);
        }

        // Create intervention_notes table.
        $table = new xmldb_table('local_courseinsights_intervention_notes');
        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE);
        $table->add_field('interventionid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null, '0');
        $table->add_field('userid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null, '0');
        $table->add_field('note', XMLDB_TYPE_TEXT, null, null, XMLDB_NOTNULL);
        $table->add_field('isprivate', XMLDB_TYPE_INTEGER, '1', null, XMLDB_NOTNULL, null, null, '0');
        $table->add_field('timecreated', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null, '0');
        $table->add_field('timemodified', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null, '0');
        $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
        $table->add_index('interventionid', XMLDB_INDEX_NOTUNIQUE, ['interventionid']);
        $table->add_index('userid', XMLDB_INDEX_NOTUNIQUE, ['userid']);
        if (!$dbman->table_exists($table)) {
            $dbman->create_table($table);
        }

        upgrade_plugin_savepoint(true, 2026071501, 'local', 'courseinsights');
    }

    if ($oldversion < 2026071502) {
        // Add lastreminder column so the follow-up reminder task only fires once per follow-up date.
        $table = new xmldb_table('local_courseinsights_interventions');
        $field = new xmldb_field('lastreminder', XMLDB_TYPE_INTEGER, '10', null, null, null, null, 'followupdate');
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }
        upgrade_plugin_savepoint(true, 2026071502, 'local', 'courseinsights');
    }

    if ($oldversion < 2026071903) {
        // Seed default risk rules on sites that did a fresh install (which skips
        // upgrade steps) and therefore never got the seeding from step 2026071500.
        if (!$DB->record_exists('local_courseinsights_risk_rules', [])) {
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
        upgrade_plugin_savepoint(true, 2026071903, 'local', 'courseinsights');
    }

    if ($oldversion < 2026071902) {
        // Create log_rollup table for incremental pre-aggregated daily event counts per course.
        $table = new xmldb_table('local_courseinsights_log_rollup');
        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE);
        $table->add_field('courseid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null, '0');
        $table->add_field('logdate', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null, '0');
        $table->add_field('event_count', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null, '0');
        $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
        $table->add_index('coursedate', XMLDB_INDEX_UNIQUE, ['courseid', 'logdate']);
        if (!$dbman->table_exists($table)) {
            $dbman->create_table($table);
        }
        upgrade_plugin_savepoint(true, 2026071902, 'local', 'courseinsights');
    }

    if ($oldversion < 2026071905) {
        // Add composite index on the core logstore table to speed up per-course
        // COUNT(DISTINCT userid) queries used by the summary cache builder.
        // Without this index MySQL does a full table scan per course.
        $table = new xmldb_table('logstore_standard_log');
        $index = new xmldb_index('logsstanlog_coutim_ix', XMLDB_INDEX_NOTUNIQUE, ['courseid', 'timecreated']);
        if (!$dbman->index_exists($table, $index)) {
            $dbman->add_index($table, $index);
        }
        upgrade_plugin_savepoint(true, 2026071905, 'local', 'courseinsights');
    }

    if ($oldversion < 2026071906) {
        // Remove empty-string DEFAULT from CHAR NOT NULL columns — XMLDB rejects DEFAULT ''
        // on CHAR fields; the plugin code always supplies values on insert so no default is needed.
        $table = new xmldb_table('local_courseinsights_risk_rules');
        $field = new xmldb_field('ruletype', XMLDB_TYPE_CHAR, '50', null, XMLDB_NOTNULL);
        $dbman->change_field_default($table, $field);

        $field = new xmldb_field('label', XMLDB_TYPE_CHAR, '255', null, XMLDB_NOTNULL);
        $dbman->change_field_default($table, $field);

        $table = new xmldb_table('local_courseinsights_interventions');
        $field = new xmldb_field('title', XMLDB_TYPE_CHAR, '255', null, XMLDB_NOTNULL);
        $dbman->change_field_default($table, $field);

        upgrade_plugin_savepoint(true, 2026071906, 'local', 'courseinsights');
    }

    return true;
}
