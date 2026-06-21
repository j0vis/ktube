<?php
/**
 * Theme Customizer — per-breakpoint layout + color suite + persistent dark-mode toggle.
 *
 * Settings (all postMessage transport except theme_default which is refresh):
 *   - ktube_grid_cols_desktop (2-6, default 4)   — archive grid ≥1025px viewport.
 *   - ktube_grid_cols_tablet  (1-4, default 3)   — 641-1024px viewport.
 *   - ktube_grid_cols_mobile  (1-3, default 2)   — ≤640px viewport.
 *   - ktube_thumb_cols_desktop (2-4, default 3)  — single-photo gallery ≥1025px.
 *   - ktube_thumb_cols_mobile  (1-3, default 2)  — single-photo gallery ≤640px.
 *   - ktube_color_{bg,text,accent,link}_{light,dark} (hex). 8 tokens.
 *   - ktube_theme_default ('auto' | 'light' | 'dark', default 'auto').
 *
 * Render: ktube_print_inline_customizer_vars builds one CSS string and pipes it
 * through wp_add_inline_style('ktube-main', $css). WordPress emits a
 * <style id="ktube-main-inline-css"> right after the linked ktube-main
 * `<link>` tag, so :root vars win source-order over assets/css/main.css's
 * hand-authored token defaults without specificity tricks. The CSS also
 * contains the `@media (max-width: 1024px)` and `@media (max-width: 640px)`
 * viewport breakpoints that swap the desktop vars to tablet/mobile — so
 * assets/css/main.css no longer carries those rules.
 *
 * Live preview: assets/js/customize-controls.js mirrors the same CSS structure
 * and rewrites the <style id="ktube-main-inline-css"> textContent whenever a
 * postMessage fires.
 *
 * Phase 7b — PHP-emitted per-setting SHA-256 fingerprints vs JS-side rebuild
 * of the same substrings. If wp.customize() bound a drift without rebuilding
 * (e.g. CSS fork without JS update) the JS console surfaces it immediately.
 *
 * @package ktube
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

function ktube_theme_default_choices(): array {
	return array( 'auto', 'light', 'dark' );
}

function ktube_sanitize_hex( string $color ): string {
	$color = trim( $color );
	if ( '' === $color ) {
		return '';
	}
	if ( preg_match( '/^#([a-f0-9]{3}|[a-f0-9]{6})$/i', $color ) ) {
		if ( 4 === strlen( $color ) ) {
			$r = $color[1];
			$g = $color[2];
			$b = $color[3];
			$color = '#' . $r . $r . $g . $g . $b . $b;
		}
		return strtolower( $color );
	}
	return '';
}

function ktube_sanitize_cols( $value, int $min, int $max ): int {
	$value = is_numeric( $value ) ? (int) $value : $min;
	return max( $min, min( $max, $value ) );
}

function ktube_sanitize_theme_default( $value ): string {
	$value = is_string( $value ) ? sanitize_key( $value ) : 'auto';
	return in_array( $value, ktube_theme_default_choices(), true ) ? $value : 'auto';
}

/**
 * ktube_register_customizer — init-hook loader.
 */
function ktube_register_customizer(): void {
	add_action( 'customize_register', 'ktube_customize_register' );
	// Phase 8-A 2026-06-21: editorial H1 + meta-description for the site
	// homepage. Registered as a SEPARATE callback so future homepage
	// sub-panels stay additive (a section split inside one callback would
	// bloat the file). Both callbacks fire on `customize_register` in
	// insertion order.
	add_action( 'customize_register', 'ktube_customize_register_homepage' );
	// priority 20 ensures ktube-main is enqueued (default 10) before we
	// attach the inline CSS. wp_print_styles fires inside wp_head; failing
	// to register first silently no-ops the inline block.
	add_action( 'wp_enqueue_scripts', 'ktube_print_inline_customizer_vars', 20 );
	// priority 1 so lt;meta name="description">gt; wins source-order over
	// later plugins that emit their own. Skipped on non-home requests.
	add_action( 'wp_head', 'ktube_render_home_meta_description', 1 );
}

/**
 * Register Customizer settings, sections, and controls.
 *
 * @param WP_Customize_Manager $wp_customize
 */
