<?php
/**
 * The optional search and filter bar above the grid.
 *
 * Override by copying to yourtheme/asm-wp-plugin/filters.php. Keep the
 * data-asm-filter attributes: the page script reads the controls through them.
 *
 * @package ASM_WP_Plugin
 *
 * @var array $args {
 *     @type string[] $controls Controls to show: search, species, sex, size, special, sort.
 *     @type string   $sort     The listing's own sort order.
 *     @type array    $options  Values for the species, sex and size menus.
 *     @type bool     $special  Whether any listed animal has special needs.
 * }
 */

defined( 'ABSPATH' ) || exit;

$asm_wp_plugin_controls = $args['controls'];
$asm_wp_plugin_menus    = array(
	'species' => array( __( 'Species', 'asm-wp-plugin' ), __( 'All species', 'asm-wp-plugin' ) ),
	'sex'     => array( __( 'Sex', 'asm-wp-plugin' ), __( 'Any sex', 'asm-wp-plugin' ) ),
	'size'    => array( __( 'Size', 'asm-wp-plugin' ), __( 'Any size', 'asm-wp-plugin' ) ),
);
$asm_wp_plugin_sorts    = array(
	'name'     => __( 'Name', 'asm-wp-plugin' ),
	'newest'   => __( 'Newest arrivals', 'asm-wp-plugin' ),
	'youngest' => __( 'Youngest first', 'asm-wp-plugin' ),
	'oldest'   => __( 'Oldest first', 'asm-wp-plugin' ),
);
?>
<form class="asm-wp-plugin-filters" data-asm-filters role="search" aria-label="<?php esc_attr_e( 'Search and filter animals', 'asm-wp-plugin' ); ?>">

	<?php if ( in_array( 'search', $asm_wp_plugin_controls, true ) ) : ?>
		<label class="asm-wp-plugin-filters__field asm-wp-plugin-filters__field--search">
			<span class="asm-wp-plugin-filters__label"><?php esc_html_e( 'Search', 'asm-wp-plugin' ); ?></span>
			<input type="search" data-asm-filter="q" placeholder="<?php esc_attr_e( 'Name or breed', 'asm-wp-plugin' ); ?>" autocomplete="off">
		</label>
	<?php endif; ?>

	<?php foreach ( $asm_wp_plugin_menus as $asm_wp_plugin_key => $asm_wp_plugin_text ) : ?>
		<?php if ( in_array( $asm_wp_plugin_key, $asm_wp_plugin_controls, true ) && count( $args['options'][ $asm_wp_plugin_key ] ) > 1 ) : ?>
			<label class="asm-wp-plugin-filters__field">
				<span class="asm-wp-plugin-filters__label"><?php echo esc_html( $asm_wp_plugin_text[0] ); ?></span>
				<select data-asm-filter="<?php echo esc_attr( $asm_wp_plugin_key ); ?>">
					<option value=""><?php echo esc_html( $asm_wp_plugin_text[1] ); ?></option>
					<?php foreach ( $args['options'][ $asm_wp_plugin_key ] as $asm_wp_plugin_option ) : ?>
						<option value="<?php echo esc_attr( $asm_wp_plugin_option ); ?>"><?php echo esc_html( $asm_wp_plugin_option ); ?></option>
					<?php endforeach; ?>
				</select>
			</label>
		<?php endif; ?>
	<?php endforeach; ?>

	<?php if ( in_array( 'sort', $asm_wp_plugin_controls, true ) ) : ?>
		<label class="asm-wp-plugin-filters__field">
			<span class="asm-wp-plugin-filters__label"><?php esc_html_e( 'Sort by', 'asm-wp-plugin' ); ?></span>
			<select data-asm-filter="sort" data-default="<?php echo esc_attr( $args['sort'] ); ?>">
				<?php foreach ( $asm_wp_plugin_sorts as $asm_wp_plugin_key => $asm_wp_plugin_text ) : ?>
					<option value="<?php echo esc_attr( $asm_wp_plugin_key ); ?>"<?php selected( $asm_wp_plugin_key, $args['sort'] ); ?>><?php echo esc_html( $asm_wp_plugin_text ); ?></option>
				<?php endforeach; ?>
			</select>
		</label>
	<?php endif; ?>

	<?php if ( in_array( 'special', $asm_wp_plugin_controls, true ) && $args['special'] ) : ?>
		<label class="asm-wp-plugin-filters__field asm-wp-plugin-filters__field--check">
			<input type="checkbox" data-asm-filter="special" value="1">
			<span><?php esc_html_e( 'Special needs only', 'asm-wp-plugin' ); ?></span>
		</label>
	<?php endif; ?>

	<button type="button" class="asm-wp-plugin-filters__clear" data-asm-clear hidden><?php esc_html_e( 'Clear', 'asm-wp-plugin' ); ?></button>

	<p class="asm-wp-plugin-filters__count" data-asm-count aria-live="polite"></p>
</form>
