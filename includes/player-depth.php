<?php
/**
 * Phase 14 perf (2026-06-21) — Player depth: HLS activation + quality selector.
 *
 * Video.js 8.17.4 ships `videojs-http-streaming` (VHS) baked into the
 * standard `video.min.js`. A separate HLS plugin file is no longer
 * required — the same `window.videojs` global plays progressive mp4 and
 * HLS `.m3u8` out of the box (see assets/vendor/videojs/README.md §
 * "Why one bundle, not three").
 *
 * This file wires the operator-facing knobs:
 *   - ktube_enable_hls             — bool. OFF defaults (ktube falls back
 *                                    to progressive mp4). ON enrolls the
 *                                    HLS engine when a `_ktube_video_url`
 *                                    ends in `.m3u8`.
 *   - ktube_default_quality        — enum: auto | 1080p | 720p | 480p |
 *                                    360p. Drives VHS's `bandwidth`
 *                                    initial-rung preference.
 *   - ktube_quality_levels         — JSON-encoded array of {label,
 *                                    bandwidth} objects exposed as a
 *                                    quality selector UI in the player.
 *                                    Drives VHS `qualityLevels`.
 *
 * The integer-mapping between enum-width and bandwidth is a server
 * helper (ktube_quality_to_bandwidth) so PHP and JS agree.
 *
 * @package ktube
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

function ktube_register_player_depth(): void {
	add_action( 'customize_register', 'ktube_customize_register_player_depth' );
	add_action( 'wp_enqueue_scripts', 'ktube_inject_player_depth_config', 12 );
}

/**
 * Quality enum + bandwidth map.
 *
 * Bandwidth numbers are operator-tunable via Customizer JSON; the
 * label enum here is the closed set the selector UI dropdown exposes.
 *
 * @return array<string,int>
 */
function ktube_quality_bandwidth_map(): array {
	return array(
		'1080p' => 5000000,
		'720p'  => 2800000,
		'480p'  => 1400000,
		'360p'  => 800000,
	);
}

/**
 * ktube_quality_choices — radio choices for the Customizer.
 *
 * 'auto' is the default (no preference / let VHS choose).
 *
 * @return array<string,string>
 */
function ktube_quality_choices(): array {
	$ktube_map = ktube_quality_bandwidth_map();
	$ktube_out = array( 'auto' => __( 'Auto (let VHS decide)', 'ktube' ) );
	foreach ( array_keys( $ktube_map ) as $ktube_q ) {
		$ktube_out[ $ktube_q ] = $ktube_q;
	}
	return $ktube_out;
}

/**
 * ktube_sanitize_player_quality — closed-enum sanitizer.
 */
function ktube_sanitize_player_quality( $value ): string {
	$ktube_value  = is_string( $value ) ? sanitize_key( $value ) : 'auto';
	$ktube_choices = array_keys( ktube_quality_choices() );
	return in_array( $ktube_value, $ktube_choices, true ) ? $ktube_value : 'auto';
}

/**
 * ktube_sanitize_quality_levels — JSON / array sanitizer.
 *
 * Accepts either a JSON string from the Customizer textarea or an array
 * (programmatic callers). Returns an array of {label, bandwidth} where
 * `label` is one of the closed-quality enum. Empty/invalid → defaults.
 *
 * @param mixed $value
 * @return array<int, array{label:string, bandwidth:int}>
 */
function ktube_sanitize_quality_levels( $value ): array {
	$ktube_bandwidth  = ktube_quality_bandwidth_map();
	$ktube_default    = array(
		array( 'label' => '1080p', 'bandwidth' => $ktube_bandwidth['1080p'] ),
		array( 'label' => '720p',  'bandwidth' => $ktube_bandwidth['720p']  ),
		array( 'label' => '480p',  'bandwidth' => $ktube_bandwidth['480p']  ),
		array( 'label' => '360p',  'bandwidth' => $ktube_bandwidth['360p']  ),
	);
	if ( is_string( $value ) ) {
		$ktube_decoded = json_decode( trim( $value ), true );
	} elseif ( is_array( $value ) ) {
		$ktube_decoded = $value;
	} else {
		return $ktube_default;
	}
	if ( ! is_array( $ktube_decoded ) ) {
		return $ktube_default;
	}
	$ktube_out = array();
	foreach ( $ktube_decoded as $ktube_row ) {
		if ( ! is_array( $ktube_row ) ) {
			continue;
		}
		$ktube_label      = isset( $ktube_row['label'] ) ? sanitize_key( (string) $ktube_row['label'] ) : '';
		$ktube_row_bw     = isset( $ktube_row['bandwidth'] ) ? (int) $ktube_row['bandwidth'] : 0;
		if ( '' === $ktube_label || ! isset( $ktube_bandwidth[ $ktube_label ] ) ) {
			continue;
		}
		if ( $ktube_row_bw <= 0 ) {
			continue;
		}
		$ktube_out[] = array(
			'label'     => $ktube_label,
			'bandwidth' => $ktube_row_bw,
		);
	}
	return empty( $ktube_out ) ? $ktube_default : $ktube_out;
}

