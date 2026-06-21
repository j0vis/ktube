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
 * Phase 14 perf (2026-06-21) — HLS engine activation + quality selector.
 * Video.js 8.17.4 ships `videojs-http-streaming` (VHS) baked into the
 * standard `video.min.js`; the same global `videojs` plays progressive
 * mp4 AND HLS `.m3u8` out of the box. The toggle + bandwidth ladder
 * arrive via `window.ktubeVideoPlayerConfig` injected at
 * wp_enqueue_scripts priority 12 from includes/player-depth.php, so
 * no extra <script> tag is needed.
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

		// Phase 14 perf (2026-06-21) — read injected player config. Defaults
		// are safe-on-mp4 so a missing config (e.g. tests, partial enqueue)
		// falls back without feature-regression.
		var ktubeConfig = ( typeof window.ktubeVideoPlayerConfig === 'object' && window.ktubeVideoPlayerConfig )
			? window.ktubeVideoPlayerConfig
			: { enable_hls: false, default_quality: 'auto', quality_levels: [] };

		nodes.forEach( function ( node ) {
			if ( ! node.id ) {
				node.id = 'ktube-vjs-' + Math.random().toString( 36 ).slice( 2, 9 );
			}
			var ktubeOpts = {
				controls: true,
				autoplay: false,
				preload: 'metadata',
				fluid: false,
				responsive: true,
				playbackRates: [ 0.5, 1, 1.25, 1.5, 2 ],
			};
			// HLS engine activation. Video.js 8 ships VHS; we only need to
			// flip the inference switch when an operator has enabled HLS.
			if ( ktubeConfig.enable_hls ) {
				ktubeOpts.html5 = ktubeOpts.html5 || {};
				ktubeOpts.html5.vhs = ktubeOpts.html5.vhs || {};
				ktubeOpts.html5.vhs.overrideNative = true;
				if ( ktubeConfig.default_quality && 'auto' !== ktubeConfig.default_quality ) {
					ktubeOpts.html5.vhs.bandwidth = ktubeBandwidthForQuality( ktubeConfig.quality_levels, ktubeConfig.default_quality );
				}
			}
			var ktubePlayer = window.videojs( node.id, ktubeOpts );

			// Quality selector UI — populate a <select> sibling after
			// `loadedmetadata` if the operator supplied a ladder AND the
			// video engine reports usable VHS state. Defers gracefully if
			// VHS isn't engaged (progressive mp4 never populates levels).
			if ( ktubeConfig.enable_hls && Array.isArray( ktubeConfig.quality_levels ) && ktubeConfig.quality_levels.length ) {
				ktubePlayer.on( 'loadedmetadata', function () {
					ktubeMountQualitySelector( node, ktubePlayer, ktubeConfig );
				} );
			}
		} );
	} );

	/**
	 * ktubeBandwidthForQuality — server-mirrored bandwidth lookup.
	 * Mirrors includes/player-depth.php::ktube_quality_bandwidth_map().
	 * Defaults to 2,800,000 (720p) when no matching row exists, the
	 * safest middle rung.
	 *
	 * @param {Array<{label:string,bandwidth:number}>} ktubeLevels
	 * @param {string} ktubeQuality
	 * @return {number}
	 */
	function ktubeBandwidthForQuality( ktubeLevels, ktubeQuality ) {
		for ( var i = 0; i < ktubeLevels.length; i++ ) {
			if ( ktubeLevels[i] && ktubeLevels[i].label === ktubeQuality ) {
				return ktubeLevels[i].bandwidth;
			}
		}
		return 2800000;
	}

	/**
	 * ktubeMountQualitySelector — render <select> before the player.
	 *
	 * The <select> uses the same HTMLDialogElement-friendly markup
	 * pattern as the lightbox dialog so a future PWA can swap it for a
	 * native <select> dropdown without re-themeing.
	 *
	 * @param {Element} ktubeNode
	 * @param {object} ktubePlayer
	 * @param {object} ktubeConfig
	 */
	function ktubeMountQualitySelector( ktubeNode, ktubePlayer, ktubeConfig ) {
		if ( ! ktubePlayer || ! ktubePlayer.qualityLevels ) {
			return;
		}
		var ktubeLevels = ktubePlayer.qualityLevels();
		if ( ! ktubeLevels || typeof ktubeLevels.length !== 'number' || ktubeLevels.length < 2 ) {
			return;
		}
		var ktubeSelect = document.createElement( 'select' );
		ktubeSelect.className = 'ktube-player-quality';
		ktubeSelect.setAttribute( 'aria-label', 'Playback quality' );
		[ 'auto', '1080p', '720p', '480p', '360p' ].forEach( function ( q ) {
			var ktubeOpt = document.createElement( 'option' );
			ktubeOpt.value = q;
			ktubeOpt.textContent = q;
			if ( q === ktubeConfig.default_quality ) {
				ktubeOpt.selected = true;
			}
			ktubeSelect.appendChild( ktubeOpt );
		} );
		ktubeNode.parentNode && ktubeNode.parentNode.insertBefore( ktubeSelect, ktubeNode.nextSibling );
		ktubeSelect.addEventListener( 'change', function () {
			// 'auto' defers to ABR; specific values re-pin.
			if ( ktubeSelect.value === 'auto' ) {
				return;
			}
			var ktubeBandwidth = ktubeBandwidthForQuality( ktubeConfig.quality_levels, ktubeSelect.value );
			var ktubeList = ktubePlayer.qualityLevels();
			for ( var i = 0; i < ktubeList.length; i++ ) {
				if ( ktubeList[i].bandwidth === ktubeBandwidth ) {
					ktubeList[i].enabled = true;
					ktubePlayer.currentQualityLevel( ktubeList[i] );
					break;
				}
			}
		} );
	}
} )();
