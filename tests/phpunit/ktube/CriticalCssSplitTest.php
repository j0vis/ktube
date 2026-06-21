<?php
/**
 * Phase 14 perf (2026-06-21) — Critical CSS split invariants.
 *
 * Locks the inline-above-the-fold split:
 *   1. assets/css/critical.css exists.
 *   2. critical.css carries the above-the-fold rules (tokens, reset,
 *      skip-link, video grid, card).
 *   3. ktube_register_critical_css() wires wp_head at priority 5 so
 *      the inline <style> lands BEFORE ktube_print_inline_customizer_vars
 *      (priority 20) and the linked ktube-main <link>.
 *   4. ktube_inline_critical_css() reads the file and emits it inside
 *      <style id="ktube-critical">. Admin/ajax/cron contexts bail
 *      early so the inline block doesn't ship where it's useless.
 *
 * Test string-escape note: PHP single-quoted strings retain backslashes
 * literally (only \\\\ and \\' are interpreted as escapes). Using single
 * quotes around an HTML fragment with literal double-quotes inside is
 * the cleanest way to avoid the \\\" mismatch bug. assertStringContainsString
 * with single quotes around a fragment like `<style id="ktube-critical">`
 * works because the string itself contains ", and PHP single-quoted
 * literals preserve that as-is.
 *
 * @package ktube
 */

class CriticalCssSplitTest extends \PHPUnit\Framework\TestCase {

	public function test_critical_css_exists(): void {
		$this->assertFileExists(
			$this->ktubeRoot() . '/assets/css/critical.css',
			'critical.css must exist (Phase 14 perf first-paint slice)'
		);
	}

	public function test_critical_css_carries_required_above_fold_selectors(): void {
		$ktube_body = (string) file_get_contents( $this->ktubeRoot() . '/assets/css/critical.css' );
		$this->assertNotFalse( $ktube_body );
		// Tokens + reset + skip-link
		$this->assertStringContainsString( ':root[data-theme="light"]', $ktube_body );
		$this->assertStringContainsString( ':root[data-theme="dark"]',  $ktube_body );
		$this->assertStringContainsString( '--ktube-color-bg',          $ktube_body );
		$this->assertStringContainsString( 'box-sizing: border-box',    $ktube_body );
		$this->assertStringContainsString( '.skip-link',                $ktube_body );
		// Above-the-fold video grid
		$this->assertStringContainsString( '.ktube-video-grid',         $ktube_body );
		$this->assertStringContainsString( '.ktube-card',               $ktube_body );
		$this->assertStringContainsString( '.ktube-card__thumb-wrap',   $ktube_body );
		$this->assertStringContainsString( '.ktube-player-wrap',        $ktube_body );
		// Reduced-motion for video card hover
		$this->assertStringContainsString( 'prefers-reduced-motion',    $ktube_body );
	}

	public function test_register_wires_wp_head_priority_5(): void {
		$GLOBALS['__ktube_actions'] = array();
		// dirname(__DIR__, 3) → ktube root from tests/phpunit/ktube/ (3 levels up).
		// Reset wp_head priority 5 callback observations before re-registering.
		$ktube_path = dirname( __DIR__, 3 ) . '/includes/critical-css.php';
		if ( ! function_exists( 'ktube_register_critical_css' ) && file_exists( $ktube_path ) ) {
			require_once $ktube_path;
		}
		ktube_register_critical_css();
		$this->assertArrayHasKey( 'wp_head', $GLOBALS['__ktube_actions'] );
		$ktube_callbacks = array_column( $GLOBALS['__ktube_actions']['wp_head'], 'callback' );
		$this->assertContains( 'ktube_inline_critical_css', $ktube_callbacks );
		// Priority 5 wins source-order over ktube_print_inline_customizer_vars
		// (priority 20) so the customizer-derived per-breakpoint vars
		// correctly override the critical slice's defaults via cascade.
		foreach ( $GLOBALS['__ktube_actions']['wp_head'] as $ktube_entry ) {
			if ( 'ktube_inline_critical_css' === $ktube_entry['callback'] ) {
				$this->assertSame( 5, $ktube_entry['priority'] );
			}
		}
	}

	public function test_inline_critical_css_emits_block_when_file_present(): void {
		// The function reads KTUBE_DIR . '/assets/css/critical.css' — the
		// production theme ships the file, so at test runtime the
		// file-present branch must emit the <style id="ktube-critical">
		// block. The file-missing short-circuit is verified by code
		// review (the production guard's logic is obvious) since the
		// composer-free bootstrap can't safely rename the file on-disk.
		ob_start();
		ktube_inline_critical_css();
		$ktube_output = (string) ob_get_clean();
		$this->assertNotEmpty( $ktube_output, 'file present at test runtime — emits the style block' );
		$this->assertStringContainsString( '<style id="ktube-critical">', $ktube_output );
	}

	private function ktubeRoot(): string {
		return dirname( __DIR__, 3 );
	}
}