function ktube_customize_register( WP_Customize_Manager $wp_customize ): void {
	$wp_customize->add_section(
		'ktube_layout',
		array(
			'title'       => __( 'ktube — Layout', 'ktube' ),
			'description' => __( 'Per-breakpoint grid + gallery thumbnail columns.', 'ktube' ),
			'priority'    => 30,
		)
	);

	$ktube_grid_settings = array(
		'ktube_grid_cols_desktop' => array(
			'label' => __( 'Archive grid columns (desktop, ≥1025px)', 'ktube' ),
			'min'   => 2,
			'max'   => 6,
			'step'  => 1,
			'default' => 4,
		),
		'ktube_grid_cols_tablet' => array(
			'label' => __( 'Archive grid columns (tablet, 641–1024px)', 'ktube' ),
			'min'   => 1,
			'max'   => 4,
			'step'  => 1,
			'default' => 3,
		),
		'ktube_grid_cols_mobile' => array(
			'label' => __( 'Archive grid columns (mobile, ≤640px)', 'ktube' ),
			'min'   => 1,
			'max'   => 3,
			'step'  => 1,
			'default' => 2,
		),
	);
	foreach ( $ktube_grid_settings as $ktube_id => $ktube_config ) {
		$wp_customize->add_setting(
			$ktube_id,
			array(
				'default'           => $ktube_config['default'],
				'type'              => 'theme_mod',
				'capability'        => 'edit_theme_options',
				'transport'         => 'postMessage',
				'sanitize_callback' => static function ( $v ) use ( $ktube_config ): int {
					return ktube_sanitize_cols( $v, $ktube_config['min'], $ktube_config['max'] );
				},
			)
		);
		$wp_customize->add_control(
			$ktube_id,
			array(
				'label'       => $ktube_config['label'],
				'section'     => 'ktube_layout',
				'type'        => 'number',
				'input_attrs' => array(
					'min'  => $ktube_config['min'],
					'max'  => $ktube_config['max'],
					'step' => $ktube_config['step'],
				),
			)
		);
	}

	$ktube_thumb_settings = array(
		'ktube_thumb_cols_desktop' => array(
			'label' => __( 'Photo-gallery columns (desktop, ≥1025px)', 'ktube' ),
			'min'   => 2,
			'max'   => 4,
			'step'  => 1,
			'default' => 3,
		),
		'ktube_thumb_cols_mobile' => array(
			'label' => __( 'Photo-gallery columns (mobile, ≤640px)', 'ktube' ),
			'min'   => 1,
			'max'   => 3,
			'step'  => 1,
			'default' => 2,
		),
	);
	foreach ( $ktube_thumb_settings as $ktube_id => $ktube_config ) {
		$wp_customize->add_setting(
			$ktube_id,
			array(
				'default'           => $ktube_config['default'],
				'type'              => 'theme_mod',
				'capability'        => 'edit_theme_options',
				'transport'         => 'postMessage',
				'sanitize_callback' => static function ( $v ) use ( $ktube_config ): int {
					return ktube_sanitize_cols( $v, $ktube_config['min'], $ktube_config['max'] );
				},
			)
		);
		$wp_customize->add_control(
			$ktube_id,
			array(
				'label'       => $ktube_config['label'],
				'section'     => 'ktube_layout',
				'type'        => 'number',
				'input_attrs' => array(
					'min'  => $ktube_config['min'],
					'max'  => $ktube_config['max'],
					'step' => $ktube_config['step'],
				),
			)
		);
	}

	$wp_customize->add_section(
		'ktube_colors',
		array(
			'title'       => __( 'ktube — Colors', 'ktube' ),
			'description' => __( 'Live WCAG contrast feedback appears next to each picker.', 'ktube' ),
			'priority'    => 31,
		)
	);

	$ktube_light_tokens = array(
		'ktube_color_bg_light'     => __( 'Background (light)', 'ktube' ),
		'ktube_color_text_light'   => __( 'Text (light)', 'ktube' ),
		'ktube_color_accent_light' => __( 'Accent (light)', 'ktube' ),
		'ktube_color_link_light'   => __( 'Link (light)', 'ktube' ),
	);
	$ktube_light_defaults = array(
		'ktube_color_bg_light'     => '#ffffff',
		'ktube_color_text_light'   => '#18181b',
		'ktube_color_accent_light' => '#db2777',
		'ktube_color_link_light'   => '#2563eb',
	);
	foreach ( $ktube_light_tokens as $ktube_id => $ktube_label ) {
		$wp_customize->add_setting(
			$ktube_id,
			array(
				'default'           => $ktube_light_defaults[ $ktube_id ],
				'type'              => 'theme_mod',
				'capability'        => 'edit_theme_options',
				'transport'         => 'postMessage',
				'sanitize_callback' => 'ktube_sanitize_hex',
			)
		);
		$wp_customize->add_control(
			new WP_Customize_Color_Control(
				$wp_customize,
				$ktube_id,
				array(
					'label'       => $ktube_label,
					'section'     => 'ktube_colors',
					'description' => __( 'Garish or unreadable here will flag WCAG contrast in real time.', 'ktube' ),
				)
			)
		);
	}

	$ktube_dark_tokens = array(
		'ktube_color_bg_dark'     => __( 'Background (dark)', 'ktube' ),
		'ktube_color_text_dark'   => __( 'Text (dark)', 'ktube' ),
		'ktube_color_accent_dark' => __( 'Accent (dark)', 'ktube' ),
		'ktube_color_link_dark'   => __( 'Link (dark)', 'ktube' ),
	);
	$ktube_dark_defaults = array(
		'ktube_color_bg_dark'     => '#0e0e10',
		'ktube_color_text_dark'   => '#e4e4e7',
		'ktube_color_accent_dark' => '#f472b6',
		'ktube_color_link_dark'   => '#60a5fa',
	);
	foreach ( $ktube_dark_tokens as $ktube_id => $ktube_label ) {
		$wp_customize->add_setting(
			$ktube_id,
			array(
				'default'           => $ktube_dark_defaults[ $ktube_id ],
				'type'              => 'theme_mod',
				'capability'        => 'edit_theme_options',
				'transport'         => 'postMessage',
				'sanitize_callback' => 'ktube_sanitize_hex',
			)
		);
		$wp_customize->add_control(
			new WP_Customize_Color_Control(
				$wp_customize,
				$ktube_id,
				array(
					'label'       => $ktube_label,
					'section'     => 'ktube_colors',
				)
			)
		);
	}

	$wp_customize->add_section(
		'ktube_dark_mode',
		array(
			'title'       => __( 'ktube — Dark mode', 'ktube' ),
			'description' => __( 'Persistent default for new visitors. Returning visitors keep their last toggle choice via localStorage.', 'ktube' ),
			'priority'    => 32,
		)
	);

	$wp_customize->add_setting(
		'ktube_theme_default',
		array(
			'default'           => 'auto',
			'type'              => 'theme_mod',
			'capability'        => 'edit_theme_options',
			'transport'         => 'refresh',
			'sanitize_callback' => 'ktube_sanitize_theme_default',
		)
	);
	$wp_customize->add_control(
		'ktube_theme_default',
		array(
			'label'       => __( 'Default theme for new visitors', 'ktube' ),
			'description' => __( 'Auto = OS preference. Light/Dark = force.', 'ktube' ),
			'section'     => 'ktube_dark_mode',
			'type'        => 'radio',
			'choices'     => array(
				'auto'  => __( 'Auto (follow OS)', 'ktube' ),
				'light' => __( 'Light', 'ktube' ),
				'dark'  => __( 'Dark', 'ktube' ),
			),
		)
	);
}

