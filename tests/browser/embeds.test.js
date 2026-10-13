/**
 * Embeds suite: one post of the test site embedded in another, driven in a
 * real browser on the engine and on the reference, step by step, each
 * step's state compared. The host page: the blockquote hidden, the iframe
 * shown and as tall as its card reports (held between 200 and 1000), sized
 * again when the card's window resizes, a click on the card's title taking
 * the whole window to the post and a click on a link to another host doing
 * nothing. The card, inside the host page's frame and opened on its own:
 * the root element's class, the sharing dialog opening and where focus
 * goes, its tabs by click and by arrow keys, the share inputs selecting
 * their text on click, the Tab trap, Escape and the close button, and focus
 * going back to the share button. States are classes, attributes, focus
 * and selection, never visibility; a label marked [css] depends on the card
 * stylesheet too (a height, or where a mouse click or the browser's own Tab
 * lands). Each stack embeds only its own site's posts, so the host post is
 * pointed at the post's address on the stack under test and its stored
 * oEmbed answer cleared first. The suite makes the two posts and removes
 * them.
 *
 *   node tests/browser/embeds.test.js
 */
const { execSync } = require( 'child_process' );
const fs = require( 'fs' );
const os = require( 'os' );
const path = require( 'path' );
const { chromium } = require( 'playwright-core' );
const { siteDir } = require( './local' );

const CHROME = '/Applications/Google Chrome.app/Contents/MacOS/Google Chrome';
const SITE = 'minn';
const stacks = { engine: `https://${ SITE }.localhost`, reference: `https://wp.${ SITE }.localhost` };
const WP = `wp --path=${ siteDir( SITE ) }/wp-reference`;
const POST_SLUG = 'zz-embeds-suite-post';
const HOST_SLUG = 'zz-embeds-suite-host';
const ELSEWHERE = 'https://example.org/elsewhere/';

let pass = 0;
let fail = 0;
function check( cond, label, detail = '' ) {
	if ( cond ) { pass++; console.log( `  ok  ${ label }` ); } else { fail++; console.log( `FAIL  ${ label }${ detail ? `\n      ${ detail }` : '' }` ); }
}
const wp = ( command ) => execSync( `${ WP } ${ command } 2>/dev/null`, { encoding: 'utf8' } ).trim();

// Rendering the embed caches it as post meta (_oembed_<hash>, with _oembed_time_<hash>) on whichever post was current; every
// cached answer that names the suite's post goes, wherever it landed.
function forgetEmbeds() {
	const meta = `${ wp( 'db prefix' ) }postmeta`;
	const rows = wp( `db query "SELECT meta_key FROM ${ meta } WHERE meta_key LIKE '\\_oembed\\_%' AND meta_value LIKE '%${ POST_SLUG }%'" --skip-column-names` );
	rows.split( '\n' ).map( ( key ) => key.trim() ).filter( ( key ) => /^_oembed_[0-9a-f]{32}$/.test( key ) ).forEach( ( key ) => {
		const hash = key.slice( '_oembed_'.length );
		wp( `db query "DELETE FROM ${ meta } WHERE meta_key IN ('_oembed_${ hash }', '_oembed_time_${ hash }')"` );
	} );
}

// An error's first line only: the rest of a Playwright error previews elements, and the card's HTML-embed box holds a script.
const firstLine = ( e ) => String( ( e && e.message ) || e ).split( '\n' )[ 0 ].replace( /<.*$/, '' ).trim();

/** The host post holds the post's address on this stack, on its own line, and renders it afresh. */
function pointHost( host, base ) {
	const file = path.join( os.tmpdir(), `minn-embeds-${ process.pid }.html` );
	fs.writeFileSync( file, `<!-- wp:paragraph --><p>Before the embed.</p><!-- /wp:paragraph -->\n\n${ base }/${ POST_SLUG }/\n\n<!-- wp:paragraph --><p>After the embed.</p><!-- /wp:paragraph -->` );
	wp( `post update ${ host } ${ file }` );
	fs.unlinkSync( file );
	const keys = JSON.parse( wp( `post meta list ${ host } --fields=meta_key --format=json` ) || '[]' ).map( ( row ) => row.meta_key );
	keys.filter( ( key ) => key.startsWith( '_oembed_' ) ).forEach( ( key ) => wp( `post meta delete ${ host } ${ key }` ) );
}

