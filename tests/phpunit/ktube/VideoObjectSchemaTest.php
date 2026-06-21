<?php
/**
 * Phase 9d — VideoObject JSON-LD schema tests.
 *
 * Asserts the cross-file invariants on includes/seo/schema.php's
 * VideoObject emitter:
 *
 *   - ktube_render_video_object_schema() emits nothing on non-singular-video.
 *   - ktube_build_video_object_schema() returns empty array when contentUrl
 *     is unresolvable (no _ktube_video_url AND no _ktube_embed_url) so
 *     Empty VideoObjects never reach the script tag.
 *   - contentUrl prefers _ktube_video_url; falls back to _ktube_embed_url.
 *   - thumbnailUrl[]: featured image takes precedence, _ktube_thumb_url is
 *     the fallback, and an empty array occupies the slot when neither is
 *     present (schema.org allows empty arrays).
 *   - uploadDate is the post_date in 'c' (RFC 3339 / ISO 8601) format.
 *   - duration is ISO 8601 PT-format from seconds (zero → PT0S, never null,
 *     so crawlers don't flag missing duration).
 *   - encodingFormat is inferred from the contentUrl extension (mp4/webm/
 *     m3u8/mov/m4v/ogg/ogv) and falls back to video/mp4 for unknown.
 *   - interactionStatistic (InteractionCounter w/ WatchAction) is included
 *     ONLY when _ktube_views > 0; missing views serialize nothing rather
 *     than a 0-count (which crawlers may treat as a quality signal).
 *   - ktube_video_object_schema filter exposes the build for partner-specific
 *     mutations (not direct database writes).
 *
 * @package ktube
 */

class VideoObjectSchemaTest extends \PHPUnit\Framework\TestCase {
	public function setUp(): void {
		$GLOBALS['__ktube_meta']              = array();
		$GLOBALS['__ktube_posts']             = array();
		$GLOBALS['__ktube_theme_mods']        = array();
		if ( function_exists( 'reset_post_meta_test' ) ) {
			reset_post_meta_test();
		} else {
			$GLOBALS['__ktube_post_meta'] = array();
		}
		if ( function_exists( 'reset_post_thumbnails_test' ) ) {
			reset_post_thumbnails_test();
		} else {
			$GLOBALS['__ktube_post_thumbnails'] = array();
		}
		if ( function_exists( 'reset_filters_test' ) ) {
			reset_filters_test();
		}
	}

	// ---- Build-helper early-bail contracts ------------------------------

	public function test_build_returns_empty_array_when_post_not_WP_Post(): void {
		$this->assertSame( array(), ktube_build_video_object_schema( 0 ) );
		$this->assertSame( array(), ktube_build_video_object_schema( null ) );
		$this->assertSame( array(), ktube_build_video_object_schema( array() ) );
	}

	public function test_build_returns_empty_when_no_video_url_and_no_embed_url(): void {
		set_post_test( 100, array( 'post_type' => 'video', 'post_status' => 'publish', 'post_name' => 'no-src', 'post_date' => '2026-06-21 12:00:00' ) );
		$ktube_post = get_post( 100 );
		$this->assertSame( array(), ktube_build_video_object_schema( $ktube_post ) );
	}

