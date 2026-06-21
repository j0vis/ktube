/*
 * ktube/lightbox-controller.js — vanilla lightbox for single-photo + archive-photo.
 *
 * Phase 0-A (2026-06-21): hand-shipped, no bundler. The previous round
 * exported from inside an IIFE wrapper — that's a SyntaxError under
 * Node ESM because `export` is only valid at module top-level. This
 * rewrite lifts the functions to module top-level so the canonical
 * `export { init, attachGallery }` works.
 *
 * Browser-side impact: top-level function declarations attached via
 * <script> (no `type="module"`) leak onto window — that's been a
 * hard fact of classic-script semantics. The names are ktube-namespaced
 * (`ktubeLightbox*` consumers read `window.ktubeLightbox.init`) so they
 * don't collide with anything else on a real page.
 *
 * Behavior (unchanged):
 *   - HTMLDialogElement for native focus trap + Esc to close + focus return.
 *   - Delegated listener on .ktube-lightbox-gallery.
 *   - ArrowLeft/Right navigate while open via the global keydown handler.
 *   - prefers-reduced-motion gating is at the CSS layer.
 */

const KTUBE_LIGHTBOX_GALLERY_SEL = '.ktube-lightbox-gallery';
const KTUBE_LIGHTBOX_TRIGGER_SEL = '.ktube-lightbox-trigger';

function ktubeLightboxInit() {
	if ( typeof document === 'undefined' ) {
		return;
	}
	const galleries = document.querySelectorAll( KTUBE_LIGHTBOX_GALLERY_SEL );
	for ( let i = 0; i < galleries.length; i++ ) {
		ktubeLightboxAttachGallery( galleries[ i ] );
	}
}

function ktubeLightboxAttachGallery( gallery ) {
	let dialog = null;
	let dialogImage = null;
	let prevFocus = null;
	let sources = [];

	gallery.addEventListener( 'click', function ( e ) {
		const trigger = e.target.closest( KTUBE_LIGHTBOX_TRIGGER_SEL );
		if ( ! trigger ) {
			return;
		}
		sources = Array.prototype.map.call(
			gallery.querySelectorAll( KTUBE_LIGHTBOX_TRIGGER_SEL ),
			function ( node ) {
				const img = node.querySelector( 'img' );
				return {
					full: node.dataset.fullSrc || '',
					alt : ( img && img.alt ) || '',
				};
			}
		);
		const startIndex = parseInt( trigger.dataset.index || '0', 10 );
		prevFocus = trigger;
		openDialog( startIndex );
	} );

	function openDialog( index ) {
		if ( ! sources.length ) {
			return;
		}
		ensureDialog();
		setImage( index );
		try {
			dialog.showModal();
		} catch ( _e ) {
			dialog.close();
			dialog.showModal();
		}
	}

	function ensureDialog() {
		if ( dialog ) {
			return;
		}
		dialog = document.createElement( 'dialog' );
		dialog.className = 'ktube-lightbox';
		dialog.setAttribute( 'aria-modal', 'true' );

		const closeBtn = btn( 'ktube-lightbox__close', 'Close', '\u00d7' );
		const prevBtn  = btn( 'ktube-lightbox__nav ktube-lightbox__nav--prev', 'Previous image', '\u2190' );
		const nextBtn  = btn( 'ktube-lightbox__nav ktube-lightbox__nav--next', 'Next image', '\u2192' );

		dialogImage = document.createElement( 'img' );
		dialogImage.className = 'ktube-lightbox__image';

		dialog.appendChild( closeBtn );
		dialog.appendChild( prevBtn );
		dialog.appendChild( dialogImage );
		dialog.appendChild( nextBtn );

		closeBtn.addEventListener( 'click', function () { dialog.close(); } );
		prevBtn.addEventListener( 'click',  function () { navigate( -1 ); } );
		nextBtn.addEventListener( 'click',  function () { navigate(  1 ); } );

		dialog.addEventListener( 'close', returnFocus );

		document.body.appendChild( dialog );
	}

	function returnFocus() {
		if ( prevFocus && typeof prevFocus.focus === 'function' ) {
			prevFocus.focus();
		}
		prevFocus = null;
		if ( dialogImage ) {
			dialogImage.removeAttribute( 'src' );
			dialogImage.removeAttribute( 'srcset' );
			dialogImage.alt = '';
		}
		delete dialog.dataset.index;
	}

	function btn( className, ariaLabel, text ) {
		const b = document.createElement( 'button' );
		b.type = 'button';
		b.className = className;
		b.setAttribute( 'aria-label', ariaLabel );
		b.textContent = text;
		return b;
	}

	function navigate( delta ) {
		if ( ! sources.length || ! dialog ) {
			return;
		}
		const current = parseInt( dialog.dataset.index || '0', 10 );
		const nextIndex = ( current + delta + sources.length ) % sources.length;
		setImage( nextIndex );
	}

	function setImage( index ) {
		if ( ! sources[ index ] ) {
			return;
		}
		dialogImage.src = sources[ index ].full || '';
		dialogImage.alt = sources[ index ].alt  || '';
		dialog.dataset.index = String( index );
	}

	if ( typeof document !== 'undefined' ) {
		document.addEventListener( 'keydown', function ( e ) {
			if ( ! dialog || ! dialog.open ) {
				return;
			}
			if ( e.key === 'ArrowLeft' ) {
				e.preventDefault();
				navigate( -1 );
			} else if ( e.key === 'ArrowRight' ) {
				e.preventDefault();
				navigate( 1 );
			}
		} );
	}

	return { dialog: dialog, openDialog: openDialog, navigate: navigate, getDialogImage: function () { return dialogImage; } };
}

// Auto-init when loaded as a plain WP <script> enqueue. Guard the
// DOMContentLoaded listener behind `typeof wp !== 'undefined'` so vitest +
// jsdom (which has no WP global) and node ESM consumers don't auto-boot
// the gallery scan — those contexts drive the dialog UI directly via
// the import-and-call pattern in tests. The `__ktubeLightboxAutoBooted`
// guard adds further defense in case the script is re-enqueued by a
// customizer-driven replay on the same page.
if ( typeof document !== 'undefined' && typeof window !== 'undefined' ) {
	window.ktubeLightbox = {
		init: ktubeLightboxInit,
		attachGallery: ktubeLightboxAttachGallery,
	};
	if ( typeof wp !== 'undefined' && ! window.__ktubeLightboxAutoBooted ) {
		window.__ktubeLightboxAutoBooted = true;
		document.addEventListener( 'DOMContentLoaded', ktubeLightboxInit );
	}
}

// ESM named exports — vitest+jsdom read these as the canonical module
// surface. The browser-side window.ktubeLightbox handle above remains
// the runtime-touchable surface for <script> enqueues (no type=module).
export {
	ktubeLightboxInit as init,
	ktubeLightboxAttachGallery as attachGallery,
};
