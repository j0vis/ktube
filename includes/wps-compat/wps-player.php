<?php
/**
 * WP-Script WPS Player (Clean Tube Player plugin) compatibility surface.
 *
 * VERIFIED-AGAINST-PLUGIN-SOURCE: YES (clean-tube-player, 2026-06-21)
 *
 * Plugin source audited at:
 *   C:\Users\Gamer\Desktop\Development\WordPress\WP-Script\Plug-ins\clean-tube-player
 *
 * Verified detection signals (primary → fallback):
 *   - defined( 'CTPL_VERSION' )                 ← plugin's actual version constant
 *   - class_exists( 'CTPL' )                    ← plugin's main class (CTPL::instance())
 *   - function_exists( 'wps_player' )           // cheap safety net for legacy WPS plugin forks
 *
 * NOTE: `CLEAN_TUBE_PLAYER_VERSION` is demoted from the runtime detection list.
 * No audited plugin source defines it; it is forward-compat-only and would
 * produce false-negatives if removed silently. See ktube_has_wps_player().
 *
 * Hook contract ktube exposes on single-video templates. Live emission lives
 * in template-parts/video/player-wrap.php (see there for runtime order):
 *   - do_action( 'ktube_before_player', WP_Post $post )
 *   - do_action( 'ktube_after_player',  WP_Post $post )
 *   - apply_filters( 'ktube_player_markup', string $ktube_native_markup, WP_Post $post ) → string
 *     ($ktube_native_markup is the canonical variable name in the live call site;
 *      it also matches the header docblock in includes/wps-compat/wps-player.php).
 *     Consumer may return '' to fall back to the native markup.
 *
 * ADVISORY-ONLY: the clean-tube-player plugin does NOT currently consume any
 * `ktube_*` hook by name (verified absent in plugin source). The hooks are a
 * forward-looking integration surface — third-party plugins (or future WPS
 * releases) MAY consume them. ktube does NOT represent these as functional today.
 *
 * WPS-Script SIBLING PLUGINS — NOT YET AUDITED:
 *   - WPS Mass Importer          — see includes/wps-compat/mass-importer.php
 *   - WPS Browser                — no ktube integration surface today
 *   - WPS Subscription           — no ktube integration surface today
 *   - Adult Mass Videos Embedder — referenced by adult-video themes, unverified
 * Any of these shifting to consume ktube_* hooks post-audit must drive a
 * re-check of the contract above.
 *
 * @package ktube
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Whether the WP-Script Clean Tube Player plugin is present and active.
 *
 * MU-plugins or child themes may force the result via:
 *   apply_filters( 'ktube_force_wps_player_active', true|false )
 *
 * @return bool
 */
function ktube_has_wps_player(): bool {
	$forced = apply_filters( 'ktube_force_wps_player_active', null );
	if ( is_bool( $forced ) ) {
		return $forced;
	}

	// Primary signal — verified directly in clean-tube-player source (`CTPL::instance()` call).
	if ( defined( 'CTPL_VERSION' ) || class_exists( 'CTPL', false ) ) {
		return true;
	}
	// Cheap safety net: detected via known public function name across older WPS forks.
	if ( function_exists( 'wps_player' ) ) {
		return true;
	}
	return false;
}

/**
 * SPECULATIVE FORWARD-COMPAT CONSTANT — REMOVED.
 *
 * `CLEAN_TUBE_PLAYER_VERSION` was previously checked in ktube_has_wps_player() as
 * a forward-compat fallback for hypothetical rebrandings of WP-Script's player
 * plugin family. Phase-9 plugin-source audit confirmed that no plugin in the
 * WP-Script catalogue defines it under that exact name — and the community fork
 * that does, lacks the `CTPL` class. Cost of maintaining the signal outweighs
 * the marginal false-positive it would catch. Reasoning is captured in the
 * file header docblock above (search "CLEAN_TUBE_PLAYER_VERSION is demoted").
 *
 * If a future ktube release needs to detect an unverified WPS fork, prefer:
 *   apply_filters( 'ktube_force_wps_player_active', true )
 * from a small MU-plugin — that path is intentional and doesn't pollute the
 * default detection chain.
 */

/**
 * Compatibility hook registration.
 */
function ktube_register_wps_player_compat(): void {
	// Verified: clean-tube-player does not reference ktube_* hooks in source.
	// Surface is advisory-only; no additional init wiring required.
}
