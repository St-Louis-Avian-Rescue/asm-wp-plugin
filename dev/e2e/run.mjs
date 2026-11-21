/**
 * Browser checks for asm-wp-plugin, run against the local Docker WordPress
 * started by `dev/bin/dev up`.
 *
 *   cd dev/e2e && npm install && node run.mjs
 *
 * Environment:
 *   CHROMIUM       Path to a Chromium or Chrome binary (default /usr/bin/chromium).
 *   E2E_SHOTS      Folder for screenshots (default dev/e2e/screenshots).
 *   E2E_ONLY       Comma-separated scenario names to run.
 *   ASM_DEV_WP_PORT, ASM_DEV_MOCK_PORT
 *                  The same port overrides you gave dev/bin/dev, if any.
 *
 * Each scenario leaves the site as it found it.
 */
import { chromium } from 'playwright-core';
import assert from 'node:assert/strict';
import { execFileSync } from 'node:child_process';
import { mkdirSync } from 'node:fs';
import { dirname, join } from 'node:path';
import { fileURLToPath } from 'node:url';

const here = dirname( fileURLToPath( import.meta.url ) );
const SITE = process.env.E2E_SITE || `http://localhost:${ process.env.ASM_DEV_WP_PORT || 8888 }`;
const MOCK = process.env.E2E_MOCK || `http://localhost:${ process.env.ASM_DEV_MOCK_PORT || 8889 }`;
const SHOTS = process.env.E2E_SHOTS || join( here, 'screenshots' );
const ONLY = ( process.env.E2E_ONLY || '' ).split( ',' ).filter( Boolean );
const TOTAL = 28;

mkdirSync( SHOTS, { recursive: true } );

const dev = ( ...args ) => execFileSync( join( here, '..', 'bin', 'dev' ), args, { encoding: 'utf8', stdio: [ 'ignore', 'pipe', 'pipe' ] } );
const mock = async ( query ) => ( await fetch( `${ MOCK }/control?${ query }` ) ).json();
const shot = ( page, name, options = {} ) => page.screenshot( { path: join( SHOTS, `${ name }.png` ), ...options } );
// Before a screenshot: no keyboard focus ring, and the pointer out of the way.
const tidy = async ( page ) => {
	await page.mouse.move( 2, 2 );
	await page.evaluate( () => document.activeElement && document.activeElement.blur() );
};
const cards = ( page ) => page.locator( '.asm-wp-plugin-card:visible' );
const modal = ( page ) => page.locator( '.asm-wp-plugin-modal' );

const browser = await chromium.launch( {
	executablePath: process.env.CHROMIUM || '/usr/bin/chromium',
	// The cached-page scenario serves a saved copy of a page, which Chrome would otherwise
	// treat as a public site asking for photos from this computer, and block.
	args: [ '--no-sandbox', '--disable-features=LocalNetworkAccessChecks,BlockInsecurePrivateNetworkRequests' ],
} );

const results = [];
const problems = [];

async function newPage( options = {} ) {
	const context = await browser.newContext( { viewport: { width: 1280, height: 900 }, ...options } );
	const page = await context.newPage();
	page.on( 'pageerror', ( error ) => problems.push( `page error: ${ error.message }` ) );
	page.on( 'console', ( message ) => {
		// A 404 for an animal who has gone is expected in one scenario and reported by the browser itself.
		if ( message.type() === 'error' && ! /status of 404/.test( message.text() ) ) {
			problems.push( `console error: ${ message.text() }` );
		}
	} );
	return page;
}

async function scenario( name, run ) {
	if ( ONLY.length && ! ONLY.includes( name ) ) {
		return;
	}
	const before = problems.length;
	try {
		await run();
		assert.equal( problems.length, before, `Browser errors: ${ problems.slice( before, before + 3 ).join( '; ' ).slice( 0, 600 ) }` );
		results.push( [ name, 'pass' ] );
		console.log( `  ok   ${ name }` );
	} catch ( error ) {
		results.push( [ name, 'FAIL' ] );
		console.log( `  FAIL ${ name }\n       ${ String( error.message ).slice( 0, 900 ).split( '\n' ).join( '\n       ' ) }` );
	}
}

async function openListing( page, path = '/adopt/' ) {
	await page.goto( SITE + path, { waitUntil: 'networkidle' } );
	await page.waitForSelector( '.asm-wp-plugin--js' );
}

// A known starting point.
await mock( 'reset=1' );
dev( 'theme', 'default' );
dev( 'refresh' );