// On the top window, before any page script: the messages frames post to it.
function recordMessages() {
	if ( window !== window.top ) return;
	window.__embedMessages = [];
	window.addEventListener( 'message', ( event ) => {
		const data = event.data;
		if ( event.source !== window && data && typeof data === 'object' ) window.__embedMessages.push( { message: data.message, value: data.value, secret: data.secret } );
	} );
}

// The host page as the embed leaves it: what the host script did, and whether the iframe follows the card's last report.
function hostState() {
	const iframe = document.querySelector( 'iframe.wp-embedded-content' );
	const quote = document.querySelector( 'blockquote.wp-embedded-content' );
	if ( ! iframe || ! quote ) return JSON.stringify( { embed: 'missing' } );
	const secret = iframe.getAttribute( 'data-secret' ) || '';
	const messages = window.__embedMessages || [];
	const heights = messages.filter( ( m ) => m.message === 'height' );
	const last = heights.length ? heights[ heights.length - 1 ].value : null;
	const clamp = ( value ) => Math.min( 1000, Math.max( 200, parseInt( value, 10 ) || 0 ) );
	return JSON.stringify( {
		hostScript: window.wp ? typeof window.wp.receiveEmbedMessage : 'none',
		quoteHidden: quote.style.display === 'none',
		iframeStyle: iframe.getAttribute( 'style' ),
		secret: /^[A-Za-z0-9]{10}$/.test( secret ) && quote.getAttribute( 'data-secret' ) === secret && iframe.getAttribute( 'src' ).endsWith( '#?secret=' + secret ),
		cardReported: heights.length > 0 && heights.every( ( m ) => m.secret === secret ),
		reportedSinceMark: window.__mark === undefined ? null : messages.slice( window.__mark ).some( ( m ) => m.message === 'height' ),
		heightAsReported: last !== null && iframe.getAttribute( 'height' ) === String( clamp( last ) ),
	} );
}

// The numbers themselves, which the card's layout decides.
function hostHeights() {
	const iframe = document.querySelector( 'iframe.wp-embedded-content' );
	const heights = ( window.__embedMessages || [] ).filter( ( m ) => m.message === 'height' );
	return JSON.stringify( { reported: heights.length ? heights[ heights.length - 1 ].value : null, height: iframe && iframe.getAttribute( 'height' ) } );
}

// Where the top window is, and the last link the card handed up.
function whereNow() {
	const links = ( window.__embedMessages || [] ).filter( ( m ) => m.message === 'link' );
	return JSON.stringify( { path: location.pathname, linkPosted: links.length ? links[ links.length - 1 ].value : null } );
}

