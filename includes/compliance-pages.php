<?php
/**
 * Phase 8-B — 2257 + DMCA + Terms compliance page templates + footer nav.
 *
 * Mirrors the pattern of includes/privacy.php:
 *   - 3 new page templates: page-2257.php, page-dmca.php, page-terms.php
 *     (each ships a default heading + the_content() slot; NEVER authors
 *     legal text — operators fill in jurisdiction-specific clauses).
 *   - Customizer `ktube_compliance` section with 3 dropdown-pages
 *     controls (one per kind).
 *   - Slug-based auto-apply (template_include filter) so a Page with
 *     slug "2257" / "dmca" / "terms" inherits the right template even
 *     when no template was explicitly chosen in the editor.
 *   - 4 menu slots helper (Privacy + 2257 + DMCA + Terms) consumed by
 *     footer.php's `<nav class="ktube-compliance-links">`.
 *
 * IMPORTANT: ktube does NOT author legal text. Operators are responsible
 * for jurisdiction-specific clauses, dates, contact information, and
 * recordkeeping details. The templates render only the chrome.
 *
 * @package ktube
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Canonical registry of compliance kinds this theme ships templates for.
 *
 * Each row: slug => [display_label, theme_mod_setting_id, template_filename].
 * Extend by adding a row + a corresponding page-*.php template file.
 *
 * @return array<string, array{label:string,setting_id:string,template:string}>
 */
function ktube_compliance_kinds(): array {
	return array(
		'2257'  => array(
			'label'      => __( '2257 Compliance', 'ktube' ),
			'setting_id' => 'ktube_2257_page_id',
			'template'   => 'page-2257.php',
		),
		'dmca'  => array(
			'label'      => __( 'DMCA', 'ktube' ),
			'setting_id' => 'ktube_dmca_page_id',
			'template'   => 'page-dmca.php',
		),
		'terms' => array(
			'label'      => __( 'Terms of Service', 'ktube' ),
			'setting_id' => 'ktube_terms_page_id',
			'template'   => 'page-terms.php',
		),
	);
}

/**
 * init hook loader.
 */
function ktube_register_compliance_pages(): void {
	add_action( 'customize_register', 'ktube_compliance_customize_register' );
	add_filter( 'theme_page_templates', 'ktube_register_compliance_page_templates', 10, 4 );
	add_filter( 'template_include', 'ktube_compliance_auto_apply_template_to_slug' );
}

/**
 * Add the 3 new templates to the Page template selector.
 *
 * @param array    $ktube_templates
 * @param WP_Theme $ktube_theme
 * @param WP_Post  $ktube_post
 * @return array
 */
function ktube_register_compliance_page_templates( array $ktube_templates, $ktube_theme = null, $ktube_post = null ): array {
	foreach ( ktube_compliance_kinds() as $ktube_kind ) {
		$ktube_templates[ $ktube_kind['template'] ] = $ktube_kind['label'];
	}
	return $ktube_templates;
}

/**
 * Auto-apply the right compliance template to a Page whose slug
 * matches one of the canonical kinds. Honors an explicit template
 * choice (does not override operators who picked a different template
 * in the editor).
 *
 * @param string $ktube_template
 * @return string
 */
function ktube_compliance_auto_apply_template_to_slug( string $ktube_template ): string {
	if ( ! function_exists( 'is_page' ) || ! is_page() ) {
		return $ktube_template;
	}
	$ktube_page_id = get_queried_object_id();
	$ktube_meta     = $ktube_page_id ? (string) get_post_meta( $ktube_page_id, '_wp_page_template', true ) : '';
	if ( ! empty( $ktube_meta ) && 'default' !== $ktube_meta ) {
		return $ktube_template;
	}
	$ktube_page = get_post( $ktube_page_id );
	if ( ! $ktube_page || ! isset( $ktube_page->post_name ) ) {
		return $ktube_template;
	}
	$ktube_slug  = (string) $ktube_page->post_name;
	$ktube_kinds = ktube_compliance_kinds();
	if ( ! isset( $ktube_kinds[ $ktube_slug ] ) ) {
		return $ktube_template;
	}
	$ktube_target = KTUBE_DIR . '/' . $ktube_kinds[ $ktube_slug ]['template'];
	return file_exists( $ktube_target ) ? $ktube_target : $ktube_template;
}

/**
 * Register the ktube_compliance Customizer section + 3 dropdown-pages
 * controls. Each control's default is 0 (slug-based fallback applies).
 *
 * @param WP_Customize_Manager $wp_customize
 */
function ktube_compliance_customize_register( WP_Customize_Manager $wp_customize ): void {
	$wp_customize->add_section(
		'ktube_compliance',
		array(
			'title'       => __( 'ktube — Compliance Pages', 'ktube' ),
			'description' => __( 'Select Pages for the legally-required compliance disclosures. Each template ships a default heading + content slot for operator-supplied legal text.', 'ktube' ),
			'priority'    => 35,
		)
	);
	foreach ( ktube_compliance_kinds() as $ktube_kind ) {
		$wp_customize->add_setting(
			$ktube_kind['setting_id'],
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
			$ktube_kind['setting_id'],
			array(
				/* translators: %s: compliance kind label (e.g. "DMCA") */
				'label'          => sprintf( __( '%s page', 'ktube' ), $ktube_kind['label'] ),
				'description'    => __( 'Pick an existing Page. Operators who create a Page with the matching slug and never touch this control still get the same behavior.', 'ktube' ),
				'section'        => 'ktube_compliance',
				'type'           => 'dropdown-pages',
				'allow_addition' => false,
			)
		);
	}
}