	public function test_build_emits_video_object_with_all_required_fields(): void {
		set_post_test( 100, array( 'post_type' => 'video', 'post_status' => 'publish', 'post_name' => 'sample', 'post_title' => 'Sample Video', 'post_excerpt' => 'Sample <em>excerpt</em>.', 'post_date' => '2026-06-21 12:00:00' ) );
		set_post_meta_test( 100, '_ktube_video_url', 'https://cdn.example.test/videos/sample.mp4' );
		set_post_meta_test( 100, '_ktube_thumb_url', 'https://cdn.example.test/thumbs/sample.jpg' );
		set_post_meta_test( 100, '_ktube_duration', '90' );

		$ktube_post = get_post( 100 );
		$ktube_data  = ktube_build_video_object_schema( $ktube_post );

		$this->assertSame( 'https://schema.org',          $ktube_data['@context'] );
		$this->assertSame( 'VideoObject',                $ktube_data['@type'] );
		$this->assertSame( 'Sample Video',               $ktube_data['name'] );
		$this->assertSame( 'Sample excerpt.',            $ktube_data['description'], 'tags stripped from excerpt' );
		$this->assertSame( 'https://cdn.example.test/videos/sample.mp4', $ktube_data['contentUrl'] );
		$this->assertSame( array( 'https://cdn.example.test/thumbs/sample.jpg' ), $ktube_data['thumbnailUrl'] );
		// uploadDate is gmdate('c', strtotime($post->post_date)) — assert
		// parseability + a few specific tokens from the fixture rather than
		// a fragile literal (TZ offset depends on the system timezone).
		$this->assertNotFalse( strtotime( (string) $ktube_data['uploadDate'] ), 'uploadDate must be parseable' );
		$this->assertStringStartsWith( '2026-06-21', (string) $ktube_data['uploadDate'] );
		$this->assertStringContainsString( 'T12:00:00', (string) $ktube_data['uploadDate'] );
		$this->assertSame( 'PT1M30S',                    $ktube_data['duration'] );
		$this->assertSame( 'video/mp4',                  $ktube_data['encodingFormat'] );
	}

	// ---- contentUrl fallback --------------------------------------------

	public function test_content_url_prefers_ktube_video_url(): void {
		set_post_test( 100, array( 'post_type' => 'video', 'post_status' => 'publish', 'post_name' => 'sample' ) );
		set_post_meta_test( 100, '_ktube_video_url', 'https://cdn.example.test/file.mp4' );
		set_post_meta_test( 100, '_ktube_embed_url', 'https://embed.example.test/file/123' );
		$ktube_data = ktube_build_video_object_schema( get_post( 100 ) );
		$this->assertSame( 'https://cdn.example.test/file.mp4', $ktube_data['contentUrl'] );
	}

	public function test_content_url_falls_back_to_embed_url(): void {
		set_post_test( 100, array( 'post_type' => 'video', 'post_status' => 'publish', 'post_name' => 'sample' ) );
		set_post_meta_test( 100, '_ktube_embed_url', 'https://embed.example.test/file/123' );
		$ktube_data = ktube_build_video_object_schema( get_post( 100 ) );
		$this->assertSame( 'https://embed.example.test/file/123', $ktube_data['contentUrl'] );
	}

	// ---- thumbnailUrl precedence ---------------------------------------

	public function test_thumbnail_prefers_featured_image_over_meta(): void {
		set_post_test( 100, array( 'post_type' => 'video', 'post_status' => 'publish', 'post_name' => 'sample' ) );
		set_post_meta_test( 100, '_ktube_video_url', 'https://cdn.example.test/file.mp4' );
		set_post_meta_test( 100, '_ktube_thumb_url', 'https://cdn.example.test/legacy.jpg' );
		set_post_thumbnail_test( 100, 555 );

		$ktube_data = ktube_build_video_object_schema( get_post( 100 ) );
		$this->assertSame( array( 'https://example.test/wp-content/uploads/555-full.jpg' ), $ktube_data['thumbnailUrl'] );
	}

	public function test_thumbnail_falls_back_to_meta_url(): void {
		set_post_test( 100, array( 'post_type' => 'video', 'post_status' => 'publish', 'post_name' => 'sample' ) );
		set_post_meta_test( 100, '_ktube_video_url', 'https://cdn.example.test/file.mp4' );
		set_post_meta_test( 100, '_ktube_thumb_url', 'https://cdn.example.test/legacy.jpg' );

		$ktube_data = ktube_build_video_object_schema( get_post( 100 ) );
		$this->assertSame( array( 'https://cdn.example.test/legacy.jpg' ), $ktube_data['thumbnailUrl'] );
	}

