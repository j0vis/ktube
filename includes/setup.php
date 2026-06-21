<?php
/**
 * Theme setup — supports, image sizes, nav menus, asset enqueues.
 *
 * Phase 0-A (2026-06-21): With Vite/SCSS dropped from the build pipeline,
 * the source-of-truth directories are now:
 *   - `assets/css/`   — hand-authored, committed, no preprocessor
 *   - `assets/js/`    — hand-authored, plain IIFE bundles, no bundler
 *   - `assets/vendor/videojs/` — vendored Video.js 8.17.4 prebuilt dist
 *
 * The cache-busting version string is derived from `filemtime()` so a
 * developer ZIP that ships a freshly-edited file still invalidates WP's
 * enqueued cache even if the developer forgot to bump `Version:` in
 * style.css. Falls back to KTUBE_VERSION when stat() fails so wp_enqueue_*
 * never receives `false`.
 *
 * @package ktube
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

function ktube_setup(): void {
	load_theme_textdomain( 'ktube', KTUBE_DIR . '/languages' );

	add_theme_support( 'automatic-feed-links' );
	add_theme_support( 'title-tag' );
	add_theme_support( 'post-thumbnails' );
	add_theme_support( 'responsive-embeds' );
	add_theme_support( 'align-wide' );
	add_theme_support( 'wp-block-styles' );
	add_theme_support( 'custom-logo', array(
		'height'      => 60,
		'width'       => 200,
		'flex-height' => true,
		'flex-width'  => true,
	) );

	add_theme_support( 'html5', array(
		'search-form',
		'comment-form',
		'comment-list',
		'gallery',
		'caption',
		'style',
		'script',
		'navigation-widgets',
	) );
	// Note: editor.css was retired alongside the SCSS pipeline (Phase 0-A
	// 2026-06-21). If block-editor styling is later needed, re-add the
	// call against a hand-authored assets/css/editor.css.

	register_nav_menus( array(
		'primary' => __( 'Primary Menu', 'ktube' ),
		'footer'  => __( 'Footer Menu', 'ktube' ),
		'social'  => __( 'Social Menu', 'ktube' ),
	) );

	add_image_size( 'ktube-card',     640, 360, true );
	add_image_size( 'ktube-card-2x', 1280, 720, true );
	add_image_size( 'ktube-hero',    1920, 1080, true );
	add_image_size( 'ktube-og',      1200, 630, true );
}
add_action( 'after_setup_theme', 'ktube_setup' );

/**
 * ktube_asset_version — cache-busting version string for an asset.
 *
 * Returns the file's mtime so a fresh commit invalidates WP's enqueued
 * cache even if the developer forgot to bump `Version:` in style.css.
 * Falls back to KTUBE_VERSION if the stat() call fails so `wp_enqueue_*`
 * never receives `false`.
 *
 * Renamed from ktube_dist_asset_version in Phase 0-A: the asset path is
 * no longer under assets/dist/, so the function name had to follow.
 *
 * @param string $path Absolute disk path to the asset file.
 * @return string|int
 */
function ktube_asset_version( string $path ) {
	$mtime = @filemtime( $path );
	return false !== $mtime ? (int) $mtime : KTUBE_VERSION;
}

/**
 * ktube_enqueue_assets — wp_enqueue_scripts hook.
 * Loads the site-wide CSS bundle + the main JS bootstrap. Phase 0-A
 * 2026-06-21: paths are assets/css/ and assets/js/ (hand-authored, no
 * preprocessor, no manifest).
 */
function ktube_enqueue_assets(): void {
	$css_path = KTUBE_DIR . '/assets/css/main.css';
	if ( file_exists( $css_path ) ) {
		wp_enqueue_style(
			'ktube-main',
			KTUBE_URI . '/assets/css/main.css',
			array(),
			ktube_asset_version( $css_path )
		);
	}
	$js_path = KTUBE_DIR . '/assets/js/main.js';
	if ( file_exists( $js_path ) ) {
		wp_enqueue_script(
			'ktube-main',
			KTUBE_URI . '/assets/js/main.js',
			array(),
			ktube_asset_version( $js_path ),
			true
		);
	}
}
add_action( 'wp_enqueue_scripts', 'ktube_enqueue_assets' );

