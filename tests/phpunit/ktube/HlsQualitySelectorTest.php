<?php
/**
 * Phase 14 perf (2026-06-21) — HLS quality-selector unit tests.
 *
 * Locks:
 *   1. ktube_sanitize_player_quality closed-set: 'auto' | '1080p' |
 *      '720p' | '480p' | '360p' preserved; anything else falls back to
 *      'auto' (a typo can't poison the radio value).
 *   2. ktube_quality_choices() exposes the closed enum + 'auto' head.
 *   3. ktube_sanitize_quality_levels(): valid JSON parses to the
 *      [{label, bandwidth}] array; invalid (not-array / malformed /
 *      missing keys) → default ladder; non-positive bandwidth rows
 *      dropped; unknown labels dropped.
 *
 * @package ktube
 */

class HlsQualitySelectorTest extends \PHPUnit\Framework\TestCase {

	public function setUp(): void {
		$GLOBALS['__ktube_theme_mods']          = array();
		$GLOBALS['__ktube_actions']             = array();
		$GLOBALS['__ktube_filters']             = array();
		$GLOBALS['__ktube_inline_scripts']      = array();
		$GLOBALS['__ktube_post_meta']           = array();
		$GLOBALS['__ktube_post_thumbnails']     = array();
		$GLOBALS['__ktube_test_is_singular']    = false;
		$GLOBALS['__ktube_test_is_singular_types'] = array();
		// dirname(__DIR__, 3) + '/includes/...' → ktube root from tests/phpunit/ktube/.
		$ktube_path = dirname( __DIR__, 3 ) . '/includes/player-depth.php';
		if ( ! function_exists( 'ktube_register_player_depth' ) && file_exists( $ktube_path ) ) {
			require_once $ktube_path;
		}
	}

	public function test_sanitize_player_quality_accepts_enclosed_set(): void {
		$this->assertSame( 'auto', ktube_sanitize_player_quality( 'auto' ) );
		$this->assertSame( '1080p', ktube_sanitize_player_quality( '1080p' ) );
		$this->assertSame( '720p',  ktube_sanitize_player_quality( '720p' ) );
		$this->assertSame( '480p',  ktube_sanitize_player_quality( '480p' ) );
		$this->assertSame( '360p',  ktube_sanitize_player_quality( '360p' ) );
	}

	public function test_sanitize_player_quality_falls_back_on_unknown(): void {
		$this->assertSame( 'auto', ktube_sanitize_player_quality( '8k' ) );
		$this->assertSame( 'auto', ktube_sanitize_player_quality( '' ) );
		$this->assertSame( 'auto', ktube_sanitize_player_quality( null ) );
		$this->assertSame( 'auto', ktube_sanitize_player_quality( array() ) );
		// Mixed-case must NOT slip past sanitize_key.
		$this->assertSame( 'auto', ktube_sanitize_player_quality( 'Auto' ) );
	}

	public function test_quality_choices_exposes_closed_enum(): void {
		$ktube_choices = ktube_quality_choices();
		$this->assertArrayHasKey( 'auto',  $ktube_choices );
		$this->assertArrayHasKey( '1080p', $ktube_choices );
		$this->assertArrayHasKey( '720p',  $ktube_choices );
		$this->assertArrayHasKey( '480p',  $ktube_choices );
		$this->assertArrayHasKey( '360p',  $ktube_choices );
		$this->assertCount( 5, $ktube_choices );
	}

	public function test_bandwidth_map_values_match_labels(): void {
		$ktube_map = ktube_quality_bandwidth_map();
		$this->assertSame( 5000000, $ktube_map['1080p'] );
		$this->assertSame( 2800000, $ktube_map['720p'] );
		$this->assertSame( 1400000, $ktube_map['480p'] );
		$this->assertSame( 800000,  $ktube_map['360p'] );
	}

	public function test_sanitize_quality_levels_parses_valid_json(): void {
		$ktube_json  = '[{"label":"1080p","bandwidth":5000000},{"label":"720p","bandwidth":2800000}]';
		$ktube_levels = ktube_sanitize_quality_levels( $ktube_json );
		$this->assertCount( 2, $ktube_levels );
		$this->assertSame( '1080p',       $ktube_levels[0]['label'] );
		$this->assertSame( 5000000,       $ktube_levels[0]['bandwidth'] );
		$this->assertSame( '720p',        $ktube_levels[1]['label'] );
	}

