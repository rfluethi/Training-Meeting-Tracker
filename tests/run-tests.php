<?php
/**
 * Lightweight tests for the plugin classes.
 *
 * Run with: php tests/run-tests.php
 * Exit code: 0 on success, 1 on failure.
 *
 * @package TrainingMeetingTracker\Tests
 */

require_once __DIR__ . '/bootstrap.php';

$tmtracker_fixture_json = file_get_contents( __DIR__ . '/fixtures/sitzungen.json' );
$tmtracker_fixture      = json_decode( $tmtracker_fixture_json, true );
$tmtracker_plugin_dir   = dirname( __DIR__ );

/**
 * Validated fixture data, as the fetcher would hand it to the renderer.
 */
function tmtracker_test_data() {
	global $tmtracker_fixture;
	return tmtracker_test_call( new TMTracker_Fetcher(), 'validate_schema', array( $tmtracker_fixture ) );
}

/**
 * Shortcode attributes with every section switched on.
 */
function tmtracker_test_atts( array $overrides = array() ) {
	return array_merge(
		array(
			'show_upcoming'    => true,
			'show_in_progress' => true,
			'show_past'        => true,
			'years'            => 'all',
			'heading_level'    => 2,
		),
		$overrides
	);
}

echo "\nTraining Meeting Tracker tests\n";
echo str_repeat( '-', 40 ) . "\n";

// ---- Fetcher: schema and normalisation ----

tmtracker_test( 'schema v2 fixture validates into three lists', function () {
	$data = tmtracker_test_data();

	tmtracker_assert_equals( 2, $data['schema_version'] );
	tmtracker_assert_equals( 2, count( $data['upcoming_sessions'] ) );
	tmtracker_assert_equals( 1, count( $data['in_progress_sessions'] ) );
	tmtracker_assert_equals( 3, count( $data['past_sessions'] ) );
} );

tmtracker_test( 'retired schema v1 is rejected', function () {
	$result = tmtracker_test_call( new TMTracker_Fetcher(), 'validate_schema', array(
		array(
			'schema_version' => 1,
			'next_session'   => array(
				'title'        => 'Sitzung 2026-06-05',
				'session_date' => '2026-06-05',
			),
		),
	) );

	tmtracker_assert_equals( null, $result );
} );

tmtracker_test( 'unknown schema (v99) is rejected', function () {
	$result = tmtracker_test_call( new TMTracker_Fetcher(), 'validate_schema', array( array( 'schema_version' => 99 ) ) );

	tmtracker_assert_equals( null, $result );
} );

tmtracker_test( 'invalid session_time is dropped (HH:MM only)', function () {
	$fetcher = new TMTracker_Fetcher();
	$base    = array(
		'title'        => 'Sitzung',
		'session_date' => '2026-06-05',
	);

	foreach ( array( '18:00:00', '24:00', '12:60', "18:00\n", '8:00' ) as $time ) {
		$out = tmtracker_test_call( $fetcher, 'normalize_session', array( $base + array( 'session_time' => $time ), true ) );
		tmtracker_assert_equals( '', $out['session_time'], 'time ' . json_encode( $time ) );
	}

	$out = tmtracker_test_call( $fetcher, 'normalize_session', array( $base + array( 'session_time' => '23:59' ), true ) );
	tmtracker_assert_equals( '23:59', $out['session_time'] );
} );

tmtracker_test( 'records without a real calendar date are dropped', function () {
	$fetcher = new TMTracker_Fetcher();

	foreach ( array( '2026-13-01', '2026-02-30', '26-06-05', "2026-06-05\n", '', array( '2026-06-05' ) ) as $date ) {
		$out = tmtracker_test_call( $fetcher, 'normalize_session', array( array( 'session_date' => $date ), true ) );
		tmtracker_assert_equals( null, $out, 'date ' . json_encode( $date ) );
	}
} );

