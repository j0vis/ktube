<?php
/**
 * Phase 7b Privacy helpers — ktube_privacy_summary, badge gating,
 * page resolution, and adaptive badge copy.
 *
 * @package ktube
 */

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../bootstrap.php';

class PrivacySummaryTest extends TestCase {

	public function setUp(): void {
		$GLOBALS['__ktube_theme_mods'] = array();
		$GLOBALS['__ktube_posts']      = array();
	}

	public function test_summary_returns_two_default_rows_when_age_gate_off(): void {
		// No age-gate, no RTA, no extras. Summary still documents 7 rows
		// (one fixed "Theme preference" row is always active).
		$ktube_rows = ktube_privacy_summary();
		$this->assertSame( 7, count( $ktube_rows ) );
		// Active count is 1 (theme preference) when nothing is configured.
		$ktube_active = 0;
		foreach ( $ktube_rows as $ktube_row ) {
			if ( $ktube_row['active'] ) {
				++$ktube_active;
			}
		}
		$this->assertSame( 1, $ktube_active );
	}

	public function test_summary_marks_age_gate_rows_active_when_gate_on(): void {
		set_theme_mod_test( 'ktube_age_gate_enabled', true );
		set_theme_mod_test( 'ktube_age_gate_min_age', 21 );
		set_theme_mod_test( 'ktube_age_gate_duration_days', 7 );
		$ktube_rows = ktube_privacy_summary();
		$ktube_active = 0;
		foreach ( $ktube_rows as $ktube_row ) {
			if ( $ktube_row['active'] ) {
				++$ktube_active;
			}
		}
		// 5 age-gate rows + 1 theme preference = 6 active when gate is on.
		$this->assertSame( 6, $ktube_active );
	}

	public function test_summary_documents_localstorage_and_cookie(): void {
		$ktube_rows = ktube_privacy_summary();
		$ktube_keys  = array_column( $ktube_rows, 'label' );
		$this->assertTrue( in_array( 'localStorage key', $ktube_keys, true ), 'summary should document localStorage key' );
		$this->assertTrue( in_array( 'Cookie name',      $ktube_keys, true ), 'summary should document cookie name' );

		// Drill into the localStorage row.
		$ktube_localstorage_row = null;
		foreach ( $ktube_rows as $ktube_row ) {
			if ( 'localStorage key' === $ktube_row['label'] ) {
				$ktube_localstorage_row = $ktube_row;
				break;
			}
		}
		$this->assertNotNull( $ktube_localstorage_row );
		$this->assertSame( 'ktube-age-confirmed-on', $ktube_localstorage_row['value'] );

		// Drill into the cookie row.
		$ktube_cookie_row = null;
		foreach ( $ktube_rows as $ktube_row ) {
			if ( 'Cookie name' === $ktube_row['label'] ) {
				$ktube_cookie_row = $ktube_row;
				break;
			}
		}
		$this->assertNotNull( $ktube_cookie_row );
		$this->assertSame( 'ktube_age_verified', $ktube_cookie_row['value'] );
	}

	public function test_summary_ttl_unit_pluralizes(): void {
		set_theme_mod_test( 'ktube_age_gate_enabled', true );
		set_theme_mod_test( 'ktube_age_gate_duration_days', 1 );
		$ktube_rows       = ktube_privacy_summary();
		$ktube_ttl_row    = null;
		foreach ( $ktube_rows as $ktube_row ) {
			if ( 'Verification retention' === $ktube_row['label'] ) {
				$ktube_ttl_row = $ktube_row;
				break;
			}
		}
		$this->assertNotNull( $ktube_ttl_row );
		$this->assertSame( '1', $ktube_ttl_row['value'] );
		$this->assertSame( 'day', $ktube_ttl_row['unit'] );

		set_theme_mod_test( 'ktube_age_gate_duration_days', 30 );
		$ktube_rows = ktube_privacy_summary();
		foreach ( $ktube_rows as $ktube_row ) {
			if ( 'Verification retention' === $ktube_row['label'] ) {
				$ktube_ttl_row = $ktube_row;
				break;
			}
		}
		$this->assertSame( 'days', $ktube_ttl_row['unit'] );
	}

	public function test_resolve_privacy_page_id_returns_zero_when_nothing_configured(): void {
		$this->assertSame( 0, ktube_resolve_privacy_page_id() );
	}

	public function test_resolve_privacy_page_id_returns_configured_id_when_post_exists(): void {
		set_theme_mod_test( 'ktube_privacy_page_id', 42 );
		set_post_test( 42, array( 'post_type' => 'page', 'post_status' => 'publish' ) );
		$this->assertSame( 42, ktube_resolve_privacy_page_id() );
	}

	public function test_resolve_privacy_page_id_returns_zero_when_configured_post_missing(): void {
		set_theme_mod_test( 'ktube_privacy_page_id', 99 );
		// No set_post_test(99, ...) → get_post returns null.
		$this->assertSame( 0, ktube_resolve_privacy_page_id() );
	}

	public function test_resolve_privacy_page_id_returns_zero_when_configured_post_is_not_a_page(): void {
		set_theme_mod_test( 'ktube_privacy_page_id', 50 );
		set_post_test( 50, array( 'post_type' => 'post', 'post_status' => 'publish' ) );
		$this->assertSame( 0, ktube_resolve_privacy_page_id() );
	}

