<?php
/**
 * Mock of the Animal Shelter Manager service API, for local development.
 *
 * Serves the made-up feed in adoptable-animals.json and drawn stand-in
 * photos, so no ASM account or internet connection is needed.
 * Run by PHP's built-in server; see dev/docker-compose.yml.
 *
 *   /asmservice?method=json_adoptable_animals&account=demo&username=demo&password=demo
 *   /asmservice?method=animal_image&animalid=101&seq=1
 *   /asmservice?method=animal_thumbnail&animalid=101
 *   /control?down=1|0     make the feed fail, or work again
 *   /control?hide=ID      drop an animal from the feed (as if adopted)
 *   /control?reset=1      undo down and hide
 */

const STATE_FILE = '/tmp/asm-mock-state.json';

$state = array(
	'down'   => false,
	'hidden' => array(),
);
if ( is_file( STATE_FILE ) ) {
	$state = array_merge( $state, (array) json_decode( file_get_contents( STATE_FILE ), true ) );
}

$path = parse_url( $_SERVER['REQUEST_URI'], PHP_URL_PATH );

if ( '/control' === $path ) {
	if ( isset( $_GET['reset'] ) ) {
		$state['down']   = false;
		$state['hidden'] = array();
	}
	if ( isset( $_GET['down'] ) ) {
		$state['down'] = (bool) $_GET['down'];
	}
	if ( isset( $_GET['hide'] ) ) {
		$state['hidden'][] = (int) $_GET['hide'];
	}
	file_put_contents( STATE_FILE, json_encode( $state ) );
	header( 'Content-Type: application/json' );
	echo json_encode( $state );
	return true;
}

if ( '/asmservice' !== $path && '/service' !== $path ) {
	http_response_code( 404 );
	echo 'Not found';
	return true;
}

$method  = $_GET['method'] ?? '';
$animals = json_decode( file_get_contents( __DIR__ . '/adoptable-animals.json' ), true );

if ( 'json_adoptable_animals' === $method ) {
	if ( $state['down'] ) {
		http_response_code( 500 );
		echo 'Mock ASM is switched off (/control?down=0 to restore).';
		return true;
	}
	if ( 'demo' !== ( $_GET['username'] ?? '' ) || 'demo' !== ( $_GET['password'] ?? '' ) ) {
		// The real service answers a bad login with an error page, not JSON.
		http_response_code( 403 );
		echo 'Invalid username and password';
		return true;
	}
	$animals = array_values(
		array_filter(
			$animals,
			function ( $animal ) use ( $state ) {
				return ! in_array( (int) $animal['ID'], $state['hidden'], true );
			}
		)
	);
	header( 'Content-Type: application/json' );
	echo json_encode( $animals );
	return true;
}

if ( 'animal_image' === $method || 'animal_thumbnail' === $method ) {
	$id      = (int) ( $_GET['animalid'] ?? 0 );
	$seq     = max( 1, (int) ( $_GET['seq'] ?? 1 ) );
	$species = '';
	foreach ( $animals as $animal ) {
		if ( (int) $animal['ID'] === $id ) {
			$species = $animal['SPECIESNAME'];
		}
	}

	header( 'Content-Type: image/svg+xml' );
	header( 'Cache-Control: public, max-age=3600' );
	echo placeholder_photo( $id, $seq, $species );
	return true;
}

http_response_code( 400 );
echo 'Unknown method';
return true;

/**
 * A stand-in "photo": a simple drawing of the species, in colours that differ
 * per animal. Later photos of the same animal change the backdrop and the pose
 * so the carousel visibly moves.
 */
