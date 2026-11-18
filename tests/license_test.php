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
 * PHPUnit tests for the license gate.
 *
 * These tests verify that license::get_status() correctly identifies valid,
 * grace-period, expired, and unlicensed states by writing token data directly
 * to config_plugins — no network calls are made.
 *
 * If COURSEINSIGHTS_LICENSE_DEV is defined in config.php (local dev bypass),
 * all status-logic tests are skipped automatically.
 *
 * @package    local_courseinsights
 * @copyright  2026 Andrei Toma
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_courseinsights;

/**
 * Tests for \local_courseinsights\license.
 *
 * @covers \local_courseinsights\license
 */
final class license_test extends \advanced_testcase {
    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
    }

    /**
     * Skip status-logic tests when the dev bypass constant is active, because
     * get_status() short-circuits to STATUS_VALID before reading any token.
     */
    private function skip_if_dev_bypass(): void {
        if (defined('COURSEINSIGHTS_LICENSE_DEV') && COURSEINSIGHTS_LICENSE_DEV) {
            $this->markTestSkipped(
                'COURSEINSIGHTS_LICENSE_DEV is defined in config.php; licence status tests are skipped in dev-bypass mode.'
            );
        }
    }

    /**
     * Stores a test license token with optional field overrides.
     *
     * @param array $overrides Key-value pairs to override default token fields.
     */
    private function store_token(array $overrides = []): void {
        $token = array_merge([
            'key'          => 'CINS-TEST-1234-5678-ABCD',
            'plan'         => 'paid',
            'domain'       => 'localhost',
            'expires_at'   => time() + 86400 * 30,
            'grace_until'  => time() + 86400 * 37,
            'activated_at' => time() - 86400,
            'local'        => false,
        ], $overrides);
        set_config('license_token', json_encode((object)$token), 'local_courseinsights');
    }

    /**
     * STATUS_UNLICENSED when config is empty.
     */
    public function test_status_unlicensed_when_config_empty(): void {
        $this->skip_if_dev_bypass();
        set_config('license_token', '', 'local_courseinsights');
        $this->assertEquals(
            \local_courseinsights\license::STATUS_UNLICENSED,
            \local_courseinsights\license::get_status()
        );
    }

    /**
     * STATUS_UNLICENSED when config contains an empty JSON object with no expires_at.
     */
    public function test_status_unlicensed_for_token_missing_expiry(): void {
        $this->skip_if_dev_bypass();
        set_config('license_token', json_encode((object)['key' => 'CINS-XXXX']), 'local_courseinsights');
        $this->assertEquals(
            \local_courseinsights\license::STATUS_UNLICENSED,
            \local_courseinsights\license::get_status()
        );
    }

    /**
     * STATUS_UNLICENSED for malformed (non-JSON) token data.
     */
    public function test_status_unlicensed_for_malformed_json(): void {
        $this->skip_if_dev_bypass();
        set_config('license_token', 'not-valid-json', 'local_courseinsights');
        $this->assertEquals(
            \local_courseinsights\license::STATUS_UNLICENSED,
            \local_courseinsights\license::get_status()
        );
    }

    /**
     * STATUS_VALID when token has a future expiry.
     */
    public function test_status_valid_for_future_expiry(): void {
        $this->skip_if_dev_bypass();
        $this->store_token([
            'expires_at'  => time() + 86400 * 30,
            'grace_until' => time() + 86400 * 37,
        ]);
        $this->assertEquals(
            \local_courseinsights\license::STATUS_VALID,
            \local_courseinsights\license::get_status()
        );
    }

    /**
     * STATUS_VALID for a token expiring in exactly one second.
     */
    public function test_status_valid_expiry_one_second_from_now(): void {
        $this->skip_if_dev_bypass();
        $this->store_token([
            'expires_at'  => time() + 1,
            'grace_until' => time() + 86400,
        ]);
        $this->assertEquals(
            \local_courseinsights\license::STATUS_VALID,
            \local_courseinsights\license::get_status()
        );
    }

    /**
     * STATUS_GRACE when token is expired but grace_until is in the future.
     */
    public function test_status_grace_within_grace_window(): void {
        $this->skip_if_dev_bypass();
        $this->store_token([
            'expires_at'  => time() - 86400, // Expired yesterday.
            'grace_until' => time() + 86400 * 6, // Grace ends in 6 days.
        ]);
        $this->assertEquals(
            \local_courseinsights\license::STATUS_GRACE,
            \local_courseinsights\license::get_status()
        );
    }

    /**
     * STATUS_EXPIRED when both expires_at and grace_until are in the past.
     */
    public function test_status_expired_after_grace_window(): void {
        $this->skip_if_dev_bypass();
        $this->store_token([
            'expires_at'  => time() - 86400 * 30,
            'grace_until' => time() - 86400 * 23,
        ]);
        $this->assertEquals(
            \local_courseinsights\license::STATUS_EXPIRED,
            \local_courseinsights\license::get_status()
        );
    }

    /**
     * STATUS_EXPIRED when token has no grace_until field (treated as 0).
     */
    public function test_status_expired_when_no_grace_until_field(): void {
        $this->skip_if_dev_bypass();
        $token = (object)[
            'key'        => 'CINS-TEST',
            'expires_at' => time() - 86400,
            // Intentionally omitted: grace_until.
        ];
        set_config('license_token', json_encode($token), 'local_courseinsights');
        $this->assertEquals(
            \local_courseinsights\license::STATUS_EXPIRED,
            \local_courseinsights\license::get_status()
        );
    }

    /**
     * Returns null when no token is stored.
     */
    public function test_get_info_returns_null_when_empty(): void {
        set_config('license_token', '', 'local_courseinsights');
        $this->assertNull(\local_courseinsights\license::get_info());
    }

    /**
     * Returns the decoded token object when a valid token is stored.
     */
    public function test_get_info_returns_decoded_token(): void {
        $this->store_token(['key' => 'CINS-INFO-TEST', 'plan' => 'paid']);
        $info = \local_courseinsights\license::get_info();
        $this->assertNotNull($info);
        $this->assertEquals('CINS-INFO-TEST', $info->key);
        $this->assertEquals('paid', $info->plan);
    }

    /**
     * get_info returns null for malformed JSON (does not throw).
     */
    public function test_get_info_returns_null_for_malformed_json(): void {
        set_config('license_token', 'broken-json-{{{', 'local_courseinsights');
        // JSON decode returns null for invalid JSON; get_info() should return null too.
        $info = \local_courseinsights\license::get_info();
        $this->assertNull($info);
    }

    /**
     * renew() returns false when no token is stored.
     */
    public function test_renew_returns_false_when_no_token(): void {
        set_config('license_token', '', 'local_courseinsights');
        $this->assertFalse(\local_courseinsights\license::renew());
    }

    /**
     * renew() returns true without contacting the server when expiry is >7 days away.
     */
    public function test_renew_returns_true_when_not_due(): void {
        $this->store_token(['expires_at' => time() + 86400 * 30]);
        $result = \local_courseinsights\license::renew();
        $this->assertTrue($result, 'renew() should return true immediately when expiry is >7 days away.');
    }
}
