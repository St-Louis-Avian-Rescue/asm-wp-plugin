<?php
/**
 * The settings page.
 *
 * @package ASM_WP_Plugin
 */

defined( 'ABSPATH' ) || exit;

/**
 * Settings → ASM WP Plugin.
 */
final class ASM_WP_Plugin_Admin {

	const PAGE = 'asm-wp-plugin';

	/**
	 * Hooks.
	 */
	public static function register() {
		add_action( 'admin_menu', array( __CLASS__, 'add_page' ) );
		add_action( 'admin_post_asm_wp_plugin_test', array( __CLASS__, 'handle_test' ) );
		add_action( 'admin_post_asm_wp_plugin_clear', array( __CLASS__, 'handle_clear' ) );
		add_filter( 'plugin_action_links_' . plugin_basename( ASM_WP_PLUGIN_FILE ), array( __CLASS__, 'action_links' ) );
	}

	/**
	 * Adds the page to the Settings menu.
	 */
	public static function add_page() {
		add_options_page(
			__( 'ASM WP Plugin', 'asm-wp-plugin' ),
			__( 'ASM WP Plugin', 'asm-wp-plugin' ),
			'manage_options',
			self::PAGE,
			array( __CLASS__, 'render_page' )
		);
	}

	/**
	 * Adds a Settings link on the Plugins screen.
	 *
	 * @param string[] $links Existing links.
	 * @return string[]
	 */
	public static function action_links( $links ) {
		array_unshift( $links, '<a href="' . esc_url( self::page_url() ) . '">' . esc_html__( 'Settings', 'asm-wp-plugin' ) . '</a>' );

		return $links;
	}

	/**
	 * URL of the settings page.
	 *
	 * @return string
	 */
	public static function page_url() {
		return admin_url( 'options-general.php?page=' . self::PAGE );
	}

	/**
	 * Handles the "Test connection" button.
	 */
	public static function handle_test() {
		self::check_request( 'asm_wp_plugin_test' );

		$animals = ASM_WP_Plugin_Client::fetch();

		if ( is_wp_error( $animals ) ) {
			self::redirect_with_notice(
				'error',
				/* translators: %s: reason the connection failed. */
				sprintf( __( 'Could not get animals from ASM: %s', 'asm-wp-plugin' ), $animals->get_error_message() )
			);
		}

		self::redirect_with_notice(
			'success',
			sprintf(
				/* translators: %s: number of animals. */
				_n( 'Connected. ASM returned %s adoptable animal.', 'Connected. ASM returned %s adoptable animals.', count( $animals ), 'asm-wp-plugin' ),
				number_format_i18n( count( $animals ) )
			)
		);
	}

	/**
	 * Handles the "Clear cache" button.
	 */
	public static function handle_clear() {
		self::check_request( 'asm_wp_plugin_clear' );

		ASM_WP_Plugin_Client::clear( true );
		$data = ASM_WP_Plugin_Client::refresh();

		if ( $data['stale'] || ! $data['available'] ) {
			self::redirect_with_notice(
				'error',
				/* translators: %s: reason the fetch failed. */
				sprintf( __( 'The cache was cleared, but fetching fresh animals failed: %s', 'asm-wp-plugin' ), $data['error'] )
			);
		}

		self::redirect_with_notice(
			'success',
			sprintf(
				/* translators: %s: number of animals. */
				_n( 'Cache cleared. %s animal fetched from ASM.', 'Cache cleared. %s animals fetched from ASM.', count( $data['animals'] ), 'asm-wp-plugin' ),
				number_format_i18n( count( $data['animals'] ) )
			)
		);
	}

