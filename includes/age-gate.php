<?php
/**
 * Phase 6 — Age gate + Restricted to Adults (RTA) meta tag.
 *
 * Both features are OFF by default. Operators must opt-in via Customizer.
 *
 * Customizer settings (theme_mods, all default OFF):
 *   - ktube_age_gate_enabled         (bool,   default false)
 *   - ktube_age_gate_min_age         (int 1-99, default 18)
 *   - ktube_age_gate_duration_days   (int 1-365, default 30)
 *   - ktube_age_gate_redirect_url    (URL,    default 'https://www.google.com/')
 *   - ktube_rta_enabled              (bool,   default false)
 *
 * Output:
 *   - ktube_render_rta_meta — emits <meta name="rating" content="RTA-5042-1996-1400-1577-RTA">
 *     in <head> when ktube_rta_enabled is true. Independent of the age gate.
 *     The label format matches the ASACP RTA register so RTA-aware
 *     browsers/extensions recognize the site as Restricted to Adults.
 *   - ktube_enqueue_age_gate — enqueues dist/js/age-gate.js on the front-end
 *     only when ktube_age_gate_enabled is true and the dist file exists.
 *     Skips admin/REST/Cron/AJAX preview iframes. Localizes
 *     window.ktubeAgeGateData with enabled/minAge/durationDays/redirectUrl.
 *
 * Privacy: only stores a unix timestamp under a non-personalized key.
 *
 * @package ktube
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * ktube_register_age_gate — init hook loader.
 */
function ktube_register_age_gate(): void {
	add_action( 'customize_register', 'ktube_age_gate_customize_register' );
	// priority -2 fires before ktube_inline_theme_bootstrap (prio -1) so the
	// gate's pre-paint hide lands FIRST; theme default + age-gate confirm
	// then proceed against an already-hidden body.
	add_action( 'wp_head', 'ktube_inline_age_gate_bootstrap', -2 );
	add_action( 'wp_head', 'ktube_render_rta_meta', 0 );
	add_action( 'wp_enqueue_scripts', 'ktube_enqueue_age_gate' );
}

/**
 * Register age-gate + RTA settings.
 *
 * @param WP_Customize_Manager $wp_customize
 */
function ktube_age_gate_customize_register( WP_Customize_Manager $wp_customize ): void {
	$wp_customize->add_section(
		'ktube_age_gate',
		array(
			'title'       => __( 'ktube — Age gate', 'ktube' ),
			'description' => __( 'Front-end first-visit gate. Both features default OFF and only fire on the public site — never in admin / preview / REST.', 'ktube' ),
			'priority'    => 33,
		)
	);

	$wp_customize->add_setting(
		'ktube_age_gate_enabled',
		array(
			'default'           => false,
			'type'              => 'theme_mod',
			'capability'        => 'edit_theme_options',
			'transport'         => 'refresh',
			'sanitize_callback' => static function ( $v ): bool {
				return (bool) $v;
			},
		)
	);
	$wp_customize->add_control(
		'ktube_age_gate_enabled',
		array(
			'label'       => __( 'Enable age gate', 'ktube' ),
			'description' => __( 'When ON, first-visit browsers see a modal asking them to confirm they are of legal age before viewing the site.', 'ktube' ),
			'section'     => 'ktube_age_gate',
			'type'        => 'checkbox',
		)
	);

	$wp_customize->add_setting(
		'ktube_age_gate_min_age',
		array(
			'default'           => 18,
			'type'              => 'theme_mod',
			'capability'        => 'edit_theme_options',
			'transport'         => 'refresh',
			'sanitize_callback' => static function ( $v ): int {
				return ktube_sanitize_cols( $v, 1, 99 );
			},
		)
	);
	$wp_customize->add_control(
		'ktube_age_gate_min_age',
		array(
			'label'       => __( 'Minimum age', 'ktube' ),
			'description' => __( '1–99. Displayed in the modal copy and exposed as window.ktubeAgeGateData.minAge.', 'ktube' ),
			'section'     => 'ktube_age_gate',
			'type'        => 'number',
			'input_attrs' => array(
				'min'  => 1,
				'max'  => 99,
				'step' => 1,
			),
		)
	);

	$wp_customize->add_setting(
		'ktube_age_gate_duration_days',
		array(
			'default'           => 30,
			'type'              => 'theme_mod',
			'capability'        => 'edit_theme_options',
			'transport'         => 'refresh',
			'sanitize_callback' => static function ( $v ): int {
				return ktube_sanitize_cols( $v, 1, 365 );
			},
		)
	);
	$wp_customize->add_control(
		'ktube_age_gate_duration_days',
		array(
			'label'       => __( 'Verification persistence (days)', 'ktube' ),
			'description' => __( 'How long a browser remembers its confirmation. 1–365.', 'ktube' ),
			'section'     => 'ktube_age_gate',
			'type'        => 'number',
			'input_attrs' => array(
				'min'  => 1,
				'max'  => 365,
				'step' => 1,
			),
		)
	);

	$wp_customize->add_setting(
		'ktube_age_gate_redirect_url',
		array(
			'default'           => 'https://www.google.com/',
			'type'              => 'theme_mod',
			'capability'        => 'edit_theme_options',
			'transport'         => 'refresh',
			'sanitize_callback' => static function ( $v ): string {
				$v = is_string( $v ) ? trim( $v ) : '';
				if ( '' === $v ) {
					return 'https://www.google.com/';
				}
				return esc_url_raw( $v );
			},
		)
	);
	$wp_customize->add_control(
		'ktube_age_gate_redirect_url',
		array(
			'label'       => __( 'Underage redirect URL', 'ktube' ),
			'description' => __( 'Where to send visitors who declare they are under the minimum age.', 'ktube' ),
			'section'     => 'ktube_age_gate',
			'type'        => 'url',
		)
	);

	$wp_customize->add_setting(
		'ktube_rta_enabled',
		array(
			'default'           => false,
			'type'              => 'theme_mod',
			'capability'        => 'edit_theme_options',
			'transport'         => 'refresh',
			'sanitize_callback' => static function ( $v ): bool {
				return (bool) $v;
			},
		)
	);
	$wp_customize->add_control(
		'ktube_rta_enabled',
		array(
			'label'       => __( 'Emit RTA meta tag', 'ktube' ),
			'description' => __( 'Emit <meta name="rating" content="RTA-5042-1996-1400-1577-RTA"> in <head>. Independent of the age gate — useful when relying on RTA-aware browsers/extensions to act on the label. Format matches the ASACP RTA register.', 'ktube' ),
			'section'     => 'ktube_age_gate',
			'type'        => 'checkbox',
		)
	);
}

