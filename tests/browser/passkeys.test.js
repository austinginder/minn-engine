// Passkeys on Minn Engine, made and used by Chrome's own WebAuthn stack
// against a virtual authenticator (CDP WebAuthn domain): the bytes the
// engine checks are the bytes a real browser sends. Minn on its own: there
// is no oracle, WordPress has no passkeys.
//
// The fixture extension tests/fixtures/extensions/minn-test-passkeys puts
// REST routes over Seams::accounts()'s passkey methods; the suite makes a
// throwaway author, adds a passkey with the PRF extension asked for, checks
// the browser will not add the same authenticator twice, signs out, signs in
// with the passkey (PRF output included), checks the session is real, that
// an answer cannot be replayed, and that a removed passkey signs nobody in.
// Everything it made is removed at exit.
//
// Run:  node tests/browser/passkeys.test.js
// Env:  MINN_ENGINE_URL (default https://minn.localhost)

const { chromium } = require( 'playwright-core' );
const { execFileSync } = require( 'child_process' );
const crypto = require( 'crypto' );
const fs = require( 'fs' );
const path = require( 'path' );
const { siteDir } = require( './local' );

const BASE = ( process.env.MINN_ENGINE_URL || 'https://minn.localhost' ).replace( /\/$/, '' );
const SITE = siteDir( new URL( BASE ).hostname.replace( /\.localhost$/, '' ) );
const REFERENCE = `${ SITE }/wp-reference`;
const FIXTURE = path.join( __dirname, '..', 'fixtures', 'extensions', 'minn-test-passkeys' );
const LINK = `${ SITE }/public/wp-content/plugins/minn-test-passkeys`;
const CHROME = '/Applications/Google Chrome.app/Contents/MacOS/Google Chrome';
const LOGIN = 'zz-passkey-author';
const PASS = crypto.randomBytes( 18 ).toString( 'base64url' );

let pass = 0, fail = 0;
const ok = ( c, label, detail = '' ) => {
	if ( c ) { pass++; console.log( `  ok  ${ label }` ); }
	else { fail++; console.log( `FAIL  ${ label }${ detail ? '\n      ' + detail : '' }` ); }
};

/** WP-CLI on the reference copy, which shares the test site's database. */
const wp = ( ...args ) => execFileSync( 'wp', args, { cwd: REFERENCE, encoding: 'utf8', stdio: [ 'ignore', 'pipe', 'pipe' ] } ).trim();
const quiet = ( ...args ) => { try { return wp( ...args ); } catch { return ''; } };

const madeLink = ! fs.existsSync( LINK );
const extensionsBefore = quiet( 'option', 'get', 'minn_active_extensions' );

const cleanup = () => {
	const id = quiet( 'user', 'get', LOGIN, '--field=ID' );
	if ( id ) {
		quiet( 'user', 'delete', id, '--yes' );
	}
	for ( const name of quiet( 'option', 'list', '--search=minn_passkey_challenge_*', '--field=option_name' ).split( '\n' ).filter( Boolean ) ) {
		quiet( 'option', 'delete', name );
	}
	if ( extensionsBefore === '' ) {
		quiet( 'option', 'delete', 'minn_active_extensions' );
	} else {
		quiet( 'option', 'update', 'minn_active_extensions', extensionsBefore );
	}
	if ( madeLink && fs.existsSync( LINK ) ) {
		fs.unlinkSync( LINK );
	}
};