	/**
	 * Stops a button request that is forged or from a user without access.
	 *
	 * @param string $action Nonce action.
	 */
	private static function check_request( $action ) {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You are not allowed to do that.', 'asm-wp-plugin' ), 403 );
		}
		check_admin_referer( $action );
	}

	/**
	 * Returns to the settings page, showing a message there.
	 *
	 * @param string $type    success or error.
	 * @param string $message Message text.
	 */
	private static function redirect_with_notice( $type, $message ) {
		set_transient(
			'asm_wp_plugin_notice_' . get_current_user_id(),
			array(
				'type'    => $type,
				'message' => $message,
			),
			MINUTE_IN_SECONDS
		);
		wp_safe_redirect( self::page_url() );
		exit;
	}

	/**
	 * Prints the message left by a button, once.
	 */
	private static function render_notice() {
		$key    = 'asm_wp_plugin_notice_' . get_current_user_id();
		$notice = get_transient( $key );
		if ( ! is_array( $notice ) ) {
			return;
		}
		delete_transient( $key );

		printf(
			'<div class="notice notice-%1$s is-dismissible"><p>%2$s</p></div>',
			'success' === $notice['type'] ? 'success' : 'error',
			esc_html( $notice['message'] )
		);
	}

	/**
	 * Prints one text-like field row.
	 *
	 * @param string $key   Setting name.
	 * @param string $label Field label.
	 * @param array  $saved Saved settings.
	 * @param array  $extra {
	 *     Optional.
	 *
	 *     @type string $type        Input type. Default text.
	 *     @type string $description Help text; may contain <code>.
	 *     @type string $class       Input class. Default regular-text.
	 *     @type array  $attrs       Extra attributes.
	 * }
	 */
	private static function text_row( $key, $label, array $saved, array $extra = array() ) {
		$extra = wp_parse_args(
			$extra,
			array(
				'type'        => 'text',
				'description' => '',
				'class'       => 'regular-text',
				'attrs'       => array(),
			)
		);
		$id    = 'asm-wp-plugin-' . str_replace( '_', '-', $key );
		$fixed = ASM_WP_Plugin_Settings::is_constant( $key );
		?>
		<tr>
			<th scope="row"><label for="<?php echo esc_attr( $id ); ?>"><?php echo esc_html( $label ); ?></label></th>
			<td>
				<?php if ( $fixed ) : ?>
					<input type="text" id="<?php echo esc_attr( $id ); ?>" class="<?php echo esc_attr( $extra['class'] ); ?>" value="<?php echo 'password' === $key ? '' : esc_attr( ASM_WP_Plugin_Settings::get( $key ) ); ?>" placeholder="<?php echo 'password' === $key ? esc_attr__( '(hidden)', 'asm-wp-plugin' ) : ''; ?>" disabled>
					<p class="description">
						<?php
						printf(
							/* translators: %s: name of a PHP constant. */
							esc_html__( 'Set by %s in wp-config.php.', 'asm-wp-plugin' ),
							'<code>' . esc_html( ASM_WP_Plugin_Settings::CONSTANTS[ $key ] ) . '</code>'
						);
						?>
					</p>
				<?php else : ?>
					<input
						type="<?php echo esc_attr( $extra['type'] ); ?>"
						id="<?php echo esc_attr( $id ); ?>"
						name="<?php echo esc_attr( ASM_WP_Plugin_Settings::OPTION . '[' . $key . ']' ); ?>"
						class="<?php echo esc_attr( $extra['class'] ); ?>"
						value="<?php echo esc_attr( $saved[ $key ] ); ?>"
						<?php foreach ( $extra['attrs'] as $name => $value ) : ?>
							<?php echo esc_attr( $name ); ?>="<?php echo esc_attr( $value ); ?>"
						<?php endforeach; ?>
					>
					<?php if ( '' !== $extra['description'] ) : ?>
						<p class="description"><?php echo wp_kses( $extra['description'], array( 'code' => array() ) ); ?></p>
					<?php endif; ?>
				<?php endif; ?>
			</td>
		</tr>
		<?php
	}

	/**
	 * Prints the settings page.
	 */
	public static function render_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$saved  = ASM_WP_Plugin_Settings::get_saved();
		$option = ASM_WP_Plugin_Settings::OPTION;
		$cache  = get_option( ASM_WP_Plugin_Client::CACHE_OPTION );
		$cache  = is_array( $cache ) ? $cache : array();
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'ASM WP Plugin', 'asm-wp-plugin' ); ?></h1>
			<?php self::render_notice(); ?>

			<p>
				<?php
				printf(
					/* translators: %s: the shortcode. */
					esc_html__( 'Show your adoptable animals on any page with the “Adoptable animals” block or the %s shortcode.', 'asm-wp-plugin' ),
					'<code>[asm_adoptable]</code>'
				);
				?>
			</p>

			<h2><?php esc_html_e( 'Status', 'asm-wp-plugin' ); ?></h2>
			<table class="widefat striped" style="max-width: 48rem;">
				<tbody>
					<tr>
						<th scope="row"><?php esc_html_e( 'Last successful fetch', 'asm-wp-plugin' ); ?></th>
						<td>
							<?php
							if ( ! empty( $cache['fetched'] ) ) {
								printf(
									/* translators: 1: how long ago, 2: number of animals. */
									esc_html__( '%1$s ago, %2$s animals', 'asm-wp-plugin' ),
									esc_html( human_time_diff( (int) $cache['fetched'] ) ),
									esc_html( number_format_i18n( count( (array) $cache['animals'] ) ) )
								);
							} else {
								esc_html_e( 'Never', 'asm-wp-plugin' );
							}
							?>
						</td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Latest problem', 'asm-wp-plugin' ); ?></th>
						<td>
							<?php
							if ( ! empty( $cache['error'] ) ) {
								printf(
									/* translators: 1: how long ago, 2: error message. */
									esc_html__( '%1$s ago: %2$s', 'asm-wp-plugin' ),
									esc_html( human_time_diff( (int) $cache['error_at'] ) ),
									esc_html( $cache['error'] )
								);
								if ( ! empty( $cache['fetched'] ) ) {
									echo ' ' . esc_html__( 'Visitors are seeing the last good copy.', 'asm-wp-plugin' );
								}
							} else {
								esc_html_e( 'None', 'asm-wp-plugin' );
							}
							?>
						</td>
					</tr>
				</tbody>
			</table>

			<p>
				<a class="button" href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=asm_wp_plugin_test' ), 'asm_wp_plugin_test' ) ); ?>"><?php esc_html_e( 'Test connection', 'asm-wp-plugin' ); ?></a>
				<a class="button" href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=asm_wp_plugin_clear' ), 'asm_wp_plugin_clear' ) ); ?>"><?php esc_html_e( 'Clear cache', 'asm-wp-plugin' ); ?></a>
				<span class="description"><?php esc_html_e( 'Both use the saved settings, so save any changes first.', 'asm-wp-plugin' ); ?></span>
			</p>

			<form method="post" action="options.php">
				<?php settings_fields( ASM_WP_Plugin_Settings::GROUP ); ?>
				<input type="hidden" name="<?php echo esc_attr( $option ); ?>[has_display_fields]" value="1">

				<h2><?php esc_html_e( 'Connection', 'asm-wp-plugin' ); ?></h2>
				<p><?php esc_html_e( 'Use an ASM user made just for this, with permission to view animals and nothing else.', 'asm-wp-plugin' ); ?></p>
				<table class="form-table" role="presentation">
					<?php
					self::text_row(
						'service_url',
						__( 'Service URL', 'asm-wp-plugin' ),
						$saved,
						array(
							'type'        => 'url',
							'description' => __( 'For sheltermanager.com leave this as it is. For your own ASM server it is the address of its <code>/service</code> page.', 'asm-wp-plugin' ),
						)
					);
					self::text_row(
						'account',
						__( 'Account', 'asm-wp-plugin' ),
						$saved,
						array( 'description' => __( 'Your sheltermanager.com account number, such as <code>ab1234</code>. Leave empty for your own ASM server.', 'asm-wp-plugin' ) )
					);
					self::text_row( 'username', __( 'Username', 'asm-wp-plugin' ), $saved, array( 'attrs' => array( 'autocomplete' => 'off' ) ) );
					?>
					<tr>
						<th scope="row"><label for="asm-wp-plugin-password"><?php esc_html_e( 'Password', 'asm-wp-plugin' ); ?></label></th>
						<td>
							<?php if ( ASM_WP_Plugin_Settings::is_constant( 'password' ) ) : ?>
								<input type="text" id="asm-wp-plugin-password" class="regular-text" value="" placeholder="<?php esc_attr_e( '(hidden)', 'asm-wp-plugin' ); ?>" disabled>
								<p class="description">
									<?php
									printf(
										/* translators: %s: name of a PHP constant. */
										esc_html__( 'Set by %s in wp-config.php.', 'asm-wp-plugin' ),
										'<code>ASM_WP_PLUGIN_PASSWORD</code>'
									);
									?>
								</p>
							<?php else : ?>
								<input type="password" id="asm-wp-plugin-password" name="<?php echo esc_attr( $option ); ?>[password]" class="regular-text" value="" autocomplete="new-password">
								<?php if ( '' !== $saved['password'] ) : ?>
									<p class="description"><?php esc_html_e( 'A password is saved. Leave this empty to keep it.', 'asm-wp-plugin' ); ?></p>
									<label><input type="checkbox" name="<?php echo esc_attr( $option ); ?>[clear_password]" value="1"> <?php esc_html_e( 'Remove the saved password', 'asm-wp-plugin' ); ?></label>
								<?php else : ?>
									<p class="description"><?php esc_html_e( 'No password is saved yet.', 'asm-wp-plugin' ); ?></p>
								<?php endif; ?>
							<?php endif; ?>
						</td>
					</tr>
					<?php
					self::text_row(
						'cache_minutes',
						__( 'Refresh every (minutes)', 'asm-wp-plugin' ),
						$saved,
						array(
							'type'        => 'number',
							'class'       => 'small-text',
							'description' => __( 'How long to keep the animals before asking ASM again.', 'asm-wp-plugin' ),
							'attrs'       => array(
								'min' => 1,
								'max' => 1440,
							),
						)
					);
					self::text_row(
						'timeout',
						__( 'Give up after (seconds)', 'asm-wp-plugin' ),
						$saved,
						array(
							'type'        => 'number',
							'class'       => 'small-text',
							'description' => __( 'If ASM has not answered by then, the last good copy is shown.', 'asm-wp-plugin' ),
							'attrs'       => array(
								'min' => 1,
								'max' => 60,
							),
						)
					);
					?>
				</table>

				<h2><?php esc_html_e( 'Display', 'asm-wp-plugin' ); ?></h2>
				<table class="form-table" role="presentation">
					<tr>
						<th scope="row"><?php esc_html_e( 'Search and filter bar', 'asm-wp-plugin' ); ?></th>
						<td>
							<label><input type="checkbox" name="<?php echo esc_attr( $option ); ?>[filters]" value="1" <?php checked( $saved['filters'] ); ?>> <?php esc_html_e( 'Show search, filters and sorting above the animals', 'asm-wp-plugin' ); ?></label>
							<p class="description"><?php echo wp_kses( __( 'A single listing can override this: <code>[asm_adoptable filters="yes"]</code> or <code>filters="no"</code>.', 'asm-wp-plugin' ), array( 'code' => array() ) ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="asm-wp-plugin-card-image"><?php esc_html_e( 'Photo on each card', 'asm-wp-plugin' ); ?></label></th>
						<td>
							<select id="asm-wp-plugin-card-image" name="<?php echo esc_attr( $option ); ?>[card_image]">
								<option value="image" <?php selected( $saved['card_image'], 'image' ); ?>><?php esc_html_e( 'Full photo (sharper)', 'asm-wp-plugin' ); ?></option>
								<option value="thumbnail" <?php selected( $saved['card_image'], 'thumbnail' ); ?>><?php esc_html_e( 'ASM thumbnail (faster)', 'asm-wp-plugin' ); ?></option>
							</select>
						</td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Details to show', 'asm-wp-plugin' ); ?></th>
						<td>
							<fieldset>
								<legend class="screen-reader-text"><?php esc_html_e( 'Details to show', 'asm-wp-plugin' ); ?></legend>
								<?php foreach ( ASM_WP_Plugin_Settings::detail_field_choices() as $field => $label ) : ?>
									<label style="display: block;"><input type="checkbox" name="<?php echo esc_attr( $option ); ?>[detail_fields][]" value="<?php echo esc_attr( $field ); ?>" <?php checked( in_array( $field, (array) $saved['detail_fields'], true ) ); ?>> <?php echo esc_html( $label ); ?></label>
								<?php endforeach; ?>
							</fieldset>
							<p class="description"><?php esc_html_e( 'Untick “Adoption fee” to hide fees, for example if you write them in the description.', 'asm-wp-plugin' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="asm-wp-plugin-currency-symbol"><?php esc_html_e( 'Currency', 'asm-wp-plugin' ); ?></label></th>
						<td>
							<input type="text" id="asm-wp-plugin-currency-symbol" name="<?php echo esc_attr( $option ); ?>[currency_symbol]" class="small-text" value="<?php echo esc_attr( $saved['currency_symbol'] ); ?>">
							<select name="<?php echo esc_attr( $option ); ?>[currency_position]" aria-label="<?php esc_attr_e( 'Currency symbol position', 'asm-wp-plugin' ); ?>">
								<option value="before" <?php selected( $saved['currency_position'], 'before' ); ?>><?php esc_html_e( 'before the amount', 'asm-wp-plugin' ); ?></option>
								<option value="after" <?php selected( $saved['currency_position'], 'after' ); ?>><?php esc_html_e( 'after the amount', 'asm-wp-plugin' ); ?></option>
							</select>
						</td>
					</tr>
					<?php
					self::text_row(
						'apply_url',
						__( 'Application link', 'asm-wp-plugin' ),
						$saved,
						array(
							'class'       => 'large-text',
							'description' => __( 'Where the apply button goes. <code>{code}</code>, <code>{id}</code> and <code>{name}</code> are replaced with the animal’s shelter code, ASM ID and name, for example <code>https://example.org/apply/?animal={code}</code>. Leave empty for no button.', 'asm-wp-plugin' ),
						)
					);
					self::text_row(
						'apply_label',
						__( 'Application button text', 'asm-wp-plugin' ),
						$saved,
						array(
							'class' => 'large-text',
							'attrs' => array( 'placeholder' => __( 'Click here to fill out an adoption application.', 'asm-wp-plugin' ) ),
						)
					);
					self::text_row(
						'share_text',
						__( 'Share message', 'asm-wp-plugin' ),
						$saved,
						array(
							'class'       => 'large-text',
							'description' => __( 'Text sent with shares. <code>{name}</code> is replaced with the animal’s name.', 'asm-wp-plugin' ),
							'attrs'       => array( 'placeholder' => __( '{name} is looking for a new family', 'asm-wp-plugin' ) ),
						)
					);
					?>
					<tr>
						<th scope="row"><?php esc_html_e( 'Credit', 'asm-wp-plugin' ); ?></th>
						<td>
							<label><input type="checkbox" name="<?php echo esc_attr( $option ); ?>[credit]" value="1" <?php checked( $saved['credit'] ); ?>> <?php esc_html_e( 'Show a small "Built with ASM WP Plugin" link under the animals', 'asm-wp-plugin' ); ?></label>
						</td>
					</tr>
				</table>

				<?php submit_button(); ?>
			</form>
		</div>
		<?php
	}
}
