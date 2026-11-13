<?php
/**
 * Test bootstrap.
 *
 * Provides the minimum of WordPress functions the plugin classes need to run
 * outside of WordPress. We do this on purpose instead of pulling in
 * wp-phpunit, so the tests stay free of heavy infrastructure.
 *
 * The stubs are deliberately simple. Options and transients live in global
 * arrays, and the HTTP layer returns whatever the test put into
 * $GLOBALS['tmtracker_test_http_response'].
 *
 * @package TrainingMeetingTracker\Tests
 */

if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', __DIR__ . '/' );
}

// Plugin constants the source files reference.
define( 'TMTRACKER_VERSION', '0.0.0-test' );
define( 'TMTRACKER_PLUGIN_BASENAME', 'training-meeting-tracker/training-meeting-tracker.php' );
define( 'TMTRACKER_OPTION_SETTINGS', 'tmtracker_settings' );
define( 'TMTRACKER_OPTION_LAST_GOOD', 'tmtracker_last_good_data' );
define( 'TMTRACKER_OPTION_FETCH_STATUS', 'tmtracker_fetch_status' );
define( 'TMTRACKER_TRANSIENT_DATA', 'tmtracker_session_data' );
define( 'TMTRACKER_TRANSIENT_BACKOFF', 'tmtracker_fetch_backoff' );
define( 'TMTRACKER_DEFAULT_JSON_URL', 'https://example.org/default/sitzungen.json' );
define( 'TMTRACKER_DEFAULT_CACHE_HOURS', 12 );
define( 'TMTRACKER_BACKOFF_MINUTES', 10 );
define( 'MINUTE_IN_SECONDS', 60 );
define( 'HOUR_IN_SECONDS', 3600 );

// ---- Translation stubs ----

