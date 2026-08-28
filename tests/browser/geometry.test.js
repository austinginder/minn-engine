// Geometry parity: the engine and the reference must lay out every visible
// element of a page at the same place and size with the same computed
// typography. Markup parity (the dogfood suite) cannot see a missing
// stylesheet rule, a wrong cascade order, or a font that never loaded; a
// rendered box can. Each page is walked top-down in system Chrome and every
// element whose box or font differs is reported; hidden elements (zero-size)
// are skipped, as is the skip link, which sits off-screen.
//
// Env:  MINN_GEOMETRY_ENGINE (default https://dogfood.localhost)
//       MINN_GEOMETRY_REF    (default http://127.0.0.1:8124, the dogfood reference)
//       MINN_GEOMETRY_PATHS  (comma-separated; default the dogfood paths)
// Skips cleanly when the reference is not running.

const { chromium } = require( 'playwright-core' );

// `--dev` measures the engine's own site against its 8123 oracle (the theme suite's pages plus
// the block battery); the default is the dogfood site against 8124.
const DEV = process.argv.includes( '--dev' );
const ENGINE = ( process.env.MINN_GEOMETRY_ENGINE || ( DEV ? 'https://minn-engine.localhost' : 'https://dogfood.localhost' ) ).replace( /\/$/, '' );
const REF = ( process.env.MINN_GEOMETRY_REF || ( DEV ? 'http://127.0.0.1:8123' : 'http://127.0.0.1:8124' ) ).replace( /\/$/, '' );
const DEV_PATHS = '/,/hello-world/,/building-in-the-open/,/sample-page/,/sample-page/docs/,/category/uncategorized/,/tag/engine/,/author/admin/,/2026/08/,/?s=open,/nonexistent/,/zz-block-battery-media/,/zz-block-battery-layout/,/page/2/,/docs/';
const DOGFOOD_PATHS = '/,/page/2/,/about/,/contact/,/case-studies/,/corporate/,/residential/11-fifth-3/,/news/,/news/title-here-like-this/,/nonexistent-page/,/?s=design';
const PATHS = ( process.env.MINN_GEOMETRY_PATHS || ( DEV ? DEV_PATHS : DOGFOOD_PATHS ) ).split( ',' );

async function reachable( url ) {
	try {
		const res = await fetch( url, { signal: AbortSignal.timeout( 5000 ) } );
		return res.ok;
	} catch ( e ) {
		return false;
	}
}

// One row per element: a structural key plus the numbers that matter.
function walk() {
	const rows = [];
	const skip = ( el ) => el.tagName === 'SCRIPT' || el.tagName === 'STYLE' || el.tagName === 'NOSCRIPT' || el.id === 'wp-skip-link';
	const visit = ( el, path ) => {
		for ( const c of el.children ) {
			if ( skip( c ) ) continue;
			const r = c.getBoundingClientRect();
			const s = getComputedStyle( c );
			// The first class names the element; later ones can be script-driven state.
			const classes = typeof c.className === 'string' ? c.className.split( ' ' ).filter( ( x ) => x && ! /^wp-container-|^wp-elements-|--\d+$/.test( x ) ).slice( 0, 1 ).join( '.' ) : '';
			const key = path + '>' + c.tagName.toLowerCase() + ( classes ? '.' + classes : '' );
			if ( r.width > 0 && r.height > 0 ) {
				rows.push( [ key, Math.round( r.x ), Math.round( r.y ), Math.round( r.width ), Math.round( r.height ), s.fontFamily.split( ',' )[ 0 ], s.fontSize, s.fontWeight, s.lineHeight, s.letterSpacing ].join( ' ' ) );
			}
			visit( c, key );
		}
	};
	visit( document.body, '' );
	return rows;
}

( async () => {
	if ( ! ( await reachable( REF + '/' ) ) ) {
		console.log( `geometry suite: reference not running at ${ REF }; skipping` );
		process.exit( 0 );
	}
	const browser = await chromium.launch( {
		channel: 'chrome',
		args: [ '--ignore-certificate-errors', '--disable-http2', '--disable-features=MacAppCodeSignClone' ],
	} );
	let pass = 0;
	let fail = 0;
	console.log( `geometry suite: ${ ENGINE } (engine) vs ${ REF } (reference)` );
	for ( const path of PATHS ) {
		const rows = {};
		for ( const base of [ ENGINE, REF ] ) {
			const page = await browser.newPage( { viewport: { width: 1400, height: 900 }, ignoreHTTPSErrors: true } );
			// The reference serves its font faces from the site's own host, a different origin from
			// its loopback address; fonts need CORS, so the fetch is replayed with the header.
			await page.route( /\.(woff2?|ttf|otf)(\?|$)/, async ( route ) => {
				const response = await route.fetch();
				await route.fulfill( { response, headers: { ...response.headers(), 'access-control-allow-origin': '*' } } );
			} );
			await page.goto( base + path, { waitUntil: 'networkidle' } );
			// Every declared face must be loaded before measuring, or a late web font skews text widths.
			const fonts = await page.evaluate( () => Promise.all( [ ...document.fonts ].map( ( f ) => f.load().then( () => f.family + ':' + f.status, ( e ) => f.family + ':' + e.name ) ) ) );
			if ( fonts.some( ( f ) => ! f.endsWith( ':loaded' ) ) ) {
				console.log( `  note ${ path } on ${ base }: fonts ${ fonts.join( ', ' ) }` );
			}
			rows[ base ] = await page.evaluate( walk );
			await page.close();
		}
		const a = rows[ ENGINE ];
		const b = rows[ REF ];
		let verdict = null;
		if ( a.length !== b.length ) {
			verdict = `${ a.length } vs ${ b.length } visible elements`;
		}
		// The first three differing rows that no differing descendant explains: an ancestor's
		// height usually only echoes a descendant's.
		const differing = [];
		for ( let i = 0; i < Math.min( a.length, b.length ); i++ ) {
			if ( a[ i ] !== b[ i ] ) {
				differing.push( i );
			}
		}
		const keyOf = ( row ) => row.split( ' ' )[ 0 ];
		const diffs = [];
		for ( const i of differing ) {
			const prefix = keyOf( a[ i ] ) + '>';
			if ( differing.some( ( j ) => j > i && keyOf( a[ j ] ).startsWith( prefix ) ) ) {
				continue;
			}
			diffs.push( `\n      engine    ${ a[ i ] }\n      reference ${ b[ i ] }` );
			if ( diffs.length === 3 ) {
				break;
			}
		}
		if ( diffs.length > 0 ) {
			verdict = ( verdict === null ? '' : verdict ) + diffs.join( '' );
		}
		if ( verdict === null ) {
			pass++;
			console.log( `  ok   ${ path } (${ a.length } elements)` );
		} else {
			fail++;
			console.log( `  FAIL ${ path }: ${ verdict }` );
		}
	}
	await browser.close();
	console.log( `\n${ pass } passed, ${ fail } failed` );
	process.exit( fail === 0 ? 0 : 1 );
} )();
