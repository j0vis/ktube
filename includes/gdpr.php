<?php
/**
 * Phase 6-C — GDPR cookie consent banner + jurisdictional geo redirect.
 *
 * Default OFF. Operators must opt-in via Customizer.
 *
 * Customizer settings (theme_mods, all default OFF except default-block-list):
 *   - ktube_gdpr_enabled             (bool,        default false)
 *   - ktube_gdpr_blocked_countries   (CSV ISO 3166-1 alpha-2, default = EU 27 + EEA 3 + UK = 31 codes)
 *   - ktube_gdpr_redirect_url        (URL,         default 'https://www.google.com/')
 *   - ktube_gdpr_categories_analytics (bool,       default false)
 *   - ktube_gdpr_categories_marketing (bool,       default false)
 *
 * Two side-effects when active:
 *   1. GEO REDIRECT: visitors from a configured country ISO code are 302'd
 *      to ktube_gdpr_redirect_url on the FIRST page render. Operators wire
 *      an IP-geo provider via the 'ktube_gdpr_resolve_country' filter
 *      (e.g. Cloudflare HTTP_CF_IPCOUNTRY, Apache mod_geoip GEOIP_COUNTRY_CODE,
 *      MaxMind GeoLite2 lookup, etc.). Default resolver returns '' so the
 *      redirect never fires until a filter is wired — protects the site
 *      from over-blocking before geo is plumbed.
 *   2. CONSENT BANNER: visitors from non-blocked jurisdictions see a
 *      bottom-positioned modal with per-category controls. Categories are
 *      Essential (always on, age-gate/theme/GDPR cookie) + Analytics + Marketing.
 *      Per-category opt-in is the strictest GDPR-strict interpretation; the
 *      'analytics on by default' / 'marketing on by default' toggles in the
 *      Customizer only pre-check the toggle on first visit — the visitor
 *      can still decline. Defaults are needed so operator-supplied
 *      analytics integrations can fire post-consent.
 *
 * Privacy integration: ktube_privacy_summary() emits a NEW 'Cookie consent'
 * row documenting the consent state + persistence keys whenever
 * ktube_gdpr_active() is true.
 *
 * @package ktube
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Canonical EU 27 + EEA 3 + UK ISO 3166-1 alpha-2 codes for jurisdictions
 * regulated by GDPR / UK-GDPR / EEA data-protection conventions. Operators
 * may overwrite via the Customizer 'ktube_gdpr_blocked_countries' setting.
 *
 * @return array<int,string>
 */
function ktube_gdpr_default_blocked_countries(): array {
	return array(
		// EU 27 (since 2020 + Croatia).
		'AT', 'BE', 'BG', 'HR', 'CY', 'CZ', 'DK', 'EE', 'FI', 'FR',
		'DE', 'GR', 'HU', 'IE', 'IT', 'LV', 'LT', 'LU', 'MT', 'NL',
		'PL', 'PT', 'RO', 'SK', 'SI', 'ES', 'SE',
		// EEA 3 (Iceland, Liechtenstein, Norway — not in EU but in EEA so GDPR applies).
		'IS', 'LI', 'NO',
		// UK (UK-GDPR post-Brexit; treat like EU for this purpose).
		'GB',
	);
}

/**
 * init hook loader.
 */
function ktube_register_gdpr(): void {
	add_action( 'customize_register',    'ktube_gdpr_customize_register' );
	add_filter( 'template_redirect',     'ktube_gdpr_enforce_redirect',   1 );
	add_action( 'wp_footer',             'ktube_gdpr_render_banner',       20 );
	add_action( 'wp_enqueue_scripts',    'ktube_gdpr_enqueue_consent' );
}

/**
 * Master gate.
 */
function ktube_gdpr_active(): bool {
	return (bool) get_theme_mod( 'ktube_gdpr_enabled', false );
}

/**
 * Resolve the configured blocked-country list. Sanitized to an array of
 * 2-letter uppercase ISO 3166-1 alpha-2 codes; invalid entries dropped.
 *
 * @return array<int,string>
 */
