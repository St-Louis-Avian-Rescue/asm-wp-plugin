<?php
/**
 * Animal data: mapping the ASM feed and preparing it for display.
 *
 * @package ASM_WP_Plugin
 */

defined( 'ABSPATH' ) || exit;

/**
 * Turns rows from ASM's json_adoptable_animals into the small set of public
 * fields the plugin stores and shows.
 */
final class ASM_WP_Plugin_Animals {

	/**
	 * Maps a decoded ASM feed to public animals, keyed by animal ID.
	 *
	 * Only the fields listed here are kept. The feed carries much more
	 * (owner names, addresses, internal notes) that must never be stored or
	 * sent to visitors.
	 *
	 * @param array $rows Decoded feed: a list of rows with upper-case keys.
	 * @return array
	 */
	public static function map_feed( array $rows ) {
		$animals = array();

		foreach ( $rows as $row ) {
			$row = (array) $row;
			if ( empty( $row['ID'] ) ) {
				continue;
			}

			$id     = absint( $row['ID'] );
			$bonded = array();
			foreach ( array( 'BONDEDANIMALID', 'BONDEDANIMAL2ID' ) as $key ) {
				if ( ! empty( $row[ $key ] ) ) {
					$bonded[] = absint( $row[ $key ] );
				}
			}

			$animal = array(
				'id'            => $id,
				'name'          => self::text( $row, 'ANIMALNAME' ),
				'breed'         => self::text( $row, 'BREEDNAME' ),
				'species'       => self::text( $row, 'SPECIESNAME' ),
				'code'          => self::text( $row, 'SHELTERCODE' ),
				'description'   => isset( $row['ANIMALCOMMENTS'] ) ? trim( wp_kses_post( (string) $row['ANIMALCOMMENTS'] ) ) : '',
				'sex'           => self::text( $row, 'SEXNAME' ),
				'size'          => self::text( $row, 'SIZENAME' ),
				'special_needs' => ! empty( $row['HASSPECIALNEEDS'] ),
				'dob'           => self::date( $row, 'DATEOFBIRTH' ),
				'dob_estimated' => ! empty( $row['ESTIMATEDDOB'] ),
				'date_in'       => self::date( $row, 'DATEBROUGHTIN' ),
				'fee'           => isset( $row['FEE'] ) ? max( 0, (int) $row['FEE'] ) : 0,
				'bonded'        => array_values( array_unique( array_diff( $bonded, array( $id ) ) ) ),
				'image_count'   => isset( $row['WEBSITEIMAGECOUNT'] ) ? min( 50, absint( $row['WEBSITEIMAGECOUNT'] ) ) : 0,
			);

			/**
			 * Filters one mapped animal, for sites that want to keep more of the ASM row.
			 *
			 * Anything added here is public: it is cached and served by the REST route.
			 *
			 * @param array $animal Public fields.
			 * @param array $row    The raw ASM row.
			 */
			$animals[ $id ] = apply_filters( 'asm_wp_plugin_map_animal', $animal, $row );
		}

		return $animals;
	}

	/**
	 * A plain-text field from an ASM row.
	 *
	 * @param array  $row ASM row.
	 * @param string $key Field name.
	 * @return string
	 */
	private static function text( array $row, $key ) {
		return isset( $row[ $key ] ) && is_scalar( $row[ $key ] ) ? sanitize_text_field( (string) $row[ $key ] ) : '';
	}

	/**
	 * A date field from an ASM row as Y-m-d, or '' if missing or malformed.
	 *
	 * @param array  $row ASM row.
	 * @param string $key Field name.
	 * @return string
	 */
	private static function date( array $row, $key ) {
		if ( empty( $row[ $key ] ) || ! is_string( $row[ $key ] ) ) {
			return '';
		}

		$date = substr( $row[ $key ], 0, 10 );
		if ( ! preg_match( '/^(\d{4})-(\d{2})-(\d{2})$/', $date, $parts ) ) {
			return '';
		}

		return checkdate( (int) $parts[2], (int) $parts[3], (int) $parts[1] ) ? $date : '';
	}

