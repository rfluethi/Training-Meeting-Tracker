<?php
/**
 * Lightweight integration tests for TMT_Renderer and TMT_Fetcher.
 *
 * Run with: php tests/run-tests.php
 * Exit code: 0 on success, 1 on failure.
 *
 * @package TrainingMeetingTracker\Tests
 */

require_once __DIR__ . '/bootstrap.php';

$fixture = json_decode( file_get_contents( __DIR__ . '/fixtures/sitzungen.json' ), true );

$fetcher    = new TMT_Fetcher();
$reflection = new ReflectionClass( $fetcher );
$validate   = $reflection->getMethod( 'validate_schema' );
$validate->setAccessible( true );
$data = $validate->invoke( $fetcher, $fixture );

$renderer = new TMT_Renderer();

$all_on = array(
	'show_upcoming'    => true,
	'show_in_progress' => true,
	'show_past'        => true,
	'years'            => 'all',
);

echo "\nTMT lightweight tests\n";
echo str_repeat( '-', 40 ) . "\n";

tmt_test( 'schema v2 fixture validates into three lists', function () use ( $data ) {
	tmt_assert_equals( 2, $data['schema_version'] );
	tmt_assert_equals( 2, count( $data['upcoming_sessions'] ) );
	tmt_assert_equals( 1, count( $data['in_progress_sessions'] ) );
	tmt_assert_equals( 3, count( $data['past_sessions'] ) );
} );

tmt_test( 'schema v1 is auto-migrated to v2', function () use ( $fetcher ) {
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
	tmt_assert_equals( 2, $result['schema_version'] );
	tmt_assert_equals( 1, count( $result['upcoming_sessions'] ) );
	tmt_assert_equals( 0, count( $result['in_progress_sessions'] ) );
	tmt_assert_equals( 1, count( $result['past_sessions'] ) );
} );

tmt_test( 'unknown schema (v99) is rejected', function () use ( $fetcher ) {
	$ref = new ReflectionClass( $fetcher );
	$m   = $ref->getMethod( 'validate_schema' );
	$m->setAccessible( true );

	$result = $m->invoke( $fetcher, array( 'schema_version' => 99 ) );
	if ( null !== $result ) {
		throw new RuntimeException( 'Schema v99 must be rejected.' );
	}
} );

tmt_test( 'invalid session_time is dropped (HH:MM only)', function () use ( $fetcher ) {
	$ref = new ReflectionClass( $fetcher );
	$m   = $ref->getMethod( 'normalize_session' );
	$m->setAccessible( true );

	$out = $m->invoke( $fetcher, array(
		'title'        => 'Sitzung',
		'session_date' => '2026-06-05',
		'session_time' => '18:00:00', // invalid, has seconds
	), true );

	tmt_assert_equals( '', $out['session_time'] );
} );

tmt_test( 'renderer emits upcoming section with both items and aria attrs', function () use ( $renderer, $data, $all_on ) {
	$html = $renderer->render( $data, $all_on );

	tmt_assert_contains( 'class="tmt-upcoming"', $html );
	tmt_assert_contains( 'aria-labelledby="tmt-upcoming-heading"', $html );
	tmt_assert_contains( 'id="tmt-upcoming-heading"', $html );
	tmt_assert_contains( 'Upcoming meetings', $html );
	tmt_assert_contains( '>Sitzung<', $html, 'title from event_name field' );
	tmt_assert_contains( '>Workshop<', $html );
	tmt_assert_contains( 'datetime="2026-06-05T18:00"', $html );
	tmt_assert_contains( 'aria-hidden="true"', $html, 'separator dot must be aria-hidden' );
} );

tmt_test( 'renderer emits in_progress section', function () use ( $renderer, $data, $all_on ) {
	$html = $renderer->render( $data, $all_on );

	tmt_assert_contains( 'class="tmt-in-progress"', $html );
	tmt_assert_contains( 'aria-labelledby="tmt-in-progress-heading"', $html );
	tmt_assert_contains( 'Meetings in progress', $html );
	tmt_assert_contains( 'datetime="2026-05-10T20:00"', $html );
} );

tmt_test( 'renderer emits past section grouped by year, descending', function () use ( $renderer, $data, $all_on ) {
	$html = $renderer->render( $data, $all_on );

	tmt_assert_contains( 'aria-labelledby="tmt-past-heading"', $html );
	tmt_assert_contains( 'Minutes', $html );
	tmt_assert_contains( '>2026<', $html );
	tmt_assert_contains( '>2025<', $html );

	$pos_2026 = strpos( $html, '>2026<' );
	$pos_2025 = strpos( $html, '>2025<' );
	if ( false === $pos_2026 || false === $pos_2025 || $pos_2026 > $pos_2025 ) {
		throw new RuntimeException( 'Years are not in descending order.' );
	}

	tmt_assert_contains( 'Minutes from', $html );
	tmt_assert_contains( 'datetime="2026-04-12"', $html );
} );

tmt_test( 'show_upcoming=false hides the upcoming section', function () use ( $renderer, $data ) {
	$html = $renderer->render( $data, array(
		'show_upcoming'    => false,
		'show_in_progress' => true,
		'show_past'        => true,
		'years'            => 'all',
	) );

	tmt_assert_not_contains( 'tmt-upcoming-heading', $html );
	tmt_assert_contains( 'tmt-in-progress-heading', $html );
	tmt_assert_contains( 'tmt-past-heading', $html );
} );

tmt_test( 'years="1" trims past to the most recent year', function () use ( $renderer, $data ) {
	$html = $renderer->render( $data, array(
		'show_upcoming'    => false,
		'show_in_progress' => false,
		'show_past'        => true,
		'years'            => 1,
	) );

	tmt_assert_contains( '>2026<', $html );
	tmt_assert_not_contains( '>2025<', $html );
} );

tmt_test( 'empty data renders prepared-state notice with role=status', function () use ( $renderer ) {
	$html = $renderer->render( null, array() );

	tmt_assert_contains( 'role="status"', $html );
	tmt_assert_contains( 'Session data is being prepared', $html );
} );

tmt_test( 'stale flag emits fallback notice with role=status', function () use ( $renderer, $data, $all_on ) {
	$html = $renderer->render( $data, $all_on, true, null );

	tmt_assert_contains( 'tmt-stale-notice', $html );
	tmt_assert_contains( 'role="status"', $html );
	tmt_assert_contains( 'last successful state', $html );
} );

tmt_test( 'rendered HTML has no rel="noopener" and no target="_blank"', function () use ( $renderer, $data, $all_on ) {
	$html = $renderer->render( $data, $all_on );

	tmt_assert_not_contains( 'rel="noopener', $html );
	tmt_assert_not_contains( 'target="_blank"', $html );
} );

tmt_test( 'renderer does not set inline link colors (theme stays in charge)', function () use ( $renderer, $data, $all_on ) {
	$html = $renderer->render( $data, $all_on );

	tmt_assert_not_contains( 'style="color', $html );
} );

echo str_repeat( '-', 40 ) . "\n";
printf(
	"Passed: %d   Failed: %d\n",
	TMT_Test_Result::$pass,
	TMT_Test_Result::$fail
);

exit( TMT_Test_Result::$fail > 0 ? 1 : 0 );
