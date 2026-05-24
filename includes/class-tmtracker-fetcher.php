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
	 * Current cache duration in hours. At least 1, at most 168 (one week).
	 *
	 * @return int
	 */
	public function get_cache_hours() {
		$settings = (array) get_option( TMTRACKER_OPTION_SETTINGS, array() );
		$hours    = isset( $settings['cache_hours'] ) ? (int) $settings['cache_hours'] : TMTRACKER_DEFAULT_CACHE_HOURS;

		if ( $hours < 1 ) {
			$hours = 1;
		}
		if ( $hours > 168 ) {
			$hours = 168;
		}

		return $hours;
	}

	/**
	 * Returns the meeting data: from cache, fresh fetch, or fallback.
	 *
	 * @param bool $force_refresh Bypass the cache.
	 * @return array{data: array|null, stale: bool, error: string|null}
	 */
	public function get_data( $force_refresh = false ) {
		if ( ! $force_refresh ) {
			$cached = get_transient( TMTRACKER_TRANSIENT_DATA );
			if ( false !== $cached && is_array( $cached ) ) {
				return array(
					'data'  => $cached,
					'stale' => false,
					'error' => null,
				);
			}
		}

		$fresh = $this->fetch_from_remote();

		if ( null !== $fresh['data'] ) {
			set_transient(
				TMTRACKER_TRANSIENT_DATA,
				$fresh['data'],
				HOUR_IN_SECONDS * $this->get_cache_hours()
			);
			update_option( TMTRACKER_OPTION_LAST_GOOD, $fresh['data'], false );

			return array(
				'data'  => $fresh['data'],
				'stale' => false,
				'error' => null,
			);
		}

		// Fallback to the last successful response.
		$last_good = get_option( TMTRACKER_OPTION_LAST_GOOD, null );
		if ( is_array( $last_good ) ) {
			return array(
				'data'  => $last_good,
				'stale' => true,
				'error' => $fresh['error'],
			);
		}

		return array(
			'data'  => null,
			'stale' => false,
			'error' => $fresh['error'],
		);
	}

	/**
	 * Clear the cache.
	 *
	 * @return void
	 */
	public function clear_cache() {
		delete_transient( TMTRACKER_TRANSIENT_DATA );
	}

	/**
	 * HTTP request against the JSON URL.
	 *
	 * @return array{data: array|null, error: string|null}
	 */
	private function fetch_from_remote() {
		$url = $this->get_url();

		$response = wp_remote_get(
			$url,
			array(
				'timeout'    => 15,
				'sslverify'  => true,
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
					__( 'HTTP error %d while loading session data.', 'training-meeting-tracker' ),
					$code
				),
			);
		}

		$body = wp_remote_retrieve_body( $response );
		$json = json_decode( $body, true );

		if ( ! is_array( $json ) ) {
			return array(
				'data'  => null,
				'error' => __( 'Session data could not be parsed as JSON.', 'training-meeting-tracker' ),
			);
		}

		$validated = $this->validate_schema( $json );
		if ( null === $validated ) {
			return array(
				'data'  => null,
				'error' => __( 'Session data has an unknown schema version.', 'training-meeting-tracker' ),
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
	 * Accepts v2 natively. A v1 payload (single next_session plus past_sessions)
	 * is migrated to v2 on the fly so the plugin still works in a world where
	 * the team repository action has not yet been updated.
	 *
	 * @param array $json Raw JSON.
	 * @return array|null Validated data, or null on schema mismatch.
	 */
	private function validate_schema( array $json ) {
		$schema_version = isset( $json['schema_version'] ) ? (int) $json['schema_version'] : 0;

		if ( 1 === $schema_version ) {
			$json           = $this->migrate_v1_to_v2( $json );
			$schema_version = 2;
		}

		if ( self::SUPPORTED_SCHEMA_VERSION !== $schema_version ) {
			return null;
		}

		$out = array(
			'schema_version'       => $schema_version,
			'generated_at'         => isset( $json['generated_at'] ) ? (string) $json['generated_at'] : '',
			'source_repo'          => isset( $json['source_repo'] ) ? (string) $json['source_repo'] : '',
			'upcoming_sessions'    => array(),
			'in_progress_sessions' => array(),
			'past_sessions'        => array(),
		);

		$out['upcoming_sessions']    = $this->normalize_list( $json, 'upcoming_sessions', true );
		$out['in_progress_sessions'] = $this->normalize_list( $json, 'in_progress_sessions', true );
		$out['past_sessions']        = $this->normalize_list( $json, 'past_sessions', false );

		return $out;
	}

	/**
	 * Migrates a v1 payload to v2.
	 *
	 * Input format v1:
	 *   next_session (single record) becomes upcoming_sessions (one element)
	 *   past_sessions stays past_sessions (unchanged)
	 *
	 * In v1 the titles typically contained the date ("Sitzung 2026-04-28").
	 * We keep that text as is. The date may appear twice in the rendered
	 * output (title plus separate date display), but the plugin stays
	 * functional until the team repository upgrades to v2.
	 *
	 * The in_progress_sessions list cannot be represented in v1 (the concept
	 * does not exist) and therefore stays empty.
	 *
	 * @param array $json Raw v1 JSON.
	 * @return array v2 form.
	 */
	private function migrate_v1_to_v2( array $json ) {
		$upcoming = array();
		if ( isset( $json['next_session'] ) && is_array( $json['next_session'] ) ) {
			$upcoming[] = $json['next_session'];
		}

		return array(
			'schema_version'       => 2,
			'generated_at'         => isset( $json['generated_at'] ) ? $json['generated_at'] : '',
			'source_repo'          => isset( $json['source_repo'] ) ? $json['source_repo'] : '',
			'upcoming_sessions'    => $upcoming,
			'in_progress_sessions' => array(),
			'past_sessions'        => isset( $json['past_sessions'] ) ? $json['past_sessions'] : array(),
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
	 * @param array $session  Raw record.
	 * @param bool  $has_time Whether the item carries a session_time field
	 *                        (upcoming and in_progress) or a minutes_date
	 *                        field (past).
	 * @return array|null
	 */
	private function normalize_session( array $session, $has_time ) {
		$date = isset( $session['session_date'] ) ? (string) $session['session_date'] : '';
		if ( ! preg_match( '/^\d{4}-\d{2}-\d{2}$/', $date ) ) {
			return null;
		}

		$normalized = array(
			'title'        => isset( $session['title'] ) ? (string) $session['title'] : '',
			'session_date' => $date,
			'url'          => isset( $session['url'] ) ? esc_url_raw( (string) $session['url'] ) : '',
		);

		if ( $has_time ) {
			$time_raw                   = isset( $session['session_time'] ) ? (string) $session['session_time'] : '';
			$normalized['session_time'] = preg_match( '/^\d{2}:\d{2}$/', $time_raw ) ? $time_raw : '';
		} else {
			$minutes = isset( $session['minutes_date'] ) ? (string) $session['minutes_date'] : '';
			if ( preg_match( '/^\d{4}-\d{2}-\d{2}$/', $minutes ) ) {
				$normalized['minutes_date'] = $minutes;
			} else {
				$normalized['minutes_date'] = '';
			}
		}

		return $normalized;
	}
}
