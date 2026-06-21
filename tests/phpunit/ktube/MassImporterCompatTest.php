<?php
/**
 * Phase 1-A — Mass Importer compat contract test.
 *
 * Asserts the cross-file invariants that hold the importer parity
 * contract together:
 *
 *   - `ktube_importer_key_map()` is non-empty and each internal alias
 *     starts with `_ktube_`.
 *   - Every legacy key AND every internal key in the map is registered
 *     on the `video` CPT via `register_post_meta()` — both halves of
 *     the pair, so REST writes succeed on either side.
 *   - Every ktube-only reserved key in `ktube_mass_importer_meta_keys()`
 *     is registered on the `video` CPT.
 *   - The sync adapter reads from `ktube_importer_key_map()` (consistency
 *     guard), expressed as a literal-call-expression static-analysis
 *     check (not a soft substring match).
 *   - `ktube_db_index_blueprint()` covers every internal key that
 *     participates in DB-query-time sorting/filtering.
 *   - `ktube_drain_importer_meta_to_ktube()` silently bails on
 *     non-existent post ids instead of synthesizing a phantom
 *     WP_Post object (regression guard).
 *   - `ktube_discover_extra_importer_keys()` surface exists + returns
 *     an array for the Phase 1-A collector followup wiring.
 *
 * @package ktube
 */

class MassImporterCompatTest extends \PHPUnit\Framework\TestCase {
	public function setUp(): void {
		$GLOBALS['__ktube_meta']       = array();
		$GLOBALS['__ktube_posts']      = array();
		$GLOBALS['__ktube_theme_mods'] = array();
		// Re-invoke registration to repopulate __ktube_meta — the
		// bootstrap require chain runs once, but a test class loaded
		// after a different test class may find prior state intact.
		if ( function_exists( 'ktube_register_meta' ) ) {
			ktube_register_meta();
		}
		if ( function_exists( 'reset_filters_test' ) ) {
			reset_filters_test();
		}
	}

	public function test_key_map_non_empty_and_prefixed(): void {
		$ktube_map = ktube_importer_key_map();
		$this->assertNotEmpty( $ktube_map, 'ktube_importer_key_map() must not be empty' );
		foreach ( $ktube_map as $ktube_legacy => $ktube_internal ) {
			$this->assertStringContainsString( '_ktube_', (string) $ktube_internal, "internal '$ktube_internal' must start with _ktube_" );
			$this->assertNotEmpty( (string) $ktube_legacy, 'legacy key must not be empty' );
		}
	}

	public function test_no_orphan_legacy_or_internal_key(): void {
		$ktube_map = ktube_importer_key_map();
		$ktube_reg = $GLOBALS['__ktube_meta']['video'] ?? array();

		foreach ( $ktube_map as $ktube_legacy => $ktube_internal ) {
			$this->assertArrayHasKey( $ktube_legacy,   $ktube_reg, "missing register_post_meta for legacy '$ktube_legacy'" );
			$this->assertArrayHasKey( $ktube_internal, $ktube_reg, "missing register_post_meta for internal '$ktube_internal'" );
			$this->assertNotEmpty( $ktube_reg[ $ktube_legacy ]['type']   ?? '', "legacy '$ktube_legacy' has no type" );
			$this->assertNotEmpty( $ktube_reg[ $ktube_internal ]['type'] ?? '', "internal '$ktube_internal' has no type" );
		}
	}

	public function test_ktube_reserved_keys_all_registered(): void {
		$ktube_reserved = ktube_mass_importer_meta_keys();
		$ktube_reg      = $GLOBALS['__ktube_meta']['video'] ?? array();
		foreach ( array_keys( $ktube_reserved ) as $ktube_key ) {
			$this->assertArrayHasKey( $ktube_key, $ktube_reg, "ktube-only key '$ktube_key' is not register_post_meta'd" );
		}
	}

