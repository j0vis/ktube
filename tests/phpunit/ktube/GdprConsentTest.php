<?php
/**
 * Phase 6-C — GDPR cookie consent + jurisdictional geo redirect tests.
 *
 * Asserts the cross-file invariants on includes/gdpr.php:
 *   - ktube_gdpr_active()  off by default
 *   - ktube_gdpr_default_blocked_countries()  ships the EU 27 + EEA 3 + UK
 *     payload verbatim (31 codes)
 *   - ktube_gdpr_blocked_countries()  sanitizes CSV to uppercase 2-letter
 *     ISO 3166-1 alpha-2 codes; garbage in → garbage filtered out
 *   - ktube_gdpr_resolve_country()  reads through filter hook
 *     'ktube_gdpr_resolve_country'; default resolver returns ''
 *   - ktube_gdpr_is_country_blocked()  uses trim+uppercase comparison
 *   - ktube_gdpr_should_redirect_visitor()  AND-chains active + blocked +
 *     not-admin
 *   - ktube_gdpr_consent_categories_default()  essential=true,
 *     analytics/marketing = configured theme_mods
 *   - ktube_gdpr_consent_for_category()  reads from passed blob first,
 *     falls back to defaults when blob is null
 *   - ktube_privacy_summary()  emits a Cookie consent row when GDPR is active
 *   - ktube_gdpr_enforce_redirect()  calls wp_redirect on the configured
 *     URL with the configured country; no-op when not in scope
 *
 * @package ktube
 */

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../bootstrap.php';

class GdprConsentTest extends TestCase {

	public function setUp(): void {
		$GLOBALS['__ktube_theme_mods']   = array();
		$GLOBALS['__ktube_posts']      = array();
		$GLOBALS['__ktube_last_redirect'] = null;
		if ( function_exists( 'reset_filters_test' ) ) {
			reset_filters_test();
		}
	}

	public function test_gdpr_active_off_by_default(): void {
		$this->assertFalse( ktube_gdpr_active(), 'GDPR master toggle must default OFF' );
	}

	public function test_gdpr_active_on_when_enabled(): void {
		set_theme_mod_test( 'ktube_gdpr_enabled', true );
		$this->assertTrue( ktube_gdpr_active() );
	}

	public function test_default_blocked_countries_contains_eu_eea_uk(): void {
		$ktube_codes = ktube_gdpr_default_blocked_countries();
		$this->assertCount( 31, $ktube_codes, 'EU 27 + EEA 3 + UK = 31 codes' );
		// Spot-check.
		$this->assertContains( 'DE', $ktube_codes );
		$this->assertContains( 'FR', $ktube_codes );
		$this->assertContains( 'IS', $ktube_codes, 'EEA Iceland' );
		$this->assertContains( 'GB', $ktube_codes, 'UK' );
		$this->assertNotContains( 'US', $ktube_codes, 'US is not in the default block' );
	}

	public function test_blocked_countries_sanitizes_csv_to_upper_iso(): void {
		set_theme_mod_test( 'ktube_gdpr_blocked_countries', 'DE, fr ,de ,xx,abcde,CZ' );
		$ktube_codes = ktube_gdpr_blocked_countries();
		// 'DE' dedup (case-insensitive) → single 'DE'.
		// 'fr' lower → uppercased to 'FR'.
		// 'xx' ok (2 chars).
		// 'abcde' 5 chars → dropped.
		$this->assertSame( array( 'DE', 'FR', 'XX', 'CZ' ), $ktube_codes, 'sanitization dedupes + uppercases + filters out non-iso' );
	}

	public function test_resolve_country_default_is_empty_when_no_filter(): void {
		// No provider filter wired → resolver returns '' so the redirect
		// path is gated safely (no accidental over-blocking on ungeo'd hosts).
		$this->assertSame( '', ktube_gdpr_resolve_country() );
	}

	public function test_resolve_country_reads_through_filter(): void {
		add_filter(
			'ktube_gdpr_resolve_country',
			static function (): string {
				return 'de';
			}
		);
		$this->assertSame( 'DE', ktube_gdpr_resolve_country(), 'filter callback result is uppercased' );
	}