/**
 * Build the inline CSS for the ktube-main handle. Identical structure to
 * customize-controls.js::buildCss() so live previews stay byte-identical.
 *
 * @return string
 */
function ktube_build_customizer_css(): string {
	// Sanitize on read as well — sanitize_callback at save time is the
	// primary guard, but if a theme_mod gets set via MU-plugin or direct
	// DB write we still want the inline CSS to be range-clamped. Defense
	// in depth prevents an out-of-range grid-cols value from emitting a
	// broken `:root` block.
	$ktube_grid_d  = ktube_sanitize_cols( get_theme_mod( 'ktube_grid_cols_desktop',  4 ), 2, 6 );
	$ktube_grid_t  = ktube_sanitize_cols( get_theme_mod( 'ktube_grid_cols_tablet',   3 ), 1, 4 );
	$ktube_grid_m  = ktube_sanitize_cols( get_theme_mod( 'ktube_grid_cols_mobile',   2 ), 1, 3 );
	$ktube_thumb_d = ktube_sanitize_cols( get_theme_mod( 'ktube_thumb_cols_desktop', 3 ), 2, 4 );
	$ktube_thumb_m = ktube_sanitize_cols( get_theme_mod( 'ktube_thumb_cols_mobile',  2 ), 1, 3 );

	$ktube_color_defaults = array(
		'ktube_color_bg_light'     => '#ffffff',
		'ktube_color_text_light'   => '#18181b',
		'ktube_color_accent_light' => '#db2777',
		'ktube_color_link_light'   => '#2563eb',
		'ktube_color_bg_dark'      => '#0e0e10',
		'ktube_color_text_dark'    => '#e4e4e7',
		'ktube_color_accent_dark'  => '#f472b6',
		'ktube_color_link_dark'    => '#60a5fa',
	);
	$ktube_vars = array();
	foreach ( $ktube_color_defaults as $ktube_id => $ktube_default ) {
		$ktube_value = ktube_sanitize_hex( (string) get_theme_mod( $ktube_id, $ktube_default ) );
		$ktube_vars[ $ktube_id ] = $ktube_value ?: $ktube_default;
	}

	$ktube_lines   = array();
	$ktube_lines[] = ':root {';
	// No space before token values — kept in lock-step with
	// ktube_settings_substring() (no-space shape for checksum extraction)
	// + customize-controls.js::ktubeCpBuildCss() (no-space mirror). This
	// keeps the Phase 7b per-key SHA checksum guard honest: the
	// substring returned from PHP is now extractable verbatim from
	// build_css() output by the JS regex mirror (was previously drifting
	// because of the leading space).
	$ktube_lines[] = "\t--ktube-grid-cols-desktop:" . $ktube_grid_d . ';';
	$ktube_lines[] = "\t--ktube-grid-cols-tablet:"  . $ktube_grid_t . ';';
	$ktube_lines[] = "\t--ktube-grid-cols-mobile:"  . $ktube_grid_m . ';';
	$ktube_lines[] = "\t--ktube-thumb-cols-desktop:" . $ktube_thumb_d . ';';
	$ktube_lines[] = "\t--ktube-thumb-cols-mobile:"  . $ktube_thumb_m . ';';
	$ktube_lines[] = '}';
	$ktube_lines[] = ':root[data-theme="light"], :root:not([data-theme]) {';
	$ktube_lines[] = "\t--ktube-color-bg:"     . $ktube_vars['ktube_color_bg_light']     . ';';
	$ktube_lines[] = "\t--ktube-color-text:"   . $ktube_vars['ktube_color_text_light']   . ';';
	$ktube_lines[] = "\t--ktube-color-accent:" . $ktube_vars['ktube_color_accent_light'] . ';';
	$ktube_lines[] = "\t--ktube-color-link:"   . $ktube_vars['ktube_color_link_light']   . ';';
	$ktube_lines[] = '}';
	$ktube_lines[] = ':root[data-theme="dark"] {';
	$ktube_lines[] = "\t--ktube-color-bg:"     . $ktube_vars['ktube_color_bg_dark']      . ';';
	$ktube_lines[] = "\t--ktube-color-text:"   . $ktube_vars['ktube_color_text_dark']    . ';';
	$ktube_lines[] = "\t--ktube-color-accent:" . $ktube_vars['ktube_color_accent_dark']  . ';';
	$ktube_lines[] = "\t--ktube-color-link:"   . $ktube_vars['ktube_color_link_dark']    . ';';
	$ktube_lines[] = '}';
	$ktube_lines[] = '@media (min-width: 641px) and (max-width: 1024px) {';
	$ktube_lines[] = "\t:root {";
	$ktube_lines[] = "\t\t--ktube-grid-cols-desktop: var(--ktube-grid-cols-tablet);";
	$ktube_lines[] = "\t\t--ktube-thumb-cols: var(--ktube-thumb-cols-desktop);";
	$ktube_lines[] = "\t}";
	$ktube_lines[] = '}';
	$ktube_lines[] = '@media (max-width: 640px) {';
	$ktube_lines[] = "\t:root {";
	$ktube_lines[] = "\t\t--ktube-grid-cols-desktop: var(--ktube-grid-cols-mobile);";
	$ktube_lines[] = "\t\t--ktube-thumb-cols: var(--ktube-thumb-cols-mobile);";
	$ktube_lines[] = "\t}";
	$ktube_lines[] = '}';
	return implode( "\n", $ktube_lines ) . "\n";
}