	public function test_sanitize_quality_levels_drops_unknown_labels(): void {
		$ktube_json  = '[{"label":"8k","bandwidth":5000000},{"label":"720p","bandwidth":2800000}]';
		$ktube_levels = ktube_sanitize_quality_levels( $ktube_json );
		$this->assertCount( 1, $ktube_levels, 'unknown label 8k must be dropped' );
		$this->assertSame( '720p', $ktube_levels[0]['label'] );
	}

	public function test_sanitize_quality_levels_drops_non_positive_bandwidth(): void {
		$ktube_json  = '[{"label":"1080p","bandwidth":0},{"label":"720p","bandwidth":-1},{"label":"480p","bandwidth":1400000}]';
		$ktube_levels = ktube_sanitize_quality_levels( $ktube_json );
		$this->assertCount( 1, $ktube_levels );
		$this->assertSame( '480p', $ktube_levels[0]['label'] );
	}

	public function test_sanitize_quality_levels_invalid_json_falls_back_to_default(): void {
		$ktube_levels = ktube_sanitize_quality_levels( 'not-valid-json' );
		$this->assertNotEmpty( $ktube_levels );
		$this->assertSame( '1080p', $ktube_levels[0]['label'] );
		$this->assertSame( 5000000, $ktube_levels[0]['bandwidth'] );
		$this->assertCount( 4, $ktube_levels, 'default ladder has 4 rungs' );
	}

	public function test_sanitize_quality_levels_non_string_non_array(): void {
		$this->assertNotEmpty( ktube_sanitize_quality_levels( null ) );
		$this->assertNotEmpty( ktube_sanitize_quality_levels( 123 ) );
		$this->assertNotEmpty( ktube_sanitize_quality_levels( false ) );
	}

	public function test_register_player_depth_wires_init_and_enqueue_hooks(): void {
		$GLOBALS['__ktube_actions'] = array();
		ktube_register_player_depth();
		$this->assertArrayHasKey( 'customize_register', $GLOBALS['__ktube_actions'] );
		$this->assertArrayHasKey( 'wp_enqueue_scripts', $GLOBALS['__ktube_actions'] );
		$ktube_callbacks_init    = array_column( $GLOBALS['__ktube_actions']['customize_register'],    'callback' );
		$ktube_callbacks_enqueue = array_column( $GLOBALS['__ktube_actions']['wp_enqueue_scripts'], 'callback' );
		$this->assertContains( 'ktube_customize_register_player_depth', $ktube_callbacks_init );
		$this->assertContains( 'ktube_inject_player_depth_config',      $ktube_callbacks_enqueue );
	}

	public function test_customize_register_player_depth_populates_manager(): void {
		// Phase 14 perf registers HLS knobs in the ktube_performance
		// section shared with image-formats; both modules run against a
		// fresh WP_Customize_Manager.
		$ktube_mgr = new WP_Customize_Manager();
		ktube_customize_register_player_depth( $ktube_mgr );
		$this->assertArrayHasKey( 'ktube_enable_hls',       $ktube_mgr->ktube_settings );
		$this->assertArrayHasKey( 'ktube_default_quality',  $ktube_mgr->ktube_settings );
		$this->assertArrayHasKey( 'ktube_quality_levels',   $ktube_mgr->ktube_settings );
		$this->assertSame( 'checkbox', $ktube_mgr->ktube_controls['ktube_enable_hls']['type'] );
		$this->assertSame( 'radio',    $ktube_mgr->ktube_controls['ktube_default_quality']['type'] );
		$this->assertSame( 'textarea', $ktube_mgr->ktube_controls['ktube_quality_levels']['type'] );
	}

	public function test_inject_player_depth_config_only_on_video_singular(): void {
		// Compile sanitize by clearing inline-scripts bag first.
		$GLOBALS['__ktube_inline_scripts'] = array();
		// Outside is_singular('video') it MUST early-bail: the global
		// inline_scripts bag stays empty.
		ktube_inject_player_depth_config();
		$this->assertSame( array(), $GLOBALS['__ktube_inline_scripts'], 'must not emit player config outside video singulars' );
	}
}