await scenario( 'grid', async () => {
	const page = await newPage();
	await openListing( page );

	assert.equal( await cards( page ).count(), TOTAL );
	assert.equal( await page.locator( '[data-asm-filters]' ).count(), 0, 'no filter bar unless asked for' );
	assert.equal( await modal( page ).isVisible(), false );

	const names = await page.locator( '.asm-wp-plugin-card__name' ).allTextContents();
	assert.deepEqual( names, [ ...names ].sort( ( a, b ) => a.localeCompare( b, undefined, { sensitivity: 'base' } ) ), 'sorted by name' );
	assert.equal( await page.locator( '.asm-wp-plugin-card__details' ).first().textContent(), 'Beagle Dog' );

	// Photos come from the configured ASM service and account, and load lazily.
	const image = page.locator( '.asm-wp-plugin-card__image img' ).first();
	assert.match( await image.getAttribute( 'src' ), new RegExp( `^${ MOCK }/asmservice\\?account=demo&method=animal_image&animalid=\\d+&seq=1$` ) );
	assert.equal( await image.getAttribute( 'loading' ), 'lazy' );

	await page.waitForTimeout( 1500 );
	await shot( page, 'grid' );
	await shot( page, 'grid-cropped', { clip: { x: 0, y: 270, width: 1280, height: 550 } } );
	await page.context().close();
} );

await scenario( 'modal', async () => {
	const page = await newPage();
	await openListing( page );

	await page.locator( '.asm-wp-plugin-card__link', { hasText: 'Archie' } ).click();
	await page.waitForSelector( '.asm-wp-plugin-detail' );
	assert.equal( await modal( page ).isVisible(), true );
	assert.equal( new URL( page.url() ).searchParams.get( 'animal' ), '101' );

	const text = ( await page.locator( '.asm-wp-plugin-detail' ).innerText() ).replace( /\s+/g, ' ' );
	assert.match( text, /Archie Beagle Dog/ );
	assert.match( text, /Adoption fee \(together\): \$300/ );
	assert.match( text, /Bonded with: Bella/ );
	assert.match( text, /Sex: Male/ );
	assert.match( text, /Age: \d+ years/ );
	assert.match( text, /do everything as a pair/ );
	assert.equal( await page.locator( '.asm-wp-plugin-detail__special' ).count(), 0 );

	// Carousel: six photos, one shown, arrows and arrow keys move through them and wrap.
	const slides = page.locator( '[data-asm-slide]' );
	const active = () => slides.evaluateAll( ( all ) => all.findIndex( ( slide ) => slide.classList.contains( 'is-active' ) ) );
	assert.equal( await slides.count(), 6 );
	assert.equal( await active(), 0 );
	await page.locator( '[data-asm-step="1"]' ).click();
	assert.equal( await active(), 1 );
	await page.keyboard.press( 'ArrowRight' );
	assert.equal( await active(), 2 );
	await page.locator( '[data-asm-step="-1"]' ).click();
	await page.keyboard.press( 'ArrowLeft' );
	await page.keyboard.press( 'ArrowLeft' );
	assert.equal( await active(), 5, 'wraps around' );
	assert.equal( await page.locator( '[data-asm-slide]:visible' ).count(), 1 );

	await page.keyboard.press( 'ArrowRight' );
	await tidy( page );
	await page.setViewportSize( { width: 1280, height: 1080 } );
	await page.waitForTimeout( 1200 );
	await shot( page, 'modal' );
	await page.setViewportSize( { width: 1280, height: 900 } );

	// The bonded companion opens in the same dialog; Back returns to the first animal.
	await page.locator( '.asm-wp-plugin-detail__companion' ).click();
	await page.waitForFunction( () => document.querySelector( '.asm-wp-plugin-detail__name' )?.textContent === 'Bella' );
	assert.equal( new URL( page.url() ).searchParams.get( 'animal' ), '102' );
	assert.match( await page.locator( '.asm-wp-plugin-detail' ).innerText(), /Bonded with: Archie/ );

	await page.goBack();
	await page.waitForFunction( () => document.querySelector( '.asm-wp-plugin-detail__name' )?.textContent === 'Archie' );

	// Escape closes and cleans the address; Back reopens; the X and the backdrop close too.
	await page.keyboard.press( 'Escape' );
	assert.equal( await modal( page ).isVisible(), false );
	assert.equal( new URL( page.url() ).searchParams.has( 'animal' ), false );
	await page.goBack();
	await page.waitForSelector( '.asm-wp-plugin-modal:visible' );
	await page.locator( '[data-asm-close]' ).click();
	assert.equal( await modal( page ).isVisible(), false );
	await page.locator( '.asm-wp-plugin-card__link' ).nth( 3 ).click();
	await page.waitForSelector( '.asm-wp-plugin-detail' );
	await modal( page ).click( { position: { x: 5, y: 5 } } );
	assert.equal( await modal( page ).isVisible(), false );

	// Pick out particular animals from the public REST route.
	await page.goto( `${ SITE }/adopt/` );
	const pick = await page.evaluate( async () => {
		const { animals } = await ( await fetch( '/wp-json/asm-wp-plugin/v1/animals' ) ).json();
		return {
			single: animals.find( ( animal ) => animal.image_count === 1 ).id,
			special: animals.find( ( animal ) => animal.special_needs ).id,
			estimated: animals.find( ( animal ) => animal.dob_estimated && animal.dob ).id,
			alone: animals.find( ( animal ) => ! animal.bonded.length && animal.fee ),
		};
	} );
	const openById = async ( id ) => {
		await page.goto( `${ SITE }/adopt/` );
		await page.locator( `[data-asm-animal="${ id }"]` ).click();
		await page.waitForSelector( '.asm-wp-plugin-detail' );
		return ( await page.locator( '.asm-wp-plugin-detail' ).innerText() ).replace( /\s+/g, ' ' );
	};

	// An animal with one photo gets no arrows.
	await openById( pick.single );
	assert.equal( await page.locator( '[data-asm-step]' ).count(), 0 );

	assert.match( await openById( pick.special ), /Has special needs/ );
	assert.match( await openById( pick.estimated ), /Age \(estimated\): / );

	// An animal on their own shows just their fee, and no companion line.
	const alone = await openById( pick.alone.id );
	assert.ok( alone.includes( 'Adoption fee: $' + pick.alone.fee / 100 ), alone );
	assert.doesNotMatch( alone, /Bonded with/ );
	await page.context().close();
} );

