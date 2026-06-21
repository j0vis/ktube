/*
 * ktube/assets/js/player.js — Hand-authored Video.js init.
 *
 * Phase 0-A 2026-06-21 reversal:
 *   - Removed the Vite dynamic `import('video.js/core')`. The vendored
 *     ktube-videojs bundle in includes/setup.php is enqueued BEFORE this
 *     script, exposing the global `videojs` symbol on `window`.
 *   - ktube-videojs-hls is enqueued after ktube-videojs only when the
 *     content model needs it (mp4 stream today); progressive h.264 mp4
 *     plays without HLS just fine.
 *   - The script swaps in IIFE form so a plain <script> tag (no
 *     `type="module"`) can enqueue it without a bundler.
 *
 * Bail conditions (matched against the prior implementation):
 *   1. No `.ktube-player` node ↦ no-op.
 *   2. `<html class="ktube-has-wps-player">` ↦ WPS Player plugin owns
 *      playback; no double-init.
 *   3. `videojs` not on window ↦ vendored ktube-videojs did not load;
 *      leave <video> markup as native fallback. Console-info warns once.
 */
( function () {
	'use strict';

	var PLAYER_SEL = '.ktube-player';

	if ( typeof document === 'undefined' ) {
		return;
	}

	// Fired once per page-session even when DOMContentLoaded re-fires (e.g.
	// after a soft reload in DevTools). Without it, devs debugging partial
	// vendor loads see the warning spam their console every keystroke.
	var ktubePlayerVideojsWarningEmitted = false;

	document.addEventListener( 'DOMContentLoaded', function () {
		if ( document.documentElement.classList.contains( 'ktube-has-wps-player' ) ) {
			return;
		}
		var nodes = document.querySelectorAll( PLAYER_SEL );
		if ( ! nodes.length ) {
			return;
		}
		if ( typeof window.videojs !== 'function' ) {
			// Vendored ktube-videojs bundle did not load. Native <video>
			// markup stays as the absolute fallback so non-JS / partial-JS
			// visitors still see the poster + native controls.
			if ( ! ktubePlayerVideojsWarningEmitted && window.console && console.warn ) {
				ktubePlayerVideojsWarningEmitted = true;
				console.warn( '[ktube] videojs global missing — falling back to native <video>.' );
			}
			return;
		}

		nodes.forEach( function ( node ) {
			if ( ! node.id ) {
				node.id = 'ktube-vjs-' + Math.random().toString( 36 ).slice( 2, 9 );
			}
			window.videojs( node.id, {
				controls: true,
				autoplay: false,
				preload: 'metadata',
				fluid: false,
				responsive: true,
				playbackRates: [ 0.5, 1, 1.25, 1.5, 2 ],
			} );
		} );
	} );
} )();
