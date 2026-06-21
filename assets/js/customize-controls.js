/*
 * ktube/customize-controls.js — Customizer live preview for ktube-main.
 *
 * Phase 0-A (2026-06-21): hand-shipped, no bundler. Round-4 lifts the
 * functions out of the prior IIFE wrapper so `export { ... }` is valid
 * under Node ESM (which rejects `export` inside any function body).
 *
 * Browser-side: top-level functions attached via <script> (no
 * `type="module"`) historically leak onto window — that's a fact of
 * classic-script semantics, unchanged by this rewrite. The Customizer
 * code path reads `window.ktubeCustomizer.*` for runtime introspection
 * to keep the preview-iframe wire consistent.
 *
 * Responsibilities:
 *   1. wp.customize live preview that rewrites
 *      <style id="ktube-main-inline-css"> on every change so on-the-fly CSS
 *      matches what's committed in includes/customizer.php.
 *   2. WCAG contrast badge per color control.
 *   3. Phase 7b CSS checksum guard: server-emitted per-key SHA-256 must
 *      hash identically against the JS rebuild or console.warn fires.
 *   4. Theme default (auto/light/dark) flips <html data-theme="…"> in the
 *      preview iframe so dark-mode.test assertions stay green.
 */

const KTUBE_CP_STYLE_ID = 'ktube-main-inline-css';

const KTUBE_CP_GRID_KEYS = [
	'ktube_grid_cols_desktop',
	'ktube_grid_cols_tablet',
	'ktube_grid_cols_mobile',
];
const KTUBE_CP_THUMB_KEYS = [
	'ktube_thumb_cols_desktop',
	'ktube_thumb_cols_mobile',
];
const KTUBE_CP_TOKEN_KEYS = [
	'ktube_color_bg_light',
	'ktube_color_text_light',
	'ktube_color_accent_light',
	'ktube_color_link_light',
	'ktube_color_bg_dark',
	'ktube_color_text_dark',
	'ktube_color_accent_dark',
	'ktube_color_link_dark',
];

const KTUBE_CP_SECTIONS = {
	'ktube_color_text_light':   [ 'ktube_color_bg_light' ],
	'ktube_color_accent_light': [ 'ktube_color_bg_light' ],
	'ktube_color_link_light':   [ 'ktube_color_bg_light', 'ktube_color_text_light' ],
	'ktube_color_text_dark':    [ 'ktube_color_bg_dark' ],
	'ktube_color_accent_dark':  [ 'ktube_color_bg_dark' ],
	'ktube_color_link_dark':    [ 'ktube_color_bg_dark',  'ktube_color_text_dark' ],
};
const KTUBE_CP_COLOR_BLOCK_FOR = {
	'ktube_color_bg_light':     'light',
	'ktube_color_text_light':   'light',
	'ktube_color_accent_light': 'light',
	'ktube_color_link_light':   'light',
	'ktube_color_bg_dark':      'dark',
	'ktube_color_text_dark':    'dark',
	'ktube_color_accent_dark':  'dark',
	'ktube_color_link_dark':    'dark',
};
const KTUBE_CP_COLOR_VAR_NAME = {
	'ktube_color_bg_light':     '--ktube-color-bg',
	'ktube_color_text_light':   '--ktube-color-text',
	'ktube_color_accent_light': '--ktube-color-accent',
	'ktube_color_link_light':   '--ktube-color-link',
	'ktube_color_bg_dark':      '--ktube-color-bg',
	'ktube_color_text_dark':    '--ktube-color-text',
	'ktube_color_accent_dark':  '--ktube-color-accent',
	'ktube_color_link_dark':    '--ktube-color-link',
};
const KTUBE_CP_BLOCK_SELECTOR = {
	light: ':root[data-theme="light"], :root:not([data-theme])',
	dark:  ':root[data-theme="dark"]',
};

