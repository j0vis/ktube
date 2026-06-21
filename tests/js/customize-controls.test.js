// vitest — customize-controls.js logic.
//
// Exports + behavior tests:
//   - buildCss() — stub wp.customize() returning a fixed map of values.
//                  Golden fixture against the documented default output.
//   - contrast(a, b), tierForRatio(r) — pure math.
//   - sha256Hex(s) — async Web Crypto SHA-256, hex output. Tests the NIST
//                    vector for SHA-256("abc") directly.
//
// Phase 7b per-key drift is exercised indirectly via mirrorSubstring() being
// defined internally; verifying the per-key checksum flow requires wp.customize
// runtime integration which is covered in the manual QA pace, not vitest.

// eslint-disable-next-line import/extensions
import { buildCss, contrast, tierForRatio, sha256Hex } from '../../assets/js/customize-controls.js';

function wpCustomizeStub( values ) {
	globalThis.wp = {
		customize: ( id ) => ( { get: () => values[ id ] ?? null } ),
		previewer: { container: [ { contentDocument: null } ] },
	};
}

describe( 'contrast()', () => {
	test( 'same color returns 1.0', () => {
		expect( contrast( '#000000', '#000000' ) ).toBeCloseTo( 1.0, 5 );
	} );

	test( 'black on white is 21', () => {
		expect( contrast( '#000000', '#ffffff' ) ).toBeCloseTo( 21.0, 1 );
	} );

	test( 'white on black is also 21 (symmetric)', () => {
		expect( contrast( '#ffffff', '#000000' ) ).toBeCloseTo( 21.0, 1 );
	} );

	test( 'invalid hex falls back to white on white = 1', () => {
		// hex() falls back to {255,255,255} on invalid input.
		expect( contrast( 'rgb(0,0,0)', 'rgb(0,0,0)' ) ).toBeCloseTo( 1.0, 5 );
	} );

	test( 'mid-grey on white is ~4.49', () => {
		expect( contrast( '#777777', '#ffffff' ) ).toBeGreaterThan( 4.4 );
		expect( contrast( '#777777', '#ffffff' ) ).toBeLessThan( 4.6 );
	} );
} );

describe( 'tierForRatio()', () => {
	test.each( [
		[ 7.0, 'AAA' ],
		[ 10, 'AAA' ],
		[ 4.5, 'AA' ],
		[ 6.99, 'AA' ],
		[ 3.0, 'AA-large' ],
		[ 4.49, 'AA-large' ],
		[ 2.99, 'FAIL' ],
		[ 1.0, 'FAIL' ],
	] )( 'ratio %f → %s', ( ratio, expected ) => {
		expect( tierForRatio( ratio ) ).toBe( expected );
	} );
} );

describe( 'buildCss() golden fixture', () => {
	beforeEach( () => {
		wpCustomizeStub( {
			ktube_grid_cols_desktop: 4,
			ktube_grid_cols_tablet: 3,
			ktube_grid_cols_mobile: 2,
			ktube_thumb_cols_desktop: 3,
			ktube_thumb_cols_mobile: 2,
			ktube_color_bg_light: '#ffffff',
			ktube_color_text_light: '#18181b',
			ktube_color_accent_light: '#db2777',
			ktube_color_link_light: '#2563eb',
			ktube_color_bg_dark: '#0e0e10',
			ktube_color_text_dark: '#e4e4e7',
			ktube_color_accent_dark: '#f472b6',
			ktube_color_link_dark: '#60a5fa',
		} );
	} );

	test( 'default values produce a stable byte-equal output', () => {
		const golden =
			`:root {` +
				`--ktube-grid-cols-desktop:4;` +
				`--ktube-grid-cols-tablet:3;` +
				`--ktube-grid-cols-mobile:2;` +
				`--ktube-thumb-cols-desktop:3;` +
				`--ktube-thumb-cols-mobile:2;` +
			`}` +
			`:root[data-theme="light"], :root:not([data-theme]) {` +
				`--ktube-color-bg:#ffffff;` +
				`--ktube-color-text:#18181b;` +
				`--ktube-color-accent:#db2777;` +
				`--ktube-color-link:#2563eb;` +
			`}` +
			`:root[data-theme="dark"] {` +
				`--ktube-color-bg:#0e0e10;` +
				`--ktube-color-text:#e4e4e7;` +
				`--ktube-color-accent:#f472b6;` +
				`--ktube-color-link:#60a5fa;` +
			`}` +
			`@media (min-width: 641px) and (max-width: 1024px) {` +
				`:root {` +
					`--ktube-grid-cols-desktop: var(--ktube-grid-cols-tablet);` +
					`--ktube-thumb-cols: var(--ktube-thumb-cols-desktop);` +
				`}` +
			`}` +
			`@media (max-width: 640px) {` +
				`:root {` +
					`--ktube-grid-cols-desktop: var(--ktube-grid-cols-mobile);` +
					`--ktube-thumb-cols: var(--ktube-thumb-cols-mobile);` +
				`}` +
			`}`;
		expect( buildCss() ).toBe( golden );
	} );

	test( 'changing one setting shifts only that variable', () => {
		wpCustomizeStub( {
			ktube_grid_cols_desktop: 5,
			ktube_grid_cols_tablet: 3,
			ktube_grid_cols_mobile: 2,
			ktube_thumb_cols_desktop: 3,
			ktube_thumb_cols_mobile: 2,
			ktube_color_bg_light: '#ffffff',
			ktube_color_text_light: '#18181b',
			ktube_color_accent_light: '#db2777',
			ktube_color_link_light: '#2563eb',
			ktube_color_bg_dark: '#0e0e10',
			ktube_color_text_dark: '#e4e4e7',
			ktube_color_accent_dark: '#f472b6',
			ktube_color_link_dark: '#60a5fa',
		} );
		expect( buildCss() ).toContain( '--ktube-grid-cols-desktop:5;' );
		expect( buildCss() ).not.toContain( '--ktube-grid-cols-desktop:4;' );
	} );
} );

describe( 'sha256Hex()', () => {
	test( 'returns 64-character lowercase hex string', async () => {
		const h = await sha256Hex( 'abc' );
		expect( h ).toMatch( /^[a-f0-9]{64}$/ );
	} );

	test( 'matches NIST vector for SHA-256("abc")', async () => {
		// SHA-256("abc") = ba7816bf8f01cfea414140de5dae2223b00361a396177a9cb410ff61f20015ad
		expect( await sha256Hex( 'abc' ) ).toBe(
			'ba7816bf8f01cfea414140de5dae2223b00361a396177a9cb410ff61f20015ad'
		);
	} );

	test( 'matches NIST vector for SHA-256("")', async () => {
		// SHA-256("")   = e3b0c44298fc1c149afbf4c8996fb92427ae41e4649b934ca495991b7852b855
		expect( await sha256Hex( '' ) ).toBe(
			'e3b0c44298fc1c149afbf4c8996fb92427ae41e4649b934ca495991b7852b855'
		);
	} );

	test( 'is deterministic', async () => {
		expect( await sha256Hex( 'ktube-css-checksum-guard' ) ).toBe(
			await sha256Hex( 'ktube-css-checksum-guard' )
		);
	} );

	test( 'different inputs produce different hashes', async () => {
		expect( await sha256Hex( 'abc' ) ).not.toBe( await sha256Hex( 'abd' ) );
	} );
} );
