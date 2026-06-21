<?php
/**
 * ktube_build_customizer_css — golden fixture. Byte-equal expected string
 * ensures the JS-side buildCss() can be cross-checked via the SHA-256
 * checksum guard emitted in window.ktubeCustomizerSettingChecksums.
 */
class BuildCustomizerCssTest extends \PHPUnit\Framework\TestCase {

	public function setUp(): void {
		reset_theme_mods_test();
	}

	public function test_default_values_render_full_golden_fixture(): void {
		// Defaults are sourced from ktube_customize_register defaults +
		// ktube_print_inline_customizer_vars defaults — NOT from any test
		// injection. Empty theme_mods bag → server-side defaults.
		$ktube_golden =			':root {' . "\n" .
			"\t" . '--ktube-grid-cols-desktop:4;' . "\n" .
			"\t" . '--ktube-grid-cols-tablet:3;'  . "\n" .
			"\t" . '--ktube-grid-cols-mobile:2;'  . "\n" .
			"\t" . '--ktube-thumb-cols-desktop:3;' . "\n" .
			"\t" . '--ktube-thumb-cols-mobile:2;'  . "\n" .
			'}' . "\n" .
			':root[data-theme="light"], :root:not([data-theme]) {' . "\n" .
			"\t" . '--ktube-color-bg:#ffffff;' . "\n" .
			"\t" . '--ktube-color-text:#18181b;' . "\n" .
			"\t" . '--ktube-color-accent:#db2777;' . "\n" .
			"\t" . '--ktube-color-link:#2563eb;' . "\n" .
			'}' . "\n" .
			':root[data-theme="dark"] {' . "\n" .
			"\t" . '--ktube-color-bg:#0e0e10;' . "\n" .
			"\t" . '--ktube-color-text:#e4e4e7;' . "\n" .
			"\t" . '--ktube-color-accent:#f472b6;' . "\n" .
			"\t" . '--ktube-color-link:#60a5fa;' . "\n" .
			'}' . "\n" .
			'@media (min-width: 641px) and (max-width: 1024px) {' . "\n" .
			"\t" . ':root {' . "\n" .
			"\t\t" . '--ktube-grid-cols-desktop: var(--ktube-grid-cols-tablet);' . "\n" .
			"\t\t" . '--ktube-thumb-cols: var(--ktube-thumb-cols-desktop);' . "\n" .
			"\t" . '}' . "\n" .
			'}' . "\n" .
			'@media (max-width: 640px) {' . "\n" .
			"\t" . ':root {' . "\n" .
			"\t\t" . '--ktube-grid-cols-desktop: var(--ktube-grid-cols-mobile);' . "\n" .
			"\t\t" . '--ktube-thumb-cols: var(--ktube-thumb-cols-mobile);' . "\n" .
			"\t" . '}' . "\n" .
			'}' . "\n";
		$this->assertSame( $ktube_golden, ktube_build_customizer_css() );
	}

	public function test_custom_grid_columns_appear_in_root_block(): void {
		set_theme_mod_test( 'ktube_grid_cols_desktop', 5 );
		set_theme_mod_test( 'ktube_grid_cols_tablet', 2 );
		$ktube_css = ktube_build_customizer_css();
		$this->assertStringContainsString( '--ktube-grid-cols-desktop:5;', $ktube_css );
		$this->assertStringContainsString( '--ktube-grid-cols-tablet:2;',  $ktube_css );
		// Untouched vars hold their defaults.
		$this->assertStringContainsString( '--ktube-grid-cols-mobile:2;',  $ktube_css );
	}

	public function test_custom_color_values_appear_in_correct_block(): void {
		set_theme_mod_test( 'ktube_color_link_dark', '#ff00aa' );
		$ktube_css = ktube_build_customizer_css();
		// Link injected into dark block only.
		$this->assertStringContainsString( ':root[data-theme="dark"] {', $ktube_css );
		$this->assertMatchesRegularExpression(
			'/:root\[data-theme="dark"\][^{]*\{[^}]*--ktube-color-link:#ff00aa;/s',
			$ktube_css
		);
		// Light block still has the original value.
		$this->assertStringContainsString( '--ktube-color-link:#2563eb;', $ktube_css );
	}

	public function test_invalid_color_string_falls_back_to_default(): void {
		set_theme_mod_test( 'ktube_color_bg_light', 'rgba(0,0,0,1)' );
		$ktube_css = ktube_build_customizer_css();
		// The light block must still mention bg with the default that ktube_sanitize_hex fell back to.
		$this->assertMatchesRegularExpression(
			'/:root\[data-theme="light"\][^{]*\{[^}]*--ktube-color-bg:#ffffff;/s',
			$ktube_css
		);
	}

	public function test_three_char_hex_is_normalized_in_output(): void {
		set_theme_mod_test( 'ktube_color_accent_dark', '#F0A' );
		$ktube_css = ktube_build_customizer_css();
		$this->assertStringContainsString( '--ktube-color-accent:#ff00aa;', $ktube_css );
	}

	public function test_clamps_out_of_range_grid_columns(): void {
		set_theme_mod_test( 'ktube_grid_cols_desktop', 99 );
		$ktube_css = ktube_build_customizer_css();
		// Max for ktube_grid_cols_desktop is 6 → clamped to 6.
		$this->assertStringContainsString( '--ktube-grid-cols-desktop:6;', $ktube_css );
	}
}