tmtracker_test( 'only http and https URLs survive, other values become empty', function () {
	$fetcher = new TMTracker_Fetcher();
	$base    = array( 'session_date' => '2026-06-05' );

	$out = tmtracker_test_call( $fetcher, 'normalize_session', array( $base + array( 'url' => 'https://example.com/issues/1' ), true ) );
	tmtracker_assert_equals( 'https://example.com/issues/1', $out['url'] );

	foreach ( array( 'javascript:alert(1)', 'ftp://example.com/file', array( 'https://example.com' ) ) as $url ) {
		$out = tmtracker_test_call( $fetcher, 'normalize_session', array( $base + array( 'url' => $url ), true ) );
		tmtracker_assert_equals( '', $out['url'], 'url ' . json_encode( $url ) );
	}
} );

tmtracker_test( 'non-scalar title and invalid minutes_date become empty strings', function () {
	$out = tmtracker_test_call( new TMTracker_Fetcher(), 'normalize_session', array(
		array(
			'title'        => array( 'not', 'a', 'string' ),
			'session_date' => '2026-06-05',
			'minutes_date' => '2026-06-31',
		),
		false,
	) );

	tmtracker_assert_equals( '', $out['title'] );
	tmtracker_assert_equals( '', $out['minutes_date'] );
} );

// ---- Fetcher: cache, fallback and pause after a failure ----

tmtracker_test( 'successful fetch is cached and recorded', function () {
	global $tmtracker_fixture_json;
	tmtracker_test_http( 200, $tmtracker_fixture_json );

	$fetcher = new TMTracker_Fetcher();
	$first   = $fetcher->get_data();
	$second  = $fetcher->get_data();
	$status  = $fetcher->get_status();

	tmtracker_assert_equals( false, $first['stale'] );
	tmtracker_assert_equals( null, $first['error'] );
	tmtracker_assert_equals( 2, count( $first['data']['upcoming_sessions'] ) );
	tmtracker_assert_equals( $first['data'], $second['data'] );
	tmtracker_assert_equals( 1, $GLOBALS['tmtracker_test_http_calls'], 'second call must come from the cache' );
	tmtracker_assert_equals( true, $status['last_success'] > 0 );
	tmtracker_assert_equals( '', $status['last_error'] );
	tmtracker_assert_equals( $first['data'], get_option( TMTRACKER_OPTION_LAST_GOOD ) );
} );

tmtracker_test( 'failed fetch falls back to the last good data and pauses', function () {
	global $tmtracker_fixture_json;
	$fetcher = new TMTracker_Fetcher();

	tmtracker_test_http( 200, $tmtracker_fixture_json );
	$fetcher->get_data();
	delete_transient( TMTRACKER_TRANSIENT_DATA );

	tmtracker_test_http( 503, '' );
	$failed = $fetcher->get_data();
	$again  = $fetcher->get_data();
	$status = $fetcher->get_status();

	tmtracker_assert_equals( true, $failed['stale'] );
	tmtracker_assert_contains( '503', (string) $failed['error'] );
	tmtracker_assert_equals( 2, count( $failed['data']['upcoming_sessions'] ) );
	tmtracker_assert_equals( true, $again['stale'] );
	tmtracker_assert_equals( 2, $GLOBALS['tmtracker_test_http_calls'], 'no new request during the pause' );
	tmtracker_assert_contains( '503', $status['last_error'] );
	tmtracker_assert_equals( true, $status['last_success'] > 0, 'last success must survive a failure' );
} );

tmtracker_test( 'forced refresh ignores the pause and clears it on success', function () {
	global $tmtracker_fixture_json;
	$fetcher = new TMTracker_Fetcher();

	tmtracker_test_http( 500, '' );
	$fetcher->get_data();

	tmtracker_test_http( 200, $tmtracker_fixture_json );
	$forced = $fetcher->get_data( true );

	tmtracker_assert_equals( false, $forced['stale'] );
	tmtracker_assert_equals( 2, $GLOBALS['tmtracker_test_http_calls'] );
	tmtracker_assert_equals( false, get_transient( TMTRACKER_TRANSIENT_BACKOFF ) );
	tmtracker_assert_equals( '', $fetcher->get_status()['last_error'] );
} );

