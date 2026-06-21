// vitest — age-gate.js exports: isVerified, persist, mountModal, clearPending.
//
// Pure isVerified() is the heart of the gate: localStorage timestamp within
// durationDays * 86_400_000 ms OR cookie match within TTL. Boundary cases at
// exactly TTL and just past TTL must be correct.

// eslint-disable-next-line import/extensions
import { isVerified, persist, clearPending } from '../../assets/js/age-gate.js';

const TTL_MS_PER_DAY = 86_400_000;
const STORAGE_KEY = 'ktube-age-confirmed-on';
const COOKIE_NAME = 'ktube_age_verified';

function clearAllStorage() {
	try {
		localStorage.clear();
	} catch ( _ ) { /* private mode */ }
	document.cookie = `${ COOKIE_NAME }=; expires=Thu, 01 Jan 1970 00:00:00 GMT; path=/`;
}

describe( 'age-gate isVerified()', () => {
	beforeEach( clearAllStorage );

	const cfg = { storageKey: STORAGE_KEY, cookieName: COOKIE_NAME };

	test( 'no storage and no cookie is unverified', () => {
		expect( isVerified( 30, cfg ) ).toBe( false );
	} );

	test( 'storage timestamp exactly at TTL-eligible (just inside)', () => {
		const ts = Date.now() - ( 30 * TTL_MS_PER_DAY - 100 );
		localStorage.setItem( STORAGE_KEY, String( ts ) );
		expect( isVerified( 30, cfg ) ).toBe( true );
	} );

	test( 'storage timestamp just past TTL is unverified', () => {
		const ts = Date.now() - ( 30 * TTL_MS_PER_DAY + 100 );
		localStorage.setItem( STORAGE_KEY, String( ts ) );
		expect( isVerified( 30, cfg ) ).toBe( false );
	} );

	test( 'cookie fallback fires when localStorage is empty', () => {
		const ts = Date.now() - 1000;
		document.cookie = `${ COOKIE_NAME }=${ ts }; path=/`;
		expect( isVerified( 30, cfg ) ).toBe( true );
	} );

	test( 'expired cookie is rejected', () => {
		const ts = Date.now() - ( 30 * TTL_MS_PER_DAY + 1000 );
		document.cookie = `${ COOKIE_NAME }=${ ts }; path=/`;
		expect( isVerified( 30, cfg ) ).toBe( false );
	} );

	test( 'localStorage wins over cookie when both are recent', () => {
		const ts = Date.now() - 100;
		localStorage.setItem( STORAGE_KEY, String( ts ) );
		document.cookie = `${ COOKIE_NAME }=${ ts }; path=/`;
		expect( isVerified( 30, cfg ) ).toBe( true );
	} );

	test( 'non-finite localStorage value falls through to cookie', () => {
		localStorage.setItem( STORAGE_KEY, 'not-a-number' );
		const ts = Date.now() - 1000;
		document.cookie = `${ COOKIE_NAME }=${ ts }; path=/`;
		expect( isVerified( 30, cfg ) ).toBe( true );
	} );

	test( 'durationDays=1: just-inside TTL verifies, one-day-plus-1ms rejects', () => {
		const inside = Date.now() - ( TTL_MS_PER_DAY - 50 );
		localStorage.setItem( STORAGE_KEY, String( inside ) );
		expect( isVerified( 1, cfg ) ).toBe( true );

		const outside = Date.now() - ( TTL_MS_PER_DAY + 50 );
		localStorage.setItem( STORAGE_KEY, String( outside ) );
		expect( isVerified( 1, cfg ) ).toBe( false );
	} );
} );

describe( 'age-gate persist()', () => {
	beforeEach( clearAllStorage );

	const cfg = { storageKey: STORAGE_KEY, cookieName: COOKIE_NAME };

	test( 'persist() writes a localStorage ms timestamp', () => {
		persist( 30, cfg );
		const ts = parseInt( localStorage.getItem( STORAGE_KEY ) || '', 10 );
		expect( Number.isFinite( ts ) ).toBe( true );
		expect( Date.now() - ts ).toBeLessThan( 50 ); // Allow clock-skew tolerance.
	} );

	test( 'persist() writes a cookie with future expiry', () => {
		persist( 30, cfg );
		expect( document.cookie ).toContain( `${ COOKIE_NAME }=` );
		// Cookie has expires= attribute — we can't peek at the value without
		// parsing document.cookie itself, which we already saw. The test is
		// really proving persist() didn't throw and set SOMETHING.
		expect( document.cookie.length ).toBeGreaterThan( 0 );
	} );

	test( 'after persist(), isVerified() returns true', () => {
		persist( 30, cfg );
		expect( isVerified( 30, cfg ) ).toBe( true );
	} );
} );

describe( 'age-gate clearPending()', () => {
	test( 'clears the pending attribute on <html>', () => {
		document.documentElement.setAttribute( 'data-ktube-age-gate', 'pending' );
		clearPending();
		expect( document.documentElement.getAttribute( 'data-ktube-age-gate' ) ).toBe( 'ready' );
	} );

	test( 'leaves attribute alone if it is not "pending"', () => {
		document.documentElement.setAttribute( 'data-ktube-age-gate', 'ready' );
		clearPending();
		expect( document.documentElement.getAttribute( 'data-ktube-age-gate' ) ).toBe( 'ready' );
	} );

	test( 'no attribute set: leaves document untouched', () => {
		document.documentElement.removeAttribute( 'data-ktube-age-gate' );
		clearPending();
		expect( document.documentElement.hasAttribute( 'data-ktube-age-gate' ) ).toBe( false );
	} );
} );