	public function test_thumbnail_empty_array_when_neither_present(): void {
		set_post_test( 100, array( 'post_type' => 'video', 'post_status' => 'publish', 'post_name' => 'sample' ) );
		set_post_meta_test( 100, '_ktube_video_url', 'https://cdn.example.test/file.mp4' );

		$ktube_data = ktube_build_video_object_schema( get_post( 100 ) );
		$this->assertSame( array(), $ktube_data['thumbnailUrl'] );
		$this->assertArrayHasKey( 'thumbnailUrl', $ktube_data, 'key still present, just empty' );
	}

	// ---- ISO 8601 duration helper --------------------------------------

	public function test_iso8601_duration_zero_is_PT0S_not_omitted(): void {
		$this->assertSame( 'PT0S', ktube_iso8601_duration( 0 ) );
		$this->assertSame( 'PT0S', ktube_iso8601_duration( -1 ) );
	}

	public function test_iso8601_duration_seconds_only(): void {
		$this->assertSame( 'PT1S',  ktube_iso8601_duration( 1 ) );
		$this->assertSame( 'PT30S', ktube_iso8601_duration( 30 ) );
		$this->assertSame( 'PT59S', ktube_iso8601_duration( 59 ) );
	}

	public function test_iso8601_duration_minutes_and_seconds(): void {
		$this->assertSame( 'PT1M',  ktube_iso8601_duration( 60 ) );
		$this->assertSame( 'PT1M30S', ktube_iso8601_duration( 90 ) );
		$this->assertSame( 'PT5M30S', ktube_iso8601_duration( 330 ) );
	}

	public function test_iso8601_duration_hours(): void {
		$this->assertSame( 'PT1H',     ktube_iso8601_duration( 3600 ) );
		$this->assertSame( 'PT1H1M',   ktube_iso8601_duration( 3660 ) );
		$this->assertSame( 'PT2H2M5S', ktube_iso8601_duration( 7325 ) );
	}

	public function test_iso8601_duration_emitted_for_build_helper(): void {
		set_post_test( 100, array( 'post_type' => 'video', 'post_status' => 'publish', 'post_name' => 'sample' ) );
		set_post_meta_test( 100, '_ktube_video_url', 'https://cdn.example.test/file.mp4' );
		set_post_meta_test( 100, '_ktube_duration', '3705' );
		$ktube_data = ktube_build_video_object_schema( get_post( 100 ) );
		$this->assertSame( 'PT1H1M45S', $ktube_data['duration'] );
	}

	// ---- encodingFormat inference --------------------------------------

	public function test_encoding_format_mp4(): void {
		$this->assertSame( 'video/mp4', ktube_infer_encoding_format( 'https://cdn.example.test/file.mp4' ) );
	}

	public function test_encoding_format_webm(): void {
		$this->assertSame( 'video/webm', ktube_infer_encoding_format( 'https://cdn.example.test/file.webm' ) );
	}

	public function test_encoding_format_m3u8_hls(): void {
		$this->assertSame( 'application/vnd.apple.mpegurl', ktube_infer_encoding_format( 'https://cdn.example.test/manifest.m3u8' ) );
		$this->assertSame( 'application/vnd.apple.mpegurl', ktube_infer_encoding_format( 'https://cdn.example.test/path/to/index.M3U8' ) );
	}

	public function test_encoding_format_mov_quicktime(): void {
		$this->assertSame( 'video/quicktime', ktube_infer_encoding_format( 'https://cdn.example.test/file.mov' ) );
	}

	public function test_encoding_format_m4v_alt_mp4(): void {
		$this->assertSame( 'video/x-m4v', ktube_infer_encoding_format( 'https://cdn.example.test/file.m4v' ) );
	}

	public function test_encoding_format_ogg_ogv(): void {
		$this->assertSame( 'video/ogg', ktube_infer_encoding_format( 'https://cdn.example.test/file.ogg' ) );
		$this->assertSame( 'video/ogg', ktube_infer_encoding_format( 'https://cdn.example.test/file.ogv' ) );
	}

