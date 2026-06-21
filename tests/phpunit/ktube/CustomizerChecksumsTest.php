<?php
/**
 * Phase 7b — Customizer CSS checksum guard tests.
 *
 * ktube_settings_substring() must mirror the JS-side buildCss substring
 * function in customize-controls.js so SHA-256(per-key) hashes match when
 * both sides agree. ktube_compute_customizer_checksums() must produce a
 * stable total hash for the same set of theme mods.
 */
class CustomizerChecksumsTest extends \PHPUnit\Framework\TestCase {

	public function setUp(): void {
		reset_theme_mods_test();
	}

	public function test_grid_substring_is_stable_string(): void {
		$this->assertSame(
			'--ktube-grid-cols-desktop:4;',
			ktube_settings_substring( 'ktube_grid_cols_desktop', 4 )
		);
		$this->assertSame(
			'--ktube-thumb-cols-mobile:2;',
			ktube_settings_substring( 'ktube_thumb_cols_mobile', 2 )
		);
	}

	public function test_color_substring_carries_theme_block_tag(): void {
		// Light and dark tokens share prefix (--ktube-color-*) so the
		// substring must disambiguate via `;|@<block>` tag. The `;`
		// matches the JS-side capture from buildCss() — they must agree
		// for per-key SHA-256 hashes to align.
		$this->assertSame(
			'--ktube-color-bg:#ffffff;|@light',
			ktube_settings_substring( 'ktube_color_bg_light', '#ffffff' )
		);
		$this->assertSame(
			'--ktube-color-bg:#0e0e10;|@dark',
			ktube_settings_substring( 'ktube_color_bg_dark', '#0e0e10' )
		);
		$this->assertSame(
			'--ktube-color-link:#60a5fa;|@dark',
			ktube_settings_substring( 'ktube_color_link_dark', '#60a5fa' )
		);
	}

	public function test_unknown_setting_returns_empty(): void {
		$this->assertSame( '', ktube_settings_substring( 'ktube_unknown', 'whatever' ) );
	}

	public function test_total_checksum_is_64_char_sha256_hex(): void {
		$ktube_c = ktube_compute_customizer_checksums();
		$this->assertMatchesRegularExpression( '/^[a-f0-9]{64}$/', $ktube_c['total'] );
	}

	public function test_per_key_checksums_are_13_entries(): void {
		// 5 grid/thumb cols + 8 color tokens = 13 — the JS-side mirror
		// iterates exactly these 13 ids. If a new theme_mod is added both
		// KTUBE_SETTINGS array (PHP) and per_key dict (server) MUST grow
		// the count here.
		$ktube_c = ktube_compute_customizer_checksums();
		$this->assertCount( 13, $ktube_c['per_key'] );
		foreach ( $ktube_c['per_key'] as $ktube_k => $ktube_h ) {
			$this->assertMatchesRegularExpression(
				'/^[a-f0-9]{64}$/',
				$ktube_h,
				"per_key[{$ktube_k}] must be SHA-256 hex"
			);
		}
	}

	public function test_grid_change_only_shifts_grid_substring_hashes(): void {
		$ktube_before = ktube_compute_customizer_checksums();
		set_theme_mod_test( 'ktube_grid_cols_desktop', 5 );
		$ktube_after = ktube_compute_customizer_checksums();

		$this->assertNotSame( $ktube_before['total'], $ktube_after['total'] );
		$this->assertNotSame(
			$ktube_before['per_key']['ktube_grid_cols_desktop'],
			$ktube_after['per_key']['ktube_grid_cols_desktop']
		);
		// Unrelated keys hash identically.
		$this->assertSame(
			$ktube_before['per_key']['ktube_color_bg_light'],
			$ktube_after['per_key']['ktube_color_bg_light']
		);
		$this->assertSame(
			$ktube_before['per_key']['ktube_color_link_dark'],
			$ktube_after['per_key']['ktube_color_link_dark']
		);
		$this->assertSame(
			$ktube_before['per_key']['ktube_thumb_cols_mobile'],
			$ktube_after['per_key']['ktube_thumb_cols_mobile']
		);
	}

	public function test_dark_color_change_shifts_dark_block_only(): void {
		$ktube_before = ktube_compute_customizer_checksums();
		set_theme_mod_test( 'ktube_color_link_dark', '#aa00ff' );
		$ktube_after = ktube_compute_customizer_checksums();

		$this->assertNotSame(
			$ktube_before['per_key']['ktube_color_link_dark'],
			$ktube_after['per_key']['ktube_color_link_dark']
		);
		// Light block stays put.
		$this->assertSame(
			$ktube_before['per_key']['ktube_color_link_light'],
			$ktube_after['per_key']['ktube_color_link_light']
		);
		// Link_other_dark NOT link_light_dark: they are keyed separately,
		// so editing only one is surgical. We don't expect the bg or accent
		// hashes to change here.
		$this->assertSame(
			$ktube_before['per_key']['ktube_color_bg_dark'],
			$ktube_after['per_key']['ktube_color_bg_dark']
		);
		$this->assertSame(
			$ktube_before['per_key']['ktube_color_accent_dark'],
			$ktube_after['per_key']['ktube_color_accent_dark']
		);
	}
}
