<?php
/**
 * Phase 8-B — Compliance pages tests.
 *
 * Asserts the cross-file invariants on includes/compliance-pages.php:
 *
 *   - ktube_compliance_kinds() exposes exactly the 3 canonical kinds
 *     (2257, dmca, terms) with consistent schema.
 *   - ktube_resolve_compliance_page_id() returns 0 for unset, returns
 *     configured id when pointing at a published `page`, returns
 *     slug-fallback id when configured id is wrong/draft/0, returns
 *     0 for unknown slugs.
 *   - ktube_get_compliance_page_url() returns '' when nothing resolves,
 *     returns permalink string otherwise.
 *   - ktube_compliance_default_heading() returns the configured label
 *     for known slugs, '' for unknown slugs.
 *   - ktube_get_compliance_footer_slots() honors canonical order
 *     (Privacy → 2257 → DMCA → Terms), skips slots without a resolved
 *     page, and emits a stable per-slot shape.
 *   - ktube_register_compliance_page_templates() adds the 3 templates
 *     to the page-template drop-down list.
 */

class CompliancePagesTest extends \PHPUnit\Framework\TestCase {
	public function setUp(): void {
		$GLOBALS['__ktube_meta']       = array();
		$GLOBALS['__ktube_posts']      = array();
		$GLOBALS['__ktube_theme_mods'] = array();
		if ( function_exists( 'reset_filters_test' ) ) {
			reset_filters_test();
		}
	}

	public function test_kinds_registry_has_three_compliance_slugs(): void {
		$ktube_kinds = ktube_compliance_kinds();
		$this->assertCount( 3, $ktube_kinds, 'should ship exactly 3 kinds (2257 + DMCA + Terms)' );
		$this->assertArrayHasKey( '2257',  $ktube_kinds );
		$this->assertArrayHasKey( 'dmca',  $ktube_kinds );
		$this->assertArrayHasKey( 'terms', $ktube_kinds );
	}

	public function test_kinds_registry_schema_is_consistent(): void {
		$ktube_kinds = ktube_compliance_kinds();
		foreach ( $ktube_kinds as $ktube_slug => $ktube_kind ) {
			$this->assertNotEmpty( $ktube_kind['label'],      "label missing for $ktube_slug" );
			$this->assertNotEmpty( $ktube_kind['setting_id'], "setting_id missing for $ktube_slug" );
			$this->assertNotEmpty( $ktube_kind['template'],   "template missing for $ktube_slug" );
			$this->assertStringContainsString( $ktube_slug, $ktube_kind['setting_id'], "setting_id should embed slug '$ktube_slug'" );
			$this->assertStringEndsWith( '.php', $ktube_kind['template'], "template must end in .php" );
			$this->assertStringContainsString( $ktube_slug, $ktube_kind['template'], "template filename should embed slug '$ktube_slug'" );
		}
	}

	public function test_resolve_compliance_page_id_zero_when_unset_and_no_slug_fallback(): void {
		// No pixels configured, no Page with slug "2257" published.
		$this->assertSame( 0, ktube_resolve_compliance_page_id( '2257' ) );
		$this->assertSame( 0, ktube_resolve_compliance_page_id( 'dmca' ) );
		$this->assertSame( 0, ktube_resolve_compliance_page_id( 'terms' ) );
	}

	public function test_resolve_compliance_page_id_unknown_slug_returns_zero(): void {
		// lookups outside the registry must NOT trigger the slug-fallback
		// query (would be a confused-deputy bug — keeping queries to
		// known compliance kinds only).
		$this->assertSame( 0, ktube_resolve_compliance_page_id( 'who-knows' ) );
		$this->assertSame( 0, ktube_resolve_compliance_page_id( '' ) );
	}

	public function test_resolve_compliance_page_id_configured_hit_published_page(): void {
		// Operator picked id=42 in the Customizer; the matching Page is
		// published; configured value wins regardless of whether a
		// slug=2257 fallback also exists.
		set_post_test( 42, array( 'post_type' => 'page', 'post_status' => 'publish', 'post_name' => 'garbage' ) );
		set_post_test( 99, array( 'post_type' => 'page', 'post_status' => 'publish', 'post_name' => '2257' ) );
		set_theme_mod_test( 'ktube_2257_page_id', 42 );
		$this->assertSame( 42, ktube_resolve_compliance_page_id( '2257' ) );
	}

	public function test_resolve_compliance_page_id_configured_miss_falls_back_to_slug(): void {
		// Operator set id=42 but that page is a draft (or doesn't exist);
		// resolver falls back to a published Page with slug matching.
		set_post_test( 99, array( 'post_type' => 'page', 'post_status' => 'publish', 'post_name' => 'dmca' ) );
		set_theme_mod_test( 'ktube_dmca_page_id', 42 ); // 42 is not registered.
		$this->assertSame( 99, ktube_resolve_compliance_page_id( 'dmca' ) );
	}