await scenario( 'keyboard', async () => {
	const page = await newPage();
	await openListing( page );

	await page.locator( '.asm-wp-plugin-card__link' ).first().focus();
	await page.keyboard.press( 'Enter' );
	await page.waitForSelector( '.asm-wp-plugin-detail' );
	assert.equal( await page.evaluate( () => document.activeElement.hasAttribute( 'data-asm-close' ) ), true, 'focus moves into the dialog' );

	// Tab stays inside the dialog.
	for ( let i = 0; i < 12; i++ ) {
		await page.keyboard.press( 'Tab' );
		assert.equal( await page.evaluate( () => !! document.activeElement.closest( '.asm-wp-plugin-modal' ) ), true );
	}

	await page.keyboard.press( 'Escape' );
	assert.equal( await page.evaluate( () => document.activeElement.getAttribute( 'data-asm-animal' ) ), '101', 'focus returns to the card' );
	await page.context().close();
} );

await scenario( 'share', async () => {
	const page = await newPage( { permissions: [ 'clipboard-read', 'clipboard-write' ] } );
	await openListing( page );
	await page.locator( '[data-asm-animal="101"]' ).click();
	await page.waitForSelector( '.asm-wp-plugin-share' );

	const link = `${ SITE }/adopt/?animal=101`;
	const hrefs = await page.locator( '.asm-wp-plugin-share__link' ).evaluateAll( ( all ) => all.map( ( a ) => a.getAttribute( 'href' ) ) );
	assert.equal( hrefs.length, 4 );
	assert.equal( hrefs[ 0 ], `https://www.facebook.com/sharer/sharer.php?u=${ encodeURIComponent( link ) }` );
	assert.equal( hrefs[ 1 ], `https://twitter.com/intent/tweet?url=${ encodeURIComponent( link ) }&text=${ encodeURIComponent( 'Archie is looking for a new family' ) }` );
	assert.equal( hrefs[ 2 ], `mailto:?subject=${ encodeURIComponent( 'Adopt - Archie' ) }&body=${ encodeURIComponent( 'Archie is looking for a new family\n' + link ) }` );
	assert.equal( hrefs[ 3 ], link );

	await page.locator( '[data-asm-copy]' ).click();
	await page.waitForFunction( () => document.querySelector( '[data-asm-copied]' ).textContent === 'Copied!' );
	assert.equal( await page.evaluate( () => navigator.clipboard.readText() ), link );
	assert.equal( await modal( page ).isVisible(), true, 'copying does not navigate away' );

	assert.equal(
		await page.locator( '.asm-wp-plugin-detail__apply a' ).getAttribute( 'href' ),
		`${ SITE }/adoption-application/?animal=A2026101`
	);

	await page.locator( '.asm-wp-plugin-share' ).scrollIntoViewIfNeeded();
	await page.waitForTimeout( 300 );
	await shot( page, 'share' );
	await page.context().close();
} );

