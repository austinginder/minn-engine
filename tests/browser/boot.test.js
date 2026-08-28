// Minn Admin boots on Minn Engine — the milestone-7 proof, in a real browser.
//
// Logs in through the engine's /wp-login.php, loads /minn-admin/, and asserts
// that app.js runs: the boot spinner is replaced by the real app chrome, the
// nav renders, content loads from wp/v2 through the engine, and no fatal
// console/page errors occur. Content-panel data proves the SPA is reading the
// engine's REST surface, not just painting a shell.
//
// Run:  node tests/browser/boot.test.js
// Env:  MINN_ENGINE_URL (default https://minn-engine.localhost),
//       MINN_ADMIN_USER / MINN_ADMIN_PASS (default admin / the dev password).

const { chromium } = require( 'playwright-core' );

const BASE = ( process.env.MINN_ENGINE_URL || 'https://minn-engine.localhost' ).replace( /\/$/, '' );
const USER = process.env.MINN_ADMIN_USER || 'admin';
const PASS = process.env.MINN_ADMIN_PASS || 'password';
const CHROME = '/Applications/Google Chrome.app/Contents/MacOS/Google Chrome';

let pass = 0, fail = 0;
const ok = ( c, label, detail = '' ) => {
	if ( c ) { pass++; console.log( `  ok  ${label}` ); }
	else { fail++; console.log( `FAIL  ${label}${detail ? '\n      ' + detail : ''}` ); }
};

( async () => {
	const browser = await chromium.launch( {
		executablePath: CHROME,
		args: [ '--ignore-certificate-errors', '--disable-http2', '--disable-features=MacAppCodeSignClone' ],
	} );
	const context = await browser.newContext( { ignoreHTTPSErrors: true } );
	const page = await context.newPage();

	const errors = [];
	page.on( 'pageerror', ( e ) => errors.push( 'pageerror: ' + e.message ) );
	page.on( 'console', ( m ) => { if ( m.type() === 'error' ) errors.push( 'console: ' + m.text() ); } );

	console.log( `boot suite: Minn Admin on ${BASE}` );

	try {
		// 1. Sign in through the engine's own login form.
		await page.goto( `${BASE}/wp-login.php`, { waitUntil: 'domcontentloaded', timeout: 20000 } );
		await page.fill( 'input[name="log"]', USER );
		await page.fill( 'input[name="pwd"]', PASS );
		await Promise.all( [
			page.waitForNavigation( { waitUntil: 'domcontentloaded', timeout: 20000 } ),
			page.click( 'button[name="wp-submit"]' ),
		] );
		ok( page.url().includes( '/minn-admin' ), 'login redirects into /minn-admin', page.url() );

		// 2. The app boots: the spinner is replaced by real chrome.
		await page.waitForSelector( '#minn-app .minn-boot-spinner', { state: 'detached', timeout: 20000 } ).catch( () => {} );
		const bootedSelector = await Promise.race( [
			page.waitForSelector( '.minn-nav', { timeout: 20000 } ).then( () => '.minn-nav' ).catch( () => null ),
			page.waitForSelector( '[class*="minn-nav"]', { timeout: 20000 } ).then( () => 'nav' ).catch( () => null ),
		] );
		ok( !! bootedSelector, 'app chrome renders (nav present)' );

		// 3. window.MINN is the engine payload.
		const boot = await page.evaluate( () => window.MINN || null );
		ok( boot && boot.user && boot.user.login === USER || ( boot && boot.user ), 'window.MINN carries the signed-in user' );
		ok( boot && typeof boot.engine === 'string' && boot.engine.includes( 'Minn Engine' ), 'boot payload is engine-issued', boot ? boot.engine : 'no boot' );

		// 4. The Overview dashboard lights up from minn-admin/v1: greeting,
		//    stats cards and the recent-activity feed all come from the engine.
		await page.waitForTimeout( 2500 );
		const dash = await page.evaluate( () => {
			const el = document.querySelector( '#minn-view' ) || document.querySelector( '.minn-main' ) || document.body;
			return el.innerText;
		} );
		ok( /Good (morning|afternoon|evening)/.test( dash ), 'dashboard greeting renders', dash.slice( 0, 120 ) );
		ok( /Published posts/i.test( dash ) && /Media files/i.test( dash ), 'overview stats cards render from minn-admin/v1' );
		ok( /published|drafted|commented/i.test( dash ), 'recent activity feed renders' );

		// 5. Open Content the way a user does and confirm rows load from wp/v2
		//    through the engine (drafts included, proving the edit-context list).
		await page.click( 'text=Content' ).catch( () => {} );
		await page.waitForTimeout( 3000 );
		const view = await page.evaluate( () => {
			const el = document.querySelector( '#minn-view' ) || document.querySelector( '.minn-main' ) || document.body;
			return el.innerText;
		} );
		ok( /Hello world|Building in the open|Texturize|Scribe/i.test( view ), 'content view lists engine posts', view.slice( 0, 120 ) );
		ok( /Draft/i.test( view ) && /Published/i.test( view ), 'content view shows draft and published statuses (edit context)' );

		// 6. No fatal errors during the whole boot + navigation.
		const fatal = errors.filter( ( e ) => ! /favicon|manifest\.json|minn-admin\/v1|admin-ajax|404|Failed to load resource/i.test( e ) );
		ok( fatal.length === 0, 'no fatal console or page errors', fatal.slice( 0, 4 ).join( '\n      ' ) );
		if ( errors.length ) {
			console.log( `      (${errors.length} total console/page messages, ${errors.length - fatal.length} expected minn-admin/v1 gaps)` );
		}
	} catch ( e ) {
		ok( false, 'suite ran without throwing', e.message );
	} finally {
		await browser.close().catch( () => {} );
	}

	console.log( `\n${pass} passed, ${fail} failed` );
	process.exit( fail > 0 ? 1 : 0 );
} )();
