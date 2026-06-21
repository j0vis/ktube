/*
 * ktube GDPR consent banner — Phase 6-C.
 *
 * Vanilla JS, IIFE, no deps. Boot strapping reads the locally-emitted
 * window.ktubeGdprConsentData object that PHP injected via
 * wp_add_inline_script('ktube-gdpr-consent', 'before'), then:
 *
 *   - If a previous consent blob exists in localStorage + cookie, skip
 *     the banner entirely (returning visitors).
 *   - Otherwise unhide the banner, hydrate the toggle defaults from the
 *     server-side categories object, wire the three button listeners.
 *
 * Persistence: the same blob is written to BOTH localStorage (key
 * 'ktube-gdpr-consent') and a cookie ('ktube_gdpr_consent', SameSite=Lax,
 * Secure). The cookie is the redundant mirror so consent survives when
 * localStorage is unavailable (private browsing on some browsers).
 *
 * ESM named export at the bottom so vitest can import the helpers
 * directly under Phase 0-A's module-resolution rules.
 */
var ktubeGdprStorageKey = 'ktube-gdpr-consent';
var ktubeGdprCookieName = 'ktube_gdpr_consent';
var ktubeGdprTtlMsPerDay = 86400000;
var ktubeGdprEventName  = 'ktube:gdprconsentchange';

document.addEventListener( 'DOMContentLoaded', ktubeGdprInit );

function ktubeGdprInit() {
	var cfg = window.ktubeGdprConsentData;
	if ( ! cfg || ! cfg.enabled ) {
		return;
	}
	var banner = document.getElementById( 'ktube-gdpr-banner' );
	if ( ! banner ) {
		return;
	}

	ktubeGdprHydrateDefaults( banner, cfg );

	var existing = ktubeGdprRead( cfg );
	if ( existing ) {
		ktubeGdprPersistApplyPostMessage( existing, cfg );
		return;
	}

	banner.removeAttribute( 'hidden' );
	ktubeGdprWireButtons( banner, cfg );
}

function ktubeGdprHydrateDefaults( banner, cfg ) {
	var inputs = banner.querySelectorAll( 'input[type="checkbox"][data-ktube-gdpr-category]' );
	for ( var i = 0; i < inputs.length; i++ ) {
		var cat = inputs[ i ].getAttribute( 'data-ktube-gdpr-category' );
		if ( ! cat || ! cfg.categories || ! cfg.categories[ cat ] ) {
			continue;
		}
		inputs[ i ].checked = !! cfg.categories[ cat ];
	}
}

function ktubeGdprRead( cfg ) {
	var raw;
	try {
		raw = localStorage.getItem( cfg.storageKey || ktubeGdprStorageKey );
	} catch ( _e ) {
		raw = null;
	}
	if ( raw ) {
		var parsed = ktubeGdprSafeParse( raw );
		if ( parsed ) {
			return parsed;
		}
	}
	var cookieMatch = document.cookie.match( new RegExp( '(?:^|;\\s*)' + ( cfg.cookieName || ktubeGdprCookieName ) + '=([^;]+)' ) );
	if ( ! cookieMatch ) {
		return null;
	}
	try {
		return ktubeGdprSafeParse( decodeURIComponent( cookieMatch[ 1 ] ) );
	} catch ( _e ) {
		return null;
	}
}

function ktubeGdprSafeParse( raw ) {
	try {
		var v = JSON.parse( raw );
		if ( v && typeof v === 'object' && v.categories && typeof v.categories === 'object' ) {
			v.categories = ktubeGdprNormalizeBlob( v.categories );
			return v;
		}
	} catch ( _e ) { /* malformed */ }
	return null;
}

function ktubeGdprNormalizeBlob( cat ) {
	var out = { essential: true };
	[ 'analytics', 'marketing' ].forEach( function ( k ) {
		out[ k ] = !! cat[ k ];
	} );
	return out;
}

function ktubeGdprWireButtons( banner, cfg ) {
	var buttons = banner.querySelectorAll( '[data-ktube-gdpr-action]' );
	for ( var i = 0; i < buttons.length; i++ ) {
		buttons[ i ].addEventListener( 'click', function ( ev ) {
			ev.preventDefault();
			var action = ev.currentTarget.getAttribute( 'data-ktube-gdpr-action' );
			var blob = ktubeGdprBuildFromBanner( banner );
			if ( 'accept-all' === action ) {
				blob.categories = { essential: true, analytics: true, marketing: true };
			} else if ( 'reject' === action ) {
				blob.categories = { essential: true, analytics: false, marketing: false };
			}
			ktubeGdprWrite( blob, cfg );
			banner.setAttribute( 'hidden', '' );
			ktubeGdprPersistApplyPostMessage( blob, cfg );
		} );
	}
}

function ktubeGdprBuildFromBanner( banner ) {
	var blob = {
		timestamp: Date.now(),
		categories: { essential: true },
	};
	var inputs = banner.querySelectorAll( 'input[type="checkbox"][data-ktube-gdpr-category]' );
	for ( var i = 0; i < inputs.length; i++ ) {
		var cat = inputs[ i ].getAttribute( 'data-ktube-gdpr-category' );
		if ( ! cat ) {
			continue;
		}
		blob.categories[ cat ] = !! inputs[ i ].checked;
	}
	return blob;
}

function ktubeGdprWrite( blob, cfg ) {
	var key   = cfg.storageKey || ktubeGdprStorageKey;
	var name  = cfg.cookieName || ktubeGdprCookieName;
	var ttlDays = Number( cfg.ttlDays ) || 180;
	var ttlMs = ttlDays * ktubeGdprTtlMsPerDay;
	var json  = JSON.stringify( blob );
	try {
		localStorage.setItem( key, json );
	} catch ( _e ) { /* private */ }
	var exp = new Date( blob.timestamp + ttlMs ).toUTCString();
	document.cookie = name + '=' + encodeURIComponent( json ) + '; expires=' + exp + '; path=/; SameSite=Lax; Secure';
}

function ktubeGdprPersistApplyPostMessage( blob, cfg ) {
	// Fire a window event so analytics/marketing plugins can hook in via
	// vanilla JS without depending on a custom cookie name. Plugins can
	// listen with: window.addEventListener('ktube:gdprconsentchange', ...)
	try {
		var ev = new CustomEvent( ktubeGdprEventName, { detail: { categories: blob.categories, timestamp: blob.timestamp } } );
		window.dispatchEvent( ev );
	} catch ( _e ) { /* old browser */ }
}

// Test-friendly handles for vitest+jsdom and phpunit stub bootstrap.
if ( typeof window !== 'undefined' ) {
	window.ktubeGdpr = {
		init: ktubeGdprInit,
		read: ktubeGdprRead,
		write: ktubeGdprWrite,
		buildFromBanner: ktubeGdprBuildFromBanner,
		hydrateDefaults: ktubeGdprHydrateDefaults,
		normalizeBlob: ktubeGdprNormalizeBlob,
	};
}

// ESM exports — vitest reads these.
export {
	ktubeGdprInit             as init,
	ktubeGdprRead             as read,
	ktubeGdprWrite            as write,
	ktubeGdprBuildFromBanner  as buildFromBanner,
	ktubeGdprHydrateDefaults  as hydrateDefaults,
	ktubeGdprNormalizeBlob    as normalizeBlob,
};