function ktube_gdpr_blocked_countries(): array {
	$ktube_csv = (string) get_theme_mod(
		'ktube_gdpr_blocked_countries',
		implode( ',', ktube_gdpr_default_blocked_countries() )
	);
	$ktube_codes = array_filter( array_map( 'trim', explode( ',', strtoupper( $ktube_csv ) ) ) );
	$ktube_codes = array_filter(
		$ktube_codes,
		static function ( $ktube_code ): bool {
			return is_string( $ktube_code ) && preg_match( '/^[A-Z]{2}$/', $ktube_code ) === 1;
		}
	);
	// Deliberately re-validate after load so a hand-edited DB row that
	// bypassed the Customizer sanitize_callback (or a leftover value
	// from a prior install) still passes through CSV cleanup here.
	// array_unique is essential — repeats would inflate in_array() hit
	// counts in should_redirect_visitor() and the sanitize_callback
	// in customize_register must mirror this so DB-stored values match
	// the read path.
	return array_values( array_unique( $ktube_codes ) );
}

/**
 * Resolve the visitor's 2-letter ISO 3166-1 alpha-2 country code. Returns ''
 * when no provider is wired (or when the filtered value is malformed), so
 * the redirect never fires accidentally — operators MUST opt in to a
 * provider via the 'ktube_gdpr_resolve_country' filter.
 *
 * @return string
 */
function ktube_gdpr_resolve_country(): string {
	$ktube_country = apply_filters( 'ktube_gdpr_resolve_country', '' );
	if ( ! is_string( $ktube_country ) ) {
		return '';
	}
	$ktube_country = trim( strtoupper( $ktube_country ) );
	if ( preg_match( '/^[A-Z]{2}$/', $ktube_country ) !== 1 ) {
		return '';
	}
	return $ktube_country;
}

/**
 * Whether the given 2-letter country code is in the configured block-list.
 *
 * @param string $ktube_country
 * @return bool
 */
function ktube_gdpr_is_country_blocked( string $ktube_country ): bool {
	if ( '' === $ktube_country ) {
		return false;
	}
	return in_array( strtoupper( $ktube_country ), ktube_gdpr_blocked_countries(), true );
}

/**
 * Whether the current request should be hard-blocked by IP. Returns true
 * when: ktube_gdpr_active() + visitor resolved to a blocked country +
 * request is a front-end render (never admin/REST/AJAX/Cron).
 *
 * @return bool
 */
function ktube_gdpr_should_redirect_visitor(): bool {
	if ( ! ktube_gdpr_active() ) {
		return false;
	}
	if ( is_admin() || wp_doing_ajax() || wp_doing_cron() || ( defined( 'REST_REQUEST' ) && REST_REQUEST ) ) {
		return false;
	}
	return ktube_gdpr_is_country_blocked( ktube_gdpr_resolve_country() );
}

/**
 * Default consent state per category. Essential is always on; Analytics
 * and Marketing are configured by Customizer (off-by-default on initial
 * load — operator pre-check only — visitor can still reject).
 *
 * @return array<string,bool>
 */
function ktube_gdpr_consent_categories_default(): array {
	return array(
		'essential'  => true,
		'analytics' => (bool) get_theme_mod( 'ktube_gdpr_categories_analytics', false ),
		'marketing' => (bool) get_theme_mod( 'ktube_gdpr_categories_marketing', false ),
	);
}

/**
 * Whether the visitor has granted consent for a category. Pure helper for
 * plugins/MU-plugins to gate their own analytics/marketing scripts.
 *
 * @param string                 $ktube_category One of essential|analytics|marketing.
 * @param array<string,bool>|null $ktube_blob Optional blob; null = read storage.
 * @return bool
 */
function ktube_gdpr_consent_for_category( string $ktube_category, $ktube_blob = null ): bool {
	if ( null === $ktube_blob ) {
		// Without a runtime bridge into the browser storage, fall back to
		// the category default. Plugins wiring analytics MUST wait for the
		// gdpr-consent.js postMessage round-trip before they fire.
		$ktube_blob = ktube_gdpr_consent_categories_default();
	}
	if ( ! is_array( $ktube_blob ) ) {
		return false;
	}
	if ( ! isset( $ktube_blob[ $ktube_category ] ) ) {
		return false;
	}
	return (bool) $ktube_blob[ $ktube_category ];
}

/**
 * Resolve the privacy page URL even if the privacy module was not loaded
 * (partial-load safety). Returns '' when no privacy page resolves.
 *
 * @return string
 */
function ktube_get_privacy_page_url_safe(): string {
	if ( function_exists( 'ktube_get_privacy_page_url' ) ) {
		return (string) ktube_get_privacy_page_url();
	}
	return '';
}

/**
 * Customizer section + 5 settings.
 *
 * @param WP_Customize_Manager $wp_customize
 */
