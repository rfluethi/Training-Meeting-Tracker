<?php
/**
 * Lightweight integration tests for TMTracker_Renderer and TMTracker_Fetcher.
 *
 * Run with: php tests/run-tests.php
 * Exit code: 0 on success, 1 on failure.
 *
 * @package TrainingMeetingTracker\Tests
 */

require_once __DIR__ . '/bootstrap.php';

$fixture = json_decode( file_get_contents( __DIR__ . '/fixtures/sitzungen.json' ), true );

$fetcher    = new TMTracker_Fetcher();
$reflection = new ReflectionClass( $fetcher );
$validate   = $reflection->getMethod( 'validate_schema' );
$validate->setAccessible( true );
$data = $validate->invoke( $fetcher, $fixture );

$renderer = new TMTracker_Renderer();

$all_on = array(
	'show_upcoming'    => true,
	'show_in_progress' => true,
	'show_past'        => true,
	'years'            => 'all',
);

echo "\nTMT lightweight tests\n";
echo str_repeat( '-', 40 ) . "\n";

tmtracker_test( 'schema v2 fixture validates into three lists', function () use ( $data ) {
	tmtracker_assert_equals( 2, $data['schema_version'] );
	tmtracker_assert_equals( 2, count( $data['upcoming_sessions'] ) );
	tmtracker_assert_equals( 1, count( $data['in_progress_sessions'] ) );
	tmtracker_assert_equals( 3, count( $data['past_sessions'] ) );
} );

tmtracker_test( 'schema v1 is auto-migrated to v2', function () use ( $fetcher ) {
	$ref = new ReflectionClass( $fetcher );
	$m   = $ref->getMethod( 'validate_schema' );
	$m->setAccessible( true );

	$result = $m->invoke( $fetcher, array(
		'schema_version' => 1,
		'next_session'   => array(
			'title'        => 'Sitzung 2026-06-05',
			'session_date' => '2026-06-05',
			'session_time' => '20:00',
			'url'          => 'https://example.com/issues/1',
		),
		'past_sessions'  => array(
			array(
				'title'        => 'Sitzung 2026-04-10',
				'session_date' => '2026-04-10',
				'minutes_date' => '2026-04-12',
				'url'          => 'https://example.com/issues/2',
			),
		),
	) );

	if ( null === $result ) {
		throw new RuntimeException( 'Schema v1 must be auto-migrated, not rejected.' );
	}
	tmtracker_assert_equals( 2, $result['schema_version'] );
	tmtracker_assert_equals( 1, count( $result['upcoming_sessions'] ) );
	tmtracker_assert_equals( 0, count( $result['in_progress_sessions'] ) );
	tmtracker_assert_equals( 1, count( $result['past_sessions'] ) );
} );

tmtracker_test( 'unknown schema (v99) is rejected', function () use ( $fetcher ) {
	$ref = new ReflectionClass( $fetcher );
	$m   = $ref->getMethod( 'validate_schema' );
	$m->setAccessible( true );

	$result = $m->invoke( $fetcher, array( 'schema_version' => 99 ) );
	if ( null !== $result ) {
		throw new RuntimeException( 'Schema v99 must be rejected.' );
	}
} );

tmtracker_test( 'invalid session_time is dropped (HH:MM only)', function () use ( $fetcher ) {
	$ref = new ReflectionClass( $fetcher );
	$m   = $ref->getMethod( 'normalize_session' );
	$m->setAccessible( true );

	$out = $m->invoke( $fetcher, array(
		'title'        => 'Sitzung',
		'session_date' => '2026-06-05',
		'session_time' => '18:00:00', // invalid, has seconds
	), true );

	tmtracker_assert_equals( '', $out['session_time'] );
} );

tmtracker_test( 'renderer emits upcoming section with both items and aria attrs', function () use ( $renderer, $data, $all_on ) {
	$html = $renderer->render( $data, $all_on );

	tmtracker_assert_contains( 'class="tmtracker-upcoming"', $html );
	tmtracker_assert_contains( 'aria-labelledby="tmtracker-upcoming-heading"', $html );
	tmtracker_assert_contains( 'id="tmtracker-upcoming-heading"', $html );
	tmtracker_assert_contains( 'Upcoming meetings', $html );
	tmtracker_assert_contains( '>Sitzung<', $html, 'title from event_name field' );
	tmtracker_assert_contains( '>Workshop<', $html );
	tmtracker_assert_contains( 'datetime="2026-06-05T18:00"', $html );
	tmtracker_assert_contains( 'aria-hidden="true"', $html, 'separator dot must be aria-hidden' );
} );