( async () => {
	console.log( `passkeys suite: ${ BASE }` );
	cleanup();
	if ( ! fs.existsSync( LINK ) ) {
		fs.symlinkSync( FIXTURE, LINK );
	}
	const active = JSON.parse( extensionsBefore || '[]' );
	wp( 'option', 'update', 'minn_active_extensions', JSON.stringify( [ ...new Set( [ ...active, 'minn-test-passkeys' ] ) ] ) );
	wp( 'user', 'create', LOGIN, `${ LOGIN }@minn-engine.localhost`, '--role=author', `--user_pass=${ PASS }`, '--display_name=Passkey Author' );

	const browser = await chromium.launch( {
		executablePath: CHROME,
		args: [ '--ignore-certificate-errors', '--disable-http2', '--disable-features=MacAppCodeSignClone' ],
	} );
	const context = await browser.newContext( { ignoreHTTPSErrors: true } );
	const page = await context.newPage();
	const errors = [];
	page.on( 'pageerror', ( e ) => errors.push( e.message ) );

	try {
		const cdp = await context.newCDPSession( page );
		await cdp.send( 'WebAuthn.enable' );
		const { authenticatorId } = await cdp.send( 'WebAuthn.addVirtualAuthenticator', { options: {
			protocol: 'ctap2', ctap2Version: 'ctap2_1', transport: 'internal',
			hasResidentKey: true, hasUserVerification: true, isUserVerified: true,
			automaticPresenceSimulation: true, hasPrf: true,
		} } );

		// Sign in with the password, and take the REST nonce Minn Admin boots with.
		await page.goto( `${ BASE }/minn-admin/login`, { waitUntil: 'domcontentloaded' } );
		await page.fill( 'input[name="log"]', LOGIN );
		await page.fill( 'input[name="pwd"]', PASS );
		await Promise.all( [ page.waitForNavigation( { waitUntil: 'domcontentloaded' } ), page.click( 'button[name="wp-submit"]' ) ] );
		const nonce = await page.evaluate( () => window.MINN && window.MINN.nonce );
		ok( typeof nonce === 'string' && nonce.length > 0, 'signed in with a password, nonce in hand' );

		// Make a passkey, asking for PRF as Datapad will.
		const made = await page.evaluate( async ( { nonce } ) => {
			const call = ( method, route, body ) => fetch( `/wp-json/minn-test/v1/${ route }`, { method, headers: { 'Content-Type': 'application/json', 'X-WP-Nonce': nonce }, body: body && JSON.stringify( body ) } ).then( async ( r ) => ( { status: r.status, body: await r.json() } ) );
			const options = ( await call( 'POST', 'passkeys/options' ) ).body;
			const publicKey = PublicKeyCredential.parseCreationOptionsFromJSON( options );
			publicKey.extensions = { prf: {} };
			const credential = await navigator.credentials.create( { publicKey } );
			const added = await call( 'POST', 'passkeys', { credential: credential.toJSON(), label: 'Virtual authenticator' } );
			const list = await call( 'GET', 'passkeys' );
			let again = 'created';
			try {
				const second = PublicKeyCredential.parseCreationOptionsFromJSON( ( await call( 'POST', 'passkeys/options' ) ).body );
				await navigator.credentials.create( { publicKey: second } );
			} catch ( e ) {
				again = e.name;
			}
			return { options, added, list, prf: credential.getClientExtensionResults().prf, again };
		}, { nonce } );
		ok( made.options.rp && made.options.rp.id === new URL( BASE ).hostname, 'options name the site as the relying party', JSON.stringify( made.options.rp ) );
		ok( made.options.authenticatorSelection.userVerification === 'required' && made.options.attestation === 'none', 'options require user verification and ask for no attestation' );
		ok( made.options.user.id !== '' && ! made.options.user.id.includes( LOGIN ), 'the user handle is random, not the login' );
		ok( made.added.status === 201 && made.added.body.label === 'Virtual authenticator' && ! ( 'publicKey' in made.added.body ), 'the passkey is kept and its key is never shown', JSON.stringify( made.added ) );
		ok( made.list.status === 200 && made.list.body.length === 1, 'the caller lists one passkey', JSON.stringify( made.list.body ) );
		ok( made.prf && made.prf.enabled === true, 'the passkey supports PRF (the key Datapad derives)', JSON.stringify( made.prf ) );
		ok( made.again === 'InvalidStateError', 'the browser refuses to add the same authenticator twice', made.again );

		// Sign out, then back in with the passkey.
		await context.clearCookies();
		const salt = crypto.randomBytes( 32 ).toString( 'base64url' );
		const signIn = await page.evaluate( async ( { salt } ) => {
			const post = ( route, body ) => fetch( `/wp-json/minn-test/v1/${ route }`, { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: body && JSON.stringify( body ) } ).then( async ( r ) => ( { status: r.status, body: await r.json() } ) );
			const options = ( await post( 'passkeys/sign-in-options' ) ).body;
			const publicKey = PublicKeyCredential.parseRequestOptionsFromJSON( options );
			const bytes = Uint8Array.from( atob( salt.replace( /-/g, '+' ).replace( /_/g, '/' ) ), ( c ) => c.charCodeAt( 0 ) );
			publicKey.extensions = { prf: { eval: { first: bytes } } };
			const assertion = await navigator.credentials.get( { publicKey } );
			const answer = assertion.toJSON();
			const first = await post( 'passkeys/sign-in', { credential: answer } );
			const replay = await post( 'passkeys/sign-in', { credential: answer } );
			const prf = assertion.getClientExtensionResults().prf;
			return { options, first, replay, prfBytes: prf && prf.results && prf.results.first ? prf.results.first.byteLength : 0 };
		}, { salt } );
		ok( signIn.options.userVerification === 'required' && signIn.options.allowCredentials.length === 0, 'sign-in options name no user (discoverable passkeys)' );
		ok( signIn.first.status === 200 && signIn.first.body.login === LOGIN, 'the passkey signs the author in', JSON.stringify( signIn.first ) );
		ok( signIn.prfBytes === 32, 'the sign-in hands the page 32 bytes of PRF output', String( signIn.prfBytes ) );
		ok( signIn.replay.status === 400 && signIn.replay.body.data && signIn.replay.body.data.reason === 'challenge', 'the same answer replayed is refused', JSON.stringify( signIn.replay ) );

		await page.goto( `${ BASE }/minn-admin/`, { waitUntil: 'domcontentloaded' } );
		const who = await page.evaluate( () => window.MINN && window.MINN.user && window.MINN.user.login );
		ok( who === LOGIN, 'the session the passkey opened is a real one (Minn Admin boots as the author)', String( who ) );
		const nonce2 = await page.evaluate( () => window.MINN && window.MINN.nonce );

		// Remove the passkey; the authenticator still offers it, and the site no longer knows it.
		const after = await page.evaluate( async ( { nonce, id } ) => {
			const removed = await fetch( `/wp-json/minn-test/v1/passkeys/${ id }`, { method: 'DELETE', headers: { 'X-WP-Nonce': nonce } } ).then( ( r ) => r.json() );
			const options = await fetch( '/wp-json/minn-test/v1/passkeys/sign-in-options', { method: 'POST' } ).then( ( r ) => r.json() );
			const assertion = await navigator.credentials.get( { publicKey: PublicKeyCredential.parseRequestOptionsFromJSON( options ) } );
			const answer = await fetch( '/wp-json/minn-test/v1/passkeys/sign-in', { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify( { credential: assertion.toJSON() } ) } ).then( async ( r ) => ( { status: r.status, body: await r.json() } ) );
			return { removed, answer };
		}, { nonce: nonce2, id: made.added.body.id } );
		ok( after.removed.removed === true, 'the author removes the passkey' );
		ok( after.answer.status === 400 && after.answer.body.data.reason === 'unknown', 'a removed passkey signs nobody in', JSON.stringify( after.answer ) );

		await cdp.send( 'WebAuthn.removeVirtualAuthenticator', { authenticatorId } );
		ok( errors.length === 0, 'no page errors', errors.join( '; ' ) );
	} catch ( e ) {
		ok( false, 'suite ran to the end', e.stack || String( e ) );
	} finally {
		await browser.close();
		cleanup();
	}
	console.log( `\n${ pass } passed, ${ fail } failed` );
	process.exit( fail === 0 ? 0 : 1 );
} )();