	public function test_resolve_compliance_page_id_configured_post_type_mismatch_falls_back(): void {
		// Operator pointed the setting at something that isn't a `page`
		// (e.g., a video CPT post). Resolver must NOT accept it.
		set_post_test( 50, array( 'post_type' => 'video', 'post_status' => 'publish', 'post_name' => 'whatever' ) );
		set_post_test( 99, array( 'post_type' => 'page',  'post_status' => 'publish', 'post_name' => 'terms' ) );
		set_theme_mod_test( 'ktube_terms_page_id', 50 );
		$this->assertSame( 99, ktube_resolve_compliance_page_id( 'terms' ) );
	}

	public function test_get_compliance_page_url_empty_when_no_resolve(): void {
		$this->assertSame( '', ktube_get_compliance_page_url( '2257' ) );
		$this->assertSame( '', ktube_get_compliance_page_url( 'dmca' ) );
		$this->assertSame( '', ktube_get_compliance_page_url( 'terms' ) );
	}

	public function test_get_compliance_page_url_returns_permalink_when_resolved(): void {
		// Bootstrap's get_permalink stub always returns the test URL.
		set_post_test( 99, array( 'post_type' => 'page', 'post_status' => 'publish', 'post_name' => '2257' ) );
		set_post_test( 98, array( 'post_type' => 'page', 'post_status' => 'publish', 'post_name' => 'dmca' ) );
		set_post_test( 97, array( 'post_type' => 'page', 'post_status' => 'publish', 'post_name' => 'terms' ) );
		set_theme_mod_test( 'ktube_2257_page_id',  99 );
		set_theme_mod_test( 'ktube_dmca_page_id',  98 );
		set_theme_mod_test( 'ktube_terms_page_id', 97 );

		$ktube_url_2257  = ktube_get_compliance_page_url( '2257' );
		$ktube_url_dmca  = ktube_get_compliance_page_url( 'dmca' );
		$ktube_url_terms = ktube_get_compliance_page_url( 'terms' );

		$this->assertNotSame( '', $ktube_url_2257 );
		$this->assertNotSame( '', $ktube_url_dmca );
		$this->assertNotSame( '', $ktube_url_terms );
	}

	public function test_default_heading_per_slug(): void {
		$this->assertSame( '2257 Compliance', ktube_compliance_default_heading( '2257' ) );
		$this->assertSame( 'DMCA',            ktube_compliance_default_heading( 'dmca' ) );
		$this->assertSame( 'Terms of Service', ktube_compliance_default_heading( 'terms' ) );
	}

	public function test_default_heading_unknown_slug_empty(): void {
		$this->assertSame( '', ktube_compliance_default_heading( 'who-knows' ) );
		$this->assertSame( '', ktube_compliance_default_heading( '' ) );
	}

	public function test_footer_slots_includes_privacy_then_2257_dmca_terms_order(): void {
		// All 4 slots configured; gate function ktube_should_show_privacy_badge
		// is not loaded in this test, so privacy slot is NOT suppressed
		// (the gate is only active when the privacy module is in scope).
		if ( function_exists( 'ktube_should_show_privacy_badge' ) ) {
			set_theme_mod_test( 'ktube_age_gate_enabled', false );
		}
		set_post_test( 11, array( 'post_type' => 'page', 'post_status' => 'publish', 'post_name' => 'privacy' ) );
		set_post_test( 22, array( 'post_type' => 'page', 'post_status' => 'publish', 'post_name' => '2257' ) );
		set_post_test( 33, array( 'post_type' => 'page', 'post_status' => 'publish', 'post_name' => 'dmca' ) );
		set_post_test( 44, array( 'post_type' => 'page', 'post_status' => 'publish', 'post_name' => 'terms' ) );
		set_theme_mod_test( 'ktube_privacy_page_id', 11 );
		set_theme_mod_test( 'ktube_2257_page_id',    22 );
		set_theme_mod_test( 'ktube_dmca_page_id',    33 );
		set_theme_mod_test( 'ktube_terms_page_id',   44 );

		$ktube_slots = ktube_get_compliance_footer_slots();
		$this->assertCount( 4, $ktube_slots, 'all 4 slots present' );

		$ktube_slugs = array_column( $ktube_slots, 'slug' );
		$this->assertSame( array( 'privacy', '2257', 'dmca', 'terms' ), $ktube_slugs, 'canonical order is preserved' );

		foreach ( $ktube_slots as $ktube_slot ) {
			$this->assertArrayHasKey( 'slug',  $ktube_slot, 'every slot has a slug' );
			$this->assertArrayHasKey( 'label', $ktube_slot, 'every slot has a label' );
			$this->assertArrayHasKey( 'url',   $ktube_slot, 'every slot has a url' );
			$this->assertNotSame( '', $ktube_slot['url'], 'every slot has a non-empty url' );
		}
	}

