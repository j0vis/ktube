<?php
/**
 * ktube PHPUnit bootstrap (composer-free).
 *
 * Purpose: load the theme's customizer.php into a sandboxed PHP process so
 * unit tests can exercise sanitizers and the buildCss() golden fixture
 * without spinning up WordPress. Stubs every WP function the theme files
 * touch inside the customizer path. The same stubs work for vendor/bin/
 * phpunit under composer — drop the file in via "auto_prepend_file" or
 * point phpunit.xml at it.
 *
 * Composer users:
 *   - This file is loaded by phpunit.xml.dist's bootstrap= attribute.
 *   - When real PHPUnit is present, the namespace-stubbed TestCase at the
 *     top of this file is NEVER used because vendor/autoload.php shadows
 *     it; PHPUnit's real TestCase takes over.
 *
 * Composer-free users:
 *   - php tests/phpunit/run.php loads this file directly. The namespace-
 *     stubbed TestCase at the top provides the four assert* methods the
 *     tests use; PHPUnit's presence is not required.
 */

namespace PHPUnit\Framework {
	/**
	 * Minimal stand-in for PHPUnit's real TestCase. Loaded ONLY when there
	 * is no real PHPUnit autoloader available. Real PHPUnit is namespaced
	 * the same way, but its loader takes precedence because vendor/autoload
	 * has higher precedence than this file's namespace block.
	 */
	class TestCase {
		private static int $passes = 0;
		private static int $fails   = 0;

		public function assertSame( $expected, $actual, string $message = '' ): void {
			self::record( $expected === $actual, $message, 'identical', $expected, $actual );
		}
		public function assertNotSame( $expected, $actual, string $message = '' ): void {
			self::record( $expected !== $actual, $message, 'not identical', $expected, $actual );
		}
		public function assertEquals( $expected, $actual, string $message = '' ): void {
			self::record( $expected == $actual, $message, 'equals', $expected, $actual );
		}
		public function assertTrue( $cond, string $message = '' ): void {
			self::record( (bool) $cond, $message, 'truthy', true, $cond );
		}
		public function assertFalse( $cond, string $message = '' ): void {
			self::record( ! $cond, $message, 'falsy', false, $cond );
		}
		public function assertCount( int $expected, $haystack, string $message = '' ): void {
			$got = is_array( $haystack ) || $haystack instanceof \Countable ? count( $haystack ) : -1;
			self::record( $got === $expected, $message, 'count ' . $expected, $expected, $got );
		}
	public function assertArrayHasKey( $key, $array, string $message = '' ): void {
		$ok = is_array( $array ) && array_key_exists( $key, $array );
		self::record( $ok, $message, "array has key '$key'", true, $ok );
	}
	public function assertArrayNotHasKey( $key, $array, string $message = '' ): void {
		$ok = is_array( $array ) && ! array_key_exists( $key, $array );
		self::record( $ok, $message, "array lacks key '$key'", true, $ok );
	}
	public function assertContains( $needle, $haystack, string $message = '' ): void {
		if ( is_string( $haystack ) ) {
			$ok = strpos( $haystack, (string) $needle ) !== false;
			self::record( $ok, $message, "string contains '$needle'", true, $ok );
			return;
		}
		if ( is_array( $haystack ) ) {
			$ok = in_array( $needle, $haystack, true );
			self::record( $ok, $message, "array contains '$needle'", true, $ok );
			return;
		}
		if ( $haystack instanceof \Countable ) {
			$ok = false;
			foreach ( $haystack as $ktube_item ) {
				if ( $ktube_item === $needle ) { $ok = true; break; }
			}
			self::record( $ok, $message, "iterable contains '$needle'", true, $ok );
			return;
		}
		self::record( false, $message, 'unsupported haystack type', '[containing]', $haystack );
	}
		public function assertNotNull( $value, string $message = '' ): void {
			self::record( null !== $value, $message, 'not null', true, $value );
		}
		public function assertIsBool( $value, string $message = '' ): void {
			self::record( is_bool( $value ), $message, 'is bool', true, $value );
		}
	public function assertIsString( $value, string $message = '' ): void {
		self::record( is_string( $value ), $message, 'is string', true, $value );
	}
	public function assertIsArray( $value, string $message = '' ): void {
		self::record( is_array( $value ), $message, 'is array', true, $value );
	}
	public function assertFileExists( $path, string $message = '' ): void {
		self::record( file_exists( (string) $path ), $message, 'file exists', true, file_exists( (string) $path ) );
	}
	public function assertNotFalse( $value, string $message = '' ): void {
		$ok = false !== $value;
		self::record( $ok, $message, 'is not false', false, $value );
	}
		public function assertEmpty( $actual, string $message = '' ): void {
			self::record( empty( $actual ), $message, 'empty', true, $actual );
		}
		public function assertNotEmpty( $actual, string $message = '' ): void {
			self::record( ! empty( $actual ), $message, 'not empty', true, $actual );
		}
		public function assertStringContainsString( string $needle, string $haystack, string $message = '' ): void {
			$ok = strpos( $haystack, $needle ) !== false;
			self::record( $ok, $message, "haystack contains '$needle'", true, $ok );
		}
	public function assertStringNotContainsString( string $needle, string $haystack, string $message = '' ): void {
		$ok = strpos( $haystack, $needle ) === false;
		self::record( $ok, $message, "haystack lacks '$needle'", true, $ok );
	}
	public function assertStringEndsWith( string $suffix, string $haystack, string $message = '' ): void {
		$len  = strlen( $suffix );
		$ok   = '' === $suffix || ( strlen( $haystack ) >= $len && 0 === substr_compare( $haystack, $suffix, -$len, $len ) );
		self::record( $ok, $message, "haystack ends with '$suffix'", true, $ok );
	}
	public function assertStringStartsWith( string $prefix, string $string, string $message = '' ): void {
		$ok = '' === $prefix ? true : ( is_string( $string ) && 0 === strpos( $string, $prefix ) );
		self::record( $ok, $message, "starts with '$prefix'", true, $ok );
	}
	public function assertNotContains( $needle, $haystack, string $message = '' ): void {
		if ( is_string( $haystack ) ) {
			$ok = false === strpos( $haystack, (string) $needle );
			self::record( $ok, $message, "haystack lacks '$needle'", true, $ok );
			return;
		}
		if ( is_array( $haystack ) ) {
			$ok = ! in_array( $needle, $haystack, true );
			self::record( $ok, $message, "array lacks '$needle'", true, $ok );
			return;
		}
		self::record( false, $message, 'unsupported haystack type', '[not_containing]', $haystack );
	}
		public function assertMatchesRegularExpression( string $pattern, string $subject, string $message = '' ): void {
			$ok = (bool) preg_match( $pattern, $subject );
			self::record( $ok, $message, "matches /$pattern/", true, $ok );
		}
		public function assertDoesNotMatchRegularExpression( string $pattern, string $subject, string $message = '' ): void {
			$ok = ! preg_match( $pattern, $subject );
			self::record( $ok, $message, "does not match /$pattern/", true, $ok );
		}

