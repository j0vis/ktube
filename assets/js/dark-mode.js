/*
 * ktube dark-mode controller — Phase 5 wire-up + Phase 7b auto-bind of
 * <button class="ktube-theme-toggle">. The button markup ships in
 * header.php; this controller wires click → toggle + keeps the aria-label
 * in lock-step with the active theme so screen readers announce the correct
 * "Switch to X" intent.
 *
 * Phase 0-A reversal (2026-06-21): this file is hand-shipped (not Vite
 * compiled). The two `export` keywords that remain at the bottom are
 * inert in a non-module <script> enqueue — they're syntax errors only
 * when parsed as a real ES module. The init DOMContentLoaded listener
 * still binds the toggle and listens for the ktube:themechange event.
 *
 * Sources, in resolution priority:
 *   1. localStorage 'ktube-theme' (returning visitor's toggle choice).
 *   2. Customizer 'ktube_theme_default' setting (read by PHP inline
 *      boot in functions.php::ktube_inline_theme_bootstrap, applied via
 *      <html data-theme="..."> before this script runs).
 *   3. matchMedia('(prefers-color-scheme: dark)') — system preference.
 */
( function ( $storageKey, $changeEvent, $toggleSel ) {
	'use strict';
	var getTheme = function () {
		return document.documentElement.getAttribute( 'data-theme' ) || 'light';
	};
	var getSavedDefault = function () {
		if ( typeof window === 'undefined' ) {
			return undefined;
		}
		var data = window.ktubeThemeData;
		if ( data && ( data.default === 'auto' || data.default === 'light' || data.default === 'dark' ) ) {
			return data.default;
		}
		return undefined;
	};
	var systemPref = function () {
		if ( typeof window === 'undefined' || ! window.matchMedia ) {
			return 'light';
		}
		return window.matchMedia( '(prefers-color-scheme: dark)' ).matches ? 'dark' : 'light';
	};
	var nextLabelFor = function ( theme ) {
		var next      = theme === 'dark' ? 'light' : 'dark';
		var visible   = next.charAt( 0 ).toUpperCase() + next.slice( 1 );
		var aria      = 'Switch to ' + next + ' mode';
		return { next: next, aria: aria, visible: visible };
	};
	var setTheme = function ( theme ) {
		if ( theme !== 'light' && theme !== 'dark' ) {
			return;
		}
		document.documentElement.setAttribute( 'data-theme', theme );
		document.documentElement.style.colorScheme = theme;
		try {
			localStorage.setItem( $storageKey, theme );
		} catch ( _e ) { /* private-mode */ }
		document.dispatchEvent( new CustomEvent( $changeEvent, { detail: { theme: theme } } ) );
	};
	var toggleTheme = function () {
		setTheme( getTheme() === 'dark' ? 'light' : 'dark' );
	};

	function paintToggle( el ) {
		var theme = getTheme();
		var info  = nextLabelFor( theme );
		el.setAttribute( 'aria-pressed', String( theme === 'dark' ) );
		el.setAttribute( 'aria-label', info.aria );
		el.setAttribute( 'title', info.aria );
		var label = el.querySelector( '.ktube-theme-toggle__label' );
		if ( label ) {
			label.textContent = info.visible;
		}
	}
	function bindToggleButton( el ) {
		if ( ! el || el.dataset.ktubeBound === '1' ) {
			return;
		}
		el.dataset.ktubeBound = '1';
		paintToggle( el );
		el.addEventListener( 'click', function ( ev ) {
			ev.preventDefault();
			toggleTheme();
			paintToggle( el );
		} );
	}
	function paintAllToggles() {
		var toggles = document.querySelectorAll( $toggleSel );
		for ( var i = 0; i < toggles.length; i++ ) {
			paintToggle( toggles[ i ] );
		}
	}

	// React to a Customizer-driven theme change so preview state stays live.
	if ( typeof window !== 'undefined' ) {
		window.addEventListener( 'message', function ( ev ) {
			var data = ev.data;
			if ( ! data || typeof data !== 'object' || data.source !== 'ktube-customizer' ) {
				return;
			}
			if ( data.type === 'theme' && ( data.value === 'light' || data.value === 'dark' ) ) {
				setTheme( data.value );
			}
		} );
	}
	if ( typeof document !== 'undefined' ) {
		document.addEventListener( 'DOMContentLoaded', function () {
			var toggles = document.querySelectorAll( $toggleSel );
			for ( var j = 0; j < toggles.length; j++ ) {
				bindToggleButton( toggles[ j ] );
			}
			paintAllToggles();
		} );
		document.addEventListener( $changeEvent, paintAllToggles );
	}

	// Exports — harmless in IIFE wrapper (no consumer reads them in
	// production); preserved for vitest tests + future <script type=module>
	// adoption if a maintainer wires that up.
	if ( typeof window !== 'undefined' ) {
		window.ktubeDarkMode = { setTheme: setTheme, toggleTheme: toggleTheme, getTheme: getTheme };
	}
}( 'ktube-theme', 'ktube:themechange', '.ktube-theme-toggle' ) );