	/**
	 * The breed-and-species line: "Greencheek Conure", or just the breed when
	 * it already names the species ("Cockatiel", "Quaker (Monk Parakeet)").
	 *
	 * @param array $animal Animal.
	 * @return string
	 */
	public static function label( array $animal ) {
		$breed   = $animal['breed'];
		$species = $animal['species'];

		if ( '' === $breed ) {
			return $species;
		}
		if ( '' === $species || false !== stripos( $breed, $species ) ) {
			return $breed;
		}

		return $breed . ' ' . $species;
	}

	/**
	 * Whole years between a date of birth and now.
	 *
	 * @param string   $dob Date of birth, Y-m-d.
	 * @param int|null $now Unix time to measure to. Defaults to the current time.
	 * @return int|null Null when there is no usable date of birth.
	 */
	public static function age_years( $dob, $now = null ) {
		if ( '' === $dob ) {
			return null;
		}

		$born = date_create_immutable( $dob, new DateTimeZone( 'UTC' ) );
		if ( ! $born ) {
			return null;
		}

		$today = date_create_immutable( gmdate( 'Y-m-d', null === $now ? time() : $now ), new DateTimeZone( 'UTC' ) );
		if ( $born > $today ) {
			return 0;
		}

		return (int) $born->diff( $today )->y;
	}

	/**
	 * Age as visitors read it.
	 *
	 * @param string   $dob Date of birth, Y-m-d.
	 * @param int|null $now Unix time to measure to.
	 * @return string '' when there is no usable date of birth.
	 */
	public static function age_text( $dob, $now = null ) {
		$years = self::age_years( $dob, $now );
		if ( null === $years ) {
			return '';
		}
		if ( $years < 1 ) {
			return __( 'Less than a year old', 'asm-wp-plugin' );
		}

		/* translators: %s: age in years. */
		return sprintf( _n( '%s year', '%s years', $years, 'asm-wp-plugin' ), number_format_i18n( $years ) );
	}

	/**
	 * Formats an ASM money amount. ASM stores money in the smallest unit (cents).
	 *
	 * @param int   $minor    Amount in the smallest currency unit.
	 * @param array $settings Plugin settings.
	 * @return string
	 */
	public static function format_money( $minor, array $settings ) {
		$minor  = (int) $minor;
		$amount = number_format_i18n( $minor / 100, 0 === $minor % 100 ? 0 : 2 );
		$symbol = $settings['currency_symbol'];

		return 'after' === $settings['currency_position'] ? $amount . $symbol : $symbol . $amount;
	}

	/**
	 * The bonded companions of an animal that are themselves in the list.
	 *
	 * @param array $animal  Animal.
	 * @param array $animals All animals, keyed by ID.
	 * @return array Companion animals.
	 */
	public static function companions( array $animal, array $animals ) {
		$companions = array();

		foreach ( $animal['bonded'] as $id ) {
			if ( isset( $animals[ $id ] ) ) {
				$companions[ $id ] = $animals[ $id ];
			}
		}

		// A bond recorded on only one of the two animals still counts.
		foreach ( $animals as $other ) {
			if ( $other['id'] !== $animal['id'] && in_array( $animal['id'], $other['bonded'], true ) ) {
				$companions[ $other['id'] ] = $other;
			}
		}

		return array_values( $companions );
	}

