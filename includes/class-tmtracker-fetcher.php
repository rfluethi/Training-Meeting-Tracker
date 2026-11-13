<?php
/**
 * Fetcher: loads sitzungen.json from the data branch of the team repository.
 *
 * @package TrainingMeetingTracker
 */

defined( 'ABSPATH' ) || exit;

/**
 * Fetches and caches the JSON data.
 */
class TMTracker_Fetcher {

	/**
	 * Supported schema major version.
	 *
	 * Version 2 (2026-05) uses three lists: upcoming_sessions, in_progress_sessions,
	 * past_sessions. Title is taken from the issue body field "Veranstaltung:".
	 */
	const SUPPORTED_SCHEMA_VERSION = 2;

	/**
	 * Lower bound of the cache duration in hours.
	 */
	const MIN_CACHE_HOURS = 1;

	/**
	 * Upper bound of the cache duration in hours (one week).
	 */
	const MAX_CACHE_HOURS = 168;

	/**
	 * Timeout of the HTTP request in seconds.
	 *
	 * The request runs while a page is rendered, so a visitor waits for it.
	 * Five seconds is the WordPress default. A slower answer counts as a
	 * failed fetch, which is harmless: the last good data is shown.
	 */
	const REQUEST_TIMEOUT = 5;

	/**
	 * Current JSON URL from the plugin settings.
	 *
	 * @return string
	 */
	public function get_url() {
		$settings = (array) get_option( TMTRACKER_OPTION_SETTINGS, array() );
		$url      = isset( $settings['json_url'] ) ? (string) $settings['json_url'] : '';

		if ( '' === $url ) {
			$url = TMTRACKER_DEFAULT_JSON_URL;
		}

		return $url;
	}

	/**
	 * Current cache duration in hours, clamped to the supported range.
	 *
	 * @return int
	 */
	public function get_cache_hours() {
		$settings = (array) get_option( TMTRACKER_OPTION_SETTINGS, array() );
		$hours    = isset( $settings['cache_hours'] ) ? (int) $settings['cache_hours'] : TMTRACKER_DEFAULT_CACHE_HOURS;

		return self::clamp_cache_hours( $hours );
	}

	/**
	 * Clamps a cache duration to the supported range.
	 *
	 * @param int $hours Cache duration in hours.
	 * @return int
	 */
	public static function clamp_cache_hours( $hours ) {
		return min( self::MAX_CACHE_HOURS, max( self::MIN_CACHE_HOURS, (int) $hours ) );
	}

	/**
	 * Returns the meeting data: from cache, fresh fetch, or fallback.
	 *
	 * After a failed fetch the source is not contacted again for
	 * TMTRACKER_BACKOFF_MINUTES minutes. Without that pause every page view
	 * would wait for a new request while the source is down.
	 *
	 * @param bool $force_refresh Bypass the cache and the pause after a failure.
	 * @return array{data: array|null, stale: bool, error: string|null}
	 */
	public function get_data( $force_refresh = false ) {
		if ( ! $force_refresh ) {
			$cached = get_transient( TMTRACKER_TRANSIENT_DATA );
			if ( is_array( $cached ) ) {
				return array(
					'data'  => $cached,
					'stale' => false,
					'error' => null,
				);
			}

			// The transient holds the error message, which may be empty.
			$recent_error = get_transient( TMTRACKER_TRANSIENT_BACKOFF );
			if ( false !== $recent_error ) {
				return $this->get_fallback( is_string( $recent_error ) ? $recent_error : '' );
			}
		}

		$fresh = $this->fetch_from_remote();

		if ( null === $fresh['data'] ) {
			$error = (string) $fresh['error'];

			set_transient(
				TMTRACKER_TRANSIENT_BACKOFF,
				$error,
				MINUTE_IN_SECONDS * TMTRACKER_BACKOFF_MINUTES
			);
			$this->record_status( false, $error );

			return $this->get_fallback( $error );
		}

		set_transient(
			TMTRACKER_TRANSIENT_DATA,
			$fresh['data'],
			HOUR_IN_SECONDS * $this->get_cache_hours()
		);
		delete_transient( TMTRACKER_TRANSIENT_BACKOFF );
		update_option( TMTRACKER_OPTION_LAST_GOOD, $fresh['data'], false );
		$this->record_status( true, '' );

		return array(
			'data'  => $fresh['data'],
			'stale' => false,
			'error' => null,
		);
	}

