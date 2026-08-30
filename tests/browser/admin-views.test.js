// Minn Admin's Manage views on the dogfood site, in a real browser: Extensions,
// Structure, System, the profile's sessions card, the changelog behind the
// version button, and the Minn bar on the public site. Every REST error the
// app meets is a failure. Signs in through the engine's own token link
// (`wp user login`), so no password rides the suite.
//
// Env: MINN_DOGFOOD_ENGINE (default https://dogfood.localhost)
//      MINN_DOGFOOD_ROOT   (default ~/Cove/Sites/dogfood.localhost/public)
//      MINN_DOGFOOD_USER   (default austin)
const { execSync } = require( 'child_process' );
const { chromium } = require( 'playwright-core' );
require( './pin-theme' ).pinTheme();

const CHROME = '/Applications/Google Chrome.app/Contents/MacOS/Google Chrome';
const BASE = ( process.env.MINN_DOGFOOD_ENGINE || 'https://dogfood.localhost' ).replace( /\/$/, '' );
const ROOT = process.env.MINN_DOGFOOD_ROOT || '~/Cove/Sites/dogfood.localhost/public';
const USER = process.env.MINN_DOGFOOD_USER || 'austin';

let pass = 0;
let fail = 0;
function ok( cond, label, detail ) {
	if ( cond ) { pass++; console.log( `  ok  ${ label }` ); } else { fail++; console.log( `FAIL  ${ label }${ detail ? `\n      ${ detail }` : '' }` ); }
}

