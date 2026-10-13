/**
 * Interactive blocks suite: the tabs, accordion, image lightbox and playlist
 * blocks on a page of the test site, driven in a real browser on the engine
 * and on the reference, step by step, each step's state compared (which tab
 * and panel show, where the focus is, which items are open, the open
 * lightbox's role, labels, focus and inert page, the playlist's current
 * track and player). The reference is read at its own host
 * (wp.<site>.localhost): through ref.<site>.localhost its module addresses
 * name the engine's host, so its view modules never load there. The suite
 * makes its page and its two tones (tests/fixtures/audio) and removes them.
 *
 *   node tests/browser/interactive-blocks.test.js
 */
const { execSync } = require( 'child_process' );
const path = require( 'path' );
const { chromium } = require( 'playwright-core' );
const { siteDir } = require( './local' );

const CHROME = '/Applications/Google Chrome.app/Contents/MacOS/Google Chrome';
const SITE = 'minn';
const stacks = { engine: `https://${ SITE }.localhost`, reference: `https://wp.${ SITE }.localhost` };
const WP = `wp --path=${ siteDir( SITE ) }/wp-reference`;
const SLUG = 'zz-interactive-blocks-suite';

let pass = 0;
let fail = 0;
function check( cond, label, detail = '' ) {
	if ( cond ) { pass++; console.log( `  ok  ${ label }` ); } else { fail++; console.log( `FAIL  ${ label }${ detail ? `\n      ${ detail }` : '' }` ); }
}
const wp = ( command ) => execSync( `${ WP } ${ command } 2>/dev/null`, { encoding: 'utf8' } ).trim();

// The page: three tabs, an accordion that opens one item at a time (its last open to begin with), one that does not,
// an image with the lightbox, and a playlist of the two tones.
function content( tones ) {
	const panel = ( label, text ) => `<!-- wp:tab-panel {"label":"${ label }"} --><div class="wp-block-tab-panel"><!-- wp:paragraph --><p>${ text }</p><!-- /wp:paragraph --></div><!-- /wp:tab-panel -->`;
	const item = ( title, text, attrs = '' ) => `<!-- wp:accordion-item${ attrs ? ' ' + attrs : '' } --><div class="wp-block-accordion-item"><!-- wp:accordion-heading {"title":"${ title }"} --><h3 class="wp-block-accordion-heading"><button class="wp-block-accordion-heading__toggle"><span class="wp-block-accordion-heading__toggle-title">${ title }</span></button></h3><!-- /wp:accordion-heading --><!-- wp:accordion-panel --><div class="wp-block-accordion-panel"><!-- wp:paragraph --><p>${ text }</p><!-- /wp:paragraph --></div><!-- /wp:accordion-panel --></div><!-- /wp:accordion-item -->`;
	const track = ( tone, title, extra ) => `<!-- wp:playlist-track ${ JSON.stringify( { id: tone.id, src: tone.url, title, ...extra } ) } /-->`;
	return [
		'<!-- wp:tabs --><div class="wp-block-tabs"><!-- wp:tab-list --><div class="wp-block-tab-list"><button>One</button><button>Two</button><button>Three</button></div><!-- /wp:tab-list --><!-- wp:tab-panels --><div class="wp-block-tab-panels">' + panel( 'One', 'First panel' ) + panel( 'Two', 'Second panel' ) + panel( 'Three', 'Third panel' ) + '</div><!-- /wp:tab-panels --></div><!-- /wp:tabs -->',
		'<!-- wp:accordion {"autoclose":true} --><div class="wp-block-accordion">' + item( 'First question', 'First answer' ) + item( 'Second question', 'Second answer' ) + item( 'Third question', 'Third answer', '{"openByDefault":true}' ) + '</div><!-- /wp:accordion -->',
		'<!-- wp:accordion --><div class="wp-block-accordion">' + item( 'Open question', 'Open answer' ) + item( 'Other question', 'Other answer' ) + '</div><!-- /wp:accordion -->',
		`<!-- wp:image {"id":${ tones.image.id },"sizeSlug":"full","linkDestination":"none","lightbox":{"enabled":true}} --><figure class="wp-block-image size-full"><img src="${ tones.image.url }" alt="Battery" class="wp-image-${ tones.image.id }"/></figure><!-- /wp:image -->`,
		'<!-- wp:playlist --><figure class="wp-block-playlist"><ol class="wp-block-playlist__tracklist">' + track( tones.a, 'ZZ Tone A', { artist: 'ZZ Band', album: 'ZZ Album', length: '0:04' } ) + track( tones.b, 'ZZ Tone B', { length: '0:03' } ) + '</ol></figure><!-- /wp:playlist -->',
	].join( '\n\n' );
}