tmtracker_test( 'failed fetch without fallback returns no data', function () {
	$GLOBALS['tmtracker_test_http_response'] = new WP_Error( 'http_request_failed', 'Could not resolve host' );

	$result = ( new TMTracker_Fetcher() )->get_data();

	tmtracker_assert_equals( null, $result['data'] );
	tmtracker_assert_equals( false, $result['stale'] );
	tmtracker_assert_equals( 'Could not resolve host', $result['error'] );
} );

tmtracker_test( 'a failure without error message still pauses', function () {
	$GLOBALS['tmtracker_test_http_response'] = new WP_Error( 'http_request_failed', '' );
	$fetcher                                 = new TMTracker_Fetcher();

	$fetcher->get_data();
	$fetcher->get_data();

	tmtracker_assert_equals( 1, $GLOBALS['tmtracker_test_http_calls'] );
} );

tmtracker_test( 'invalid JSON and unknown schema count as failed fetches', function () {
	$fetcher = new TMTracker_Fetcher();

	tmtracker_test_http( 200, '<html>not json</html>' );
	$result = $fetcher->get_data( true );
	tmtracker_assert_contains( 'JSON', (string) $result['error'] );

	tmtracker_test_http( 200, '{"schema_version": 99}' );
	$result = $fetcher->get_data( true );
	tmtracker_assert_contains( 'schema', (string) $result['error'] );
} );

tmtracker_test( 'clear_cache drops cache and pause but keeps the fallback', function () {
	global $tmtracker_fixture_json;
	$fetcher = new TMTracker_Fetcher();

	tmtracker_test_http( 200, $tmtracker_fixture_json );
	$fetcher->get_data();
	set_transient( TMTRACKER_TRANSIENT_BACKOFF, 'some error' );

	$fetcher->clear_cache();

	tmtracker_assert_equals( false, get_transient( TMTRACKER_TRANSIENT_DATA ) );
	tmtracker_assert_equals( false, get_transient( TMTRACKER_TRANSIENT_BACKOFF ) );
	tmtracker_assert_equals( true, is_array( get_option( TMTRACKER_OPTION_LAST_GOOD ) ) );
} );

tmtracker_test( 'cache duration is clamped to 1 to 168 hours', function () {
	tmtracker_assert_equals( 1, TMTracker_Fetcher::clamp_cache_hours( 0 ) );
	tmtracker_assert_equals( 1, TMTracker_Fetcher::clamp_cache_hours( -5 ) );
	tmtracker_assert_equals( 12, TMTracker_Fetcher::clamp_cache_hours( 12 ) );
	tmtracker_assert_equals( 168, TMTracker_Fetcher::clamp_cache_hours( 999 ) );
} );

// ---- Renderer ----

tmtracker_test( 'renderer emits upcoming section with both items and aria attrs', function () {
	$html = ( new TMTracker_Renderer() )->render( tmtracker_test_data(), tmtracker_test_atts() );

	tmtracker_assert_contains( 'class="tmtracker-upcoming"', $html );
	tmtracker_assert_contains( 'aria-labelledby="tmtracker-upcoming-heading"', $html );
	tmtracker_assert_contains( 'id="tmtracker-upcoming-heading"', $html );
	tmtracker_assert_contains( 'Upcoming meetings', $html );
	tmtracker_assert_contains( '>Sitzung<', $html, 'title from the Veranstaltung field' );
	tmtracker_assert_contains( '>Workshop<', $html );
	tmtracker_assert_contains( 'datetime="2026-06-05T18:00"', $html );
	tmtracker_assert_contains( 'aria-hidden="true"', $html, 'separator dot must be aria-hidden' );
} );

tmtracker_test( 'renderer emits in_progress section', function () {
	$html = ( new TMTracker_Renderer() )->render( tmtracker_test_data(), tmtracker_test_atts() );

	tmtracker_assert_contains( 'class="tmtracker-in-progress"', $html );
	tmtracker_assert_contains( 'aria-labelledby="tmtracker-in-progress-heading"', $html );
	tmtracker_assert_contains( 'Meetings in progress', $html );
	tmtracker_assert_contains( 'datetime="2026-05-10T20:00"', $html );
} );

