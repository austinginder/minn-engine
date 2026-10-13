/**
 * Embed card suite: a post's embed card (/<post>/embed/) laid out on the
 * engine and on the reference, element by element. Three cards (a plain post
 * with its excerpt and a comment, one with a wide featured image above the
 * title, one with a square image beside the excerpt) are each loaded in
 * frames of 600x338 and 300x200 and on their own at 1000px wide, with the
 * sharing dialog closed, open, and without scripts; then the links and
 * buttons are hovered and focused one at a time. Each state compares every
 * element's box (to 1px) and the computed styles that show. The icons are
 * compared by box and by the colour they paint, never by their artwork:
 * background-image, content and mask are not read. The dialog is opened by
 * the suite itself (its hidden class), so no card script is needed.
 * The reference is read at its own host (wp.<site>.localhost). The suite
 * makes its posts, images and comment and removes them.
 *
 *   node tests/browser/embed-card.test.js
 */
const { execSync, execFileSync } = require( 'child_process' );
const fs = require( 'fs' );
const os = require( 'os' );
const path = require( 'path' );
const { chromium } = require( 'playwright-core' );
const { siteDir } = require( './local' );

const CHROME = '/Applications/Google Chrome.app/Contents/MacOS/Google Chrome';
const SITE = 'minn';
const stacks = { engine: `https://${ SITE }.localhost`, reference: `https://wp.${ SITE }.localhost` };
const WP = `wp --path=${ siteDir( SITE ) }/wp-reference`;
const SLUG = 'zz-embed-card-suite';

let pass = 0;
let fail = 0;
function check( cond, label, detail = '' ) {
	if ( cond ) { pass++; console.log( `  ok  ${ label }` ); } else { fail++; console.log( `FAIL  ${ label }${ detail ? `\n      ${ detail }` : '' }` ); }
}
const wp = ( command ) => execSync( `${ WP } ${ command } 2>/dev/null`, { encoding: 'utf8' } ).trim();

// The computed styles compared: what shows of each element. Never background-image, content or mask.
// Colour comes first: the border, decoration and outline colours that follow it from currentColor then read as echoes.
const PROPS = [
	'display', 'visibility', 'position', 'top', 'right', 'bottom', 'left', 'float', 'clear', 'z-index', 'transform',
	'color', 'background-color', 'background-size', 'background-position', 'background-repeat', 'box-shadow',
	'box-sizing', 'width', 'height', 'min-width', 'max-width', 'min-height', 'max-height',
	'margin-top', 'margin-right', 'margin-bottom', 'margin-left', 'padding-top', 'padding-right', 'padding-bottom', 'padding-left',
	...[ 'top', 'right', 'bottom', 'left' ].flatMap( ( side ) => [ 'width', 'style', 'color' ].map( ( part ) => `border-${ side }-${ part }` ) ),
	'border-top-left-radius', 'border-top-right-radius', 'border-bottom-right-radius', 'border-bottom-left-radius',
	'font-family', 'font-size', 'font-weight', 'font-style', 'line-height', 'letter-spacing',
	'text-decoration-line', 'text-decoration-style', 'text-decoration-color', 'text-align', 'vertical-align', 'white-space',
	'opacity', 'overflow-x', 'overflow-y', 'cursor', 'outline-style', 'outline-width', 'outline-color', 'outline-offset',
	'list-style-type', 'resize', 'clip-path', 'transition-property', 'transition-duration', 'transition-timing-function',
];

// Every element of the page that can render: a key from its tags, first classes and places, its box and styles.
// Scripts, styles and the stylesheet links a page prints late are left out (they have no box); elements are named by
// tag and class only, and no text is read.
function walk( props ) {
	const rows = [];
	const read = ( el, key ) => {
		const r = el.getBoundingClientRect();
		const s = getComputedStyle( el );
		rows.push( { key, box: [ r.x, r.y, r.width, r.height ], style: props.map( ( p ) => s.getPropertyValue( p ) ) } );
	};
	const visit = ( el, key ) => {
		read( el, key );
		let n = 0;
		for ( const child of el.children ) {
			if ( [ 'SCRIPT', 'STYLE', 'LINK', 'META', 'NOSCRIPT', 'TEMPLATE' ].includes( child.tagName ) ) continue;
			const cls = typeof child.className === 'string' ? child.className.split( ' ' ).filter( ( c ) => c && ! /^post-\d+$/.test( c ) )[ 0 ] : '';
			visit( child, `${ key } > ${ child.tagName.toLowerCase() }${ cls ? '.' + cls : '' }[${ n++ }]` );
		}
	};
	read( document.documentElement, 'html' );
	visit( document.body, 'body' );
	return rows;
}

