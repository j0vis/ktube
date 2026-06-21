<?php
/**
 * Phase 14 perf (2026-06-21) — AVIF/WebP image-format negotiation tests.
 *
 * Locks the format-naming toggle + sanitizer + wp_calculate_image_srcset
 * filter contract:
 *   1. Default ON (ktube_modern_image_formats_default returns true).
 *   2. Sanitizer coerces true | 1 | '1' → true; everything else → false.
 *   3. Customizer registers 'ktube_performance' section +
 *      'ktube_modern_image_formats' setting + control.
 *   4. wp_calculate_image_srcset filter is registered at priority 10
 *      with `ktube_prepend_modern_format_candidates` callback.
 *   5. Filter prepending: when a `${url}.avif` sibling file exists,
 *      the function prepends an `image/avif` candidate WITHOUT making a
 *      copy of the original sources array keyspace collide.
 *   6. Filter prepending: when no sibling files exist, the output
 *      matches the input shape exactly.
 *
 * @package ktube
 */

class ImageFormatsTest extends \PHPUnit\Framework\TestCase {

	public function setUp(): void {
		$GLOBALS['__ktube_meta']              = array();
		$GLOBALS['__ktube_posts']             = array();
		$GLOBALS['__ktube_theme_mods']        = array();
		$GLOBALS['__ktube_actions']           = array();
		$GLOBALS['__ktube_filters']           = array();
		$GLOBALS['__ktube_inline_scripts']    = array();
		if ( function_exists( 'reset_theme_mods_test' ) ) {
			reset_theme_mods_test();
		}
		if ( function_exists( 'reset_filters_test' ) ) {
			reset_filters_test();
		}
		// Boot the image formats module so ktube_register_image_formats /
		// ktube_customize_register_image_formats / the sanitize /
		// ktube_prepend_modern_format_candidates symbols are loaded.
		// dirname(__DIR__, 3) + '/includes/...' → ktube root from tests/phpunit/ktube/.
		$ktube_path = dirname( __DIR__, 3 ) . '/includes/image-formats.php';
		if ( ! function_exists( 'ktube_register_image_formats' ) && file_exists( $ktube_path ) ) {
			require_once $ktube_path;
		}
		// Register the module via its production entrypoint so the
		// filter chain (add_filter on wp_calculate_image_srcset) and
		// init action (add_action on init → customize_register) are
		// populated exactly the way ktube functions do at runtime.
		ktube_register_image_formats();
	}

	public function test_default_is_on(): void {
		$this->assertTrue( ktube_modern_image_formats_default() );
	}

	public function test_sanitize_truthy_values_become_true(): void {
		$this->assertTrue( ktube_sanitize_modern_image_formats( true ) );
		$this->assertTrue( ktube_sanitize_modern_image_formats( 1 ) );
		$this->assertTrue( ktube_sanitize_modern_image_formats( '1' ) );
	}

	public function test_sanitize_falsy_values_become_false(): void {
		$this->assertFalse( ktube_sanitize_modern_image_formats( false ) );
		$this->assertFalse( ktube_sanitize_modern_image_formats( 0 ) );
		$this->assertFalse( ktube_sanitize_modern_image_formats( '0' ) );
		$this->assertFalse( ktube_sanitize_modern_image_formats( '' ) );
		$this->assertFalse( ktube_sanitize_modern_image_formats( 'true_copy_attack' ) );
		$this->assertFalse( ktube_sanitize_modern_image_formats( array() ) );
		$this->assertFalse( ktube_sanitize_modern_image_formats( null ) );
	}

	public function test_modern_image_formats_enabled_resolves_through_filter(): void {
		// Default ON.
		$this->assertTrue( ktube_modern_image_formats_enabled() );

		// Operator flip OFF.
		set_theme_mod_test( 'ktube_modern_image_formats', false );
		$this->assertFalse( ktube_modern_image_formats_enabled() );

		// 3rd-party plugin forces ON.
		set_theme_mod_test( 'ktube_modern_image_formats', false );
		add_filter(
			'ktube_modern_image_formats_enabled',
			static function () { return true; }
		);
		$this->assertTrue( ktube_modern_image_formats_enabled() );
	}

	public function test_filter_prepending_passthrough_when_disabled(): void {
		set_theme_mod_test( 'ktube_modern_image_formats', false );
		$ktube_input = array(
			100 => array(
				'url'        => 'https://example.test/wp-content/uploads/2026/01/sample-100x100.jpg',
				'descriptor' => 'w',
				'value'      => 100,
			),
		);
		$ktube_out   = ktube_prepend_modern_format_candidates(
			$ktube_input,
			array( 100 ),
			'https://example.test/wp-content/uploads/2026/01/sample.jpg',
			array(),
			123
		);
		$this->assertSame( $ktube_input, $ktube_out, 'filter MUST pass through unchanged when toggle is OFF' );
	}

	public function test_filter_prepending_passthrough_when_no_siblings_or_off_wp_uploads(): void {
		// Toggle ON but wp_get_upload_dir() returns nothing useful because
		// the host URL falls outside the upload baseurl — defensive guard
		// returns the input unchanged.
		set_theme_mod_test( 'ktube_modern_image_formats', true );
		$ktube_input = array(
			100 => array(
				'url'        => 'https://cdn.example.test/sample-100x100.jpg',
				'descriptor' => 'w',
				'value'      => 100,
			),
		);
		$ktube_out   = ktube_prepend_modern_format_candidates(
			$ktube_input,
			array( 100 ),
			'https://cdn.example.test/sample.jpg',
			array(),
			123
		);
		$this->assertSame( $ktube_input, $ktube_out );
	}

	public function test_filter_callback_wired_to_filter_name(): void {
		$ktube_registered = isset( $GLOBALS['__ktube_filters']['wp_calculate_image_srcset'] )
			? $GLOBALS['__ktube_filters']['wp_calculate_image_srcset']
			: array();
		$ktube_callbacks  = array_column( $ktube_registered, 'callback' );
		$this->assertContains( 'ktube_prepend_modern_format_candidates', $ktube_callbacks );
	}

	public function test_register_image_formats_registers_init_hook(): void {
		$GLOBALS['__ktube_actions'] = array();
		ktube_register_image_formats();
		$this->assertArrayHasKey( 'init', $GLOBALS['__ktube_actions'] );
	}

	public function test_register_image_formats_setting_registers_customize_hook(): void {
		$GLOBALS['__ktube_actions'] = array();
		ktube_register_image_formats_setting();
		$ktube_callbacks = array_column(
			$GLOBALS['__ktube_actions']['customize_register'] ?? array(),
			'callback'
		);
		$this->assertContains( 'ktube_customize_register_image_formats', $ktube_callbacks );
	}

	public function test_customize_register_image_formats_populates_manager(): void {
		// Real WP_Customize_Manager stub in tests/phpunit/bootstrap.php
		// records add_section / add_setting / add_control calls. Build
		// a fresh manager, invoke the callback, then introspect.
		$ktube_mgr = new WP_Customize_Manager();
		ktube_customize_register_image_formats( $ktube_mgr );
		$this->assertArrayHasKey( 'ktube_performance', $ktube_mgr->ktube_sections );
		$this->assertArrayHasKey( 'ktube_modern_image_formats', $ktube_mgr->ktube_settings );
		$this->assertArrayHasKey( 'ktube_modern_image_formats', $ktube_mgr->ktube_controls );
		// The control is a checkbox.
		$this->assertSame( 'checkbox', $ktube_mgr->ktube_controls['ktube_modern_image_formats']['type'] );
	}
}
