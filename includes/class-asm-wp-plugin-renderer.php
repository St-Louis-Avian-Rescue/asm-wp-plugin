<?php
/**
 * Front-end output: the shortcode, templates and assets.
 *
 * @package ASM_WP_Plugin
 */

defined( 'ABSPATH' ) || exit;

/**
 * Renders the adoptable animals listing.
 */
final class ASM_WP_Plugin_Renderer {

	/**
	 * The filter bar controls, in display order.
	 *
	 * @var string[]
	 */
	const FILTER_CONTROLS = array( 'search', 'species', 'sex', 'size', 'special', 'sort' );

	/**
	 * How many listings have been rendered on this page, for unique IDs.
	 *
	 * @var int
	 */
	private static $instances = 0;

	/**
	 * Hooks.
	 */
	public static function register() {
		add_shortcode( 'asm_adoptable', array( __CLASS__, 'shortcode' ) );
		add_action( 'init', array( __CLASS__, 'register_assets' ) );
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'maybe_enqueue_early' ), 20 );
	}

	/**
	 * Registers the stylesheet and script. They are only loaded on pages that show a listing.
	 */
	public static function register_assets() {
		wp_register_style( 'asm-wp-plugin', ASM_WP_PLUGIN_URL . 'assets/css/asm-wp-plugin.css', array(), ASM_WP_PLUGIN_VERSION );
		wp_register_script(
			'asm-wp-plugin',
			ASM_WP_PLUGIN_URL . 'assets/js/asm-wp-plugin.js',
			array(),
			ASM_WP_PLUGIN_VERSION,
			array(
				'in_footer' => true,
				'strategy'  => 'defer',
			)
		);
		wp_localize_script(
			'asm-wp-plugin',
			'asmWpPluginConfig',
			array(
				'restUrl' => esc_url_raw( rest_url( 'asm-wp-plugin/v1/' ) ),
				'i18n'    => array(
					'loading'   => __( 'Loading…', 'asm-wp-plugin' ),
					'error'     => __( 'Sorry, the details could not be loaded. Please try again.', 'asm-wp-plugin' ),
					'gone'      => __( 'This animal is no longer listed. They may have found a home!', 'asm-wp-plugin' ),
					'copied'    => __( 'Copied!', 'asm-wp-plugin' ),
					/* translators: %d: number of animals shown. */
					'countOne'  => __( '%d animal', 'asm-wp-plugin' ),
					/* translators: %d: number of animals shown. */
					'countMany' => __( '%d animals', 'asm-wp-plugin' ),
				),
			)
		);
	}

	/**
	 * Loads the stylesheet in the head when the page content is known to hold a listing.
	 *
	 * Listings placed any other way (widgets, templates) still work: the
	 * assets are then enqueued when the listing renders.
	 */
	public static function maybe_enqueue_early() {
		$post = get_post();
		if ( ! is_singular() || ! $post ) {
			return;
		}

		if ( has_shortcode( $post->post_content, 'asm_adoptable' ) || has_block( 'asm-wp-plugin/adoptable', $post ) ) {
			self::enqueue_assets();
		}
	}

	/**
	 * Loads the stylesheet and script.
	 */
	public static function enqueue_assets() {
		wp_enqueue_style( 'asm-wp-plugin' );
		wp_enqueue_script( 'asm-wp-plugin' );
	}

	/**
	 * The [asm_adoptable] shortcode.
	 *
	 * @param array|string $atts Shortcode attributes.
	 * @return string
	 */
	public static function shortcode( $atts ) {
		return self::render( is_array( $atts ) ? $atts : array() );
	}

	/**
	 * Cleans shortcode or block attributes.
	 *
	 * @param array $atts Raw attributes.
	 * @return array {
	 *     @type string[]      $species Species to include, empty for all.
	 *     @type string        $sort    name, newest, youngest or oldest.
	 *     @type int           $limit   Most animals to show, 0 for all.
	 *     @type int           $columns Fixed column count, 0 for automatic.
	 *     @type string[]|null $filters Filter bar controls, null to follow the setting.
	 * }
	 */
	public static function normalize_atts( array $atts ) {
		$atts = shortcode_atts(
			array(
				'species' => '',
				'sort'    => 'name',
				'limit'   => 0,
				'columns' => 0,
				'filters' => '',
			),
			$atts,
			'asm_adoptable'
		);

		$species = is_array( $atts['species'] ) ? $atts['species'] : explode( ',', (string) $atts['species'] );
		$species = array_values( array_filter( array_map( 'sanitize_text_field', array_map( 'trim', $species ) ), 'strlen' ) );

		$sort = strtolower( trim( (string) $atts['sort'] ) );
		if ( ! in_array( $sort, array( 'name', 'newest', 'youngest', 'oldest' ), true ) ) {
			$sort = 'name';
		}

		return array(
			'species' => $species,
			'sort'    => $sort,
			'limit'   => absint( $atts['limit'] ),
			'columns' => min( 8, absint( $atts['columns'] ) ),
			'filters' => self::parse_filters( $atts['filters'] ),
		);
	}

	/**
	 * Reads the "filters" attribute.
	 *
	 * @param mixed $value '', a yes/no word, or a comma-separated list of controls.
	 * @return string[]|null Controls to show, or null to follow the setting.
	 */
	private static function parse_filters( $value ) {
		if ( is_array( $value ) ) {
			return array_values( array_intersect( self::FILTER_CONTROLS, $value ) );
		}

		$value = strtolower( trim( (string) $value ) );
		if ( '' === $value ) {
			return null;
		}
		if ( in_array( $value, array( '1', 'yes', 'true', 'on', 'all' ), true ) ) {
			return self::FILTER_CONTROLS;
		}
		if ( in_array( $value, array( '0', 'no', 'false', 'off', 'none' ), true ) ) {
			return array();
		}

		return array_values( array_intersect( self::FILTER_CONTROLS, array_map( 'trim', explode( ',', $value ) ) ) );
	}

	/**
	 * A fingerprint of everything the rendered listing depends on.
	 *
	 * The page script compares it with the live value to notice that a
	 * full-page cache is showing an out-of-date listing.
	 *
	 * @param array $data Result of ASM_WP_Plugin_Client::get_data().
	 * @return string
	 */
	public static function hash( array $data ) {
		$settings = ASM_WP_Plugin_Settings::get_all();
		unset( $settings['username'], $settings['password'], $settings['cache_minutes'], $settings['timeout'] );

		return md5( $data['hash'] . '|' . ASM_WP_PLUGIN_VERSION . '|' . wp_json_encode( $settings ) );
	}

	/**
	 * Renders a listing.
	 *
	 * @param array $atts Raw shortcode or block attributes.
	 * @return string
	 */
	public static function render( array $atts ) {
		$atts = self::normalize_atts( $atts );
		$data = ASM_WP_Plugin_Client::get_data();

		self::enqueue_assets();

		if ( ! $data['available'] ) {
			return self::render_unavailable( $data );
		}

		$settings = ASM_WP_Plugin_Settings::get_all();
		$base_url = self::current_url();
		$parts    = self::render_parts( $atts, $data, $settings, $base_url );

		// A link to one animal (?animal=123) opens their details straight away.
		$open    = '';
		$open_id = self::requested_animal();
		if ( $open_id && isset( $data['animals'][ $open_id ] ) ) {
			$open = self::render_detail( $data['animals'][ $open_id ], $data['animals'], $settings, $base_url );
		}

		++self::$instances;

		return self::get_template(
			'grid',
			array(
				'id'       => 'asm-wp-plugin-' . self::$instances,
				'atts'     => $atts,
				'credit'   => ! empty( $settings['credit'] ),
				'hash'     => self::hash( $data ),
				'base_url' => $base_url,
				'count'    => $parts['count'],
				'list'     => $parts['list'],
				'filters'  => $parts['filters'],
				'modal'    => self::get_template(
					'modal',
					array(
						'base_url' => $base_url,
						'detail'   => $open,
					)
				),
			)
		);
	}

	/**
	 * Renders the parts of a listing that change when the animal data changes.
	 *
	 * @param array  $atts     Normalised attributes.
	 * @param array  $data     Result of ASM_WP_Plugin_Client::get_data().
	 * @param array  $settings Plugin settings.
	 * @param string $base_url URL of the page the listing is on.
	 * @return array { count: int, list: string, filters: string }
	 */
	public static function render_parts( array $atts, array $data, array $settings, $base_url ) {
		$animals = ASM_WP_Plugin_Animals::query( $data['animals'], $atts );
		$list    = '';

		foreach ( $animals as $animal ) {
			$list .= self::get_template(
				'card',
				array( 'animal' => ASM_WP_Plugin_Animals::present( $animal, $data['animals'], $settings, $base_url ) )
			);
		}

		$controls = null === $atts['filters']
			? ( $settings['filters'] ? self::FILTER_CONTROLS : array() )
			: $atts['filters'];

		$filters = '';
		if ( $controls && $animals ) {
			$filters = self::get_template(
				'filters',
				array(
					'controls' => $controls,
					'sort'     => $atts['sort'],
					'options'  => array(
						'species' => self::distinct( $animals, 'species' ),
						'sex'     => self::distinct( $animals, 'sex' ),
						'size'    => self::distinct( $animals, 'size' ),
					),
					'special'  => (bool) array_filter( wp_list_pluck( $animals, 'special_needs' ) ),
				)
			);
		}

		return array(
			'count'   => count( $animals ),
			'list'    => $list,
			'filters' => $filters,
		);
	}

	/**
	 * Renders the details of one animal.
	 *
	 * @param array  $animal   Animal.
	 * @param array  $animals  All animals, keyed by ID.
	 * @param array  $settings Plugin settings.
	 * @param string $base_url URL of the page the listing is on.
	 * @return string
	 */
	public static function render_detail( array $animal, array $animals, array $settings, $base_url ) {
		return self::get_template(
			'detail',
			array( 'animal' => ASM_WP_Plugin_Animals::present( $animal, $animals, $settings, $base_url ) )
		);
	}

	/**
	 * What visitors see when there is no animal data at all.
	 *
	 * @param array $data Result of ASM_WP_Plugin_Client::get_data().
	 * @return string
	 */
	private static function render_unavailable( array $data ) {
		$html = '<div class="asm-wp-plugin asm-wp-plugin--unavailable"><p>'
			. esc_html__( 'Our list of adoptable animals is not available right now. Please check back soon.', 'asm-wp-plugin' )
			. '</p>';

		if ( current_user_can( 'manage_options' ) ) {
			$html .= '<p class="asm-wp-plugin-admin-note">' . sprintf(
				/* translators: 1: reason the animals could not be loaded, 2: settings page URL. */
				wp_kses( __( 'Only administrators see this: %1$s <a href="%2$s">Open the ASM WP Plugin settings</a>.', 'asm-wp-plugin' ), array( 'a' => array( 'href' => array() ) ) ),
				esc_html( '' !== $data['error'] ? $data['error'] : __( 'No animals have been fetched yet.', 'asm-wp-plugin' ) ),
				esc_url( admin_url( 'options-general.php?page=asm-wp-plugin' ) )
			) . '</p>';
		}

		return $html . '</div>';
	}

	/**
	 * The distinct non-empty values of one field, sorted.
	 *
	 * @param array  $animals Animals.
	 * @param string $key     Field name.
	 * @return string[]
	 */
	private static function distinct( array $animals, $key ) {
		$values = array_unique( array_filter( wp_list_pluck( $animals, $key ), 'strlen' ) );
		natcasesort( $values );

		return array_values( $values );
	}

	/**
	 * The animal ID asked for in the page URL, if any.
	 *
	 * @return int
	 */
	public static function requested_animal() {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- A public, read-only link to one animal.
		return isset( $_GET['animal'] ) ? absint( wp_unslash( $_GET['animal'] ) ) : 0;
	}

	/**
	 * The URL of the page being shown, without its query string.
	 *
	 * @return string
	 */
	private static function current_url() {
		if ( is_singular() ) {
			$url = get_permalink( get_queried_object_id() );
			if ( $url ) {
				return $url;
			}
		}

		global $wp;

		return home_url( isset( $wp->request ) && '' !== $wp->request ? user_trailingslashit( $wp->request ) : '/' );
	}

	/**
	 * Finds a template, letting the active theme override it.
	 *
	 * A theme overrides templates/card.php by providing asm-wp-plugin/card.php.
	 *
	 * @param string $name Template name without the extension.
	 * @return string Path to the file.
	 */
	public static function locate_template( $name ) {
		$name  = sanitize_file_name( $name );
		$theme = locate_template( array( 'asm-wp-plugin/' . $name . '.php' ) );
		$path  = $theme ? $theme : ASM_WP_PLUGIN_DIR . 'templates/' . $name . '.php';

		/**
		 * Filters the path of a template file.
		 *
		 * @param string $path Path to the template.
		 * @param string $name Template name: grid, card, filters, modal or detail.
		 */
		return apply_filters( 'asm_wp_plugin_template', $path, $name );
	}

	/**
	 * Renders a template to a string.
	 *
	 * @param string $name Template name without the extension.
	 * @param array  $args Values for the template, available to it as $args.
	 * @return string
	 */
	public static function get_template( $name, array $args ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed -- Used by the included template.
		$path = self::locate_template( $name );
		if ( ! is_readable( $path ) ) {
			return '';
		}

		ob_start();
		include $path;

		return (string) ob_get_clean();
	}

	/**
	 * An inline SVG icon for the share row.
	 *
	 * @param string $name facebook, x, email or link.
	 * @return string SVG markup, safe to print.
	 */
	public static function icon( $name ) {
		$paths = array(
			'facebook' => '<path fill="currentColor" d="M13.5 21v-7.5h2.5l.4-3h-2.9V8.6c0-.9.3-1.5 1.5-1.5h1.5V4.4c-.3 0-1.2-.1-2.2-.1-2.2 0-3.7 1.3-3.7 3.8v2.4H8v3h2.6V21z"/>',
			'x'        => '<path fill="currentColor" d="M17.3 4h2.8l-6.2 7 7.3 9h-5.7l-4.4-5.4L6.3 20H3.5l6.6-7.5L3.1 4h5.8l4 5zm-1 14.4h1.6L8 5.5H6.3z"/>',
			'email'    => '<path fill="currentColor" d="M3 6h18v12H3zm2 2v.4l7 4.6 7-4.6V8zm14 2.8-7 4.6-7-4.6V16h14z"/>',
			'link'     => '<path fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" d="M10 14a4 4 0 0 0 5.7 0l3-3a4 4 0 0 0-5.7-5.7l-1 1M14 10a4 4 0 0 0-5.7 0l-3 3a4 4 0 0 0 5.7 5.7l1-1"/>',
		);

		if ( ! isset( $paths[ $name ] ) ) {
			return '';
		}

		return '<svg class="asm-wp-plugin-icon" viewBox="0 0 24 24" width="24" height="24" aria-hidden="true" focusable="false">' . $paths[ $name ] . '</svg>';
	}
}