function __( $text, $domain = null ) {
	return $text;
}
function esc_html__( $text, $domain = null ) {
	return htmlspecialchars( (string) $text, ENT_QUOTES, 'UTF-8' );
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
function esc_url_raw( $url, $protocols = null ) {
	// Only the protocol check of the real function is reproduced.
	$url       = trim( (string) $url );
	$protocols = is_array( $protocols ) ? $protocols : array( 'http', 'https', 'ftp', 'mailto' );
	$scheme    = strtolower( (string) parse_url( $url, PHP_URL_SCHEME ) );

	return in_array( $scheme, $protocols, true ) ? $url : '';
}
function wp_http_validate_url( $url ) {
	// The real function also rejects private and loopback addresses.
	$parts = parse_url( (string) $url );

	if ( empty( $parts['scheme'] ) || empty( $parts['host'] ) ) {
		return false;
	}

	return in_array( $parts['scheme'], array( 'http', 'https' ), true ) ? $url : false;
}

// ---- Option and transient stubs ----

$GLOBALS['tmtracker_test_options']    = array();
$GLOBALS['tmtracker_test_transients'] = array();

function get_option( $key, $default_value = false ) {
	return array_key_exists( $key, $GLOBALS['tmtracker_test_options'] )
		? $GLOBALS['tmtracker_test_options'][ $key ]
		: $default_value;
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

// ---- User and site stubs ----

// Visitor by default. A test sets this to true to act as administrator.
$GLOBALS['tmtracker_test_is_admin'] = false;

function current_user_can( $cap ) {
	return (bool) $GLOBALS['tmtracker_test_is_admin'];
}
function home_url( $path = '/' ) {
	return 'https://example.org' . $path;
}

// ---- HTTP stubs ----

class WP_Error {
	private $message;

	public function __construct( $code = '', $message = '' ) {
		$this->message = $message;
	}

	public function get_error_message() {
		return $this->message;
	}
}

$GLOBALS['tmtracker_test_http_response'] = null;
$GLOBALS['tmtracker_test_http_calls']    = 0;

function is_wp_error( $thing ) {
	return $thing instanceof WP_Error;
}
function wp_safe_remote_get( $url, $args = array() ) {
	++$GLOBALS['tmtracker_test_http_calls'];
	return $GLOBALS['tmtracker_test_http_response'];
}
function wp_remote_retrieve_response_code( $response ) {
	return ( is_array( $response ) && isset( $response['code'] ) ) ? $response['code'] : 0;
}
function wp_remote_retrieve_body( $response ) {
	return ( is_array( $response ) && isset( $response['body'] ) ) ? $response['body'] : '';
}

// ---- Shortcode and settings stubs ----

function shortcode_atts( $pairs, $atts, $shortcode = '' ) {
	$out = array();
	foreach ( $pairs as $name => $default_value ) {
		$out[ $name ] = array_key_exists( $name, (array) $atts ) ? $atts[ $name ] : $default_value;
	}
	return $out;
}

$GLOBALS['tmtracker_test_enqueued_styles'] = array();

function wp_enqueue_style( $handle ) {
	$GLOBALS['tmtracker_test_enqueued_styles'][] = $handle;
}

$GLOBALS['tmtracker_test_settings_errors'] = array();

function add_settings_error( $setting, $code, $message, $type = 'error' ) {
	$GLOBALS['tmtracker_test_settings_errors'][] = $code;
}

// ---- Plugin source loading ----

$tmtracker_includes = dirname( __DIR__ ) . '/includes/';
require_once $tmtracker_includes . 'class-tmtracker-fetcher.php';
require_once $tmtracker_includes . 'class-tmtracker-renderer.php';
require_once $tmtracker_includes . 'class-tmtracker-shortcode.php';
require_once $tmtracker_includes . 'class-tmtracker-settings.php';

// ---- Test helpers ----

/**
 * Resets options, transients, HTTP stub and collected settings errors.
 */
function tmtracker_test_reset() {
	$GLOBALS['tmtracker_test_options']         = array();
	$GLOBALS['tmtracker_test_transients']      = array();
	$GLOBALS['tmtracker_test_http_response']   = null;
	$GLOBALS['tmtracker_test_http_calls']      = 0;
	$GLOBALS['tmtracker_test_enqueued_styles'] = array();
	$GLOBALS['tmtracker_test_settings_errors'] = array();
	$GLOBALS['tmtracker_test_is_admin']        = false;
}

/**
 * Makes the HTTP stub answer with the given status code and body.
 */
function tmtracker_test_http( $code, $body ) {
	$GLOBALS['tmtracker_test_http_response'] = array(
		'code' => $code,
		'body' => $body,
	);
}

/**
 * Calls a private method.
 */
function tmtracker_test_call( $instance, $method, array $args = array() ) {
	$reflection = new ReflectionMethod( $instance, $method );
	$reflection->setAccessible( true );
	return $reflection->invokeArgs( $instance, $args );
}

// ---- Test framework (super minimal) ----

class TMTracker_Test_Result {
	public static $pass = 0;
	public static $fail = 0;

	public static function ok( $name ) {
		++self::$pass;
		echo "  \033[32mPASS\033[0m  $name\n";
	}

	public static function fail( $name, $message ) {
		++self::$fail;
		echo "  \033[31mFAIL\033[0m  $name\n          $message\n";
	}
}

function tmtracker_test( $name, callable $fn ) {
	tmtracker_test_reset();
	try {
		$fn();
		TMTracker_Test_Result::ok( $name );
	} catch ( Throwable $e ) {
		TMTracker_Test_Result::fail( $name, $e->getMessage() );
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

function tmtracker_assert_equals( $expected, $actual, $label = '' ) {
	if ( $expected !== $actual ) {
		throw new RuntimeException(
			'Expected: ' . var_export( $expected, true )
			. "\n          Actual:   " . var_export( $actual, true )
			. ( $label ? "\n          ({$label})" : '' )
		);
	}
}