		private static function record( bool $ok, string $message, string $kind, $expected, $actual ): void {
			$caller = debug_backtrace( DEBUG_BACKTRACE_IGNORE_ARGS, 2 )[1] ?? null;
			$where  = $caller ? ( $caller['class'] ?? $caller['function'] ) : 'global';
			if ( $ok ) {
				self::$passes++;
				echo "  PASS  $where::{$caller['function']}\n";
				return;
			}
			self::$fails++;
			echo "  FAIL  $where::{$caller['function']}  $message\n    expected ($kind): " . var_export( $expected, true ) . "\n    actual:           " . var_export( $actual, true ) . "\n";
		}

		public static function totals(): array {
			return array( self::$passes, self::$fails );
		}
	}
}

namespace {

	// ---- Environment setup ---------------------------------------------

	if ( ! defined( 'ABSPATH' ) ) {
		define( 'ABSPATH', __DIR__ . '/../../' );
	}
	if ( ! defined( 'KTUBE_VERSION' ) ) {
		define( 'KTUBE_VERSION', '0.1.0' );
	}
	// The theme's functions.php defines KTUBE_DIR / KTUBE_URI alongside
	// KTUBE_VERSION. The bootstrap can't load functions.php (it transitively
	// bootstraps wp_get_theme() etc.) so we mirror the same defines here.
	// Production module paths read KTUBE_DIR . '/assets/...' so a missing
	// definition is a PHP 8 fatal under tests.
	if ( ! defined( 'KTUBE_DIR' ) ) {
		define( 'KTUBE_DIR', get_template_directory() );
	}
	if ( ! defined( 'KTUBE_URI' ) ) {
		define( 'KTUBE_URI', get_template_directory_uri() );
	}
	if ( ! defined( 'KTUBE_MIN_PHP' ) ) {
		define( 'KTUBE_MIN_PHP', '8.0' );
	}
	if ( ! defined( 'KTUBE_MIN_WP' ) ) {
		define( 'KTUBE_MIN_WP', '6.5' );
	}

	/**
	 * Test-controllable theme_mod bag. Tests use set_theme_mod_test() to
	 * inject values; theme functions call get_theme_mod() which we stub.
	 */
	$GLOBALS['__ktube_theme_mods'] = array();
	function get_theme_mod( $name, $default = false ) {
		return $GLOBALS['__ktube_theme_mods'][$name] ?? $default;
	}
	function set_theme_mod_test( $name, $value ) {
		$GLOBALS['__ktube_theme_mods'][$name] = $value;
		return true;
	}
	function reset_theme_mods_test() {
		$GLOBALS['__ktube_theme_mods'] = array();
	}

	// ---- Minimal WP stubs ----------------------------------------------

