<?php
/**
 * Schema.org JSON-LD + Open Graph + Twitter Card emitters.
 *
 * Hooked on `wp_head` (priority 1) so they run before any other theme head markup.
 *
 *   - ktube_render_blog_article_schema        — single-blog → Article
 *   - ktube_render_photo_image_object_schema  — single-photo → ImageGallery w/ ImageObjects
 *   - ktube_render_video_object_schema        — single-video → VideoObject (Phase 9d, brief §6)
 *   - ktube_render_open_graph_meta            — singles (video|blog|photo) → OG tags
 *   - ktube_render_twitter_card_meta          — singles → Twitter card tags
 *
 * @package ktube
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * ktube_register_schema — init hook loader.
 */
function ktube_register_schema(): void {
	add_action( 'wp_head', 'ktube_render_open_graph_meta', 1 );
	add_action( 'wp_head', 'ktube_render_twitter_card_meta', 1 );
	add_action( 'wp_head', 'ktube_render_blog_article_schema', 2 );
	add_action( 'wp_head', 'ktube_render_photo_image_object_schema', 2 );
	add_action( 'wp_head', 'ktube_render_video_object_schema', 2 );
}

/**
 * Render Article JSON-LD for single-blog.
 *
 * Phase 9e upgrade: articleBody (from `wp_strip_all_tags the_content`) +
 * keywords (from `get_the_terms post_tag`) feed richer rich-snippets per
 * brief §6 (carryover from §5-B #10). Both are OPT-IN: empty post_content
 * or zero tags OMIT the field so the schema doesn't ship misleading
 * blanks. The build helper below is the testable+filterable core; this
 * outer function is just the wp_head priority-2 emitter.
 */
function ktube_render_blog_article_schema(): void {
	if ( ! is_singular( 'blog' ) ) {
		return;
	}
	$ktube_post = get_post();
	if ( ! $ktube_post ) {
		return;
	}
	$ktube_data = ktube_build_blog_article_schema( $ktube_post );
	wp_print_inline_script_tag(
		(string) wp_json_encode( $ktube_data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ),
		array( 'type' => 'application/ld+json' )
	);
}

/**
 * Build the Article schema array (extracted for testability + filterability;
 * mirrors the Phase 9d `ktube_build_video_object_schema` pattern).
 *
 * Schema.org / Article reference:
 *   - headline         required (Article.headline)
 *   - description      optional (Article.description; we emit the
 *                      wp_strip_all_tags'd excerpt)
 *   - datePublished    required (ISO 8601 / RFC 3339)
 *   - dateModified     required (ISO 8601 / RFC 3339)
 *   - author           recommended (Person node)
 *   - publisher        recommended (Organization node)
 *   - mainEntityOfPage recommended (WebPage @id)
 *   - image            optional  (URL or array; we emit array form)
 *   - articleBody      optional  (Phase 9e add; the verbatim body text)
 *   - keywords         optional  (Phase 9e add; visible tag names)
 *
 * @param WP_Post $ktube_post
 * @return array<string,mixed>
 */
