<?php
/**
 * WP-Script Mass Importer compatibility layer.
 *
 * LINEAGE NOTE
 * ============
 * The `ktube_importer_key_map()` below is NOT independently verifiable
 * against the closed-source WPS Mass Importer / "adult-mass-videos-embedder"
 * plugin binary by downstream maintainers — that plugin is proprietary
 * commercial software distributed only through wp-script.com customer
 * dashboards and has no public source repository.
 *
 * The map below was reconstructed from:
 *
 *   1. Public docs.wp-script.com parameter tables (Mass Grabber "fields"
 *      and "options" documentation pages).
 *   2. Public WP-Script REST schema fragments referenced from those docs.
 *   3. Observed legacy KingTube v2.x post-meta keys imported by that plugin
 *      on operating production installs.
 *
 * A maintainer who holds a current WPS Mass Grabber license can VERIFY
 * this contract empirically:
 *
 *   - Stage a clean WordPress install with ktube + the official
 *     Mass Grabber plugin.
 *   - Run a one-video import dump into the `video` CPT.
 *   - GET `/wp-json/wp/v2/videos/<id>` and inspect `meta` keys present.
 *   - Assert each returned key string is present in this map.
 *     Any extra keys surface via `ktube_discover_extra_importer_keys()`
 *     (returns an empty array today; the wiring runs in Phase 1-A
 *     collector followup — see `RELEASING.md` §LIN).
 *
 * Mismatches between the map and a current plugin binary SHOULD be raised
 * by filing an issue — the map is a contract, not a guess.
 *
 * @package ktube
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Canonical importer → ktube meta-key map.
 *
 * Filterable so MU-plugins can extend with partner-specific keys.
 *
 * @return array<string,string> legacy_key => ktube_alias
 */
function ktube_importer_key_map(): array {
	$default = array(
		// Core media metadata.
		'duration'       => '_ktube_duration',
		'video_url'      => '_ktube_video_url',
		'embed'          => '_ktube_embed_url',
		'trailer_url'    => '_ktube_trailer_url',
		'thumb'          => '_ktube_thumb_url',
		'thumbs'         => '_ktube_thumbs',

		// Identifiers + provenance.
		'video_id'       => '_ktube_source_id',
		'external_id'    => '_ktube_external_id',
		'source_url'     => '_ktube_source_url',

		// Partner / feed attribution.
		'partner'        => '_ktube_partner',
		'partner_cat'    => '_ktube_partner_cat',
		'feed'           => '_ktube_feed',

		// Boolean + textual flags.
		'is_vr'          => '_ktube_is_vr',
		'original_title' => '_ktube_original_title',
		'tracking_url'   => '_ktube_tracking_url',

		// Engagement counters (Phase 1-A additions; documented in db-indexes.php).
		'views'          => '_ktube_views',
		'rating'         => '_ktube_rating',
	);

	/**
	 * Filter the legacy → ktube meta-key map.
	 *
	 * @param array<string,string> $default Default map.
	 */
	return (array) apply_filters( 'ktube_importer_key_map', $default );
}

/**
 * ktube-reserved meta keys (no plugin counterpart).
 *
 * @return array<string,string> ktube_key => human description
 */
function ktube_mass_importer_meta_keys(): array {
	$default = array(
		'_ktube_quality_variants' => __( 'Quality variants (serialized array)',      'ktube' ),
		'_ktube_thumb_timestamps' => __( 'Storyboard frame timestamps',              'ktube' ),
		'_ktube_storyboard_url'   => __( 'Plugin-supplied WebP/GIF storyboard',      'ktube' ),
	);

	/**
	 * Filter the ktube-only meta key list.
	 *
	 * @param array<string,string> $default Default ktube-only key map.
	 */
	return (array) apply_filters( 'ktube_mass_importer_meta_keys', $default );
}

/**
 * Returns the union of importer key map + ktube-only reserved keys.
 *
 * Single source of truth for "every _ktube_* key this theme declares on
 * the video CPT" — used by db-indexes.php + tests so the index doc stays
 * in sync with the map.
 *
 * @return array<string,string> ktube_key => legacy_key|ktube-only
 */
function ktube_all_video_meta_keys(): array {
	$ktube_map      = ktube_importer_key_map();
	$ktube_reserved = ktube_mass_importer_meta_keys();

	$ktube_aliases = array();
	foreach ( $ktube_map as $ktube_legacy => $ktube_internal ) {
		$ktube_aliases[ $ktube_internal ] = $ktube_legacy;
	}
	foreach ( $ktube_reserved as $ktube_internal => $ktube_human ) {
		$ktube_aliases[ $ktube_internal ] = 'ktube-only';
	}

	return $ktube_aliases;
}

/**
 * Returns meta keys the importer might write that are NOT declared in
 * `ktube_importer_key_map()`. Today this always returns an empty array —
 * we have no live source for what the closed-source importer actually
 * scrapes beyond the public docs. The wiring runs in the Phase 1-A
 * collector followup (tools/release.mjs LIN-diff against the staging
 * REST response); for now the surface exists so callers don't need
 * defensive checks.
 *
 * Filterable so MU-plugins can override with their own binary-diff harness.
 *
 * @return array<string,string> legacy_key => observed_frequency
 */
function ktube_discover_extra_importer_keys(): array {
	/**
	 * Filter the discovered-extra-keys bag.
	 *
	 * @param array<string,string> $ktube_default Default empty collection.
	 */
	return (array) apply_filters( 'ktube_discover_extra_importer_keys', array() );
}

/**
 * Shim hook — reserved for Phase 9 cross-plugin REST controllers.
 */
function ktube_register_mass_importer_compat(): void {
	// Reserved for future parity hardening.
}