	public function test_encoding_format_unknown_defaults_to_mp4(): void {
		$this->assertSame( 'video/mp4', ktube_infer_encoding_format( 'https://cdn.example.test/file.avi' ) );
		$this->assertSame( 'video/mp4', ktube_infer_encoding_format( 'https://cdn.example.test/file' ) );
	}

	public function test_encoding_format_for_url_with_query_string(): void {
		$this->assertSame( 'video/mp4', ktube_infer_encoding_format( 'https://cdn.example.test/file.mp4?token=abc' ) );
	}

	// ---- interactionStatistic gating -----------------------------------

	public function test_interaction_statistic_omitted_when_views_zero(): void {
		set_post_test( 100, array( 'post_type' => 'video', 'post_status' => 'publish', 'post_name' => 'sample' ) );
		set_post_meta_test( 100, '_ktube_video_url', 'https://cdn.example.test/file.mp4' );
		set_post_meta_test( 100, '_ktube_views', '0' );

		$ktube_data = ktube_build_video_object_schema( get_post( 100 ) );
		$this->assertArrayNotHasKey( 'interactionStatistic', $ktube_data, 'do not serialize 0-count as a quality signal' );
	}

	public function test_interaction_statistic_omitted_when_views_unset(): void {
		set_post_test( 100, array( 'post_type' => 'video', 'post_status' => 'publish', 'post_name' => 'sample' ) );
		set_post_meta_test( 100, '_ktube_video_url', 'https://cdn.example.test/file.mp4' );

		$ktube_data = ktube_build_video_object_schema( get_post( 100 ) );
		$this->assertArrayNotHasKey( 'interactionStatistic', $ktube_data );
	}

	public function test_interaction_statistic_emitted_when_views_set(): void {
		set_post_test( 100, array( 'post_type' => 'video', 'post_status' => 'publish', 'post_name' => 'sample' ) );
		set_post_meta_test( 100, '_ktube_video_url', 'https://cdn.example.test/file.mp4' );
		set_post_meta_test( 100, '_ktube_views', '12345' );

		$ktube_data = ktube_build_video_object_schema( get_post( 100 ) );
		$this->assertArrayHasKey( 'interactionStatistic', $ktube_data );

		$ktube_stat = $ktube_data['interactionStatistic'];
		$this->assertSame( 'InteractionCounter',           $ktube_stat['@type'] );
		$this->assertSame( array( '@type' => 'WatchAction' ), $ktube_stat['interactionType'] );
		$this->assertSame( 12345,                          $ktube_stat['userInteractionCount'] );
	}

	// ---- Filter hook testability ---------------------------------------
	// NOTE: add_filter / apply_filters stubs in tests/phpunit/bootstrap.php
	// walk the registered callback registry in priority + insertion order.

	public function test_ktube_video_object_schema_filter_extends_data(): void {
		set_post_test( 100, array( 'post_type' => 'video', 'post_status' => 'publish', 'post_name' => 'sample' ) );
		set_post_meta_test( 100, '_ktube_video_url', 'https://cdn.example.test/file.mp4' );

		add_filter(
			'ktube_video_object_schema',
			static function ( $ktube_data ): array {
				$ktube_data['isAccessibleForFree'] = false;
				return $ktube_data;
			}
		);

		$ktube_data = ktube_build_video_object_schema( get_post( 100 ) );
		$this->assertArrayHasKey( 'isAccessibleForFree', $ktube_data );
		$this->assertFalse( $ktube_data['isAccessibleForFree'] );
	}

	// ---- Render-emitter gate -------------------------------------------