/**
 * ktube_enqueue_dark_mode — emits dark-mode.js on every front-end page,
 * gated on the file's existence. Phase 0-A: source moved from
 * assets/dist/ to assets/js/; functionality unchanged.
 *
 * No wp_add_inline_script window.ktubeThemeData is emitted here; the
 * FOUC-prevention script in functions.php::ktube_inline_theme_bootstrap
 * resolves <html data-theme> at wp_head priority -1, and dark-mode.js
 * reads that attribute via `getTheme()`. The Customizer preview iframe
 * STILL emits window.ktubeThemeData via ktube_enqueue_customize_controls
 * because controls.js needs the Customizer default for its postMessage
 * bootstrap.
 */
function ktube_enqueue_dark_mode(): void {
	$js_path = KTUBE_DIR . '/assets/js/dark-mode.js';
	if ( ! file_exists( $js_path ) ) {
		return;
	}
	wp_enqueue_script(
		'ktube-dark-mode',
		KTUBE_URI . '/assets/js/dark-mode.js',
		array(),
		ktube_asset_version( $js_path ),
		true
	);
}
add_action( 'wp_enqueue_scripts', 'ktube_enqueue_dark_mode', 9 );

/**
 * ktube_enqueue_trailer_controller — only on video archive + video single +
 * channel/actor tax pages. Skips everywhere else so we don't preload JS.
 */
function ktube_enqueue_trailer_controller(): void {
	$ktube_needs = is_post_type_archive( 'video' )
		|| is_singular( 'video' )
		|| is_tax( array( 'channel', 'actor' ) );
	if ( ! $ktube_needs ) {
		return;
	}
	$js_path = KTUBE_DIR . '/assets/js/video-grid.js';
	if ( file_exists( $js_path ) ) {
		wp_enqueue_script(
			'ktube-trailer',
			KTUBE_URI . '/assets/js/video-grid.js',
			array(),
			ktube_asset_version( $js_path ),
			true
		);
	}
}
add_action( 'wp_enqueue_scripts', 'ktube_enqueue_trailer_controller', 12 );

/**
 * ktube_enqueue_lightbox — single-photo + archive-photo only.
 * Native HTMLDialogElement provides focus trap + Esc-to-close.
 *
 * Phase 14 perf (2026-06-21) — deferred-stylesheet split. The photo
 * grid + lightbox CSS now live in assets/css/lightbox.css rather than
 * shipping inside assets/css/main.css. This enqueue loads the
 * stylesheet ONLY on photo templates, removing ~6 KB of unused CSS
 * from every non-photo page load.
 */
function ktube_enqueue_lightbox(): void {
	if ( ! ( is_singular( 'photo' ) || is_post_type_archive( 'photo' ) ) ) {
		return;
	}
	$css_path = KTUBE_DIR . '/assets/css/lightbox.css';
	if ( file_exists( $css_path ) ) {
		wp_enqueue_style(
			'ktube-lightbox-css',
			KTUBE_URI . '/assets/css/lightbox.css',
			array( 'ktube-main' ),
			ktube_asset_version( $css_path )
		);
	}
	$js_path = KTUBE_DIR . '/assets/js/lightbox-controller.js';
	if ( file_exists( $js_path ) ) {
		wp_enqueue_script(
			'ktube-lightbox',
			KTUBE_URI . '/assets/js/lightbox-controller.js',
			array(),
			ktube_asset_version( $js_path ),
			true
		);
	}
}
add_action( 'wp_enqueue_scripts', 'ktube_enqueue_lightbox', 13 );