tmtracker_test( 'renderer emits in_progress section', function () use ( $renderer, $data, $all_on ) {
	$html = $renderer->render( $data, $all_on );

	tmtracker_assert_contains( 'class="tmtracker-in-progress"', $html );
	tmtracker_assert_contains( 'aria-labelledby="tmtracker-in-progress-heading"', $html );
	tmtracker_assert_contains( 'Meetings in progress', $html );
	tmtracker_assert_contains( 'datetime="2026-05-10T20:00"', $html );
} );

tmtracker_test( 'renderer emits past section grouped by year, descending', function () use ( $renderer, $data, $all_on ) {
	$html = $renderer->render( $data, $all_on );

	tmtracker_assert_contains( 'aria-labelledby="tmtracker-past-heading"', $html );
	tmtracker_assert_contains( 'Minutes', $html );
	tmtracker_assert_contains( '>2026<', $html );
	tmtracker_assert_contains( '>2025<', $html );

	$pos_2026 = strpos( $html, '>2026<' );
	$pos_2025 = strpos( $html, '>2025<' );
	if ( false === $pos_2026 || false === $pos_2025 || $pos_2026 > $pos_2025 ) {
		throw new RuntimeException( 'Years are not in descending order.' );
	}

	tmtracker_assert_contains( 'Meeting from', $html );
	tmtracker_assert_contains( 'datetime="2026-04-12"', $html );
} );

tmtracker_test( 'show_upcoming=false hides the upcoming section', function () use ( $renderer, $data ) {
	$html = $renderer->render( $data, array(
		'show_upcoming'    => false,
		'show_in_progress' => true,
		'show_past'        => true,
		'years'            => 'all',
	) );

	tmtracker_assert_not_contains( 'tmtracker-upcoming-heading', $html );
	tmtracker_assert_contains( 'tmtracker-in-progress-heading', $html );
	tmtracker_assert_contains( 'tmtracker-past-heading', $html );
} );

tmtracker_test( 'years="1" trims past to the most recent year', function () use ( $renderer, $data ) {
	$html = $renderer->render( $data, array(
		'show_upcoming'    => false,
		'show_in_progress' => false,
		'show_past'        => true,
		'years'            => 1,
	) );

	tmtracker_assert_contains( '>2026<', $html );
	tmtracker_assert_not_contains( '>2025<', $html );
} );

tmtracker_test( 'empty data renders prepared-state notice with role=status', function () use ( $renderer ) {
	$html = $renderer->render( null, array() );

	tmtracker_assert_contains( 'role="status"', $html );
	tmtracker_assert_contains( 'Session data is being prepared', $html );
} );

tmtracker_test( 'stale flag emits fallback notice with role=status', function () use ( $renderer, $data, $all_on ) {
	$html = $renderer->render( $data, $all_on, true, null );

	tmtracker_assert_contains( 'tmtracker-stale-notice', $html );
	tmtracker_assert_contains( 'role="status"', $html );
	tmtracker_assert_contains( 'last successful state', $html );
} );

tmtracker_test( 'rendered HTML has no rel="noopener" and no target="_blank"', function () use ( $renderer, $data, $all_on ) {
	$html = $renderer->render( $data, $all_on );

	tmtracker_assert_not_contains( 'rel="noopener', $html );
	tmtracker_assert_not_contains( 'target="_blank"', $html );
} );

tmtracker_test( 'renderer does not set inline link colors (theme stays in charge)', function () use ( $renderer, $data, $all_on ) {
	$html = $renderer->render( $data, $all_on );

	tmtracker_assert_not_contains( 'style="color', $html );
} );

echo str_repeat( '-', 40 ) . "\n";
printf(
	"Passed: %d   Failed: %d\n",
	TMTRACKER_Test_Result::$pass,
	TMTRACKER_Test_Result::$fail
);

exit( TMTRACKER_Test_Result::$fail > 0 ? 1 : 0 );
