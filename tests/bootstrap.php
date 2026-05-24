<?php
/**
 * Test bootstrap.
 *
 * Provides the minimum of WordPress functions that TMTracker_Renderer and
 * TMTracker_Fetcher need to run outside of WordPress. We do this on purpose
 * instead of pulling in wp-phpunit, so the tests stay free of heavy
 * infrastructure.
 *
 * Scope of the stubs: just the escaping/translation/date helpers used by
 * the renderer. The Fetcher HTTP path is not exercised here. For that
 * we test the schema validator with a JSON fixture loaded from disk.
 *
 * @package TrainingMeetingTracker\Tests
 */

if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', __DIR__ . '/' );
}

// Plugin constants the source files reference at load time.
if ( ! defined( 'TMTRACKER_VERSION' ) ) {
	define( 'TMTRACKER_VERSION', '0.0.0-test' );
}
if ( ! defined( 'TMTRACKER_OPTION_SETTINGS' ) ) {
	define( 'TMTRACKER_OPTION_SETTINGS', 'tmtracker_settings' );
}
if ( ! defined( 'TMTRACKER_OPTION_LAST_GOOD' ) ) {
	define( 'TMTRACKER_OPTION_LAST_GOOD', 'tmtracker_last_good_data' );
}
if ( ! defined( 'TMTRACKER_TRANSIENT_DATA' ) ) {
	define( 'TMTRACKER_TRANSIENT_DATA', 'tmtracker_session_data' );
}
if ( ! defined( 'TMTRACKER_DEFAULT_JSON_URL' ) ) {
	define( 'TMTRACKER_DEFAULT_JSON_URL', 'https://example.invalid/sitzungen.json' );
}
if ( ! defined( 'TMTRACKER_DEFAULT_CACHE_HOURS' ) ) {
	define( 'TMTRACKER_DEFAULT_CACHE_HOURS', 12 );
}
if ( ! defined( 'HOUR_IN_SECONDS' ) ) {
	define( 'HOUR_IN_SECONDS', 3600 );
}

// ---- Translation stubs ----

function __( $text, $domain = null ) {
	return $text;
}
function esc_html__( $text, $domain = null ) {
	return htmlspecialchars( (string) $text, ENT_QUOTES, 'UTF-8' );
}
function esc_attr__( $text, $domain = null ) {
	return htmlspecialchars( (string) $text, ENT_QUOTES, 'UTF-8' );
}
function esc_html_e( $text, $domain = null ) {
	echo esc_html__( $text, $domain );
}

// ---- Escaping stubs ----

function esc_html( $text ) {
	return htmlspecialchars( (string) $text, ENT_QUOTES, 'UTF-8' );
}
function esc_attr( $text ) {
	return htmlspecialchars( (string) $text, ENT_QUOTES, 'UTF-8' );
}
function esc_url( $url ) {
	// Trivial pass-through for tests; the real esc_url() is far stricter.
	return htmlspecialchars( (string) $url, ENT_QUOTES, 'UTF-8' );
}
function esc_url_raw( $url ) {
	return (string) $url;
}

// ---- Option/transient stubs ----

$GLOBALS['tmtracker_test_options']    = array();
$GLOBALS['tmtracker_test_transients'] = array();

function get_option( $key, $default = false ) {
	return array_key_exists( $key, $GLOBALS['tmtracker_test_options'] )
		? $GLOBALS['tmtracker_test_options'][ $key ]
		: $default;
}
function update_option( $key, $value, $autoload = null ) {
	$GLOBALS['tmtracker_test_options'][ $key ] = $value;
	return true;
}
function get_transient( $key ) {
	return array_key_exists( $key, $GLOBALS['tmtracker_test_transients'] )
		? $GLOBALS['tmtracker_test_transients'][ $key ]
		: false;
}
function set_transient( $key, $value, $expiration = 0 ) {
	$GLOBALS['tmtracker_test_transients'][ $key ] = $value;
	return true;
}
function delete_transient( $key ) {
	unset( $GLOBALS['tmtracker_test_transients'][ $key ] );
	return true;
}

// ---- User/permission stubs ----

function current_user_can( $cap ) {
	return false;
}

// ---- Date stubs ----

function wp_date( $format, $timestamp = null, $timezone = null ) {
	return gmdate( $format, $timestamp ?? time() );
}
function home_url( $path = '/' ) {
	return 'http://example.invalid' . $path;
}

// ---- Hook stubs (no-ops; the renderer does not use them) ----

if ( ! function_exists( 'is_wp_error' ) ) {
	function is_wp_error( $thing ) { return false; }
}
if ( ! function_exists( 'wp_remote_get' ) ) {
	function wp_remote_get( $url, $args = array() ) { return array(); }
}
if ( ! function_exists( 'wp_remote_retrieve_response_code' ) ) {
	function wp_remote_retrieve_response_code( $r ) { return 200; }
}
if ( ! function_exists( 'wp_remote_retrieve_body' ) ) {
	function wp_remote_retrieve_body( $r ) { return ''; }
}

// ---- Plugin source loading ----

$plugin_dir = dirname( __DIR__ ) . '/includes/';
require_once $plugin_dir . 'class-tmtracker-fetcher.php';
require_once $plugin_dir . 'class-tmtracker-renderer.php';

// ---- Test framework (super minimal) ----

class TMTRACKER_Test_Result {
	public static $pass  = 0;
	public static $fail  = 0;
	public static $names = array();

	public static function ok( $name ) {
		self::$pass++;
		echo "  \033[32mPASS\033[0m  $name\n";
	}

	public static function fail( $name, $message ) {
		self::$fail++;
		self::$names[] = $name;
		echo "  \033[31mFAIL\033[0m  $name\n          $message\n";
	}
}

function tmtracker_test( $name, callable $fn ) {
	try {
		$fn();
		TMTRACKER_Test_Result::ok( $name );
	} catch ( Throwable $e ) {
		TMTRACKER_Test_Result::fail( $name, $e->getMessage() );
	}
}

function tmtracker_assert_contains( $needle, $haystack, $label = '' ) {
	if ( strpos( $haystack, $needle ) === false ) {
		throw new RuntimeException( "Expected to find:\n            " . $needle . ( $label ? "\n          ({$label})" : '' ) );
	}
}

function tmtracker_assert_not_contains( $needle, $haystack, $label = '' ) {
	if ( strpos( $haystack, $needle ) !== false ) {
		throw new RuntimeException( "Did not expect:\n            " . $needle . ( $label ? "\n          ({$label})" : '' ) );
	}
}

function tmtracker_assert_equals( $expected, $actual ) {
	if ( $expected !== $actual ) {
		throw new RuntimeException( "Expected: " . var_export( $expected, true ) . "\n          Actual:   " . var_export( $actual, true ) );
	}
}