/**
 * Compose the substring of ktube_build_customizer_css() that a single setting
 * owns. The JS-side mirror in customize-controls.js computes the same
 * substring so SHA-256(per-key) matches when both sides agree.
 *
 * @param string $ktube_id    Setting id (ktube_*).
 * @param mixed  $ktube_value Sanitized value.
 * @return string
 */
function ktube_settings_substring( string $ktube_id, $ktube_value ): string {
	switch ( $ktube_id ) {
		case 'ktube_grid_cols_desktop':  return '--ktube-grid-cols-desktop:' . (int) $ktube_value . ';';
		case 'ktube_grid_cols_tablet':   return '--ktube-grid-cols-tablet:'  . (int) $ktube_value . ';';
		case 'ktube_grid_cols_mobile':   return '--ktube-grid-cols-mobile:'  . (int) $ktube_value . ';';
		case 'ktube_thumb_cols_desktop': return '--ktube-thumb-cols-desktop:' . (int) $ktube_value . ';';
		case 'ktube_thumb_cols_mobile':  return '--ktube-thumb-cols-mobile:'  . (int) $ktube_value . ';';
		case 'ktube_color_bg_light':     return '--ktube-color-bg:'     . (string) $ktube_value . ';|@light';
		case 'ktube_color_text_light':   return '--ktube-color-text:'   . (string) $ktube_value . ';|@light';
		case 'ktube_color_accent_light': return '--ktube-color-accent:' . (string) $ktube_value . ';|@light';
		case 'ktube_color_link_light':   return '--ktube-color-link:'   . (string) $ktube_value . ';|@light';
		case 'ktube_color_bg_dark':      return '--ktube-color-bg:'     . (string) $ktube_value . ';|@dark';
		case 'ktube_color_text_dark':    return '--ktube-color-text:'   . (string) $ktube_value . ';|@dark';
		case 'ktube_color_accent_dark':  return '--ktube-color-accent:' . (string) $ktube_value . ';|@dark';
		case 'ktube_color_link_dark':    return '--ktube-color-link:'   . (string) $ktube_value . ';|@dark';
	}
	return '';
}