( async () => {
	let login;
	try {
		// Run from the site dir: its wp-cli.yml is what routes `user login` to the engine's verb.
		login = execSync( `wp user login ${ USER } 2>/dev/null`, { encoding: 'utf8', cwd: ROOT } ).trim().split( '\n' ).pop();
	} catch ( e ) {
		login = '';
	}
	if ( ! /^https?:\/\//.test( login || '' ) ) {
		console.log( `admin-views suite: no dogfood site at ${ ROOT }; skipping` );
		process.exit( 0 );
	}
	console.log( `admin-views suite: ${ BASE }` );
	const browser = await chromium.launch( { executablePath: CHROME, args: [ '--ignore-certificate-errors', '--disable-http2', '--disable-features=MacAppCodeSignClone' ] } );
	const context = await browser.newContext( { ignoreHTTPSErrors: true, viewport: { width: 1400, height: 900 } } );
	const page = await context.newPage();
	const errors = [];
	page.on( 'pageerror', ( e ) => errors.push( 'pageerror: ' + e.message ) );
	page.on( 'console', ( m ) => { if ( m.type() === 'error' ) errors.push( 'console: ' + m.text().slice( 0, 160 ) ); } );
	page.on( 'response', ( r ) => {
		if ( r.status() >= 400 && r.url().includes( '/wp-json/' ) ) {
			errors.push( `${ r.status() } ${ r.request().method() } ${ r.url().replace( BASE, '' ).slice( 0, 140 ) }` );
		}
	} );
	try {
		await page.goto( login, { waitUntil: 'domcontentloaded', timeout: 20000 } );
		ok( page.url().includes( '/minn-admin' ), 'token login lands in /minn-admin', page.url() );
		const view = async ( route ) => {
			await page.goto( `${ BASE }/minn-admin/${ route }`, { waitUntil: 'domcontentloaded', timeout: 20000 } );
			await page.waitForFunction( () => {
				const t = ( document.querySelector( '#minn-view' ) || document.body ).innerText || '';
				return t.length > 40 && ! /Loading (extensions|your profile|content|settings)/.test( t );
			}, null, { timeout: 20000 } ).catch( () => {} );
			return page.evaluate( () => ( document.querySelector( '#minn-view' ) || document.body ).innerText );
		};
		let text = await view( 'extensions' );
		const nav = await page.evaluate( () => document.body.innerText );
		ok( /Activity Log/.test( nav ), 'the Tools group lists the Stream adapter\'s Activity Log', nav.slice( 0, 120 ) );
		ok( /Plugins/.test( text ) && /Block Visibility/.test( text ) && /By /.test( text ) && ! /Something went wrong/.test( text ), 'Extensions lists the site\'s plugins with their authors', text.slice( 0, 160 ) );
		await page.waitForSelector( '[data-xtab="themes"]', { timeout: 15000 } );
		await page.click( '[data-xtab="themes"]' );
		await page.waitForSelector( '.minn-theme', { timeout: 15000 } );
		const themes = await page.evaluate( () => ( {
			engine: !!( window.MINN && window.MINN.engine ),
			preview: document.querySelectorAll( 'a.minn-theme-preview' ).length,
			cards: document.querySelectorAll( '.minn-theme' ).length,
		} ) );
		ok( themes.engine, 'the boot payload names Minn Engine' );
		ok( themes.cards > 0 && themes.preview === 0, 'theme cards have no Live preview bail-out', JSON.stringify( themes ) );
		text = await view( 'stream' );
		ok( ! /Open Stream/.test( text ), 'Activity Log has no Open Stream bail-out', text.slice( 0, 200 ) );
		text = await view( 'posttypes' );
		ok( /Post Types/.test( text ) && /Minn Engine/.test( text ) && ! /WordPress/.test( text ), 'Structure lists post types managed by Minn Engine', text.slice( 0, 200 ) );
		text = await view( 'system' );
		ok( /Minn Engine/.test( text ) && /healthy/.test( text ) && ! /Something went wrong/.test( text ), 'System renders the engine diagnostics', text.slice( 0, 200 ) );
		ok( ! /one-shot jobs/.test( text ) && ! await page.$( '#minn-sys-tools' ), 'System has no Tools card on the engine' );
		text = await view( 'profile' );
		ok( /Sessions/.test( text ) && /this session/.test( text ), 'the profile lists live sessions', text.slice( -300 ) );
		const gone = await page.evaluate( () => ( {
			w: !! document.querySelector( '#minn-wp-admin-link' ),
			toggles: Array.from( document.querySelectorAll( '.minn-toggle-label' ) ).map( ( e ) => e.textContent.trim() ),
		} ) );
		ok( ! gone.w, 'no WordPress button in the sidebar' );
		ok( ! gone.toggles.some( ( t ) => /default admin|admin bar|toolbar/i.test( t ) ), 'no wp-admin-only switches on the profile', gone.toggles.join( ' | ' ) );
		await page.goto( `${ BASE }/minn-admin/`, { waitUntil: 'domcontentloaded', timeout: 20000 } );
		await page.waitForSelector( '#minn-ver-btn', { timeout: 15000 } );
		page.once( 'dialog', ( d ) => { errors.push( 'alert: ' + d.message() ); d.dismiss(); } );
		await page.click( '#minn-ver-btn' );
		await page.waitForTimeout( 2500 );
		const modal = await page.evaluate( () => { const m = document.querySelector( '.minn-changelog' ); return m ? m.innerText.slice( 0, 120 ) : ''; } );
		ok( /v\d+\.\d+/.test( modal ), 'the version button opens the changelog', modal );
		await page.goto( `${ BASE }/`, { waitUntil: 'load', timeout: 20000 } );
		await page.waitForTimeout( 2500 );
		const bar = await page.evaluate( () => {
			const b = document.querySelector( '#minn-bar' );
			return { present: !! b, edit: !! document.querySelector( '#minn-bar .minn-bar-edit' ), bodyClass: document.body.classList.contains( 'minn-front-bar' ), config: !! window.MINN_BAR };
		} );
		ok( bar.present && bar.bodyClass && bar.config, 'the Minn bar renders on the public site', JSON.stringify( bar ) );
		ok( bar.edit, 'the bar offers Edit for the page being viewed' );
		ok( errors.length === 0, 'no console, page, or REST errors', errors.slice( 0, 8 ).join( '\n      ' ) );
	} finally {
		await browser.close();
	}
	console.log( `\n${ pass } passed, ${ fail } failed` );
	process.exit( fail > 0 ? 1 : 0 );
} )().catch( ( e ) => { console.error( 'FAILED', e.message ); process.exit( 1 ); } );