function ktubeCpLin( c ) {
	const v = c / 255;
	return v <= 0.04045 ? v / 12.92 : Math.pow( ( v + 0.055 ) / 1.055, 2.4 );
}
function ktubeCpHex( h ) {
	const v = ( h || '' ).trim();
	if ( ! /^#([a-f0-9]{6})$/i.test( v ) ) {
		return { r: 255, g: 255, b: 255 };
	}
	return {
		r: parseInt( v.slice( 1, 3 ), 16 ),
		g: parseInt( v.slice( 3, 5 ), 16 ),
		b: parseInt( v.slice( 5, 7 ), 16 ),
	};
}
function ktubeCpContrast( a, b ) {
	const A = ktubeCpHex( a );
	const B = ktubeCpHex( b );
	const L1 = 0.2126 * ktubeCpLin( A.r ) + 0.7152 * ktubeCpLin( A.g ) + 0.0722 * ktubeCpLin( A.b );
	const L2 = 0.2126 * ktubeCpLin( B.r ) + 0.7152 * ktubeCpLin( B.g ) + 0.0722 * ktubeCpLin( B.b );
	const hi = L1 >= L2 ? L1 : L2;
	const lo = L1 >= L2 ? L2 : L1;
	return ( hi + 0.05 ) / ( lo + 0.05 );
}
function ktubeCpLiveStyleEl() {
	const doc = ( wp.customize.previewer.container[ 0 ] && wp.customize.previewer.container[ 0 ].contentDocument ) || document;
	let el = doc.getElementById( KTUBE_CP_STYLE_ID );
	if ( ! el ) {
		el = doc.createElement( 'style' );
		el.id = KTUBE_CP_STYLE_ID;
		( doc.head || doc.documentElement ).appendChild( el );
	}
	return el;
}

function ktubeCpSha256Hex( text ) {
	return crypto.subtle.digest( 'SHA-256', new TextEncoder().encode( text ) ).then( function ( buf ) {
		return Array.prototype.map.call( new Uint8Array( buf ), function ( b ) {
			return b.toString( 16 ).padStart( 2, '0' );
		} ).join( '' );
	} );
}

function ktubeCpReadMirrorValue( id ) {
	if ( typeof wp !== 'undefined' && wp.customize ) {
		return wp.customize( id ).get();
	}
	return undefined;
}

function ktubeCpMirrorSubstring( id, value, css ) {
	switch ( id ) {
		case 'ktube_grid_cols_desktop':  return '--ktube-grid-cols-desktop:' + value + ';';
		case 'ktube_grid_cols_tablet':   return '--ktube-grid-cols-tablet:'  + value + ';';
		case 'ktube_grid_cols_mobile':   return '--ktube-grid-cols-mobile:'  + value + ';';
		case 'ktube_thumb_cols_desktop': return '--ktube-thumb-cols-desktop:' + value + ';';
		case 'ktube_thumb_cols_mobile':  return '--ktube-thumb-cols-mobile:'  + value + ';';
	}
	if ( KTUBE_CP_COLOR_VAR_NAME[ id ] ) {
		const block     = KTUBE_CP_COLOR_BLOCK_FOR[ id ];
		const blockSel  = KTUBE_CP_BLOCK_SELECTOR[ block ].replace( /[.*+?^${}()|[\]\\]/g, '\\$&' );
		const blockMatch = css.match( new RegExp( blockSel + '\\s*\\{([^}]*)\\}' ) );
		if ( ! blockMatch ) {
			return '';
		}
		const varNameEsc = KTUBE_CP_COLOR_VAR_NAME[ id ].replace( /[.*+?^${}()|[\]\\]/g, '\\$&' );
		const varMatch   = blockMatch[ 1 ].match( new RegExp( '(' + varNameEsc + ':)([^;]+);' ) );
		if ( ! varMatch ) {
			return '';
		}
		return varMatch[ 0 ] + '|@' + block;
	}
	return '';
}