/**
 * Compute SHA-256 fingerprints exposed as window.ktubeCustomizerSettingChecksums
 * on the Customizer preview iframe. JS-side rebuild of the same substrings
 * must hash identically or console.warn fires the diverged setting(s). This
 * is the Phase 7b CSS checksum guard (to-do.md §3 nit).
 *
 * @return array{total:string, per_key:array<string,string>}
 */
function ktube_compute_customizer_checksums(): array {
	$ktube_settings = array(
		'ktube_grid_cols_desktop'  => (int) get_theme_mod( 'ktube_grid_cols_desktop', 4 ),
		'ktube_grid_cols_tablet'   => (int) get_theme_mod( 'ktube_grid_cols_tablet',  3 ),
		'ktube_grid_cols_mobile'   => (int) get_theme_mod( 'ktube_grid_cols_mobile',  2 ),
		'ktube_thumb_cols_desktop' => (int) get_theme_mod( 'ktube_thumb_cols_desktop', 3 ),
		'ktube_thumb_cols_mobile'  => (int) get_theme_mod( 'ktube_thumb_cols_mobile',  2 ),
		'ktube_color_bg_light'     => ktube_sanitize_hex( (string) get_theme_mod( 'ktube_color_bg_light',     '#ffffff' ) ) ?: '#ffffff',
		'ktube_color_text_light'   => ktube_sanitize_hex( (string) get_theme_mod( 'ktube_color_text_light',   '#18181b' ) ) ?: '#18181b',
		'ktube_color_accent_light' => ktube_sanitize_hex( (string) get_theme_mod( 'ktube_color_accent_light', '#db2777' ) ) ?: '#db2777',
		'ktube_color_link_light'   => ktube_sanitize_hex( (string) get_theme_mod( 'ktube_color_link_light',   '#2563eb' ) ) ?: '#2563eb',
		'ktube_color_bg_dark'      => ktube_sanitize_hex( (string) get_theme_mod( 'ktube_color_bg_dark',      '#0e0e10' ) ) ?: '#0e0e10',
		'ktube_color_text_dark'    => ktube_sanitize_hex( (string) get_theme_mod( 'ktube_color_text_dark',    '#e4e4e7' ) ) ?: '#e4e4e7',
		'ktube_color_accent_dark'  => ktube_sanitize_hex( (string) get_theme_mod( 'ktube_color_accent_dark',  '#f472b6' ) ) ?: '#f472b6',
		'ktube_color_link_dark'    => ktube_sanitize_hex( (string) get_theme_mod( 'ktube_color_link_dark',    '#60a5fa' ) ) ?: '#60a5fa',
	);
	$ktube_per_key = array();
	foreach ( $ktube_settings as $ktube_id => $ktube_value ) {
		$ktube_per_key[ $ktube_id ] = hash( 'sha256', ktube_settings_substring( $ktube_id, $ktube_value ) );
	}
	return array(
		'total'   => hash( 'sha256', ktube_build_customizer_css() ),
		'per_key' => $ktube_per_key,
	);
}