await scenario( 'deep-links', async () => {
	const page = await newPage();

	await page.goto( `${ SITE }/adopt/?animal=101`, { waitUntil: 'networkidle' } );
	assert.equal( await modal( page ).isVisible(), true, '?animal= opens the dialog' );
	assert.equal( await page.locator( '.asm-wp-plugin-detail__name' ).textContent(), 'Archie' );

	// The carousel works on a dialog the server rendered, and closing leaves a clean address.
	await page.locator( '[data-asm-step="1"]' ).click();
	assert.equal( await page.locator( '[data-asm-slide].is-active img' ).getAttribute( 'alt' ), 'Archie, photo 2 of 6' );
	await page.keyboard.press( 'Escape' );
	assert.equal( new URL( page.url() ).search, '' );

	// A link to an animal who is not listed just shows the listing.
	await page.goto( `${ SITE }/adopt/?animal=99999`, { waitUntil: 'networkidle' } );
	assert.equal( await cards( page ).count(), TOTAL );
	await page.waitForFunction( () => /no longer listed/.test( document.querySelector( '.asm-wp-plugin-modal' ).textContent ) );
	await page.context().close();
} );

await scenario( 'no-javascript', async () => {
	const page = await newPage( { javaScriptEnabled: false } );

	await page.goto( `${ SITE }/adopt-filters/` );
	assert.equal( await cards( page ).count(), TOTAL, 'the grid is in the HTML' );
	assert.equal( await page.locator( '[data-asm-filters]' ).isVisible(), false, 'the filter bar needs the script, so it stays hidden' );

	// Cards are ordinary links, and the server renders the details.
	await page.goto( `${ SITE }/adopt/` );
	await page.locator( '[data-asm-animal="101"]' ).click();
	await page.waitForURL( /animal=101/ );
	assert.equal( await page.locator( '.asm-wp-plugin-detail__name' ).textContent(), 'Archie' );
	assert.equal( await modal( page ).isVisible(), true );
	await page.locator( '[data-asm-close]' ).click();
	await page.waitForURL( `${ SITE }/adopt/` );
	assert.equal( await modal( page ).isVisible(), false );
	await page.context().close();
} );