// Two computed values match when equal, or when they differ only in pixel lengths by under a pixel.
function same( a, b ) {
	if ( a === b ) return true;
	const px = /-?\d+(\.\d+)?px/g;
	if ( a.replace( px, 'px' ) !== b.replace( px, 'px' ) ) return false;
	const x = a.match( px ) || [];
	const y = b.match( px ) || [];
	return x.length === y.length && x.every( ( v, i ) => Math.abs( parseFloat( v ) - parseFloat( y[ i ] ) ) <= 1 );
}

// The differences between two walks, each naming the element, the property and both values.
function compare( engine, reference ) {
	const out = [];
	const name = ( key ) => key.split( ' > ' ).slice( -3 ).join( ' > ' );
	const byKey = new Map( reference.map( ( row ) => [ row.key, row ] ) );
	const seen = new Set();
	for ( const row of engine ) {
		const ref = byKey.get( row.key );
		if ( ! ref ) {
			out.push( `${ name( row.key ) }: only on the engine` );
			continue;
		}
		seen.add( row.key );
		const [ ex, ey, ew, eh ] = row.box;
		const [ rx, ry, rw, rh ] = ref.box;
		if ( [ ex - rx, ey - ry, ew - rw, eh - rh ].some( ( d ) => Math.abs( d ) > 1 ) ) {
			const fmt = ( b ) => b.map( ( v ) => Math.round( v * 100 ) / 100 ).join( ',' );
			out.push( `${ name( row.key ) } box (x,y,w,h): engine ${ fmt( row.box ) }, reference ${ fmt( ref.box ) }` );
		}
		PROPS.forEach( ( p, i ) => {
			if ( ! same( row.style[ i ], ref.style[ i ] ) ) {
				out.push( `${ name( row.key ) } ${ p }: engine ${ row.style[ i ] }, reference ${ ref.style[ i ] }` );
			}
		} );
	}
	for ( const row of reference ) {
		if ( ! seen.has( row.key ) ) out.push( `${ name( row.key ) }: only on the reference` );
	}
	return out;
}

// The colour an icon paints: the most common colour among the pixels that change when its background is taken away.
async function iconColour( page, helper, frame, selector ) {
	const el = frame.locator( selector ).first();
	await el.scrollIntoViewIfNeeded();
	const box = await el.boundingBox();
	if ( ! box ) return null;
	const clip = { x: box.x, y: box.y, width: Math.max( 1, box.width ), height: Math.max( 1, box.height ) };
	const shot = async () => ( await page.screenshot( { clip } ) ).toString( 'base64' );
	const withIcon = await shot();
	await el.evaluate( ( node ) => {
		node.style.setProperty( 'transition', 'none', 'important' );
		node.style.setProperty( 'background-image', 'none', 'important' );
	} );
	await page.waitForTimeout( 50 );
	const without = await shot();
	await el.evaluate( ( node ) => {
		node.style.removeProperty( 'background-image' );
		node.style.removeProperty( 'transition' );
	} );
	return helper.evaluate( async ( [ a, b ] ) => {
		const pixels = async ( b64 ) => {
			const img = new Image();
			img.src = 'data:image/png;base64,' + b64;
			await img.decode();
			const canvas = document.createElement( 'canvas' );
			canvas.width = img.width;
			canvas.height = img.height;
			const ctx = canvas.getContext( '2d' );
			ctx.drawImage( img, 0, 0 );
			return ctx.getImageData( 0, 0, img.width, img.height ).data;
		};
		const [ p, q ] = [ await pixels( a ), await pixels( b ) ];
		const counts = new Map();
		for ( let i = 0; i < p.length; i += 4 ) {
			if ( p[ i ] !== q[ i ] || p[ i + 1 ] !== q[ i + 1 ] || p[ i + 2 ] !== q[ i + 2 ] ) {
				const c = `${ p[ i ] },${ p[ i + 1 ] },${ p[ i + 2 ] }`;
				counts.set( c, ( counts.get( c ) || 0 ) + 1 );
			}
		}
		const top = [ ...counts ].sort( ( x, y ) => y[ 1 ] - x[ 1 ] )[ 0 ];
		return top ? top[ 0 ] : 'nothing painted';
	}, [ withIcon, without ] );
}

const nearColour = ( a, b ) => {
	const x = String( a ).split( ',' ).map( Number );
	const y = String( b ).split( ',' ).map( Number );
	return x.length === 3 && y.length === 3 && x.every( ( v, i ) => Math.abs( v - y[ i ] ) <= 8 );
};

