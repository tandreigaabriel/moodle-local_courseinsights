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
 * Admin settings page.
 *
 * @package    local_courseinsights
 * @copyright  2026 Andrei Toma
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

if (!class_exists('admin_setting_courseinsights_licensekey')) {
    /**
     * Custom admin setting for the Course Insights license key.
     *
     * Triggers activation on save and displays a live status badge below the input.
     */
    class admin_setting_courseinsights_licensekey extends admin_setting_configtext {
        /**
         * Save the key and immediately attempt activation.
         *
         * @param string $data Value submitted by the form.
         * @return string Empty string on success, error string on failure.
         */
        public function write_setting($data) {
            $result = parent::write_setting($data);
            if ($result === '') {
                \local_courseinsights\license::activate($data);
            }
            return $result;
        }

        /**
         * Render the setting HTML with a live status badge appended.
         *
         * @param string $data Current saved value.
         * @param string $query Search query (for highlighting).
         * @return string HTML.
         */
        public function output_html($data, $query = '') {
            $html = parent::output_html($data, $query);

            $status = \local_courseinsights\license::get_status();
            $info   = \local_courseinsights\license::get_info();

            $badges = [
                \local_courseinsights\license::STATUS_VALID =>
                    ['success', get_string('license_status_valid', 'local_courseinsights')],
                \local_courseinsights\license::STATUS_GRACE =>
                    ['warning', get_string('license_status_grace', 'local_courseinsights')],
                \local_courseinsights\license::STATUS_EXPIRED =>
                    ['danger', get_string('license_status_expired', 'local_courseinsights')],
                \local_courseinsights\license::STATUS_UNLICENSED =>
                    ['secondary', get_string('license_status_unlicensed', 'local_courseinsights')],
            ];

            [$cls, $label] = $badges[$status] ?? ['secondary', $status];

            $badge = '<span class="badge badge-' . s($cls) . ' p-2" style="font-size:0.85em">' . s($label) . '</span>';

            $detail = '';
            if ($info) {
                $plan = !empty($info->plan) ? ucfirst($info->plan) : '';
                if ($plan) {
                    $detail .= ' &nbsp;<strong>'
                        . get_string('license_plan', 'local_courseinsights')
                        . ':</strong> ' . s($plan);
                }
                if (!empty($info->expires_at)) {
                    $detail .= ' &nbsp;<strong>'
                        . get_string('license_expires', 'local_courseinsights')
                        . ':</strong> '
                        . userdate($info->expires_at, get_string('strftimedate', 'langconfig'));
                }
                if (!empty($info->domain)) {
                    $detail .= ' &nbsp;<strong>' . get_string('license_domain', 'local_courseinsights') . ':</strong> '
                    . s($info->domain);
                }
                if (!empty($info->local)) {
                    $detail .= ' &nbsp;<span class="text-muted small">'
                        . get_string('license_local_note', 'local_courseinsights')
                        . '</span>';
                }
            }

            return $html . '<div class="mt-2 mb-1">' . $badge . $detail . '</div>';
        }
    }
} // end class_exists guard

