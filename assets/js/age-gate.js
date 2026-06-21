/*
 * ktube age-gate modal controller — Phase 6.
 *
 * Phase 0-A reversal (2026-06-21): hand-shipped (not Vite compiled). The
 * minified IIFE wrapper is preserved verbatim from the prior dist bundle
 * so the existing vitest tests + the page-template modal markup behavior
 * are unchanged. The `export {}` block at the bottom is inert in a
 * non-module <script> tag and only matters under Vite's modulepreload path
 * (no longer used).
 *
 * Public surface used by vitest:
 *   - isVerified(durationDays, cfg) → boolean (localStorage + cookie check)
 *   - mountModal(data), persist(), clearPending() — dialog actions
 *
 * Architecture (unchanged):
 *   - ktubeAgeGateData is injected by PHP via wp_add_inline_script ONLY
 *     when ktube_age_gate_enabled is true.
 *   - Inline boot at wp_head priority -2 sets <html data-ktube-age-gate>
 *     so the CSS hide rule "html[data-ktube-age-gate=\"pending\"] body
 *     { visibility: hidden }" runs before stylesheet paints.
 *   - showModal() rejects on user-activation-lacking contexts; fallback
 *     path uses a manual Tab focus trap and SCSS forced display.
 */
var ktubeAgeGateFocusableSelector = [
	'a[href]',
	'button:not([disabled])',
	'input:not([disabled])',
	'select:not([disabled])',
	'textarea:not([disabled])',
	'[tabindex]:not([tabindex="-1"])'
].join( ',' );
var ktubeAgeGateStorageKey = 'ktube-age-confirmed-on';
var ktubeAgeGateCookieName = 'ktube_age_verified';
var ktubeAgeGateTtlMsPerDay = 86400000;

document.addEventListener( 'DOMContentLoaded', ktubeAgeGateInit );

function ktubeAgeGateInit() {
	var cfg = window.ktubeAgeGateData;
	if ( ! cfg || ! cfg.enabled ) {
		ktubeAgeGateClearPending();
		return;
	}
	var durationDays = Number( cfg.durationDays ) || 30;
	if ( ktubeAgeGateIsVerified( durationDays, cfg ) ) {
		ktubeAgeGateClearPending();
		return;
	}
	ktubeAgeGateMountModal( cfg );
}

function ktubeAgeGateClearPending() {
	var html = document.documentElement;
	if ( html.getAttribute( 'data-ktube-age-gate' ) === 'pending' ) {
		html.setAttribute( 'data-ktube-age-gate', 'ready' );
	}
}

function ktubeAgeGateIsVerified( durationDays, cfg ) {
	var ttlMs   = durationDays * ktubeAgeGateTtlMsPerDay;
	var now     = Date.now();
	var storage = cfg.storageKey || ktubeAgeGateStorageKey;
	var cookie  = cfg.cookieName || ktubeAgeGateCookieName;
	try {
		var raw = localStorage.getItem( storage );
		var ts  = parseInt( raw || '', 10 );
		if ( Number.isFinite( ts ) && now - ts < ttlMs ) {
			return true;
		}
	} catch ( _e ) { /* private mode */ }
	if ( ! cookie ) {
		return false;
	}
	var match = document.cookie.match( new RegExp( '(?:^|;\\s*)' + cookie + '=(\\d+)' ) );
	if ( ! match ) {
		return false;
	}
	var ts = parseInt( match[ 1 ], 10 );
	return Number.isFinite( ts ) && now - ts < ttlMs;
}

function ktubeAgeGatePersist( durationDays, cfg ) {
	var ts    = Date.now();
	var ttlMs = durationDays * ktubeAgeGateTtlMsPerDay;
	try {
		localStorage.setItem( cfg.storageKey || ktubeAgeGateStorageKey, String( ts ) );
	} catch ( _e ) { /* private */ }
	var cookie = cfg.cookieName || ktubeAgeGateCookieName;
	if ( cookie ) {
		var exp = new Date( ts + ttlMs ).toUTCString();
		document.cookie = cookie + '=' + ts + '; expires=' + exp + '; path=/; SameSite=Lax; Secure';
	}
}

function ktubeAgeGateSetBackgroundInert( inert ) {
	var bg = document.body;
	if ( ! bg ) {
		return;
	}
	if ( inert ) {
		bg.setAttribute( 'inert', '' );
	} else {
		bg.removeAttribute( 'inert' );
	}
}

function ktubeAgeGateButton( className, text, onClick ) {
	var b = document.createElement( 'button' );
	b.type = 'button';
	b.className = className;
	b.textContent = text;
	b.addEventListener( 'click', function ( ev ) {
		ev.preventDefault();
		onClick();
	} );
	return b;
}

