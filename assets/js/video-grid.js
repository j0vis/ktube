/*
 * ktube/video-grid.js — Hover-trailer + storyboard controller (Phase 2).
 *
 * Phase 0-A reversal (2026-06-21): hand-shipped. Minified IIFE preserved
 * verbatim from the prior dist bundle so the existing vitest tests + the
 * trailer-on-hover UX are unchanged.
 *
 * Behavior (unchanged):
 *   - Single delegated event listener per .ktube-video-grid container.
 *   - IntersectionObserver gates per-item wiring until viewport entry.
 *   - prefers-reduced-motion gates autoplay video trailers.
 *   - Touch-only `(hover: none) | (pointer: coarse)` skips entirely.
 *   - Trailer node is REMOVED on mouseleave (not hidden) to avoid
 *     background decode cost on idle cards.
 *   - Storyboard fallback: data-storyboard URL is an image swap.
 */
( function () {
	'use strict';
	var GRID_SEL  = '.ktube-video-grid';
	var ITEM_SEL  = '.ktube-video-grid__item';
	var CARD_SEL  = '.ktube-card';
	var MOTION_Q  = '(prefers-reduced-motion: reduce)';
	var TOUCH_Q   = '(hover: none), (pointer: coarse)';
	var TOUCH_ONLY = typeof window !== 'undefined' && window.matchMedia( TOUCH_Q ).matches;

	if ( ! TOUCH_ONLY && typeof document !== 'undefined' ) {
		document.addEventListener( 'DOMContentLoaded', init );
	}

	function init() {
		var grids = document.querySelectorAll( GRID_SEL );
		if ( ! grids.length ) {
			return;
		}
		var io = new IntersectionObserver( function ( entries ) {
			for ( var i = 0; i < entries.length; i++ ) {
				var entry = entries[ i ];
				if ( ! entry.isIntersecting ) {
					continue;
				}
				entry.target.setAttribute( 'data-in-view', '' );
				io.unobserve( entry.target );
			}
		}, { rootMargin: '200px 0px' } );

		grids.forEach( function ( grid ) {
			grid.querySelectorAll( ITEM_SEL ).forEach( function ( item ) {
				io.observe( item );
			} );
			attachDelegated( grid );
		} );
	}

	function attachDelegated( grid ) {
		var active = null;

		grid.addEventListener( 'mouseover', function ( e ) {
			var card = e.target.closest( CARD_SEL );
			if ( ! card || active === card ) {
				return;
			}
			if ( ! card.parentElement || ! card.parentElement.hasAttribute( 'data-in-view' ) ) {
				return;
			}
			var url = card.dataset.trailerUrl || card.dataset.storyboard || '';
			if ( ! url ) {
				return;
			}
			var explicitType = card.dataset.trailerType || '';
			var inferredType = ( ! card.dataset.trailerUrl && card.dataset.storyboard ) ? 'image' : '';
			var type = explicitType || inferredType;
			teardown( active );
			mount( card, url, type );
			active = card;
		} );

		grid.addEventListener( 'mouseout', function ( e ) {
			var card = e.target.closest( CARD_SEL );
			if ( ! card || active !== card ) {
				return;
			}
			if ( e.relatedTarget && card.contains( e.relatedTarget ) ) {
				return;
			}
			teardown( active );
			active = null;
		} );
	}

	function mount( card, url, type ) {
		var wrap   = card.querySelector( '.ktube-card__thumb-wrap' );
		var poster = wrap && wrap.querySelector( '.ktube-card__thumb' );
		if ( ! wrap || ! poster ) {
			return;
		}
		poster.dataset.ktubePrevSrc    = poster.getAttribute( 'src' ) || '';
		poster.dataset.ktubePrevSrcset = poster.getAttribute( 'srcset' ) || '';
		poster.removeAttribute( 'srcset' );
		poster.src = url;
		card._ktubeMode = 'image';
		if ( type === 'image' ) {
			return;
		}
		var REDUCED = typeof window !== 'undefined' && window.matchMedia( MOTION_Q ).matches;
		if ( REDUCED ) {
			return;
		}
		var v = document.createElement( 'video' );
		v.className = 'ktube-card__trailer';
		v.muted = true;
		v.playsInline = true;
		v.loop = true;
		v.preload = 'auto';
		v.setAttribute( 'aria-hidden', 'true' );
		v.poster = poster.dataset.ktubePrevSrc || '';

		var source = document.createElement( 'source' );
		source.src = url;
		source.type = ( url.toLowerCase().endsWith( '.webm' ) ? 'video/webm' : 'video/mp4' );
		v.appendChild( source );
		wrap.appendChild( v );

		var playPromise = v.play();
		if ( playPromise && typeof playPromise.then === 'function' ) {
			playPromise.catch( function () {
				/* autoplay blocked; element stays as fallback poster swap. */
			} );
		}
		card._ktubeMode = 'video';
		card._ktubeEl = v;
	}

	function teardown( card ) {
		if ( ! card ) {
			return;
		}
		if ( card._ktubeMode === 'image' ) {
			var poster = card.querySelector( '.ktube-card__thumb' );
			if ( poster ) {
				if ( poster.dataset.ktubePrevSrc ) {
					poster.src = poster.dataset.ktubePrevSrc;
					delete poster.dataset.ktubePrevSrc;
				}
				if ( poster.dataset.ktubePrevSrcset ) {
					poster.setAttribute( 'srcset', poster.dataset.ktubePrevSrcset );
					delete poster.dataset.ktubePrevSrcset;
				}
			}
			card._ktubeMode = null;
			return;
		}
		if ( card._ktubeMode === 'video' && card._ktubeEl ) {
			var v = card._ktubeEl;
			card._ktubeEl = null;
			card._ktubeMode = null;
			try { v.pause(); } catch ( _e ) {}
			try { v.currentTime = 0; } catch ( _e ) {}
			try { v.removeAttribute( 'src' ); } catch ( _e ) {}
			try { v.load(); } catch ( _e ) {}
			if ( v.parentNode ) {
				v.parentNode.removeChild( v );
			}
		}
	}
} )();
