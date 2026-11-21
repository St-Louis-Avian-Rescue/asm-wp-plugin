<?php
/**
 * PHPUnit bootstrap: loads the WordPress test suite with the plugin active.
 *
 * @package ASM_WP_Plugin
 */

// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals -- Names are fixed by the WordPress test suite.
$asm_wp_plugin_root = dirname( __DIR__ );

if ( ! getenv( 'WP_PHPUNIT__TESTS_CONFIG' ) ) {
	putenv( 'WP_PHPUNIT__TESTS_CONFIG=' . __DIR__ . '/wp-tests-config.php' ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.runtime_configuration_putenv
}

require_once $asm_wp_plugin_root . '/vendor/autoload.php';

define( 'WP_TESTS_PHPUNIT_POLYFILLS_PATH', $asm_wp_plugin_root . '/vendor/yoast/phpunit-polyfills' );

$asm_wp_plugin_tests_dir = getenv( 'WP_PHPUNIT__DIR' ) ? getenv( 'WP_PHPUNIT__DIR' ) : $asm_wp_plugin_root . '/vendor/wp-phpunit/wp-phpunit';

require_once $asm_wp_plugin_tests_dir . '/includes/functions.php';

tests_add_filter(
	'muplugins_loaded',
	function () use ( $asm_wp_plugin_root ) {
		require $asm_wp_plugin_root . '/asm-wp-plugin.php';
	}
);

require $asm_wp_plugin_tests_dir . '/includes/bootstrap.php';
require __DIR__ . '/class-asm-wp-plugin-test-case.php';