	/**
	 * The URL of one of an animal's photos on the ASM service.
	 *
	 * @param int    $id       Animal ID.
	 * @param int    $seq      Photo number, starting at 1.
	 * @param array  $settings Plugin settings.
	 * @param string $method   animal_image or animal_thumbnail.
	 * @return string
	 */
	public static function image_url( $id, $seq, array $settings, $method = 'animal_image' ) {
		$args = array();
		if ( '' !== $settings['account'] ) {
			$args['account'] = rawurlencode( $settings['account'] );
		}
		$args['method']   = $method;
		$args['animalid'] = (int) $id;
		if ( 'animal_image' === $method ) {
			$args['seq'] = (int) $seq;
		}

		$url = add_query_arg( $args, $settings['service_url'] );

		/**
		 * Filters a photo URL, for sites that proxy or resize ASM images.
		 *
		 * @param string $url    Photo URL.
		 * @param int    $id     Animal ID.
		 * @param int    $seq    Photo number.
		 * @param string $method animal_image or animal_thumbnail.
		 */
		return apply_filters( 'asm_wp_plugin_image_url', $url, (int) $id, (int) $seq, $method );
	}

	/**
	 * Fills the placeholders in a URL template.
	 *
	 * @param string $template URL with {id}, {code} and {name} placeholders.
	 * @param array  $animal   Animal.
	 * @return string
	 */
	public static function fill_url( $template, array $animal ) {
		if ( '' === $template ) {
			return '';
		}

		return strtr(
			$template,
			array(
				'{id}'   => rawurlencode( (string) $animal['id'] ),
				'{code}' => rawurlencode( $animal['code'] ),
				'{name}' => rawurlencode( $animal['name'] ),
			)
		);
	}

	/**
	 * Applies a shortcode's species filter, sort order and limit.
	 *
	 * @param array $animals All animals, keyed by ID.
	 * @param array $atts    Normalised shortcode attributes.
	 * @return array Animals as a list.
	 */
	public static function query( array $animals, array $atts ) {
		$animals = array_values( $animals );

		if ( ! empty( $atts['species'] ) ) {
			$wanted  = array_map( 'strtolower', $atts['species'] );
			$animals = array_values(
				array_filter(
					$animals,
					function ( $animal ) use ( $wanted ) {
						return in_array( strtolower( $animal['species'] ), $wanted, true );
					}
				)
			);
		}

		$animals = self::sort( $animals, $atts['sort'] );

		if ( $atts['limit'] > 0 ) {
			$animals = array_slice( $animals, 0, $atts['limit'] );
		}

		return $animals;
	}

	/**
	 * Sorts a list of animals. Ties and missing dates fall back to name.
	 *
	 * @param array  $animals Animals as a list.
	 * @param string $order   name, newest, youngest or oldest.
	 * @return array
	 */
	public static function sort( array $animals, $order ) {
		$by_name = function ( $a, $b ) {
			$result = strcasecmp( $a['name'], $b['name'] );
			return 0 !== $result ? $result : $a['id'] - $b['id'];
		};

		// Compares two dates, always putting animals without one last.
		$by_date = function ( $key, $newest_first ) use ( $by_name ) {
			return function ( $a, $b ) use ( $key, $newest_first, $by_name ) {
				if ( $a[ $key ] === $b[ $key ] ) {
					return $by_name( $a, $b );
				}
				if ( '' === $a[ $key ] ) {
					return 1;
				}
				if ( '' === $b[ $key ] ) {
					return -1;
				}
				$result = strcmp( $a[ $key ], $b[ $key ] );
				return $newest_first ? -$result : $result;
			};
		};

		switch ( $order ) {
			case 'newest':
				usort( $animals, $by_date( 'date_in', true ) );
				break;
			case 'youngest':
				usort( $animals, $by_date( 'dob', true ) );
				break;
			case 'oldest':
				usort( $animals, $by_date( 'dob', false ) );
				break;
			default:
				usort( $animals, $by_name );
		}

		return $animals;
	}