/**
 * ktube_enqueue_player — video-template-only enqueue.
 *
 * Skips entirely when WPS Player plugin owns playback (no double-init).
 * On singular('video'), this enqueue also loads the vendored Video.js
 * UMD bundle + the corresponding CSS — Phase 3b code-split evolved into
 * a Phase 0-A vendored-handle split: Video.js 8.17.4 ships VHS
 * (videojs-http-streaming) baked into the standard `video.min.js`, so
 * a separate HLS bundle is no longer required (HLS plays via the single
 * vendored bundle).
 *
 * Order matters: ktube-videojs must register BEFORE ktube-player.js so
 * the global `videojs` symbol exists when assets/js/player.js calls
 * `window.videojs(…)` on DOMContentLoaded.
 */
function ktube_enqueue_player(): void {
	if ( ! is_singular( 'video' ) ) {
		return;
	}
	// WPS Player plugin owns playback when installed/active — defer.
	// Function-existence guard required because the require order in
	// functions.php loads includes/wps-compat/wps-player.php AFTER this
	// file; a future require re-order would otherwise turn this into a
	// fatal "Call to undefined function" on singular('video') pages.
	if ( ! function_exists( 'ktube_has_wps_player' ) || ktube_has_wps_player() ) {
		return;
	}
	$vendor_js_path  = KTUBE_DIR . '/assets/vendor/videojs/video.min.js';
	$vendor_css_path = KTUBE_DIR . '/assets/vendor/videojs/video-js.min.css';
	if ( file_exists( $vendor_js_path ) ) {
		wp_enqueue_script(
			'ktube-videojs',
			KTUBE_URI . '/assets/vendor/videojs/video.min.js',
			array(),
			ktube_asset_version( $vendor_js_path ),
			true
		);
	}
	if ( file_exists( $vendor_css_path ) ) {
		wp_enqueue_style(
			'ktube-videojs-css',
			KTUBE_URI . '/assets/vendor/videojs/video-js.min.css',
			array( 'ktube-main' ),
			ktube_asset_version( $vendor_css_path )
		);
	}
	$player_path = KTUBE_DIR . '/assets/js/player.js';
	if ( file_exists( $player_path ) ) {
		wp_enqueue_script(
			'ktube-player',
			KTUBE_URI . '/assets/js/player.js',
			array( 'ktube-videojs' ),
			ktube_asset_version( $player_path ),
			true
		);
	}
}
add_action( 'wp_enqueue_scripts', 'ktube_enqueue_player', 11 );

/**
 * ktube_enqueue_customize_controls — Customizer preview iframe only.
 *
 * NOT site-wide (don't ship on every page). Bound to the
 * `customize_preview_init` action so WordPress only loads controls.js
 * inside the Customizer's preview iframe. Emits two inline-script globals
 * consumed by controls.js (theme default for dark-mode + per-setting SHA-256
 * fingerprints for the drift guard).
 */
function ktube_enqueue_customize_controls(): void {
	$js_path = KTUBE_DIR . '/assets/js/customize-controls.js';
	if ( ! file_exists( $js_path ) ) {
		return;
	}
	wp_enqueue_script(
		'ktube-customize-controls',
		KTUBE_URI . '/assets/js/customize-controls.js',
		array( 'customize-preview' ),
		ktube_asset_version( $js_path ),
		true
	);
	wp_add_inline_script(
		'ktube-customize-controls',
		'window.ktubeThemeData = ' . wp_json_encode(
			array(
				'default' => get_theme_mod( 'ktube_theme_default', 'auto' ),
			)
		) . ';',
		'before'
	);
	$ktube_checksums = ktube_compute_customizer_checksums();
	wp_add_inline_script(
		'ktube-customize-controls',
		'window.ktubeCustomizerSettingChecksums = ' . wp_json_encode(
			array(
				'total'   => $ktube_checksums['total'],
				'per_key' => $ktube_checksums['per_key'],
			)
		) . ';',
		'before'
	);
}
add_action( 'customize_preview_init', 'ktube_enqueue_customize_controls' );