await scenario( 'filters', async () => {
	const page = await newPage();
	await openListing( page, '/adopt-filters/' );

	const count = () => page.locator( '[data-asm-count]' ).textContent();
	const param = ( name ) => new URL( page.url() ).searchParams.get( name );
	const species = page.locator( '[data-asm-filter="species"]' );
	const search = page.locator( '[data-asm-filter="q"]' );
	const sort = page.locator( '[data-asm-filter="sort"]' );

	assert.equal( await page.locator( '[data-asm-filters]' ).isVisible(), true );
	assert.equal( await count(), `${ TOTAL } animals` );
	assert.equal( await page.locator( '[data-asm-clear]' ).isVisible(), false );
	for ( const control of [ 'q', 'species', 'sex', 'size', 'special', 'sort' ] ) {
		assert.equal( await page.locator( `[data-asm-filter="${ control }"]` ).count(), 1, `${ control } control` );
	}
	await page.waitForTimeout( 1200 );
	await shot( page, 'filters' );

	// Species.
	await species.selectOption( 'Rabbit' );
	assert.equal( await count(), '4 animals' );
	assert.deepEqual( [ ...new Set( await cards( page ).evaluateAll( ( all ) => all.map( ( card ) => card.dataset.species ) ) ) ], [ 'Rabbit' ] );
	assert.equal( param( 'asm_species' ), 'Rabbit' );
	assert.equal( await page.locator( '[data-asm-clear]' ).isVisible(), true );

	// Search narrows within it, on name or breed, case-insensitively.
	await search.fill( 'LOP' );
	await page.waitForFunction( () => document.querySelector( '[data-asm-count]' ).textContent !== '4 animals' );
	const lops = await cards( page ).evaluateAll( ( all ) => all.map( ( card ) => card.dataset.search ) );
	assert.ok( lops.length > 0 && lops.every( ( text ) => text.includes( 'lop' ) ), JSON.stringify( lops ) );
	assert.equal( param( 'asm_q' ), 'LOP' );
	await shot( page, 'filters-applied' );
	await search.fill( '' );
	await page.waitForFunction( () => document.querySelector( '[data-asm-count]' ).textContent === '4 animals' );
	await tidy( page );
	await page.waitForTimeout( 800 );
	await shot( page, 'filters-species', { clip: { x: 0, y: 270, width: 1280, height: 400 } } );
	await search.fill( 'LOP' );
	await page.waitForFunction( () => document.querySelector( '[data-asm-count]' ).textContent !== '4 animals' );

	// The filtered view survives a reload, so it can be shared.
	await page.reload( { waitUntil: 'networkidle' } );
	assert.equal( await species.inputValue(), 'Rabbit' );
	assert.equal( await search.inputValue(), 'LOP' );
	assert.equal( await cards( page ).count(), lops.length );

	// Opening an animal keeps the filters in the address.
	await cards( page ).first().locator( 'a' ).click();
	await page.waitForSelector( '.asm-wp-plugin-detail' );
	assert.ok( param( 'animal' ) && param( 'asm_species' ) === 'Rabbit' );
	await page.keyboard.press( 'Escape' );
	assert.equal( param( 'asm_species' ), 'Rabbit' );

	// Nothing matches.
	await search.fill( 'zzzz' );
	await page.waitForSelector( '[data-asm-no-matches]:visible' );
	assert.equal( await count(), '0 animals' );

	// Clear puts everything back.
	await page.locator( '[data-asm-clear]' ).click();
	assert.equal( await count(), `${ TOTAL } animals` );
	assert.equal( new URL( page.url() ).search, '' );
	assert.equal( await page.locator( '[data-asm-no-matches]' ).isVisible(), false );

	// Sex, size and special needs.
	await page.locator( '[data-asm-filter="sex"]' ).selectOption( 'Female' );
	assert.equal( await count(), '16 animals' );
	await page.locator( '[data-asm-filter="size"]' ).selectOption( 'Small' );
	assert.equal( await count(), '7 animals' );
	await search.fill( 'clover' );
	await page.waitForFunction( () => document.querySelector( '[data-asm-count]' ).textContent === '1 animal' );
	await page.locator( '[data-asm-clear]' ).click();
	await page.locator( '[data-asm-filter="special"]' ).check();
	assert.equal( await count(), '2 animals' );
	assert.equal( param( 'asm_special' ), '1' );
	await page.locator( '[data-asm-clear]' ).click();

	// Sorting.
	const order = ( attribute ) => cards( page ).evaluateAll( ( all, name ) => all.map( ( card ) => card.getAttribute( name ) ), attribute );
	await sort.selectOption( 'newest' );
	const arrivals = await order( 'data-date-in' );
	assert.deepEqual( arrivals, [ ...arrivals ].sort().reverse(), 'newest arrivals first' );
	assert.equal( param( 'asm_sort' ), 'newest' );
	await sort.selectOption( 'youngest' );
	const births = ( await order( 'data-dob' ) ).filter( Boolean );
	assert.deepEqual( births, [ ...births ].sort().reverse(), 'youngest first' );
	await sort.selectOption( 'oldest' );
	const oldest = ( await order( 'data-dob' ) ).filter( Boolean );
	assert.deepEqual( oldest, [ ...oldest ].sort(), 'oldest first' );
	await sort.selectOption( 'name' );
	assert.equal( param( 'asm_sort' ), null, 'the default order is not written to the address' );
	assert.equal( await page.locator( '.asm-wp-plugin-card__name' ).first().textContent(), 'Archie' );

	// Only some controls: [asm_adoptable filters="search,sort"] via the REST grid route.
	const partial = await page.evaluate( async () => ( await ( await fetch( '/wp-json/asm-wp-plugin/v1/grid?filters=search,sort' ) ).json() ).filters );
	assert.ok( partial.includes( 'data-asm-filter="q"' ) && partial.includes( 'data-asm-filter="sort"' ) && ! partial.includes( 'data-asm-filter="species"' ) );
	await page.context().close();
} );

await scenario( 'block', async () => {
	const page = await newPage();
	await openListing( page, '/adopt-block/' );

	// The demo block asks for dogs and cats, newest first, four columns, with the bar.
	assert.equal( await cards( page ).count(), 17 );
	assert.deepEqual( [ ...new Set( await cards( page ).evaluateAll( ( all ) => all.map( ( card ) => card.dataset.species ) ) ) ].sort(), [ 'Cat', 'Dog' ] );
	assert.equal( await page.locator( '[data-asm-filter="sort"]' ).inputValue(), 'newest' );
	assert.equal( await page.locator( '.asm-wp-plugin-grid' ).evaluate( ( grid ) => getComputedStyle( grid ).gridTemplateColumns.split( ' ' ).length ), 4 );
	assert.deepEqual( await page.locator( '[data-asm-filter="species"] option' ).allTextContents(), [ 'All species', 'Cat', 'Dog' ] );
	await page.context().close();
} );

