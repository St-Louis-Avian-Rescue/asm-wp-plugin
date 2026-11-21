<?php
/**
 * Plugin settings.
 *
 * @package ASM_WP_Plugin
 */

defined( 'ABSPATH' ) || exit;

/**
 * Reads, validates and stores the plugin settings.
 *
 * Everything lives in one option that is not autoloaded, because it holds
 * the ASM password. The connection fields can instead be set as constants in
 * wp-config.php, which then win over the saved values:
 * ASM_WP_PLUGIN_SERVICE_URL, ASM_WP_PLUGIN_ACCOUNT, ASM_WP_PLUGIN_USERNAME and
 * ASM_WP_PLUGIN_PASSWORD.
 */
final class ASM_WP_Plugin_Settings {

	const OPTION = 'asm_wp_plugin_settings';
	const GROUP  = 'asm_wp_plugin';

	/**
	 * Settings that a wp-config.php constant can override.
	 *
	 * @var string[]
	 */
	const CONSTANTS = array(
		'service_url' => 'ASM_WP_PLUGIN_SERVICE_URL',
		'account'     => 'ASM_WP_PLUGIN_ACCOUNT',
		'username'    => 'ASM_WP_PLUGIN_USERNAME',
		'password'    => 'ASM_WP_PLUGIN_PASSWORD',
	);

	/**
	 * Hooks.
	 */
	public static function register() {
		add_action( 'admin_init', array( __CLASS__, 'install' ), 5 );
		add_action( 'admin_init', array( __CLASS__, 'register_setting' ) );
		add_action( 'rest_api_init', array( __CLASS__, 'register_setting' ) );
		add_action( 'update_option_' . self::OPTION, array( __CLASS__, 'on_update' ), 10, 2 );
	}

	/**
	 * Registers the option with the Settings API.
	 */
	public static function register_setting() {
		register_setting(
			self::GROUP,
			self::OPTION,
			array(
				'type'              => 'array',
				'sanitize_callback' => array( __CLASS__, 'sanitize' ),
				'show_in_rest'      => false,
				'default'           => array(),
			)
		);
	}

	/**
	 * Default values.
	 *
	 * @return array
	 */
	public static function defaults() {
		return array(
			'service_url'       => 'https://service.sheltermanager.com/asmservice',
			'account'           => '',
			'username'          => '',
			'password'          => '',
			'cache_minutes'     => 15,
			'timeout'           => 10,
			'card_image'        => 'image',
			'detail_fields'     => array( 'fee', 'bonded', 'sex', 'age' ),
			'currency_symbol'   => '$',
			'currency_position' => 'before',
			'apply_url'         => '',
			'apply_label'       => '',
			'share_text'        => '',
			'filters'           => 0,
			'credit'            => 1,
		);
	}

	/**
	 * The detail fields a site can switch on or off, with their labels.
	 *
	 * @return array
	 */
	public static function detail_field_choices() {
		return array(
			'fee'     => __( 'Adoption fee', 'asm-wp-plugin' ),
			'bonded'  => __( 'Bonded companion', 'asm-wp-plugin' ),
			'sex'     => __( 'Sex', 'asm-wp-plugin' ),
			'age'     => __( 'Age', 'asm-wp-plugin' ),
			'size'    => __( 'Size', 'asm-wp-plugin' ),
			'arrival' => __( 'Arrival date', 'asm-wp-plugin' ),
			'code'    => __( 'Shelter code', 'asm-wp-plugin' ),
		);
	}

	/**
	 * The saved settings, without constant overrides.
	 *
	 * @return array
	 */
	public static function get_saved() {
		$saved = get_option( self::OPTION, array() );

		return wp_parse_args( is_array( $saved ) ? $saved : array(), self::defaults() );
	}

	/**
	 * The effective settings: saved values with wp-config.php constants applied.
	 *
	 * @return array
	 */
	public static function get_all() {
		$settings = self::get_saved();

		foreach ( self::CONSTANTS as $key => $constant ) {
			if ( self::is_constant( $key ) ) {
				$settings[ $key ] = (string) constant( $constant );
			}
		}

		/**
		 * Filters the effective plugin settings.
		 *
		 * @param array $settings Settings keyed by name.
		 */
		return apply_filters( 'asm_wp_plugin_settings', $settings );
	}

	/**
	 * One effective setting.
	 *
	 * @param string $key Setting name.
	 * @return mixed
	 */
	public static function get( $key ) {
		$settings = self::get_all();

		return isset( $settings[ $key ] ) ? $settings[ $key ] : null;
	}

	/**
	 * Whether a setting is supplied by a wp-config.php constant.
	 *
	 * @param string $key Setting name.
	 * @return bool
	 */
	public static function is_constant( $key ) {
		return isset( self::CONSTANTS[ $key ] ) && defined( self::CONSTANTS[ $key ] );
	}

	/**
	 * Whether there is enough to call ASM.
	 *
	 * @return bool
	 */
	public static function is_configured() {
		$settings = self::get_all();

		return '' !== $settings['service_url'] && '' !== $settings['username'] && '' !== $settings['password'];
	}