	public function test_footer_slots_excludes_unconfigured(): void {
		// Only dmca + terms configured.
		set_post_test( 33, array( 'post_type' => 'page', 'post_status' => 'publish', 'post_name' => 'dmca' ) );
		set_post_test( 44, array( 'post_type' => 'page', 'post_status' => 'publish', 'post_name' => 'terms' ) );
		set_theme_mod_test( 'ktube_dmca_page_id',  33 );
		set_theme_mod_test( 'ktube_terms_page_id', 44 );

		$ktube_slots = ktube_get_compliance_footer_slots();
		$ktube_slugs = array_column( $ktube_slots, 'slug' );
		$this->assertSame( array( 'dmca', 'terms' ), $ktube_slugs, 'unconfigured slots are silently dropped' );
	}

	public function test_footer_slots_skips_privacy_when_no_privacy_page(): void {
		// Only 2257 configured.
		set_post_test( 22, array( 'post_type' => 'page', 'post_status' => 'publish', 'post_name' => '2257' ) );
		set_theme_mod_test( 'ktube_2257_page_id', 22 );

		$ktube_slots = ktube_get_compliance_footer_slots();
		$ktube_slugs = array_column( $ktube_slots, 'slug' );
		$this->assertSame( array( '2257' ), $ktube_slugs, 'privacy slot dropped when no privacy page' );
	}

	public function test_footer_slots_privacy_slot_suppressed_when_badge_active(): void {
		// Phase 7b badge already shows the privacy page when age-gate is
		// active; the nav slot must be suppressed in that case to avoid
		// rendering two copies of the same URL.
		// We control ktube_should_show_privacy_badge() indirectly via the
		// ktube_age_gate_enabled + ktube_privacy_page_id theme_mods.
		if ( function_exists( 'ktube_should_show_privacy_badge' ) ) {
			set_theme_mod_test( 'ktube_age_gate_enabled', true );
		}
		set_post_test( 11, array( 'post_type' => 'page', 'post_status' => 'publish', 'post_name' => 'privacy' ) );
		set_post_test( 22, array( 'post_type' => 'page', 'post_status' => 'publish', 'post_name' => '2257' ) );
		set_theme_mod_test( 'ktube_privacy_page_id', 11 );
		set_theme_mod_test( 'ktube_2257_page_id',    22 );

		$ktube_slots = ktube_get_compliance_footer_slots();
		$ktube_slugs = array_column( $ktube_slots, 'slug' );
		$this->assertSame( array( '2257' ), $ktube_slugs, 'privacy slot suppressed when badge is active' );
	}

	public function test_footer_slots_filter_hook_appends_custom_slot(): void {
		set_post_test( 22, array( 'post_type' => 'page', 'post_status' => 'publish', 'post_name' => 'dmca' ) );
		set_theme_mod_test( 'ktube_dmca_page_id', 22 );

		$ktube_filter = static function ( $ktube_slots ): array {
			$ktube_slots[] = array(
				'slug'  => 'gdpr',
				'label' => 'GDPR',
				'url'   => 'https://example.test/gdpr/',
			);
			return $ktube_slots;
		};
		add_filter( 'ktube_compliance_footer_slots', $ktube_filter );

		$ktube_slots = ktube_get_compliance_footer_slots();
		$ktube_slugs = array_column( $ktube_slots, 'slug' );
		$this->assertSame( array( 'dmca', 'gdpr' ), $ktube_slugs, 'MU-plugins can append slots via filter' );

		remove_filter( 'ktube_compliance_footer_slots', $ktube_filter );
	}

	public function test_register_compliance_page_templates_adds_three_entries(): void {
		// The dropdown list appends 3 entries — covers operators who
		// choose the template explicitly in the editor instead of
		// relying on slug-based auto-apply.
		$ktube_dropdown = array( 'default' => 'Default Template' );
		$ktube_result    = ktube_register_compliance_page_templates( $ktube_dropdown );
		$this->assertArrayHasKey( 'page-2257.php',  $ktube_result );
		$this->assertArrayHasKey( 'page-dmca.php',  $ktube_result );
		$this->assertArrayHasKey( 'page-terms.php', $ktube_result );
		$this->assertArrayHasKey( 'default',        $ktube_result, 'existing entries preserved' );
		$this->assertSame( '2257 Compliance', $ktube_result['page-2257.php'] );
		$this->assertSame( 'DMCA',            $ktube_result['page-dmca.php'] );
		$this->assertSame( 'Terms of Service', $ktube_result['page-terms.php'] );
	}
}