	// Phase 8-A 2026-06-21: this stub now records into the same global
	// the *test helpers* write through. Earlier (Phase 4-5) we relied on
	// `add_action_test( tag, callback, priority, args )` as the only path,
	// but real WP records via `add_action`, so theme code that calls
	// `add_action(...)` was leaving `__ktube_actions` empty in tests
	// (silent). Closing that gap so any test that asserts on hooked
	// callbacks (Phase 9b Privacy, Phase 8-A Homepage) sees the chain
	// populated when ktube *production* code runs.
	//
	// v2 (2026-06-21) — wrapped in `function_exists` so a future composer-
	// installed Brain\Monkey / WP autoload takes precedence. Mirrors the
	// pattern already in place two lines below for `add_filter`.
	$GLOBALS['__ktube_actions'] = array();
	if ( ! function_exists( 'add_action' ) ) {
		function add_action( $ktube_tag, $ktube_callback, int $ktube_priority = 10, int $ktube_args = 1 ): bool {
			$GLOBALS['__ktube_actions'][ $ktube_tag ][] = array(
				'priority' => $ktube_priority,
				'callback' => $ktube_callback,
				'args'     => $ktube_args,
			);
			return true;
		}
	}
	function add_action_test( $ktube_tag, $ktube_callback, int $ktube_priority = 10, int $ktube_args = 1 ): void {
		$GLOBALS['__ktube_actions'][ $ktube_tag ][] = array(
			'priority'  => $ktube_priority,
			'callback'  => $ktube_callback,
			'args'      => $ktube_args,
		);
	}
	function do_action_count_test( string $ktube_tag ): int {
		return isset( $GLOBALS['__ktube_actions'][ $ktube_tag ] )
			? count( $GLOBALS['__ktube_actions'][ $ktube_tag ] )
			: 0;
	}
	// Filter registry: add_filter records into $GLOBALS['__ktube_filters'];
	// apply_filters walks the registry in priority order. remove_filter
	// unwires. This unblocks filter-aware tests (Phase 8-B
	// CompliancePagesTest::test_footer_slots_filter_hook_appends_custom_slot).
	$GLOBALS['__ktube_filters'] = array();
	if ( ! function_exists( 'add_filter' ) ) {
		function add_filter( $ktube_tag, $ktube_callback, int $ktube_priority = 10, int $ktube_args = 1 ): bool {
			$GLOBALS['__ktube_filters'][ $ktube_tag ][] = array(
				'priority' => $ktube_priority,
				'callback' => $ktube_callback,
				'args'     => $ktube_args,
			);
			return true;
		}
	}
	if ( ! function_exists( 'remove_filter' ) ) {
		function remove_filter( $ktube_tag, $ktube_callback, int $ktube_priority = 10 ): bool {
			if ( empty( $GLOBALS['__ktube_filters'][ $ktube_tag ] ) ) {
				return false;
			}
			foreach ( $GLOBALS['__ktube_filters'][ $ktube_tag ] as $ktube_i => $ktube_entry ) {
				if ( $ktube_entry['priority'] === $ktube_priority && $ktube_entry['callback'] === $ktube_callback ) {
					unset( $GLOBALS['__ktube_filters'][ $ktube_tag ][ $ktube_i ] );
					$GLOBALS['__ktube_filters'][ $ktube_tag ] = array_values( $GLOBALS['__ktube_filters'][ $ktube_tag ] );
					return true;
				}
			}
			return false;
		}
	}
	function add_theme_support() {}
	function load_theme_textdomain() {}
	function add_image_size() {}
	function register_nav_menus() {}
	function add_editor_style() {}
	function bloginfo( $k ) { return 'ktube'; }
	function get_bloginfo( $k, $d = '' ) { return 'ktube'; }
	function get_template_directory() { return realpath( __DIR__ . '/../../' ); }
	function get_template_directory_uri() { return 'https://example.test/wp-content/themes/ktube/'; }
	function wp_get_theme() { return new class { public function get($k) { return '0.1.0'; } }; }
	function wp_enqueue_script() {}
	function wp_enqueue_style() {}
	function wp_add_inline_script( $handle, $data = '', $position = 'after' ) {
		$GLOBALS['__ktube_inline_scripts'][] = array( 'handle' => $handle, 'data' => $data, 'position' => $position );
	}
	function wp_add_inline_style( $handle, $css = '' ) {
		$GLOBALS['__ktube_inline_styles'][] = array( 'handle' => $handle, 'css' => $css );
	}
	function wp_register_script() {}
	function wp_register_style() {}
	function wp_style_is( $handle, $list = 'enqueued' ) {
		return ! empty( $GLOBALS['__ktube_style_states'][ $handle ][ $list ] );
	}
	// Test-controllable style enqueue/registration state bag. Mirrors wp_style_is.
	function set_style_state_test( string $ktube_handle, string $ktube_list, bool $ktube_value ): void {
		$GLOBALS['__ktube_style_states'][ $ktube_handle ][ $ktube_list ] = $ktube_value;
	}
	function __( $s, $d = '' ) { return $s; }
	function _e( $s, $d = '' ) { echo $s; }
	function _n( $s, $p, $n, $d = '' ) { return ( 1 === (int) $n ) ? $s : $p; }
	function esc_attr__( $s, $d = '' ) { return $s; }
	function esc_html__( $s, $d = '' ) { return $s; }
	function esc_html_e( $s, $d = '' ) { echo esc_html( $s ); }
	function esc_html( $s ) { return htmlspecialchars( (string) $s, ENT_QUOTES ); }
	function esc_attr( $s ) { return htmlspecialchars( (string) $s, ENT_QUOTES ); }
	function esc_url( $s ) { return $s; }
	function esc_url_raw( $s ) { return (string) $s; }
	function esc_js( $s ) { return $s; }
	function sanitize_key( $s ) { return preg_replace( '/[^a-z0-9_\-]/', '', strtolower( (string) $s ) ); }
	function wp_json_encode( $v, $flags = 0 ) { return json_encode( $v, $flags ); }
	function is_admin() { return false; }
	function wp_doing_ajax() { return false; }
	function wp_doing_cron() { return false; }
	function is_singular( $ktube_type = '' ) {
		// Test-controllable via $GLOBALS['__ktube_test_is_singular_types']
		// (array of post types). Default false (matches no-call real WP).
		$ktube_types = $GLOBALS['__ktube_test_is_singular_types'] ?? array();
		if ( empty( $ktube_type ) ) {
			return ! empty( $GLOBALS['__ktube_test_is_singular'] );
		}
		if ( is_array( $ktube_type ) ) {
			foreach ( $ktube_type as $ktube_one ) {
				if ( in_array( (string) $ktube_one, $ktube_types, true ) ) {
					return true;
				}
			}
			return false;
		}
		return in_array( (string) $ktube_type, $ktube_types, true );
	}
	function is_front_page() {
		// Test-controllable via $GLOBALS['__ktube_test_is_front_page']
		// (bool). Defaults false (matches no-call real WP).
		return ! empty( $GLOBALS['__ktube_test_is_front_page'] );
	}
	function is_home() {
		// Test-controllable via $GLOBALS['__ktube_test_is_home'] (bool).
		return ! empty( $GLOBALS['__ktube_test_is_home'] );
	}
	function set_is_front_page_test( bool $ktube_value ): void {
		$GLOBALS['__ktube_test_is_front_page'] = $ktube_value;
	}
	function set_is_home_test( bool $ktube_value ): void {
		$GLOBALS['__ktube_test_is_home'] = $ktube_value;
	}
	function reset_request_state_test(): void {
		$GLOBALS['__ktube_test_is_front_page']      = false;
		$GLOBALS['__ktube_test_is_home']            = false;
		$GLOBALS['__ktube_test_is_singular']        = false;
		$GLOBALS['__ktube_test_is_singular_types']  = array();
	}
	function get_post_thumbnail_id( $ktube_post_id = null ) {
		$ktube_id = $ktube_post_id ?: get_the_ID();
		return $GLOBALS['__ktube_post_thumbnails'][ (int) $ktube_id ] ?? 0;
	}
	function get_the_ID() {
		// Phase 9d schema tests use this via get_post_thumbnail_id's
		// default branch when no post id is passed. Returns null in
		// the stub so the thumbnail map stays empty (matches real WP
		// returning 0 when no post is queried).
		return 0;
	}
	function set_post_thumbnail_test( int $ktube_post_id, int $ktube_thumb_id ): void {
		$GLOBALS['__ktube_post_thumbnails'][ $ktube_post_id ] = $ktube_thumb_id;
	}
	// wp_get_upload_dir — Phase 14 perf test harness stub. Returns an
	// empty basedir/baseurl so image-forms test paths that don't override
	// the upload_dir filter early-bail inside ktube_prepend_modern_format_candidates
	// without touching the disk. Tests that need a fixture directory set
	// the `upload_dir` filter to point at it.
	if ( ! function_exists( 'wp_get_upload_dir' ) ) {
		function wp_get_upload_dir() {
			$ktube_out = array(
				'basedir' => '',
				'baseurl' => '',
				'error'   => false,
			);
			return apply_filters( 'upload_dir', $ktube_out );
		}
	}
	function reset_post_thumbnails_test(): void {
		$GLOBALS['__ktube_post_thumbnails'] = array();
	}
	$GLOBALS['__ktube_post_thumbnails'] = array();
	function wp_get_attachment_image_url( $ktube_attachment_id, $ktube_size = 'full' ) {
		if ( ! $ktube_attachment_id ) {
			return '';
		}
		return 'https://example.test/wp-content/uploads/' . (int) $ktube_attachment_id . '-' . sanitize_key( (string) $ktube_size ) . '.jpg';
	}
	function the_author_meta( $k, $id ) { return 'Test Author'; }
	// Phase 9e — Article schema author field. The pre-Phase-9e
	// ktube_render_blog_article_schema() ALREADY called this function but
	// the boot never had a stub, so the author path was latent-untested.
	// Exposed now because BlogArticleSchemaTest exercises author/publisher
	// on every test method.
	function get_the_author_meta( $ktube_key = '', $ktube_user_id = 0 ) {
		$ktube_user_id = (int) $ktube_user_id;
		if ( 'display_name' === $ktube_key || '' === $ktube_key ) {
			return 'Test Author';
		}
		if ( 'ID' === $ktube_key ) {
			return $ktube_user_id > 0 ? (string) $ktube_user_id : '1';
		}
		if ( 'user_email' === $ktube_key ) {
			return 'author@example.test';
		}
		return '';
	}
	function get_author_posts_url( $id ) { return 'https://example.test/author/' . $id; }
	function get_the_date( $f = '', $post = null ) {
		$ktube_raw = is_object( $post ) && isset( $post->post_date ) ? (string) $post->post_date : '';
		if ( '' === $ktube_raw ) {
			return '' !== $f ? '' : '2026-01-01';
		}
		$ktube_ts = strtotime( $ktube_raw );
		if ( false === $ktube_ts ) {
			return '2026-01-01';
		}
		return '' === $f ? gmdate( 'F j, Y', $ktube_ts ) : gmdate( $f, $ktube_ts );
	}
	function get_the_modified_date( $f = '', $post = null ) {
		$ktube_raw = is_object( $post ) && isset( $post->post_modified ) ? (string) $post->post_modified : (string) get_the_date( 'c', $post );
		if ( '' === $ktube_raw ) {
			return '' !== $f ? '' : '2026-01-01';
		}
		$ktube_ts = strtotime( $ktube_raw );
		if ( false === $ktube_ts ) {
			return '2026-01-01';
		}
		return '' === $f ? gmdate( 'F j, Y', $ktube_ts ) : gmdate( $f, $ktube_ts );
	}
	function get_the_title( $post ) { return is_object( $post ) ? ( $post->post_title ?? 'Test' ) : 'Test'; }
	function get_permalink( $post ) {
		// Accept either a WP_Post object OR an int|WP_Post id (real WP
		// accepts both via get_post() coercion; phase 8-B compliance
		// helpers pass int ids directly).
		if ( is_object( $post ) && isset( $post->ID ) && (int) $post->ID > 0 ) {
			// Deterministic permalinks per fixture id so tests can assert
			// url patterns across archives. ID 0 returns '' so defensive
			// guards in theme code paths (e.g. mainEntityOfPage guards)
			// can be tested without factory-rigged fixtures.
			return 'https://example.test/?p=' . (int) $post->ID;
		}
		if ( is_numeric( $post ) && (int) $post > 0 ) {
			// Int|WP_Post id path — phase 8-B uses (int) id; also real WP
			// coerces int→WP_Post before returning.
			return 'https://example.test/?p=' . (int) $post;
		}
		return '';
	}
	function get_the_excerpt( $post ) { return is_object( $post ) ? ( $post->post_excerpt ?? 'Test excerpt' ) : 'Test excerpt'; }
	function wp_print_inline_script_tag( $tag, $attrs = array() ) {
		echo '<script' . ( isset( $attrs['type'] ) ? ' type="' . esc_attr( $attrs['type'] ) . '"' : '' ) . '>' . $tag . '</script>';
	}
	function home_url( $path = '/' ) { return 'https://example.test' . ( $path ?: '/' ); }
	function get_template_part() {}
	function wp_reset_postdata() {}
	function get_post( $ktube_id = null ) {
		if ( null === $ktube_id ) {
			return null;
		}
		return $GLOBALS['__ktube_posts'][ (int) $ktube_id ] ?? null;
	}
	function maybe_unserialize( $data ) {
		if ( is_string( $data ) ) {
			$result = @unserialize( $data, array( 'allowed_classes' => false ) );
			if ( $result !== false || $data === 'b:0;' ) {
				return $result;
			}
		}
		return $data;
	}
	function wp_get_post_terms() { return array(); }
	// Phase 9e — Article schema keywords helper. Real WP's get_the_terms()
	// returns `array|WP_Error` of WP_Term objects for `( $post, $taxonomy )`;
	// the stub honors the (post_id, taxonomy) key into
	// $GLOBALS['__ktube_post_terms'] and returns an array of name-only
	// objects. We accept WP_Post OR int id (real WP coerces).
	if ( ! function_exists( 'get_the_terms' ) ) {
		function get_the_terms( $ktube_post, $ktube_taxonomy ) {
			$ktube_id = 0;
			if ( is_object( $ktube_post ) && isset( $ktube_post->ID ) ) {
				$ktube_id = (int) $ktube_post->ID;
			} elseif ( is_numeric( $ktube_post ) ) {
				$ktube_id = (int) $ktube_post;
			} else {
				return array();
			}
			if ( '' === $ktube_taxonomy ) {
				return array();
			}
			return $GLOBALS['__ktube_post_terms'][ $ktube_id ][ $ktube_taxonomy ] ?? array();
		}
	}
	function set_post_terms_test( int $ktube_post_id, string $ktube_taxonomy, array $ktube_term_names ): void {
		$ktube_terms = array();
		foreach ( $ktube_term_names as $ktube_name ) {
			$ktube_terms[] = (object) array(
				'name'     => (string) $ktube_name,
				'slug'     => sanitize_key( (string) $ktube_name ),
				'term_id'  => count( $ktube_terms ) + 1,
			);
		}
		$GLOBALS['__ktube_post_terms'][ $ktube_post_id ][ $ktube_taxonomy ] = $ktube_terms;
	}
	function reset_post_terms_test(): void {
		$GLOBALS['__ktube_post_terms'] = array();
	}
	$GLOBALS['__ktube_post_terms'] = array();
	function is_wp_error( $v ) { return false; }
	function get_post_meta( $id, $key, $single = false ) {
		$ktube_value = $GLOBALS['__ktube_post_meta'][ (int) $id ][ $key ] ?? '';
		return $single ? $ktube_value : array( $ktube_value );
	}
	function set_post_meta_test( int $ktube_post_id, string $ktube_key, $ktube_value ): void {
		$GLOBALS['__ktube_post_meta'][ $ktube_post_id ][ $ktube_key ] = $ktube_value;
	}
	function reset_post_meta_test(): void {
		$GLOBALS['__ktube_post_meta'] = array();
	}
	$GLOBALS['__ktube_post_meta'] = array();
	function wp_strip_all_tags( $ktube_str ) {
		$ktube_str = (string) $ktube_str;
		// Real WP's wp_strip_all_tags() strips content INSIDE <script> /
		// <style> / <noscript> tags too (it carries its own regex inside
		// WP source, not just native strip_tags). We mirror that here so
		// schema.php's articleBody extraction matches production. Plain-
		// text excerpts (PrivacySummaryTest, VideoObjectSchemaTest) pass
		// through unchanged because none of them put <script> in the
		// excerpt fixture — the regex no-ops.
		$ktube_str = preg_replace( '#<(script|style|noscript)\b[^>]*>.*?</\1\s*>#mis', '', $ktube_str );
		return trim( strip_tags( $ktube_str ) );
	}
	// Phase 8-A 2026-06-21 — sanitize_text_field / sanitize_textarea_field
	// stubs. Customizer sanitize_callback closures on ktube_home_h1 and
	// ktube_home_description reference these. The closures are stored in
	// $GLOBALS via add_setting() recording and never invoked by tests on
	// the registration path; the stubs exist so a future test that runs
	// `apply_filters` over the closures doesn't fatal.
	if ( ! function_exists( 'sanitize_text_field' ) ) {
		function sanitize_text_field( $ktube_str ) {
			$ktube_str = (string) $ktube_str;
			$ktube_str = strip_tags( $ktube_str );
			$ktube_str = preg_replace( '/[\r\n\t ]+/', ' ', $ktube_str );
			return trim( $ktube_str );
		}
	}
	if ( ! function_exists( 'sanitize_textarea_field' ) ) {
		function sanitize_textarea_field( $ktube_str ) {
			$ktube_str = (string) $ktube_str;
			$ktube_str = strip_tags( $ktube_str );
			$ktube_str = preg_replace( '/[\t ]+/', ' ', $ktube_str );
			return trim( $ktube_str );
		}
	}
	// Phase 6-C: GDPR redirect stub. Records the redirect target in a
	// global so tests can assert it without actually halting the runner.
	$GLOBALS['__ktube_last_redirect'] = null;
	function wp_redirect( $ktube_location, int $ktube_status = 302 ): bool {
		$GLOBALS['__ktube_last_redirect'] = array( 'location' => (string) $ktube_location, 'status' => $ktube_status );
		return true;
	}
	function reset_redirect_test(): void {
		$GLOBALS['__ktube_last_redirect'] = null;
	}
	// apply_filters walks the $GLOBALS['__ktube_filters'] registry in
	// priority order (lower first; ties broken by insertion order to
	// mirror WP's stable ordering at equal priority); the final
	// filtered value wins. External WP plumbing autoloaded later still
	// wins (function_exists guard).
	if ( ! function_exists( 'apply_filters' ) ) {
		function apply_filters( $ktube_tag, $ktube_value, ...$ktube_args ) {
			if ( empty( $GLOBALS['__ktube_filters'][ $ktube_tag ] ) ) {
				return $ktube_value;
			}
			$ktube_entries = $GLOBALS['__ktube_filters'][ $ktube_tag ];
			// Stable sort: priority asc, then insertion order asc.
			// We assoc-sort by index for ties because usort is unstable.
			$ktube_paired = array();
			foreach ( $ktube_entries as $ktube_i => $ktube_entry ) {
				$ktube_paired[] = array( $ktube_entry['priority'], $ktube_i, $ktube_entry );
			}
			usort( $ktube_paired, static function ( $a, $b ): int {
				return $a[0] <=> $b[0] ?: $a[1] <=> $b[1];
			} );
			foreach ( $ktube_paired as $ktube_pair ) {
				[ , , $ktube_entry ] = $ktube_pair;
				$ktube_cb_args = array_slice( $ktube_args, 0, (int) $ktube_entry['args'] );
				$ktube_value    = $ktube_entry['callback']( $ktube_value, ...$ktube_cb_args );
			}
			return $ktube_value;
		}
	}
	function reset_filters_test(): void {
		$GLOBALS['__ktube_filters'] = array();
	}
	if ( ! function_exists( 'do_action' ) ) {
		function do_action( $ktube_tag, ...$ktube_args ) {}
	}