	/**
	 * Creates the option, not autoloaded, if it does not exist yet.
	 *
	 * Runs on activation and before the settings page can save, so the
	 * option is never created with WordPress's default autoloading.
	 *
	 * @param mixed $values Initial values, merged over the defaults.
	 */
	public static function install( $values = array() ) {
		if ( false === get_option( self::OPTION ) ) {
			add_option( self::OPTION, wp_parse_args( is_array( $values ) ? $values : array(), self::defaults() ), '', false );
		}
	}

	/**
	 * Validates settings submitted from the settings page.
	 *
	 * @param mixed $input Raw submitted values.
	 * @return array
	 */
	public static function sanitize( $input ) {
		$input = is_array( $input ) ? wp_unslash( $input ) : array();
		$old   = self::get_saved();
		$clean = $old;

		if ( isset( $input['service_url'] ) ) {
			$url                  = esc_url_raw( trim( (string) $input['service_url'] ), array( 'http', 'https' ) );
			$clean['service_url'] = '' !== $url ? $url : self::defaults()['service_url'];
		}

		foreach ( array( 'account', 'username' ) as $key ) {
			if ( isset( $input[ $key ] ) ) {
				$clean[ $key ] = sanitize_text_field( (string) $input[ $key ] );
			}
		}

		// The form never shows the saved password, so an empty field means "keep it".
		if ( isset( $input['password'] ) && '' !== (string) $input['password'] ) {
			$clean['password'] = (string) $input['password'];
		}
		if ( ! empty( $input['clear_password'] ) ) {
			$clean['password'] = '';
		}

		if ( isset( $input['cache_minutes'] ) ) {
			$clean['cache_minutes'] = min( 1440, max( 1, absint( $input['cache_minutes'] ) ) );
		}
		if ( isset( $input['timeout'] ) ) {
			$clean['timeout'] = min( 60, max( 1, absint( $input['timeout'] ) ) );
		}

		if ( isset( $input['card_image'] ) ) {
			$clean['card_image'] = 'thumbnail' === $input['card_image'] ? 'thumbnail' : 'image';
		}

		// Checkbox groups send nothing when every box is cleared, so a marker field says the form was shown.
		if ( isset( $input['detail_fields'] ) || isset( $input['has_display_fields'] ) ) {
			$chosen                 = isset( $input['detail_fields'] ) ? (array) $input['detail_fields'] : array();
			$clean['detail_fields'] = array_values( array_intersect( array_keys( self::detail_field_choices() ), $chosen ) );
			$clean['filters']       = empty( $input['filters'] ) ? 0 : 1;
			$clean['credit']        = empty( $input['credit'] ) ? 0 : 1;
		}

		if ( isset( $input['currency_symbol'] ) ) {
			$clean['currency_symbol'] = sanitize_text_field( (string) $input['currency_symbol'] );
		}
		if ( isset( $input['currency_position'] ) ) {
			$clean['currency_position'] = 'after' === $input['currency_position'] ? 'after' : 'before';
		}

		foreach ( array( 'apply_label', 'share_text' ) as $key ) {
			if ( isset( $input[ $key ] ) ) {
				$clean[ $key ] = sanitize_text_field( (string) $input[ $key ] );
			}
		}

		// Placeholders such as {code} are not valid in a URL until they are filled in, so protect them.
		if ( isset( $input['apply_url'] ) ) {
			$clean['apply_url'] = self::sanitize_url_template( (string) $input['apply_url'] );
		}

		return array_intersect_key( $clean, self::defaults() );
	}

	/**
	 * Cleans a URL that may contain {placeholders}.
	 *
	 * @param string $template URL template.
	 * @return string
	 */
	public static function sanitize_url_template( $template ) {
		$template = trim( $template );
		if ( '' === $template ) {
			return '';
		}

		$tokens = array(
			'{id}'   => 'asmwppluginidtoken',
			'{code}' => 'asmwpplugincodetoken',
			'{name}' => 'asmwppluginnametoken',
		);
		$url    = esc_url_raw( strtr( $template, $tokens ), array( 'http', 'https', 'mailto' ) );

		return strtr( $url, array_flip( $tokens ) );
	}

	/**
	 * Drops cached animals when the settings change.
	 *
	 * @param mixed $old_value Previous settings.
	 * @param mixed $value     New settings.
	 */
	public static function on_update( $old_value, $value ) {
		$connection = array( 'service_url', 'account', 'username', 'password' );

		foreach ( $connection as $key ) {
			$before = is_array( $old_value ) && isset( $old_value[ $key ] ) ? $old_value[ $key ] : '';
			$after  = is_array( $value ) && isset( $value[ $key ] ) ? $value[ $key ] : '';
			if ( $before !== $after ) {
				// A different account: the last good copy belongs to someone else.
				ASM_WP_Plugin_Client::clear( true );
				return;
			}
		}

		ASM_WP_Plugin_Client::clear( false );
	}
}
