<?php
/**
 * Phase 14 perf (2026-06-21) — AVIF + WebP image-format negotiation.
 *
 * Adds `type='image/avif'` and `type='image/webp'` candidates to every
 * `wp_calculate_image_srcset()` call when:
 *   1. The Customizer toggle `ktube_modern_image_formats` is ON (default).
 *   2. A sibling `${url}.avif` or `${url}.webp` file exists on disk.
 *
 * ktube deliberately does NOT generate AVIF/WebP variants server-side
 * (would force a transcoder dependency on operators). The filter expects
 * an upstream tool (ShortPixel / Imagify / EWWW / a manual export from
 * a CDN) to keep `${path}.avif` / `${path}.webp` siblings in sync with
 * the original JPG/PNG. Operators without that pipeline simply see the
 * existing srcset passthrough — zero-cost, opt-in.
 *
 * Why `wp_calculate_image_srcset` not `wp_get_attachment_image_attributes`:
 * the semantics map directly. WP's srcset output goes through this
 * filter unconditionally for every <img> that uses `wp_get_attachment_image`
 * (and srcset helpers). The filter returns the same `array<int, array{url,
 * descriptor, value, mime_type?}>` shape WP expects; modern browsers then
 * pick the smallest acceptable type from each candidate's `type` hint.
 *
 * @package ktube
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

function ktube_register_image_formats(): void {
	add_action( 'init', 'ktube_register_image_formats_setting' );
	add_filter( 'wp_calculate_image_srcset', 'ktube_prepend_modern_format_candidates', 10, 5 );
}

/**
 * Register the Customizer toggle + control.
 *
 * Phase 14 perf adds this as a SEPARATE customizer callback so the
 * existing ktube_customize_register() closure (one of two — layout +
 * colors + dark_mode) stays additive. Operators who don't ship AVIF/WebP
 * variants flip it OFF in the Customizer; srcset passthrough resumes.
 */
function ktube_register_image_formats_setting(): void {
	if ( ! function_exists( 'add_action' ) ) {
		return;
	}
	add_action( 'customize_register', 'ktube_customize_register_image_formats' );
}

/**
 * ktube_modern_image_formats_default — defaults to ON.
 *
 * Lazy getter: callable from sanitize_callback at register-time which
 * runs before theme_mods are loaded. Lives in its own function so a
 * tester can flip the default in one place.
 */
function ktube_modern_image_formats_default(): bool {
	return true;
}

/**
 * ktube_sanitize_modern_image_formats — checkbox sanitizer.
 *
 * Real WP's checkbox sanitizer coerces `'1'` / `true` → true and
 * eliminates sneaky values like `'0_true'`. We mirror WP's WP_Customize_Setting
 * checkbox behavior: only `true | 1 | '1'` are truthy.
 */
function ktube_sanitize_modern_image_formats( $value ): bool {
	if ( true === $value ) {
		return true;
	}
	if ( '1' === $value || 1 === $value ) {
		return true;
	}
	return false;
}

/**
 * ktube_modern_image_formats_enabled — runtime read.
 *
 * Resolves through `apply_filters` so a future SEO/performance plugin
 * can force-disable per page. Default is the customizer mod itself.
 */
function ktube_modern_image_formats_enabled(): bool {
	$ktube_default = ktube_modern_image_formats_default();
	$ktube_value    = get_theme_mod( 'ktube_modern_image_formats', $ktube_default );
	if ( ! is_bool( $ktube_value ) ) {
		$ktube_value = ktube_sanitize_modern_image_formats( $ktube_value );
	}
	/**
	 * Filter whether AVIF/WebP srcset candidates should be emitted.
	 *
	 * @param bool $ktube_value
	 */
	return (bool) apply_filters( 'ktube_modern_image_formats_enabled', $ktube_value );
}

/**
 * ktube_customize_register_image_formats — Customizer panel + control.
 *
 * @param WP_Customize_Manager $ktube_customize
 */
