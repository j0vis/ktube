<?php
/**
 * Phase 14 perf (2026-06-21) — Critical CSS inline emission.
 *
 * Emits assets/css/critical.css as an inline <style> AT wp_head priority 5
 * so the source-of-truth above-the-fold rules paint before the linked
 * assets/css/main.css finishes loading. The ktube-main <link rel="stylesheet">
 * still loads afterward, carrying non-critical styles (blog card, theme
 * toggle, age-gate modal, privacy summary, WCAG badge) which paint
 * progressively without blocking the first contentful render.
 *
 * Trust outline:
 *   - critical.css is hand-authored and intentionally small (< 14 KB).
 *   - We do NOT minify at runtime: ktube ships verbatim CSS so a
 *     developer who edits main.css / critical.css in a code review
 *     sees the same bytes that the browser parses.
 *   - File is read once per page render. Combined with
 *     ktube_asset_version()'s mtime-based cache-bust on the linked
 *     main.css, operators never have to bump a version manually after a
 *     CSS edit.
 *
 * @package ktube
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * ktube_register_critical_css — init hook loader.
 */
function ktube_register_critical_css(): void {
	add_action( 'wp_head', 'ktube_inline_critical_css', 5 );
}

/**
 * ktube_inline_critical_css — emit critical.css inline AT <head>.
 *
 * Reads assets/css/critical.css (the source-of-truth above-the-fold slice)
 * and emits it inside <style id="ktube-critical">. The id is fixed so
 * tests can target it deterministically; WP doesn't otherwise reference
 * this id.
 *
 * Emits nothing if:
 *   - the file is missing (operator ZIPS a stripped theme by mistake),
 *   - the file is empty (still under maintenance),
 *   - WP is in admin context (no critical paint there),
 *   - WP is doing cron / AJAX (no <head> paint at all).
 *
 * The first three guards fail open with a no-op rather than throwing —
 * the linked main.css still ships its full content (including the
 * critical slice duplicated inside it) so the page renders correctly
 * with one extra round-trip. The user's experience is slower rather
 * than broken.
 */
function ktube_inline_critical_css(): void {
	if ( is_admin() || wp_doing_ajax() || wp_doing_cron() ) {
		return;
	}
	$ktube_path = KTUBE_DIR . '/assets/css/critical.css';
	if ( ! file_exists( $ktube_path ) ) {
		return;
	}
	$ktube_css = (string) file_get_contents( $ktube_path );
	if ( '' === $ktube_css ) {
		return;
	}
	// <style id="ktube-critical">…</style>. No nonce needed because the
	// contents are server-controlled, not user-controlled.
	echo '<style id="ktube-critical">' . $ktube_css . '</style>' . "\n"; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped — file_get_contents of static theme asset.
}