tmtracker_test( 'renderer emits past section grouped by year, descending', function () {
	$html = ( new TMTracker_Renderer() )->render( tmtracker_test_data(), tmtracker_test_atts() );

	tmtracker_assert_contains( 'aria-labelledby="tmtracker-past-heading"', $html );
	tmtracker_assert_contains( 'Minutes', $html );
	tmtracker_assert_contains( '>2026<', $html );
	tmtracker_assert_contains( '>2025<', $html );

	$pos_2026 = strpos( $html, '>2026<' );
	$pos_2025 = strpos( $html, '>2025<' );
	if ( false === $pos_2026 || false === $pos_2025 || $pos_2026 > $pos_2025 ) {
		throw new RuntimeException( 'Years are not in descending order.' );
	}
} );

tmtracker_test( 'past entries show only the meeting date, not the minutes date', function () {
	$html = ( new TMTracker_Renderer() )->render( tmtracker_test_data(), tmtracker_test_atts() );

	tmtracker_assert_contains( '<span class="tmtracker-meta"><time datetime="2026-04-10">10.04.2026</time></span>', $html );
	tmtracker_assert_not_contains( 'Meeting from', $html );
	tmtracker_assert_not_contains( '2026-04-12', $html, 'minutes_date must not be rendered' );
} );

tmtracker_test( 'show_upcoming=false hides the upcoming section', function () {
	$html = ( new TMTracker_Renderer() )->render( tmtracker_test_data(), tmtracker_test_atts( array( 'show_upcoming' => false ) ) );

	tmtracker_assert_not_contains( 'tmtracker-upcoming-heading', $html );
	tmtracker_assert_contains( 'tmtracker-in-progress-heading', $html );
	tmtracker_assert_contains( 'tmtracker-past-heading', $html );
} );

tmtracker_test( 'years="1" trims past to the most recent year', function () {
	$html = ( new TMTracker_Renderer() )->render(
		tmtracker_test_data(),
		tmtracker_test_atts(
			array(
				'show_upcoming'    => false,
				'show_in_progress' => false,
				'years'            => 1,
			)
		)
	);

	tmtracker_assert_contains( '>2026<', $html );
	tmtracker_assert_not_contains( '>2025<', $html );
} );

tmtracker_test( 'missing data renders prepared-state notice with role=status', function () {
	$html = ( new TMTracker_Renderer() )->render( null, array() );

	tmtracker_assert_contains( 'role="status"', $html );
	tmtracker_assert_contains( 'Meeting data is being prepared', $html );
} );

tmtracker_test( 'upcoming section without entries says that no date is set', function () {
	$data                      = tmtracker_test_data();
	$data['upcoming_sessions'] = array();
	$html                      = ( new TMTracker_Renderer() )->render( $data, tmtracker_test_atts() );

	tmtracker_assert_contains( 'id="tmtracker-upcoming-heading"', $html );
	tmtracker_assert_contains( '<p class="tmtracker-no-date">No date set yet.</p>', $html );
	tmtracker_assert_contains( 'tmtracker-in-progress-heading', $html );
	tmtracker_assert_not_contains( '<ul class="tmtracker-list"></ul>', $html );
} );

tmtracker_test( 'valid data without visible meetings renders a notice', function () {
	$renderer = new TMTracker_Renderer();
	$empty    = array(
		'upcoming_sessions'    => array(),
		'in_progress_sessions' => array(),
		'past_sessions'        => array(),
	);

	$html = $renderer->render( $empty, tmtracker_test_atts() );
	tmtracker_assert_contains( 'No date set yet.', $html, 'the upcoming section stays visible' );
	tmtracker_assert_not_contains( 'no meetings to show', $html );

	tmtracker_assert_contains( 'no meetings to show', $renderer->render( $empty, tmtracker_test_atts( array( 'show_upcoming' => false ) ) ) );
	tmtracker_assert_contains(
		'no meetings to show',
		$renderer->render(
			tmtracker_test_data(),
			tmtracker_test_atts(
				array(
					'show_upcoming'    => false,
					'show_in_progress' => false,
					'show_past'        => false,
				)
			)
		)
	);
} );