await scenario( 'page-cache-refresh', async () => {
	const page = await newPage();

	// Keep a copy of the page as a full-page cache would, then let an animal be adopted.
	const cached = await ( await fetch( `${ SITE }/adopt/` ) ).text();
	assert.ok( cached.includes( 'data-asm-animal="101"' ) );
	await mock( 'hide=101' );
	dev( 'expire' );

	try {
		await page.route( `${ SITE }/adopt/`, ( route ) => route.fulfill( { status: 200, contentType: 'text/html', body: cached } ) );

		// With the freshness check held back, the stale page still lists Archie; opening her explains.
		const oldHash = cached.match( /data-hash="([0-9a-f]+)"/ )[ 1 ];
		await page.route( '**/asm-wp-plugin/v1/status*', ( route ) => route.fulfill( { status: 200, contentType: 'application/json', body: JSON.stringify( { hash: oldHash } ) } ) );
		await page.goto( `${ SITE }/adopt/`, { waitUntil: 'domcontentloaded' } );
		await page.waitForSelector( '.asm-wp-plugin--js' );
		assert.equal( await cards( page ).count(), TOTAL );
		await page.unroute( '**/asm-wp-plugin/v1/status*' );
		await page.locator( '[data-asm-animal="101"]' ).click();
		await page.waitForFunction( () => /no longer listed/.test( document.querySelector( '.asm-wp-plugin-modal' ).textContent ) );

		// That also triggers a check, which replaces the listing without a reload.
		await page.waitForFunction( ( total ) => document.querySelectorAll( '.asm-wp-plugin-card' ).length === total - 1, TOTAL );
		assert.equal( await page.locator( '[data-asm-animal="101"]' ).count(), 0 );
		await page.keyboard.press( 'Escape' );

		// The normal case: the same stale HTML, corrected right after load.
		await page.goto( `${ SITE }/adopt/`, { waitUntil: 'networkidle' } );
		await page.waitForFunction( ( total ) => document.querySelectorAll( '.asm-wp-plugin-card' ).length === total - 1, TOTAL );
		assert.notEqual( await page.locator( '.asm-wp-plugin' ).getAttribute( 'data-hash' ), oldHash );
	} finally {
		await mock( 'reset=1' );
		dev( 'refresh' );
		await page.context().close();
	}
} );

await scenario( 'asm-down', async () => {
	const page = await newPage();

	try {
		// ASM goes away after a good fetch: visitors keep seeing the last good copy.
		await mock( 'down=1' );
		dev( 'expire' );
		await openListing( page );
		assert.equal( await cards( page ).count(), TOTAL );
		const status = await page.evaluate( async () => ( await fetch( '/wp-json/asm-wp-plugin/v1/status' ) ).json() );
		assert.equal( status.stale, true );
		assert.equal( status.count, TOTAL );
		await page.locator( '[data-asm-animal="101"]' ).click();
		await page.waitForSelector( '.asm-wp-plugin-detail' );
		await page.waitForTimeout( 800 );
		await shot( page, 'asm-down-last-good-copy' );

		// The settings page says so.
		await login( page );
		await page.goto( `${ SITE }/wp-admin/options-general.php?page=asm-wp-plugin` );
		const statusText = ( await page.locator( 'table.widefat' ).innerText() ).replace( /\s+/g, ' ' );
		assert.match( statusText, /Latest problem .*ASM answered with HTTP status 500.*Visitors are seeing the last good copy/ );
		await shot( page, 'asm-down-settings' );

		// With no copy at all, visitors get a plain message and administrators the reason.
		dev( 'wp', 'eval', 'ASM_WP_Plugin_Client::clear( true );' );
		const anonymous = await newPage();
		await anonymous.goto( `${ SITE }/adopt/`, { waitUntil: 'networkidle' } );
		const visitor = await anonymous.locator( '.asm-wp-plugin' ).innerText();
		assert.match( visitor, /not available right now/ );
		assert.doesNotMatch( visitor, /HTTP status/ );
		await shot( anonymous, 'asm-down-no-copy' );
		await anonymous.context().close();

		dev( 'expire' );
		await page.goto( `${ SITE }/adopt/`, { waitUntil: 'networkidle' } );
		assert.match( await page.locator( '.asm-wp-plugin-admin-note' ).innerText(), /Only administrators see this: ASM answered with HTTP status 500/ );

	} finally {
		await mock( 'reset=1' );
		dev( 'refresh' );
		await page.context().close();
	}
} );

