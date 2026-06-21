<?php
/**
 * Phase 7b — Privacy disclosure.
 *
 * Theme owns the data behind the auto-generated privacy card AND the footer
 * badge that surfaces it. Operators create a Page titled "Privacy" with slug
 * "privacy" (or any other slug + a Page id selected from the Customizer) —
 * ktube does the rest: the page renders an auto-doc data sheet documenting
 * what the theme currently stores about the visitor, and a small badge in
 * the site footer links to it whenever the age gate is active.
 *
 * Settings (theme_mods):
 *   - ktube_privacy_page_id (int, default 0) — id of a `page` post. 0 means
 *     "fall back to slug `privacy`" so operators who create a Page with
 *     that slug and never touch the Customizer still get working flow.
 *
 * Helper surface:
 *   - ktube_resolve_privacy_page_id()       — int (0 if no page resolves).
 *   - ktube_get_privacy_page_url()          — string permalink or ''.
 *   - ktube_should_show_privacy_badge()     — bool (gate on age-gate +
 *                                             resolvable page).
 *   - ktube_privacy_badge_copy()            — string copy that adapts to
 *                                             active features.
 *   - ktube_privacy_summary()               — array of rows documenting
 *                                             what the theme stores.
 *
 * Page template surface:
 *   - page-privacy.php auto-applied to:
 *     a) any Page that explicitly selects the "Privacy" template
 *        (theme_page_templates filter),
 *     b) any Page with slug `privacy` if no template was chosen
 *        (template_include filter).
 *
 * @package ktube
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * ktube_register_privacy — init hook loader.
 */
function ktube_register_privacy(): void {
	add_action( 'customize_register', 'ktube_privacy_customize_register' );
	add_filter( 'theme_page_templates', 'ktube_privacy_register_page_template', 10, 3 );
	add_filter( 'template_include', 'ktube_privacy_auto_apply_template_to_slug' );
}

/**
 * Add the Privacy drop-down entry to the Page template selector inside
 * the WP editor. Operators who create a Page and explicitly choose
 * "Privacy" from the drop-down get the privacy template regardless of
 * the page's slug.
 *
 * @param array    $ktube_templates
 * @param WP_Theme $ktube_theme
 * @param WP_Post  $ktube_post
 * @return array
 */
function ktube_privacy_register_page_template( array $ktube_templates, $ktube_theme = null, $ktube_post = null ): array {
	$ktube_templates['page-privacy.php'] = __( 'Privacy', 'ktube' );
	return $ktube_templates;
}

/**
 * Auto-apply the privacy template to a Page with slug `privacy` even
 * when no template was explicitly chosen. Short-circuits on non-page
 * queries and on pages whose slug does NOT match `privacy`, so the
 * filter only runs the slug-check + meta-read path on candidate pages.
 *
 * @param string $ktube_template
 * @return string
 */
function ktube_privacy_auto_apply_template_to_slug( string $ktube_template ): string {
	if ( ! is_page( 'privacy' ) ) {
		return $ktube_template;
	}
	// Honor an explicit template choice: if the operator picked a
	// specific template in the editor, do NOT override it.
	$ktube_page_id = get_queried_object_id();
	$ktube_meta     = $ktube_page_id ? (string) get_post_meta( $ktube_page_id, '_wp_page_template', true ) : '';
	if ( ! empty( $ktube_meta ) && 'default' !== $ktube_meta ) {
		return $ktube_template;
	}
	$ktube_privacy_template = KTUBE_DIR . '/page-privacy.php';
	if ( ! file_exists( $ktube_privacy_template ) ) {
		return $ktube_template;
	}
	return $ktube_privacy_template;
}

/**
 * Register the ktube_privacy_page_id Customizer control.
 *
 * @param WP_Customize_Manager $wp_customize
 */
