<?php
/**
 * The "Adoptable animals" block.
 *
 * @package ASM_WP_Plugin
 */

defined( 'ABSPATH' ) || exit;

/**
 * Registers a block that shows the same listing as the [asm_adoptable] shortcode.
 */
final class ASM_WP_Plugin_Block {

	/**
	 * Hooks.
	 */
	public static function register() {
		add_action( 'init', array( __CLASS__, 'register_block' ), 11 );
	}

	/**
	 * Registers the block and its editor script.
	 */
	public static function register_block() {
		wp_register_script(
			'asm-wp-plugin-block-editor',
			ASM_WP_PLUGIN_URL . 'block/editor.js',
			array( 'wp-blocks', 'wp-element', 'wp-components', 'wp-block-editor', 'wp-server-side-render', 'wp-i18n' ),
			ASM_WP_PLUGIN_VERSION,
			true
		);
		wp_set_script_translations( 'asm-wp-plugin-block-editor', 'asm-wp-plugin', ASM_WP_PLUGIN_DIR . 'languages' );

		register_block_type(
			ASM_WP_PLUGIN_DIR . 'block',
			array( 'render_callback' => array( __CLASS__, 'render' ) )
		);
	}

	/**
	 * Renders the block.
	 *
	 * @param array $attributes Block attributes.
	 * @return string
	 */
	public static function render( $attributes ) {
		$attributes = is_array( $attributes ) ? $attributes : array();

		return ASM_WP_Plugin_Renderer::render(
			array(
				'species' => isset( $attributes['species'] ) ? $attributes['species'] : '',
				'sort'    => isset( $attributes['sort'] ) ? $attributes['sort'] : 'name',
				'limit'   => isset( $attributes['limit'] ) ? $attributes['limit'] : 0,
				'columns' => isset( $attributes['columns'] ) ? $attributes['columns'] : 0,
				'filters' => isset( $attributes['filters'] ) ? $attributes['filters'] : '',
			)
		);
	}
}