await scenario( 'second-theme', async () => {
	const page = await newPage();

	try {
		// Default theme: the dialog uses the theme's base and contrast colours.
		await page.goto( `${ SITE }/adopt-filters/?animal=101`, { waitUntil: 'networkidle' } );
		const light = await page.locator( '.asm-wp-plugin-modal__content' ).evaluate( ( el ) => getComputedStyle( el ).backgroundColor );
		assert.equal( light, 'rgb(255, 255, 255)' );

		dev( 'theme', 'demo' );
		await openListing( page, '/adopt-filters/' );

		// Fonts and colours are inherited from the theme without any plugin CSS changes.
		const card = page.locator( '.asm-wp-plugin-card' ).first();
		assert.match( await card.evaluate( ( el ) => getComputedStyle( el ).fontFamily ), /Georgia/ );
		assert.equal( await page.locator( '.asm-wp-plugin-card__name' ).first().evaluate( ( el ) => getComputedStyle( el ).color ), 'rgb(244, 239, 230)' );

		// The theme's asm-wp-plugin/card.php is used, including by the REST refresh.
		assert.equal( await page.locator( '.asm-wp-plugin-card .asm-wp-plugin-demo-tags' ).count(), TOTAL );
		const viaRest = await page.evaluate( async () => ( await ( await fetch( '/wp-json/asm-wp-plugin/v1/grid' ) ).json() ).list );
		assert.ok( viaRest.includes( 'asm-wp-plugin-demo-tags' ) );

		// Custom properties set by the theme take effect.
		assert.equal( await page.locator( '.asm-wp-plugin-card__image' ).first().evaluate( ( el ) => getComputedStyle( el ).aspectRatio ), '1 / 1' );
		await page.waitForTimeout( 1500 );
		await shot( page, 'theme-demo-grid' );
		await shot( page, 'theme-demo-cropped', { clip: { x: 0, y: 270, width: 1280, height: 460 } } );

		await page.locator( '[data-asm-animal="101"]' ).click();
		await page.waitForSelector( '.asm-wp-plugin-detail' );
		const content = page.locator( '.asm-wp-plugin-modal__content' );
		assert.equal( await content.evaluate( ( el ) => getComputedStyle( el ).backgroundColor ), 'rgb(27, 26, 23)', 'dialog follows the theme base colour' );
		assert.equal( await page.locator( '.asm-wp-plugin-detail__apply' ).evaluate( ( el ) => getComputedStyle( el ).backgroundColor ), 'rgb(242, 179, 61)', 'accent custom property' );
		await tidy( page );
		await page.waitForTimeout( 1200 );
		await shot( page, 'theme-demo-modal' );
	} finally {
		dev( 'theme', 'default' );
		await page.context().close();
	}
} );

await scenario( 'mobile', async () => {
	const page = await newPage( { viewport: { width: 390, height: 844 }, isMobile: true, hasTouch: true } );
	await openListing( page, '/adopt-filters/' );

	assert.equal( await page.locator( '.asm-wp-plugin-grid' ).evaluate( ( grid ) => getComputedStyle( grid ).gridTemplateColumns.split( ' ' ).length ), 2 );
	assert.equal( await page.evaluate( () => document.documentElement.scrollWidth <= window.innerWidth ), true, 'no sideways scrolling' );
	await page.waitForTimeout( 1200 );
	await shot( page, 'mobile-grid' );

	await page.locator( '[data-asm-animal="101"]' ).tap();
	await page.waitForSelector( '.asm-wp-plugin-detail' );
	const box = await page.locator( '.asm-wp-plugin-modal__content' ).boundingBox();
	assert.ok( box.x >= 0 && box.x + box.width <= 390 );
	await page.waitForTimeout( 1200 );
	await shot( page, 'mobile-modal' );
	await page.context().close();
} );

async function login( page ) {
	await page.goto( `${ SITE }/wp-login.php` );
	await page.fill( '#user_login', 'admin' );
	await page.fill( '#user_pass', 'admin' );
	await page.click( '#wp-submit' );
	await page.waitForURL( /wp-admin/ );
}