// What a person sees of the blocks, in one snapshot.
function snapshot() {
	const tabs = [ ...document.querySelectorAll( '.wp-block-tabs' ) ].map( ( root ) => ( {
		buttons: [ ...root.querySelectorAll( '.wp-block-tab-list button' ) ].map( ( b ) => [ b.getAttribute( 'aria-selected' ), b.getAttribute( 'tabindex' ), b === document.activeElement ] ),
		panels: [ ...root.querySelectorAll( '.wp-block-tab-panel' ) ].map( ( p ) => p.hasAttribute( 'hidden' ) ),
	} ) );
	const accordions = [ ...document.querySelectorAll( '.wp-block-accordion' ) ].map( ( root ) => [ ...root.querySelectorAll( '.wp-block-accordion-item' ) ].map( ( item ) => {
		const toggle = item.querySelector( '.wp-block-accordion-heading__toggle' );
		const panel = item.querySelector( '.wp-block-accordion-panel' );
		return [ item.classList.contains( 'is-open' ), toggle.getAttribute( 'aria-expanded' ), panel.getAttribute( 'hidden' ), toggle === document.activeElement ];
	} ) );
	const o = document.querySelector( '.wp-lightbox-overlay' );
	const lightbox = o && { cls: o.className, role: o.getAttribute( 'role' ), modal: o.getAttribute( 'aria-modal' ), label: o.getAttribute( 'aria-label' ), focus: document.activeElement === o ? 'overlay' : ( document.activeElement && document.activeElement.className ) || '', inert: document.querySelectorAll( 'body > [inert]' ).length > 0, alts: [ ...o.querySelectorAll( 'img' ) ].map( ( i ) => i.getAttribute( 'alt' ) ), close: o.querySelector( '.wp-lightbox-close-button' ).getAttribute( 'aria-label' ), trigger: ( document.querySelector( 'button.lightbox-trigger' ) || {} ).ariaLabel || null };
	const btn = document.querySelector( '.waveform-btn' );
	const slider = document.querySelector( '.waveform-container' );
	const playlist = { current: [ ...document.querySelectorAll( '.wp-block-playlist-track__button' ) ].map( ( b ) => b.getAttribute( 'aria-current' ) ), button: btn && [ btn.getAttribute( 'aria-label' ), btn.classList.contains( 'playing' ) ], slider: slider && [ slider.getAttribute( 'role' ), slider.getAttribute( 'aria-label' ), slider.getAttribute( 'aria-valuemax' ) ] };
	return JSON.stringify( { tabs, accordions, lightbox, playlist, hash: location.hash } ).replace( /tabs_\d+/g, 'tabs_N' ).replace( /accordion-item-\d+/g, 'accordion-item-N' );
}

