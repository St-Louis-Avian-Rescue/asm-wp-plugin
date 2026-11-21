<?php
/**
 * ASM WP Plugin Demo theme.
 *
 * @package ASM_WP_Plugin
 */

add_action(
	'wp_enqueue_scripts',
	function () {
		wp_enqueue_style( 'asm-wp-plugin-demo', get_stylesheet_uri(), array(), '1.0' );
	}
);