function ktube_privacy_customize_register( WP_Customize_Manager $wp_customize ): void {
	$wp_customize->add_section(
		'ktube_privacy',
		array(
			'title'       => __( 'ktube — Privacy', 'ktube' ),
			'description' => __( 'Select the Page that documents your site&apos;s privacy practices. The footer badge surfaces here whenever the age gate is active.', 'ktube' ),
			'priority'    => 34,
		)
	);

	$wp_customize->add_setting(
		'ktube_privacy_page_id',
		array(
			'default'           => 0,
			'type'              => 'theme_mod',
			'capability'        => 'edit_theme_options',
			'transport'         => 'refresh',
			'sanitize_callback' => static function ( $v ): int {
				return (int) $v;
			},
		)
	);
	$wp_customize->add_control(
		'ktube_privacy_page_id',
		array(
			'label'       => __( 'Privacy page', 'ktube' ),
			'description' => __( 'Pick an existing Page. Operators who created a Page titled &quot;Privacy&quot; (slug &quot;privacy&quot;) and never touch this control still get the same behavior.', 'ktube' ),
			'section'     => 'ktube_privacy',
			'type'        => 'dropdown-pages',
			'allow_addition' => false,
		)
	);
}

/**
 * Resolve the privacy page id. Customizer-configured value wins when it
 * points at a published, accessible `page`. Falls back to a Page with
 * slug `privacy` so the slug-based convention works out of the box.
 * Returns 0 when nothing resolves.
 *
 * @return int
 */
function ktube_resolve_privacy_page_id(): int {
	$ktube_configured = (int) get_theme_mod( 'ktube_privacy_page_id', 0 );
	if ( $ktube_configured > 0 ) {
		$ktube_post = get_post( $ktube_configured );
		if ( $ktube_post && 'page' === $ktube_post->post_type && 'publish' === $ktube_post->post_status ) {
			return $ktube_configured;
		}
	}
	// Fallback: any Page with slug `privacy`.
	$ktube_query = new WP_Query(
		array(
			'post_type'      => 'page',
			'post_status'    => 'publish',
			'name'           => 'privacy',
			'posts_per_page' => 1,
			'no_found_rows'  => true,
			'fields'         => 'ids',
		)
	);
	if ( $ktube_query->have_posts() ) {
		foreach ( $ktube_query->posts as $ktube_id ) {
			return (int) $ktube_id;
		}
	}
	return 0;
}

/**
 * Permalink for the resolved privacy page, or empty string when no
 * page is configured.
 *
 * @return string
 */
function ktube_get_privacy_page_url(): string {
	$ktube_page_id = ktube_resolve_privacy_page_id();
	if ( ! $ktube_page_id ) {
		return '';
	}
	return (string) get_permalink( $ktube_page_id );
}

/**
 * Whether the footer badge should render. The badge's copy implies that
 * the site performs age verification, so it is gated on age-gate being
 * ON — even with a privacy page configured — to avoid lying. The RTA
 * flag is informational and is folded into the same gate because the
 * privacy page documents both.
 *
 * @return bool
 */
function ktube_should_show_privacy_badge(): bool {
	if ( ! ktube_age_gate_active() ) {
		return false;
	}
	return '' !== ktube_get_privacy_page_url();
}

/**
 * Adaptive badge copy that reflects active protections. Returns a
 * short (≤60 char) human sentence that screen-readers announce without
 * truncation.
 *
 * @return string
 */
function ktube_privacy_badge_copy(): string {
	// Sanitize min_age on read so a hand-edited DB row outside clamp still
	// surfaces a sensible badge. Same defensive pattern as
	// ktube_build_customizer_css() in includes/customizer.php.
	$ktube_min_age  = ktube_sanitize_cols( (int) get_theme_mod( 'ktube_age_gate_min_age', 18 ), 1, 99 );
	$ktube_rta_on   = (bool) get_theme_mod( 'ktube_rta_enabled', false );
	$ktube_gate_on  = ktube_age_gate_active();
	if ( $ktube_gate_on && $ktube_rta_on ) {
		return sprintf(
			/* translators: %d: configured minimum age */
			__( 'We verify visitors are %d+. Protected by RTA.', 'ktube' ),
			$ktube_min_age
		);
	}
	if ( $ktube_gate_on ) {
		return sprintf(
			/* translators: %d: configured minimum age */
			__( 'We verify visitors are %d+.', 'ktube' ),
			$ktube_min_age
		);
	}
	if ( $ktube_rta_on ) {
		return __( 'Protected by RTA.', 'ktube' );
	}
	return __( 'Privacy & cookies', 'ktube' );
}

