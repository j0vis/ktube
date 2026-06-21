<?php
/**
 * ktube functions.php — bootstrap only.
 *
 * Real work in /includes/. Phase N modules added by:
 *   1. Creating /includes/foo.php with ktube_register_foo() (or init hook).
 *   2. Adding require_once below.
 *   3. Adding init/add_action hookup.
 *
 * Phase 0-A (2026-06-21): the dropped Vite/SCSS build pipeline made
 * the prior "pre-built assets missing" admin notice obsolete — CSS/JS
 * are hand-authored at assets/css/ and assets/js/ and shipped verbatim.
 * Cloning the repo no longer requires `npm install`.
 *
 * @package ktube
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'KTUBE_VERSION', wp_get_theme()->get( 'Version' ) );
define( 'KTUBE_DIR', get_template_directory() );
define( 'KTUBE_URI', get_template_directory_uri() );
define( 'KTUBE_MIN_PHP', '8.0' );
define( 'KTUBE_MIN_WP', '6.5' );

/**
 * Resolve the data-theme attribute for the inline FOUC-prevention script in
 * header.php. Server returns the *static* default chosen in Customizer
 * ('light' or 'dark'); when 'auto' the default is 'light' and the inline JS
 * swaps it once localStorage or matchMedia returns. The runtime resolution
 * (localStorage → Customizer default → OS preference) lives in dark-mode.js.
 */
function ktube_resolve_initial_theme(): string {
	$ktube_default = get_theme_mod( 'ktube_theme_default', 'auto' );
	if ( 'dark' === $ktube_default ) {
		return 'dark';
	}
	return 'light';
}

/**
 * ktube_inline_theme_bootstrap — echo a tiny pre-DOM script that lets the
 * visitor's localStorage choice win over the saved Customizer default without
 * clobbering an explicit server-resolved 'dark'.
 *
 * Rules (in order):
 *   1. If localStorage 'ktube-theme' is 'light' | 'dark' → use it.
 *   2. Otherwise if the server already resolved 'dark' (Customizer forced
 *      dark) → keep it. Never flip a server-set 'dark' to 'light' from OS.
 *   3. Otherwise the server fell back to the 'light' default (theme_default
 *      is 'auto'); honor matchMedia('prefers-color-scheme: dark').
 *
 * Mirrors assets/js/dark-mode.js::getTheme() lookup semantics on the client.
 */
function ktube_inline_theme_bootstrap(): void {
	$ktube_initial = ktube_resolve_initial_theme();
	?>
	<script>
		(function () {
			var html = document.documentElement;
			try {
				var ktubeStored = localStorage.getItem('ktube-theme');
				if (ktubeStored === 'light' || ktubeStored === 'dark') {
					html.setAttribute('data-theme', ktubeStored);
					return;
				}
			} catch (_) {}
			if (html.getAttribute('data-theme') === 'dark') {
				return;
			}
			if (window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches) {
				html.setAttribute('data-theme', 'dark');
			}
		})();
	</script>
	<style id="ktube-color-scheme-meta">
		:root { color-scheme: light; }
		:root[data-theme="dark"] { color-scheme: dark; }
	</style>
	<?php
}
add_action( 'wp_head', 'ktube_inline_theme_bootstrap', -1 );

if ( version_compare( PHP_VERSION, KTUBE_MIN_PHP, '<' ) ) {
	add_action(
		'admin_notices',
		static function (): void {
			printf(
				'<div class="notice notice-error"><p>%s</p></div>',
				esc_html(
					sprintf(
						/* translators: %s: required PHP version */
						__( 'ktube requires PHP %s or later. Theme has not loaded.', 'ktube' ),
						KTUBE_MIN_PHP
					)
				)
			);
		}
	);
	return;
}

global $wp_version;
if ( ! isset( $wp_version ) || version_compare( $wp_version, KTUBE_MIN_WP, '<' ) ) {
	add_action(
		'admin_notices',
		static function (): void {
			printf(
				'<div class="notice notice-error"><p>%s</p></div>',
				esc_html(
					sprintf(
						/* translators: %s: required WordPress version */
						__( 'ktube requires WordPress %s or later. Theme has not loaded.', 'ktube' ),
						KTUBE_MIN_WP
					)
				)
			);
		}
	);
	return;
}

/*
 * Phase 0-A 2026-06-21:
 * The pre-existing "pre-built assets missing" admin notice was retired.
 * Hand-authored assets/{css,js,vendor}/ files ship in the repo verbatim;
 * there is no build step that could outrun a developer's ZIP upload. If a
 * future phase ever introduces a build step (e.g. block-bundle compilation),
 * resurrect a similar guard inside setup.php with a sfw guard path that
 * can actually fail (e.g. a runtime-only asset).
 */

require_once KTUBE_DIR . '/includes/setup.php';
require_once KTUBE_DIR . '/includes/post-types.php';
require_once KTUBE_DIR . '/includes/taxonomies.php';
require_once KTUBE_DIR . '/includes/meta.php';
require_once KTUBE_DIR . '/includes/wps-compat/mass-importer.php';
require_once KTUBE_DIR . '/includes/wps-compat/wps-player.php';
require_once KTUBE_DIR . '/includes/seo/schema.php';
require_once KTUBE_DIR . '/includes/template-functions.php';
require_once KTUBE_DIR . '/includes/wps-compat/importer-adapter.php';
require_once KTUBE_DIR . '/includes/wps-compat/db-indexes.php';
require_once KTUBE_DIR . '/includes/customizer.php';
require_once KTUBE_DIR . '/includes/age-gate.php';
require_once KTUBE_DIR . '/includes/privacy.php';
require_once KTUBE_DIR . '/includes/compliance-pages.php';
require_once KTUBE_DIR . '/includes/gdpr.php';
require_once KTUBE_DIR . '/includes/critical-css.php';
require_once KTUBE_DIR . '/includes/image-formats.php';
require_once KTUBE_DIR . '/includes/player-depth.php';

add_action( 'init', 'ktube_register_taxonomies', 8 );
add_action( 'init', 'ktube_register_post_types', 9 );
add_action( 'init', 'ktube_register_meta', 11 );
add_action( 'init', 'ktube_register_mass_importer_compat', 12 );
add_action( 'init', 'ktube_register_wps_player_compat', 12 );
add_action( 'init', 'ktube_register_schema' );
add_action( 'init', 'ktube_register_importer_adapter', 13 );
add_action( 'init', 'ktube_register_db_indexes_compat', 13 );
add_action( 'init', 'ktube_register_customizer', 14 );
add_action( 'init', 'ktube_register_age_gate', 15 );
add_action( 'init', 'ktube_register_privacy', 16 );
add_action( 'init', 'ktube_register_compliance_pages', 17 );
add_action( 'init', 'ktube_register_gdpr', 18 );
add_action( 'init', 'ktube_register_critical_css', 19 );
add_action( 'init', 'ktube_register_image_formats', 20 );
add_action( 'init', 'ktube_register_player_depth', 21 );
