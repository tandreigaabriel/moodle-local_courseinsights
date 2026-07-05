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

    return true;
}
