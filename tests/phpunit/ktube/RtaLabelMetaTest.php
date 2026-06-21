<?php
/**
 * Phase 6-B — RTA label format CI smoke test.
 *
 * Asserts the literal <meta name="rating" content="RTA-5042-1996-1400-1577-RTA">
 * output of ktube_render_rta_meta(). The label format matches the ASACP RTA
 * register; downstream consumers (RTA-aware browsers/extensions) recognize
 * the site as Restricted to Adults only when the literal string is present
 * in <head>. This test fails CI on any drift away from the canonical format.
 *
 * @package ktube
 */

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../bootstrap.php';

class RtaLabelMetaTest extends TestCase {

	public function setUp(): void {
		$GLOBALS['__ktube_theme_mods'] = array();
	}

	public function test_emits_nothing_when_rta_disabled(): void {
		// Default theme_mod state (false). Renderer must early-return.
		ob_start();
		ktube_render_rta_meta();
		$ktube_output = ob_get_clean();
		$this->assertSame( '', $ktube_output, 'no output when ktube_rta_enabled is unset/false' );
	}

	public function test_emits_canonical_rta_label_when_enabled(): void {
		set_theme_mod_test( 'ktube_rta_enabled', true );
		ob_start();
		ktube_render_rta_meta();
		$ktube_output = (string) ob_get_clean();
		// Exact byte-for-byte match (no whitespace drift allowed).
		$this->assertSame(
			'<meta name="rating" content="RTA-5042-1996-1400-1577-RTA">' . "\n",
			$ktube_output,
			'renderer must emit the canonical ASACP RTA register label verbatim'
		);
	}

	public function test_output_starts_with_meta_opening_tag(): void {
		set_theme_mod_test( 'ktube_rta_enabled', true );
		ob_start();
		ktube_render_rta_meta();
		$ktube_output = (string) ob_get_clean();
		$this->assertStringStartsWith( '<meta', $ktube_output );
	}

	public function test_output_contains_rating_name_attribute(): void {
		// name="rating" is the SEO/discovery signal that RTA-aware
		// search snippets + browser extensions match against.
		set_theme_mod_test( 'ktube_rta_enabled', true );
		ob_start();
		ktube_render_rta_meta();
		$ktube_output = (string) ob_get_clean();
		$this->assertStringContainsString( 'name="rating"', $ktube_output );
	}

	public function test_output_contains_asacp_register_label_value(): void {
		// "RTA-5042-1996-1400-1577-RTA" is the label registered with the
		// Association of Sites Advocating Child Protection (ASACP).
		// Any drift in this string breaks RTA-aware tooling downstream.
		set_theme_mod_test( 'ktube_rta_enabled', true );
		ob_start();
		ktube_render_rta_meta();
		$ktube_output = (string) ob_get_clean();
		$this->assertStringContainsString( 'RTA-5042-1996-1400-1577-RTA', $ktube_output );
	}

	public function test_output_has_trailing_newline_for_legibility(): void {
		set_theme_mod_test( 'ktube_rta_enabled', true );
		ob_start();
		ktube_render_rta_meta();
		$ktube_output = (string) ob_get_clean();
		$this->assertStringEndsWith( "\n", $ktube_output );
	}

	public function test_output_does_not_use_deprecated_http_equiv_format(): void {
		// Belt-and-suspenders guard: the previous format
		// (<meta http-equiv="RTA" content="restrict">) is the wrong shape;
		// if anyone reintroduces it, this test catches it.
		set_theme_mod_test( 'ktube_rta_enabled', true );
		ob_start();
		ktube_render_rta_meta();
		$ktube_output = (string) ob_get_clean();
		$this->assertStringNotContainsString( 'http-equiv="RTA"', $ktube_output, 'old http-equiv format must NOT be used' );
		$this->assertStringNotContainsString( 'content="restrict"',  $ktube_output, 'old "content=restrict" must NOT be used' );
	}

	public function test_emits_exactly_one_meta_tag_when_enabled(): void {
		// Sanity: no accidental double-emit when the function is invoked
		// twice in the same wp_head cycle (would be a regression).
		set_theme_mod_test( 'ktube_rta_enabled', true );
		ob_start();
		ktube_render_rta_meta();
		$ktube_output_a = (string) ob_get_clean();
		ob_start();
		ktube_render_rta_meta();
		$ktube_output_b = (string) ob_get_clean();
		$this->assertSame( $ktube_output_a, $ktube_output_b, 'two invocations under same gate state should be byte-identical' );
		// One meta tag per invocation — count substring.
		$this->assertSame( 1, substr_count( $ktube_output_a, 'RTA-5042-1996-1400-1577-RTA' ) );
	}
}
