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

module.exports = { pinTheme };
