/**
 * Interactivity suite: the engine's MIT client runtime drives the
 * navigation block's mobile overlay and a third-party plugin's view module
 * (mosne-dark-palette) on the dogfood site, with no page errors.
 *
 *   node tests/browser/interactivity.test.js
 */
const { chromium } = require( 'playwright-core' );
require( './pin-theme' ).pinTheme();

const CHROME = '/Applications/Google Chrome.app/Contents/MacOS/Google Chrome';
const BASE = ( process.env.MINN_INTERACTIVITY_BASE || `https://${ require( './local' ).local( 'dogfood' ) }.localhost` ).replace( /\/$/, '' );

let pass = 0;
let fail = 0;
function check( cond, label, detail = '' ) {
	if ( cond ) { pass++; console.log( `  ok  ${ label }` ); } else { fail++; console.log( `FAIL  ${ label }${ detail ? `\n      ${ detail }` : '' }` ); }
}

( async () => {
	let reachable = true;
	try {
		const res = await fetch( BASE + '/', { signal: AbortSignal.timeout( 5000 ) } );
		reachable = res.ok;
	} catch ( e ) {
		reachable = false;
	}
	if ( ! reachable ) {
		console.log( `interactivity suite: no dogfood site at ${ BASE }; skipping` );
		process.exit( 0 );
	}
	console.log( `interactivity suite: ${ BASE }` );
	const browser = await chromium.launch( { executablePath: CHROME, args: [ '--ignore-certificate-errors', '--disable-http2', '--disable-features=MacAppCodeSignClone' ] } );
	const errors = [];
	const failedRequests = [];

	// Mobile: the overlay menu.
	const mobile = await browser.newContext( { viewport: { width: 400, height: 800 }, ignoreHTTPSErrors: true } );
	const page = await mobile.newPage();
	page.on( 'pageerror', ( e ) => errors.push( 'pageerror: ' + e.message ) );
	page.on( 'console', ( m ) => { if ( m.type() === 'error' ) errors.push( 'console: ' + m.text() ); } );
	page.on( 'requestfailed', ( r ) => { if ( /minn-engine\/|view\.js/.test( r.url() ) ) failedRequests.push( r.url() ); } );
	page.on( 'response', ( r ) => { if ( r.status() >= 400 && /minn-engine|wp-content\/plugins.*view\.js/.test( r.url() ) ) failedRequests.push( r.status() + ' ' + r.url() ); } );
	await page.goto( BASE + '/', { waitUntil: 'networkidle' } );

	const modules = await page.evaluate( () => ( {
		importmap: !! document.querySelector( 'script#wp-importmap[type="importmap"]' ),
		navModule: !! document.getElementById( '@wordpress/block-library/navigation/view-js-module' ),
		pluginModule: !! document.querySelector( 'script[type="module"][id$="view-script-module-js-module"]' ),
		imports: JSON.parse( document.querySelector( 'script#wp-importmap' )?.textContent || '{}' ).imports || {},
	} ) );
	check( modules.importmap, 'the page prints an import map', JSON.stringify( modules ) );
	check( '@wordpress/interactivity' in modules.imports && modules.imports[ '@wordpress/interactivity' ].includes( '/minn/assets/interactivity.js' ), 'the import map resolves @wordpress/interactivity to the engine runtime', JSON.stringify( modules.imports ) );
	check( modules.navModule, 'the navigation view module is printed' );
	check( modules.pluginModule, 'the plugin view module (mosne-dark-palette) is printed' );

	const open = page.locator( '.wp-block-navigation__responsive-container-open' ).first();
	const container = page.locator( '.wp-block-navigation__responsive-container' ).first();
	check( await open.isVisible(), 'the open button is visible at 400px' );
	check( ! ( await container.evaluate( ( el ) => el.classList.contains( 'is-menu-open' ) ) ), 'the menu starts closed' );
	await open.click();
	await page.waitForTimeout( 100 );
	const opened = await container.evaluate( ( el ) => ( {
		open: el.classList.contains( 'is-menu-open' ),
		modal: el.classList.contains( 'has-modal-open' ),
		html: document.documentElement.classList.contains( 'has-modal-open' ),
		role: el.querySelector( '.wp-block-navigation__responsive-dialog' )?.getAttribute( 'role' ),
		ariaModal: el.querySelector( '.wp-block-navigation__responsive-dialog' )?.getAttribute( 'aria-modal' ),
		visible: getComputedStyle( el ).position === 'fixed',
	} ) );
	check( opened.open && opened.modal, 'clicking the hamburger marks the container open', JSON.stringify( opened ) );
	check( opened.html, 'html gains has-modal-open while the overlay is open' );
	check( opened.role === 'dialog' && opened.ariaModal === 'true', 'the dialog gains role=dialog and aria-modal', JSON.stringify( opened ) );
	check( opened.visible, 'the overlay is a fixed full-screen layer' );
	const firstLinkVisible = await page.locator( '.wp-block-navigation__responsive-container.is-menu-open .wp-block-navigation-item a' ).first().isVisible();
	check( firstLinkVisible, 'menu links are visible inside the overlay' );
	await page.keyboard.press( 'Escape' );
	await page.waitForTimeout( 100 );
	const closed = await container.evaluate( ( el ) => ( { open: el.classList.contains( 'is-menu-open' ), html: document.documentElement.classList.contains( 'has-modal-open' ), role: el.querySelector( '.wp-block-navigation__responsive-dialog' )?.getAttribute( 'role' ) } ) );
	check( ! closed.open && ! closed.html && closed.role === null, 'Escape closes the overlay and clears the attributes', JSON.stringify( closed ) );
	await open.click();
	await page.waitForTimeout( 100 );
	check( await container.evaluate( ( el ) => el.classList.contains( 'is-menu-open' ) ), 'the overlay reopens after Escape' );
	// The dogfood theme hides the close button (visibility: hidden); the action still runs when it is clickable.
	const close = page.locator( '.wp-block-navigation__responsive-container-close' ).first();
	if ( await close.isVisible() ) {
		await close.click();
	} else {
		await close.evaluate( ( el ) => el.click() );
	}
	await page.waitForTimeout( 100 );
	check( ! ( await container.evaluate( ( el ) => el.classList.contains( 'is-menu-open' ) ) ), 'the close button closes the overlay' );

	// Desktop: the plugin's dark-mode toggle runs through the runtime (store, getContext, bind, class).
	const desktop = await browser.newContext( { viewport: { width: 1280, height: 900 }, ignoreHTTPSErrors: true } );
	const wide = await desktop.newPage();
	wide.on( 'pageerror', ( e ) => errors.push( 'pageerror: ' + e.message ) );
	wide.on( 'console', ( m ) => { if ( m.type() === 'error' ) errors.push( 'console: ' + m.text() ); } );
	await wide.goto( BASE + '/', { waitUntil: 'networkidle' } );
	const toggle = wide.locator( '.navigaiton-item__wrapper' ).first();
	check( ( await toggle.count() ) === 1, 'the dark-palette toggle is on the page' );
	const before = await toggle.evaluate( ( el ) => ( { mode: JSON.parse( el.getAttribute( 'data-wp-context' ) ).mode, theme: document.documentElement.getAttribute( 'data-theme' ), button: el.querySelector( 'button' )?.className, aria: el.querySelector( 'span' )?.getAttribute( 'aria-label' ) } ) );
	await toggle.click();
	await wide.waitForTimeout( 100 );
	const after = await toggle.evaluate( ( el ) => ( { theme: document.documentElement.getAttribute( 'data-theme' ), button: el.querySelector( 'button' )?.className, aria: el.querySelector( 'span' )?.getAttribute( 'aria-label' ), stored: ( () => { try { return localStorage.getItem( 'mosne-dark-palette' ); } catch ( e ) { return null; } } )() } ) );
	check( after.theme === 'light', 'the first click (auto to light) sets data-theme=light on <html>', JSON.stringify( { before, after } ) );
	await toggle.click();
	await wide.waitForTimeout( 100 );
	const second = await toggle.evaluate( ( el ) => ( { theme: document.documentElement.getAttribute( 'data-theme' ), button: el.querySelector( 'button' )?.className } ) );
	check( second.theme === 'dark' && /has-icon--dark/.test( second.button || '' ), 'the second click (light to dark) sets data-theme=dark', JSON.stringify( second ) );
	check( after.button !== before.button && /has-icon--(light|dark|auto)/.test( after.button || '' ), 'data-wp-bind--class swaps the button class', JSON.stringify( { before, after } ) );
	check( after.aria !== before.aria, 'data-wp-bind--aria-label follows the context', JSON.stringify( { before, after } ) );
	check( [ 'light', 'dark', 'auto' ].includes( after.stored ), 'the plugin action ran (localStorage written)', String( after.stored ) );

	// Submenu hover/click on desktop, if the site has one.
	const submenu = wide.locator( '.wp-block-navigation-item.has-child' ).first();
	if ( await submenu.count() ) {
		const button = submenu.locator( '.wp-block-navigation-submenu__toggle' ).first();
		if ( await button.count() ) {
			await button.click();
			await wide.waitForTimeout( 100 );
			check( ( await button.getAttribute( 'aria-expanded' ) ) === 'true', 'a submenu toggle sets aria-expanded via state.isSubmenuOpen' );
		}
	}

	// The runtime's own guards, on pages of our making served at the site's address with its import map: server
	// data is read only from the script element the server printed, a reserved key in parsed data never reaches
	// Object.prototype, and an event handler attribute is never bound.
	const runtime = modules.imports[ '@wordpress/interactivity' ];
	const shell = ( body ) => `<!doctype html><html><head><script type="importmap">${ JSON.stringify( { imports: { '@wordpress/interactivity': runtime } } ) }</script></head><body>${ body }<script type="module">import { store } from '@wordpress/interactivity'; store( 'zzguard', {} ); window.zzReady = true;</script></body></html>`;
	const guarded = await browser.newContext( { ignoreHTTPSErrors: true } );
	const guard = await guarded.newPage();
	guard.on( 'pageerror', ( e ) => errors.push( 'guard pageerror: ' + e.message ) );
	const pages = {
		'/zz-guard-shadow/': '<div id="wp-script-module-data-@wordpress/interactivity">{"state":{"zzguard":{"word":"shadowed"}}}</div>'
			+ '<script type="application/json" id="wp-script-module-data-@wordpress/interactivity">{"state":{"zzguard":{"word":"served"}}}</script>'
			+ '<div data-wp-interactive="zzguard"><span id="zz-word" data-wp-text="state.word"></span></div>',
		'/zz-guard-proto/': '<script type="application/json" id="wp-script-module-data-@wordpress/interactivity">{"state":{"zzguard":{"__proto__":{"zzPolluted":"state"},"code":"alert(1)","word":"served"}}}</script>'
			+ '<div data-wp-interactive="zzguard" data-wp-context=\'{"__proto__":{"zzPollutedContext":"context"},"x":1}\'>'
			+ '<button id="zz-bind" data-wp-bind--onclick="state.code" data-wp-bind--ONMOUSEOVER="state.code" data-wp-bind--title="state.word">b</button>'
			+ '<span id="zz-ctx" data-wp-text="context.x"></span></div>',
		// Security sweep 2026-11-12 (3.1, 3.4): an address or style an author writes into context.
		'/zz-guard-url/': '<div data-wp-interactive="zzguard" data-wp-context=\'{"js":"  JavaScript:alert(1)","data":"data:text/html,x","ok":"https://example.com/ok","css":"background:url(https://example.com/t.gif)","color":"red"}\'>'
			+ '<a id="zz-js" href="https://example.com/" data-wp-bind--href="context.js">a</a>'
			+ '<a id="zz-ok" href="https://example.com/" data-wp-bind--href="context.ok">a</a>'
			+ '<iframe id="zz-frame" data-wp-bind--src="context.data" data-wp-bind--srcdoc="context.js"></iframe>'
			+ '<button id="zz-form" data-wp-bind--formaction="context.js">b</button>'
			+ '<div id="zz-css" data-wp-bind--style="context.css" data-wp-style--background-image="context.css" data-wp-style--color="context.color">d</div></div>',
	};
	await guarded.route( ( url ) => Object.keys( pages ).includes( new URL( url ).pathname ), ( route ) => route.fulfill( { status: 200, contentType: 'text/html', body: shell( pages[ new URL( route.request().url() ).pathname ] ) } ) );
	await guard.goto( BASE + '/zz-guard-shadow/', { waitUntil: 'networkidle' } );
	await guard.waitForFunction( () => window.zzReady === true, null, { timeout: 10000 } ).catch( () => {} );
	const word = await guard.evaluate( () => document.getElementById( 'zz-word' ).textContent );
	check( word === 'served', 'server state is read from the script element, past an earlier element carrying its id', JSON.stringify( word ) );
	await guard.goto( BASE + '/zz-guard-proto/', { waitUntil: 'networkidle' } );
	await guard.waitForFunction( () => window.zzReady === true, null, { timeout: 10000 } ).catch( () => {} );
	const proto = await guard.evaluate( () => ( {
		state: ( {} ).zzPolluted,
		context: ( {} ).zzPollutedContext,
		hydrated: document.getElementById( 'zz-bind' ).getAttribute( 'title' ),
		text: document.getElementById( 'zz-ctx' ).textContent,
		onclick: document.getElementById( 'zz-bind' ).getAttribute( 'onclick' ),
		onmouseover: document.getElementById( 'zz-bind' ).getAttribute( 'onmouseover' ),
	} ) );
	check( proto.hydrated === 'served' && proto.text === '1', 'the guard page hydrates (title and context bound)', JSON.stringify( proto ) );
	check( proto.state === undefined && proto.context === undefined, 'a __proto__ key in server state or context never reaches Object.prototype', JSON.stringify( proto ) );
	check( proto.onclick === null && proto.onmouseover === null, 'an event handler attribute is never bound', JSON.stringify( proto ) );
	await guard.goto( BASE + '/zz-guard-url/', { waitUntil: 'networkidle' } );
	await guard.waitForFunction( () => window.zzReady === true, null, { timeout: 10000 } ).catch( () => {} );
	const url = await guard.evaluate( () => ( {
		js: document.getElementById( 'zz-js' ).getAttribute( 'href' ),
		ok: document.getElementById( 'zz-ok' ).getAttribute( 'href' ),
		src: document.getElementById( 'zz-frame' ).getAttribute( 'src' ),
		srcdoc: document.getElementById( 'zz-frame' ).getAttribute( 'srcdoc' ),
		formaction: document.getElementById( 'zz-form' ).getAttribute( 'formaction' ),
		style: document.getElementById( 'zz-css' ).getAttribute( 'style' ) || '',
	} ) );
	check( url.js === null && url.src === null && url.formaction === null && url.ok === 'https://example.com/ok', 'a javascript: or data: address is never bound, as on the reference (a safe one is)', JSON.stringify( url ) );
	check( url.srcdoc === null, 'a frame\'s srcdoc is never bound', JSON.stringify( url ) );
	check( ! url.style.includes( 'url(' ) && url.style.includes( 'red' ), 'a style binding sets no url(), and a plain one still applies', JSON.stringify( url ) );
	await guarded.close();

	check( errors.length === 0, 'no page or console errors', errors.join( '\n      ' ) );
	check( failedRequests.length === 0, 'every module request succeeded', failedRequests.join( '\n      ' ) );
	await browser.close();
	console.log( `\n${ pass } passed, ${ fail } failed` );
	process.exit( fail ? 1 : 0 );
} )();