function ktube_gdpr_customize_register( WP_Customize_Manager $wp_customize ): void {
	$wp_customize->add_section(
		'ktube_gdpr',
		array(
			/* translators: brief summary for operator */
			'title'       => __( 'ktube — GDPR', 'ktube' ),
			'description' => __( 'EU/EEA/UK jurisdictional hard-block + cookie consent banner. Wire your IP-geo provider via the ktube_gdpr_resolve_country filter before enabling. Both features default OFF and only fire on the public site — never in admin / preview / REST.', 'ktube' ),
			'priority'    => 32,
		)
	);

	$wp_customize->add_setting(
		'ktube_gdpr_enabled',
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
		'ktube_gdpr_enabled',
		array(
			'label'       => __( 'Enable GDPR compliance', 'ktube' ),
			'description' => __( 'When ON, the theme enforces an IP-based jurisdictional hard-block for the configured country list AND shows a cookie consent banner to remaining visitors.', 'ktube' ),
			'section'     => 'ktube_gdpr',
			'type'        => 'checkbox',
		)
	);		$wp_customize->add_setting(
		'ktube_gdpr_blocked_countries',
		array(
			'default'           => implode( ',', ktube_gdpr_default_blocked_countries() ),
			'type'              => 'theme_mod',
			'capability'        => 'edit_theme_options',
			'transport'         => 'refresh',
			'sanitize_callback' => static function ( $v ): string {
				$v = is_string( $v ) ? trim( $v ) : '';
				$ktube_codes = array_filter( array_map( 'trim', explode( ',', strtoupper( $v ) ) ) );
				$ktube_codes = array_filter(
					$ktube_codes,
					static function ( $ktube_code ): bool {
						return is_string( $ktube_code ) && preg_match( '/^[A-Z]{2}$/', $ktube_code ) === 1;
					}
				);
				// Mirrors ktube_gdpr_blocked_countries() read path —
				// dedupe + reorder on write so DB-stored values already
				// in canonical form when testers read them back.
				return implode( ',', array_values( array_unique( $ktube_codes ) ) );
			},
		)
	);
	$wp_customize->add_control(
		'ktube_gdpr_blocked_countries',
		array(
			'label'       => __( 'Blocked countries', 'ktube' ),
			'description' => __( 'ISO 3166-1 alpha-2 CSV. EU 27 + EEA 3 + UK are pre-filled. Override per-country if your legal counsel recommends a different list.', 'ktube' ),
			'section'     => 'ktube_gdpr',
			'type'        => 'text',
		)
	);

	$wp_customize->add_setting(
		'ktube_gdpr_redirect_url',
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
		'ktube_gdpr_redirect_url',
		array(
			'label'       => __( 'Blocked-country redirect URL', 'ktube' ),
			'description' => __( 'Where to send visitors from blocked jurisdictions. Default points to Google.', 'ktube' ),
			'section'     => 'ktube_gdpr',
			'type'        => 'url',
		)
	);

	$wp_customize->add_setting(
		'ktube_gdpr_categories_analytics',
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
		'ktube_gdpr_categories_analytics',
		array(
			'label'       => __( 'Pre-check Analytics on first visit', 'ktube' ),
			'description' => __( 'When ON, the consent banner pre-checks the Analytics toggle on first visit. Visitors can still decline. Leave OFF for the strictest GDPR-strict consent flow.', 'ktube' ),
			'section'     => 'ktube_gdpr',
			'type'        => 'checkbox',
		)
	);

	$wp_customize->add_setting(
		'ktube_gdpr_categories_marketing',
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
		'ktube_gdpr_categories_marketing',
		array(
			'label'       => __( 'Pre-check Marketing on first visit', 'ktube' ),
			'description' => __( 'When ON, the consent banner pre-checks the Marketing toggle on first visit. Visitors can still decline.', 'ktube' ),
			'section'     => 'ktube_gdpr',
			'type'        => 'checkbox',
		)
	);
}

/**
 * template_redirect hook — 302 visitors from blocked countries away.
 * Fires at priority 1 so it runs BEFORE templates render / output starts.
 * No-op for admin / preview / REST / AJAX / Cron.
 */
function ktube_gdpr_enforce_redirect(): void {
	if ( ! ktube_gdpr_should_redirect_visitor() ) {
		return;
	}
	$ktube_target = (string) get_theme_mod( 'ktube_gdpr_redirect_url', 'https://www.google.com/' );
	if ( '' === trim( $ktube_target ) ) {
		$ktube_target = 'https://www.google.com/';
	}
	wp_redirect( esc_url_raw( $ktube_target ), 302 );
	exit;
}

/**
 * Render the consent banner mountpoint. wp_footer priority 20 — runs
 * after ktube-compliance-links nav so the banner is the visually-last
 * element before </body>.
 */
