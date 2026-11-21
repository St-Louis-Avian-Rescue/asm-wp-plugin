<?php
/**
 * Field mapping and display values.
 *
 * @package ASM_WP_Plugin
 */

/**
 * Tests ASM_WP_Plugin_Animals.
 */
class AnimalsTest extends ASM_WP_Plugin_Test_Case {

	/**
	 * Mapped animals for the fixture feed.
	 *
	 * @return array
	 */
	private function animals() {
		return ASM_WP_Plugin_Animals::map_feed( self::feed() );
	}

	public function test_maps_the_asm_fields() {
		$animals = $this->animals();

		$this->assertSame( array( 101, 102, 12 ), array_keys( $animals ) );
		$this->assertSame(
			array(
				'id'            => 101,
				'name'          => 'Archie',
				'breed'         => 'Beagle',
				'species'       => 'Dog',
				'code'          => 'A2026101',
				'description'   => "A curious dog.\n\nGood with kids.",
				'sex'           => 'Female',
				'size'          => 'Small',
				'special_needs' => false,
				'dob'           => '2020-03-01',
				'dob_estimated' => false,
				'date_in'       => '2026-03-10',
				'fee'           => 12500,
				'bonded'        => array( 102 ),
				'image_count'   => 3,
			),
			$animals[101]
		);
	}

	public function test_keeps_only_public_fields() {
		$stored = wp_json_encode( $this->animals() );

		$this->assertStringNotContainsString( 'Private Person', $stored );
		$this->assertStringNotContainsString( 'Foster Carer', $stored );
		$this->assertStringNotContainsString( 'Internal note', $stored );
	}

	public function test_skips_rows_without_an_id_and_tolerates_missing_fields() {
		$animals = ASM_WP_Plugin_Animals::map_feed(
			array(
				array( 'ANIMALNAME' => 'No ID' ),
				array( 'ID' => 7 ),
			)
		);

		$this->assertSame( array( 7 ), array_keys( $animals ) );
		$this->assertSame( '', $animals[7]['name'] );
		$this->assertSame( '', $animals[7]['dob'] );
		$this->assertSame( 0, $animals[7]['fee'] );
		$this->assertSame( array(), $animals[7]['bonded'] );
	}

	public function test_bad_dates_become_empty() {
		$animals = ASM_WP_Plugin_Animals::map_feed(
			array(
				array(
					'ID'            => 1,
					'DATEOFBIRTH'   => '2021-02-31T00:00:00',
					'DATEBROUGHTIN' => 'yesterday',
				),
			)
		);

		$this->assertSame( '', $animals[1]['dob'] );
		$this->assertSame( '', $animals[1]['date_in'] );
	}

	public function test_text_fields_lose_markup_and_comments_lose_scripts() {
		$zed = $this->animals()[12];

		$this->assertStringNotContainsString( '<', $zed['name'] );
		$this->assertStringNotContainsString( '<script', $zed['description'] );
		$this->assertStringNotContainsString( 'onerror', $zed['description'] );
		$this->assertStringContainsString( '<b>Bold</b>', $zed['description'] );
	}

	/**
	 * @dataProvider data_labels
	 *
	 * @param string $breed    Breed.
	 * @param string $species  Species.
	 * @param string $expected Label.
	 */
	public function test_label( $breed, $species, $expected ) {
		$this->assertSame(
			$expected,
			ASM_WP_Plugin_Animals::label(
				array(
					'breed'   => $breed,
					'species' => $species,
				)
			)
		);
	}

	public function data_labels() {
		return array(
			'breed then species'          => array( 'Beagle', 'Dog', 'Beagle Dog' ),
			'breed is the species'        => array( 'Rabbit', 'Rabbit', 'Rabbit' ),
			'breed already names species' => array( 'Quaker Parrot', 'quaker', 'Quaker Parrot' ),
			'no breed'                    => array( '', 'Finch', 'Finch' ),
			'no species'                  => array( 'Tabby', '', 'Tabby' ),
		);
	}