function ktube_customize_register_image_formats( $ktube_customize ): void {
	if ( ! is_object( $ktube_customize ) || ! method_exists( $ktube_customize, 'add_section' ) ) {
		return;
	}
	$ktube_customize->add_section(
		'ktube_performance',
		array(
			'title'       => __( 'ktube — Performance', 'ktube' ),
			/* translators: %s is a non-formatted description, keep generic */
			'description' => __( 'Phase 14 perf knobs. AVIF / WebP srcset negotiation requires the corresponding file variants to exist on disk; otherwise no candidates are emitted and srcset passthrough resumes.', 'ktube' ),
			'priority'    => 38,
		)
	);
	$ktube_customize->add_setting(
		'ktube_modern_image_formats',
		array(
			'default'           => ktube_modern_image_formats_default(),
			'type'              => 'theme_mod',
			'capability'        => 'edit_theme_options',
			'transport'         => 'refresh',
			'sanitize_callback' => 'ktube_sanitize_modern_image_formats',
		)
	);
	$ktube_customize->add_control(
		'ktube_modern_image_formats',
		array(
			'label'       => __( 'Serve modern image formats (AVIF / WebP)', 'ktube' ),
			'description' => __( 'Emits `type="image/avif"` and `type="image/webp"` candidates when the file variants exist on disk. No effect if variants are absent.', 'ktube' ),
			'section'     => 'ktube_performance',
			'type'        => 'checkbox',
		)
	);
}

/**
 * ktube_prepend_modern_format_candidates — wp_calculate_image_srcset filter.
 *
 * For each existing candidate URL, runs a disk check for `.avif` and
 * `.webp` siblings in the same wp-content/uploads tree. Hits prepend
 * a fresh candidate BEFORE the original so browsers that accept the
 * newer format short-circuit and never download the legacy one.
 *
 * Concatenation key collision-avoidance: we re-key candidates using
 * the format prefix (`avif_…`, `webp_…`) so PHP never overwrites an
 * entry when the original srcset happened to collide on numeric key.
 *
 * @param array<int|string, array{url:string, descriptor?:string, value?:int, mime_type?:string}> $ktube_sources
 * @param array<int|string, int>                                                                  $ktube_size_array
 * @param string                                                                                  $ktube_image_src
 * @param array<string, mixed>                                                                    $ktube_image_meta
 * @param int                                                                                     $ktube_attachment_id
 * @return array<int|string, array{url:string, descriptor?:string, value?:int, mime_type?:string}>
 */
function ktube_prepend_modern_format_candidates( $ktube_sources, $ktube_size_array, $ktube_image_src, $ktube_image_meta, $ktube_attachment_id ) {
	if ( ! ktube_modern_image_formats_enabled() ) {
		return $ktube_sources;
	}
	if ( ! is_array( $ktube_sources ) || empty( $ktube_sources ) ) {
		return $ktube_sources;
	}
	$ktube_upload_dir = wp_get_upload_dir();
	if ( empty( $ktube_upload_dir['basedir'] ) || empty( $ktube_upload_dir['baseurl'] ) ) {
		return $ktube_sources;
	}
	$ktube_base_dir = (string) $ktube_upload_dir['basedir'];
	$ktube_base_url = (string) $ktube_upload_dir['baseurl'];
	$ktube_out      = array();
	foreach ( $ktube_sources as $ktube_key => $ktube_entry ) {
		// Defensive: every entry must be an array with a `url` field. Skip
		// anything malformed so a broken candidate doesn't strip later
		// entries from the srcset.
		if ( ! is_array( $ktube_entry ) || ! isset( $ktube_entry['url'] ) || ! is_string( $ktube_entry['url'] ) || '' === $ktube_entry['url'] ) {
			$ktube_out[ $ktube_key ] = $ktube_entry;
			continue;
		}
		$ktube_url = $ktube_entry['url'];
		foreach ( array( 'avif', 'webp' ) as $ktube_fmt ) {
			$ktube_candidate_url  = preg_replace( '/\.(jpe?g|png|gif)$/i', '.' . $ktube_fmt, $ktube_url );
			$ktube_candidate_path = '';
			if ( 0 === strpos( $ktube_candidate_url, $ktube_base_url ) ) {
				$ktube_candidate_path = $ktube_base_dir . substr( $ktube_candidate_url, strlen( $ktube_base_url ) );
			}
			if ( '' === $ktube_candidate_path || ! file_exists( $ktube_candidate_path ) ) {
				continue;
			}
			$ktube_out[ $ktube_fmt . '_' . $ktube_key ] = array(
				'url'        => $ktube_candidate_url,
				'descriptor' => isset( $ktube_entry['descriptor'] ) ? $ktube_entry['descriptor'] : 'w',
				'value'      => isset( $ktube_entry['value'] ) ? (int) $ktube_entry['value'] : 0,
				'mime_type'  => 'image/' . $ktube_fmt,
			);
		}
		$ktube_out[ $ktube_key ] = $ktube_entry;
	}
	return $ktube_out;
}
