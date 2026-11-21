<?php
/**
 * Rendered output: escaping, and what the settings form reveals.
 *
 * @package ASM_WP_Plugin
 */

/**
 * Tests the shortcodes, templates and settings page.
 */
class OutputTest extends ASM_WP_Plugin_Test_Case {

	/**
	 * Clears the page URL parameters between tests.
	 */
	public function tear_down() {
		unset( $_GET['animal'] );
		wp_set_current_user( 0 );

		parent::tear_down();
	}

	public function test_listing_shows_every_animal() {
		$html = do_shortcode( '[asm_adoptable]' );

		$this->assertSame( 3, substr_count( $html, 'class="asm-wp-plugin-card"' ) );
		$this->assertStringContainsString( 'Beagle Dog', $html );
		$this->assertStringContainsString( 'data-asm-animal="101"', $html );
		$this->assertMatchesRegularExpression( '/data-hash="[0-9a-f]{32}"/', $html );
		$this->assertStringNotContainsString( 'data-asm-filters ', $html, 'The filter bar is off by default.' );
		$this->assertTrue( wp_script_is( 'asm-wp-plugin', 'enqueued' ) );
		$this->assertTrue( wp_style_is( 'asm-wp-plugin', 'enqueued' ) );
	}

	public function test_markup_from_asm_is_escaped_in_the_listing() {
		$html = do_shortcode( '[asm_adoptable filters="yes"]' );

		$this->assertStringNotContainsString( '<script', $html );
		$this->assertStringNotContainsString( 'alert("name")', $html );
		$this->assertStringNotContainsString( 'onerror', $html );
	}

	public function test_markup_from_asm_is_escaped_in_the_details() {
		$data     = ASM_WP_Plugin_Client::get_data();
		$settings = ASM_WP_Plugin_Settings::get_all();
		$html     = ASM_WP_Plugin_Renderer::render_detail( $data['animals'][12], $data['animals'], $settings, 'https://example.org/adopt/' );

		$this->assertStringNotContainsString( '<script', $html );
		$this->assertStringNotContainsString( 'onerror', $html );
		$this->assertStringContainsString( '<b>Bold</b>', $html, 'Harmless formatting in the description is kept.' );
		$this->assertStringContainsString( 'code=A%22B', $html, 'The shelter code is URL-encoded inside the apply link.' );
		$this->assertStringNotContainsString( 'code=A"B', $html );
	}

	/**
	 * Stores animals directly, as if a site's own filter had added unsafe values after mapping.
	 *
	 * @return array The hostile animal.
	 */
	private function store_hostile_animal() {
		$evil   = '"><img src=x onerror=alert(1)><script>alert(2)</script>';
		$animal = array(
			'id'            => 5,
			'name'          => 'Name' . $evil,
			'breed'         => 'Breed' . $evil,
			'species'       => 'Species' . $evil,
			'code'          => 'Code' . $evil,
			'description'   => 'Text<script>alert(3)</script>',
			'sex'           => 'Sex' . $evil,
			'size'          => 'Size' . $evil,
			'special_needs' => true,
			'dob'           => '2020-01-01' . $evil,
			'dob_estimated' => false,
			'date_in'       => '2024-01-01',
			'fee'           => 100,
			'bonded'        => array( 6 ),
			'image_count'   => 2,
		);
		$friend = array_merge(
			$animal,
			array(
				'id'      => 6,
				'bonded'  => array(),
				// Different values, so the species, sex and size menus are all shown.
				'species' => 'Other' . $evil,
				'sex'     => 'Other' . $evil,
				'size'    => 'Other' . $evil,
			)
		);

		update_option(
			ASM_WP_Plugin_Client::CACHE_OPTION,
			array(
				'animals'  => array(
					5 => $animal,
					6 => $friend,
				),
				'fetched'  => time(),
				'hash'     => 'x',
				'error'    => '',
				'error_at' => 0,
			),
			false
		);
		set_transient( ASM_WP_Plugin_Client::FRESH_TRANSIENT, 'ok', 60 );

		$settings                  = ASM_WP_Plugin_Settings::get_saved();
		$settings['detail_fields'] = array_keys( ASM_WP_Plugin_Settings::detail_field_choices() );
		$settings['apply_label']   = 'Apply' . $evil;
		$settings['share_text']    = '{name}' . $evil;
		update_option( ASM_WP_Plugin_Settings::OPTION, $settings );
		set_transient( ASM_WP_Plugin_Client::FRESH_TRANSIENT, 'ok', 60 );

		return $animal;
	}

