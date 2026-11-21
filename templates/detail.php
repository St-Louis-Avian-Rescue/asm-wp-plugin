<?php
/**
 * One animal's details, shown inside the dialog.
 *
 * Override by copying to yourtheme/asm-wp-plugin/detail.php. Keep the data-*
 * attributes: the photo carousel, companion links and copy button use them.
 *
 * @package ASM_WP_Plugin
 *
 * @var array $args {
 *     @type array $animal Animal fields plus display values. See ASM_WP_Plugin_Animals::present().
 * }
 */

defined( 'ABSPATH' ) || exit;

$asm_wp_plugin_animal = $args['animal'];
$asm_wp_plugin_fields = $asm_wp_plugin_animal['fields'];
?>
<article class="asm-wp-plugin-detail" data-id="<?php echo esc_attr( $asm_wp_plugin_animal['id'] ); ?>">

	<?php if ( $asm_wp_plugin_animal['images'] ) : ?>
		<div class="asm-wp-plugin-slides" data-asm-slides>
			<?php foreach ( $asm_wp_plugin_animal['images'] as $asm_wp_plugin_index => $asm_wp_plugin_image ) : ?>
				<div class="asm-wp-plugin-slide<?php echo 0 === $asm_wp_plugin_index ? ' is-active' : ''; ?>" data-asm-slide>
					<img
						src="<?php echo esc_url( $asm_wp_plugin_image ); ?>"
						alt="<?php /* translators: 1: animal name, 2: photo number, 3: number of photos. */ echo esc_attr( sprintf( __( '%1$s, photo %2$d of %3$d', 'asm-wp-plugin' ), $asm_wp_plugin_animal['name'], $asm_wp_plugin_index + 1, count( $asm_wp_plugin_animal['images'] ) ) ); ?>"
						decoding="async"
						<?php echo 0 === $asm_wp_plugin_index ? '' : 'loading="lazy"'; ?>
					>
				</div>
			<?php endforeach; ?>

			<?php if ( count( $asm_wp_plugin_animal['images'] ) > 1 ) : ?>
				<button type="button" class="asm-wp-plugin-slides__nav asm-wp-plugin-slides__nav--prev" data-asm-step="-1" aria-label="<?php esc_attr_e( 'Previous photo', 'asm-wp-plugin' ); ?>">&#10094;</button>
				<button type="button" class="asm-wp-plugin-slides__nav asm-wp-plugin-slides__nav--next" data-asm-step="1" aria-label="<?php esc_attr_e( 'Next photo', 'asm-wp-plugin' ); ?>">&#10095;</button>
			<?php endif; ?>
		</div>
	<?php endif; ?>

	<div class="asm-wp-plugin-detail__header">
		<h2 class="asm-wp-plugin-detail__name"><?php echo esc_html( $asm_wp_plugin_animal['name'] ); ?></h2>
		<p class="asm-wp-plugin-detail__label"><?php echo esc_html( $asm_wp_plugin_animal['label'] ); ?></p>
	</div>

	<?php if ( $asm_wp_plugin_animal['special_needs'] ) : ?>
		<p class="asm-wp-plugin-detail__special">&#10084; <?php esc_html_e( 'Has special needs', 'asm-wp-plugin' ); ?></p>
	<?php endif; ?>

	<div class="asm-wp-plugin-detail__facts">
		<?php if ( in_array( 'fee', $asm_wp_plugin_fields, true ) && '' !== $asm_wp_plugin_animal['fee_text'] ) : ?>
			<p>
				<?php
				echo esc_html(
					$asm_wp_plugin_animal['companions']
						? __( 'Adoption fee (together):', 'asm-wp-plugin' )
						: __( 'Adoption fee:', 'asm-wp-plugin' )
				);
				?>
				<?php echo esc_html( $asm_wp_plugin_animal['fee_text'] ); ?>
			</p>
		<?php endif; ?>

		<?php if ( in_array( 'bonded', $asm_wp_plugin_fields, true ) && $asm_wp_plugin_animal['companions'] ) : ?>
			<p>
				<?php esc_html_e( 'Bonded with:', 'asm-wp-plugin' ); ?>
				<?php foreach ( $asm_wp_plugin_animal['companions'] as $asm_wp_plugin_index => $asm_wp_plugin_companion ) : ?>
					<?php echo $asm_wp_plugin_index ? ', ' : ''; ?>
					<a class="asm-wp-plugin-detail__companion" href="<?php echo esc_url( $asm_wp_plugin_companion['url'] ); ?>" data-asm-animal="<?php echo esc_attr( $asm_wp_plugin_companion['id'] ); ?>"><?php echo esc_html( $asm_wp_plugin_companion['name'] ); ?></a>
				<?php endforeach; ?>
			</p>
		<?php endif; ?>

		<?php if ( in_array( 'sex', $asm_wp_plugin_fields, true ) && '' !== $asm_wp_plugin_animal['sex'] ) : ?>
			<p><?php esc_html_e( 'Sex:', 'asm-wp-plugin' ); ?> <?php echo esc_html( $asm_wp_plugin_animal['sex'] ); ?></p>
		<?php endif; ?>

		<?php if ( in_array( 'age', $asm_wp_plugin_fields, true ) && '' !== $asm_wp_plugin_animal['age_text'] ) : ?>
			<p>
				<?php echo esc_html( $asm_wp_plugin_animal['dob_estimated'] ? __( 'Age (estimated):', 'asm-wp-plugin' ) : __( 'Age:', 'asm-wp-plugin' ) ); ?>
				<?php echo esc_html( $asm_wp_plugin_animal['age_text'] ); ?>
			</p>
		<?php endif; ?>

		<?php if ( in_array( 'size', $asm_wp_plugin_fields, true ) && '' !== $asm_wp_plugin_animal['size'] ) : ?>
			<p><?php esc_html_e( 'Size:', 'asm-wp-plugin' ); ?> <?php echo esc_html( $asm_wp_plugin_animal['size'] ); ?></p>
		<?php endif; ?>

		<?php if ( in_array( 'arrival', $asm_wp_plugin_fields, true ) && '' !== $asm_wp_plugin_animal['date_in_text'] ) : ?>
			<p><?php esc_html_e( 'With us since:', 'asm-wp-plugin' ); ?> <?php echo esc_html( $asm_wp_plugin_animal['date_in_text'] ); ?></p>
		<?php endif; ?>

		<?php if ( in_array( 'code', $asm_wp_plugin_fields, true ) && '' !== $asm_wp_plugin_animal['code'] ) : ?>
			<p><?php esc_html_e( 'Shelter code:', 'asm-wp-plugin' ); ?> <?php echo esc_html( $asm_wp_plugin_animal['code'] ); ?></p>
		<?php endif; ?>
	</div>

	<?php if ( '' !== $asm_wp_plugin_animal['description'] ) : ?>
		<div class="asm-wp-plugin-detail__description">
			<?php echo wp_kses_post( wpautop( $asm_wp_plugin_animal['description'] ) ); ?>
		</div>
	<?php endif; ?>

	<?php if ( '' !== $asm_wp_plugin_animal['apply_url'] ) : ?>
		<div class="asm-wp-plugin-detail__apply">
			<a href="<?php echo esc_url( $asm_wp_plugin_animal['apply_url'] ); ?>"><?php echo esc_html( $asm_wp_plugin_animal['apply_label'] ); ?></a>
		</div>
	<?php endif; ?>

	<div class="asm-wp-plugin-share">
		<a class="asm-wp-plugin-share__link" href="<?php echo esc_url( $asm_wp_plugin_animal['share_facebook'] ); ?>" target="_blank" rel="noopener noreferrer" aria-label="<?php esc_attr_e( 'Share on Facebook', 'asm-wp-plugin' ); ?>">
			<?php echo ASM_WP_Plugin_Renderer::icon( 'facebook' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Fixed SVG markup. ?>
		</a>
		<a class="asm-wp-plugin-share__link" href="<?php echo esc_url( $asm_wp_plugin_animal['share_x'] ); ?>" target="_blank" rel="noopener noreferrer" aria-label="<?php esc_attr_e( 'Share on X', 'asm-wp-plugin' ); ?>">
			<?php echo ASM_WP_Plugin_Renderer::icon( 'x' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Fixed SVG markup. ?>
		</a>
		<a class="asm-wp-plugin-share__link" href="<?php echo esc_url( $asm_wp_plugin_animal['share_email'], array( 'mailto' ) ); ?>" aria-label="<?php esc_attr_e( 'Share by email', 'asm-wp-plugin' ); ?>">
			<?php echo ASM_WP_Plugin_Renderer::icon( 'email' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Fixed SVG markup. ?>
		</a>
		<a class="asm-wp-plugin-share__link asm-wp-plugin-share__link--copy" href="<?php echo esc_url( $asm_wp_plugin_animal['url'] ); ?>" data-asm-copy aria-label="<?php esc_attr_e( 'Copy link', 'asm-wp-plugin' ); ?>">
			<?php echo ASM_WP_Plugin_Renderer::icon( 'link' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Fixed SVG markup. ?>
			<span class="asm-wp-plugin-share__tooltip" data-asm-copied role="status"></span>
		</a>
	</div>
	<p class="asm-wp-plugin-share__caption"><?php esc_html_e( 'Share me!', 'asm-wp-plugin' ); ?></p>
</article>