function ktubeAgeGateMountModal( data ) {
	var minAge = data.minAge !== undefined ? data.minAge : 18;

	var dialog = document.createElement( 'dialog' );
	dialog.className = 'ktube-age-gate';
	dialog.setAttribute( 'aria-labelledby', 'ktube-age-gate__title' );
	dialog.setAttribute( 'aria-describedby', 'ktube-age-gate__msg' );

	var card = document.createElement( 'div' );
	card.className = 'ktube-age-gate__dialog';

	var title = document.createElement( 'h2' );
	title.id = 'ktube-age-gate__title';
	title.className = 'ktube-age-gate__title';
	title.textContent = minAge + '+ verification required';

	var msg = document.createElement( 'p' );
	msg.id = 'ktube-age-gate__msg';
	msg.className = 'ktube-age-gate__msg';
	msg.textContent = 'This site contains content intended for adults aged ' + minAge + ' and over. Please confirm your age to continue.';

	var actions = document.createElement( 'div' );
	actions.className = 'ktube-age-gate__actions';

	var confirmBtn = ktubeAgeGateButton(
		'ktube-age-gate__btn ktube-age-gate__btn--confirm',
		'I am ' + minAge + ' or older',
		function () { ktubeAgeGateOnConfirm( data ); }
	);
	var declineBtn = ktubeAgeGateButton(
		'ktube-age-gate__btn ktube-age-gate__btn--decline',
		'I am under ' + minAge,
		function () { ktubeAgeGateOnDecline( data ); }
	);

	actions.appendChild( confirmBtn );
	actions.appendChild( declineBtn );
	card.appendChild( title );
	card.appendChild( msg );
	card.appendChild( actions );
	dialog.appendChild( card );
	document.body.appendChild( dialog );

	dialog.addEventListener( 'cancel', function ( ev ) {
		ev.preventDefault();
	} );

	var modalOpened = false;
	try {
		dialog.showModal();
		modalOpened = true;
		dialog.setAttribute( 'aria-modal', 'true' );
	} catch ( _e ) {
		modalOpened = false;
		dialog.setAttribute( 'data-ktube-fallback', 'true' );
	}

	ktubeAgeGateSetBackgroundInert( true );
	ktubeAgeGateClearPending();

	if ( ! modalOpened ) {
		dialog.addEventListener( 'keydown', ktubeAgeGateOnKeydown );
	}
	confirmBtn.focus();
}

function ktubeAgeGateOnConfirm( data ) {
	ktubeAgeGatePersist( Number( data.durationDays ) || 30, data );
	var dialog = document.querySelector( 'dialog.ktube-age-gate' );
	if ( dialog ) {
		dialog.close();
		dialog.remove();
	}
	ktubeAgeGateSetBackgroundInert( false );
	ktubeAgeGateClearPending();
}

function ktubeAgeGateOnDecline( data ) {
	var target = data.redirectUrl || 'https://www.google.com/';
	window.location.replace( target );
}

function ktubeAgeGateOnKeydown( ev ) {
	if ( ev.key !== 'Tab' ) {
		return;
	}
	var dialog = ev.currentTarget;
	var focusables = Array.prototype.slice.call(
		dialog.querySelectorAll( ktubeAgeGateFocusableSelector )
	).filter( function ( el ) {
		return el.offsetParent !== null || el === document.activeElement;
	} );
	if ( focusables.length === 0 ) {
		ev.preventDefault();
		return;
	}
	var first  = focusables[ 0 ];
	var last   = focusables[ focusables.length - 1 ];
	var active = document.activeElement;
	if ( ev.shiftKey && active === first ) {
		ev.preventDefault();
		last.focus();
	} else if ( ! ev.shiftKey && active === last ) {
		ev.preventDefault();
		first.focus();
	}
}

// Test-friendly handle. vitest+jsdom + phpunit stub bootstrap both read
// these names; renaming would break the test suite.
if ( typeof window !== 'undefined' ) {
	window.ktubeAgeGate = {
		init: ktubeAgeGateInit,
		isVerified: ktubeAgeGateIsVerified,
		persist: ktubeAgeGatePersist,
		clearPending: ktubeAgeGateClearPending,
		mountModal: ktubeAgeGateMountModal,
		onConfirm: ktubeAgeGateOnConfirm,
		onDecline: ktubeAgeGateOnDecline,
	};
}

// ESM named exports — vitest's transformer reads these as the canonical
// module surface. The window.* handles above remain for non-module
// consumers (and the manual integration smoke test) so renaming or
// removing either side without updating vitest+jsdom tests is now a
// Phase 0-A contract change.
export {
	ktubeAgeGateInit          as init,
	ktubeAgeGateIsVerified    as isVerified,
	ktubeAgeGatePersist       as persist,
	ktubeAgeGateClearPending  as clearPending,
	ktubeAgeGateMountModal    as mountModal,
	ktubeAgeGateOnConfirm     as onConfirm,
	ktubeAgeGateOnDecline     as onDecline,
};