/**
 * Privacy page data sheet rows. The page template iterates these and
 * renders `<dt>/<dd>` pairs plus an active state badge. Each row
 * documents ONE thing the theme might store / emit, regardless of
 * whether that thing is currently active. Active rows are shown
 * prominently; inactive rows collapse under an `<details>` so visitors
 * learn what the theme is capable of without getting confused about
 * the live state.
 *
 * @return array<int, array{active:bool, label:string, value:string, unit:string, description:string}>
 */
function ktube_privacy_summary(): array {
	$ktube_min_age  = (int) get_theme_mod( 'ktube_age_gate_min_age', 18 );
	$ktube_ttl_days = (int) get_theme_mod( 'ktube_age_gate_duration_days', 30 );
	$ktube_redirect = (string) get_theme_mod( 'ktube_age_gate_redirect_url', 'https://www.google.com/' );
	$ktube_gate_on  = ktube_age_gate_active();
	$ktube_rta_on   = (bool) get_theme_mod( 'ktube_rta_enabled', false );

	$ktube_rows = array();
	$ktube_rows[] = array(
		'active'      => $ktube_gate_on,
		'label'       => __( 'Minimum age', 'ktube' ),
		'value'       => (string) $ktube_min_age,
		'unit'        => __( 'years', 'ktube' ),
		'description' => __( 'Visitors are asked to confirm they are at least this old before viewing the site.', 'ktube' ),
	);
	$ktube_rows[] = array(
		'active'      => $ktube_gate_on,
		'label'       => __( 'localStorage key', 'ktube' ),
		'value'       => 'ktube-age-confirmed-on',
		'unit'        => '',
		'description' => __( 'Stores a unix timestamp of the moment the visitor confirmed their age. No personally identifying data is stored.', 'ktube' ),
	);
	$ktube_rows[] = array(
		'active'      => $ktube_gate_on,
		'label'       => __( 'Cookie name', 'ktube' ),
		'value'       => 'ktube_age_verified',
		'unit'        => '',
		'description' => __( 'Mirrors the localStorage timestamp so the verification persists when localStorage is unavailable. Set with SameSite=Lax + Secure.', 'ktube' ),
	);
	$ktube_rows[] = array(
		'active'      => $ktube_gate_on,
		'label'       => __( 'Verification retention', 'ktube' ),
		'value'       => (string) $ktube_ttl_days,
		'unit'        => _n( 'day', 'days', $ktube_ttl_days, 'ktube' ),
		'description' => __( 'Browsers stop honoring the verification after this many days.', 'ktube' ),
	);
	$ktube_rows[] = array(
		'active'      => $ktube_gate_on,
		'label'       => __( 'Underage redirect', 'ktube' ),
		'value'       => $ktube_redirect,
		'unit'        => '',
		'description' => __( 'Visitors who decline the confirmation are sent here instead of being shown the site.', 'ktube' ),
	);
	$ktube_rows[] = array(
		'active'      => $ktube_rta_on,
		'label'       => __( 'RTA meta tag', 'ktube' ),
		'value'       => '<meta name="rating" content="RTA-5042-1996-1400-1577-RTA">',
		'unit'        => '',
		'description' => __( 'Emitted in the page <head>. Independent of the age gate — used by RTA-aware browsers and parental-control extensions.', 'ktube' ),
	);
	$ktube_rows[] = array(
		'active'      => true,
		'label'       => __( 'Theme preference', 'ktube' ),
		'value'       => 'ktube-theme',
		'unit'        => '',
		'description' => __( 'Light or dark mode choice is stored in localStorage so a returning visitor sees the same theme they last selected.', 'ktube' ),
	);
	if ( function_exists( 'ktube_gdpr_active' ) && ktube_gdpr_active() ) {
		$ktube_rows[] = array(
			'active'      => true,
			'label'       => __( 'Cookie consent', 'ktube' ),
			/* translators: %1$s: storage key, %2$s: cookie name — both are literal identifiers */
			'value'       => sprintf( __( 'localStorage = %1$s; cookie = %2$s', 'ktube' ), 'ktube-gdpr-consent', 'ktube_gdpr_consent' ),
			'unit'        => '',
			'description' => __( 'Stored when the visitor explicitly accepts or rejects optional analytics/marketing categories via the consent banner. Essential cookies (age verification, theme preference) are always on and are not gated by this row.', 'ktube' ),
		);
	}
	return $ktube_rows;
}
