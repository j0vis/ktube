<?php
/**
 * Phase 9e — Blog Article JSON-LD schema tests.
 *
 * Asserts the cross-file invariants on includes/seo/schema.php's
 * Article emitter after the articleBody + keywords uplift (carryover
 * toward §5-B #10 — richer rich-snippets per brief §6).
 *
 *   - ktube_build_blog_article_schema() returns empty array when the
 *     post isn't a WP_Post (early-bail mirrors Phase 9d VideoObject).
 *   - Existing fields (headline, description, datePublished, dateModified,
 *     author, publisher, mainEntityOfPage, image) are unchanged.
 *   - articleBody is included when post_content is non-empty (verbatim,
 *     HTML stripped via wp_strip_all_tags) and OMITTED when empty so
 *     crawlers don't see misleading blanks.
 *   - keywords is included when get_the_terms('post_tag') returns ≥1
 *     terms and OMITTED when no post_tag terms. Term names are mapped
 *     to a string[] and dedup'd.
 *   - ktube_blog_article_schema filter exposes the build for partner-
 *     specific mutations (NOT direct DB writes).
 *   - Render emitter gates on is_singular('blog') (boot-stub returns
 *     false, so we exercise the build helper directly).
 *
 * @package ktube
 */

class BlogArticleSchemaTest extends \PHPUnit\Framework\TestCase {
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
		if ( function_exists( 'reset_post_terms_test' ) ) {
			reset_post_terms_test();
		} else {
			$GLOBALS['__ktube_post_terms'] = array();
		}
		if ( function_exists( 'reset_filters_test' ) ) {
			reset_filters_test();
		}
	}

	// ---- Build-helper early-bail contracts ------------------------------

	public function test_build_returns_empty_array_when_post_not_WP_Post(): void {
		$this->assertSame( array(), ktube_build_blog_article_schema( 0 ) );
		$this->assertSame( array(), ktube_build_blog_article_schema( null ) );
		$this->assertSame( array(), ktube_build_blog_article_schema( array() ) );
		$this->assertSame( array(), ktube_build_blog_article_schema( 'not-a-post' ) );
	}

	public function test_build_emits_article_with_all_required_fields_when_minimal_post(): void {
		set_post_test(
			100,
			array(
				'post_type'    => 'blog',
				'post_status'  => 'publish',
				'post_name'    => 'sample-blog',
				'post_title'   => 'Sample Blog Post',
				'post_excerpt' => 'Sample <em>excerpt</em> with HTML.',
				'post_author'  => 1,
				'post_date'    => '2026-06-21 12:00:00',
			)
		);
		$ktube_data = ktube_build_blog_article_schema( get_post( 100 ) );

		$this->assertSame( 'https://schema.org', $ktube_data['@context'] );
		$this->assertSame( 'Article',           $ktube_data['@type'] );
		$this->assertSame( 'Sample Blog Post',  $ktube_data['headline'] );
		$this->assertSame( 'Sample excerpt with HTML.', $ktube_data['description'], 'tags stripped from description' );
		$this->assertNotFalse( strtotime( $ktube_data['datePublished'] ) );
		$this->assertNotFalse( strtotime( $ktube_data['dateModified'] ) );
		$this->assertSame( 'Person',        $ktube_data['author']['@type'] );
		$this->assertSame( 'Organization',  $ktube_data['publisher']['@type'] );
		$this->assertSame( 'WebPage',       $ktube_data['mainEntityOfPage']['@type'] );
		$this->assertStringContainsString( '?p=100', (string) $ktube_data['mainEntityOfPage']['@id'] );

		// Phase 9e additions: absent by default in minimal post (no body, no tags).
		$this->assertArrayNotHasKey( 'articleBody', $ktube_data );
		$this->assertArrayNotHasKey( 'keywords',    $ktube_data );
		$this->assertArrayNotHasKey( 'image',       $ktube_data );
	}

	// ---- articleBody: emit / strip / omit ------------------------------

	public function test_article_body_emitted_when_post_content_non_empty(): void {
		set_post_test(
			100,
			array(
				'post_type'      => 'blog',
				'post_status'    => 'publish',
				'post_name'      => 'sample-blog',
				'post_title'     => 'Sample',
				'post_content'   => 'This is a long-form blog post body with multiple sentences.',
				'post_excerpt'   => 'short',
				'post_date'      => '2026-06-21 12:00:00',
			)
		);
		$ktube_data = ktube_build_blog_article_schema( get_post( 100 ) );
		$this->assertArrayHasKey( 'articleBody', $ktube_data );
		$this->assertSame( 'This is a long-form blog post body with multiple sentences.', $ktube_data['articleBody'] );
	}

	public function test_article_body_strips_html_tags_from_post_content(): void {
		set_post_test(
			100,
			array(
				'post_type'    => 'blog',
				'post_status'  => 'publish',
				'post_name'    => 'sample-blog',
				'post_title'   => 'Sample',
				// Real WP content is HTML-encoded Word; strip should remove tags
				// but keep inner text. wp_strip_all_tags also trims.
				'post_content' => '<p>Hello <strong>world</strong>!</p><script>alert(1)</script>',
				'post_excerpt' => 'short',
				'post_date'    => '2026-06-21 12:00:00',
			)
		);
		$ktube_data = ktube_build_blog_article_schema( get_post( 100 ) );
		$this->assertArrayHasKey( 'articleBody', $ktube_data );
		$this->assertStringNotContainsString( '<', (string) $ktube_data['articleBody'], 'no HTML tags remain' );
		$this->assertStringContainsString( 'Hello',       (string) $ktube_data['articleBody'] );
		$this->assertStringContainsString( 'world',       (string) $ktube_data['articleBody'] );
		$this->assertStringContainsString( '!',           (string) $ktube_data['articleBody'] );
		$this->assertStringNotContainsString( 'alert',      (string) $ktube_data['articleBody'], 'script body content stripped' );
	}

	public function test_article_body_omitted_when_post_content_empty(): void {
		set_post_test(
			100,
			array(
				'post_type'    => 'blog',
				'post_status'  => 'publish',
				'post_name'    => 'sample-blog',
				'post_title'   => 'Sample',
				'post_content' => '',
				'post_excerpt' => 'short',
				'post_date'    => '2026-06-21 12:00:00',
			)
		);
		$ktube_data = ktube_build_blog_article_schema( get_post( 100 ) );
		$this->assertArrayNotHasKey( 'articleBody', $ktube_data, 'do not emit articleBody:"" for empty post_content' );
	}

	// ---- keywords: emit / omit -----------------------------------------

	public function test_keywords_emitted_from_post_tag_terms(): void {
		set_post_test(
			100,
			array(
				'post_type'    => 'blog',
				'post_status'  => 'publish',
				'post_name'    => 'sample-blog',
				'post_title'   => 'Sample',
				'post_content' => 'Body text.',
				'post_excerpt' => 'short',
				'post_date'    => '2026-06-21 12:00:00',
			)
		);
		set_post_terms_test( 100, 'post_tag', array( 'news', 'tech', 'wp' ) );
		$ktube_data = ktube_build_blog_article_schema( get_post( 100 ) );
		$this->assertArrayHasKey( 'keywords', $ktube_data );
		$this->assertSame( array( 'news', 'tech', 'wp' ), $ktube_data['keywords'] );
	}

	public function test_keywords_omitted_when_no_post_tag_terms(): void {
		set_post_test(
			100,
			array(
				'post_type'    => 'blog',
				'post_status'  => 'publish',
				'post_name'    => 'sample-blog',
				'post_title'   => 'Sample',
				'post_content' => 'Body text.',
				'post_excerpt' => 'short',
				'post_date'    => '2026-06-21 12:00:00',
			)
		);
		$ktube_data = ktube_build_blog_article_schema( get_post( 100 ) );
		$this->assertArrayNotHasKey( 'keywords', $ktube_data );
	}

	public function test_keywords_sourced_only_from_post_tag_not_other_taxonomies(): void {
		// Article schema spec says 'keywords' = tags; we don't pull from
		// 'category' (which is hierarchical) or 'actor'. Verify by setting
		// non-post_tag terms and asserting keywords is missing.
		set_post_test(
			100,
			array(
				'post_type'    => 'blog',
				'post_status'  => 'publish',
				'post_name'    => 'sample-blog',
				'post_title'   => 'Sample',
				'post_content' => 'Body text.',
				'post_excerpt' => 'short',
				'post_date'    => '2026-06-21 12:00:00',
			)
		);
		// blog CPT in includes/post-types.php supports category + post_tag
		// but NOT actor / channel — so we test category is ignored.
		set_post_terms_test( 100, 'category', array( 'updates' ) );
		$ktube_data = ktube_build_blog_article_schema( get_post( 100 ) );
		$this->assertArrayNotHasKey( 'keywords', $ktube_data, 'keywords must NOT pull from category taxonomy' );
	}

	public function test_keywords_dedup_repeats_when_same_term_set_twice(): void {
		set_post_test(
			100,
			array(
				'post_type'    => 'blog',
				'post_status'  => 'publish',
				'post_name'    => 'sample-blog',
				'post_title'   => 'Sample',
				'post_content' => 'Body.',
				'post_excerpt' => 'short',
				'post_date'    => '2026-06-21 12:00:00',
			)
		);
		// Helper writes the same set twice (simulating a buggy importer);
		// our finalize step should dedupe via array_unique.
		set_post_terms_test( 100, 'post_tag', array( 'news', 'news' ) );
		$ktube_data = ktube_build_blog_article_schema( get_post( 100 ) );
		$this->assertSame( array( 'news' ), $ktube_data['keywords'] );
	}

	// ---- Filter hook testability ---------------------------------------

	public function test_ktube_blog_article_schema_filter_extends_data(): void {
		set_post_test(
			100,
			array(
				'post_type'    => 'blog',
				'post_status'  => 'publish',
				'post_name'    => 'sample-blog',
				'post_title'   => 'Sample',
				'post_content' => 'Body.',
				'post_excerpt' => 'short',
				'post_date'    => '2026-06-21 12:00:00',
			)
		);
		add_filter(
			'ktube_blog_article_schema',
			static function ( $ktube_data ): array {
				$ktube_data['isAccessibleForFree'] = true;
				return $ktube_data;
			}
		);
		$ktube_data = ktube_build_blog_article_schema( get_post( 100 ) );
		$this->assertArrayHasKey( 'isAccessibleForFree', $ktube_data );
		$this->assertTrue( $ktube_data['isAccessibleForFree'] );
	}

	public function test_ktube_blog_article_schema_filter_can_remove_article_body(): void {
		set_post_test(
			100,
			array(
				'post_type'    => 'blog',
				'post_status'  => 'publish',
				'post_name'    => 'sample-blog',
				'post_title'   => 'Sample',
				'post_content' => 'Body that should be stripped by the filter.',
				'post_excerpt' => 'short',
				'post_date'    => '2026-06-21 12:00:00',
			)
		);
		add_filter(
			'ktube_blog_article_schema',
			static function ( $ktube_data ): array {
				unset( $ktube_data['articleBody'] );
				return $ktube_data;
			}
		);
		$ktube_data = ktube_build_blog_article_schema( get_post( 100 ) );
		$this->assertArrayNotHasKey( 'articleBody', $ktube_data );
	}

	// ---- Render-emitter gate -------------------------------------------

	public function test_render_emits_nothing_on_non_blog_singular(): void {
		// ktube_render_blog_article_schema() bails early on ! is_singular('blog').
		// Boot stub is_singular() returns false unconditionally. We assert the
		// function does not fatal and emits no Article JSON-LD when called
		// outside a blog singular context.
		$ktube_threw = false;
		try {
			ob_start();
			ktube_render_blog_article_schema();
			$ktube_output = ob_get_clean();
		} catch ( \Throwable $e ) {
			$ktube_threw = true;
			$ktube_output = '';
		}
		$this->assertFalse( $ktube_threw );
		$this->assertNotContains( 'application/ld+json', (string) $ktube_output );
		$this->assertNotContains( '"@type":"Article"',     (string) $ktube_output );
	}

	public function test_render_emits_ld_json_when_blog_post_has_article_body(): void {
		set_post_test(
			100,
			array(
				'post_type'    => 'blog',
				'post_status'  => 'publish',
				'post_name'    => 'sample-blog',
				'post_title'   => 'Sample',
				'post_content' => 'The full body of the blog post.',
				'post_excerpt' => 'short',
				'post_date'    => '2026-06-21 12:00:00',
			)
		);
		set_post_terms_test( 100, 'post_tag', array( 'phase-9e', 'seo' ) );
		$ktube_data = ktube_build_blog_article_schema( get_post( 100 ) );
		$ktube_json  = (string) wp_json_encode( $ktube_data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE );
		$this->assertStringContainsString( '"@type":"Article"',                      $ktube_json );
		$this->assertStringContainsString( '"articleBody":"The full body',         $ktube_json );
		$this->assertStringContainsString( '"keywords":["phase-9e","seo"]',        $ktube_json );
	}

	// ---- Existing-field regression guards ------------------------------

	public function test_9e_extension_preserves_headline_description_dating(): void {
		set_post_test(
			100,
			array(
				'post_type'      => 'blog',
				'post_status'    => 'publish',
				'post_name'      => 'sample-blog',
				'post_title'     => 'Phase 9e Sample',
				'post_content'   => 'rich body',
				'post_excerpt'   => '<p>rich <em>excerpt</em></p>',
				'post_date'      => '2026-06-21 12:00:00',
				'post_modified'  => '2026-06-21 13:30:00',
			)
		);
		$ktube_data = ktube_build_blog_article_schema( get_post( 100 ) );
		$this->assertSame( 'Phase 9e Sample',           $ktube_data['headline'] );
		$this->assertSame( 'rich excerpt',              $ktube_data['description'], 'tags stripped from existing description' );
		$this->assertStringStartsWith( '2026-06-21',    $ktube_data['datePublished'] );
		$this->assertStringStartsWith( '2026-06-21',    $ktube_data['dateModified'] );
	}

	public function test_9e_extension_preserves_image_field_when_thumb_set(): void {
		set_post_test(
			100,
			array(
				'post_type'    => 'blog',
				'post_status'  => 'publish',
				'post_name'    => 'sample-blog',
				'post_title'   => 'Sample',
				'post_content' => 'Body.',
				'post_excerpt' => 'short',
				'post_date'    => '2026-06-21 12:00:00',
			)
		);
		set_post_thumbnail_test( 100, 555 );
		$ktube_data = ktube_build_blog_article_schema( get_post( 100 ) );
		$this->assertArrayHasKey( 'image', $ktube_data );
		$this->assertSame( array( 'https://example.test/wp-content/uploads/555-full.jpg' ), $ktube_data['image'] );
	}
}