/**
 * ktube_customize_register_player_depth — Performance panel radio + JSON.
 *
 * @param WP_Customize_Manager $ktube_customize
 */
function ktube_customize_register_player_depth( $ktube_customize ): void {
	if ( ! is_object( $ktube_customize ) || ! method_exists( $ktube_customize, 'add_section' ) ) {
		return;
	}
	if ( ! isset( $ktube_customize->ktube_sections['ktube_performance'] ) ) {
		$ktube_customize->add_section(
			'ktube_performance',
			array(
				'title'       => __( 'ktube — Performance', 'ktube' ),
				'description' => __( 'Phase 14 perf knobs. AVIF / WebP + HLS quality selector. HLS requires `_ktube_video_url` ending in `.m3u8`.', 'ktube' ),
				'priority'    => 38,
			)
		);
	}
	$ktube_customize->add_setting(
		'ktube_enable_hls',
		array(
			'default'           => false,
			'type'              => 'theme_mod',
			'capability'        => 'edit_theme_options',
			'transport'         => 'refresh',
			'sanitize_callback' => 'ktube_sanitize_modern_image_formats',
		)
	);
	$ktube_customize->add_control(
		'ktube_enable_hls',
		array(
			'label'       => __( 'Activate HLS engine for `.m3u8` videos', 'ktube' ),
			'description' => __( 'Video.js 8 bundles VHS out of the box. Flip ON if your operators serve HLS manifests. Progressive mp4 plays without this toggle.', 'ktube' ),
			'section'     => 'ktube_performance',
			'type'        => 'checkbox',
		)
	);
	$ktube_customize->add_setting(
		'ktube_default_quality',
		array(
			'default'           => 'auto',
			'type'              => 'theme_mod',
			'capability'        => 'edit_theme_options',
			'transport'         => 'refresh',
			'sanitize_callback' => 'ktube_sanitize_player_quality',
		)
	);
	$ktube_customize->add_control(
		'ktube_default_quality',
		array(
			'label'       => __( 'Default playback quality (HLS)', 'ktube' ),
			'description' => __( 'Initial rung the player requests before ABR kicks in.', 'ktube' ),
			'section'     => 'ktube_performance',
			'type'        => 'radio',
			'choices'     => ktube_quality_choices(),
		)
	);
	$ktube_customize->add_setting(
		'ktube_quality_levels',
		array(
			'default'           => '',
			'type'              => 'theme_mod',
			'capability'        => 'edit_theme_options',
			'transport'         => 'refresh',
			'sanitize_callback' => 'ktube_sanitize_quality_levels',
		)
	);
	$ktube_customize->add_control(
		'ktube_quality_levels',
		array(
			'label'       => __( 'Quality levels (JSON: `[{"label":"1080p","bandwidth":5000000}]`)', 'ktube' ),
			'description' => __( 'Optional override of the closed `[1080p, 720p, 480p, 360p]` ladder. Leave blank to use defaults.', 'ktube' ),
			'section'     => 'ktube_performance',
			'type'        => 'textarea',
		)
	);
}

/**
 * ktube_inject_player_depth_config — emit window.ktubeVideoPlayerConfig.
 *
 * Bound at wp_enqueue_scripts priority 12 so it lands AFTER
 * ktube_enqueue_player (priority 11) which already enqueued
 * ktube-videojs. assets/js/player.js reads the inline script via
 * window.ktubeVideoPlayerConfig at DOMContentLoaded.
 */
function ktube_inject_player_depth_config(): void {
	if ( ! is_singular( 'video' ) ) {
		return;
	}
	$ktube_bandwidth = ktube_quality_bandwidth_map();
	$ktube_default_levels = array(
		array( 'label' => '1080p', 'bandwidth' => $ktube_bandwidth['1080p'] ),
		array( 'label' => '720p',  'bandwidth' => $ktube_bandwidth['720p']  ),
		array( 'label' => '480p',  'bandwidth' => $ktube_bandwidth['480p']  ),
		array( 'label' => '360p',  'bandwidth' => $ktube_bandwidth['360p']  ),
	);
	$ktube_levels_raw = get_theme_mod( 'ktube_quality_levels', '' );
	$ktube_levels     = ktube_sanitize_quality_levels( $ktube_levels_raw );
	if ( empty( $ktube_levels ) ) {
		$ktube_levels = $ktube_default_levels;
	}
	$ktube_config = array(
		'enable_hls'      => (bool) get_theme_mod( 'ktube_enable_hls', false ),
		'default_quality' => ktube_sanitize_player_quality( get_theme_mod( 'ktube_default_quality', 'auto' ) ),
		'quality_levels'  => $ktube_levels,
	);
	wp_add_inline_script(
		'ktube-videojs',
		'window.ktubeVideoPlayerConfig = ' . wp_json_encode( $ktube_config ) . ';',
		'before'
	);
}