await scenario( 'settings', async () => {
	const page = await newPage();
	await login( page );
	const url = `${ SITE }/wp-admin/options-general.php?page=asm-wp-plugin`;
	await page.goto( url );

	// The saved password is never sent to the browser.
	const password = page.locator( '#asm-wp-plugin-password' );
	assert.equal( await password.getAttribute( 'type' ), 'password' );
	assert.equal( await password.inputValue(), '' );
	assert.match( await page.locator( 'body' ).innerText(), /A password is saved\. Leave this empty to keep it\./ );
	assert.equal( ( await page.content() ).match( /value="demo"/g ).length, 2, 'only the account and username fields hold "demo"' );
	await shot( page, 'settings', { fullPage: true } );

	await page.getByRole( 'link', { name: 'Test connection' } ).click();
	await page.waitForURL( url );
	assert.match( await page.locator( '.notice-success' ).innerText(), /Connected\. ASM returned 28 adoptable animals\./ );

	await page.getByRole( 'link', { name: 'Clear cache' } ).click();
	await page.waitForURL( url );
	assert.match( await page.locator( '.notice-success' ).innerText(), /Cache cleared\. 28 animals fetched from ASM\./ );

	// Saving without touching the password keeps it; a wrong one is reported by Test connection.
	const filters = page.locator( 'input[name="asm_wp_plugin_settings[filters]"]' );
	await filters.check();
	await page.locator( 'input[name="asm_wp_plugin_settings[detail_fields][]"][value="fee"]' ).uncheck();
	await page.locator( '#submit' ).click();
	await page.waitForURL( /settings-updated=true/ );
	assert.equal( await filters.isChecked(), true );

	try {
		const front = await newPage();
		await front.goto( `${ SITE }/adopt/?animal=101`, { waitUntil: 'networkidle' } );
		assert.equal( await front.locator( '[data-asm-filters]' ).isVisible(), true, 'the setting switches the bar on site-wide' );
		assert.doesNotMatch( await front.locator( '.asm-wp-plugin-detail__facts' ).innerText(), /Adoption fee/, 'the fee can be hidden' );
		await front.context().close();

		await page.goto( url );
		await page.getByRole( 'link', { name: 'Test connection' } ).click();
		await page.waitForURL( url );
		assert.match( await page.locator( '.notice-success' ).innerText(), /Connected/, 'the password survived the save' );

		await page.fill( '#asm-wp-plugin-password', 'wrong' );
		await page.locator( '#submit' ).click();
		await page.waitForURL( /settings-updated=true/ );
		await page.getByRole( 'link', { name: 'Test connection' } ).click();
		await page.waitForURL( url );
		assert.match( await page.locator( '.notice-error' ).innerText(), /Could not get animals from ASM: ASM answered with HTTP status 403/ );
	} finally {
		await page.goto( url );
		await page.fill( '#asm-wp-plugin-password', 'demo' );
		await page.locator( 'input[name="asm_wp_plugin_settings[filters]"]' ).uncheck();
		await page.locator( 'input[name="asm_wp_plugin_settings[detail_fields][]"][value="fee"]' ).check();
		await page.locator( '#submit' ).click();
		await page.waitForURL( /settings-updated=true/ );
		dev( 'refresh' );
		await page.context().close();
	}
} );

await scenario( 'block-editor', async () => {
	const page = await newPage();
	await login( page );
	const id = dev( 'wp', 'post', 'list', '--post_type=page', '--name=adopt-block', '--field=ID' ).trim().split( /\s+/ ).pop();
	await page.goto( `${ SITE }/wp-admin/post.php?post=${ id }&action=edit` );

	// The block previews the listing in the editor, and its settings are in the sidebar.
	const canvas = page.frameLocator( 'iframe[name="editor-canvas"]' );
	await canvas.locator( '.asm-wp-plugin-card' ).first().waitFor( { timeout: 30000 } );
	assert.equal( await canvas.locator( '.asm-wp-plugin-card' ).count(), 17 );
	assert.equal( await canvas.locator( '.wp-block-missing, .block-editor-warning' ).count(), 0 );

	// Dismiss the editor's first-run welcome guide.
	await page.evaluate( () => window.wp.data.dispatch( 'core/preferences' ).set( 'core/edit-post', 'welcomeGuide', false ) );
	await page.locator( '.components-modal__screen-overlay' ).waitFor( { state: 'detached', timeout: 5000 } ).catch( () => {} );
	await canvas.locator( '[data-type="asm-wp-plugin/adoptable"]' ).click( { position: { x: 10, y: 10 } } );
	await page.getByRole( 'button', { name: 'Settings', exact: true } ).first().click().catch( () => {} );
	await page.waitForTimeout( 800 );
	await shot( page, 'block-editor' );
	await page.context().close();
} );

await browser.close();

const failed = results.filter( ( [ , outcome ] ) => outcome !== 'pass' );
console.log( `\n${ results.length - failed.length } of ${ results.length } scenarios passed. Screenshots: ${ SHOTS }` );
process.exit( failed.length ? 1 : 0 );
