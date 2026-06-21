// vitest — lightbox-controller.js logic.
//
// The dialog uses HTMLDialogElement.showModal() which jsdom polyfills in
// setup.js. We drive attachGallery directly and inspect the resulting
// dialog element + focus behavior.

// eslint-disable-next-line import/extensions
import { attachGallery } from '../../assets/js/lightbox-controller.js';

function buildGallery( count ) {
	// Clean slate so previous tests' dialog + gallery are gone. Otherwise
	// document.querySelector('dialog.ktube-lightbox') picks up stale DOM
	// and dataset.index reads become undefined.
	document.body.innerHTML = '';
	const gallery = document.createElement( 'div' );
	gallery.className = 'ktube-lightbox-gallery';
	for ( let i = 0; i < count; i++ ) {
		const t = document.createElement( 'button' );
		t.className = 'ktube-lightbox-trigger';
		t.dataset.index = String( i );
		t.dataset.fullSrc = `https://example.test/img-${ i + 1 }.jpg`;
		const img = document.createElement( 'img' );
		img.alt = `image ${ i + 1 } alt`;
		t.appendChild( img );
		gallery.appendChild( t );
	}
	document.body.appendChild( gallery );
	return gallery;
}

describe( 'lightbox-controller arrow navigation', () => {
	test( 'arrow right advances and wraps modulo sources.length', () => {
		const gallery = buildGallery( 3 );
		attachGallery( gallery );
		const trigger = gallery.querySelectorAll( '.ktube-lightbox-trigger' )[ 2 ];
		const focusSpy = vi.spyOn( trigger, 'focus' );
		trigger.click(); // opens at index 2 (set via dataset.index)

		const dialog = document.querySelector( 'dialog.ktube-lightbox' );
		expect( dialog.open ).toBe( true );
		expect( dialog.dataset.index ).toBe( '2' );

		// Simulate ArrowRight twice; current=2 → next=0 → next=1
		document.dispatchEvent( new KeyboardEvent( 'keydown', { key: 'ArrowRight' } ) );
		expect( dialog.dataset.index ).toBe( '0' );
		document.dispatchEvent( new KeyboardEvent( 'keydown', { key: 'ArrowRight' } ) );
		expect( dialog.dataset.index ).toBe( '1' );

		// Wrap again: 1 → 2 → 0 (after enough presses).
		document.dispatchEvent( new KeyboardEvent( 'keydown', { key: 'ArrowRight' } ) );
		expect( dialog.dataset.index ).toBe( '2' );
		document.dispatchEvent( new KeyboardEvent( 'keydown', { key: 'ArrowRight' } ) );
		expect( dialog.dataset.index ).toBe( '0' );
		expect( focusSpy ).not.toHaveBeenCalled();

		dialog.close();
	} );

	test( 'arrow left wraps backward modulo sources.length', () => {
		const gallery = buildGallery( 3 );
		attachGallery( gallery );
		gallery.querySelectorAll( '.ktube-lightbox-trigger' )[ 0 ].click(); // open at 0

		const dialog = document.querySelector( 'dialog.ktube-lightbox' );
		expect( dialog.dataset.index ).toBe( '0' );

		document.dispatchEvent( new KeyboardEvent( 'keydown', { key: 'ArrowLeft' } ) );
		// -1 → wraps to 2
		expect( dialog.dataset.index ).toBe( '2' );

		document.dispatchEvent( new KeyboardEvent( 'keydown', { key: 'ArrowLeft' } ) );
		// 2 → 1
		expect( dialog.dataset.index ).toBe( '1' );

		dialog.close();
	} );

	test( 'arrow key events are ignored when dialog is closed', () => {
		const gallery = buildGallery( 2 );
		attachGallery( gallery );
		gallery.querySelectorAll( '.ktube-lightbox-trigger' )[ 0 ].click();
		const dialog = document.querySelector( 'dialog.ktube-lightbox' );
		dialog.close();

		const before = dialog.dataset.index;
		document.dispatchEvent( new KeyboardEvent( 'keydown', { key: 'ArrowRight' } ) );
		// After close the listener still exists but the guard rejects it.
		expect( dialog.dataset.index ).toBe( before );
	} );
} );

describe( 'lightbox-controller focus return', () => {
	test( 'closing the dialog returns focus to the trigger', () => {
		const gallery = buildGallery( 2 );
		attachGallery( gallery );
		const trigger = gallery.querySelectorAll( '.ktube-lightbox-trigger' )[ 1 ];
		const focusSpy = vi.spyOn( trigger, 'focus' );
		trigger.click();

		const dialog = document.querySelector( 'dialog.ktube-lightbox' );
		expect( dialog.dataset.index ).toBe( '1' );

		dialog.close(); // fires close event → returnFocus()

		expect( focusSpy ).toHaveBeenCalled();
		expect( dialogImage()?.src ).toBe( '' );
		expect( dialogImage()?.alt ).toBe( '' );

		function dialogImage() {
			return dialog.querySelector( 'img.ktube-lightbox__image' );
		}
	} );

	test( 'close clears the dataset.index so a future open restarts at 0', () => {
		const gallery = buildGallery( 2 );
		attachGallery( gallery );
		gallery.querySelectorAll( '.ktube-lightbox-trigger' )[ 1 ].click();
		const dialog = document.querySelector( 'dialog.ktube-lightbox' );
		dialog.close();
		expect( dialog.dataset.index ).toBeUndefined();
	} );
} );
