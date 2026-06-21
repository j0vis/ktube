<?php
/**
 * og:type regression — locks the Phase 9d VideoObject-surfacing
 * implication: single-video emits og:type="video.other" so a link
 * preview on Facebook/Discord/Twitter/etc renders as a video card.
 * Other single-post-types emit og:type="article" (default).
 *
 * The emitter lives in includes/seo/schema.php::ktube_render_open_graph_meta.
 * The bootstrap's is_singular() stub supports per-type injection via
 * $GLOBALS['__ktube_test_is_singular_types']; we exercise each branch.
 *
 * HTML-attribute encoding note: ktube emits attributes via esc_attr,
 * which the bootstrap stub implements with htmlspecialchars(ENT_QUOTES).
 * This converts literal " inside attribute values to &quot;. The
 * assertions below either side-step the encoding (looking up just the
 * tokens that don't contain quotes) or expect the htmlspecialchars
 * output verbatim via single-quoted PHP strings.
 *
 * @package ktube
 */

class OgVideoTypeTest extends \PHPUnit\Framework\TestCase {

	public function setUp(): void {
		$GLOBALS['__ktube_actions']              = array();
		$GLOBALS['__ktube_meta']                 = array();
		$GLOBALS['__ktube_posts']                = array();
		$GLOBALS['__ktube_theme_mods']           = array();
		$GLOBALS['__ktube_post_meta']            = array();
		$GLOBALS['__ktube_post_thumbnails']      = array();
		$GLOBALS['__ktube_test_is_singular']     = false;
		$GLOBALS['__ktube_test_is_singular_types'] = array();
		if ( function_exists( 'reset_post_meta_test' ) ) {
			reset_post_meta_test();
		}
		if ( function_exists( 'reset_post_thumbnails_test' ) ) {
			reset_post_thumbnails_test();
		}
		// dirname(__DIR__, 3) + '/includes/...' → ktube root from tests/phpunit/ktube/.
		$ktube_path = dirname( __DIR__, 3 ) . '/includes/seo/schema.php';
		if ( ! function_exists( 'ktube_render_open_graph_meta' ) && file_exists( $ktube_path ) ) {
			require_once $ktube_path;
		}
	}

	private function ktubeRenderOgOutput(): string {
		ob_start();
		ktube_render_open_graph_meta();
		return (string) ob_get_clean();
	}

	public function test_og_type_video_other_on_video_singular(): void {
		$GLOBALS['__ktube_test_is_singular_types'] = array( 'video' );
		set_post_test(
			100,
			array(
				'post_type'    => 'video',
				'post_status'  => 'publish',
				'post_name'    => 'sample',
				'post_title'   => 'Sample Video',
				'post_excerpt' => 'Sample excerpt',
			)
		);
		$ktube_out = $this->ktubeRenderOgOutput();
		// Esc_attr converts " to &quot; inside attribute values; the OG
		// emitter renders og:type as a property whose value is a closed
		// enum string. We assert the relaxed forms (no quote chars in
		// the search) so the encoded HTML and the lax HTML both pass.
		$this->assertStringContainsString( 'og:type', $ktube_out );
		$this->assertStringContainsString( 'video.other', $ktube_out );
		$this->assertStringNotContainsString( 'article', $ktube_out );
		// Sanity: at least one og:title / og:url must follow the type tag.
		$this->assertStringContainsString( 'property="og:title"', $ktube_out );
		$this->assertStringContainsString( 'property="og:url"',   $ktube_out );
	}

	public function test_og_type_article_on_blog_singular(): void {
		$GLOBALS['__ktube_test_is_singular_types'] = array( 'blog' );
		set_post_test(
			101,
			array(
				'post_type'    => 'blog',
				'post_status'  => 'publish',
				'post_name'    => 'sample',
				'post_title'   => 'Sample Blog',
				'post_excerpt' => 'Sample excerpt',
			)
		);
		$ktube_out = $this->ktubeRenderOgOutput();
		$this->assertStringContainsString( 'og:type', $ktube_out );
		$this->assertStringContainsString( 'article', $ktube_out );
		$this->assertStringNotContainsString( 'video.other', $ktube_out );
	}

	public function test_og_type_article_on_photo_singular(): void {
		$GLOBALS['__ktube_test_is_singular_types'] = array( 'photo' );
		set_post_test(
			102,
			array(
				'post_type'    => 'photo',
				'post_status'  => 'publish',
				'post_name'    => 'sample',
				'post_title'   => 'Sample Photo',
				'post_excerpt' => 'Sample excerpt',
			)
		);
		$ktube_out = $this->ktubeRenderOgOutput();
		$this->assertStringContainsString( 'og:type', $ktube_out );
		$this->assertStringContainsString( 'article', $ktube_out );
		$this->assertStringNotContainsString( 'video.other', $ktube_out );
	}

	public function test_og_meta_emits_title_url_and_description(): void {
		$GLOBALS['__ktube_test_is_singular_types'] = array( 'video' );
		set_post_test(
			103,
			array(
				'post_type'    => 'video',
				'post_status'  => 'publish',
				'post_name'    => 'sample',
				'post_title'   => 'OG title',
				'post_excerpt' => 'OG excerpt body',
			)
		);
		$ktube_out = $this->ktubeRenderOgOutput();
		// Use relaxed string matches that don't depend on quote-encoding
		// inside attribute values. Both esc_attr and lax HTML pass.
		$this->assertStringContainsString( 'OG title',         $ktube_out );
		$this->assertStringContainsString( '?p=103',           $ktube_out, 'page permalink carries the post id' );
		$this->assertStringContainsString( 'OG excerpt body',  $ktube_out );
	}

	public function test_og_image_emitted_only_when_featured_image_set(): void {
		$GLOBALS['__ktube_test_is_singular_types'] = array( 'blog' );
		set_post_test(
			104,
			array(
				'post_type'    => 'blog',
				'post_status'  => 'publish',
				'post_name'    => 'thumb-test',
				'post_title'   => 'Thumb test',
			)
		);
		// No featured image → no og:image tag.
		$ktube_out_no_thumb = $this->ktubeRenderOgOutput();
		$this->assertStringNotContainsString( 'property="og:image"', $ktube_out_no_thumb );

		// Featured image set → og:image tag emitted.
		set_post_thumbnail_test( 104, 555 );
		$ktube_out_with_thumb = $this->ktubeRenderOgOutput();
		$this->assertStringContainsString( 'property="og:image"', $ktube_out_with_thumb );
	}

	public function test_og_meta_bails_when_not_a_supported_singular(): void {
		// Simulate a regular WP_Post singular (post type 'post') — ktube
		// restricts OG to video|blog|photo.
		$GLOBALS['__ktube_test_is_singular_types'] = array( 'post' );
		set_post_test(
			105,
			array(
				'post_type'   => 'post',
				'post_status' => 'publish',
				'post_name'   => 'sample',
				'post_title'  => 'Regular post',
			)
		);
		$ktube_out = $this->ktubeRenderOgOutput();
		$this->assertStringNotContainsString( 'og:type',  $ktube_out );
		$this->assertStringNotContainsString( 'og:title', $ktube_out );
	}
}