	public function test_age() {
		$now = strtotime( '2026-11-20 12:00:00 UTC' );

		$this->assertSame( 6, ASM_WP_Plugin_Animals::age_years( '2020-03-01', $now ) );
		$this->assertSame( 5, ASM_WP_Plugin_Animals::age_years( '2020-11-21', $now ) );
		$this->assertSame( 6, ASM_WP_Plugin_Animals::age_years( '2020-11-20', $now ) );
		$this->assertSame( 0, ASM_WP_Plugin_Animals::age_years( '2027-01-01', $now ) );
		$this->assertNull( ASM_WP_Plugin_Animals::age_years( '', $now ) );

		$this->assertSame( '6 years', ASM_WP_Plugin_Animals::age_text( '2020-03-01', $now ) );
		$this->assertSame( '1 year', ASM_WP_Plugin_Animals::age_text( '2025-06-01', $now ) );
		$this->assertSame( 'Less than a year old', ASM_WP_Plugin_Animals::age_text( '2026-06-01', $now ) );
		$this->assertSame( '', ASM_WP_Plugin_Animals::age_text( '', $now ) );
	}

	public function test_money() {
		$before = array(
			'currency_symbol'   => '$',
			'currency_position' => 'before',
		);
		$after  = array(
			'currency_symbol'   => ' €',
			'currency_position' => 'after',
		);

		$this->assertSame( '$125', ASM_WP_Plugin_Animals::format_money( 12500, $before ) );
		$this->assertSame( '$99.99', ASM_WP_Plugin_Animals::format_money( 9999, $before ) );
		$this->assertSame( '$1,250', ASM_WP_Plugin_Animals::format_money( 125000, $before ) );
		$this->assertSame( '125 €', ASM_WP_Plugin_Animals::format_money( 12500, $after ) );
	}

	public function test_bonded_pair_is_linked_both_ways_and_fees_add_up() {
		$animals  = $this->animals();
		$settings = ASM_WP_Plugin_Settings::get_all();

		// Only Archie's row records the bond; Bella must still show it.
		$helios = ASM_WP_Plugin_Animals::present( $animals[102], $animals, $settings, 'https://example.org/adopt/' );
		$aurora = ASM_WP_Plugin_Animals::present( $animals[101], $animals, $settings, 'https://example.org/adopt/' );

		$this->assertSame( array( 101 ), wp_list_pluck( $helios['companions'], 'id' ) );
		$this->assertSame( array( 102 ), wp_list_pluck( $aurora['companions'], 'id' ) );
		$this->assertSame( '$250', $helios['fee_text'] );
		$this->assertSame( '$250', $aurora['fee_text'] );
		$this->assertSame( 'https://example.org/adopt/?animal=102', $aurora['companions'][0]['url'] );
	}

	public function test_a_companion_who_is_not_listed_is_ignored() {
		$animals = $this->animals();
		unset( $animals[102] );

		$aurora = ASM_WP_Plugin_Animals::present( $animals[101], $animals, ASM_WP_Plugin_Settings::get_all(), 'https://example.org/adopt/' );

		$this->assertSame( array(), $aurora['companions'] );
		$this->assertSame( '$125', $aurora['fee_text'] );
	}

	public function test_image_urls_use_the_configured_service_and_account() {
		$settings = ASM_WP_Plugin_Settings::get_all();
		$animals  = $this->animals();
		$aurora   = ASM_WP_Plugin_Animals::present( $animals[101], $animals, $settings, 'https://example.org/adopt/' );

		$this->assertCount( 3, $aurora['images'] );
		$this->assertSame( 'https://asm.test/asmservice?account=zz9999&method=animal_image&animalid=101&seq=2', $aurora['images'][1] );
		$this->assertSame( $aurora['images'][0], $aurora['card_image'] );

		$settings['card_image'] = 'thumbnail';
		$settings['account']    = '';
		$aurora                 = ASM_WP_Plugin_Animals::present( $animals[101], $animals, $settings, 'https://example.org/adopt/' );

		$this->assertSame( 'https://asm.test/asmservice?method=animal_thumbnail&animalid=101', $aurora['card_image'] );
	}