	public function test_templates_escape_every_field_themselves() {
		$this->store_hostile_animal();
		$_GET['animal'] = '5';

		// The listing with every filter control, and the details dialog opened on the animal.
		$html = do_shortcode( '[asm_adoptable filters="yes"]' );

		$this->assertStringContainsString( 'asm-wp-plugin-modal is-open', $html );
		$this->assertStringContainsString( 'Name&quot;&gt;&lt;img', $html );
		$this->assertSame( 4, substr_count( $html, '<select' ), 'The species, sex, size and sort menus are all present.' );
		$this->assertStringNotContainsString( '<script', $html );
		$this->assertStringNotContainsString( '<img src=x', $html );
		$this->assertStringNotContainsString( '"><img', $html );
		$this->assertStringNotContainsString( 'onerror=alert(1)>', $html );

		$rest = rest_get_server()->dispatch( new WP_REST_Request( 'GET', '/asm-wp-plugin/v1/grid' ) )->get_data();
		$this->assertStringNotContainsString( '<img src=x', $rest['list'] );
	}

	public function test_a_hostile_apply_link_is_dropped() {
		$clean = ASM_WP_Plugin_Settings::sanitize( array( 'apply_url' => 'javascript:alert(1)//{code}' ) );

		$this->assertSame( '', $clean['apply_url'] );

		// Even if one were stored some other way, the template would not print it.
		$settings              = ASM_WP_Plugin_Settings::get_all();
		$settings['apply_url'] = 'javascript:alert(1)';
		$data                  = ASM_WP_Plugin_Client::get_data();
		$html                  = ASM_WP_Plugin_Renderer::render_detail( $data['animals'][101], $data['animals'], $settings, 'https://example.org/adopt/' );

		$this->assertStringNotContainsString( 'javascript:', $html );
	}

	public function test_details_show_the_expected_facts() {
		$data = ASM_WP_Plugin_Client::get_data();
		$html = ASM_WP_Plugin_Renderer::render_detail( $data['animals'][102], $data['animals'], ASM_WP_Plugin_Settings::get_all(), 'https://example.org/adopt/' );
		$text = preg_replace( '/\s+/', ' ', wp_strip_all_tags( $html ) );

		$this->assertStringContainsString( 'Has special needs', $text );
		$this->assertStringContainsString( 'Adoption fee (together): $250', $text );
		$this->assertStringContainsString( 'Bonded with: Archie', $text );
		$this->assertStringContainsString( 'Sex: Male', $text );
		$this->assertStringContainsString( 'Age (estimated):', $text );
		$this->assertStringNotContainsString( 'Size:', $text, 'Size is not one of the default details.' );
		$this->assertStringContainsString( 'https://example.org/apply/?code=A2026102&#038;name=Bella', $html );
		$this->assertSame( 4, substr_count( $html, 'asm-wp-plugin-share__link' ) - substr_count( $html, 'asm-wp-plugin-share__link--copy' ) );
	}

	public function test_the_fee_can_be_hidden() {
		$settings                  = ASM_WP_Plugin_Settings::get_saved();
		$settings['detail_fields'] = array( 'sex', 'size' );
		update_option( ASM_WP_Plugin_Settings::OPTION, $settings );

		$data = ASM_WP_Plugin_Client::get_data();
		$text = wp_strip_all_tags( ASM_WP_Plugin_Renderer::render_detail( $data['animals'][101], $data['animals'], ASM_WP_Plugin_Settings::get_all(), 'https://example.org/adopt/' ) );

		$this->assertStringNotContainsString( 'Adoption fee', $text );
		$this->assertStringNotContainsString( '$', $text );
		$this->assertStringContainsString( 'Size:', $text );
	}

	public function test_a_link_to_one_animal_opens_their_details() {
		$_GET['animal'] = '102';

		$html = do_shortcode( '[asm_adoptable]' );

		$this->assertStringContainsString( 'asm-wp-plugin-modal is-open', $html );
		$this->assertStringContainsString( '<article class="asm-wp-plugin-detail" data-id="102">', $html );
	}

	public function test_a_link_to_an_animal_who_has_gone_just_shows_the_listing() {
		$_GET['animal'] = '99999';

		$html = do_shortcode( '[asm_adoptable]' );

		$this->assertStringNotContainsString( 'is-open', $html );
		$this->assertSame( 3, substr_count( $html, 'class="asm-wp-plugin-card"' ) );
	}

	public function test_filter_bar_follows_the_setting_and_the_attribute() {
		$this->assertStringContainsString( 'data-asm-filter="q"', do_shortcode( '[asm_adoptable filters="yes"]' ) );
		$this->assertStringNotContainsString( 'data-asm-filter="q"', do_shortcode( '[asm_adoptable filters="species,sort"]' ) );
		$this->assertStringContainsString( 'data-asm-filter="species"', do_shortcode( '[asm_adoptable filters="species,sort"]' ) );

		$settings            = ASM_WP_Plugin_Settings::get_saved();
		$settings['filters'] = 1;
		update_option( ASM_WP_Plugin_Settings::OPTION, $settings );

		$this->assertStringContainsString( 'data-asm-filter="q"', do_shortcode( '[asm_adoptable]' ) );
		$this->assertStringNotContainsString( 'data-asm-filter="q"', do_shortcode( '[asm_adoptable filters="no"]' ) );
	}