// The card's state: root class, dialog class, tabs, panels, focus and selection (named by tag, class and tab, never by text).
function cardState() {
	const dialog = document.querySelector( '.wp-embed-share-dialog' );
	const name = ( el ) => {
		if ( ! el || el === document.body ) return 'body';
		const classes = typeof el.className === 'string' && el.className.trim() ? '.' + el.className.trim().split( /\s+/ ).join( '.' ) : '';
		const tab = el.getAttribute( 'role' ) === 'tab' ? `[tab ${ ( el.getAttribute( 'aria-controls' ) || '' ).replace( /^wp-embed-share-tab-/, '' ).replace( /(-\d+)+$/, '' ) }]` : '';
		return el.tagName.toLowerCase() + classes + tab;
	};
	const allSelected = ( el ) => {
		const length = el.tagName === 'TEXTAREA' ? el.textLength : el.value.length;
		return length > 0 && el.selectionStart === 0 && el.selectionEnd === length;
	};
	return JSON.stringify( {
		root: document.documentElement.className,
		dialog: dialog ? dialog.className : null,
		tabs: [ ...document.querySelectorAll( '.wp-embed-share-tab-button [role="tab"]' ) ].map( ( t ) => `${ t.getAttribute( 'aria-selected' ) }/${ t.getAttribute( 'tabindex' ) }` ),
		panels: [ ...document.querySelectorAll( '.wp-embed-share-tab' ) ].map( ( p ) => p.getAttribute( 'aria-hidden' ) ),
		focus: name( document.activeElement ),
		selected: [ ...document.querySelectorAll( '.wp-embed-share-input' ) ].map( allSelected ),
	} );
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
		console.log( `embeds suite: ${ Object.values( stacks ).join( ' or ' ) } not answering; skipping` );
		process.exit( 0 );
	}
	console.log( `embeds suite: ${ stacks.engine } (engine) / ${ stacks.reference } (reference)` );
	const made = [];
	forgetEmbeds();
	let browser;
	try {
		const post = parseInt( wp( `post create --post_type=post --post_status=publish --comment_status=open --post_title="ZZ Embeds Suite Post" --post_name=${ POST_SLUG } --post_content='<!-- wp:paragraph --><p>The post the embeds suite embeds.</p><!-- /wp:paragraph -->' --post_excerpt='Embedded by the embeds suite, with a <a href="${ ELSEWHERE }">link elsewhere</a>.' --porcelain` ), 10 );
		if ( post > 1 ) made.push( post );
		const host = parseInt( wp( `post create --post_type=post --post_status=publish --post_title="ZZ Embeds Suite Host" --post_name=${ HOST_SLUG } --post_content='' --porcelain` ), 10 );
		if ( host > 1 ) made.push( host );
		check( post > 1 && host > 1, 'the post to embed and its host post are in place' );

		browser = await chromium.launch( { executablePath: CHROME, args: [ '--ignore-certificate-errors', '--disable-http2', '--disable-features=MacAppCodeSignClone' ] } );
		const runs = {};
		for ( const [ stack, base ] of Object.entries( stacks ) ) {
			pointHost( host, base );
			const context = await browser.newContext( { ignoreHTTPSErrors: true, viewport: { width: 1000, height: 900 } } );
			await context.addInitScript( recordMessages );
			const tab = await context.newPage();
			const errors = [];
			tab.on( 'pageerror', ( e ) => errors.push( firstLine( e ) ) );
			const log = [];
			// One step: the action (its failure noted in the state, so the run goes on), a pause, then the state read where it lives.
			const step = async ( label, where, read, act, wait = 250 ) => {
				let failed = null;
				if ( act ) {
					try {
						await act();
					} catch ( e ) {
						failed = firstLine( e );
					}
				}
				await tab.waitForTimeout( wait );
				let state;
				try {
					state = await where().evaluate( read );
				} catch ( e ) {
					state = `unreadable: ${ firstLine( e ) }`;
				}
				log.push( [ label, failed ? `${ state } (action failed: ${ failed })` : state ] );
			};
			const openHost = async () => {
				await tab.goto( `${ base }/${ HOST_SLUG }/`, { waitUntil: 'load' } );
				await tab.waitForTimeout( 1500 );
			};
			const top = () => tab.mainFrame();
			const cardFrame = () => tab.frames().find( ( f ) => f.url().includes( `/${ POST_SLUG }/embed/` ) ) || tab.mainFrame();
			const click = ( frame, selector ) => frame.locator( selector ).first().click( { timeout: 3000 } );
			const arrived = ( pathname ) => tab.waitForURL( ( url ) => url.pathname === pathname, { timeout: 4000 } ).then( () => tab.waitForLoadState( 'load' ) );

			// The host side.
			await openHost();
			await step( 'the host page after load', top, hostState );
			await step( 'the host page after load: the reported height and the iframe\'s [css]', top, hostHeights );
			const narrow = () => top().evaluate( () => {
				window.__mark = window.__embedMessages.length;
				document.querySelector( 'iframe.wp-embedded-content' ).width = 160;
			} );
			await step( 'the iframe after the card\'s window resizes', top, hostState, narrow, 900 );
			await step( 'the iframe after the card\'s window resizes: the heights [css]', top, hostHeights );
			await step( 'a click on the card\'s title link takes the window to the post', top, whereNow, async () => {
				await click( cardFrame(), '.wp-embed-heading a' );
				await arrived( `/${ POST_SLUG }/` );
			} );
			await openHost();
			await step( 'a click on a link to another host leaves the window where it is', top, whereNow, () => click( cardFrame(), `.wp-embed-excerpt a[href="${ ELSEWHERE }"]` ), 1200 );

			// The card, in the host page's frame, then on its own.
			for ( const mode of [ 'in a frame', 'on its own' ] ) {
				if ( mode === 'in a frame' ) {
					await openHost();
				} else {
					await tab.goto( `${ base }/${ POST_SLUG }/embed/`, { waitUntil: 'load' } );
					await tab.waitForTimeout( 600 );
				}
				const card = cardFrame;
				const at = ( label ) => `card ${ mode }: ${ label }`;
				const key = ( name ) => () => tab.keyboard.press( name );
				const focus = ( selector ) => () => card().evaluate( ( s ) => document.querySelector( s ).focus(), selector );
				await step( at( 'the root element\'s class' ), card, cardState );
				await step( at( 'a click on the share button opens the dialog [css]' ), card, cardState, () => click( card(), '.wp-embed-share-dialog-open' ) );
				await step( at( 'a click on the HTML tab [css]' ), card, cardState, () => click( card(), '.wp-embed-share-tab-button-html [role="tab"]' ) );
				await step( at( 'a click on it again [css]' ), card, cardState, () => click( card(), '.wp-embed-share-tab-button-html [role="tab"]' ) );
				await step( at( 'a click on the HTML code selects it [css]' ), card, cardState, () => click( card(), 'textarea.wp-embed-share-input' ) );
				await step( at( 'a click on the WordPress tab [css]' ), card, cardState, () => click( card(), '.wp-embed-share-tab-button-wordpress [role="tab"]' ) );
				await step( at( 'a click on the address selects it [css]' ), card, cardState, () => click( card(), 'input.wp-embed-share-input' ) );
				await step( at( 'focus on the selected tab' ), card, cardState, focus( '.wp-embed-share-tab-button [aria-selected="true"]' ) );
				for ( const name of [ 'ArrowRight', 'ArrowRight', 'ArrowLeft', 'ArrowLeft', 'ArrowDown', 'End', 'Home' ] ) {
					await step( at( `${ name } on the tabs` ), card, cardState, key( name ) );
				}
				await step( at( 'Tab from the selected tab, the browser\'s own [css]' ), card, cardState, key( 'Tab' ) );
				await step( at( 'Tab again, the browser\'s own [css]' ), card, cardState, key( 'Tab' ) );
				await step( at( 'Tab again, kept in the dialog [css]' ), card, cardState, key( 'Tab' ) );
				await step( at( 'Shift+Tab on the selected tab goes to the close button' ), card, cardState, async () => {
					await focus( '.wp-embed-share-tab-button [aria-selected="true"]' )();
					await tab.keyboard.press( 'Shift+Tab' );
				} );
				await step( at( 'Tab on the close button goes to the selected tab' ), card, cardState, async () => {
					await focus( '.wp-embed-share-dialog-close' )();
					await tab.keyboard.press( 'Tab' );
				} );
				await step( at( 'Escape closes it, focus back on the share button' ), card, cardState, key( 'Escape' ) );
				await step( at( 'Escape with it closed' ), card, cardState, key( 'Escape' ) );
				await step( at( 'Enter on the share button opens it' ), card, cardState, key( 'Enter' ) );
				await step( at( 'ArrowRight to the HTML tab' ), card, cardState, key( 'ArrowRight' ) );
				await step( at( 'a click on the close button, focus back on the share button [css]' ), card, cardState, () => click( card(), '.wp-embed-share-dialog-close' ) );
				await step( at( 'opening it again focuses the HTML tab' ), card, cardState, key( 'Enter' ) );
				await step( at( 'Enter on the close button, focus back on the share button' ), card, cardState, async () => {
					await focus( '.wp-embed-share-dialog-close' )();
					await tab.keyboard.press( 'Enter' );
				} );
			}
			// On its own, a link is followed as any other.
			await step( 'the card on its own: a click on the title link goes to the post', top, whereNow, async () => {
				await click( tab.mainFrame(), '.wp-embed-heading a' );
				await arrived( `/${ POST_SLUG }/` );
			} );
			runs[ stack ] = { log, errors };
			await context.close();
		}
		runs.engine.log.forEach( ( [ label, engine ], i ) => {
			const reference = ( runs.reference.log[ i ] || [] )[ 1 ];
			check( engine === reference, label, `engine    ${ engine }\n      reference ${ reference }` );
		} );
		check( runs.engine.errors.length === 0, 'no page errors on the engine', runs.engine.errors.join( ' | ' ) );
	} catch ( e ) {
		check( false, 'the suite ran to its end', firstLine( e ) );
	} finally {
		if ( browser ) await browser.close();
		forgetEmbeds();
		for ( const id of made.reverse() ) {
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
