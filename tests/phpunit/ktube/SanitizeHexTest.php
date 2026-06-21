<?php
/**
 * ktube_sanitize_hex — accepts #abc and #abcdef, normalizes 3 → 6,
 * lowercases, rejects rgba/hsla/named/whitespace-only.
 */
class SanitizeHexTest extends \PHPUnit\Framework\TestCase {

	public function setUp(): void {
		reset_theme_mods_test();
	}

	public function test_three_char_hex_is_expanded_and_lowercased(): void {
		$this->assertSame( '#aabbcc', ktube_sanitize_hex( '#ABC' ) );
		$this->assertSame( '#ff00aa', ktube_sanitize_hex( '#F0a' ) );
	}

	public function test_six_char_hex_is_just_lowercased(): void {
		$this->assertSame( '#abcdef', ktube_sanitize_hex( '#ABCDEF' ) );
		$this->assertSame( '#18181b', ktube_sanitize_hex( '#18181b' ) );
	}

	public function test_six_char_hex_with_caps_is_lowercased(): void {
		$this->assertSame( '#abcdef', ktube_sanitize_hex( '#aBcDeF' ) );
	}

	public function test_rgba_string_is_rejected(): void {
		$this->assertSame( '', ktube_sanitize_hex( 'rgba(0,0,0,1)' ) );
	}

	public function test_hsla_string_is_rejected(): void {
		$this->assertSame( '', ktube_sanitize_hex( 'hsla(0,100%,50%,1)' ) );
	}

	public function test_named_color_is_rejected(): void {
		$this->assertSame( '', ktube_sanitize_hex( 'red' ) );
		$this->assertSame( '', ktube_sanitize_hex( 'transparent' ) );
	}

	public function test_whitespace_is_stripped_then_validated(): void {
		$this->assertSame( '#abcdef', ktube_sanitize_hex( '  #ABCDEF  ' ) );
		$this->assertSame( '', ktube_sanitize_hex( '   ' ) );
	}

	public function test_empty_string_returns_empty(): void {
		$this->assertSame( '', ktube_sanitize_hex( '' ) );
	}

	public function test_seven_char_hash_is_rejected(): void {
		$this->assertSame( '', ktube_sanitize_hex( '#abcdef0' ) );
	}

	public function test_two_char_hex_is_rejected(): void {
		$this->assertSame( '', ktube_sanitize_hex( '#ab' ) );
	}
}
