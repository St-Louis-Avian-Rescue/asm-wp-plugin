<?php
/**
 * Configuration for the WordPress test suite inside the dev containers.
 *
 * Uses the separate wordpress_test database created by dev/db-init, because
 * the suite drops and recreates its tables on every run.
 *
 * @package ASM_WP_Plugin
 */

// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals -- Names are fixed by the WordPress test suite.
define( 'ABSPATH', getenv( 'WP_TESTS_ABSPATH' ) ? getenv( 'WP_TESTS_ABSPATH' ) : '/var/www/html/' );

define( 'DB_NAME', getenv( 'WP_TESTS_DB_NAME' ) ? getenv( 'WP_TESTS_DB_NAME' ) : 'wordpress_test' );
define( 'DB_USER', getenv( 'WORDPRESS_DB_USER' ) ? getenv( 'WORDPRESS_DB_USER' ) : 'wordpress' );
define( 'DB_PASSWORD', getenv( 'WORDPRESS_DB_PASSWORD' ) ? getenv( 'WORDPRESS_DB_PASSWORD' ) : 'wordpress' );
define( 'DB_HOST', getenv( 'WORDPRESS_DB_HOST' ) ? getenv( 'WORDPRESS_DB_HOST' ) : 'db' );
define( 'DB_CHARSET', 'utf8' );
define( 'DB_COLLATE', '' );

$table_prefix = 'wptests_'; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- Required by the test suite.

define( 'WP_TESTS_DOMAIN', 'example.org' );
define( 'WP_TESTS_EMAIL', 'admin@example.org' );
define( 'WP_TESTS_TITLE', 'ASM WP Plugin Tests' );
define( 'WP_PHP_BINARY', 'php' );
define( 'WPLANG', '' );
define( 'WP_DEBUG', true );
