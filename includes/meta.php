<?php
/**
 * Custom meta registrations for video + photo CPTs.
 *
 * Two layers of keys:
 *   1. ktube-canonical keys (`_ktube_*`) — templates read these.
 *   2. Legacy keys the WP-Script Mass Importer writes directly on the
 *      public post-meta — registered as show_in_rest so the importer's
 *      REST writes succeed. Synced to ktube-canonical via
 *      includes/wps-compat/importer-adapter.php on save_post_video.
 *
 * @package ktube
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * ktube_register_meta — runs on init priority 11 (after CPTs).
 */
function ktube_register_meta(): void {
	$auth = static function ( $allowed, $meta_key, $post_id ): bool {
		return current_user_can( 'edit_post', $post_id );
	};

	// ktube-canonical keys.
	$ktube_meta = array(
		'_ktube_duration'         => 'integer',
		'_ktube_video_url'        => 'string',
		'_ktube_embed_url'        => 'string',
		'_ktube_trailer_url'      => 'string',
		'_ktube_thumb_url'        => 'string',
		'_ktube_thumbs'           => 'string',
		'_ktube_source_id'        => 'string',
		'_ktube_partner'          => 'string',
		'_ktube_partner_cat'      => 'string',
		'_ktube_feed'             => 'string',
		'_ktube_is_vr'            => 'boolean',
		'_ktube_original_title'   => 'string',
		'_ktube_tracking_url'     => 'string',
		'_ktube_views'            => 'integer',
		'_ktube_rating'           => 'integer',
		'_ktube_source_url'       => 'string',
		'_ktube_external_id'      => 'string',
		'_ktube_quality_variants' => 'string',
		'_ktube_thumb_timestamps' => 'string',
		'_ktube_storyboard_url'   => 'string',
	);

	foreach ( $ktube_meta as $key => $type ) {
		register_post_meta(
			'video',
			$key,
			array(
				'type'         => $type,
				'single'       => true,
				'show_in_rest' => true,
				'auth_callback'=> $auth,
			)
		);
	}

	// Verbatim legacy keys the WP-Script Mass Importer writes on the post.
	// Registered show_in_rest so REST writes succeed without modification.
	// NOTE: must stay in lock-step with ktube_importer_key_map() in
	// includes/wps-compat/mass-importer.php — adding a new entry there
	// without adding the legacy key here will silently drop REST writes.
	// MassImporterCompatTest asserts the bidirectional completeness.
	$ktube_legacy_meta = array(
		'duration'       => 'integer',
		'video_url'      => 'string',
		'embed'          => 'string',
		'trailer_url'    => 'string',
		'thumb'          => 'string',
		'thumbs'         => 'string',
		'video_id'       => 'string',
		'partner'        => 'string',
		'partner_cat'    => 'string',
		'feed'           => 'string',
		'is_vr'          => 'boolean',
		'original_title' => 'string',
		'tracking_url'   => 'string',
		// Phase 1-A additions — engagement counters + provenance.
		'views'          => 'integer',
		'rating'         => 'integer',
		'source_url'     => 'string',
		'external_id'    => 'string',
	);

	foreach ( $ktube_legacy_meta as $key => $type ) {
		register_post_meta(
			'video',
			$key,
			array(
				'type'         => $type,
				'single'       => true,
				'show_in_rest' => true,
				'auth_callback'=> $auth,
			)
		);
	}

	// Photo CPT meta.
	register_post_meta(
		'photo',
		'_ktube_photo_set',
		array(
			'type'         => 'string',
			'single'       => true,
			'show_in_rest' => true,
			'auth_callback'=> $auth,
		)
	);

	register_post_meta(
		'photo',
		'_ktube_cover_photo_id',
		array(
			'type'         => 'integer',
			'single'       => true,
			'show_in_rest' => true,
			'auth_callback'=> $auth,
		)
	);
}