function ktube_build_blog_article_schema( $ktube_post ): array {
	if ( ! ( $ktube_post instanceof WP_Post ) ) {
		return array();
	}
	$ktube_thumb_id   = (int) get_post_thumbnail_id( $ktube_post->ID );
	$ktube_image_url  = $ktube_thumb_id ? (string) wp_get_attachment_image_url( $ktube_thumb_id, 'full' ) : '';
	$ktube_author_obj = array(
		'@type' => 'Person',
		'name'  => get_the_author_meta( 'display_name', (int) $ktube_post->post_author ),
		'url'   => get_author_posts_url( (int) $ktube_post->post_author ),
	);
	$ktube_publisher_obj = array(
		'@type' => 'Organization',
		'name'  => get_bloginfo( 'name' ),
		'url'   => home_url( '/' ),
	);
	$ktube_data = array(
		'@context'         => 'https://schema.org',
		'@type'            => 'Article',
		'headline'         => get_the_title( $ktube_post ),
		'description'      => wp_strip_all_tags( (string) get_the_excerpt( $ktube_post ) ),
		'datePublished'    => get_the_date( 'c', $ktube_post ),
		'dateModified'     => get_the_modified_date( 'c', $ktube_post ),
		'author'           => $ktube_author_obj,
		'publisher'        => $ktube_publisher_obj,
		'mainEntityOfPage' => array(
			'@type' => 'WebPage',
			'@id'   => get_permalink( $ktube_post ),
		),
	);

	// Phase 9e — articleBody: stripped body text. Omit the key entirely
	// when post_content is empty (draft stages, misimported posts) so
	// schema.org doesn't see `articleBody: ""` and flag it as a quality
	// signal. wp_strip_all_tags already trims in the stub, but real WP
	// also shortcodes + embeds; strip is sufficient for the JSON-LD layer.
	if ( isset( $ktube_post->post_content ) ) {
		$ktube_article_body = wp_strip_all_tags( (string) $ktube_post->post_content );
		if ( '' !== $ktube_article_body ) {
			$ktube_data['articleBody'] = $ktube_article_body;
		}
	}

	// Phase 9e — keywords: from post_tag terms. Real WP's get_the_terms()
	// returns array|WP_Error|WP_Term[] for the post+taxonomy pair. We
	// coerce the result to a string[] via `name` field. Per schema.org:
	// "keywords" is recommended but optional; we omit the key when no
	// tags exist (operators still see a parseable Article block).
	$ktube_terms = get_the_terms( $ktube_post, 'post_tag' );
	if ( is_array( $ktube_terms ) && ! empty( $ktube_terms ) ) {
		$ktube_keyword_names = array();
		foreach ( $ktube_terms as $ktube_term ) {
			if ( is_object( $ktube_term ) && isset( $ktube_term->name ) && '' !== (string) $ktube_term->name ) {
				$ktube_keyword_names[] = (string) $ktube_term->name;
			}
		}
		if ( ! empty( $ktube_keyword_names ) ) {
			$ktube_data['keywords'] = array_values( array_unique( $ktube_keyword_names ) );
		}
	}

	if ( '' !== $ktube_image_url ) {
		$ktube_data['image'] = array( $ktube_image_url );
	}

	/**
	 * Filter the Blog Article schema data before JSON encoding.
	 *
	 * @param array<string,mixed> $ktube_data
	 * @param WP_Post             $ktube_post
	 */
	return (array) apply_filters( 'ktube_blog_article_schema', $ktube_data, $ktube_post );
}

/**
 * Render ImageGallery JSON-LD for single-photo with embedded ImageObject list.
 */
function ktube_render_photo_image_object_schema(): void {
	if ( ! is_singular( 'photo' ) ) {
		return;
	}
	$ktube_post = get_post();
	if ( ! $ktube_post ) {
		return;
	}
	$ktube_set_raw = (string) get_post_meta( $ktube_post->ID, '_ktube_photo_set', true );
	$ktube_set_arr = is_string( $ktube_set_raw ) ? maybe_unserialize( $ktube_set_raw ) : array();
	if ( ! is_array( $ktube_set_arr ) ) {
		return;
	}
	$ktube_images = array();
	foreach ( $ktube_set_arr as $ktube_id_raw ) {
		$ktube_id = (int) $ktube_id_raw;
		if ( ! $ktube_id ) {
			continue;
		}
		$ktube_url = wp_get_attachment_image_url( $ktube_id, 'full' );
		if ( ! $ktube_url ) {
			continue;
		}
		$ktube_images[] = array(
			'@type'      => 'ImageObject',
			'contentUrl' => $ktube_url,
			'thumbnail'  => wp_get_attachment_image_url( $ktube_id, 'ktube-card' ) ?: $ktube_url,
			'name'       => (string) get_the_title( $ktube_id ),
		);
	}
	if ( ! $ktube_images ) {
		return;
	}
	$ktube_data = array(
		'@context'    => 'https://schema.org',
		'@type'       => 'ImageGallery',
		'name'        => get_the_title( $ktube_post ),
		'description' => wp_strip_all_tags( (string) get_the_excerpt( $ktube_post ) ),
		'image'       => $ktube_images,
	);
	wp_print_inline_script_tag(
		(string) wp_json_encode( $ktube_data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ),
		array( 'type' => 'application/ld+json' )
	);
}

/**
 * Render VideoObject JSON-LD for single-video.
 *
 * Brief §6 + Phase 9d. Emits a schema.org/VideoObject block with:
 *   - contentUrl     (from _ktube_video_url OR _ktube_embed_url fallback)
 *   - thumbnailUrl[] (featured-image full OR _ktube_thumb_url fallback OR empty array)
 *   - uploadDate     (post_date in ISO 8601 / RFC 3339)
 *   - duration       (PT-format ISO 8601 from _ktube_duration seconds)
 *   - encodingFormat (MIME inferred from contentUrl extension)
 *   - interactionStatistic (InteractionCounter WatchAction only if _ktube_views > 0)
 *
 * Emits nothing when the post has neither _ktube_video_url nor _ktube_embed_url,
 * to avoid emitting a VideoObject with no playable URL.
 */
