/*
 * ktube site-wide JS — runs once per page.
 * No Vite import surface: wp_enqueue_script handles the load order
 * (phase -1 inline theme bootstrap → ktube-dark-mode → ktube-trailer →
 * ktube-share → ktube-lightbox → ktube-player on singular('video') only).
 *
 * This file is reserved as the place to wire cross-cutting site-wide
 * handlers. Phase 7 already wired the share menu into the deck; the
 * bottom of this file is intentionally a no-op so any future global
 * binding has a place to live.
 */
( function () {
	'use strict';

	if ( typeof document === 'undefined' ) {
		return;
	}

	document.addEventListener( 'DOMContentLoaded', function () {
		// Reserved for future delegated global handlers.
	} );
} )();
