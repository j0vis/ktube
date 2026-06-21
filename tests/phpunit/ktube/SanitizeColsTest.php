<?php
/**
 * ktube_sanitize_cols — clamp [min,max], default to min on non-numeric
 * input, integer-cast numeric strings, accept float-truncating ints.
 */
class SanitizeColsTest extends \PHPUnit\Framework\TestCase {

	public function setUp(): void {
		reset_theme_mods_test();
	}

	public function test_value_at_or_below_max_passes_through_or_clamps(): void {
		$this->assertSame( 3, ktube_sanitize_cols( 3, 1, 6 ) );
		// 5 with max=4 → clamped to 4 (NOT 5).
		$this->assertSame( 4, ktube_sanitize_cols( 5, 2, 4 ) );
	}

	public function test_value_below_min_is_clamped_up(): void {
		$this->assertSame( 2, ktube_sanitize_cols( 1, 2, 6 ) );
		$this->assertSame( 2, ktube_sanitize_cols( 0, 2, 6 ) );
		$this->assertSame( 2, ktube_sanitize_cols( -99, 2, 6 ) );
	}

	public function test_value_above_max_is_clamped_down(): void {
		$this->assertSame( 6, ktube_sanitize_cols( 7, 2, 6 ) );
		$this->assertSame( 4, ktube_sanitize_cols( 99, 2, 4 ) );
	}

	public function test_non_numeric_input_falls_back_to_min(): void {
		$this->assertSame( 2, ktube_sanitize_cols( 'banana', 2, 6 ) );
		$this->assertSame( 2, ktube_sanitize_cols( null, 2, 6 ) );
		$this->assertSame( 2, ktube_sanitize_cols( array(), 2, 6 ) );
	}

	public function test_numeric_string_is_int_cast(): void {
		$this->assertSame( 4, ktube_sanitize_cols( '4', 2, 6 ) );
		$this->assertSame( 4, ktube_sanitize_cols( '4.7', 2, 6 ) );
	}

	public function test_min_equals_max_returns_that_value(): void {
		$this->assertSame( 3, ktube_sanitize_cols( 5, 3, 3 ) );
		$this->assertSame( 3, ktube_sanitize_cols( 1, 3, 3 ) );
	}
}
