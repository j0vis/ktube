<?php
/**
 * WP-Script Mass Importer adapter — sync legacy keys to internal ktube keys.
 *
 * Copies the importer's public meta keys onto ktube's prefixed equivalents
 * whenever save_post_video fires. Keeps templates compatible with the
 * canonical ktube key namespace while remaining a no-op when the
 * importer hasn't been used.
 *
 * Phase 1-A: the sync map is now sourced from `ktube_importer_key_map()`
 * (single source of truth) instead of being duplicated here. Adding a
 * legacy→ktube key pair to `ktube_importer_key_map()` automatically
 * extends this adapter with no further edits.
 *
 * @package ktube
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * ktube_register_importer_adapter — init hook.
 */
function ktube_register_importer_adapter(): void {
	add_action( 'save_post_video', 'ktube_sync_importer_meta_on_save', 20, 3 );
}

/**
 * Copy importer-written meta keys onto ktube's prefixed equivalents.
 *
 * Auth gate: only fires when the current user can edit the video.
 *
 * @param int     $post_id Post ID.
 * @param WP_Post $post    Post object.
 * @param bool    $update  Whether this is an existing post being updated.
 */
function ktube_sync_importer_meta_on_save( int $post_id, WP_Post $post, bool $update ): void {
	if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
		return;
	}
	if ( function_exists( 'wp_is_post_revision' ) && function_exists( 'wp_is_post_autosave' ) ) {
		if ( wp_is_post_revision( $post_id ) || wp_is_post_autosave( $post_id ) ) {
			return;
		}
	}
	if ( ! current_user_can( 'edit_post', $post_id ) ) {
		return;
	}

	/**
	 * Filter the legacy → ktube key map at sync time.
	 *
	 * Defaults to ktube_importer_key_map().
	 *
	 * @param array<string,string> $ktube_map Map of legacy → ktube aliases.
	 * @param int                  $post_id  Post being saved.
	 */
	$ktube_map = (array) apply_filters( 'ktube_importer_sync_keys', ktube_importer_key_map(), $post_id );

	foreach ( $ktube_map as $ktube_legacy => $ktube_internal ) {
		$ktube_legacy = (string) $ktube_legacy;
		$ktube_value  = get_post_meta( $post_id, $ktube_legacy, true );
		if ( '' === $ktube_value || null === $ktube_value ) {
			continue;
		}
		$ktube_existing = get_post_meta( $post_id, $ktube_internal, true );
		if ( (string) $ktube_existing === (string) $ktube_value ) {
			continue;
		}
		update_post_meta( $post_id, $ktube_internal, $ktube_value );
	}

	/**
	 * Fires after importer → ktube meta sync completes.
	 *
	 * @param int                 $post_id  Post ID.
	 * @param array<string,string> $ktube_map Map of legacy → ktube keys.
	 */
	do_action( 'ktube_importer_synced', $post_id, $ktube_map );
}

/**
 * Drains the bootstrap-known set of meta keys to ensure all importer-
 * provided keys land on both the legacy and ktube alias sides of the
 * post. Called from upgrade routines / on plugin activation.
 *
 * Silently no-ops if the post no longer exists — we'd otherwise synthesize
 * an empty WP_Post object and run a no-op sync against it, which is a
 * silent "sync succeeded" log line for a non-existent id.
 *
 * @param int $post_id Post ID to drain onto.
 */
function ktube_drain_importer_meta_to_ktube( int $post_id ): void {
	if ( $post_id <= 0 ) {
		return;
	}
	$ktube_post = get_post( $post_id );
	if ( ! $ktube_post instanceof WP_Post ) {
		return;
	}
	ktube_sync_importer_meta_on_save( $post_id, $ktube_post, true );
}