	public function test_resolve_country_filters_out_malformed_values(): void {
		add_filter(
			'ktube_gdpr_resolve_country',
			static function (): string {
				return 'DEU';  // 3 chars: not ISO alpha-2.
			}
		);
		$this->assertSame( '', ktube_gdpr_resolve_country(), 'malformed codes filtered → empty (no redirect)' );
	}

	public function test_is_country_blocked_matches_against_configured_list(): void {
		set_theme_mod_test( 'ktube_gdpr_enabled', true );
		set_theme_mod_test( 'ktube_gdpr_blocked_countries', 'DE,FR' );
		$this->assertTrue(  ktube_gdpr_is_country_blocked( 'DE' ) );
		$this->assertTrue(  ktube_gdpr_is_country_blocked( 'de' ), 'lowercase input uppercased for match' );
		$this->assertFalse( ktube_gdpr_is_country_blocked( 'US' ) );
		$this->assertFalse( ktube_gdpr_is_country_blocked( '' ),    'empty string never blocked' );
	}

	public function test_should_redirect_visitor_requires_active_and_blocked_country(): void {
		set_theme_mod_test( 'ktube_gdpr_enabled', true );
		set_theme_mod_test( 'ktube_gdpr_blocked_countries', 'DE' );
		add_filter(
			'ktube_gdpr_resolve_country',
			static function (): string {
				return 'DE';
			}
		);

		// gdpr ON + blocked country → true.
		$this->assertTrue( ktube_gdpr_should_redirect_visitor() );

		// gdpr OFF → false even with blocked country.
		set_theme_mod_test( 'ktube_gdpr_enabled', false );
		$this->assertFalse( ktube_gdpr_should_redirect_visitor() );
	}

	public function test_should_redirect_visitor_false_when_country_unresolved(): void {
		set_theme_mod_test( 'ktube_gdpr_enabled', true );
		set_theme_mod_test( 'ktube_gdpr_blocked_countries', 'DE' );
		// No filter registered, resolver returns ''.
		$this->assertFalse( ktube_gdpr_should_redirect_visitor(), 'no-IP-geo path: never blocks' );
	}

	public function test_consent_categories_default_essential_is_always_on(): void {
		$ktube_cats = ktube_gdpr_consent_categories_default();
		$this->assertSame( true,  $ktube_cats['essential'], 'essential cookie always on' );
		$this->assertSame( false, $ktube_cats['analytics'], 'analytics off by default' );
		$this->assertSame( false, $ktube_cats['marketing'], 'marketing off by default' );
	}

	public function test_consent_categories_default_reflects_customizer(): void {
		set_theme_mod_test( 'ktube_gdpr_categories_analytics', true );
		set_theme_mod_test( 'ktube_gdpr_categories_marketing', true );
		$ktube_cats = ktube_gdpr_consent_categories_default();
		$this->assertSame( true, $ktube_cats['analytics'] );
		$this->assertSame( true, $ktube_cats['marketing'] );
		$this->assertSame( true, $ktube_cats['essential'], 'essential unaffected by per-category toggles' );
	}

	public function test_consent_for_category_reads_from_blob(): void {
		$ktube_blob = array(
			'essential'  => true,
			'analytics'  => true,
			'marketing'  => false,
		);
		$this->assertTrue(  ktube_gdpr_consent_for_category( 'essential', $ktube_blob ) );
		$this->assertTrue(  ktube_gdpr_consent_for_category( 'analytics', $ktube_blob ) );
		$this->assertFalse( ktube_gdpr_consent_for_category( 'marketing', $ktube_blob ) );
		$this->assertFalse( ktube_gdpr_consent_for_category( 'unknown',   $ktube_blob ) );
	}

	public function test_consent_for_category_unknown_returns_false_even_with_blob(): void {
		$this->assertFalse( ktube_gdpr_consent_for_category( 'unknown', array( 'essential' => true ) ) );
	}

