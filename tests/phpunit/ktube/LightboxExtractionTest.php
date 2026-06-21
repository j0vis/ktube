<?php
/**
 * Phase 14 perf (2026-06-21) — Lightbox CSS extraction invariants.
 *
 * Locks the deferred-stylesheet split:
 *   1. assets/css/lightbox.css exists (filesystem required-path).
 *   2. lightbox.css carries the photo+lightbox rule selectors that
 *      single-photo.php + archive-photo.php templates need.
 *   3. assets/css/main.css no longer carries those rules (extraction
 *      succeeded — without this guard a future refactor could quietly
 *      re-include them and double the bytes on every non-photo page
 *      load).
 *   4. includes/setup.php's ktube_enqueue_lightbox() now enqueues a
 *      stylesheet via the `ktube-lightbox-css` handle (and STILL
 *      enqueues the `ktube-lightbox` script handle).
 *
 * @package ktube
 */

class LightboxExtractionTest extends \PHPUnit\Framework\TestCase {

	public function test_lightbox_css_exists(): void {
		$this->assertFileExists(
			$this->ktubeRoot() . '/assets/css/lightbox.css',
			'lightbox.css must exist (Phase 14 perf deferred-stylesheet split)'
		);
	}

	public function test_lightbox_css_carries_required_selectors(): void {
		$ktube_body = (string) file_get_contents( $this->ktubeRoot() . '/assets/css/lightbox.css' );
		$this->assertNotFalse( $ktube_body );
		$this->assertStringContainsString( '.ktube-photo-grid',  $ktube_body );
		$this->assertStringContainsString( '.ktube-photo-card',  $ktube_body );
		$this->assertStringContainsString( '.ktube-lightbox',     $ktube_body );
		$this->assertStringContainsString( '.ktube-lightbox-trigger', $ktube_body );
		$this->assertStringContainsString( '.ktube-lightbox__close', $ktube_body );
		$this->assertStringContainsString( '.ktube-lightbox__nav',   $ktube_body );
		$this->assertStringContainsString( 'prefers-reduced-motion',  $ktube_body );
	}

	public function test_main_css_no_longer_carries_lightbox_block(): void {
		$ktube_body = (string) file_get_contents( $this->ktubeRoot() . '/assets/css/main.css' );
		$this->assertNotFalse( $ktube_body );
		// .ktube-lightbox rules are the smoking gun — main.css MUST NOT
		// contain them after the Phase 14 perf extraction.
		$this->assertStringNotContainsString(
			'.ktube-lightbox {',
			$ktube_body,
			'main.css still carries .ktube-lightbox rule — extraction incomplete'
		);
		$this->assertStringNotContainsString(
			'.ktube-photo-card {',
			$ktube_body,
			'main.css still carries .ktube-photo-card rule — extraction incomplete'
		);
		// Section header was added in main.css above lightbox rules; check
		// the section marker is gone too.
		$this->assertStringNotContainsString(
			'Photos CPT + lightbox',
			$ktube_body,
			'main.css still has the Photos CPT + lightbox section header — section was carved incompletely'
		);
	}

	public function test_setup_enqueues_lightbox_stylesheet_handle(): void {
		$ktube_body = (string) file_get_contents( $this->ktubeRoot() . '/includes/setup.php' );
		$this->assertNotFalse( $ktube_body );
		// Regex form absorbs whitespace / indentation variability from
		// future refactors without breaking this assertion.
		$this->assertMatchesRegularExpression(
			'/wp_enqueue_style\(\s*\'ktube-lightbox-css\'/',
			$ktube_body,
			'ktube_enqueue_lightbox() must wp_enqueue_style ktube-lightbox-css on photo templates'
		);
		$this->assertStringContainsString(
			'assets/css/lightbox.css',
			$ktube_body,
			'ktube_enqueue_lightbox() source-of-truth path must reference the extracted stylesheet'
		);
		// Script handle kept for forward compat. Sanity-check that the JS
		// enqueue still uses 'ktube-lightbox' (script-side handle).
		$this->assertMatchesRegularExpression(
			'/wp_enqueue_script\(\s*\'ktube-lightbox\'/',
			$ktube_body,
			'ktube-lightbox script handle must remain for JS controller'
		);
	}

	public function test_lightbox_enqueue_gated_on_photo_template(): void {
		$ktube_body = (string) file_get_contents( $this->ktubeRoot() . '/includes/setup.php' );
		$this->assertNotFalse( $ktube_body );
		$this->assertStringContainsString(
			"is_singular( 'photo' ) || is_post_type_archive( 'photo' )",
			$ktube_body,
			'ktube_enqueue_lightbox() must gate on photo templates only — prevents css bytes on homepage'
		);
	}

	private function ktubeRoot(): string {
		// tests/phpunit/ktube/ → tests/phpunit → tests → ktube root.
		return dirname( __DIR__, 3 );
	}
}