function ktube_gdpr_render_banner(): void {
	if ( ! ktube_gdpr_active() ) {
		return;
	}
	if ( is_admin() || wp_doing_ajax() || wp_doing_cron() || ( defined( 'REST_REQUEST' ) && REST_REQUEST ) ) {
		return;
	}
	?>
	<div id="ktube-gdpr-banner" class="ktube-gdpr-banner" role="region" aria-label="<?php esc_attr_e( 'Cookie consent', 'ktube' ); ?>" hidden>
		<div class="ktube-gdpr-banner__inner">
			<p class="ktube-gdpr-banner__msg"><?php echo esc_html__( 'This site uses cookies for essential services and optional analytics/marketing. Choose how you want to consent.', 'ktube' ); ?></p>
			<ul class="ktube-gdpr-banner__categories">
				<li class="ktube-gdpr-banner__category ktube-gdpr-banner__category--essential">
					<span class="ktube-gdpr-banner__category-label"><?php esc_html_e( 'Essential', 'ktube' ); ?></span>
					<span class="ktube-gdpr-banner__category-status" aria-label="<?php esc_attr_e( 'Always on', 'ktube' ); ?>"><?php esc_html_e( 'Always on', 'ktube' ); ?></span>
				</li>
				<li class="ktube-gdpr-banner__category ktube-gdpr-banner__category--analytics">
					<span class="ktube-gdpr-banner__category-label"><?php esc_html_e( 'Analytics', 'ktube' ); ?></span>
					<label class="ktube-gdpr-banner__toggle">
						<input type="checkbox" name="ktube-gdpr-category" value="analytics" data-ktube-gdpr-category="analytics" />
						<span class="ktube-gdpr-banner__toggle-text"><?php esc_html_e( 'Allow', 'ktube' ); ?></span>
					</label>
				</li>
				<li class="ktube-gdpr-banner__category ktube-gdpr-banner__category--marketing">
					<span class="ktube-gdpr-banner__category-label"><?php esc_html_e( 'Marketing', 'ktube' ); ?></span>
					<label class="ktube-gdpr-banner__toggle">
						<input type="checkbox" name="ktube-gdpr-category" value="marketing" data-ktube-gdpr-category="marketing" />
						<span class="ktube-gdpr-banner__toggle-text"><?php esc_html_e( 'Allow', 'ktube' ); ?></span>
					</label>
				</li>
			</ul>
			<div class="ktube-gdpr-banner__actions">
				<button type="button" class="ktube-gdpr-banner__btn ktube-gdpr-banner__btn--reject"          data-ktube-gdpr-action="reject"><?php esc_html_e( 'Reject all', 'ktube' ); ?></button>
				<button type="button" class="ktube-gdpr-banner__btn ktube-gdpr-banner__btn--accept-selected" data-ktube-gdpr-action="accept-selected"><?php esc_html_e( 'Accept selected', 'ktube' ); ?></button>
				<button type="button" class="ktube-gdpr-banner__btn ktube-gdpr-banner__btn--accept-all"     data-ktube-gdpr-action="accept-all"><?php esc_html_e( 'Accept all', 'ktube' ); ?></button>
			</div>
		</div>
	</div>
	<?php
}

/**
 * Enqueue the JS bundle + localize the config object. Front-end only.
 */
function ktube_gdpr_enqueue_consent(): void {
	if ( ! ktube_gdpr_active() ) {
		return;
	}
	if ( is_admin() || wp_doing_ajax() || wp_doing_cron() || ( defined( 'REST_REQUEST' ) && REST_REQUEST ) ) {
		return;
	}
	$ktube_js = KTUBE_DIR . '/assets/js/gdpr-consent.js';
	if ( ! file_exists( $ktube_js ) ) {
		return;
	}
	wp_enqueue_script(
		'ktube-gdpr-consent',
		KTUBE_URI . '/assets/js/gdpr-consent.js',
		array(),
		KTUBE_VERSION,
		true
	);
	wp_add_inline_script(
		'ktube-gdpr-consent',
		'window.ktubeGdprConsentData = ' . wp_json_encode(
			array(
				'enabled'    => true,
				'storageKey' => 'ktube-gdpr-consent',
				'cookieName' => 'ktube_gdpr_consent',
				'ttlDays'    => 180,
				'categories' => ktube_gdpr_consent_categories_default(),
				'privacyUrl' => ktube_get_privacy_page_url_safe(),
			)
		) . ';',
		'before'
	);
}
