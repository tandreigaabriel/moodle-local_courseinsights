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
 * Privacy API provider for local_courseinsights.
 *
 * @package    local_courseinsights
 * @copyright  2026 Andrei Toma
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_courseinsights\privacy;

use core_privacy\local\metadata\collection;
use core_privacy\local\request\approved_contextlist;
use core_privacy\local\request\approved_userlist;
use core_privacy\local\request\contextlist;
use core_privacy\local\request\userlist;
use core_privacy\local\request\writer;

/**
 * Privacy provider for reminder records stored by Course Insights.
 */
class provider implements
    \core_privacy\local\metadata\provider,
    \core_privacy\local\request\core_userlist_provider,
    \core_privacy\local\request\plugin\provider {
    /**
     * Returns metadata about personal data stored by this plugin.
     *
     * @param collection $collection Metadata collection.
     * @return collection Updated metadata collection.
     */
    public static function get_metadata(collection $collection): collection {
        $collection->add_database_table(
            'local_courseinsights_reminders',
            [
                'userid' => 'privacy:metadata:reminders:userid',
                'courseid' => 'privacy:metadata:reminders:courseid',
                'timereminded' => 'privacy:metadata:reminders:timereminded',
            ],
            'privacy:metadata:reminders'
        );

        $collection->add_database_table(
            'local_courseinsights_atrisk',
            [
                'userid' => 'privacy:metadata:atrisk:userid',
                'courseid' => 'privacy:metadata:atrisk:courseid',
                'threshold' => 'privacy:metadata:atrisk:threshold',
                'lastaccess' => 'privacy:metadata:atrisk:lastaccess',
                'daysinactive' => 'privacy:metadata:atrisk:daysinactive',
                'timemodified' => 'privacy:metadata:atrisk:timemodified',
            ],
            'privacy:metadata:atrisk'
        );

        $collection->add_database_table(
            'local_courseinsights_summary',
            [
                'teachers' => 'privacy:metadata:summary:teachers',
            ],
            'privacy:metadata:summary'
        );

        $collection->add_database_table(
            'local_courseinsights_risk_scores',
            [
                'userid'         => 'privacy:metadata:risk_scores:userid',
                'courseid'       => 'privacy:metadata:risk_scores:courseid',
                'score'          => 'privacy:metadata:risk_scores:score',
                'risklevel'      => 'privacy:metadata:risk_scores:risklevel',
                'reasons'        => 'privacy:metadata:risk_scores:reasons',
                'timecalculated' => 'privacy:metadata:risk_scores:timecalculated',
            ],
            'privacy:metadata:risk_scores'
        );

        $collection->add_database_table(
            'local_courseinsights_interventions',
            [
                'userid'      => 'privacy:metadata:interventions:userid',
                'courseid'    => 'privacy:metadata:interventions:courseid',
                'createdby'   => 'privacy:metadata:interventions:createdby',
                'title'       => 'privacy:metadata:interventions:title',
                'status'      => 'privacy:metadata:interventions:status',
                'riskscore'   => 'privacy:metadata:interventions:riskscore',
                'timecreated' => 'privacy:metadata:interventions:timecreated',
            ],
            'privacy:metadata:interventions'
        );

        $collection->add_database_table(
            'local_courseinsights_intervention_notes',
            [
                'userid'      => 'privacy:metadata:intervention_notes:userid',
                'note'        => 'privacy:metadata:intervention_notes:note',
                'isprivate'   => 'privacy:metadata:intervention_notes:isprivate',
                'timecreated' => 'privacy:metadata:intervention_notes:timecreated',
            ],
            'privacy:metadata:intervention_notes'
        );

        return $collection;
    }

    /**
     * Gets the contexts containing Course Insights data for the supplied user.
     *
     * @param int $userid User ID.
     * @return contextlist Context list.
     */
    public static function get_contexts_for_userid(int $userid): contextlist {
        $contextlist = new contextlist();
        $contextlist->add_from_sql(
            "SELECT ctx.id
               FROM {context} ctx
               JOIN {local_courseinsights_reminders} cir ON cir.courseid = ctx.instanceid
              WHERE ctx.contextlevel = :contextlevel
                AND cir.userid = :userid",
            [
                'contextlevel' => CONTEXT_COURSE,
                'userid' => $userid,
            ]
        );
        $contextlist->add_from_sql(
            "SELECT ctx.id
               FROM {context} ctx
               JOIN {local_courseinsights_atrisk} cia ON cia.courseid = ctx.instanceid
              WHERE ctx.contextlevel = :contextlevel
                AND cia.userid = :userid",
            [
                'contextlevel' => CONTEXT_COURSE,
                'userid' => $userid,
            ]
        );
        $contextlist->add_from_sql(
            "SELECT ctx.id
               FROM {context} ctx
               JOIN {local_courseinsights_risk_scores} rs ON rs.courseid = ctx.instanceid
              WHERE ctx.contextlevel = :contextlevel
                AND rs.userid = :userid",
            [
                'contextlevel' => CONTEXT_COURSE,
                'userid' => $userid,
            ]
        );
        $contextlist->add_from_sql(
            "SELECT ctx.id
               FROM {context} ctx
               JOIN {local_courseinsights_interventions} ci ON ci.courseid = ctx.instanceid
              WHERE ctx.contextlevel = :contextlevel
                AND (ci.userid = :userid OR ci.createdby = :createdby)",
            [
                'contextlevel' => CONTEXT_COURSE,
                'userid'       => $userid,
                'createdby'    => $userid,
            ]
        );
        $contextlist->add_from_sql(
            "SELECT ctx.id
               FROM {context} ctx
               JOIN {local_courseinsights_summary} cis ON cis.courseid = ctx.instanceid
               JOIN {role_assignments} ra ON ra.contextid = ctx.id AND ra.userid = :userid
               JOIN {role} r ON r.id = ra.roleid AND r.archetype = 'editingteacher'
              WHERE ctx.contextlevel = :contextlevel",
            [
                'contextlevel' => CONTEXT_COURSE,
                'userid' => $userid,
            ]
        );

        return $contextlist;
    }

    /**
     * Exports Course Insights data for the approved contexts.
     *
     * @param approved_contextlist $contextlist Approved context list.
     * @return void
     */
    public static function export_user_data(approved_contextlist $contextlist): void {
        global $DB;

        $userid = $contextlist->get_user()->id;
        foreach ($contextlist->get_contexts() as $context) {
            if (!$context instanceof \context_course) {
                continue;
            }

            $records = $DB->get_records(
                'local_courseinsights_reminders',
                [
                    'userid' => $userid,
                    'courseid' => $context->instanceid,
                ],
                'timereminded ASC'
            );

            if (!empty($records)) {
                $data = [];
                foreach ($records as $record) {
                    $data[] = (object) [
                        'courseid' => (int) $record->courseid,
                        'timereminded' => (int) $record->timereminded,
                        'timeremindedformatted' => userdate((int) $record->timereminded),
                    ];
                }

                writer::with_context($context)->export_data(
                    [get_string('privacy:reminders', 'local_courseinsights')],
                    (object) ['reminders' => $data]
                );
            }

            $atriskrecords = $DB->get_records(
                'local_courseinsights_atrisk',
                [
                    'userid' => $userid,
                    'courseid' => $context->instanceid,
                ],
                'threshold ASC'
            );

            if (empty($atriskrecords)) {
                continue;
            }

            $atriskdata = [];
            foreach ($atriskrecords as $record) {
                $atriskdata[] = (object) [
                    'courseid' => (int) $record->courseid,
                    'threshold' => (int) $record->threshold,
                    'lastaccess' => (int) $record->lastaccess,
                    'lastaccessformatted' => !empty($record->lastaccess) ? userdate((int) $record->lastaccess) : '',
                    'daysinactive' => $record->daysinactive !== null ? (int) $record->daysinactive : null,
                    'timemodified' => (int) $record->timemodified,
                    'timemodifiedformatted' => userdate((int) $record->timemodified),
                ];
            }

            writer::with_context($context)->export_data(
                [get_string('privacy:atrisk', 'local_courseinsights')],
                (object) ['atrisk' => $atriskdata]
            );

            $summary = $DB->get_record(
                'local_courseinsights_summary',
                ['courseid' => $context->instanceid],
                'teachers',
                IGNORE_MISSING
            );
            if ($summary && !empty($summary->teachers)) {
                writer::with_context($context)->export_data(
                    [get_string('privacy:summary', 'local_courseinsights')],
                    (object) ['teachers' => $summary->teachers]
                );
            }

            $riskscores = $DB->get_records(
                'local_courseinsights_risk_scores',
                [
                    'userid'   => $userid,
                    'courseid' => $context->instanceid,
                ]
            );
            if (!empty($riskscores)) {
                $riskdata = [];
                foreach ($riskscores as $record) {
                    $riskdata[] = (object) [
                        'courseid'                => (int) $record->courseid,
                        'score'                   => (int) $record->score,
                        'risklevel'               => $record->risklevel,
                        'reasons'                 => $record->reasons,
                        'timecalculated'          => (int) $record->timecalculated,
                        'timecalculatedformatted' => userdate((int) $record->timecalculated),
                    ];
                }
                writer::with_context($context)->export_data(
                    [get_string('privacy:risk_scores', 'local_courseinsights')],
                    (object) ['riskscores' => $riskdata]
                );
            }

            // Export interventions where this user is the student.
            $interventions = $DB->get_records(
                'local_courseinsights_interventions',
                ['userid' => $userid, 'courseid' => $context->instanceid]
            );
            if (!empty($interventions)) {
                $intdata = [];
                foreach ($interventions as $record) {
                    $intdata[] = (object) [
                        'title'        => $record->title,
                        'status'       => $record->status,
                        'riskscore'    => $record->riskscore,
                        'timecreated'  => userdate((int) $record->timecreated),
                    ];
                }
                writer::with_context($context)->export_data(
                    [get_string('privacy:interventions', 'local_courseinsights')],
                    (object) ['interventions' => $intdata]
                );
            }

            // Export notes authored by this user on any intervention in this course.
            $notessql = "SELECT n.id, n.note, n.isprivate, n.timecreated
                           FROM {local_courseinsights_intervention_notes} n
                           JOIN {local_courseinsights_interventions} ci ON ci.id = n.interventionid
                          WHERE n.userid = :userid AND ci.courseid = :courseid";
            $authornotes = $DB->get_records_sql($notessql, ['userid' => $userid, 'courseid' => $context->instanceid]);
            if (!empty($authornotes)) {
                $notedata = [];
                foreach ($authornotes as $record) {
                    $notedata[] = (object) [
                        'note'        => $record->note,
                        'isprivate'   => (bool) $record->isprivate,
                        'timecreated' => userdate((int) $record->timecreated),
                    ];
                }
                writer::with_context($context)->export_data(
                    [get_string('privacy:intervention_notes', 'local_courseinsights')],
                    (object) ['notes' => $notedata]
                );
            }
        }
    }

    /**
     * Deletes Course Insights data for all users in a context.
     *
     * @param \context $context Context to delete from.
     * @return void
     */
    public static function delete_data_for_all_users_in_context(\context $context): void {
        global $DB;

        if (!$context instanceof \context_course) {
            return;
        }

        $DB->delete_records('local_courseinsights_reminders', ['courseid' => $context->instanceid]);
        $DB->delete_records('local_courseinsights_atrisk', ['courseid' => $context->instanceid]);
        $DB->delete_records('local_courseinsights_summary', ['courseid' => $context->instanceid]);
        $DB->delete_records('local_courseinsights_risk_scores', ['courseid' => $context->instanceid]);

        // Delete intervention notes then interventions for this course.
        $intids = $DB->get_fieldset_select(
            'local_courseinsights_interventions',
            'id',
            'courseid = :courseid',
            ['courseid' => $context->instanceid]
        );
        if (!empty($intids)) {
            [$insql, $inparams] = $DB->get_in_or_equal($intids, SQL_PARAMS_NAMED);
            $DB->delete_records_select('local_courseinsights_intervention_notes', "interventionid {$insql}", $inparams);
        }
        $DB->delete_records('local_courseinsights_interventions', ['courseid' => $context->instanceid]);
    }

    /**
     * Deletes Course Insights data for a user in approved contexts.
     *
     * @param approved_contextlist $contextlist Approved context list.
     * @return void
     */
    public static function delete_data_for_user(approved_contextlist $contextlist): void {
        global $DB;

        $userid = $contextlist->get_user()->id;
        foreach ($contextlist->get_contexts() as $context) {
            if ($context instanceof \context_course) {
                $DB->delete_records(
                    'local_courseinsights_reminders',
                    [
                        'userid' => $userid,
                        'courseid' => $context->instanceid,
                    ]
                );
                $DB->delete_records(
                    'local_courseinsights_atrisk',
                    [
                        'userid' => $userid,
                        'courseid' => $context->instanceid,
                    ]
                );
                $DB->delete_records(
                    'local_courseinsights_risk_scores',
                    [
                        'userid'   => $userid,
                        'courseid' => $context->instanceid,
                    ]
                );
                // Delete interventions where this user is the student, plus associated notes.
                $intids = $DB->get_fieldset_select(
                    'local_courseinsights_interventions',
                    'id',
                    'userid = :userid AND courseid = :courseid',
                    ['userid' => $userid, 'courseid' => $context->instanceid]
                );
                if (!empty($intids)) {
                    [$insql, $inparams] = $DB->get_in_or_equal($intids, SQL_PARAMS_NAMED);
                    $DB->delete_records_select('local_courseinsights_intervention_notes', "interventionid {$insql}", $inparams);
                    $DB->delete_records_select('local_courseinsights_interventions', "id {$insql}", $inparams);
                }
                // Delete notes authored by this user in this course's interventions.
                $allintids = $DB->get_fieldset_select(
                    'local_courseinsights_interventions',
                    'id',
                    'courseid = :courseid',
                    ['courseid' => $context->instanceid]
                );
                if (!empty($allintids)) {
                    [$allinsql, $allinparams] = $DB->get_in_or_equal($allintids, SQL_PARAMS_NAMED, 'aid');
                    $allinparams['authorid'] = $userid;
                    $DB->delete_records_select(
                        'local_courseinsights_intervention_notes',
                        "userid = :authorid AND interventionid {$allinsql}",
                        $allinparams
                    );
                }
                $isteacher = $DB->record_exists_sql(
                    "SELECT 1 FROM {role_assignments} ra
                       JOIN {role} r ON r.id = ra.roleid
                      WHERE ra.userid = :userid AND ra.contextid = :ctxid
                        AND r.archetype = 'editingteacher'",
                    ['userid' => $userid, 'ctxid' => $context->id]
                );
                if ($isteacher) {
                    $DB->delete_records('local_courseinsights_summary', ['courseid' => $context->instanceid]);
                }
            }
        }
    }

    /**
     * Adds users with Course Insights data in the supplied context.
     *
     * @param userlist $userlist User list.
     * @return void
     */
    public static function get_users_in_context(userlist $userlist): void {
        $context = $userlist->get_context();
        if (!$context instanceof \context_course) {
            return;
        }

        $userlist->add_from_sql(
            'userid',
            "SELECT userid
               FROM {local_courseinsights_reminders}
              WHERE courseid = :courseid",
            ['courseid' => $context->instanceid]
        );
        $userlist->add_from_sql(
            'userid',
            "SELECT userid
               FROM {local_courseinsights_atrisk}
              WHERE courseid = :courseid",
            ['courseid' => $context->instanceid]
        );
        $userlist->add_from_sql(
            'userid',
            "SELECT userid
               FROM {local_courseinsights_risk_scores}
              WHERE courseid = :courseid",
            ['courseid' => $context->instanceid]
        );
        $userlist->add_from_sql(
            'userid',
            "SELECT userid
               FROM {local_courseinsights_interventions}
              WHERE courseid = :courseid",
            ['courseid' => $context->instanceid]
        );
        $userlist->add_from_sql(
            'userid',
            "SELECT createdby AS userid
               FROM {local_courseinsights_interventions}
              WHERE courseid = :courseid",
            ['courseid' => $context->instanceid]
        );
        $userlist->add_from_sql(
            'userid',
            "SELECT ra.userid
               FROM {local_courseinsights_summary} s
               JOIN {context} ctx ON ctx.instanceid = s.courseid AND ctx.contextlevel = :contextlevel
               JOIN {role_assignments} ra ON ra.contextid = ctx.id
               JOIN {role} r ON r.id = ra.roleid AND r.archetype = 'editingteacher'
              WHERE s.courseid = :courseid",
            ['courseid' => $context->instanceid, 'contextlevel' => CONTEXT_COURSE]
        );
    }

    /**
     * Deletes Course Insights data for approved users in a context.
     *
     * @param approved_userlist $userlist Approved user list.
     * @return void
     */
    public static function delete_data_for_users(approved_userlist $userlist): void {
        global $DB;

        $context = $userlist->get_context();
        if (!$context instanceof \context_course) {
            return;
        }

        $userids = $userlist->get_userids();
        if (empty($userids)) {
            return;
        }

        [$insql, $params] = $DB->get_in_or_equal($userids, SQL_PARAMS_NAMED);
        $params['courseid'] = $context->instanceid;

        $DB->delete_records_select(
            'local_courseinsights_reminders',
            "courseid = :courseid AND userid {$insql}",
            $params
        );
        $DB->delete_records_select(
            'local_courseinsights_atrisk',
            "courseid = :courseid AND userid {$insql}",
            $params
        );
        $DB->delete_records_select(
            'local_courseinsights_risk_scores',
            "courseid = :courseid AND userid {$insql}",
            $params
        );
        // Delete interventions (as student) and associated notes.
        $intids = $DB->get_fieldset_select(
            'local_courseinsights_interventions',
            'id',
            "courseid = :courseid AND userid {$insql}",
            $params
        );
        if (!empty($intids)) {
            [$intidsql, $intidparams] = $DB->get_in_or_equal($intids, SQL_PARAMS_NAMED, 'intid');
            $DB->delete_records_select('local_courseinsights_intervention_notes', "interventionid {$intidsql}", $intidparams);
            $DB->delete_records_select('local_courseinsights_interventions', "id {$intidsql}", $intidparams);
        }
        // Delete notes authored by these users in this course.
        $allintids = $DB->get_fieldset_select(
            'local_courseinsights_interventions',
            'id',
            'courseid = :courseid',
            ['courseid' => $context->instanceid]
        );
        if (!empty($allintids)) {
            [$aidsql, $aidparams] = $DB->get_in_or_equal($allintids, SQL_PARAMS_NAMED, 'caid');
            [$uidsql, $uidparams] = $DB->get_in_or_equal($userids, SQL_PARAMS_NAMED, 'cuid');
            $DB->delete_records_select(
                'local_courseinsights_intervention_notes',
                "interventionid {$aidsql} AND userid {$uidsql}",
                array_merge($aidparams, $uidparams)
            );
        }

        [$teacherinsql, $teacherparams] = $DB->get_in_or_equal($userids, SQL_PARAMS_NAMED, 'tuid');
        $teacherparams['tctxid'] = $context->id;
        $hasaffectedteacher = $DB->record_exists_sql(
            "SELECT 1 FROM {role_assignments} ra
               JOIN {role} r ON r.id = ra.roleid
              WHERE ra.userid {$teacherinsql} AND ra.contextid = :tctxid
                AND r.archetype = 'editingteacher'",
            $teacherparams
        );
        if ($hasaffectedteacher) {
            $DB->delete_records('local_courseinsights_summary', ['courseid' => $context->instanceid]);
        }
    }
}