function ktube_render_video_object_schema(): void {
	if ( ! is_singular( 'video' ) ) {
		return;
	}
	$ktube_post = get_post();
	if ( ! $ktube_post ) {
		return;
	}
	$ktube_data = ktube_build_video_object_schema( $ktube_post );
	if ( empty( $ktube_data ) ) {
		return;
	}
	wp_print_inline_script_tag(
		(string) wp_json_encode( $ktube_data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ),
		array( 'type' => 'application/ld+json' )
	);
}

/**
 * Build the VideoObject schema array (extracted for testability + filterability).
 *
 * Returns an empty array when neither contentUrl candidate is present so
 * callers can bail early instead of emitting a VideoObject with no playable URL.
 *
 * @param WP_Post $ktube_post
 * @return array<string,mixed>
 */
function ktube_build_video_object_schema( $ktube_post ): array {
	if ( ! ( $ktube_post instanceof WP_Post ) ) {
		return array();
	}
	$ktube_video_url = (string) get_post_meta( $ktube_post->ID, '_ktube_video_url', true );
	if ( '' === $ktube_video_url ) {
		$ktube_video_url = (string) get_post_meta( $ktube_post->ID, '_ktube_embed_url', true );
	}
	if ( '' === $ktube_video_url ) {
		return array();
	}

	// Thumbnail: featured image takes precedence; _ktube_thumb_url fallback.
	$ktube_thumb_url = '';
	$ktube_thumb_id  = (int) get_post_thumbnail_id( $ktube_post->ID );
	if ( $ktube_thumb_id ) {
		$ktube_thumb_url = (string) wp_get_attachment_image_url( $ktube_thumb_id, 'full' );
	}
	if ( '' === $ktube_thumb_url ) {
		$ktube_thumb_url = (string) get_post_meta( $ktube_post->ID, '_ktube_thumb_url', true );
	}

	$ktube_data = array(
		'@context'      => 'https://schema.org',
		'@type'         => 'VideoObject',
		'name'          => get_the_title( $ktube_post ),
		'description'   => wp_strip_all_tags( (string) get_the_excerpt( $ktube_post ) ),
		'contentUrl'    => $ktube_video_url,
		'thumbnailUrl'  => '' !== $ktube_thumb_url ? array( $ktube_thumb_url ) : array(),
		'uploadDate'    => get_the_date( 'c', $ktube_post ),
		'duration'      => ktube_iso8601_duration( (int) get_post_meta( $ktube_post->ID, '_ktube_duration', true ) ),
		'encodingFormat'=> ktube_infer_encoding_format( $ktube_video_url ),
	);
	// mainEntityOfPage: defensive guard so a freshly-staged draft id (no
	// permalink) doesn't emit a `?p=0` WebPage @id in the JSON-LD.
	$ktube_permalink = (string) get_permalink( $ktube_post );
	if ( '' !== $ktube_permalink ) {
		$ktube_data['mainEntityOfPage'] = array(
			'@type' => 'WebPage',
			'@id'   => $ktube_permalink,
		);
	}

	// Optional: view-count InteractionCounter. Skip when no recorded views
	// (would otherwise serialize "userInteractionCount": 0 which crawlers
	// may treat as a quality signal).
	$ktube_views = (int) get_post_meta( $ktube_post->ID, '_ktube_views', true );
	if ( $ktube_views > 0 ) {
		$ktube_data['interactionStatistic'] = array(
			'@type'               => 'InteractionCounter',
			'interactionType'     => array(
				'@type' => 'WatchAction',
			),
			'userInteractionCount'=> $ktube_views,
		);
	}

	/**
	 * Filter the VideoObject schema data before JSON encoding.
	 *
	 * @param array<string,mixed> $ktube_data
	 * @param WP_Post             $ktube_post
	 */
	return (array) apply_filters( 'ktube_video_object_schema', $ktube_data, $ktube_post );
}

/**
 * Convert a duration in seconds to ISO 8601 PT-format ("PT1H30M5S").
 *
 * Returns 'PT0S' (a valid zero-duration) when seconds <= 0 so the
 * schema always carries a parseable value rather than omitting the
 * field — Google Search Console flags missing duration as a quality
 * issue, but a zero duration is a known-truthful marker for live
 * streams / misconfigured imports.
 *
 * @param int $ktube_seconds Duration in seconds.
 * @return string
 */