	// Record post-meta registration calls so tests can assert which keys
	// each CPT exposes via REST. Yields to a real register_post_meta if
	// one is autoloaded (e.g. via Brain\\Monkey or a WP load).
	$GLOBALS['__ktube_meta'] = array();
	if ( ! function_exists( 'register_post_meta' ) ) {
		function register_post_meta( $ktube_type, $ktube_key, $ktube_args = array() ) {
			$ktube_args = is_array( $ktube_args ) ? $ktube_args : array();
			$GLOBALS['__ktube_meta'][ $ktube_type ][ $ktube_key ] = $ktube_args;
			return true;
		}
	}
	function reset_meta_registry_test(): void {
		$GLOBALS['__ktube_meta'] = array();
	}

	// ---- Class stubs ---------------------------------------------------

	class WP_Customize_Manager {
		public $ktube_sections = array();
		public $ktube_settings = array();
		public $ktube_controls = array();
		public function add_section( $ktube_id = '', $ktube_args = array() ): void {
			$this->ktube_sections[ (string) $ktube_id ] = is_array( $ktube_args ) ? $ktube_args : array();
		}
		public function add_setting( $ktube_id = '', $ktube_args = array() ): void {
			$this->ktube_settings[ (string) $ktube_id ] = is_array( $ktube_args ) ? $ktube_args : array();
		}
		/**
		 * Accept either a WP_Customize_Control OBJECT (color control style)
		 * or a string id + array (text/number/textarea style). Real WP
		 * has both call shapes; the stub records both so homepage +
		 * color-section tests can introspect.
		 */
		public function add_control( $ktube_id_or_obj = null, $ktube_args = array() ): void {
			if ( is_object( $ktube_id_or_obj ) ) {
				$ktube_label = is_object( $ktube_id_or_obj ) && isset( $ktube_id_or_obj->label )
					? (string) $ktube_id_or_obj->label
					: '';
				$this->ktube_controls[] = array(
					'object'   => true,
					'class'    => get_class( $ktube_id_or_obj ),
					'label'    => $ktube_label,
					'args'     => is_array( $ktube_args ) ? $ktube_args : array(),
				);
			} else {
				$this->ktube_controls[ (string) $ktube_id_or_obj ] = is_array( $ktube_args ) ? $ktube_args : array();
			}
		}
		// Phase 8-A 2026-06-21 homepage tests build a fresh manager per
		// test method to avoid bleed-over; this method resets without
		// re-instantiating (cheaper + preserves property refs).
		public function ktube_reset_customizer_registry(): void {
			$this->ktube_sections = array();
			$this->ktube_settings = array();
			$this->ktube_controls = array();
		}
	}
	class WP_Customize_Color_Control {
		public $label = '';
		public function __construct( $ktube_manager = null, $ktube_id = '', $ktube_args = array() ) {
			$this->label = is_array( $ktube_args ) && isset( $ktube_args['label'] ) ? (string) $ktube_args['label'] : '';
		}
	}
	// #[\AllowDynamicProperties] silences PHP 8.2+ deprecation notices
	// for the dynamic property assignment in set_post_test() (post_date,
	// post_modified, post_name, post_title, etc). Also matches real WP
	// core's WP_Post which historically allowed dynamic fields until
	// WP 6.x formally typed them.
	#[\AllowDynamicProperties]
	class WP_Post { public $ID = 0; public $post_title = ''; public $post_excerpt = ''; public $post_author = 1; public $post_type = 'post'; }
	class WP_Query {
		public $posts = array();
		public function __construct( $args = array() ) {
			// Honor the three filters that ktube_resolve_privacy_page_id()
			// passes: name, post_type, post_status. The stub ignores all
			// other args (posts_per_page, no_found_rows, fields).
			if ( empty( $GLOBALS['__ktube_posts'] ) ) {
				return;
			}
			$ktube_limit = isset( $args['posts_per_page'] ) ? (int) $args['posts_per_page'] : -1;
			foreach ( $GLOBALS['__ktube_posts'] as $ktube_id => $ktube_post ) {
				$ktube_pass = true;
				if ( isset( $args['name'] )       && $args['name']       !== $ktube_post->post_name   ) { $ktube_pass = false; }
				if ( isset( $args['post_type'] )  && $args['post_type']  !== $ktube_post->post_type  ) { $ktube_pass = false; }
				if ( isset( $args['post_status'] ) && $args['post_status'] !== $ktube_post->post_status ) { $ktube_pass = false; }
				if ( $ktube_pass ) {
					$this->posts[] = $ktube_id;
					if ( $ktube_limit > 0 && count( $this->posts ) >= $ktube_limit ) {
						break;
					}
				}
			}
		}
		public function have_posts() { return count( $this->posts ) > 0; }
		public function the_post() {}
	}