function ktubeCpBuildCss() {
	const gridD  = wp.customize( 'ktube_grid_cols_desktop'  ).get() || 4;
	const gridT  = wp.customize( 'ktube_grid_cols_tablet'   ).get() || 3;
	const gridM  = wp.customize( 'ktube_grid_cols_mobile'   ).get() || 2;
	const thumbD = wp.customize( 'ktube_thumb_cols_desktop' ).get() || 3;
	const thumbM = wp.customize( 'ktube_thumb_cols_mobile'  ).get() || 2;
	const bgL    = wp.customize( 'ktube_color_bg_light'     ).get();
	const txL    = wp.customize( 'ktube_color_text_light'   ).get();
	const acL    = wp.customize( 'ktube_color_accent_light' ).get();
	const lkL    = wp.customize( 'ktube_color_link_light'   ).get();
	const bgD    = wp.customize( 'ktube_color_bg_dark'      ).get();
	const txD    = wp.customize( 'ktube_color_text_dark'    ).get();
	const acD    = wp.customize( 'ktube_color_accent_dark'  ).get();
	const lkD    = wp.customize( 'ktube_color_link_dark'    ).get();
	return (
		':root {' +
			'--ktube-grid-cols-desktop:' + gridD  + ';' +
			'--ktube-grid-cols-tablet:'  + gridT  + ';' +
			'--ktube-grid-cols-mobile:'  + gridM  + ';' +
			'--ktube-thumb-cols-desktop:' + thumbD + ';' +
			'--ktube-thumb-cols-mobile:'  + thumbM + ';' +
		'}' +
		':root[data-theme="light"], :root:not([data-theme]) {' +
			'--ktube-color-bg:'     + bgL + ';' +
			'--ktube-color-text:'   + txL + ';' +
			'--ktube-color-accent:' + acL + ';' +
			'--ktube-color-link:'   + lkL + ';' +
		'}' +
		':root[data-theme="dark"] {' +
			'--ktube-color-bg:'     + bgD + ';' +
			'--ktube-color-text:'   + txD + ';' +
			'--ktube-color-accent:' + acD + ';' +
			'--ktube-color-link:'   + lkD + ';' +
		'}' +
		'@media (min-width: 641px) and (max-width: 1024px) {' +
			':root {' +
				'--ktube-grid-cols-desktop: var(--ktube-grid-cols-tablet);' +
				'--ktube-thumb-cols: var(--ktube-thumb-cols-desktop);' +
			'}' +
		'}' +
		'@media (max-width: 640px) {' +
			':root {' +
				'--ktube-grid-cols-desktop: var(--ktube-grid-cols-mobile);' +
				'--ktube-thumb-cols: var(--ktube-thumb-cols-mobile);' +
			'}' +
		'}'
	);
}

function ktubeCpPaint() {
	ktubeCpLiveStyleEl().textContent = ktubeCpBuildCss();
}

function ktubeCpVerifyChecksum() {
	const expected = window.ktubeCustomizerSettingChecksums;
	if ( ! expected || typeof expected.total !== 'string' ) {
		return Promise.resolve();
	}
	const css     = ktubeCpBuildCss();
	const ids     = KTUBE_CP_GRID_KEYS.concat( KTUBE_CP_THUMB_KEYS ).concat( KTUBE_CP_TOKEN_KEYS );
	const substrs = ids.map( function ( id ) { return ktubeCpMirrorSubstring( id, ktubeCpReadMirrorValue( id ), css ); } );
	return Promise.all( [ ktubeCpSha256Hex( css ) ].concat( substrs.map( function ( s ) { return ktubeCpSha256Hex( s ); } ) ) ).then( function ( digests ) {
		const totalActual = digests[ 0 ];
		if ( totalActual === expected.total ) {
			return;
		}
		const drifts = [];
		for ( let i = 0; i < ids.length; i++ ) {
			if ( digests[ i + 1 ] !== ( expected.per_key || {} )[ ids[ i ] ] ) {
				drifts.push( ids[ i ] );
			}
		}
		console.warn(
			'[ktube] Customizer CSS checksum drift detected.\n' +
			'  expected: ' + expected.total + '\n' +
			'  actual:   ' + totalActual + '\n' +
			'  divergent setting(s): ' + ( drifts.length ? drifts.join( ', ' ) : '(total mismatch only — substring split inconclusive)' )
		);
	} );
}

function ktubeCpTierForRatio( ratio ) {
	if ( ratio >= 7 )   { return 'AAA'; }
	if ( ratio >= 4.5 ) { return 'AA'; }
	if ( ratio >= 3 )   { return 'AA-large'; }
	return 'FAIL';
}