	/**
	 * Clear the cache and the pause after a failed fetch.
	 *
	 * The last successful response stays in place as fallback.
	 *
	 * @return void
	 */
	public function clear_cache() {
		delete_transient( TMTRACKER_TRANSIENT_DATA );
		delete_transient( TMTRACKER_TRANSIENT_BACKOFF );
	}

	/**
	 * Fetch status for the settings page.
	 *
	 * @return array{last_attempt: int, last_success: int, last_error: string}
	 */
	public function get_status() {
		$status = get_option( TMTRACKER_OPTION_FETCH_STATUS, array() );
		$status = is_array( $status ) ? $status : array();

		return array(
			'last_attempt' => isset( $status['last_attempt'] ) ? (int) $status['last_attempt'] : 0,
			'last_success' => isset( $status['last_success'] ) ? (int) $status['last_success'] : 0,
			'last_error'   => isset( $status['last_error'] ) ? (string) $status['last_error'] : '',
		);
	}

	/**
	 * Result built from the last successful response.
	 *
	 * @param string $error Reason why no fresh data is available.
	 * @return array{data: array|null, stale: bool, error: string|null}
	 */
	private function get_fallback( $error ) {
		$last_good = get_option( TMTRACKER_OPTION_LAST_GOOD, null );

		if ( is_array( $last_good ) ) {
			return array(
				'data'  => $last_good,
				'stale' => true,
				'error' => $error,
			);
		}

		return array(
			'data'  => null,
			'stale' => false,
			'error' => $error,
		);
	}

	/**
	 * Stores the outcome of a fetch attempt.
	 *
	 * @param bool   $success Whether the attempt delivered valid data.
	 * @param string $error   Error message of a failed attempt.
	 * @return void
	 */
	private function record_status( $success, $error ) {
		$status                 = $this->get_status();
		$status['last_attempt'] = time();
		$status['last_error']   = $success ? '' : (string) $error;

		if ( $success ) {
			$status['last_success'] = $status['last_attempt'];
		}

		update_option( TMTRACKER_OPTION_FETCH_STATUS, $status, false );
	}

	/**
	 * HTTP request against the JSON URL.
	 *
	 * Uses wp_safe_remote_get() because the URL is entered by a user: requests
	 * to private or loopback addresses are rejected.
	 *
	 * @return array{data: array|null, error: string|null}
	 */
	private function fetch_from_remote() {
		$url = $this->get_url();

		$response = wp_safe_remote_get(
			$url,
			array(
				'timeout'    => self::REQUEST_TIMEOUT,
				'headers'    => array(
					'Accept' => 'application/json',
				),
				'user-agent' => 'TrainingMeetingTracker/' . TMTRACKER_VERSION . '; ' . home_url(),
			)
		);

		if ( is_wp_error( $response ) ) {
			return array(
				'data'  => null,
				'error' => $response->get_error_message(),
			);
		}

		$code = (int) wp_remote_retrieve_response_code( $response );
		if ( $code < 200 || $code >= 300 ) {
			return array(
				'data'  => null,
				'error' => sprintf(
					/* translators: %d: HTTP status code. */
					__( 'HTTP error %d while loading meeting data.', 'training-meeting-tracker' ),
					$code
				),
			);
		}

		$body = wp_remote_retrieve_body( $response );
		$json = json_decode( $body, true );

		if ( ! is_array( $json ) ) {
			return array(
				'data'  => null,
				'error' => __( 'Meeting data could not be parsed as JSON.', 'training-meeting-tracker' ),
			);
		}

		$validated = $this->validate_schema( $json );
		if ( null === $validated ) {
			return array(
				'data'  => null,
				'error' => __( 'Meeting data has an unknown schema version.', 'training-meeting-tracker' ),
			);
		}

		return array(
			'data'  => $validated,
			'error' => null,
		);
	}