/**
 * Resolve a compliance-kind page id. Customizer-configured value wins
 * when it points at a published `page`; otherwise slug-based fallback.
 * Returns 0 when no page resolves (including unknown slugs).
 *
 * @param string $slug One of ktube_compliance_kinds() keys, or any
 *                     unknown string (returns 0).
 * @return int
 */
function ktube_resolve_compliance_page_id( string $slug ): int {
	$ktube_kinds = ktube_compliance_kinds();
	if ( ! isset( $ktube_kinds[ $slug ] ) ) {
		return 0;
	}
	$ktube_setting    = $ktube_kinds[ $slug ]['setting_id'];
	$ktube_configured = (int) get_theme_mod( $ktube_setting, 0 );
	if ( $ktube_configured > 0 ) {
		$ktube_post = get_post( $ktube_configured );
		if ( $ktube_post && 'page' === $ktube_post->post_type && 'publish' === $ktube_post->post_status ) {
			return $ktube_configured;
		}
	}
	$ktube_query = new WP_Query(
		array(
			'post_type'      => 'page',
			'post_status'    => 'publish',
			'name'           => $slug,
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
 * Permalink for the resolved compliance page, or '' when none resolves.
 *
 * @param string $slug One of ktube_compliance_kinds() keys.
 * @return string
 */
function ktube_get_compliance_page_url( string $slug ): string {
	$ktube_page_id = ktube_resolve_compliance_page_id( $slug );
	if ( ! $ktube_page_id ) {
		return '';
	}
	return (string) get_permalink( $ktube_page_id );
}

/**
 * Default heading echo when an operator leaves the page title blank.
 * The Page title (operator-supplied via the editor) ALWAYS takes
 * precedence; this is purely a fallback for blank titles.
 *
 * NEVER used as authoritative legal text.
 *
 * @param string $slug
 * @return string
 */
function ktube_compliance_default_heading( string $slug ): string {
	$ktube_kinds = ktube_compliance_kinds();
	if ( ! isset( $ktube_kinds[ $slug ] ) ) {
		return '';
	}
	return (string) $ktube_kinds[ $slug ]['label'];
}

/**
 * 4-slot compliance nav for the footer.
 *
 * Order: Privacy → 2257 → DMCA → Terms (canonical disclosure order
 * from brief §3.8). Slots whose page does not resolve are skipped.
 * Privacy slot is included whenever its PAGE resolves, EXCEPT when
 * the Phase 7b privacy badge is also rendered (age-gate active + page
 * resolves) — in that case the badge is the primary disclosure link
 * and the nav slot is suppressed to avoid duplicate Display to the
 * same URL. The badge and the nav are separate surfaces with
 * deliberately-separate gates.
 *
 * Filterable so MU-plugins can append jurisdiction-specific entries
 * (e.g. `gdpr`, `eu_cookie_law`) without touching the theme code.
 *
 * @return array<int,array{slug:string,label:string,url:string}>
 */
function ktube_get_compliance_footer_slots(): array {
	$ktube_kinds = ktube_compliance_kinds();
	$ktube_order = array( 'privacy', '2257', 'dmca', 'terms' );
	$ktube_slots = array();
	foreach ( $ktube_order as $ktube_slug ) {
		if ( 'privacy' === $ktube_slug ) {
			if ( ! function_exists( 'ktube_get_privacy_page_url' ) ) {
				continue;
			}
			$ktube_url = ktube_get_privacy_page_url();
			if ( '' === $ktube_url ) {
				continue;
			}
			// Phase 7b badge already surfaces the privacy page when
			// age-gate is active; suppress the nav slot in that case to
			// avoid rendering two copies of the same link target.
			if ( function_exists( 'ktube_should_show_privacy_badge' ) && ktube_should_show_privacy_badge() ) {
				continue;
			}
			$ktube_slots[] = array(
				'slug'  => 'privacy',
				'label' => __( 'Privacy', 'ktube' ),
				'url'   => $ktube_url,
			);
			continue;
		}
		if ( ! isset( $ktube_kinds[ $ktube_slug ] ) ) {
			continue;
		}
		$ktube_url = ktube_get_compliance_page_url( $ktube_slug );
		if ( '' === $ktube_url ) {
			continue;
		}
		$ktube_slots[] = array(
			'slug'  => $ktube_slug,
			'label' => $ktube_kinds[ $ktube_slug ]['label'],
			'url'   => $ktube_url,
		);
	}

	/**
	 * Filter the compliance footer nav slots.
	 *
	 * @param array<int,array{slug:string,label:string,url:string}> $ktube_slots
	 */
	return (array) apply_filters( 'ktube_compliance_footer_slots', $ktube_slots );
}
