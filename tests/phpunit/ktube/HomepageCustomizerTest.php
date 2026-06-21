<?php
/**
 * Phase 8-A 2026-06-21 — Homepage editorial Customizer.
 *
 * Tests the section + control registration chain + the helper getters
 * used by index.php + the <meta name="description"> emitter.
 *
 * The WP_Customize_Manager stub in bootstrap.php records every
 * add_section / add_setting / add_control call onto public properties
 * so we can introspect the registration without spinning up WP core.
 *
 * @package ktube
 */

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../bootstrap.php';

class HomepageCustomizerTest extends TestCase {

	public function setUp(): void {
		$GLOBALS['__ktube_theme_mods'] = array();
		$GLOBALS['__ktube_actions']    = array();
		$GLOBALS['__ktube_posts']      = array();
		reset_request_state_test();
		// Some test files load the customizer earlier; re-run
		// ktube_register_customizer() so __ktube_actions is re-seeded
		// with the homepage subpanel hook regardless of import order.
		ktube_register_customizer();
	}

	public function test_helpers_return_empty_when_unset(): void {
		$this->assertSame( '', ktube_get_home_h1() );
		$this->assertSame( '', ktube_get_home_description() );
	}

	public function test_helpers_return_theme_mod_value_when_set(): void {
		set_theme_mod_test( 'ktube_home_h1', 'Our latest videos' );
		set_theme_mod_test( 'ktube_home_description', 'Hand-picked clips updated daily.' );
		$this->assertSame( 'Our latest videos', ktube_get_home_h1() );
		$this->assertSame( 'Hand-picked clips updated daily.', ktube_get_home_description() );
	}

	public function test_helpers_pass_through_raw_theme_mod_value(): void {
		// Theme_mod value pass-through is intentional; sanitize happens
		// at the customizer sanitize_callback. Helpers return raw value.
		set_theme_mod_test( 'ktube_home_h1', '<script>alert(1)</script>Hello' );
		$ktube = ktube_get_home_h1();
		$this->assertStringContainsString( '<script>alert(1)</script>Hello', $ktube );
	}

	public function test_customize_register_homepage_callback_is_hooked(): void {
		$ktube_hooks     = $GLOBALS['__ktube_actions']['customize_register'] ?? array();
		$ktube_callbacks = array_column( $ktube_hooks, 'callback' );
		$this->assertTrue(
			in_array( 'ktube_customize_register_homepage', $ktube_callbacks, true ),
			'ktube_customize_register_homepage must be hooked on customize_register'
		);
		$this->assertTrue(
			in_array( 'ktube_customize_register', $ktube_callbacks, true ),
			'ktube_customize_register must still be hooked on customize_register after Phase 8-A'
		);
	}

	public function test_customize_register_homepage_adds_section_with_section_id_homepage(): void {
		$ktube_manager = new WP_Customize_Manager();
		ktube_customize_register_homepage( $ktube_manager );
		$this->assertArrayHasKey( 'ktube_homepage', $ktube_manager->ktube_sections );
		$this->assertSame(
			'ktube — Homepage',
			$ktube_manager->ktube_sections['ktube_homepage']['title']
		);
	}

	public function test_customize_register_homepage_adds_h1_setting_and_control(): void {
		$ktube_manager = new WP_Customize_Manager();
		ktube_customize_register_homepage( $ktube_manager );

		$this->assertArrayHasKey( 'ktube_home_h1', $ktube_manager->ktube_settings );
		$ktube_h1_setting = $ktube_manager->ktube_settings['ktube_home_h1'];
		$this->assertSame( '', $ktube_h1_setting['default'] );
		$this->assertSame( 'theme_mod', $ktube_h1_setting['type'] );
		$this->assertSame( 'refresh', $ktube_h1_setting['transport'] );
		$this->assertTrue( is_callable( $ktube_h1_setting['sanitize_callback'] ) );

		$this->assertArrayHasKey( 'ktube_home_h1', $ktube_manager->ktube_controls );
		$ktube_h1_control = $ktube_manager->ktube_controls['ktube_home_h1'];
		$this->assertSame( 'text', $ktube_h1_control['type'] );
		$this->assertSame( 'ktube_homepage', $ktube_h1_control['section'] );
	}