	/**
	 * Schema validation.
	 *
	 * Only the supported schema version is accepted. Any other version,
	 * including the retired version 1, counts as a failed fetch.
	 *
	 * @param array $json Raw JSON.
	 * @return array|null Validated data, or null on schema mismatch.
	 */
	private function validate_schema( array $json ) {
		$schema_version = ( isset( $json['schema_version'] ) && is_scalar( $json['schema_version'] ) )
			? (int) $json['schema_version']
			: 0;

		if ( self::SUPPORTED_SCHEMA_VERSION !== $schema_version ) {
			return null;
		}

		return array(
			'schema_version'       => $schema_version,
			'generated_at'         => $this->get_string( $json, 'generated_at' ),
			'source_repo'          => $this->get_string( $json, 'source_repo' ),
			'upcoming_sessions'    => $this->normalize_list( $json, 'upcoming_sessions', true ),
			'in_progress_sessions' => $this->normalize_list( $json, 'in_progress_sessions', true ),
			'past_sessions'        => $this->normalize_list( $json, 'past_sessions', false ),
		);
	}

	/**
	 * Normalises a list from the top level JSON.
	 *
	 * @param array  $json     Raw JSON.
	 * @param string $key      Top level key that holds the list.
	 * @param bool   $has_time Whether the records carry a session_time field
	 *                         (true) or a minutes_date field (false, for past
	 *                         minutes).
	 * @return array
	 */
	private function normalize_list( array $json, $key, $has_time ) {
		if ( ! isset( $json[ $key ] ) || ! is_array( $json[ $key ] ) ) {
			return array();
		}

		$out = array();
		foreach ( $json[ $key ] as $session ) {
			if ( ! is_array( $session ) ) {
				continue;
			}
			$normalized = $this->normalize_session( $session, $has_time );
			if ( null !== $normalized ) {
				$out[] = $normalized;
			}
		}
		return $out;
	}

	/**
	 * Normalises a single meeting record.
	 *
	 * Records without a valid calendar date are dropped. An invalid time or
	 * minutes date is replaced by an empty string.
	 *
	 * @param array $session  Raw record.
	 * @param bool  $has_time Whether the item carries a session_time field
	 *                        (upcoming and in_progress) or a minutes_date
	 *                        field (past).
	 * @return array|null
	 */
	private function normalize_session( array $session, $has_time ) {
		$date = $this->get_string( $session, 'session_date' );
		if ( ! $this->is_valid_date( $date ) ) {
			return null;
		}

		$normalized = array(
			'title'        => trim( $this->get_string( $session, 'title' ) ),
			'session_date' => $date,
			'url'          => esc_url_raw( $this->get_string( $session, 'url' ), array( 'http', 'https' ) ),
		);

		if ( $has_time ) {
			$time                       = $this->get_string( $session, 'session_time' );
			$normalized['session_time'] = $this->is_valid_time( $time ) ? $time : '';
		} else {
			$minutes                    = $this->get_string( $session, 'minutes_date' );
			$normalized['minutes_date'] = $this->is_valid_date( $minutes ) ? $minutes : '';
		}

		return $normalized;
	}

	/**
	 * Reads a scalar value from an array as string.
	 *
	 * @param array  $source Array to read from.
	 * @param string $key    Key to read.
	 * @return string Empty string if the key is missing or not scalar.
	 */
	private function get_string( array $source, $key ) {
		return ( isset( $source[ $key ] ) && is_scalar( $source[ $key ] ) ) ? (string) $source[ $key ] : '';
	}

	/**
	 * Whether a value is an existing calendar date in YYYY-MM-DD form.
	 *
	 * @param string $value Value to check.
	 * @return bool
	 */
	private function is_valid_date( $value ) {
		if ( ! preg_match( '/^(\d{4})-(\d{2})-(\d{2})\z/', $value, $parts ) ) {
			return false;
		}

		return checkdate( (int) $parts[2], (int) $parts[3], (int) $parts[1] );
	}

	/**
	 * Whether a value is a time of day in 24 hour HH:MM form.
	 *
	 * @param string $value Value to check.
	 * @return bool
	 */
	private function is_valid_time( $value ) {
		return 1 === preg_match( '/^(?:[01]\d|2[0-3]):[0-5]\d\z/', $value );
	}
}
