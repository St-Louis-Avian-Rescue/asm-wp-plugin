<?php
/**
 * The dialog that shows one animal's details.
 *
 * Override by copying to yourtheme/asm-wp-plugin/modal.php. Keep the data-*
 * attributes: the page script finds its elements through them.
 *
 * @package ASM_WP_Plugin
 *
 * @var array $args {
 *     @type string $base_url URL of this page, where the close link goes without JavaScript.
 *     @type string $detail   Rendered details when the page was opened on one animal, or ''.
 * }
 */

defined( 'ABSPATH' ) || exit;
?>
<div
	class="asm-wp-plugin-modal<?php echo '' !== $args['detail'] ? ' is-open' : ''; ?>"
	data-asm-modal
	role="dialog"
	aria-modal="true"
	aria-label="<?php esc_attr_e( 'Animal details', 'asm-wp-plugin' ); ?>"
	<?php echo '' === $args['detail'] ? 'hidden' : ''; ?>
>
	<div class="asm-wp-plugin-modal__content">
		<div class="asm-wp-plugin-modal__header">
			<a class="asm-wp-plugin-modal__close" href="<?php echo esc_url( $args['base_url'] ); ?>" data-asm-close aria-label="<?php esc_attr_e( 'Close', 'asm-wp-plugin' ); ?>">&times;</a>
		</div>
		<div class="asm-wp-plugin-modal__body" data-asm-modal-body>
			<?php echo $args['detail']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Rendered by the detail template. ?>
		</div>
	</div>
</div>
