<?php
/**
 * Shared set-up for the plugin's tests.
 *
 * @package ASM_WP_Plugin
 */

/**
 * Base test case: configures the plugin and stands in for the ASM service.
 */
abstract class ASM_WP_Plugin_Test_Case extends WP_UnitTestCase {

	/**
	 * URLs requested from "ASM" during the test.
	 *
	 * @var string[]
	 */
	protected $requests = array();

	/**
	 * What the fake ASM service answers: an array of rows, a WP_Error, or
	 * an array with 'code' and 'body' for a raw response.
	 *
	 * @var mixed
	 */
	protected $asm_response = array();

	/**
	 * Configures the plugin and intercepts HTTP.
	 */
	public function set_up() {
		parent::set_up();

		delete_option( ASM_WP_Plugin_Settings::OPTION );
		delete_option( ASM_WP_Plugin_Client::CACHE_OPTION );
		delete_transient( ASM_WP_Plugin_Client::FRESH_TRANSIENT );

		ASM_WP_Plugin_Settings::install(
			array(
				'service_url' => 'https://asm.test/asmservice',
				'account'     => 'zz9999',
				'username'    => 'web user',
				'password'    => 'p&ss w#rd',
				'apply_url'   => 'https://example.org/apply/?code={code}&name={name}',
			)
		);

		$this->requests     = array();
		$this->asm_response = self::feed();

		add_filter( 'pre_http_request', array( $this, 'fake_asm' ), 10, 3 );
	}

	/**
	 * Answers requests to the fake ASM service.
	 *
	 * @param mixed  $pre  Short-circuit value.
	 * @param array  $args Request arguments.
	 * @param string $url  Request URL.
	 * @return mixed
	 */
	public function fake_asm( $pre, $args, $url ) {
		if ( 0 !== strpos( $url, 'https://asm.test/' ) ) {
			return $pre;
		}

		$this->requests[] = $url;

		if ( is_wp_error( $this->asm_response ) ) {
			return $this->asm_response;
		}

		$raw = isset( $this->asm_response['code'], $this->asm_response['body'] );

		return array(
			'headers'  => array(),
			'body'     => $raw ? $this->asm_response['body'] : wp_json_encode( $this->asm_response ),
			'response' => array(
				'code'    => $raw ? $this->asm_response['code'] : 200,
				'message' => 'OK',
			),
			'cookies'  => array(),
			'filename' => null,
		);
	}

	/**
	 * A small ASM feed: a bonded pair, and one row that
	 * tries to inject markup and carries fields that must never be shown.
	 *
	 * @return array
	 */
	public static function feed() {
		return array(
			array(
				'ID'                  => 101,
				'ANIMALNAME'          => 'Archie',
				'BREEDNAME'           => 'Beagle',
				'SPECIESNAME'         => 'Dog',
				'ANIMALCOMMENTS'      => "A curious dog.\n\nGood with kids.",
				'SHELTERCODE'         => 'A2026101',
				'WEBSITEIMAGECOUNT'   => 3,
				'SIZENAME'            => 'Small',
				'BONDEDANIMALID'      => 102,
				'DATEOFBIRTH'         => '2020-03-01T00:00:00',
				'ESTIMATEDDOB'        => 0,
				'DATEBROUGHTIN'       => '2026-03-10T00:00:00',
				'HASSPECIALNEEDS'     => 0,
				'SEXNAME'             => 'Female',
				'FEE'                 => 12500,
				'ORIGINALOWNERNAME'   => 'Private Person',
				'CURRENTOWNERNAME'    => 'Foster Carer',
				'HIDDENANIMALDETAILS' => 'Internal note',
			),
			array(
				'ID'                => 102,
				'ANIMALNAME'        => 'Bella',
				'BREEDNAME'         => 'Beagle',
				'SPECIESNAME'       => 'Dog',
				'ANIMALCOMMENTS'    => 'Gentle and house-trained.',
				'SHELTERCODE'       => 'A2026102',
				'WEBSITEIMAGECOUNT' => 1,
				'SIZENAME'          => 'Small',
				'BONDEDANIMALID'    => 0,
				'DATEOFBIRTH'       => '2019-06-15T00:00:00',
				'ESTIMATEDDOB'      => 1,
				'DATEBROUGHTIN'     => '2026-03-10T00:00:00',
				'HASSPECIALNEEDS'   => 1,
				'SEXNAME'           => 'Male',
				'FEE'               => 12500,
			),
			array(
				'ID'                => 12,
				'ANIMALNAME'        => 'Zed <script>alert("name")</script>',
				'BREEDNAME'         => 'Rabbit',
				'SPECIESNAME'       => 'Rabbit',
				'ANIMALCOMMENTS'    => 'Sweet.</script><script>alert("comments")</script><img src=x onerror=alert(1)> <b>Bold</b>',
				'SHELTERCODE'       => 'A"B',
				'WEBSITEIMAGECOUNT' => 0,
				'SIZENAME'          => 'Medium',
				'BONDEDANIMALID'    => 0,
				'DATEOFBIRTH'       => null,
				'ESTIMATEDDOB'      => 0,
				'DATEBROUGHTIN'     => '2025-01-05T00:00:00',
				'HASSPECIALNEEDS'   => 0,
				'SEXNAME'           => 'Unknown',
				'FEE'               => 9999,
			),
		);
	}
}