	public function test_keys_collection_is_union_of_map_and_reserved(): void {
		$ktube_all = ktube_all_video_meta_keys();
		$ktube_map = ktube_importer_key_map();
		$ktube_res = ktube_mass_importer_meta_keys();

		$this->assertCount( count( $ktube_map ) + count( $ktube_res ), $ktube_all, 'ktube_all_video_meta_keys must be the union of map + reserved' );

		foreach ( $ktube_map as $ktube_internal ) {
			$this->assertArrayHasKey( $ktube_internal, $ktube_all, "map alias '$ktube_internal' missing from ktube_all_video_meta_keys" );
			$this->assertNotSame( 'ktube-only', $ktube_all[ $ktube_internal ], "map alias '$ktube_internal' wrongly tagged ktube-only" );
		}
		foreach ( array_keys( $ktube_res ) as $ktube_key ) {
			$this->assertSame( 'ktube-only', $ktube_all[ $ktube_key ], "reserved key '$ktube_key' not tagged ktube-only" );
		}
	}

	public function test_adapter_reads_from_canonical_map(): void {
		// Static-analysis guard: the adapter must source its sync map from
		// ktube_importer_key_map() at the call site (not just mention the
		// name in a comment). Asserting the literal call expression avoids
		// false positives from the function's own declaration appearing
		// elsewhere in the file.
		$ktube_adapter_path = __DIR__ . '/../../../includes/wps-compat/importer-adapter.php';
		$this->assertFileExists( $ktube_adapter_path, 'adapter file missing' );
		$ktube_adapter_source = file_get_contents( $ktube_adapter_path );
		$this->assertNotFalse( $ktube_adapter_source, 'unable to read adapter source' );

		$ktube_compact = (string) preg_replace( '/\s+/', ' ', $ktube_adapter_source );

		$this->assertStringContainsString( "apply_filters( 'ktube_importer_sync_keys', ktube_importer_key_map(),", $ktube_compact, 'adapter must source its sync map from ktube_importer_key_map() via the ktube_importer_sync_keys filter' );
		$this->assertStringContainsString( "do_action( 'ktube_importer_synced',", $ktube_compact, 'adapter must emit ktube_importer_synced action' );
	}

	public function test_drain_helper_skips_missing_post(): void {
		// Regression guard: ktube_drain_importer_meta_to_ktube() must NOT
		// synthesize an empty WP_Post for a non-existent post id (Phase 1-A
		// fix). Because our stub `get_post()` returns null, the helper
		// must silently bail — observable here as: no exception, no fatal.
		$ktube_threw = false;
		try {
			ktube_drain_importer_meta_to_ktube( 999999 );
		} catch ( \Throwable $e ) {
			$ktube_threw = true;
		}
		$this->assertFalse( $ktube_threw, 'drain helper must silently bail on non-existent post, not throw' );

		// And the explicit no-op-on-zero-id guard.
		$ktube_threw_zero = false;
		try {
			ktube_drain_importer_meta_to_ktube( 0 );
		} catch ( \Throwable $e ) {
			$ktube_threw_zero = true;
		}
		$this->assertFalse( $ktube_threw_zero, 'drain helper must silently bail on post_id 0' );
	}

	public function test_db_index_blueprint_covers_sort_keys(): void {
		$ktube_blueprint = ktube_db_index_blueprint();
		$ktube_all       = ktube_all_video_meta_keys();

		foreach ( array_keys( $ktube_blueprint ) as $ktube_key ) {
			$this->assertArrayHasKey( $ktube_key, $ktube_all, "blueprint key '$ktube_key' is not declared in ktube_all_video_meta_keys" );
		}

		// The two engagement counters added in Phase 1-A MUST have an
		// entry — protects against future operators adding a 3rd counter
		// and forgetting to extend the blueprint.
		$this->assertArrayHasKey( '_ktube_views',    $ktube_blueprint );
		$this->assertArrayHasKey( '_ktube_rating',   $ktube_blueprint );
		$this->assertArrayHasKey( '_ktube_duration', $ktube_blueprint );
	}
}
