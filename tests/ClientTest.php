<?php
/**
 * Fetching from ASM and caching.
 *
 * @package ASM_WP_Plugin
 */

/**
 * Tests ASM_WP_Plugin_Client.
 */
class ClientTest extends ASM_WP_Plugin_Test_Case {

	public function test_request_goes_to_the_configured_service_with_encoded_credentials() {
		ASM_WP_Plugin_Client::get_data();

		$this->assertCount( 1, $this->requests );
		$this->assertSame(
			'https://asm.test/asmservice?account=zz9999&method=json_adoptable_animals&username=web%20user&password=p%26ss%20w%23rd',
			$this->requests[0]
		);
	}

	public function test_account_is_left_out_for_self_hosted_asm() {
		$settings                = ASM_WP_Plugin_Settings::get_saved();
		$settings['account']     = '';
		$settings['service_url'] = 'https://asm.test/service';
		update_option( ASM_WP_Plugin_Settings::OPTION, $settings );

		ASM_WP_Plugin_Client::get_data();

		$this->assertStringStartsWith( 'https://asm.test/service?method=json_adoptable_animals&username=', end( $this->requests ) );
	}

	public function test_first_call_fetches_and_later_calls_use_the_cache() {
		$first = ASM_WP_Plugin_Client::get_data();

		$this->assertTrue( $first['available'] );
		$this->assertFalse( $first['stale'] );
		$this->assertCount( 3, $first['animals'] );
		$this->assertNotSame( '', $first['hash'] );

		$this->asm_response = array();
		$second             = ASM_WP_Plugin_Client::get_data();

		$this->assertCount( 1, $this->requests, 'The second call must not hit ASM.' );
		$this->assertSame( $first['animals'], $second['animals'] );
	}

	public function test_expired_cache_is_refetched_and_the_hash_follows_the_data() {
		$first = ASM_WP_Plugin_Client::get_data();

		$feed = self::feed();
		array_pop( $feed );
		$this->asm_response = $feed;
		ASM_WP_Plugin_Client::clear();

		$second = ASM_WP_Plugin_Client::get_data();

		$this->assertCount( 2, $this->requests );
		$this->assertCount( 2, $second['animals'] );
		$this->assertNotSame( $first['hash'], $second['hash'] );
	}

	public function test_cache_lifetime_follows_the_setting() {
		$settings                  = ASM_WP_Plugin_Settings::get_saved();
		$settings['cache_minutes'] = 7;
		update_option( ASM_WP_Plugin_Settings::OPTION, $settings );
		$this->requests = array();

		ASM_WP_Plugin_Client::get_data();

		$timeout = (int) get_option( '_transient_timeout_' . ASM_WP_Plugin_Client::FRESH_TRANSIENT );
		$this->assertEqualsWithDelta( time() + 7 * MINUTE_IN_SECONDS, $timeout, 5 );
	}

	public function test_when_asm_is_down_the_last_good_copy_is_served() {
		$good = ASM_WP_Plugin_Client::get_data();

		$this->asm_response = new WP_Error( 'http_request_failed', 'cURL error 28: Operation timed out' );
		ASM_WP_Plugin_Client::clear();
		$stale = ASM_WP_Plugin_Client::get_data();

		$this->assertTrue( $stale['available'] );
		$this->assertTrue( $stale['stale'] );
		$this->assertSame( 'cURL error 28: Operation timed out', $stale['error'] );
		$this->assertSame( $good['animals'], $stale['animals'] );
		$this->assertSame( $good['hash'], $stale['hash'] );
		$this->assertSame( $good['fetched'], $stale['fetched'] );
	}

	public function test_after_a_failure_asm_is_not_retried_straight_away() {
		$this->asm_response = array(
			'code' => 500,
			'body' => 'Server error',
		);

		ASM_WP_Plugin_Client::get_data();
		ASM_WP_Plugin_Client::get_data();
		ASM_WP_Plugin_Client::get_data();

		$this->assertCount( 1, $this->requests );
	}

	public function test_recovers_once_asm_is_back() {
		ASM_WP_Plugin_Client::get_data();
		$this->asm_response = new WP_Error( 'http_request_failed', 'down' );
		ASM_WP_Plugin_Client::clear();
		ASM_WP_Plugin_Client::get_data();

		$this->asm_response = self::feed();
		ASM_WP_Plugin_Client::clear();
		$data = ASM_WP_Plugin_Client::get_data();

		$this->assertFalse( $data['stale'] );
		$this->assertSame( '', $data['error'] );
	}

