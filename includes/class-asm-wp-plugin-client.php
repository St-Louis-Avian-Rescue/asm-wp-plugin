<?php
/**
 * Fetching and caching the ASM feed.
 *
 * @package ASM_WP_Plugin
 */

defined( 'ABSPATH' ) || exit;

/**
 * Talks to the Animal Shelter Manager service and keeps a local copy.
 *
 * The last good copy is kept in an option that never expires. A transient
 * says whether that copy is still fresh. When ASM cannot be reached the old
 * copy keeps being served, and the next attempt waits a couple of minutes.
 */
final class ASM_WP_Plugin_Client {

	const CACHE_OPTION    = 'asm_wp_plugin_cache';
	const FRESH_TRANSIENT = 'asm_wp_plugin_fresh';

	/**
	 * The animals to show, refreshing from ASM when the copy has expired.
	 *
	 * @return array {
	 *     @type array  $animals   Public animals keyed by ID.
	 *     @type int    $fetched   Unix time of the last good fetch, 0 if never.
	 *     @type string $hash      Fingerprint of the animal data.
	 *     @type bool   $available Whether there is any copy to show.
	 *     @type bool   $stale     Whether the latest attempt failed.
	 *     @type string $error     Why the latest attempt failed, or ''.
	 * }
	 */
	public static function get_data() {
		if ( false !== get_transient( self::FRESH_TRANSIENT ) ) {
			return self::result( self::read_cache() );
		}

		return self::refresh();
	}

	/**
	 * Fetches from ASM now and updates the stored copy.
	 *
	 * @return array Same shape as get_data().
	 */
	public static function refresh() {
		// Stops every other visitor from also calling ASM while this request does.
		set_transient( self::FRESH_TRANSIENT, 'refreshing', 30 );

		$animals = self::fetch();
		$cache   = self::read_cache();

		if ( is_wp_error( $animals ) ) {
			$cache['error']    = $animals->get_error_message();
			$cache['error_at'] = time();
			update_option( self::CACHE_OPTION, $cache, false );

			/**
			 * Filters how long to wait before trying ASM again after a failure.
			 *
			 * @param int $seconds Seconds to wait.
			 */
			set_transient( self::FRESH_TRANSIENT, 'error', (int) apply_filters( 'asm_wp_plugin_retry_seconds', 2 * MINUTE_IN_SECONDS ) );

			return self::result( $cache );
		}

		$cache = array(
			'animals'  => $animals,
			'fetched'  => time(),
			'hash'     => md5( (string) wp_json_encode( $animals ) ),
			'error'    => '',
			'error_at' => 0,
		);
		update_option( self::CACHE_OPTION, $cache, false );
		set_transient( self::FRESH_TRANSIENT, 'ok', max( 1, (int) ASM_WP_Plugin_Settings::get( 'cache_minutes' ) ) * MINUTE_IN_SECONDS );

		return self::result( $cache );
	}

	/**
	 * Forgets that the stored copy is fresh, and optionally the copy itself.
	 *
	 * @param bool $drop_copy Also delete the last good copy.
	 */
	public static function clear( $drop_copy = false ) {
		delete_transient( self::FRESH_TRANSIENT );

		if ( $drop_copy ) {
			delete_option( self::CACHE_OPTION );
		}
	}

	/**
	 * Calls ASM for the adoptable animals.
	 *
	 * @return array|WP_Error Public animals keyed by ID.
	 */
	public static function fetch() {
		$settings = ASM_WP_Plugin_Settings::get_all();

		if ( ! ASM_WP_Plugin_Settings::is_configured() ) {
			return new WP_Error( 'asm_wp_plugin_not_configured', __( 'The ASM service URL, username and password have not all been set.', 'asm-wp-plugin' ) );
		}

		$query = array(
			'method'   => 'json_adoptable_animals',
			'username' => $settings['username'],
			'password' => $settings['password'],
		);
		if ( '' !== $settings['account'] ) {
			$query = array( 'account' => $settings['account'] ) + $query;
		}

		$url = add_query_arg( array_map( 'rawurlencode', $query ), $settings['service_url'] );

		/**
		 * Filters the arguments of the request to ASM.
		 *
		 * @param array $args Arguments for wp_remote_get().
		 */
		$args = apply_filters(
			'asm_wp_plugin_request_args',
			array(
				'timeout' => max( 1, (int) $settings['timeout'] ),
				'headers' => array( 'Accept' => 'application/json' ),
			)
		);

		$response = wp_remote_get( $url, $args );

		if ( is_wp_error( $response ) ) {
			// Transport errors can quote the URL, which carries the password.
			$message = str_replace(
				array( $url, rawurlencode( $settings['password'] ), $settings['password'] ),
				array( $settings['service_url'], '***', '***' ),
				$response->get_error_message()
			);

			return new WP_Error( 'asm_wp_plugin_request_failed', $message );
		}

		$code = (int) wp_remote_retrieve_response_code( $response );
		if ( 200 !== $code ) {
			return new WP_Error(
				'asm_wp_plugin_bad_status',
				/* translators: %d: HTTP status code. */
				sprintf( __( 'ASM answered with HTTP status %d. Check the account, username and password.', 'asm-wp-plugin' ), $code )
			);
		}

		$rows = json_decode( wp_remote_retrieve_body( $response ), true );
		if ( ! is_array( $rows ) || ( $rows && ! self::looks_like_feed( $rows ) ) ) {
			return new WP_Error( 'asm_wp_plugin_bad_body', __( 'ASM did not send back a list of animals. Check the account, username and password.', 'asm-wp-plugin' ) );
		}

		/**
		 * Filters the animals after they are fetched and mapped, before caching.
		 *
		 * @param array $animals Public animals keyed by ID.
		 */
		return apply_filters( 'asm_wp_plugin_feed', ASM_WP_Plugin_Animals::map_feed( $rows ) );
	}

	/**
	 * Whether decoded JSON is a list of animal rows.
	 *
	 * @param array $rows Decoded JSON.
	 * @return bool
	 */
	private static function looks_like_feed( array $rows ) {
		$first = reset( $rows );

		return is_array( $first ) && isset( $first['ID'] );
	}

	/**
	 * The stored copy, with every key present.
	 *
	 * @return array
	 */
	private static function read_cache() {
		$cache = get_option( self::CACHE_OPTION );

		return wp_parse_args(
			is_array( $cache ) ? $cache : array(),
			array(
				'animals'  => array(),
				'fetched'  => 0,
				'hash'     => '',
				'error'    => '',
				'error_at' => 0,
			)
		);
	}

	/**
	 * Shapes the stored copy for callers.
	 *
	 * @param array $cache Stored copy.
	 * @return array
	 */
	private static function result( array $cache ) {
		return array(
			'animals'   => is_array( $cache['animals'] ) ? $cache['animals'] : array(),
			'fetched'   => (int) $cache['fetched'],
			'hash'      => (string) $cache['hash'],
			'available' => $cache['fetched'] > 0,
			'stale'     => '' !== $cache['error'],
			'error'     => (string) $cache['error'],
			'error_at'  => (int) $cache['error_at'],
		);
	}
}