tmtracker_test( 'stale notice is shown to administrators only', function () {
	$renderer = new TMTracker_Renderer();

	$html = $renderer->render( tmtracker_test_data(), tmtracker_test_atts(), true, 'HTTP error 503' );
	tmtracker_assert_not_contains( 'tmtracker-stale-notice', $html, 'visitor' );
	tmtracker_assert_contains( 'Upcoming meetings', $html, 'visitors still see the last good data' );

	$GLOBALS['tmtracker_test_is_admin'] = true;
	$html                               = $renderer->render( tmtracker_test_data(), tmtracker_test_atts(), true, 'HTTP error 503' );
	tmtracker_assert_contains( '<p class="tmtracker-stale-notice" role="status">', $html, 'administrator' );
	tmtracker_assert_contains( 'last successful state', $html );
} );

tmtracker_test( 'error without data is shown to administrators only', function () {
	$renderer = new TMTracker_Renderer();

	tmtracker_assert_not_contains( 'tmtracker-error', $renderer->render( null, array(), false, 'HTTP error 404' ) );

	$GLOBALS['tmtracker_test_is_admin'] = true;
	tmtracker_assert_contains( 'HTTP error 404', $renderer->render( null, array(), false, 'HTTP error 404' ) );
} );

tmtracker_test( 'rendered HTML has no rel="noopener" and no target="_blank"', function () {
	$html = ( new TMTracker_Renderer() )->render( tmtracker_test_data(), tmtracker_test_atts() );

	tmtracker_assert_not_contains( 'rel="noopener', $html );
	tmtracker_assert_not_contains( 'target="_blank"', $html );
} );

tmtracker_test( 'renderer does not set inline link colors (theme stays in charge)', function () {
	$html = ( new TMTracker_Renderer() )->render( tmtracker_test_data(), tmtracker_test_atts() );

	tmtracker_assert_not_contains( 'style="color', $html );
} );

tmtracker_test( 'second list on a page gets its own heading IDs', function () {
	$renderer = new TMTracker_Renderer();
	$first    = $renderer->render( tmtracker_test_data(), tmtracker_test_atts() );
	$second   = $renderer->render( tmtracker_test_data(), tmtracker_test_atts() );

	tmtracker_assert_contains( 'id="tmtracker-past-heading"', $first );
	tmtracker_assert_contains( 'id="tmtracker-past-heading-2"', $second );
	tmtracker_assert_contains( 'aria-labelledby="tmtracker-past-heading-2"', $second );
	tmtracker_assert_not_contains( 'id="tmtracker-past-heading"', $second );
} );

tmtracker_test( 'heading_level shifts section and year headings and is clamped', function () {
	$renderer = new TMTracker_Renderer();

	$html = $renderer->render( tmtracker_test_data(), tmtracker_test_atts( array( 'heading_level' => 3 ) ) );
	tmtracker_assert_contains( '<h3 class="tmtracker-heading"', $html );
	tmtracker_assert_contains( '<h4 class="tmtracker-year">', $html );
	tmtracker_assert_not_contains( '<h2', $html );

	$html = $renderer->render( tmtracker_test_data(), tmtracker_test_atts( array( 'heading_level' => 9 ) ) );
	tmtracker_assert_contains( '<h5 class="tmtracker-heading"', $html );
	tmtracker_assert_contains( '<h6 class="tmtracker-year">', $html );

	$html = $renderer->render( tmtracker_test_data(), tmtracker_test_atts( array( 'heading_level' => 1 ) ) );
	tmtracker_assert_contains( '<h2 class="tmtracker-heading"', $html );
} );