	public function test_resolve_privacy_page_id_falls_back_to_slug(): void {
		// No Customizer config, but a Page exists with post_name = 'privacy'
		// (registered via the slug-fallback query).
		set_post_test( 7, array( 'post_type' => 'page', 'post_status' => 'publish', 'post_name' => 'privacy' ) );
		$this->assertSame( 7, ktube_resolve_privacy_page_id() );
	}

	public function test_resolve_privacy_page_id_prefers_configured_over_slug_fallback(): void {
		set_theme_mod_test( 'ktube_privacy_page_id', 42 );
		set_post_test( 42, array( 'post_type' => 'page', 'post_status' => 'publish', 'post_name' => 'about-us' ) );
		set_post_test( 7,  array( 'post_type' => 'page', 'post_status' => 'publish', 'post_name' => 'privacy' ) );
		$this->assertSame( 42, ktube_resolve_privacy_page_id() );
	}

	public function test_get_privacy_page_url_returns_empty_when_nothing_resolves(): void {
		$this->assertSame( '', ktube_get_privacy_page_url() );
	}

	public function test_get_privacy_page_url_returns_permalink_when_page_resolves(): void {
		set_theme_mod_test( 'ktube_privacy_page_id', 42 );
		set_post_test( 42, array( 'post_type' => 'page', 'post_status' => 'publish' ) );
		$ktube_url = ktube_get_privacy_page_url();
		$this->assertNotEmpty( $ktube_url );
		$this->assertMatchesRegularExpression( '#^https?://#', $ktube_url );
	}

	public function test_should_show_privacy_badge_requires_age_gate_and_resolvable_page(): void {
		// No gate, no page → false.
		$this->assertFalse( ktube_should_show_privacy_badge() );

		// Gate ON, no page → false (badge copy would lie).
		set_theme_mod_test( 'ktube_age_gate_enabled', true );
		$this->assertFalse( ktube_should_show_privacy_badge() );

		// Gate ON, page resolves → true.
		set_post_test( 42, array( 'post_type' => 'page', 'post_status' => 'publish' ) );
		set_theme_mod_test( 'ktube_privacy_page_id', 42 );
		$this->assertTrue( ktube_should_show_privacy_badge() );

		// Gate OFF, page resolves → false (badge gated on age-gate).
		set_theme_mod_test( 'ktube_age_gate_enabled', false );
		$this->assertFalse( ktube_should_show_privacy_badge() );
	}

	public function test_badge_copy_adapts_to_active_protections(): void {
		// Gate on, RTA off → age-gate single line.
		set_theme_mod_test( 'ktube_age_gate_enabled', true );
		set_theme_mod_test( 'ktube_age_gate_min_age', 18 );
		set_theme_mod_test( 'ktube_rta_enabled', false );
		$this->assertSame( 'We verify visitors are 18+.', ktube_privacy_badge_copy() );

		// Gate on + RTA on → both.
		set_theme_mod_test( 'ktube_rta_enabled', true );
		$this->assertSame( 'We verify visitors are 18+. Protected by RTA.', ktube_privacy_badge_copy() );

		// Gate off, RTA on → RTA only.
		set_theme_mod_test( 'ktube_age_gate_enabled', false );
		$this->assertSame( 'Protected by RTA.', ktube_privacy_badge_copy() );

		// Gate off, RTA off → neutral copy (returned for completeness even
		// though the badge itself is gated off in this state).
		set_theme_mod_test( 'ktube_rta_enabled', false );
		$this->assertSame( 'Privacy & cookies', ktube_privacy_badge_copy() );
	}

	public function test_badge_copy_substitutes_custom_minimum_age(): void {
		set_theme_mod_test( 'ktube_age_gate_enabled', true );
		set_theme_mod_test( 'ktube_age_gate_min_age', 21 );
		$this->assertSame( 'We verify visitors are 21+.', ktube_privacy_badge_copy() );
	}

	public function test_summary_row_keys_are_complete(): void {
		$ktube_rows = ktube_privacy_summary();
		foreach ( $ktube_rows as $ktube_row ) {
			$this->assertArrayHasKey( 'active',      $ktube_row );
			$this->assertArrayHasKey( 'label',       $ktube_row );
			$this->assertArrayHasKey( 'value',       $ktube_row );
			$this->assertArrayHasKey( 'unit',        $ktube_row );
			$this->assertArrayHasKey( 'description', $ktube_row );
			$this->assertIsBool( $ktube_row['active'] );
			$this->assertIsString( $ktube_row['label'] );
			$this->assertIsString( $ktube_row['value'] );
			$this->assertIsString( $ktube_row['unit'] );
			$this->assertIsString( $ktube_row['description'] );
		}
	}

	public function test_summary_rta_row_contains_meta_tag_value(): void {
		set_theme_mod_test( 'ktube_rta_enabled', true );
		$ktube_rows = ktube_privacy_summary();
		$ktube_rta_row = null;
		foreach ( $ktube_rows as $ktube_row ) {
			if ( 'RTA meta tag' === $ktube_row['label'] ) {
				$ktube_rta_row = $ktube_row;
				break;
			}
		}
		$this->assertNotNull( $ktube_rta_row );
		$this->assertStringContainsString( 'name="rating"', $ktube_rta_row['value'] );
		$this->assertStringContainsString( 'RTA-5042-1996-1400-1577-RTA', $ktube_rta_row['value'] );
		$this->assertTrue( $ktube_rta_row['active'] );
	}
}