if ($hassiteconfig) {
    $ADMIN->add('reports', new admin_externalpage(
        'local_courseinsights',
        get_string('pluginname', 'local_courseinsights'),
        new moodle_url('/local/courseinsights/index.php'),
        'local/courseinsights:view'
    ));

    $settings = new admin_settingpage(
        'local_courseinsights_settings',
        get_string('settingspage', 'local_courseinsights')
    );

    // Marketplace / Terms of Use notice.
    $marketplacenotice = '<div class="alert alert-info" role="alert">'
        . get_string('marketplace_notice', 'local_courseinsights') . '<br><br>'
        . '<a href="https://tandreig.com/terms-and-conditions" target="_blank" rel="noopener">'
        . get_string('marketplace_terms_link', 'local_courseinsights') . '</a>'
        . '&nbsp;&nbsp;<a href="https://tandreig.com/privacy-policy" target="_blank" rel="noopener">'
        . get_string('marketplace_privacy_link', 'local_courseinsights') . '</a>'
        . '</div>';

    $settings->add(new admin_setting_heading(
        'local_courseinsights/marketplace_notice',
        '',
        $marketplacenotice
    ));

    // Licence.
    $settings->add(new admin_setting_heading(
        'local_courseinsights/license_heading',
        get_string('settings_license', 'local_courseinsights'),
        get_string('settings_license_desc', 'local_courseinsights')
    ));

    $settings->add(new admin_setting_courseinsights_licensekey(
        'local_courseinsights/license_key',
        get_string('settings_license_key', 'local_courseinsights'),
        get_string('settings_license_key_desc', 'local_courseinsights'),
        '',
        PARAM_TEXT
    ));

    // General.
    $settings->add(new admin_setting_configtext(
        'local_courseinsights/miniexamkeywords',
        get_string('miniexamkeywords', 'local_courseinsights'),
        get_string('miniexamkeywords_desc', 'local_courseinsights'),
        get_string('miniexamkeywords_default', 'local_courseinsights'),
        PARAM_TEXT
    ));

    $settings->add(new admin_setting_configtext(
        'local_courseinsights/examkeywords',
        get_string('examkeywords', 'local_courseinsights'),
        get_string('examkeywords_desc', 'local_courseinsights'),
        get_string('examkeywords_default', 'local_courseinsights'),
        PARAM_TEXT
    ));

    $settings->add(new admin_setting_configtext(
        'local_courseinsights/studentroleids',
        get_string('studentroleids', 'local_courseinsights'),
        get_string('studentroleids_desc', 'local_courseinsights'),
        get_string('studentroleids_default', 'local_courseinsights'),
        PARAM_TEXT
    ));

    $settings->add(new admin_setting_configcheckbox(
        'local_courseinsights/enablecache',
        get_string('enablecache', 'local_courseinsights'),
        get_string('enablecache_desc', 'local_courseinsights'),
        0
    ));

    $settings->add(new admin_setting_configtext(
        'local_courseinsights/coursesperpage',
        get_string('coursesperpage', 'local_courseinsights'),
        get_string('coursesperpage_desc', 'local_courseinsights'),
        12,
        PARAM_INT
    ));

    $settings->add(new admin_setting_heading(
        'local_courseinsights/brandheading',
        get_string('brandheading', 'local_courseinsights'),
        ''
    ));

    $settings->add(new admin_setting_configtext(
        'local_courseinsights/brandname',
        get_string('brandname', 'local_courseinsights'),
        get_string('brandname_desc', 'local_courseinsights'),
        '',
        PARAM_TEXT
    ));

    $settings->add(new admin_setting_configtext(
        'local_courseinsights/brandlogourl',
        get_string('brandlogourl', 'local_courseinsights'),
        get_string('brandlogourl_desc', 'local_courseinsights'),
        '',
        PARAM_URL
    ));

    $settings->add(new admin_setting_configtext(
        'local_courseinsights/brandaccentcolor',
        get_string('brandaccentcolor', 'local_courseinsights'),
        get_string('brandaccentcolor_desc', 'local_courseinsights'),
        '',
        PARAM_TEXT
    ));

    $settings->add(new admin_setting_heading(
        'local_courseinsights/alertsheading',
        get_string('alertsheading', 'local_courseinsights'),
        ''
    ));

    $settings->add(new admin_setting_configcheckbox(
        'local_courseinsights/alertsenabled',
        get_string('alertsenabled', 'local_courseinsights'),
        get_string('alertsenabled_desc', 'local_courseinsights'),
        0
    ));

    $settings->add(new admin_setting_configtext(
        'local_courseinsights/alertcompletionthreshold',
        get_string('alertcompletionthreshold', 'local_courseinsights'),
        get_string('alertcompletionthreshold_desc', 'local_courseinsights'),
        50,
        PARAM_INT
    ));

    $settings->add(new admin_setting_configtext(
        'local_courseinsights/alertinactivedays',
        get_string('alertinactivedays', 'local_courseinsights'),
        get_string('alertinactivedays_desc', 'local_courseinsights'),
        30,
        PARAM_INT
    ));

    $settings->add(new admin_setting_heading(
        'local_courseinsights/studentreminderheading',
        get_string('studentreminderheading', 'local_courseinsights'),
        ''
    ));

    $settings->add(new admin_setting_configcheckbox(
        'local_courseinsights/studentreminderenabled',
        get_string('studentreminderenabled', 'local_courseinsights'),
        get_string('studentreminderenabled_desc', 'local_courseinsights'),
        0
    ));

    $settings->add(new admin_setting_configtext(
        'local_courseinsights/studentinactivitydays',
        get_string('studentinactivitydays', 'local_courseinsights'),
        get_string('studentinactivitydays_desc', 'local_courseinsights'),
        14,
        PARAM_INT
    ));

    $settings->add(new admin_setting_heading(
        'local_courseinsights/digestheading',
        get_string('digestheading', 'local_courseinsights'),
        ''
    ));

    $settings->add(new admin_setting_configcheckbox(
        'local_courseinsights/digestenabled',
        get_string('digestenabled', 'local_courseinsights'),
        get_string('digestenabled_desc', 'local_courseinsights'),
        0
    ));

    $settings->add(new admin_setting_configselect(
        'local_courseinsights/digestfrequency',
        get_string('digestfrequency', 'local_courseinsights'),
        get_string('digestfrequency_desc', 'local_courseinsights'),
        'weekly',
        [
            'weekly'  => get_string('digestfrequency_weekly', 'local_courseinsights'),
            'monthly' => get_string('digestfrequency_monthly', 'local_courseinsights'),
        ]
    ));

    $settings->add(new admin_setting_heading(
        'local_courseinsights/webhookheading',
        get_string('webhookheading', 'local_courseinsights'),
        ''
    ));

    $settings->add(new admin_setting_configtext(
        'local_courseinsights/webhookurl',
        get_string('webhookurl', 'local_courseinsights'),
        get_string('webhookurl_desc', 'local_courseinsights'),
        '',
        PARAM_URL
    ));

    $settings->add(new admin_setting_configpasswordunmask(
        'local_courseinsights/webhookapikey',
        get_string('webhookapikey', 'local_courseinsights'),
        get_string('webhookapikey_desc', 'local_courseinsights'),
        ''
    ));

    $settings->add(new admin_setting_heading(
        'local_courseinsights/msgtemplatesheading',
        get_string('msgtemplatesheading', 'local_courseinsights'),
        get_string('msgtemplatesheading_desc', 'local_courseinsights')
    ));

    $settings->add(new admin_setting_configtext(
        'local_courseinsights/tmpl1_subject',
        get_string('tmpl1_subject', 'local_courseinsights'),
        get_string('tmpl1_subject_desc', 'local_courseinsights'),
        get_string('tmpl1_subject_default', 'local_courseinsights'),
        PARAM_TEXT
    ));

    $settings->add(new admin_setting_configtextarea(
        'local_courseinsights/tmpl1_body',
        get_string('tmpl1_body', 'local_courseinsights'),
        get_string('tmpl1_body_desc', 'local_courseinsights'),
        get_string('tmpl1_body_default', 'local_courseinsights'),
        PARAM_TEXT
    ));

    $settings->add(new admin_setting_configtext(
        'local_courseinsights/tmpl2_subject',
        get_string('tmpl2_subject', 'local_courseinsights'),
        get_string('tmpl2_subject_desc', 'local_courseinsights'),
        get_string('tmpl2_subject_default', 'local_courseinsights'),
        PARAM_TEXT
    ));

    $settings->add(new admin_setting_configtextarea(
        'local_courseinsights/tmpl2_body',
        get_string('tmpl2_body', 'local_courseinsights'),
        get_string('tmpl2_body_desc', 'local_courseinsights'),
        get_string('tmpl2_body_default', 'local_courseinsights'),
        PARAM_TEXT
    ));

    $ADMIN->add('localplugins', $settings);
}