tmtracker_test( 'link carries the date in its accessible name', function () {
	$html = ( new TMTracker_Renderer() )->render( tmtracker_test_data(), tmtracker_test_atts() );

	tmtracker_assert_contains( 'aria-label="Sitzung, 05.06.2026"', $html );
	tmtracker_assert_contains( 'aria-label="Workshop, 01.12.2025"', $html );
} );

tmtracker_test( 'empty title falls back to a generic name instead of an empty link', function () {
	$data = array(
		'upcoming_sessions' => array(
			array(
				'title'        => '',
				'session_date' => '2026-06-05',
				'session_time' => '',
				'url'          => 'https://example.com/issues/1',
			),
		),
	);
	$html = ( new TMTracker_Renderer() )->render( $data, tmtracker_test_atts() );

	tmtracker_assert_contains( '>Meeting</a>', $html );
	tmtracker_assert_not_contains( '></a>', $html );
} );

tmtracker_test( 'titles are escaped', function () {
	$data = array(
		'upcoming_sessions' => array(
			array(
				'title'        => '<script>alert(1)</script>',
				'session_date' => '2026-06-05',
				'session_time' => '',
				'url'          => '',
			),
		),
	);
	$html = ( new TMTracker_Renderer() )->render( $data, tmtracker_test_atts() );

	tmtracker_assert_not_contains( '<script>', $html );
	tmtracker_assert_contains( '&lt;script&gt;', $html );
} );

// ---- Shortcode attributes ----

tmtracker_test( 'shortcode attributes: defaults', function () {
	$shortcode = new TMTracker_Shortcode( new TMTracker_Fetcher(), new TMTracker_Renderer() );

	tmtracker_assert_equals(
		array(
			'show_upcoming'    => true,
			'show_in_progress' => true,
			'show_past'        => true,
			'years'            => 'all',
			'heading_level'    => 2,
		),
		$shortcode->normalize_atts( '' )
	);
} );

tmtracker_test( 'shortcode attributes: values, legacy alias and clamping', function () {
	$shortcode = new TMTracker_Shortcode( new TMTracker_Fetcher(), new TMTracker_Renderer() );

	$atts = $shortcode->normalize_atts(
		array(
			'show_upcoming'    => 'true',
			'show_next'        => 'false',
			'show_in_progress' => 'No',
			'show_past'        => ' JA ',
			'years'            => '3',
			'heading_level'    => '4',
		)
	);
	tmtracker_assert_equals( false, $atts['show_upcoming'], 'show_next wins when it is set' );
	tmtracker_assert_equals( false, $atts['show_in_progress'] );
	tmtracker_assert_equals( true, $atts['show_past'] );
	tmtracker_assert_equals( 3, $atts['years'] );
	tmtracker_assert_equals( 4, $atts['heading_level'] );

	$atts = $shortcode->normalize_atts(
		array(
			'years'         => '-2',
			'heading_level' => 'abc',
		)
	);
	tmtracker_assert_equals( 'all', $atts['years'] );
	tmtracker_assert_equals( 2, $atts['heading_level'] );
} );

tmtracker_test( 'shortcode callback fetches, renders and enqueues the stylesheet', function () {
	global $tmtracker_fixture_json;
	tmtracker_test_http( 200, $tmtracker_fixture_json );

	$shortcode = new TMTracker_Shortcode( new TMTracker_Fetcher(), new TMTracker_Renderer() );
	$html      = $shortcode->render(
		array(
			'show_upcoming' => 'false',
			'years'         => '1',
			'heading_level' => '3',
		)
	);

	tmtracker_assert_not_contains( 'tmtracker-upcoming', $html );
	tmtracker_assert_contains( '<h3 class="tmtracker-heading" id="tmtracker-past-heading">', $html );
	tmtracker_assert_contains( '>2026<', $html );
	tmtracker_assert_not_contains( '>2025<', $html );
	tmtracker_assert_equals( array( 'tmtracker-frontend' ), $GLOBALS['tmtracker_test_enqueued_styles'] );
} );