	public function test_apply_and_share_links() {
		$settings               = ASM_WP_Plugin_Settings::get_all();
		$settings['share_text'] = '{name} needs a home @rescue';
		$animals                = $this->animals();
		$zed                    = ASM_WP_Plugin_Animals::present( $animals[12], $animals, $settings, 'https://example.org/adopt/?animal=5&x=1' );

		// The placeholders are URL-encoded, so a code or name cannot break out of the link.
		$this->assertSame( 'https://example.org/apply/?code=A%22B&name=' . rawurlencode( $zed['name'] ), $zed['apply_url'] );
		$this->assertSame( 'https://example.org/adopt/?x=1&animal=12', $zed['url'] );
		$this->assertStringStartsWith( 'https://www.facebook.com/sharer/sharer.php?u=https%3A%2F%2Fexample.org', $zed['share_facebook'] );
		$this->assertStringContainsString( rawurlencode( ' needs a home @rescue' ), $zed['share_x'] );
		$this->assertStringStartsWith( 'mailto:?subject=', $zed['share_email'] );
	}

	public function test_query_filters_sorts_and_limits() {
		$animals = $this->animals();
		$atts    = ASM_WP_Plugin_Renderer::normalize_atts( array() );

		$this->assertSame( array( 101, 102, 12 ), wp_list_pluck( ASM_WP_Plugin_Animals::query( $animals, $atts ), 'id' ), 'By name: Archie, Bella, Zed.' );

		$atts = ASM_WP_Plugin_Renderer::normalize_atts( array( 'species' => ' dog , Cat' ) );
		$this->assertSame( array( 101, 102 ), wp_list_pluck( ASM_WP_Plugin_Animals::query( $animals, $atts ), 'id' ) );

		$atts = ASM_WP_Plugin_Renderer::normalize_atts( array( 'sort' => 'oldest' ) );
		$this->assertSame( array( 102, 101, 12 ), wp_list_pluck( ASM_WP_Plugin_Animals::query( $animals, $atts ), 'id' ), 'No date of birth sorts last.' );

		$atts = ASM_WP_Plugin_Renderer::normalize_atts( array( 'sort' => 'youngest' ) );
		$this->assertSame( array( 101, 102, 12 ), wp_list_pluck( ASM_WP_Plugin_Animals::query( $animals, $atts ), 'id' ) );

		$atts = ASM_WP_Plugin_Renderer::normalize_atts(
			array(
				'sort'  => 'newest',
				'limit' => 2,
			)
		);
		$this->assertSame( array( 101, 102 ), wp_list_pluck( ASM_WP_Plugin_Animals::query( $animals, $atts ), 'id' ), 'Same arrival date falls back to name.' );
	}

	public function test_shortcode_attributes_are_normalised() {
		$atts = ASM_WP_Plugin_Renderer::normalize_atts(
			array(
				'sort'    => 'DROP TABLE',
				'limit'   => '-3',
				'columns' => '99',
				'filters' => 'search, bogus ,sort',
			)
		);

		$this->assertSame( 'name', $atts['sort'] );
		$this->assertSame( 3, $atts['limit'] );
		$this->assertSame( 8, $atts['columns'] );
		$this->assertSame( array( 'search', 'sort' ), $atts['filters'] );

		$this->assertNull( ASM_WP_Plugin_Renderer::normalize_atts( array() )['filters'] );
		$this->assertSame( array(), ASM_WP_Plugin_Renderer::normalize_atts( array( 'filters' => 'no' ) )['filters'] );
		$this->assertSame( ASM_WP_Plugin_Renderer::FILTER_CONTROLS, ASM_WP_Plugin_Renderer::normalize_atts( array( 'filters' => 'yes' ) )['filters'] );
	}
}