/**
 * Pipe the customizer CSS into the ktube-main handle so it loads in <head>
 * immediately after the linked <link rel="ktube-main">.
 *
 * No-op unless ktube-main is BOTH registered AND enqueued. Belt-and-
 * suspenders: in WP semantics enqueue implies registration, so checking
 * both is redundant at runtime — but the explicit dual gate documents
 * intent and makes the test (tests/phpunit/ktube/PrintInlineCustomizerVarsTest)
 * able to simulate the MU-plugin dequeue case where the handle is
 * registered but wp_dequeue_style('ktube-main') drops the enqueue, which
 * would otherwise emit an orphan <style> block against an unreferenced
 * token set. (to-do.md §5.3 nit — closed 2026-06-21)
 *
 * Phase 0-A 2026-06-21: ktube-main is enqueued from includes/setup.php at
 * the assets/css/main.css path. The dual-gate test still applies to
 * exact-path-unchanged wp_style_is handle lookups, so no further changes
 * were needed here.
 */
function ktube_print_inline_customizer_vars(): void {
	if ( ! wp_style_is( 'ktube-main', 'registered' ) || ! wp_style_is( 'ktube-main', 'enqueued' ) ) {
		return;
	}
	wp_add_inline_style( 'ktube-main', ktube_build_customizer_css() );
}

/**
 * Phase 8-A 2026-06-21 — Homepage editorial sub-panel.
 *
 * Two controls:
 *   - ktube_home_h1          (text, default '') — H1 on the site homepage.
 *                                       Falls back to wp_title() when unset.
 *   - ktube_home_description (textarea, default '') — Lead paragraph AND
 *                                       lt;meta name="description">gt; on the
 *                                       site homepage.
 *
 * Why one section for two controls: each would otherwise own a one-line
 * panel. Co-locating also makes the operator mental model obvious —
 * "edit the homepage copy here."
 *
 * @param WP_Customize_Manager $wp_customize
 */