	public function test_with_no_copy_at_all_nothing_is_available() {
		$this->asm_response = new WP_Error( 'http_request_failed', 'down' );

		$data = ASM_WP_Plugin_Client::get_data();

		$this->assertFalse( $data['available'] );
		$this->assertSame( array(), $data['animals'] );
		$this->assertSame( 'down', $data['error'] );
	}

	/**
	 * @dataProvider data_bad_responses
	 *
	 * @param array $response Raw response.
	 */
	public function test_bad_responses_are_errors( $response ) {
		$this->asm_response = $response;

		$result = ASM_WP_Plugin_Client::fetch();

		$this->assertWPError( $result );
	}

	public function data_bad_responses() {
		return array(
			'wrong password page' => array(
				array(
					'code' => 200,
					'body' => '<html>Invalid username and password</html>',
				),
			),
			'forbidden'           => array(
				array(
					'code' => 403,
					'body' => 'Forbidden',
				),
			),
			'json but not a feed' => array(
				array(
					'code' => 200,
					'body' => '{"error":"nope"}',
				),
			),
			'list of non-animals' => array(
				array(
					'code' => 200,
					'body' => '["a","b"]',
				),
			),
		);
	}

	public function test_an_empty_feed_is_a_valid_answer() {
		$this->asm_response = array();

		$data = ASM_WP_Plugin_Client::get_data();

		$this->assertTrue( $data['available'] );
		$this->assertFalse( $data['stale'] );
		$this->assertSame( array(), $data['animals'] );
	}

	public function test_not_configured_makes_no_request() {
		$settings             = ASM_WP_Plugin_Settings::get_saved();
		$settings['password'] = '';
		update_option( ASM_WP_Plugin_Settings::OPTION, $settings );
		$this->requests = array();

		$result = ASM_WP_Plugin_Client::fetch();

		$this->assertWPError( $result );
		$this->assertSame( 'asm_wp_plugin_not_configured', $result->get_error_code() );
		$this->assertSame( array(), $this->requests );
	}

	public function test_the_password_never_appears_in_an_error() {
		$this->asm_response = new WP_Error(
			'http_request_failed',
			'Could not resolve https://asm.test/asmservice?account=zz9999&method=json_adoptable_animals&username=web%20user&password=p%26ss%20w%23rd'
		);

		$data = ASM_WP_Plugin_Client::get_data();

		$this->assertStringNotContainsString( 'p%26ss', $data['error'] );
		$this->assertStringNotContainsString( 'p&ss', $data['error'] );
		$this->assertStringNotContainsString( 'p&ss', wp_json_encode( get_option( ASM_WP_Plugin_Client::CACHE_OPTION ) ) );
	}

	public function test_the_cache_is_not_autoloaded_and_holds_no_private_fields() {
		ASM_WP_Plugin_Client::get_data();

		$this->assertArrayNotHasKey( ASM_WP_Plugin_Client::CACHE_OPTION, wp_load_alloptions( true ) );
		$this->assertStringNotContainsString( 'Private Person', wp_json_encode( get_option( ASM_WP_Plugin_Client::CACHE_OPTION ) ) );
	}

	public function test_changing_the_account_drops_the_old_copy() {
		ASM_WP_Plugin_Client::get_data();

		$settings            = ASM_WP_Plugin_Settings::get_saved();
		$settings['account'] = 'aa0001';
		update_option( ASM_WP_Plugin_Settings::OPTION, $settings );

		$this->assertFalse( get_option( ASM_WP_Plugin_Client::CACHE_OPTION ) );
	}

	public function test_changing_a_display_setting_keeps_the_copy_but_refetches() {
		ASM_WP_Plugin_Client::get_data();

		$settings            = ASM_WP_Plugin_Settings::get_saved();
		$settings['filters'] = 1;
		update_option( ASM_WP_Plugin_Settings::OPTION, $settings );

		$this->assertIsArray( get_option( ASM_WP_Plugin_Client::CACHE_OPTION ) );
		$this->assertFalse( get_transient( ASM_WP_Plugin_Client::FRESH_TRANSIENT ) );
	}
}