( async () => {
	let reachable = true;
	for ( const base of Object.values( stacks ) ) {
		try {
			reachable = reachable && ( await fetch( base + '/', { signal: AbortSignal.timeout( 5000 ) } ) ).ok;
		} catch ( e ) {
			reachable = false;
		}
	}
	if ( ! reachable ) {
		console.log( `interactive blocks suite: ${ Object.values( stacks ).join( ' or ' ) } not answering; skipping` );
		process.exit( 0 );
	}
	console.log( `interactive blocks suite: ${ stacks.engine } (engine) / ${ stacks.reference } (reference)` );
	const made = [];
	let browser;
	try {
		const fixtures = path.join( __dirname, '..', 'fixtures', 'audio' );
		const tone = ( file, title ) => {
			const id = parseInt( wp( `media import ${ path.join( fixtures, file ) } --title="${ title }" --porcelain` ), 10 );
			if ( id > 1 ) made.push( [ 'post', id ] );
			return { id, url: wp( `post get ${ id } --field=guid` ) };
		};
		const tones = { a: tone( 'zz-tone-a.mp3', 'ZZ Suite Tone A' ), b: tone( 'zz-tone-b.mp3', 'ZZ Suite Tone B' ) };
		const imageId = parseInt( wp( "post list --post_type=attachment --post_mime_type=image --format=ids --posts_per_page=1 --orderby=ID --order=ASC" ), 10 );
		tones.image = { id: imageId, url: wp( `post get ${ imageId } --field=guid` ) };
		const file = path.join( require( 'os' ).tmpdir(), `minn-interactive-${ process.pid }.html` );
		require( 'fs' ).writeFileSync( file, content( tones ) );
		const page = parseInt( wp( `post create ${ file } --post_type=page --post_status=publish --post_title="ZZ Interactive Blocks Suite" --post_name=${ SLUG } --porcelain` ), 10 );
		require( 'fs' ).unlinkSync( file );
		if ( page > 1 ) made.push( [ 'post', page ] );
		check( tones.a.id > 1 && tones.b.id > 1 && imageId > 1 && page > 1, 'the page, its tones and its image are in place' );

		browser = await chromium.launch( { executablePath: CHROME, args: [ '--ignore-certificate-errors', '--disable-http2', '--autoplay-policy=no-user-gesture-required', '--disable-features=MacAppCodeSignClone' ] } );
		const runs = {};
		for ( const [ stack, base ] of Object.entries( stacks ) ) {
			const context = await browser.newContext( { ignoreHTTPSErrors: true, viewport: { width: 1200, height: 900 } } );
			const tab = await context.newPage();
			const errors = [];
			tab.on( 'pageerror', ( e ) => errors.push( e.message ) );
			const log = [];
			const step = async ( label, act, wait = 200 ) => {
				if ( act ) await act();
				await tab.waitForTimeout( wait );
				log.push( [ label, await tab.evaluate( snapshot ) ] );
			};
			const open = async ( hash = '' ) => {
				await tab.goto( `${ base }/${ SLUG }/${ hash }`, { waitUntil: 'load' } );
				await tab.waitForTimeout( 1200 );
			};
			await open();
			const tabs = tab.locator( '.wp-block-tab-list button' );
			const toggles = tab.locator( '.wp-block-accordion-heading__toggle' );
			const tracks = tab.locator( '.wp-block-playlist-track__button' );
			await step( 'the page as it loads' );
			await step( 'a click on the second tab', () => tabs.nth( 1 ).click() );
			for ( const key of [ 'ArrowRight', 'ArrowRight', 'ArrowLeft', 'End', 'Home', 'ArrowDown', 'Enter' ] ) {
				await step( `${ key } on the tabs`, () => tab.keyboard.press( key ) );
			}
			await step( 'opening the first item, one at a time', () => toggles.nth( 0 ).click() );
			await step( 'opening the second item', () => toggles.nth( 1 ).click() );
			await step( 'closing it again', () => toggles.nth( 1 ).click() );
			await step( 'opening an item where several may be open', () => toggles.nth( 3 ).click() );
			await step( 'opening the lightbox', async () => {
				await tab.hover( '.wp-lightbox-container img' );
				await tab.click( 'button.lightbox-trigger' );
			}, 900 );
			await step( 'closing it with Escape', () => tab.keyboard.press( 'Escape' ), 900 );
			await step( 'opening it from the image and closing it with a click', async () => {
				await tab.click( '.wp-lightbox-container img' );
				await tab.waitForTimeout( 900 );
				await tab.mouse.click( 30, 30 );
			}, 900 );
			await step( 'playing the second track', () => tracks.nth( 1 ).click(), 1500 );
			await step( 'pausing it from its own button', () => tracks.nth( 1 ).click(), 800 );
			await step( 'playing it from the player', () => tab.locator( '.waveform-btn' ).click(), 800 );
			const third = await tab.evaluate( () => ( document.querySelectorAll( '.wp-block-tabs .wp-block-tab-panel' )[ 2 ] || {} ).id || '' );
			const second = await tab.evaluate( () => ( document.querySelectorAll( '.wp-block-accordion-panel' )[ 1 ] || {} ).id || '' );
			await open( '#' + third );
			await tab.reload( { waitUntil: 'load' } );
			await step( 'a page opened at a tab panel', null, 1000 );
			await open( '#' + second );
			await tab.reload( { waitUntil: 'load' } );
			await step( 'a page opened at an item\'s panel', null, 1000 );
			runs[ stack ] = { log, errors };
			await context.close();
		}
		runs.engine.log.forEach( ( [ label, engine ], i ) => {
			const reference = ( runs.reference.log[ i ] || [] )[ 1 ];
			check( engine === reference, label, `engine    ${ engine }\n      reference ${ reference }` );
		} );
		check( runs.engine.errors.length === 0, 'no page errors on the engine', runs.engine.errors.join( ' | ' ) );
	} catch ( e ) {
		check( false, 'the suite ran to its end', e.message );
	} finally {
		if ( browser ) await browser.close();
		for ( const [ , id ] of made.reverse() ) {
			if ( id > 1 ) {
				try {
					wp( `post delete ${ id } --force` );
				} catch ( e ) {
					console.log( `  could not delete post ${ id }` );
				}
			}
		}
	}
	console.log( `\n${ pass } passed, ${ fail } failed` );
	process.exit( fail === 0 ? 0 : 1 );
} )();
