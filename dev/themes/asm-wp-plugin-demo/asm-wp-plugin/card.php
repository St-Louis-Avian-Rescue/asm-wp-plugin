<?php
/**
 * Theme override of the plugin's templates/card.php: adds sex, age and
 * special-needs tags under the name.
 *
 * @package ASM_WP_Plugin
 *
 * @var array $args { @type array $animal Animal fields plus display values. }
 */

defined( 'ABSPATH' ) || exit;

$asm_wp_plugin_animal = $args['animal'];
?>
<li
	class="asm-wp-plugin-card"
	data-id="<?php echo esc_attr( $asm_wp_plugin_animal['id'] ); ?>"
	data-name="<?php echo esc_attr( $asm_wp_plugin_animal['name'] ); ?>"
	data-search="<?php echo esc_attr( strtolower( $asm_wp_plugin_animal['name'] . ' ' . $asm_wp_plugin_animal['label'] ) ); ?>"
	data-species="<?php echo esc_attr( $asm_wp_plugin_animal['species'] ); ?>"
	data-sex="<?php echo esc_attr( $asm_wp_plugin_animal['sex'] ); ?>"
	data-size="<?php echo esc_attr( $asm_wp_plugin_animal['size'] ); ?>"
	data-special="<?php echo $asm_wp_plugin_animal['special_needs'] ? '1' : '0'; ?>"
	data-date-in="<?php echo esc_attr( $asm_wp_plugin_animal['date_in'] ); ?>"
	data-dob="<?php echo esc_attr( $asm_wp_plugin_animal['dob'] ); ?>"
>
	<a class="asm-wp-plugin-card__link" href="<?php echo esc_url( $asm_wp_plugin_animal['url'] ); ?>" data-asm-animal="<?php echo esc_attr( $asm_wp_plugin_animal['id'] ); ?>">
		<span class="asm-wp-plugin-card__image">
			<?php if ( $asm_wp_plugin_animal['image_count'] > 0 ) : ?>
				<img src="<?php echo esc_url( $asm_wp_plugin_animal['card_image'] ); ?>" alt="" loading="lazy" decoding="async">
			<?php endif; ?>
		</span>
		<span class="asm-wp-plugin-card__name"><?php echo esc_html( $asm_wp_plugin_animal['name'] ); ?></span>
		<span class="asm-wp-plugin-card__details"><?php echo esc_html( $asm_wp_plugin_animal['label'] ); ?></span>
		<span class="asm-wp-plugin-demo-tags">
			<?php if ( '' !== $asm_wp_plugin_animal['sex'] && 'Unknown' !== $asm_wp_plugin_animal['sex'] ) : ?>
				<span class="asm-wp-plugin-demo-tag"><?php echo esc_html( $asm_wp_plugin_animal['sex'] ); ?></span>
			<?php endif; ?>
			<?php if ( '' !== $asm_wp_plugin_animal['age_text'] ) : ?>
				<span class="asm-wp-plugin-demo-tag"><?php echo esc_html( $asm_wp_plugin_animal['age_text'] ); ?></span>
			<?php endif; ?>
			<?php if ( $asm_wp_plugin_animal['special_needs'] ) : ?>
				<span class="asm-wp-plugin-demo-tag asm-wp-plugin-demo-tag--special"><?php esc_html_e( 'Special needs', 'asm-wp-plugin-demo' ); ?></span>
			<?php endif; ?>
		</span>
	</a>
</li>
