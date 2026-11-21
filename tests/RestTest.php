<?php
/**
 * The REST routes used by the page script.
 *
 * @package ASM_WP_Plugin
 */

/**
 * Tests ASM_WP_Plugin_REST.
 */
class RestTest extends ASM_WP_Plugin_Test_Case {

	/**
	 * Sends a GET request to one of the plugin's routes.
	 *
	 * @param string $path   Route below /asm-wp-plugin/v1.
	 * @param array  $params Query parameters.
	 * @return WP_REST_Response
	 */
	private function get( $path, array $params = array() ) {
		$request = new WP_REST_Request( 'GET', '/asm-wp-plugin/v1' . $path );
		$request->set_query_params( $params );

		return rest_get_server()->dispatch( $request );
	}

	public function test_status_hash_matches_the_rendered_listing() {
		$html   = do_shortcode( '[asm_adoptable]' );
		$status = $this->get( '/status' );

		$this->assertSame( 200, $status->get_status() );
		$this->assertSame( 3, $status->get_data()['count'] );
		$this->assertStringContainsString( 'data-hash="' . $status->get_data()['hash'] . '"', $html );
	}

	public function test_status_hash_changes_when_an_animal_leaves() {
		$before = $this->get( '/status' )->get_data()['hash'];

		$feed = self::feed();
		array_pop( $feed );
		$this->asm_response = $feed;
		ASM_WP_Plugin_Client::clear();

		$this->assertNotSame( $before, $this->get( '/status' )->get_data()['hash'] );
	}

	public function test_status_hash_changes_with_a_display_setting() {
		$before = $this->get( '/status' )->get_data()['hash'];

		$settings                    = ASM_WP_Plugin_Settings::get_saved();
		$settings['currency_symbol'] = '£';
		update_option( ASM_WP_Plugin_Settings::OPTION, $settings );

		$this->assertNotSame( $before, $this->get( '/status' )->get_data()['hash'] );
	}

	public function test_responses_ask_not_to_be_cached() {
		foreach ( array( '/status', '/animals', '/animals/101', '/animals/1', '/grid' ) as $path ) {
			$headers = $this->get( $path )->get_headers();
			$this->assertSame( 'no-store', $headers['Cache-Control'], $path );
		}
	}

	public function test_animals_route_serves_only_public_fields() {
		$data = $this->get( '/animals' )->get_data();
		$json = wp_json_encode( $data );

		$this->assertCount( 3, $data['animals'] );
		$this->assertSame(
			array( 'id', 'name', 'breed', 'species', 'code', 'description', 'sex', 'size', 'special_needs', 'dob', 'dob_estimated', 'date_in', 'fee', 'bonded', 'image_count', 'label', 'images', 'thumbnail', 'age_years' ),
			array_keys( $data['animals'][0] )
		);
		$this->assertStringNotContainsString( 'Private Person', $json );
		$this->assertStringNotContainsString( 'web user', $json );
		$this->assertStringNotContainsString( 'p&ss', $json );
		$this->assertStringNotContainsString( 'p%26ss', $json );
	}

	public function test_one_animal_comes_with_rendered_details() {
		$response = $this->get( '/animals/101', array( 'base' => 'http://example.org/adopt/?x=1#top' ) );
		$data     = $response->get_data();

		$this->assertSame( 200, $response->get_status() );
		$this->assertSame( 'Archie', $data['animal']['name'] );
		$this->assertStringContainsString( '<article class="asm-wp-plugin-detail" data-id="101">', $data['html'] );
		$this->assertStringContainsString( 'href="http://example.org/adopt/?animal=102"', $data['html'], 'Links are built on the page the listing is on.' );
	}

	public function test_links_never_point_at_another_site() {
		$data = $this->get( '/animals/101', array( 'base' => 'https://evil.example/adopt/' ) )->get_data();

		$this->assertStringNotContainsString( 'evil.example', $data['html'] );
		$this->assertStringContainsString( 'href="' . home_url( '/' ) . '?animal=102"', $data['html'] );
	}

	public function test_an_animal_who_has_gone_is_a_404() {
		$this->assertSame( 404, $this->get( '/animals/99999' )->get_status() );
	}

	public function test_grid_honours_the_listing_attributes() {
		$data = $this->get(
			'/grid',
			array(
				'species' => 'Dog',
				'sort'    => 'oldest',
				'filters' => 'all',
			)
		)->get_data();

		$this->assertSame( 2, $data['count'] );
		$this->assertSame( 2, substr_count( $data['list'], 'class="asm-wp-plugin-card"' ) );
		$this->assertLessThan( strpos( $data['list'], 'data-id="101"' ), strpos( $data['list'], 'data-id="102"' ), 'Oldest first puts Bella before Archie.' );
		$this->assertStringContainsString( 'data-asm-filter="q"', $data['filters'] );
		$this->assertSame( $this->get( '/status' )->get_data()['hash'], $data['hash'] );

		$this->assertSame( '', $this->get( '/grid', array( 'filters' => 'none' ) )->get_data()['filters'] );
	}
}