/**
 * Render the RTA meta tag. Independent of age gate; once ON it prints on
 * every front-end <head> including the age-gate modal page.
 */
function ktube_render_rta_meta(): void {
	if ( ! get_theme_mod( 'ktube_rta_enabled', false ) ) {
		return;
	}
	echo '<meta name="rating" content="RTA-5042-1996-1400-1577-RTA">' . "\n";
}

/**
 * Enqueue the age-gate JS bundle. Front-end only — never admin, REST, cron,
 * AJAX, or the Customizer preview iframe.
 */
function ktube_enqueue_age_gate(): void {
	if ( ! get_theme_mod( 'ktube_age_gate_enabled', false ) ) {
		return;
	}
	if ( is_admin() || wp_doing_ajax() || wp_doing_cron() || ( defined( 'REST_REQUEST' ) && REST_REQUEST ) ) {
		return;
	}
	$ktube_js = KTUBE_DIR . '/assets/js/age-gate.js';
	if ( ! file_exists( $ktube_js ) ) {
		return;
	}
	wp_enqueue_script(
		'ktube-age-gate',
		KTUBE_URI . '/assets/js/age-gate.js',
		array(),
		KTUBE_VERSION,
		true
	);
	wp_add_inline_script(
		'ktube-age-gate',
		'window.ktubeAgeGateData = ' . wp_json_encode(
			array(
				'enabled'      => true,
				'minAge'       => (int) get_theme_mod( 'ktube_age_gate_min_age', 18 ),
				'durationDays' => (int) get_theme_mod( 'ktube_age_gate_duration_days', 30 ),
				'redirectUrl'  => (string) get_theme_mod( 'ktube_age_gate_redirect_url', 'https://www.google.com/' ),
				'storageKey'   => 'ktube-age-confirmed-on',
				'cookieName'   => 'ktube_age_verified',
			)
		) . ';',
		'before'
	);
}

/**
 * Public read-only helper for plugins / dev tooling that want to gate on the
 * same theme mods without duplicating the lookup.
 */
function ktube_age_gate_active(): bool {
	return (bool) get_theme_mod( 'ktube_age_gate_enabled', false );
}

/**
 * ktube_inline_age_gate_bootstrap — pre-paint hide for the age gate.
 *
 * Runs at wp_head priority -2 (before theme-bootstrap at -1). If the gate is
 * enabled, emits an inline <script> + <style> that:
 *   1. Marks <html data-ktube-age-gate="pending"> so the CSS can hide body
 *      content before stylesheet + JS bundle finish parsing.
 *   2. Sets data-ktube-age-gate="ready" if the same-tab localStorage slot
 *      already shows the visitor has confirmed during this browser session.
 *
 * The TTL is read from ktube_age_gate_duration_days so the inline check uses
 * the same window as age-gate.js (avoids drift between the two layers).
 *
 * age-gate.js strips data-ktube-age-gate="pending" when it has either:
 *   - resolved the visitor as verified (no modal needed), or
 *   - finished mounting the modal (visibility:hidden lifts via <dialog>).
 */
function ktube_inline_age_gate_bootstrap(): void {
	if ( ! ktube_age_gate_active() ) {
		return;
	}
	$ktube_storage  = 'ktube-age-confirmed-on';
	$ktube_ttl_days = (int) get_theme_mod( 'ktube_age_gate_duration_days', 30 );
	$ktube_ttl_ms   = $ktube_ttl_days * 86400 * 1000;
	?>
	<script>
		(function () {
			try {
				var ttlMs = <?php echo (int) $ktube_ttl_ms; ?>;
				var stored = localStorage.getItem('<?php echo esc_js( $ktube_storage ); ?>');
				if (stored) {
					var ts = parseInt(stored, 10);
					if (Number.isFinite(ts) && (Date.now() - ts) < ttlMs) {
						document.documentElement.setAttribute('data-ktube-age-gate', 'ready');
						return;
					}
				}
			} catch (_) {}
			document.documentElement.setAttribute('data-ktube-age-gate', 'pending');
		})();
	</script>
	<style id="ktube-age-gate-pending">
		html[data-ktube-age-gate="pending"] body { visibility: hidden; }
		html[data-ktube-age-gate="ready"]  body { visibility: visible; }
	</style>
	<?php
}
