<?php
/**
 * Customizer hardening — ktube_print_inline_customizer_vars gating.
 *
 * Verifies the belt-and-suspenders dual check (BOTH 'registered' AND
 * 'enqueued') so a MU-plugin that calls `wp_dequeue_style('ktube-main')`
 * after enqueue does NOT emit an orphan <style> block against an
 * unreferenced token set. (to-do.md §5.3 — closed 2026-06-21.)
 *
 * @package ktube
 */

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../bootstrap.php';

class PrintInlineCustomizerVarsTest extends TestCase {

	public function setUp(): void {
		$GLOBALS['__ktube_theme_mods']   = array();
		$GLOBALS['__ktube_style_states'] = array();
		$GLOBALS['__ktube_inline_styles'] = array();
	}

	public function test_pipes_css_when_both_registered_and_enqueued(): void {
		set_style_state_test( 'ktube-main', 'registered', true );
		set_style_state_test( 'ktube-main', 'enqueued',  true );
		ktube_print_inline_customizer_vars();
		$this->assertNotEmpty( $GLOBALS['__ktube_inline_styles'], 'should pipe inline CSS when both flags are true' );
		$this->assertSame( 'ktube-main', $GLOBALS['__ktube_inline_styles'][0]['handle'] );
	}

	public function test_noop_when_only_registered_but_not_enqueued(): void {
		// MU-plugin dequeue scenario: registered but dequeued after enqueue.
		set_style_state_test( 'ktube-main', 'registered', true );
		ktube_print_inline_customizer_vars();
		$this->assertEmpty( $GLOBALS['__ktube_inline_styles'], 'should noop when only registered (no enqueued)' );
	}

	public function test_noop_when_only_enqueued_but_not_registered(): void {
		// Defensive parity: enqueued without registration is a logical
		// contradiction in WP, but the function should still noop.
		set_style_state_test( 'ktube-main', 'enqueued', true );
		ktube_print_inline_customizer_vars();
		$this->assertEmpty( $GLOBALS['__ktube_inline_styles'], 'should noop when only enqueued (no registered)' );
	}

	public function test_noop_when_neither_registered_nor_enqueued(): void {
		ktube_print_inline_customizer_vars();
		$this->assertEmpty( $GLOBALS['__ktube_inline_styles'], 'should noop when neither flag is true' );
	}

	public function test_piped_css_contains_root_block_with_token_defaults(): void {
		set_style_state_test( 'ktube-main', 'registered', true );
		set_style_state_test( 'ktube-main', 'enqueued',  true );
		ktube_print_inline_customizer_vars();
		$ktube_css = $GLOBALS['__ktube_inline_styles'][0]['css'] ?? '';
		$this->assertStringContainsString( ':root {',                       $ktube_css );
		$this->assertStringContainsString( '--ktube-grid-cols-desktop:',     $ktube_css );
		$this->assertStringContainsString( ':root[data-theme="dark"]',      $ktube_css, 'should include dark theme block' );
		$this->assertStringContainsString( '--ktube-color-link:',           $ktube_css );
	}

	public function test_piped_css_reflects_customizer_settings(): void {
		set_theme_mod_test( 'ktube_grid_cols_desktop', 5 );
		set_theme_mod_test( 'ktube_color_link_light',   '#ff00ff' );
		set_style_state_test( 'ktube-main', 'registered', true );
		set_style_state_test( 'ktube-main', 'enqueued',  true );
		ktube_print_inline_customizer_vars();
		$ktube_css = $GLOBALS['__ktube_inline_styles'][0]['css'] ?? '';
		$this->assertStringContainsString( '--ktube-grid-cols-desktop: 5;', $ktube_css, 'should reflect customizer-set grid cols' );
		$this->assertStringContainsString( '--ktube-color-link:#ff00ff;',   $ktube_css, 'should reflect customizer-set color link' );
	}

	public function test_per_setting_checksum_is_an_sha256_hex(): void {
		set_style_state_test( 'ktube-main', 'registered', true );
		set_style_state_test( 'ktube-main', 'enqueued',  true );
		ktube_print_inline_customizer_vars();
		$ktube_checksums = ktube_compute_customizer_checksums();
		$this->assertMatchesRegularExpression( '/^[a-f0-9]{64}$/', $ktube_checksums['total'], 'total checksum should be 64-char hex' );
		foreach ( $ktube_checksums['per_key'] as $ktube_key => $ktube_hash ) {
			$this->assertMatchesRegularExpression( '/^[a-f0-9]{64}$/', $ktube_hash, "$ktube_key checksum should be 64-char hex" );
		}
	}

	public function test_piped_css_does_not_emit_zero_when_token_deserted(): void {
		// Defense-in-depth: if a hand-edited DB row sets ktube_grid_cols_desktop
		// to an out-of-range value, the inline CSS should still clamp + emit
		// a sensible value (not "0" or the bad value).
		set_theme_mod_test( 'ktube_grid_cols_desktop', 999 );
		set_style_state_test( 'ktube-main', 'registered', true );
		set_style_state_test( 'ktube-main', 'enqueued',  true );
		ktube_print_inline_customizer_vars();
		$ktube_css = $GLOBALS['__ktube_inline_styles'][0]['css'] ?? '';
		$this->assertStringNotContainsString( '--ktube-grid-cols-desktop: 0;',    $ktube_css );
		$this->assertStringNotContainsString( '--ktube-grid-cols-desktop: 999;',  $ktube_css, 'should clamp out-of-range values on read' );
	}
}