	// ---- Privacy-page require flow stubs -------------------------------

	// New stubs that the privacy require flow needs but the minimal block above
	// didn't already define. Note: get_post / get_post_meta / wp_kses /
	// is_singular / get_post_thumbnail_id / maybe_unserialize are all in the
	// minimal stubs block ABOVE; do NOT re-declare here (would fatal on load).

	function is_page( $page = '' ) { return false; }
	function get_queried_object_id() { return 0; }
	function the_title() {}
	function the_content() {}
	function in_the_loop() { return false; }
	function have_posts() { return false; }
	function the_ID() { return 0; }
	function post_class( $class = '' ) { echo 'class="' . esc_attr( trim( (string) $class ) ) . '"'; }
	// Test-controllable post map. Provides a fake DB so resolve_privacy_page_id()
	// can verify both the configured-id branch and the slug fallback branch.
	$GLOBALS['__ktube_posts'] = array();
	function set_post_test( int $ktube_id, array $ktube_props ): void {
		$ktube_defaults = array( 'post_type' => 'page', 'post_status' => 'publish', 'post_name' => 'privacy', 'post_title' => 'Test', 'post_author' => 1 );
		// Instantiate a real WP_Post rather than casting an array to
		// stdClass; schema.php uses instanceof WP_Post to gate
		// VideoObject/Blog/ImageGallery emit and a stdClass silently
		// fails the guard, returning [] (which then triggers "Undefined
		// array key" cascades everywhere).
		$ktube_post          = new WP_Post();
		$ktube_post->ID      = $ktube_id;
		foreach ( array_merge( $ktube_defaults, $ktube_props ) as $ktube_k => $ktube_v ) {
			$ktube_post->{$ktube_k} = $ktube_v;
		}
		$GLOBALS['__ktube_posts'][ $ktube_id ] = $ktube_post;
	}