	/**
	 * Everything a template needs to show one animal.
	 *
	 * @param array  $animal   Animal.
	 * @param array  $animals  All animals, keyed by ID.
	 * @param array  $settings Plugin settings.
	 * @param string $base_url URL of the page the listing is on.
	 * @return array
	 */
	public static function present( array $animal, array $animals, array $settings, $base_url ) {
		$id     = $animal['id'];
		$images = array();
		for ( $seq = 1; $seq <= $animal['image_count']; $seq++ ) {
			$images[] = self::image_url( $id, $seq, $settings );
		}

		$card_image = 'thumbnail' === $settings['card_image']
			? self::image_url( $id, 1, $settings, 'animal_thumbnail' )
			: self::image_url( $id, 1, $settings );

		$companions = array();
		$fee        = $animal['fee'];
		foreach ( self::companions( $animal, $animals ) as $companion ) {
			$fee         += $companion['fee'];
			$companions[] = array(
				'id'   => $companion['id'],
				'name' => $companion['name'],
				'url'  => self::animal_url( $companion['id'], $base_url ),
			);
		}

		$url        = self::animal_url( $id, $base_url );
		$share_text = '' !== $settings['share_text']
			? $settings['share_text']
			/* translators: {name} is replaced with the animal's name. Keep it as is. */
			: __( '{name} is looking for a new family', 'asm-wp-plugin' );
		$share_text = str_replace( '{name}', $animal['name'], $share_text );

		$view = array_merge(
			$animal,
			array(
				'label'          => self::label( $animal ),
				'url'            => $url,
				'card_image'     => $card_image,
				'images'         => $images,
				'companions'     => $companions,
				'fee_total'      => $fee,
				'fee_text'       => $fee > 0 ? self::format_money( $fee, $settings ) : '',
				'age_text'       => self::age_text( $animal['dob'] ),
				'date_in_text'   => '' !== $animal['date_in'] ? date_i18n( get_option( 'date_format' ), strtotime( $animal['date_in'] . ' 12:00:00' ) ) : '',
				'apply_url'      => self::fill_url( $settings['apply_url'], $animal ),
				'apply_label'    => '' !== $settings['apply_label'] ? $settings['apply_label'] : __( 'Click here to fill out an adoption application.', 'asm-wp-plugin' ),
				'share_text'     => $share_text,
				'share_facebook' => 'https://www.facebook.com/sharer/sharer.php?u=' . rawurlencode( $url ),
				'share_x'        => 'https://twitter.com/intent/tweet?url=' . rawurlencode( $url ) . '&text=' . rawurlencode( $share_text ),
				'share_email'    => 'mailto:?subject=' . rawurlencode(
					/* translators: %s: animal name. */
					sprintf( __( 'Adopt - %s', 'asm-wp-plugin' ), $animal['name'] )
				) . '&body=' . rawurlencode( $share_text . "\n" . $url ),
				'fields'         => array_values( (array) $settings['detail_fields'] ),
			)
		);

		/**
		 * Filters the data passed to the card and detail templates.
		 *
		 * @param array $view   Animal fields plus display values.
		 * @param array $animal The stored animal.
		 */
		return apply_filters( 'asm_wp_plugin_present_animal', $view, $animal );
	}

	/**
	 * The shareable link that opens one animal.
	 *
	 * @param int    $id       Animal ID.
	 * @param string $base_url URL of the page the listing is on.
	 * @return string
	 */
	public static function animal_url( $id, $base_url ) {
		return add_query_arg( 'animal', (int) $id, remove_query_arg( 'animal', $base_url ) );
	}

	/**
	 * The fields served by the REST route for one animal.
	 *
	 * @param array $animal   Animal.
	 * @param array $animals  All animals, keyed by ID.
	 * @param array $settings Plugin settings.
	 * @return array
	 */
	public static function to_public( array $animal, array $animals, array $settings ) {
		$images = array();
		for ( $seq = 1; $seq <= $animal['image_count']; $seq++ ) {
			$images[] = self::image_url( $animal['id'], $seq, $settings );
		}

		$animal['label']     = self::label( $animal );
		$animal['images']    = $images;
		$animal['thumbnail'] = self::image_url( $animal['id'], 1, $settings, 'animal_thumbnail' );
		$animal['age_years'] = self::age_years( $animal['dob'] );
		$animal['bonded']    = wp_list_pluck( self::companions( $animal, $animals ), 'id' );

		return $animal;
	}
}
