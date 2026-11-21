<?php
/**
 * Plugin Name: ASM WP Plugin dev helpers
 * Description: Local development only. Lets WordPress reach the mock ASM service at the same URL the browser uses.
 *
 * The browser loads animal photos from the mock's published port on
 * localhost (8889 unless ASM_DEV_MOCK_PORT says otherwise), so that is the
 * service URL saved in the plugin settings. Inside the WordPress container
 * "localhost" is the container itself, so server-side requests to that
 * address are re-sent to the asm-mock container instead.
 *
 * @package ASM_WP_Plugin
 */

add_filter(
	'pre_http_request',
	function ( $response, $args, $url ) {
		$port   = getenv( 'ASM_DEV_MOCK_PORT' );
		$public = 'http://localhost:' . ( $port ? $port : '8889' ) . '/';
		if ( 0 !== strpos( $url, $public ) ) {
			return $response;
		}
		return wp_remote_request( 'http://asm-mock:8889/' . substr( $url, strlen( $public ) ), $args );
	},
	10,
	3
);
