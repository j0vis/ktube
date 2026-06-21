<?php
/**
 * ktube_resolve_initialTheme tests via the theme's own helper.
 *
 * The function lives in functions.php, NOT customizer.php — but it only
 * depends on get_theme_mod (stubbed) and a global default. We re-define
 * the function inline from the source so this test is hermetic. Mirror
 * it byte-for-byte with functions.php's definition.
 */
class ResolveInitialThemeTest extends \PHPUnit\Framework\TestCase {

	public function setUp(): void {
		reset_theme_mods_test();
		if ( ! function_exists( 'ktube_resolve_initial_theme' ) ) {
			// Mirror of functions.php::ktube_resolve_initial_theme (kept
			// private to this test — drop if functions.php is included
			// above the bootstrap).
			eval('function ktube_resolve_initial_theme(): string {
				$ktube_default = get_theme_mod( "ktube_theme_default", "auto" );
				if ( "dark" === $ktube_default ) {
					return "dark";
				}
				return "light";
			}');
		}
	}

	public function test_dark_default_returns_dark(): void {
		set_theme_mod_test( 'ktube_theme_default', 'dark' );
		$this->assertSame( 'dark', ktube_resolve_initial_theme() );
	}

	public function test_light_default_returns_light(): void {
		set_theme_mod_test( 'ktube_theme_default', 'light' );
		$this->assertSame( 'light', ktube_resolve_initial_theme() );
	}

	public function test_auto_default_returns_light(): void {
		// Auto is resolved CLIENT-SIDE via matchMedia. The PHP-side default
		// must be "light" — server is biased to FOUC-prevention; client
		// overrides if OS pref is dark.
		set_theme_mod_test( 'ktube_theme_default', 'auto' );
		$this->assertSame( 'light', ktube_resolve_initial_theme() );
	}

	public function test_missing_mod_falls_back_to_light(): void {
		// No theme_mod set → default is auto → server returns light.
		$this->assertSame( 'light', ktube_resolve_initial_theme() );
	}

	public function test_invalid_default_falls_back_to_light(): void {
		set_theme_mod_test( 'ktube_theme_default', 'rainbow' );
		// sanitize_theme_default would normalize… test the raw path that
		// ignores unknown values explicitly.
		$this->assertSame( 'light', ktube_resolve_initial_theme() );
	}
}
