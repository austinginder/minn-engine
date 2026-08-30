// The dev site runs the Minn site theme; the browser parity suites compare
// twentytwentyfive. Pin it for the process and restore on exit, unless
// run-all.sh already pinned it (MINN_TEST_KEEP_THEME).
const { execSync } = require( 'child_process' );
const path = require( 'path' );

const PUBLIC = path.resolve( __dirname, '..', '..', 'public' );
const wp = ( args ) => execSync( `/opt/homebrew/bin/wp ${ args }`, { cwd: PUBLIC, stdio: [ 'ignore', 'pipe', 'ignore' ] } ).toString().trim();

function pinTheme( slug = 'twentytwentyfive' ) {
	if ( process.env.MINN_TEST_KEEP_THEME ) {
		return;
	}
	const saved = { template: wp( 'option get template' ), stylesheet: wp( 'option get stylesheet' ) };
	if ( saved.template === slug && saved.stylesheet === slug ) {
		return;
	}
	wp( `option update template ${ slug }` );
	wp( `option update stylesheet ${ slug }` );
	const restore = () => {
		try {
			wp( `option update template ${ saved.template }` );
			wp( `option update stylesheet ${ saved.stylesheet }` );
		} catch ( e ) { /* the site keeps the pinned theme; rerun restores it */ }
	};
	process.on( 'exit', restore );
	process.on( 'SIGINT', () => { restore(); process.exit( 130 ); } );
}

// The same for the language: WPLANG and users' locale meta go to en_US for
// the process (the fixtures and prose checks are English), back on exit.
function pinLocale() {
	if ( process.env.MINN_TEST_KEEP_LOCALE ) {
		return;
	}
	const prefix = wp( 'config get table_prefix' ) || 'wp_';
	const site = wp( `db query "SELECT option_value FROM ${ prefix }options WHERE option_name = 'WPLANG'" --skip-column-names` );
	const rows = wp( `db query "SELECT user_id, meta_value FROM ${ prefix }usermeta WHERE meta_key = 'locale' AND meta_value <> ''" --skip-column-names` )
		.split( '\n' ).filter( Boolean ).map( ( r ) => r.split( '\t' ) );
	if ( ! site && rows.length === 0 ) {
		return;
	}
	wp( `db query "UPDATE ${ prefix }usermeta SET meta_value = '' WHERE meta_key = 'locale'"` );
	if ( site ) {
		wp( 'option update WPLANG ""' );
	}
	const restore = () => {
		try {
			if ( site ) {
				wp( `option update WPLANG ${ site }` );
			}
			rows.forEach( ( [ id, locale ] ) => wp( `db query "UPDATE ${ prefix }usermeta SET meta_value = '${ locale }' WHERE meta_key = 'locale' AND user_id = ${ Number( id ) }"` ) );
		} catch ( e ) { /* rerun restores it */ }
	};
	process.on( 'exit', restore );
}

const pinThemeOnly = pinTheme;
module.exports = { pinTheme: ( slug ) => { pinThemeOnly( slug ); pinLocale(); }, pinLocale };
