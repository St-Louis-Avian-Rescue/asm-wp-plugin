<?php
/**
 * Removes everything the plugin stored when it is deleted.
 *
 * @package ASM_WP_Plugin
 */

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

delete_option( 'asm_wp_plugin_settings' );
delete_option( 'asm_wp_plugin_cache' );
delete_transient( 'asm_wp_plugin_fresh' );