	// ---- Load the theme function(s) under test --------------------------

	$ktube_test_customizer = __DIR__ . '/../../includes/customizer.php';
	if ( ! file_exists( $ktube_test_customizer ) ) {
		throw new RuntimeException( 'Cannot locate includes/customizer.php — bootstrapper is in the wrong place.' );
	}
	require_once $ktube_test_customizer;
	$ktube_test_age_gate = __DIR__ . '/../../includes/age-gate.php';
	if ( file_exists( $ktube_test_age_gate ) ) {
		require_once $ktube_test_age_gate;
	}
	$ktube_test_privacy = __DIR__ . '/../../includes/privacy.php';
	if ( file_exists( $ktube_test_privacy ) ) {
		require_once $ktube_test_privacy;
	}
	$ktube_test_mass_importer = __DIR__ . '/../../includes/wps-compat/mass-importer.php';
	if ( file_exists( $ktube_test_mass_importer ) ) {
		require_once $ktube_test_mass_importer;
	}
	$ktube_test_importer_adapter = __DIR__ . '/../../includes/wps-compat/importer-adapter.php';
	if ( file_exists( $ktube_test_importer_adapter ) ) {
		require_once $ktube_test_importer_adapter;
	}
	$ktube_test_db_indexes = __DIR__ . '/../../includes/wps-compat/db-indexes.php';
	if ( file_exists( $ktube_test_db_indexes ) ) {
		require_once $ktube_test_db_indexes;
	}
	$ktube_test_meta = __DIR__ . '/../../includes/meta.php';
	if ( file_exists( $ktube_test_meta ) ) {
		require_once $ktube_test_meta;
	}
	$ktube_test_compliance_pages = __DIR__ . '/../../includes/compliance-pages.php';
	if ( file_exists( $ktube_test_compliance_pages ) ) {
		require_once $ktube_test_compliance_pages;
	}
	$ktube_test_gdpr = __DIR__ . '/../../includes/gdpr.php';
	if ( file_exists( $ktube_test_gdpr ) ) {
		require_once $ktube_test_gdpr;
	}
	$ktube_test_critical_css = __DIR__ . '/../../includes/critical-css.php';
	if ( file_exists( $ktube_test_critical_css ) ) {
		require_once $ktube_test_critical_css;
	}
	$ktube_test_image_formats = __DIR__ . '/../../includes/image-formats.php';
	if ( file_exists( $ktube_test_image_formats ) ) {
		require_once $ktube_test_image_formats;
	}
	$ktube_test_player_depth = __DIR__ . '/../../includes/player-depth.php';
	if ( file_exists( $ktube_test_player_depth ) ) {
		require_once $ktube_test_player_depth;
	}

	$ktube_test_schema = __DIR__ . '/../../includes/seo/schema.php';
	if ( file_exists( $ktube_test_schema ) ) {
		require_once $ktube_test_schema;
	}
}