tmtracker_test( 'shortcode callback without data renders the prepared-state notice', function () {
	tmtracker_test_http( 404, '' );

	$shortcode = new TMTracker_Shortcode( new TMTracker_Fetcher(), new TMTracker_Renderer() );
	$html      = $shortcode->render( '' );

	tmtracker_assert_contains( 'tmtracker-empty', $html );
	tmtracker_assert_not_contains( 'tmtracker-error', $html, 'the error is for administrators only' );
} );

// ---- Settings ----

tmtracker_test( 'settings: valid values are kept, cache hours are clamped', function () {
	$settings = new TMTracker_Settings( new TMTracker_Fetcher() );

	$out = $settings->sanitize(
		array(
			'json_url'    => '  https://example.com/data/sitzungen.json ',
			'cache_hours' => '999',
		)
	);
	tmtracker_assert_equals( 'https://example.com/data/sitzungen.json', $out['json_url'] );
	tmtracker_assert_equals( 168, $out['cache_hours'] );

	$out = $settings->sanitize( array( 'cache_hours' => 'abc' ) );
	tmtracker_assert_equals( 1, $out['cache_hours'] );
	tmtracker_assert_equals( array(), $GLOBALS['tmtracker_test_settings_errors'] );
} );

tmtracker_test( 'settings: empty URL and non-array input fall back to the defaults', function () {
	$settings = new TMTracker_Settings( new TMTracker_Fetcher() );

	$out = $settings->sanitize( array( 'json_url' => '   ' ) );
	tmtracker_assert_equals( TMTRACKER_DEFAULT_JSON_URL, $out['json_url'] );

	$out = $settings->sanitize( 'garbage' );
	tmtracker_assert_equals( TMTRACKER_DEFAULT_JSON_URL, $out['json_url'] );
	tmtracker_assert_equals( TMTRACKER_DEFAULT_CACHE_HOURS, $out['cache_hours'] );
} );

tmtracker_test( 'settings: a key missing in the input keeps its saved value', function () {
	update_option(
		TMTRACKER_OPTION_SETTINGS,
		array(
			'json_url'    => 'https://example.com/saved.json',
			'cache_hours' => 48,
		)
	);
	$settings = new TMTracker_Settings( new TMTracker_Fetcher() );

	$out = $settings->sanitize( array( 'cache_hours' => 6 ) );
	tmtracker_assert_equals( 'https://example.com/saved.json', $out['json_url'] );
	tmtracker_assert_equals( 6, $out['cache_hours'] );

	$out = $settings->sanitize( array( 'json_url' => 'https://example.com/new.json' ) );
	tmtracker_assert_equals( 'https://example.com/new.json', $out['json_url'] );
	tmtracker_assert_equals( 48, $out['cache_hours'] );
} );

tmtracker_test( 'settings: invalid URL keeps the saved URL and is reported once', function () {
	update_option( TMTRACKER_OPTION_SETTINGS, array( 'json_url' => 'https://example.com/saved.json' ) );
	$settings = new TMTracker_Settings( new TMTracker_Fetcher() );

	foreach ( array( 'javascript:alert(1)', 'ftp://example.com/sitzungen.json', 'not a url' ) as $url ) {
		$out = $settings->sanitize( array( 'json_url' => $url ) );
		tmtracker_assert_equals( 'https://example.com/saved.json', $out['json_url'], 'input ' . $url );
	}

	tmtracker_assert_equals( array( 'tmtracker_invalid_url' ), $GLOBALS['tmtracker_test_settings_errors'] );
} );

// ---- Consistency of files that have to change together ----

tmtracker_test( 'uninstall.php removes every option and transient the plugin defines', function () use ( $tmtracker_plugin_dir ) {
	$main      = file_get_contents( $tmtracker_plugin_dir . '/training-meeting-tracker.php' );
	$uninstall = file_get_contents( $tmtracker_plugin_dir . '/uninstall.php' );

	preg_match_all( "/define\\( 'TMTRACKER_(OPTION|TRANSIENT)_[A-Z_]+', '([a-z_]+)' \\);/", $main, $matches, PREG_SET_ORDER );
	tmtracker_assert_equals( 5, count( $matches ), 'number of keys found in the main file' );

	foreach ( $matches as $match ) {
		$call = ( 'OPTION' === $match[1] ? 'delete_option' : 'delete_transient' ) . "( '" . $match[2] . "' )";
		tmtracker_assert_contains( $call, $uninstall );
	}
} );

