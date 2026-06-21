// vitest setup — runs before every test file. ESM-only; uses
// `import` (NOT `require` — which is undefined in ESM contexts and will
// throw under vitest).
//
// jsdom doesn't ship:
//   • HTMLDialogElement.showModal() / .close() / .open  → stubbed.
//   • matchMedia                                       → stubbed.
// jsdom DOES have crypto.subtle via Node's webcrypto, but only when we
// explicitly set `globalThis.crypto = webcrypto`. Without it, vitest
// reports `crypto is not defined`.

import { webcrypto as nodeWebCrypto } from 'node:crypto';

// Always ensure crypto.subtle is available, regardless of jsdom version.
if ( typeof globalThis.crypto === 'undefined' || ! globalThis.crypto.subtle ) {
	globalThis.crypto = nodeWebCrypto;
}

if ( typeof window !== 'undefined' ) {
	// jsdom's HTMLDialogElement doesn't implement showModal/close in older
	// versions and is missing the polyfill in newer ones too. Add minimal
	// stubs that flip `open` and dispatch a 'close' event so dialog-based
	// code paths in age-gate.js / lightbox-controller.js can be exercised.
	if ( typeof window.HTMLDialogElement !== 'undefined' ) {
		const proto = window.HTMLDialogElement.prototype;
		if ( ! proto.showModal ) {
			proto.showModal = function () {
				this.open = true;
			};
		}
		if ( ! proto.close ) {
			proto.close = function () {
				this.open = false;
				this.dispatchEvent( new Event( 'close' ) );
			};
		}
	}

	if ( typeof window.matchMedia !== 'function' ) {
		window.matchMedia = ( q ) => ( {
			matches: false,
			media: q,
			onchange: null,
			addListener: () => {},
			removeListener: () => {},
			addEventListener: () => {},
			removeEventListener: () => {},
			dispatchEvent: () => false,
		} );
	}
}
