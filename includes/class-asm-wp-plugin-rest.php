<?php
/**
 * REST routes used by the page script.
 *
 * @package ASM_WP_Plugin
 */

defined( 'ABSPATH' ) || exit;

/**
 * Serves the cached animals under /wp-json/asm-wp-plugin/v1/.
 *
 * Pages are often held in a full-page cache for hours or days. These routes
 * let the page script find out that its listing is out of date and replace
 * it, and load an animal's details when a visitor opens them. Every response
 * asks not to be cached.
 */
final class ASM_WP_Plugin_REST {

	const ROUTE_NAMESPACE = 'asm-wp-plugin/v1';

	/**
	 * Hooks.
	 */
	public static function register() {
		add_action( 'rest_api_init', array( __CLASS__, 'register_routes' ) );
	}

	/**
	 * Registers the routes. All are public and read-only.
	 */
	public static function register_routes() {
		$listing_args = array(
			'species' => array(
				'type'              => 'string',
				'default'           => '',
				'sanitize_callback' => 'sanitize_text_field',
			),
			'sort'    => array(
				'type'              => 'string',
				'default'           => 'name',
				'sanitize_callback' => 'sanitize_key',
			),
			'limit'   => array(
				'type'              => 'integer',
				'default'           => 0,
				'sanitize_callback' => 'absint',
			),
			'filters' => array(
				'type'              => 'string',
				'default'           => '',
				'sanitize_callback' => 'sanitize_text_field',
			),
			'base'    => array(
				'type'              => 'string',
				'default'           => '',
				'sanitize_callback' => 'esc_url_raw',
			),
		);

		register_rest_route(
			self::ROUTE_NAMESPACE,
			'/status',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( __CLASS__, 'get_status' ),
				'permission_callback' => '__return_true',
			)
		);

		register_rest_route(
			self::ROUTE_NAMESPACE,
			'/animals',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( __CLASS__, 'get_animals' ),
				'permission_callback' => '__return_true',
				'args'                => array_diff_key( $listing_args, array( 'filters' => 1 ) ),
			)
		);

		register_rest_route(
			self::ROUTE_NAMESPACE,
			'/animals/(?P<id>\d+)',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( __CLASS__, 'get_animal' ),
				'permission_callback' => '__return_true',
				'args'                => array(
					'id'   => array(
						'type'              => 'integer',
						'sanitize_callback' => 'absint',
					),
					'base' => $listing_args['base'],
				),
			)
		);

		register_rest_route(
			self::ROUTE_NAMESPACE,
			'/grid',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( __CLASS__, 'get_grid' ),
				'permission_callback' => '__return_true',
				'args'                => $listing_args,
			)
		);
	}

	/**
	 * GET /status: the fingerprint of the current listing.
	 *
	 * @return WP_REST_Response
	 */
	public static function get_status() {
		$data = ASM_WP_Plugin_Client::get_data();

		return self::respond(
			array(
				'hash'    => ASM_WP_Plugin_Renderer::hash( $data ),
				'count'   => count( $data['animals'] ),
				'fetched' => $data['fetched'],
				'stale'   => $data['stale'],
			)
		);
	}

	/**
	 * GET /animals: the public fields of every animal.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response
	 */
	public static function get_animals( WP_REST_Request $request ) {
		$data     = ASM_WP_Plugin_Client::get_data();
		$settings = ASM_WP_Plugin_Settings::get_all();
		$atts     = ASM_WP_Plugin_Renderer::normalize_atts( $request->get_params() );
		$animals  = array();

		foreach ( ASM_WP_Plugin_Animals::query( $data['animals'], $atts ) as $animal ) {
			$animals[] = ASM_WP_Plugin_Animals::to_public( $animal, $data['animals'], $settings );
		}

		return self::respond(
			array(
				'hash'    => ASM_WP_Plugin_Renderer::hash( $data ),
				'fetched' => $data['fetched'],
				'stale'   => $data['stale'],
				'animals' => $animals,
			)
		);
	}

	/**
	 * GET /animals/<id>: one animal, with its details rendered by the detail template.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public static function get_animal( WP_REST_Request $request ) {
		$data = ASM_WP_Plugin_Client::get_data();
		$id   = (int) $request['id'];

		if ( ! isset( $data['animals'][ $id ] ) ) {
			$error = new WP_Error( 'asm_wp_plugin_not_found', __( 'This animal is no longer listed.', 'asm-wp-plugin' ), array( 'status' => 404 ) );
			$error = rest_convert_error_to_response( $error );
			$error->header( 'Cache-Control', 'no-store' );

			return $error;
		}

		$settings = ASM_WP_Plugin_Settings::get_all();
		$animal   = $data['animals'][ $id ];

		return self::respond(
			array(
				'animal' => ASM_WP_Plugin_Animals::to_public( $animal, $data['animals'], $settings ),
				'html'   => ASM_WP_Plugin_Renderer::render_detail( $animal, $data['animals'], $settings, self::base_url( $request ) ),
			)
		);
	}

	/**
	 * GET /grid: the cards and filter bar, rendered by the theme's templates.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response
	 */
	public static function get_grid( WP_REST_Request $request ) {
		$data  = ASM_WP_Plugin_Client::get_data();
		$atts  = ASM_WP_Plugin_Renderer::normalize_atts( $request->get_params() );
		$parts = ASM_WP_Plugin_Renderer::render_parts( $atts, $data, ASM_WP_Plugin_Settings::get_all(), self::base_url( $request ) );

		return self::respond(
			array(
				'hash'    => ASM_WP_Plugin_Renderer::hash( $data ),
				'count'   => $parts['count'],
				'list'    => $parts['list'],
				'filters' => $parts['filters'],
			)
		);
	}

	/**
	 * The page the listing is on, as reported by the page script.
	 *
	 * Links are built from it, so anything not on this site is ignored.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return string
	 */
	private static function base_url( WP_REST_Request $request ) {
		$base = (string) $request['base'];
		$home = home_url( '/' );

		if ( '' === $base || wp_parse_url( $base, PHP_URL_HOST ) !== wp_parse_url( $home, PHP_URL_HOST ) ) {
			return $home;
		}

		return strtok( $base, '?#' );
	}

	/**
	 * Builds a response that caches are told not to keep.
	 *
	 * @param array $body Response data.
	 * @return WP_REST_Response
	 */
	private static function respond( array $body ) {
		$response = new WP_REST_Response( $body );
		$response->header( 'Cache-Control', 'no-store' );

		return $response;
	}
}