	public function test_customize_register_homepage_adds_description_setting_and_control_as_textarea(): void {
		$ktube_manager = new WP_Customize_Manager();
		ktube_customize_register_homepage( $ktube_manager );

		$this->assertArrayHasKey( 'ktube_home_description', $ktube_manager->ktube_settings );
		$ktube_setting = $ktube_manager->ktube_settings['ktube_home_description'];
		$this->assertSame( '', $ktube_setting['default'] );
		$this->assertSame( 'theme_mod', $ktube_setting['type'] );
		$this->assertTrue( is_callable( $ktube_setting['sanitize_callback'] ) );

		$this->assertArrayHasKey( 'ktube_home_description', $ktube_manager->ktube_controls );
		$ktube_control = $ktube_manager->ktube_controls['ktube_home_description'];
		$this->assertSame( 'textarea', $ktube_control['type'] );
		$this->assertSame( 'ktube_homepage', $ktube_control['section'] );
	}

	public function test_sanitize_callback_for_h1_strips_html(): void {
		$ktube_manager = new WP_Customize_Manager();
		ktube_customize_register_homepage( $ktube_manager );
		$ktube_cb = $ktube_manager->ktube_settings['ktube_home_h1']['sanitize_callback'];
		// sanitize_text_field: strip_tags → collapse [\r\n\t ]+ → ' ' → trim.
		// Input  "  <b>Hello</b> world  " becomes "Hello world".
		$this->assertSame( 'Hello world', $ktube_cb( '  <b>Hello</b> world  ' ) );
	}

	public function test_sanitize_callback_for_description_preserves_newlines(): void {
		$ktube_manager = new WP_Customize_Manager();
		ktube_customize_register_homepage( $ktube_manager );
		$ktube_cb = $ktube_manager->ktube_settings['ktube_home_description']['sanitize_callback'];
		// sanitize_textarea_field: strip_tags → collapse [\t ]+ → ' ' → trim.
		// Unlike sanitize_text_field, the \n is preserved. trim only
		// operates on the leading/trailing run of the whole string — it
		// does NOT touch inter-line whitespace, so "Line one\n  Line two"
		// keeps the leading double-space → " Line two" after the collapse.
		// Input  "  Line one\n  Line two  ":
		//   strip_tags       →  "  Line one\n  Line two  "
		//   collapse [\t ]+  →  " Line one\n Line two "
		//   trim outer edges →  "Line one\n Line two"
		$this->assertSame(
			"Line one\n Line two",
			$ktube_cb( "  Line one\n  Line two  " )
		);
	}

	public function test_render_home_meta_description_skips_on_non_front_page(): void {
		set_theme_mod_test( 'ktube_home_description', 'Should NOT be emitted off the homepage.' );
		ob_start();
		ktube_render_home_meta_description();
		$ktube_output = (string) ob_get_clean();
		$this->assertSame( '', $ktube_output );
	}

	public function test_render_home_meta_description_skips_when_description_empty(): void {
		// No set_theme_mod_test → empty default. Even with is_front_page
		// true, an empty description should not emit. (The stubs default
		// is_front_page to false so the early return wins either way;
		// this test asserts the empty-default branch.)
		ob_start();
		ktube_render_home_meta_description();
		$ktube_output = (string) ob_get_clean();
		$this->assertSame( '', $ktube_output );
	}

	public function test_render_home_meta_description_emits_meta_tag_on_front_page(): void {
		set_is_front_page_test( true );
		set_theme_mod_test( 'ktube_home_description', 'Hand-picked clips updated daily.' );
		ob_start();
		ktube_render_home_meta_description();
		$ktube_output = (string) ob_get_clean();
		$this->assertStringContainsString( '<meta name="description"', $ktube_output );
		$this->assertStringContainsString( 'Hand-picked clips updated daily.', $ktube_output );
		$this->assertStringContainsString( 'content=', $ktube_output );
	}

	public function test_render_home_meta_description_emits_meta_tag_on_home_index(): void {
		set_is_home_test( true );
		set_theme_mod_test( 'ktube_home_description', 'Front-page fallback via is_home().' );
		ob_start();
		ktube_render_home_meta_description();
		$ktube_output = (string) ob_get_clean();
		$this->assertStringContainsString( '<meta name="description"', $ktube_output );
		$this->assertStringContainsString( 'Front-page fallback via is_home().', $ktube_output );
	}

	public function test_render_home_meta_description_escapes_special_characters(): void {
		set_is_front_page_test( true );
		set_theme_mod_test( 'ktube_home_description', 'Tom & Jerry "best of"' );
		ob_start();
		ktube_render_home_meta_description();
		$ktube_output = (string) ob_get_clean();
		// esc_attr converts & to &amp; and " to &quot; in attribute context.
		$this->assertStringContainsString( 'Tom &amp; Jerry &quot;best of&quot;', $ktube_output );
		$this->assertStringNotContainsString( 'Tom & Jerry "best of"', $ktube_output );
	}
}
