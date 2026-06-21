<?php
/**
 * ktube_sanitize_theme_default — accept only 'auto' / 'light' / 'dark'
 * after sanitize_key; anything else falls back to 'auto'.
 */
class SanitizeThemeDefaultTest extends \PHPUnit\Framework\TestCase {

	public function setUp(): void {
		reset_theme_mods_test();
	}

	public function test_three_valid_values_pass_through(): void {
		$this->assertSame( 'auto',  ktube_sanitize_theme_default( 'auto' ) );
		$this->assertSame( 'light', ktube_sanitize_theme_default( 'light' ) );
		$this->assertSame( 'dark',  ktube_sanitize_theme_default( 'dark' ) );
	}

	public function test_uppercase_is_normalized(): void {
		$this->assertSame( 'auto',  ktube_sanitize_theme_default( 'AUTO' ) );
		$this->assertSame( 'light', ktube_sanitize_theme_default( 'Light' ) );
	}

	public function test_invalid_value_falls_back_to_auto(): void {
		$this->assertSame( 'auto', ktube_sanitize_theme_default( 'rainbow' ) );
		$this->assertSame( 'auto', ktube_sanitize_theme_default( 'sepia' ) );
	}

	public function test_non_string_input_falls_back_to_auto(): void {
		$this->assertSame( 'auto', ktube_sanitize_theme_default( 1 ) );
		$this->assertSame( 'auto', ktube_sanitize_theme_default( null ) );
		$this->assertSame( 'auto', ktube_sanitize_theme_default( array() ) );
	}

	public function test_empty_string_falls_back_to_auto(): void {
		$this->assertSame( 'auto', ktube_sanitize_theme_default( '' ) );
	}
}
