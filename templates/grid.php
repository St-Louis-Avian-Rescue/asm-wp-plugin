<?php
/**
 * The listing: filter bar, grid of cards and the details dialog.
 *
 * Override by copying to yourtheme/asm-wp-plugin/grid.php. Keep the data-*
 * attributes: the page script finds its elements through them.
 *
 * @package ASM_WP_Plugin
 *
 * @var array $args {
 *     @type string $id       Unique ID for this listing.
 *     @type array  $atts     Normalised shortcode attributes.
 *     @type string $hash     Fingerprint of the data this was rendered from.
 *     @type string $base_url URL of this page.
 *     @type int    $count    Number of animals.
 *     @type string $list     Rendered cards.
 *     @type string $filters  Rendered filter bar, or ''.
 *     @type string $modal    Rendered details dialog.
 *     @type bool   $credit   Whether to show the "Built with" line.
 * }
 */

defined( 'ABSPATH' ) || exit;

$asm_wp_plugin_columns = $args['atts']['columns'];
$asm_wp_plugin_request = array(
	'species' => implode( ',', $args['atts']['species'] ),
	'sort'    => $args['atts']['sort'],
	'limit'   => $args['atts']['limit'],
	'filters' => null === $args['atts']['filters'] ? '' : ( $args['atts']['filters'] ? implode( ',', $args['atts']['filters'] ) : 'none' ),
);
?>
<div
	id="<?php echo esc_attr( $args['id'] ); ?>"
	class="asm-wp-plugin<?php echo $asm_wp_plugin_columns ? ' asm-wp-plugin--columns' : ''; ?>"
	data-asm-wp-plugin
	data-hash="<?php echo esc_attr( $args['hash'] ); ?>"
	data-base="<?php echo esc_url( $args['base_url'] ); ?>"
	data-request="<?php echo esc_attr( wp_json_encode( $asm_wp_plugin_request ) ); ?>"
	<?php if ( $asm_wp_plugin_columns ) : ?>
		style="--asm-wp-plugin-columns: <?php echo (int) $asm_wp_plugin_columns; ?>"
	<?php endif; ?>
>
	<div data-asm-filters-slot>
		<?php echo $args['filters']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Rendered by the filters template. ?>
	</div>

	<ul class="asm-wp-plugin-grid" data-asm-grid>
		<?php echo $args['list']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Rendered by the card template. ?>
	</ul>

	<p class="asm-wp-plugin-message" data-asm-empty<?php echo $args['count'] ? ' hidden' : ''; ?>>
		<?php esc_html_e( 'There are no animals available for adoption right now. Please check back soon.', 'asm-wp-plugin' ); ?>
	</p>
	<p class="asm-wp-plugin-message" data-asm-no-matches hidden>
		<?php esc_html_e( 'No animals match your search.', 'asm-wp-plugin' ); ?>
	</p>

	<?php if ( ! empty( $args['credit'] ) ) : ?>
		<p class="asm-wp-plugin-credit">
			<?php
			printf(
				/* translators: %s: the plugin's name, as a link. */
				esc_html__( 'Built with %s', 'asm-wp-plugin' ),
				'<a href="' . esc_url( ASM_WP_PLUGIN_HOMEPAGE ) . '" rel="noopener">ASM WP Plugin</a>'
			);
			?>
		</p>
	<?php endif; ?>

	<?php echo $args['modal']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Rendered by the modal template. ?>
</div>