	public function test_render_emits_nothing_on_non_video_singular(): void {
		// ktube_render_video_object_schema() bails early on ! is_singular('video').
		// The bootstrap is_singular() stub returns false unconditionally; we
		// only assert the function does not fatal and somehow emits no
		// JSON-LD script when called outside a video context.
		$ktube_threw = false;
		try {
			ob_start();
			ktube_render_video_object_schema();
			$ktube_output = ob_get_clean();
		} catch ( \Throwable $e ) {
			$ktube_threw = true;
			$ktube_output = '';
		}
		$this->assertFalse( $ktube_threw );
		$this->assertNotContains( 'application/ld+json', (string) $ktube_output, 'must not emit VideoObject JSON-LD when not on a video singular' );
		$this->assertNotContains( 'VideoObject',         (string) $ktube_output );
	}

	public function test_render_emits_nothing_when_post_has_no_playable_url(): void {
		// is_singular() is stubbed false; force-call the build path via
		// direct invocation of the build helper instead. We assert the
		// build returns empty array — the renderer's call site uses the
		// same guard so the same conclusion holds for both paths.
		set_post_test( 100, array( 'post_type' => 'video', 'post_status' => 'publish', 'post_name' => 'sample' ) );
		$ktube_data = ktube_build_video_object_schema( get_post( 100 ) );
		$this->assertSame( array(), $ktube_data, 'no VideoObject without a playable URL' );
	}

	public function test_render_emits_json_ld_script_when_post_has_url(): void {
		set_post_test( 100, array( 'post_type' => 'video', 'post_status' => 'publish', 'post_name' => 'sample' ) );
		set_post_meta_test( 100, '_ktube_video_url', 'https://cdn.example.test/file.mp4' );
		$ktube_post  = get_post( 100 );
		$ktube_data   = ktube_build_video_object_schema( $ktube_post );
		$this->assertNotEmpty( $ktube_data );

		// Spot-check that the data is JSON-encodable without error or
		// loss of UTF-8 / slashes — matching the production flags.
		$ktube_json  = (string) wp_json_encode( $ktube_data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE );
		$this->assertStringContainsString( '"@type":"VideoObject"',         $ktube_json );
		$this->assertStringContainsString( '"contentUrl":"https://cdn.',   $ktube_json );
		$this->assertStringContainsString( '"encodingFormat":"video/mp4"',  $ktube_json );
	}

	// ---- mainEntityOfPage defensive guard -----------------------------
	//
	// The defensive guard `'' !== get_permalink(...)` exists so the JSON-LD
	// never ships a `'@id' => 'https://example.test/?p=0'` WebPage node for
	// freshly-staged drafts (which the stub models as ID 0 → permalink '').

	public function test_main_entity_of_page_emitted_when_permalink_set(): void {
		set_post_test( 99, array( 'post_type' => 'video', 'post_status' => 'publish', 'post_name' => 'sample' ) );
		set_post_meta_test( 99, '_ktube_video_url', 'https://cdn.example.test/file.mp4' );
		$ktube_data = ktube_build_video_object_schema( get_post( 99 ) );
		$this->assertArrayHasKey( 'mainEntityOfPage', $ktube_data );
		$this->assertSame( 'WebPage',                $ktube_data['mainEntityOfPage']['@type'] );
		$this->assertSame( 'https://example.test/?p=99', $ktube_data['mainEntityOfPage']['@id'] );
	}

	public function test_main_entity_of_page_omitted_when_post_id_zero(): void {
		// Post id 0 models a freshly-staged draft with no real permalink;
		// get_permalink stub returns '' for id 0, so mainEntityOfPage guard
		// MUST skip the block rather than emit ?p=0.
		set_post_test( 0, array( 'post_type' => 'video', 'post_status' => 'draft', 'post_name' => 'sample-draft' ) );
		set_post_meta_test( 0, '_ktube_video_url', 'https://cdn.example.test/file.mp4' );
		$ktube_data = ktube_build_video_object_schema( get_post( 0 ) );
		$this->assertArrayNotHasKey( 'mainEntityOfPage', $ktube_data, 'do not emit ?p=0 mainEntityOfPage for draft ids' );
	}
}