function ktube_iso8601_duration( int $ktube_seconds ): string {
	if ( $ktube_seconds <= 0 ) {
		return 'PT0S';
	}
	$ktube_h = (int) floor( $ktube_seconds / 3600 );
	$ktube_m = (int) floor( ( $ktube_seconds % 3600 ) / 60 );
	$ktube_s = $ktube_seconds % 60;
	$ktube_iso = 'PT';
	if ( $ktube_h > 0 ) {
		$ktube_iso .= $ktube_h . 'H';
	}
	if ( $ktube_m > 0 ) {
		$ktube_iso .= $ktube_m . 'M';
	}
	if ( $ktube_s > 0 || ( 0 === $ktube_h && 0 === $ktube_m ) ) {
		$ktube_iso .= $ktube_s . 'S';
	}
	return $ktube_iso;
}

/**
 * Infer the encoding MIME type from a URL path's extension.
 *
 * Defaults to 'video/mp4' when extension is unknown so the schema always
 * carries a value Google can parse; HLS streams get the application/
 * vnd.apple.mpegurl MIME so LibreJS-aware crawlers recognize .m3u8.
 *
 * @param string $ktube_url URL whose extension is the encoding signal.
 * @return string
 */
function ktube_infer_encoding_format( string $ktube_url ): string {
	$ktube_path = (string) parse_url( $ktube_url, PHP_URL_PATH );
	$ktube_ext  = strtolower( (string) pathinfo( $ktube_path, PATHINFO_EXTENSION ) );
	$ktube_map  = array(
		'mp4'  => 'video/mp4',
		'm4v'  => 'video/x-m4v',
		'webm' => 'video/webm',
		'ogg'  => 'video/ogg',
		'ogv'  => 'video/ogg',
		'mov'  => 'video/quicktime',
		'm3u8' => 'application/vnd.apple.mpegurl',
	);
	return isset( $ktube_map[ $ktube_ext ] ) ? $ktube_map[ $ktube_ext ] : 'video/mp4';
}

/**
 * Open Graph meta tags — video / blog / photo singles.
 */
function ktube_render_open_graph_meta(): void {
	if ( ! is_singular( array( 'video', 'blog', 'photo' ) ) ) {
		return;
	}
	$ktube_post = get_post();
	if ( ! $ktube_post ) {
		return;
	}
	$ktube_image_url = wp_get_attachment_image_url( (int) get_post_thumbnail_id( $ktube_post->ID ), 'full' );
	$ktube_type      = ( 'video' === $ktube_post->post_type ) ? 'video.other' : 'article';
	?>
	<meta property="og:type" content="<?php echo esc_attr( $ktube_type ); ?>">
	<meta property="og:title" content="<?php echo esc_attr( get_the_title( $ktube_post ) ); ?>">
	<meta property="og:url" content="<?php echo esc_url( get_permalink( $ktube_post ) ); ?>">
	<meta property="og:site_name" content="<?php echo esc_attr( get_bloginfo( 'name' ) ); ?>">
	<meta property="og:description" content="<?php echo esc_attr( wp_strip_all_tags( (string) get_the_excerpt( $ktube_post ) ) ); ?>">
	<?php if ( $ktube_image_url ) : ?>
	<meta property="og:image" content="<?php echo esc_url( $ktube_image_url ); ?>">
	<?php endif; ?>
	<?php
}

/**
 * Twitter Card meta tags — video / blog / photo singles.
 */
function ktube_render_twitter_card_meta(): void {
	if ( ! is_singular( array( 'video', 'blog', 'photo' ) ) ) {
		return;
	}
	$ktube_post = get_post();
	if ( ! $ktube_post ) {
		return;
	}
	$ktube_card = has_post_thumbnail() ? 'summary_large_image' : 'summary';
	?>
	<meta name="twitter:card" content="<?php echo esc_attr( $ktube_card ); ?>">
	<meta name="twitter:title" content="<?php echo esc_attr( get_the_title( $ktube_post ) ); ?>">
	<meta name="twitter:url" content="<?php echo esc_url( get_permalink( $ktube_post ) ); ?>">
	<meta name="twitter:description" content="<?php echo esc_attr( wp_strip_all_tags( (string) get_the_excerpt( $ktube_post ) ) ); ?>">
	<?php
}