	public function test_when_asm_is_down_the_listing_still_shows_the_last_copy() {
		ASM_WP_Plugin_Client::get_data();
		$this->asm_response = new WP_Error( 'http_request_failed', 'down' );
		ASM_WP_Plugin_Client::clear();

		$html = do_shortcode( '[asm_adoptable]' );

		$this->assertSame( 3, substr_count( $html, 'class="asm-wp-plugin-card"' ) );
		$this->assertStringNotContainsString( 'not available right now', $html );
	}

	public function test_with_nothing_to_show_visitors_get_a_plain_message() {
		$this->asm_response = new WP_Error( 'http_request_failed', '<b>boom</b>' );

		$html = do_shortcode( '[asm_adoptable]' );

		$this->assertStringContainsString( 'not available right now', $html );
		$this->assertStringNotContainsString( 'boom', $html, 'Visitors do not see the technical reason.' );

		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );
		ASM_WP_Plugin_Client::clear();
		$html = do_shortcode( '[asm_adoptable]' );

		$this->assertStringContainsString( '&lt;b&gt;boom&lt;/b&gt;', $html, 'Administrators do, escaped.' );
	}

	public function test_settings_keep_the_password_when_the_field_is_left_empty() {
		$clean = ASM_WP_Plugin_Settings::sanitize(
			array(
				'username' => ' new user ',
				'password' => '',
			)
		);

		$this->assertSame( 'new user', $clean['username'] );
		$this->assertSame( 'p&ss w#rd', $clean['password'] );

		$this->assertSame( 'n3w "p<ss', ASM_WP_Plugin_Settings::sanitize( array( 'password' => 'n3w "p<ss' ) )['password'], 'A password is stored exactly as typed.' );
		$this->assertSame( '', ASM_WP_Plugin_Settings::sanitize( array( 'clear_password' => '1' ) )['password'] );
	}

	public function test_settings_are_validated() {
		$clean = ASM_WP_Plugin_Settings::sanitize(
			array(
				'service_url'        => 'ftp://nope',
				'cache_minutes'      => '0',
				'timeout'            => '999',
				'card_image'         => 'bogus',
				'has_display_fields' => '1',
				'detail_fields'      => array( 'fee', 'bogus', 'size' ),
				'currency_position'  => 'sideways',
				'apply_url'          => ' https://example.org/apply/?animal={code}&n={name} ',
				'unknown'            => 'x',
			)
		);

		$this->assertSame( 'https://service.sheltermanager.com/asmservice', $clean['service_url'] );
		$this->assertSame( 1, $clean['cache_minutes'] );
		$this->assertSame( 60, $clean['timeout'] );
		$this->assertSame( 'image', $clean['card_image'] );
		$this->assertSame( array( 'fee', 'size' ), $clean['detail_fields'] );
		$this->assertSame( 0, $clean['filters'], 'An unticked box on the submitted form switches the bar off.' );
		$this->assertSame( 0, $clean['credit'], 'An unticked box on the submitted form switches the credit off.' );
		$this->assertSame( 'before', $clean['currency_position'] );
		$this->assertSame( 'https://example.org/apply/?animal={code}&n={name}', $clean['apply_url'] );
		$this->assertArrayNotHasKey( 'unknown', $clean );
		$this->assertArrayNotHasKey( 'has_display_fields', $clean );
	}

	public function test_the_settings_page_never_prints_the_password() {
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );

		ob_start();
		ASM_WP_Plugin_Admin::render_page();
		$html = ob_get_clean();

		$this->assertStringContainsString( 'name="asm_wp_plugin_settings[password]"', $html );
		$this->assertStringContainsString( 'A password is saved', $html );
		$this->assertStringNotContainsString( 'p&ss', $html );
		$this->assertStringNotContainsString( 'p&amp;ss', $html );
		$this->assertStringNotContainsString( 'w#rd', $html );
	}

	public function test_the_credit_line_is_on_by_default_and_can_be_switched_off() {
		$this->assertSame( 1, ASM_WP_Plugin_Settings::defaults()['credit'] );

		$html = do_shortcode( '[asm_adoptable]' );
		$this->assertStringContainsString( '<p class="asm-wp-plugin-credit">', $html );
		$this->assertStringContainsString( '<a href="' . esc_url( ASM_WP_PLUGIN_HOMEPAGE ) . '" rel="noopener">ASM WP Plugin</a>', $html );

		$settings           = ASM_WP_Plugin_Settings::get_saved();
		$settings['credit'] = 0;
		update_option( ASM_WP_Plugin_Settings::OPTION, $settings );

		$this->assertStringNotContainsString( 'asm-wp-plugin-credit', do_shortcode( '[asm_adoptable]' ) );
	}
}