tmtracker_test( 'bootstrap stubs use the same keys as the plugin', function () use ( $tmtracker_plugin_dir ) {
	$main = file_get_contents( $tmtracker_plugin_dir . '/training-meeting-tracker.php' );

	foreach ( array( 'TMTRACKER_OPTION_SETTINGS', 'TMTRACKER_OPTION_LAST_GOOD', 'TMTRACKER_OPTION_FETCH_STATUS', 'TMTRACKER_TRANSIENT_DATA', 'TMTRACKER_TRANSIENT_BACKOFF' ) as $name ) {
		tmtracker_assert_contains( "define( '" . $name . "', '" . constant( $name ) . "' );", $main );
	}
} );

tmtracker_test( 'translation files cover every string used in the code', function () use ( $tmtracker_plugin_dir ) {
	$used = array();
	foreach ( glob( $tmtracker_plugin_dir . '/includes/*.php' ) as $file ) {
		$tokens = token_get_all( file_get_contents( $file ) );
		$count  = count( $tokens );
		for ( $i = 0; $i < $count; $i++ ) {
			if ( ! is_array( $tokens[ $i ] ) || T_STRING !== $tokens[ $i ][0] || ! in_array( $tokens[ $i ][1], array( '__', 'esc_html__' ), true ) ) {
				continue;
			}
			for ( $j = $i + 1; $j < $count; $j++ ) {
				if ( is_array( $tokens[ $j ] ) && T_CONSTANT_ENCAPSED_STRING === $tokens[ $j ][0] ) {
					$used[ stripslashes( substr( $tokens[ $j ][1], 1, -1 ) ) ] = true;
					break;
				}
			}
		}
	}
	$used = array_keys( $used );
	sort( $used );

	$languages = $tmtracker_plugin_dir . '/languages/';
	$locales   = array( 'de_DE', 'de_DE_formal', 'de_CH', 'de_CH_informal', 'de_AT' );

	foreach ( array_merge( array( '' ), $locales ) as $locale ) {
		$name    = 'training-meeting-tracker' . ( '' === $locale ? '.pot' : '-' . $locale . '.po' );
		$content = file_get_contents( $languages . $name );
		preg_match_all( '/^msgid "(.+)"$/m', $content, $matches );
		$msgids = array_map( 'stripcslashes', $matches[1] );
		sort( $msgids );

		tmtracker_assert_equals( $used, $msgids, $name );

		if ( '' === $locale ) {
			continue;
		}

		tmtracker_assert_contains( '"Language: ' . $locale . '\\n"', $content, $name );
		tmtracker_assert_equals( 1, preg_match_all( '/^msgstr ""$/m', $content ), 'only the header may have an empty msgstr in ' . $name );

		$mo = file_get_contents( $languages . 'training-meeting-tracker-' . $locale . '.mo' );
		tmtracker_assert_contains( 'Language: ' . $locale . "\n", $mo, 'compiled ' . $locale . ' file' );
		foreach ( $used as $msgid ) {
			tmtracker_assert_contains( $msgid . "\0", $mo, 'compiled ' . $locale . ' file' );
		}
	}

	tmtracker_assert_equals(
		count( $locales ) * 2 + 1,
		count( glob( $languages . 'training-meeting-tracker*' ) ),
		'no translation file without a test'
	);
} );

echo str_repeat( '-', 40 ) . "\n";
printf(
	"Passed: %d   Failed: %d\n",
	TMTracker_Test_Result::$pass,
	TMTracker_Test_Result::$fail
);

exit( TMTracker_Test_Result::$fail > 0 ? 1 : 0 );