	public function test_privacy_summary_emits_cookie_consent_row_when_gdpr_active(): void {
		set_theme_mod_test( 'ktube_gdpr_enabled', true );
		$ktube_rows = ktube_privacy_summary();
		$ktube_labels = array_column( $ktube_rows, 'label' );
		$this->assertContains( 'Cookie consent', $ktube_labels, 'GDPR-active sites must show Cookie consent row' );

		$ktube_consent_row = null;
		foreach ( $ktube_rows as $ktube_row ) {
			if ( 'Cookie consent' === $ktube_row['label'] ) {
				$ktube_consent_row = $ktube_row;
				break;
			}
		}
		$this->assertNotNull( $ktube_consent_row );
		$this->assertTrue(  $ktube_consent_row['active'] );
		$this->assertStringContainsString( 'ktube-gdpr-consent',     $ktube_consent_row['value'] );
		$this->assertStringContainsString( 'ktube_gdpr_consent',     $ktube_consent_row['value'] );
	}

	public function test_privacy_summary_omits_cookie_consent_row_when_gdpr_disabled(): void {
		$ktube_rows = ktube_privacy_summary();
		$ktube_labels = array_column( $ktube_rows, 'label' );
		$this->assertNotContains( 'Cookie consent', $ktube_labels, 'GDPR-off sites omit the cookie consent row' );
	}

	public function test_enforce_redirect_calls_wp_redirect_with_target(): void {
		set_theme_mod_test( 'ktube_gdpr_enabled', true );
		set_theme_mod_test( 'ktube_gdpr_blocked_countries', 'DE' );
		set_theme_mod_test( 'ktube_gdpr_redirect_url', 'https://example.test/blocked/' );
		add_filter(
			'ktube_gdpr_resolve_country',
			static function (): string {
				return 'DE';
			}
		);

		ktube_gdpr_enforce_redirect();

		$ktube_redirect = $GLOBALS['__ktube_last_redirect'];
		$this->assertNotNull( $ktube_redirect, 'wp_redirect must be called when visitor in blocked country' );
		$this->assertSame( 'https://example.test/blocked/', $ktube_redirect['location'] );
		$this->assertSame( 302, $ktube_redirect['status'] );
	}

	public function test_enforce_redirect_no_op_when_no_block(): void {
		set_theme_mod_test( 'ktube_gdpr_enabled', true );
		set_theme_mod_test( 'ktube_gdpr_blocked_countries', 'DE' );
		// Resolver returns '' (no filter).
		ktube_gdpr_enforce_redirect();
		$this->assertNull( $GLOBALS['__ktube_last_redirect'], 'no redirect when IP-geo unresolved' );
	}

	public function test_enforce_redirect_uses_default_url_when_configured_is_empty(): void {
		set_theme_mod_test( 'ktube_gdpr_enabled', true );
		set_theme_mod_test( 'ktube_gdpr_blocked_countries', 'DE' );
		set_theme_mod_test( 'ktube_gdpr_redirect_url', '' );
		add_filter(
			'ktube_gdpr_resolve_country',
			static function (): string {
				return 'DE';
			}
		);

		ktube_gdpr_enforce_redirect();

		$ktube_redirect = $GLOBALS['__ktube_last_redirect'];
		$this->assertNotNull( $ktube_redirect );
		$this->assertSame( 'https://www.google.com/', $ktube_redirect['location'], 'default fallback URL when configured URL is empty' );
	}

	public function test_helper_exports_and_blocked_array_uniqueness(): void {
		// Edge: a CSV with repeats MUST dedupe so a 99-of-100 chance of
		// accidentally short-circuiting doesn't sneak past.
		set_theme_mod_test( 'ktube_gdpr_blocked_countries', 'DE,DE,DE,FR,FR' );
		$ktube_codes = ktube_gdpr_blocked_countries();
		$this->assertCount( 2, $ktube_codes );
		$this->assertSame( array( 'DE', 'FR' ), $ktube_codes, 'CSV is deduped in canonical order' );
	}
}