// Generated images: a wide one (shown above the title) and a square one (beside the excerpt).
function image( file, width, height ) {
	execFileSync( 'php', [ '-r', `
		[ , $file, $w, $h ] = $argv;
		$im = imagecreatetruecolor( (int) $w, (int) $h );
		imagefill( $im, 0, 0, imagecolorallocate( $im, 34, 113, 177 ) );
		imagefilledellipse( $im, (int) ( $w / 2 ), (int) ( $h / 2 ), (int) ( $h * 0.6 ), (int) ( $h * 0.6 ), imagecolorallocate( $im, 240, 200, 80 ) );
		imagepng( $im, $file );
	`, file, String( width ), String( height ) ] );
}

// Long enough that the card trims it and adds its "Continue reading" link.
const LONG = 'This card shows the start of a longer post. The post goes on well past what a card can hold, so the card trims it to its first words and links to the rest of it. '
	+ 'The excerpt wraps over several lines in a narrow frame and over fewer in a wide one, and it ends with a link to keep reading the post on its own site, '
	+ 'which is where the rest of these words are, every one of them, from the first line of the post to the last line of the post.';

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
		console.log( `embed card suite: ${ Object.values( stacks ).join( ' or ' ) } not answering; skipping` );
		process.exit( 0 );
	}
	console.log( `embed card suite: ${ stacks.engine } (engine) / ${ stacks.reference } (reference)` );
	const made = [];
	const temp = [];
	let browser;
	try {
		// The posts: made once, as both stacks share the database.
		const post = ( slug, title, args ) => {
			const file = path.join( os.tmpdir(), `minn-embed-card-${ process.pid }-${ slug }.html` );
			fs.writeFileSync( file, `<!-- wp:paragraph --><p>${ LONG }</p><!-- /wp:paragraph -->` );
			temp.push( file );
			const id = parseInt( wp( `post create ${ file } --post_type=post --post_status=publish --post_title="${ title }" --post_name=${ SLUG }-${ slug } ${ args } --porcelain` ), 10 );
			if ( id > 1 ) made.push( [ 'post', id ] );
			return id;
		};
		const featured = ( postId, name, width, height ) => {
			const file = path.join( os.tmpdir(), `${ SLUG }-${ name }-${ process.pid }.png` );
			image( file, width, height );
			temp.push( file );
			const id = parseInt( wp( `media import ${ file } --post_id=${ postId } --featured_image --title="ZZ Embed Card Suite ${ name }" --porcelain` ), 10 );
			if ( id > 1 ) made.push( [ 'post', id ] );
			return id;
		};
		const plain = post( 'plain', 'ZZ Embed Card Plain', '' );
		const comment = parseInt( wp( `comment create --comment_post_ID=${ plain } --comment_content="A comment on the card." --comment_author="ZZ Suite" --comment_approved=1 --porcelain` ), 10 );
		if ( comment > 0 ) made.push( [ 'comment', comment ] );
		const wide = post( 'wide', 'ZZ Embed Card Wide Image', '--post_excerpt="A wide featured image shows above the title of the card."' );
		const wideImage = featured( wide, 'wide', 1200, 500 );
		const square = post( 'square', 'ZZ Embed Card Square Image', '' );
		const squareImage = featured( square, 'square', 600, 600 );
		check( plain > 1 && comment > 0 && wide > 1 && wideImage > 1 && square > 1 && squareImage > 1, 'the three posts, their images and the comment are in place' );

		const cards = [
			{ name: 'plain card', slug: `${ SLUG }-plain`, shape: null },
			{ name: 'wide image card', slug: `${ SLUG }-wide`, shape: 'rectangular' },
			{ name: 'square image card', slug: `${ SLUG }-square`, shape: 'square' },
		];
		const views = [
			{ name: '600x338 frame', frame: { width: 600, height: 338 } },
			{ name: '300x200 frame', frame: { width: 300, height: 200 } },
			{ name: '1000px page', page: { width: 1000, height: 700 } },
		];
		// What is hovered and focused, with the dialog closed and then open.
		const closedTargets = [
			[ 'the heading link', '.wp-embed-heading a' ],
			[ 'the featured image link', '.wp-embed-featured-image a' ],
			[ 'the "Continue reading" link', '.wp-embed-more' ],
			[ 'the site title link', '.wp-embed-site-title a' ],
			[ 'the comments link', '.wp-embed-comments a' ],
			[ 'the share button', '.wp-embed-share-dialog-open' ],
		];
		const openTargets = [
			[ 'the selected tab', '.wp-embed-share-tab-button [aria-selected="true"]', true ],
			[ 'the other tab', '.wp-embed-share-tab-button [aria-selected="false"]', false ],
			[ 'the URL input', '.wp-embed-share-tab:not([aria-hidden="true"]) .wp-embed-share-input', true ],
			[ 'the close button', '.wp-embed-share-dialog-close', true ],
		];
		const htmlTabTargets = [
			[ 'the other tab', '.wp-embed-share-tab-button [aria-selected="false"]', false ],
			[ 'the HTML input', '.wp-embed-share-tab:not([aria-hidden="true"]) .wp-embed-share-input', true ],
		];
		const icons = [
			[ 'the comments icon', '.wp-embed-comments .dashicons', '.wp-embed-comments a' ],
			[ 'the share icon', '.wp-embed-share .dashicons', '.wp-embed-share-dialog-open' ],
		];

		browser = await chromium.launch( { executablePath: CHROME, args: [ '--ignore-certificate-errors', '--disable-http2', '--disable-features=MacAppCodeSignClone' ] } );
		const helperContext = await browser.newContext();
		const helper = await helperContext.newPage();
		for ( const card of cards ) {
			for ( const view of views ) {
				// Both stacks at once, each in its own browser context.
				const run = async ( base ) => {
					const context = await browser.newContext( { ignoreHTTPSErrors: true, viewport: view.page || { width: 1000, height: 700 } } );
					const page = await context.newPage();
					const url = `${ base }/${ card.slug }/embed/`;
					let frame;
					if ( view.frame ) {
						await page.setContent( `<!DOCTYPE html><body style="margin:0"><iframe src="${ url }" width="${ view.frame.width }" height="${ view.frame.height }" style="border:0;display:block" title="card"></iframe></body>` );
						frame = await ( await page.$( 'iframe' ) ).contentFrame();
						await frame.waitForLoadState( 'load' );
					} else {
						await page.goto( url, { waitUntil: 'load' } );
						frame = page.mainFrame();
					}
					await frame.evaluate( () => document.fonts.ready.then( () => Promise.all( [ ...document.images ].map( ( i ) => i.decode().catch( () => null ) ) ) ) );
					// Where the featured image shows: its shape, and whether it comes before the title.
					const shape = await frame.evaluate( () => {
						const image = document.querySelector( '.wp-embed-featured-image' );
						if ( ! image ) return null;
						const kind = [ 'rectangular', 'square' ].find( ( c ) => image.classList.contains( c ) ) || 'other';
						const above = !! ( image.compareDocumentPosition( document.querySelector( '.wp-embed-heading' ) ) & Node.DOCUMENT_POSITION_FOLLOWING );
						return `${ kind } ${ above ? 'above' : 'below' } the title`;
					} );
					// Whatever the card script did or did not do to the root, both stacks start from the same classes.
					const root = ( cls ) => frame.evaluate( ( c ) => { document.documentElement.className = c; }, cls );
					const dialog = ( open ) => frame.evaluate( ( o ) => document.querySelector( '.wp-embed-share-dialog' ).classList.toggle( 'hidden', ! o ), open );
					// The dialog's tabs, switched by their attributes as the card script would.
					const showTab = ( kind ) => frame.evaluate( ( k ) => {
						const mine = ( id ) => ( id || '' ).startsWith( `wp-embed-share-tab-${ k }-` );
						for ( const button of document.querySelectorAll( '.wp-embed-share-tab-button button' ) ) {
							button.setAttribute( 'aria-selected', mine( button.getAttribute( 'aria-controls' ) ) ? 'true' : 'false' );
							button.setAttribute( 'tabindex', mine( button.getAttribute( 'aria-controls' ) ) ? '0' : '-1' );
						}
						for ( const panel of document.querySelectorAll( '.wp-embed-share-tab' ) ) {
							panel.setAttribute( 'aria-hidden', mine( panel.id ) ? 'false' : 'true' );
						}
					}, kind );
					const rest = async () => {
						await page.mouse.move( 0, 0 );
						await frame.evaluate( () => { if ( document.activeElement ) document.activeElement.blur(); window.scrollTo( 0, 0 ); } );
						await page.waitForTimeout( 200 );
					};
					await root( 'js' );
					const log = [];
					const snap = async ( label ) => log.push( [ label, await frame.evaluate( walk, PROPS ) ] );
					await rest();
					await snap( 'dialog closed' );
					for ( const [ what, selector ] of closedTargets ) {
						if ( ! ( await frame.$( selector ) ) ) continue;
						await frame.locator( selector ).first().hover();
						await page.waitForTimeout( 200 );
						await snap( `hover on ${ what }` );
						await rest();
						await frame.locator( selector ).first().focus();
						await page.waitForTimeout( 200 );
						await snap( `focus on ${ what }` );
						await rest();
					}
					const colours = [];
					for ( const [ what, selector, trigger ] of icons ) {
						colours.push( [ `${ what }'s colour`, await iconColour( page, helper, frame, selector ) ] );
						await frame.locator( trigger ).first().hover();
						await page.waitForTimeout( 200 );
						colours.push( [ `${ what }'s colour on hover`, await iconColour( page, helper, frame, selector ) ] );
						await rest();
					}
					const openStates = async ( where, targets ) => {
						for ( const [ what, selector, focusable ] of targets ) {
							await frame.locator( selector ).first().hover();
							await page.waitForTimeout( 200 );
							await snap( `${ where }, hover on ${ what }` );
							await page.mouse.move( 0, 0 );
							await page.waitForTimeout( 200 );
							if ( focusable ) {
								await frame.locator( selector ).first().focus();
								await page.waitForTimeout( 200 );
								await snap( `${ where }, focus on ${ what }` );
								await frame.evaluate( () => document.activeElement && document.activeElement.blur() );
							}
						}
					};
					await dialog( true );
					await page.waitForTimeout( 400 );
					await snap( 'dialog open' );
					await openStates( 'dialog open', openTargets );
					colours.push( [ 'the close icon\'s colour', await iconColour( page, helper, frame, '.wp-embed-share-dialog-close .dashicons' ) ] );
					await showTab( 'html' );
					await page.waitForTimeout( 200 );
					await snap( 'dialog open on the HTML tab' );
					await openStates( 'dialog open on the HTML tab', htmlTabTargets );
					await showTab( 'wordpress' );
					await dialog( false );
					await root( 'no-js' );
					await rest();
					// The dialog fades out over a quarter second.
					await page.waitForTimeout( 300 );
					await snap( 'without scripts' );
					await context.close();
					return { log, colours, shape };
				};
				const [ engineRun, referenceRun ] = await Promise.all( [ run( stacks.engine ), run( stacks.reference ) ] );
				const runs = { engine: engineRun, reference: referenceRun };
				const prefix = `${ card.name }, ${ view.name }`;
				if ( view === views[ 0 ] ) {
					const want = card.shape && `${ card.shape } ${ card.shape === 'square' ? 'below' : 'above' } the title`;
					check( runs.engine.shape === want && runs.reference.shape === want, `${ card.name }: ${ want ? `its image shows ${ want }` : 'no featured image' } on both stacks`, `engine ${ runs.engine.shape }, reference ${ runs.reference.shape }` );
				}
				const reference = new Map( runs.reference.log );
				for ( const [ label, rows ] of runs.engine.log ) {
					const ref = reference.get( label );
					const diffs = ref ? compare( rows, ref ) : [ 'no such state on the reference' ];
					const shown = diffs.slice( 0, 10 ).concat( diffs.length > 10 ? [ `... and ${ diffs.length - 10 } more` ] : [] );
					check( diffs.length === 0, `${ prefix }, ${ label } (${ rows.length } elements)`, shown.join( '\n      ' ) );
				}
				if ( runs.engine.log.length !== runs.reference.log.length ) {
					check( false, `${ prefix }: the same states on both stacks`, `engine ${ runs.engine.log.length }, reference ${ runs.reference.log.length }` );
				}
				const refColours = new Map( runs.reference.colours );
				for ( const [ label, colour ] of runs.engine.colours ) {
					const ref = refColours.get( label );
					check( nearColour( colour, ref ), `${ prefix }, ${ label } (rgb(${ ref }))`, `engine rgb(${ colour }), reference rgb(${ ref })` );
				}
			}
		}
		await helperContext.close();
	} catch ( e ) {
		// Only the message's first line, and no element previews: they could carry the dialog's embed code.
		check( false, 'the suite ran to its end', String( e.message ).split( '\n' )[ 0 ].replace( /<[\s\S]*/, '' ) );
	} finally {
		if ( browser ) await browser.close();
		for ( const file of temp ) {
			try {
				fs.unlinkSync( file );
			} catch ( e ) {}
		}
		for ( const [ type, id ] of made.reverse() ) {
			try {
				if ( type === 'comment' && id > 0 ) wp( `comment delete ${ id } --force` );
				if ( type === 'post' && id > 1 ) wp( `post delete ${ id } --force` );
			} catch ( e ) {
				console.log( `  could not delete ${ type } ${ id }` );
			}
		}
	}
	console.log( `\n${ pass } passed, ${ fail } failed` );
	process.exit( fail === 0 ? 0 : 1 );
} )();