function ktubeCpUpdateBadge( settingId ) {
	const bgKeys = KTUBE_CP_SECTIONS[ settingId ];
	if ( ! bgKeys ) {
		return;
	}
	const ctrl = wp.customize.control( settingId );
	if ( ! ctrl || ! ctrl.container ) {
		return;
	}
	const desc = ctrl.container.find( '.description' );
	if ( ! desc.length ) {
		return;
	}
	const fg = wp.customize( settingId ).get();
	const worst = bgKeys.map( function ( bgKey ) {
		return ktubeCpContrast( fg, wp.customize( bgKey ).get() );
	} ).reduce( function ( m, r ) { return Math.min( m, r ); }, Infinity );
	const tier = ktubeCpTierForRatio( worst );

	desc.attr( 'aria-live', 'polite' );
	desc.find( '.ktube-cp-badge' ).remove();
	desc.append( ' <span class="ktube-cp-badge ktube-cp-badge--' + tier.toLowerCase() + '">WCAG ' + tier + ' \u00b7 ' + worst.toFixed( 2 ) + ':1</span>' );
}

function ktubeCpPaintThemeDefault() {
	const def = wp.customize( 'ktube_theme_default' ).get() || 'auto';
	const doc = wp.customize.previewer.container[ 0 ] && wp.customize.previewer.container[ 0 ].contentDocument;
	if ( ! doc ) {
		return;
	}
	const html = doc.documentElement;
	if ( ! html ) {
		return;
	}
	const resolved = ( def === 'auto' )
		? ( doc.defaultView.matchMedia( '(prefers-color-scheme: dark)' ).matches ? 'dark' : 'light' )
		: def;
	html.setAttribute( 'data-theme', resolved );
	html.style.colorScheme = resolved;
}

// Auto-init when wp.customize is available (Customizer preview iframe).
if ( typeof wp !== 'undefined' && wp.customize ) {
	wp.customize.bind( 'ready', function () {
		ktubeCpPaint();
		ktubeCpPaintThemeDefault();
		ktubeCpVerifyChecksum();

		KTUBE_CP_GRID_KEYS.concat( KTUBE_CP_THUMB_KEYS ).forEach( function ( id ) {
			wp.customize( id, function ( v ) { v.bind( ktubeCpPaint ); } );
		} );
		KTUBE_CP_TOKEN_KEYS.forEach( function ( id ) {
			wp.customize( id, function ( v ) {
				v.bind( function () {
					ktubeCpPaint();
					ktubeCpUpdateBadge( id );
				} );
			} );
		} );
		wp.customize( 'ktube_theme_default', function ( v ) { v.bind( ktubeCpPaintThemeDefault ); } );
	} );
}

// Back-compat global handles for the Customizer wire that reads via
// window.ktubeCustomizer.* (the iframe's runtime-touchable surface,
// because controls.js is enqueued WITHOUT `type="module"` so it can't
// consume ES exports at boot — only window globals).
if ( typeof window !== 'undefined' ) {
	window.ktubeCustomizer = {
		buildCss: ktubeCpBuildCss,
		contrast: ktubeCpContrast,
		paint: ktubeCpPaint,
		paintThemeDefault: ktubeCpPaintThemeDefault,
		verifyChecksum: ktubeCpVerifyChecksum,
		tierForRatio: ktubeCpTierForRatio,
		updateBadge: ktubeCpUpdateBadge,
		sha256Hex: ktubeCpSha256Hex,
	};
}

// ESM named exports — vitest+jsdom reads these as the canonical module
// surface. The browser-side window.ktubeCustomizer handle above remains
// the runtime-touchable surface for <script> enqueues (no type=module).
// `ktubeCp`-prefixed locals stay module-internal to avoid colliding with
// globally-leaked `<function>()` declarations on browsers that load this
// as a classic script (no type=module); the `as` aliases below give the
// export names the rest of the codebase (tests, fixtures, docs) reads.
export {
	ktubeCpBuildCss          as buildCss,
	ktubeCpContrast          as contrast,
	ktubeCpPaint             as paint,
	ktubeCpPaintThemeDefault as paintThemeDefault,
	ktubeCpVerifyChecksum    as verifyChecksum,
	ktubeCpTierForRatio      as tierForRatio,
	ktubeCpUpdateBadge       as updateBadge,
	ktubeCpSha256Hex         as sha256Hex,
};
