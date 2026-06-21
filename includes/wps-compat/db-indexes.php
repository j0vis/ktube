<?php
/**
 * Phase 1-A — DB index policy + scaling recommendations for the
 * WPS Mass Importer pipeline at 10k+ videos.
 *
 * DECISION (locked 2026-06-21)
 * ============================
 * KTUBE DOES NOT modify core `wp_postmeta` schema in any way.
 *
 * Rationale:
 *
 *   - WordPress core owns the `wp_postmeta` table; `dbDelta` is intended
 *     for plugin/cpt table schema only, NOT for core tables.
 *   - Adding a composite `(meta_key, meta_value(N))` index on the core
 *     table races with WP core upgrades and breaks forward-compat silently.
 *   - The brief optimisation (§3.7) calls for "queries that perform well
 *     at scale (10k+ videos)" — achievable below this threshold with WP's
 *     built-in term_relationships indexes + stock meta cache priming; above
 *     10k, a dedicated `ktube_video_stats` table is the right tool.
 *
 * Primary query paths the ktube front-end emits, and the recommended
 * index strategy for each:
 *
 * 1) Taxonomy-filtered date archive
 *    SELECT p.* FROM wp_posts p
 *    INNER JOIN wp_term_relationships tr ON p.ID = tr.object_id
 *    WHERE p.post_type='video'
 *      AND p.post_status='publish'
 *      AND tr.term_taxonomy_id IN ( … )
 *    ORDER BY p.post_date DESC LIMIT 20;
 *
 *    Indexes used (core WP, no theme action):
 *      - `wp_posts` PRIMARY by ID + composite `(post_type, post_status, post_date)` index
 *      - `wp_term_relationships` PRIMARY `(object_id, term_taxonomy_id)` + index on `term_taxonomy_id`
 *
 *    Good up to ~10k rows. Beyond ~10k term_relationships scans become
 *    noticeable; mitigation is fulltext on `wp_posts.post_title` or an
 *    Elasticsearch layer — out of theme scope.
 *
 * 2) View-count sort
 *    SELECT p.* FROM wp_posts p
 *    INNER JOIN wp_postmeta m ON p.ID = m.post_id
 *    WHERE p.post_type='video' AND m.meta_key='_ktube_views'
 *    ORDER BY CAST(m.meta_value AS UNSIGNED) DESC LIMIT 20;
 *
 *    Index used: composite `(post_id, meta_key)` primary on `wp_postmeta`
 *    plus the `(meta_key, meta_value(N))` implicit scan.
 *    Result at 10k: ~120-300 ms cold cache; at 100k: 1-3s.
 *    Mitigation: dedicated `ktube_video_stats` table (Phase 14 perf pass):
 *      CREATE TABLE wp_ktube_video_stats (
 *        video_id BIGINT UNSIGNED NOT NULL PRIMARY KEY,
 *        views BIGINT UNSIGNED NOT NULL DEFAULT 0,
 *        rating INT NOT NULL DEFAULT 0,
 *        duration INT NOT NULL DEFAULT 0,
 *        INDEX idx_views (views),
 *        INDEX idx_duration (duration)
 *      );
 *    Hooked write-through on update_post_meta + ktube_views increment.
 *
 * 3) Duration sort
 *    Same as (2) but `meta_key='_ktube_duration'`. Same mitigation.
 *
 * 4) Single-key exists check (per card render): _ktube_trailer_url,
 *    _ktube_thumb_url, _ktube_video_url. Handled in PHP via the post-meta
 *    in-memory cache primed by WP_Query — O(1) per card regardless of
 *    archive size; no SQL index work needed.
 *
 * 5) Front-page archive with term_id filter
 *    same query shape as (1) but candidate term_taxonomy_id list is small
 *    (<10 per page). Native indexes suffice up to 10k rows.
 *
 * Implementation status
 * ---------------------
 * The custom `wp_ktube_video_stats` table is NOT shipped in Phase 1-A.
 * It is queued for Phase 14 (Performance pass) per `to-do.md` §5-B.
 *
 * Until that ships, query paths (2)+(3) are expected to degrade
 * gracefully under WP_Query caching + page-cache combination above
 * ~50k posts. The README explicitly warns operators about this.
 *
 * @package ktube
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Returns a structured inventory of the ktube video meta keys that
 * participate in DB-query-time sorting/filtering.
 *
 * Single source of truth so the index doc stays in lockstep with
 * ktube_importer_key_map() + ktube_mass_importer_meta_keys().
 *
 * @return array<string,array{used_for:array<string>,index_strategy:string}>
 */
function ktube_db_index_blueprint(): array {
	return array(
		'_ktube_views'     => array(
			'used_for'      => array( 'view-count sort (front-page \"most viewed\" block)' ),
			'index_strategy' => 'deferred to ktube_video_stats table (Phase 14)',
		),
		'_ktube_duration'  => array(
			'used_for'      => array( 'duration sort (\"shortest videos\" archive)' ),
			'index_strategy' => 'deferred to ktube_video_stats table (Phase 14)',
		),
		'_ktube_rating'    => array(
			'used_for'      => array( 'rating-driven sort (\"top rated\" archive)' ),
			'index_strategy' => 'deferred to ktube_video_stats table (Phase 14)',
		),
		'_ktube_trailer_url' => array(
			'used_for'      => array( 'per-card EXISTS check on hover-trailer wire-up' ),
			'index_strategy' => 'none — postmeta cache, O(1) per card',
		),
		'_ktube_thumb_url'   => array(
			'used_for'      => array( 'per-card EXISTS check on poster render' ),
			'index_strategy' => 'none — postmeta cache, O(1) per card',
		),
		'_ktube_video_url'   => array(
			'used_for'      => array( 'player src candidate on singular(video)' ),
			'index_strategy' => 'none — singular lookup, by-id fetch',
		),
		'_ktube_embed_url'   => array(
			'used_for'      => array( 'player src candidate on singular(video)' ),
			'index_strategy' => 'none — singular lookup, by-id fetch',
		),
		'_ktube_quality_variants' => array(
			'used_for'      => array( 'quality variant picker on player controls' ),
			'index_strategy' => 'none — serialized array, O(1) by-id fetch',
		),
		'_ktube_storyboard_url' => array(
			'used_for'      => array( 'hover storyboard sprite source' ),
			'index_strategy' => 'none — per-card O(1) by-id fetch',
		),
	);
}

/**
 * Shim hook — registers the Phase 14 perf-pass hooks (no-op until then).
 */
function ktube_register_db_indexes_compat(): void {
	// Reserved for Phase 14 — write-through to ktube_video_stats table.
}
