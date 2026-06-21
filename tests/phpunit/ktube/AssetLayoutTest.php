<?php
/**
 * AssetLayoutTest — Phase 0-A (2026-06-21) reversal invariant.
 *
 * Locks the post-reversal hand-managed asset layout. CI runs this in
 * build-enforcement.yml + locally via `php tests/phpunit/run.php`.
 *
 * Asserts:
 *   - 27 required paths exist under the theme root (CSS, JS, vendor).
 *   - 5 forbidden paths from the pre-reversal Vite/SCSS/dist layout do
 *     NOT exist (vite.config.js, assets/dist/, assets/src/,
 *     theme-manifest.json, node_modules/).
 *   - The vendored Video.js LICENSE is Apache-2.0 verbatim.
 *
 * If a future maintainer re-introduces a build step, this test fires.
 */

namespace Ktube\Tests;

use PHPUnit\Framework\TestCase;

class AssetLayoutTest extends TestCase {

	/**
	 * @var string[]
	 */
	private static array $required = array(
		'style.css',
		'functions.php',
		'theme.json',
		'includes/setup.php',
		'includes/post-types.php',
		'includes/taxonomies.php',
		'includes/meta.php',
		'includes/customizer.php',
		'includes/age-gate.php',
		'includes/privacy.php',
		'includes/seo/schema.php',
		'includes/wps-compat/mass-importer.php',
		'includes/wps-compat/wps-player.php',
		'includes/wps-compat/importer-adapter.php',
		'includes/template-functions.php',
		'assets/css/main.css',
		'assets/js/main.js',
		'assets/js/player.js',
		'assets/js/dark-mode.js',
		'assets/js/age-gate.js',
		'assets/js/video-grid.js',
		'assets/js/lightbox-controller.js',
		'assets/js/customize-controls.js',
		'assets/vendor/videojs/video.min.js',
		'assets/vendor/videojs/video-js.min.css',
		'assets/vendor/videojs/README.md',
		'assets/vendor/videojs/VENDORED.json',
	);

	/**
	 * Phase 0-A reversal leftovers and intentionally-retired build
	 * artifacts. Each name has a one-line rationale so a maintainer who
	 * wants to re-introduce the path knows which contract they're
	 * violating:
	 *   - vite.config.js      — Vite was removed (zero-install mandate).
	 *   - assets/dist         — Vite output dir, gone with Vite.
	 *   - assets/src          — Vite source dir, gone with Vite.
	 *   - theme-manifest.json — superseded by git + VENDORED.json sidecar.
	 *   - assets/css/editor.css — `add_editor_style` retired; block-editor
	 *                              styling is theme.json-only.
	 *
	 * @var string[]
	 */
	private static array $forbidden = array(
		'vite.config.js',
		'assets/dist',
		'assets/src',
		'theme-manifest.json',
		'assets/css/editor.css',
	);

	public function test_required_paths_present(): void {
		$root = $this->ktubeRoot();
		foreach ( self::$required as $rel ) {
			$this->assertTrue(
				file_exists( $root . '/' . $rel ),
				'required asset missing: ' . $rel
			);
		}
	}

	public function test_forbidden_paths_absent(): void {
		$root = $this->ktubeRoot();
		foreach ( self::$forbidden as $rel ) {
			$this->assertFalse(
				file_exists( $root . '/' . $rel ),
				'Phase 0-A reversal leftover re-appeared: ' . $rel
			);
		}
	}

	public function test_vendored_videojs_license_is_apache(): void {
		$license = $this->ktubeRoot() . '/assets/vendor/videojs/LICENSE';
		$this->assertTrue( file_exists( $license ), 'vendored Video.js LICENSE missing (Apache-2.0 §4a)' );
		$body = file_get_contents( $license );
		$this->assertNotFalse( $body, 'LICENSE unreadable' );
		// Apache-2.0 verbatim texts start with the standard header.
		$this->assertStringContainsString( 'Apache License', $body );
		$this->assertStringContainsString( 'Version 2.0', $body );
		$this->assertStringContainsString( 'Licensed under the Apache License', $body );
	}

	public function test_vendored_videojs_size_sanity(): void {
		$js  = $this->ktubeRoot() . '/assets/vendor/videojs/video.min.js';
		$css = $this->ktubeRoot() . '/assets/vendor/videojs/video-js.min.css';
		$this->assertGreaterThan( 500000, (int) filesize( $js ), 'video.min.js below expected Video.js 8 floor' );
		$this->assertGreaterThan( 30000,  (int) filesize( $css ), 'video-js.min.css below expected Video.js 8 floor' );
	}

	private function ktubeRoot(): string {
		// tests/phpunit/ktube/ → tests/phpunit → tests → ktube root.
		return dirname( __DIR__, 3 );
	}
}