function placeholder_photo( int $id, int $seq, string $species ): string {
	$coats = array(
		array( '#c98f5a', '#8a5a34', '#f3dcc3' ), // Tan.
		array( '#8d8f98', '#5b5d66', '#e6e7ec' ), // Grey.
		array( '#e2b96f', '#a97b36', '#fbefd6' ), // Gold.
		array( '#6b4a3a', '#3f2a20', '#d9c2b3' ), // Brown.
		array( '#f1ede6', '#c9c1b4', '#ffffff' ), // White.
		array( '#3d3d45', '#1f1f25', '#b9b9c4' ), // Black.
		array( '#d9794a', '#9c4b25', '#fbe3d2' ), // Ginger.
	);
	$plumage = array(
		array( '#3fae6b', '#22794a', '#f4d35e' ), // Green.
		array( '#4b9fe0', '#2a6fb0', '#f4d35e' ), // Blue.
		array( '#e2574c', '#a8322a', '#f4d35e' ), // Red.
		array( '#9aa3ad', '#667079', '#e2574c' ), // Grey.
		array( '#f2c744', '#c4952a', '#f08a4b' ), // Yellow.
	);

	$hue  = ( $id * 53 + ( $seq - 1 ) * 24 ) % 360;
	$bg   = "hsl($hue,46%,86%)";
	$halo = "hsl($hue,52%,92%)";
	$tilt = array( 0, -6, 6, -3, 3, 0 )[ ( $seq - 1 ) % 6 ];
	$zoom = array( 1.25, 1.34, 1.18, 1.4, 1.25, 1.12 )[ ( $seq - 1 ) % 6 ];
	$flip = 0 === $seq % 2 ? -1 : 1;

	$eyes = '<circle cx="-46" cy="-14" r="13" fill="#26211f"/><circle cx="46" cy="-14" r="13" fill="#26211f"/>'
		. '<circle cx="-41" cy="-19" r="4.5" fill="#fff"/><circle cx="51" cy="-19" r="4.5" fill="#fff"/>';

	if ( 'Parrot' === $species ) {
		list( $main, $dark, $accent ) = $plumage[ $id % count( $plumage ) ];
		$art = '<path d="M-30 -150 q10 -60 34 -4 q22 -62 30 4 q34 -44 22 22 z" fill="' . $accent . '"/>'
			. '<ellipse cx="0" cy="150" rx="118" ry="130" fill="' . $dark . '"/>'
			. '<circle cx="0" cy="0" r="132" fill="' . $main . '"/>'
			. '<ellipse cx="-58" cy="-22" rx="40" ry="44" fill="#fff"/><ellipse cx="58" cy="-22" rx="40" ry="44" fill="#fff"/>'
			. '<circle cx="-54" cy="-20" r="15" fill="#26211f"/><circle cx="54" cy="-20" r="15" fill="#26211f"/>'
			. '<circle cx="-49" cy="-26" r="5" fill="#fff"/><circle cx="59" cy="-26" r="5" fill="#fff"/>'
			. '<path d="M-34 6 q34 -30 68 0 q4 62 -34 96 q-38 -34 -34 -96 z" fill="#3b3733"/>'
			. '<path d="M-22 10 q22 -18 44 0 q0 30 -22 46 q-22 -16 -22 -46 z" fill="#5a544e"/>';
	} else {
		list( $main, $dark, $light ) = $coats[ $id % count( $coats ) ];
		$nose = '<path d="M-16 26 h32 q0 20 -16 22 q-16 -2 -16 -22 z" fill="#3a2f2c"/>'
			. '<path d="M0 48 v16 M0 64 q-18 16 -32 4 M0 64 q18 16 32 4" fill="none" stroke="#3a2f2c" stroke-width="5" stroke-linecap="round"/>';
		switch ( $species ) {
			case 'Cat':
				$art = '<path d="M-124 -40 L-108 -170 L-30 -104 z" fill="' . $main . '"/><path d="M124 -40 L108 -170 L30 -104 z" fill="' . $main . '"/>'
					. '<path d="M-104 -70 L-98 -136 L-58 -100 z" fill="#f2b6b0"/><path d="M104 -70 L98 -136 L58 -100 z" fill="#f2b6b0"/>'
					. '<ellipse cx="0" cy="160" rx="120" ry="120" fill="' . $dark . '"/>'
					. '<ellipse cx="0" cy="0" rx="136" ry="118" fill="' . $main . '"/>'
					. '<ellipse cx="0" cy="48" rx="62" ry="46" fill="' . $light . '"/>'
					. '<ellipse cx="-50" cy="-14" rx="17" ry="20" fill="#7fb069"/><ellipse cx="50" cy="-14" rx="17" ry="20" fill="#7fb069"/>'
					. '<ellipse cx="-50" cy="-14" rx="6" ry="17" fill="#26211f"/><ellipse cx="50" cy="-14" rx="6" ry="17" fill="#26211f"/>'
					. '<path d="M-12 30 h24 l-12 14 z" fill="#e58f8a"/>'
					. '<path d="M0 44 v12 M0 56 q-14 14 -26 4 M0 56 q14 14 26 4 M-70 40 h-70 M-70 54 l-66 14 M70 40 h70 M70 54 l66 14" fill="none" stroke="#3a2f2c" stroke-width="4" stroke-linecap="round"/>';
				break;
			case 'Rabbit':
				$art = '<ellipse cx="-52" cy="-176" rx="34" ry="112" fill="' . $main . '" transform="rotate(-8 -52 -176)"/><ellipse cx="52" cy="-176" rx="34" ry="112" fill="' . $main . '" transform="rotate(8 52 -176)"/>'
					. '<ellipse cx="-52" cy="-170" rx="15" ry="84" fill="#f2b6b0" transform="rotate(-8 -52 -170)"/><ellipse cx="52" cy="-170" rx="15" ry="84" fill="#f2b6b0" transform="rotate(8 52 -170)"/>'
					. '<ellipse cx="0" cy="160" rx="118" ry="120" fill="' . $dark . '"/>'
					. '<ellipse cx="0" cy="0" rx="118" ry="112" fill="' . $main . '"/>'
					. '<ellipse cx="-28" cy="50" rx="34" ry="30" fill="' . $light . '"/><ellipse cx="28" cy="50" rx="34" ry="30" fill="' . $light . '"/>'
					. $eyes
					. '<path d="M-12 26 h24 l-12 14 z" fill="#e58f8a"/><rect x="-9" y="62" width="18" height="20" rx="3" fill="#fff"/>';
				break;
			case 'Guinea Pig':
				$art = '<ellipse cx="-112" cy="-70" rx="38" ry="30" fill="' . $dark . '" transform="rotate(-24 -112 -70)"/><ellipse cx="112" cy="-70" rx="38" ry="30" fill="' . $dark . '" transform="rotate(24 112 -70)"/>'
					. '<ellipse cx="0" cy="150" rx="150" ry="120" fill="' . $dark . '"/>'
					. '<ellipse cx="0" cy="10" rx="150" ry="116" fill="' . $main . '"/>'
					. '<path d="M-40 -104 q40 -20 80 0 q10 90 -40 150 q-50 -60 -40 -150 z" fill="' . $light . '"/>'
					. $eyes
					. '<ellipse cx="0" cy="40" rx="16" ry="11" fill="#e58f8a"/>'
					. '<path d="M0 51 v12 M0 63 q-12 12 -22 3 M0 63 q12 12 22 3" fill="none" stroke="#3a2f2c" stroke-width="4" stroke-linecap="round"/>';
				break;
			default: // Dog, and anything else.
				$art = '<ellipse cx="0" cy="160" rx="124" ry="120" fill="' . $dark . '"/>'
					. '<ellipse cx="0" cy="0" rx="122" ry="126" fill="' . $main . '"/>'
					. '<ellipse cx="-124" cy="-10" rx="44" ry="96" fill="' . $dark . '" transform="rotate(14 -124 -10)"/><ellipse cx="124" cy="-10" rx="44" ry="96" fill="' . $dark . '" transform="rotate(-14 124 -10)"/>'
					. '<ellipse cx="0" cy="56" rx="68" ry="56" fill="' . $light . '"/>'
					. $eyes . $nose
					. '<path d="M-14 84 q14 34 28 0 z" fill="#e58f8a"/>';
		}
	}

	return '<svg xmlns="http://www.w3.org/2000/svg" width="600" height="700" viewBox="0 0 600 700">'
		. '<rect width="600" height="700" fill="' . $bg . '"/>'
		. '<circle cx="300" cy="330" r="250" fill="' . $halo . '"/>'
		. '<g transform="translate(300 380) rotate(' . $tilt . ') scale(' . ( $flip * $zoom ) . ' ' . $zoom . ')">' . $art . '</g>'
		. '</svg>';
}