function ktube_customize_register_homepage( WP_Customize_Manager $wp_customize ): void {
	$wp_customize->add_section(
		'ktube_homepage',
		array(
			'title'       => __( 'ktube — Homepage', 'ktube' ),
			/* translators: %s: section contextual help — keep brief */
			'description' => __( 'Editorial H1 + lead paragraph for the site homepage. The same description also serves as the SEO &lt;meta name="description"&gt; on the homepage request.', 'ktube' ),
			'priority'    => 36,
		)
	);

	$wp_customize->add_setting(
		'ktube_home_h1',
		array(
			'default'           => '',
			'type'              => 'theme_mod',
			'capability'        => 'edit_theme_options',
			'transport'         => 'refresh',
			'sanitize_callback' => static function ( $v ): string {
				// sanitize_text_field strips tags + collapses whitespace;
				// intentional — H1 is one-line.
				return sanitize_text_field( (string) $v );
			},
		)
	);
	$wp_customize->add_control(
		'ktube_home_h1',
		array(
			'label'       => __( 'Homepage H1', 'ktube' ),
			'description' => __( 'Custom heading for the site homepage. Leave empty to fall back to the standard site title.', 'ktube' ),
			'section'     => 'ktube_homepage',
			'type'        => 'text',
		)
	);

	$wp_customize->add_setting(
		'ktube_home_description',
		array(
			'default'           => '',
			'type'              => 'theme_mod',
			'capability'        => 'edit_theme_options',
			'transport'         => 'refresh',
			'sanitize_callback' => static function ( $v ): string {
				// sanitize_textarea_field preserves line breaks but strips
				// tags + dangerous protocols.
				return sanitize_textarea_field( (string) $v );
			},
		)
	);
	$wp_customize->add_control(
		'ktube_home_description',
		array(
			'label'       => __( 'Homepage description', 'ktube' ),
			'description' => __( 'Lead paragraph on the homepage AND &lt;meta name="description"&gt; in &lt;head&gt;.', 'ktube' ),
			'section'     => 'ktube_homepage',
			'type'        => 'textarea',
		)
	);
}

/**
 * Phase 8-A 2026-06-21 — homepage editorial getters.
 *
 * Both default to '' so callers can chain `if ( '' !== $value )` without a
 * null check. The fallback chain to `wp_title()` / `get_bloginfo('description')`
 * lives in the template (index.php) where WP's conditional tags are
 * available; helpers stay minimal here so test surface is narrow.
 *
 * @return string
 */
function ktube_get_home_h1(): string {
	return (string) get_theme_mod( 'ktube_home_h1', '' );
}

/**
 * Phase 8-A 2026-06-21 — homepage description getter (lead text +
 * &lt;meta name="description"&gt;). Returns '' by default; callers gate on
 * empty to fall back to bloginfo('description') etc.
 *
 * @return string
 */
function ktube_get_home_description(): string {
	return (string) get_theme_mod( 'ktube_home_description', '' );
}

/**
 * Phase 8-A 2026-06-21 — emit &lt;meta name="description"&gt; on the
 * site homepage ONLY, sourced from ktube_get_home_description().
 *
 * Gating rules:
 *   1. Front page OR blog posts index only (`is_front_page() || is_home()`).
 *      Single-post / archive / search contexts intentionally omit the
 *      homepage description — WordPress emits description via Yoast/AIOSEO
 *      in those contexts and we don't want to stomp on those plugins.
 *   2. Non-empty description. Empty `ktube_home_description` falls
 *      through to plugin or no-description rendering.
 *
 * Hooked at wp_head priority 1 so operator output lands BEFORE later
 * plugins (which usually run at default 10).
 */
function ktube_render_home_meta_description(): void {
	if ( ! ( is_front_page() || is_home() ) ) {
		return;
	}
	$ktube_description = ktube_get_home_description();
	if ( '' === $ktube_description ) {
		return;
	}
	echo '<meta name="description" content="' . esc_attr( $ktube_description ) . "\">\n"; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped — esc_attr above.
}

/**
 * Note: ktube_enqueue_customize_controls() lives in includes/setup.php
 * (Phase 0-A 2026-06-21 reversal). The duplicate registration that
 * previously lived here — pointing at the pre-reversal
 * assets/dist/js/customize-controls.js + using ktube_dist_asset_version —
 * was removed because it would have silently enqueued the missing
 * distribution-tree file on `customize_preview_init` after the reversal,
 * causing the Customizer preview iframe to drop the live-preview wire.
 *
 * The Single Source of Truth for the Customizer iframe enqueue is the
 * function in includes/setup.php, which uses assets/js/customize-controls.js
 * and ktube_asset_version().
 */
