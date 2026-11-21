<?php
/**
 * Plugin Name:       ASM WP Plugin – Adoptable Animals from Animal Shelter Manager
 * Description:       Shows the adoptable animals from your Animal Shelter Manager (ASM / sheltermanager.com) account on your site, with photos, details, sharing and optional search and filters.
 * Version:           1.0.1
 * Requires at least: 6.3
 * Requires PHP:      7.4
 * Author:            Jacob Sabella
 * Author URI:        https://jacobsabella.com
 * License:           MIT
 * License URI:       https://opensource.org/licenses/MIT
 * Text Domain:       asm-wp-plugin
 * Domain Path:       /languages
 *
 * @package ASM_WP_Plugin
 */

defined( 'ABSPATH' ) || exit;

define( 'ASM_WP_PLUGIN_VERSION', '1.0.1' );
define( 'ASM_WP_PLUGIN_HOMEPAGE', 'https://github.com/St-Louis-Avian-Rescue/asm-wp-plugin' );
define( 'ASM_WP_PLUGIN_FILE', __FILE__ );
define( 'ASM_WP_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'ASM_WP_PLUGIN_URL', plugin_dir_url( __FILE__ ) );

require_once ASM_WP_PLUGIN_DIR . 'includes/class-asm-wp-plugin-settings.php';
require_once ASM_WP_PLUGIN_DIR . 'includes/class-asm-wp-plugin-animals.php';
require_once ASM_WP_PLUGIN_DIR . 'includes/class-asm-wp-plugin-client.php';
require_once ASM_WP_PLUGIN_DIR . 'includes/class-asm-wp-plugin-renderer.php';
require_once ASM_WP_PLUGIN_DIR . 'includes/class-asm-wp-plugin-rest.php';
require_once ASM_WP_PLUGIN_DIR . 'includes/class-asm-wp-plugin-block.php';
require_once ASM_WP_PLUGIN_DIR . 'includes/class-asm-wp-plugin-admin.php';

/**
 * Wires the plugin into WordPress.
 */
function asm_wp_plugin_init() {
	ASM_WP_Plugin_Settings::register();
	ASM_WP_Plugin_Renderer::register();
	ASM_WP_Plugin_REST::register();
	ASM_WP_Plugin_Block::register();

	if ( is_admin() ) {
		ASM_WP_Plugin_Admin::register();
	}
}
add_action( 'plugins_loaded', 'asm_wp_plugin_init' );

register_activation_hook( __FILE__, array( 'ASM_WP_Plugin_Settings', 'install' ) );
