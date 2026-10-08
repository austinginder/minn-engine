// Local facts the browser suites need that stay off the public repository:
// tests/local.json (gitignored), else tests/local.example.json. The keys and
// what they mean are in tests/local.php.
const fs = require( 'fs' );
const os = require( 'os' );
const path = require( 'path' );

const file = path.join( __dirname, '..', 'local.json' );
const values = JSON.parse( fs.readFileSync( fs.existsSync( file ) ? file : path.join( __dirname, '..', 'local.example.json' ), 'utf8' ) );

/** One value from the local file, as a string. */
const local = ( key ) => String( values[ key ] ?? '' );

/** The folder Cove keeps its sites in. */
const sitesDir = ( process.env.MINN_SITES_DIR || local( 'sites' ) || path.join( os.homedir(), 'Cove', 'Sites' ) ).replace( /\/$/, '' );

/** A Cove site's root folder by its name (the part before .localhost). */
const siteDir = ( name ) => `${ sitesDir }/${ name }.localhost`;

module.exports = { local, sitesDir, siteDir };
