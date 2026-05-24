<?php
/**
 * Renderer: builds the HTML for the session list.
 *
 * @package TrainingMeetingTracker
 */

defined( 'ABSPATH' ) || exit;

/**
 * HTML rendering.
 *
 * The renderer is intentionally pure: it does not look at the wall clock or
 * the database. It receives a validated payload from the fetcher (already
 * partitioned into upcoming / in_progress / past) and turns it into HTML.
 */
class TMTracker_Renderer {

	/**
	 * Render the complete list.
	 *
	 * @param array|null  $data  Validated session data (or null).
	 * @param array       $atts  Shortcode attributes (show_upcoming,
	 *                           show_in_progress, show_past, years).
	 * @param bool        $stale Whether the data is from the fallback.
	 * @param string|null $error Optional error message.
	 * @return string HTML.
	 */
	public function render( $data, array $atts, $stale = false, $error = null ) {
		if ( null === $data ) {
			return $this->render_empty( $error );
		}

		$show_upcoming    = ! empty( $atts['show_upcoming'] );
		$show_in_progress = ! empty( $atts['show_in_progress'] );
		$show_past        = ! empty( $atts['show_past'] );
		$years            = isset( $atts['years'] ) ? $atts['years'] : 'all';

		$html  = '<div class="tmtracker-container">';
		$html .= $stale ? $this->render_stale_notice() : '';

		if ( $show_upcoming && ! empty( $data['upcoming_sessions'] ) ) {
			$html .= $this->render_session_list(
				$data['upcoming_sessions'],
				esc_html__( 'Upcoming meetings', 'training-meeting-tracker' ),
				'tmtracker-upcoming',
				'tmtracker-upcoming-heading'
			);
		}

		if ( $show_in_progress && ! empty( $data['in_progress_sessions'] ) ) {
			$html .= $this->render_session_list(
				$data['in_progress_sessions'],
				esc_html__( 'Meetings in progress', 'training-meeting-tracker' ),
				'tmtracker-in-progress',
				'tmtracker-in-progress-heading'
			);
		}

		if ( $show_past && ! empty( $data['past_sessions'] ) ) {
			$html .= $this->render_past_sessions( $data['past_sessions'], $years );
		}

		$html .= '</div>';

		return $html;
	}

	/**
	 * Empty-state output.
	 *
	 * @param string|null $error Error message.
	 * @return string
	 */
	private function render_empty( $error ) {
		$html  = '<div class="tmtracker-container tmtracker-empty" role="status">';
		$html .= '<p>' . esc_html__( 'Session data is being prepared.', 'training-meeting-tracker' ) . '</p>';

		if ( null !== $error && current_user_can( 'manage_options' ) ) {
			$html .= '<p class="tmtracker-error" role="alert">' . esc_html( $error ) . '</p>';
		}

		$html .= '</div>';

		return $html;
	}

	/**
	 * Notice that the data is from the fallback.
	 *
	 * @return string
	 */
	private function render_stale_notice() {
		return '<p class="tmtracker-stale-notice" role="status">'
			. esc_html__( 'The source is currently unreachable: showing the last successful state.', 'training-meeting-tracker' )
			. '</p>';
	}

	/**
	 * Render a list of sessions that carry a session_time (upcoming or
	 * in_progress). Each item shows title + date + optional time.
	 *
	 * @param array  $sessions      List of session records.
	 * @param string $heading       Already-escaped heading text.
	 * @param string $section_class CSS class for the wrapping <section>.
	 * @param string $heading_id    ID for the heading, used by aria-labelledby.
	 * @return string
	 */
	private function render_session_list( array $sessions, $heading, $section_class, $heading_id ) {
		$html  = '<section class="' . esc_attr( $section_class ) . '" aria-labelledby="' . esc_attr( $heading_id ) . '">';
		$html .= '<h2 class="tmtracker-heading" id="' . esc_attr( $heading_id ) . '">' . $heading . '</h2>';
		$html .= '<ul class="tmtracker-list">';

		foreach ( $sessions as $session ) {
			$html .= $this->render_session_item( $session );
		}

		$html .= '</ul>';
		$html .= '</section>';

		return $html;
	}

	/**
	 * Render a single item from the upcoming or in_progress lists.
	 *
	 * @param array $session Validated session record.
	 * @return string
	 */
	private function render_session_item( array $session ) {
		$title         = isset( $session['title'] ) ? $session['title'] : '';
		$session_date  = isset( $session['session_date'] ) ? $session['session_date'] : '';
		$session_time  = isset( $session['session_time'] ) ? $session['session_time'] : '';
		$url           = isset( $session['url'] ) ? $session['url'] : '';
		$formatted     = $this->format_date( $session_date );
		$datetime_attr = $this->build_datetime_attr( $session_date, $session_time );

		$html = '<li class="tmtracker-item">';

		if ( '' !== $url ) {
			$html .= '<a class="tmtracker-link" href="' . esc_url( $url ) . '">'
				. esc_html( $title ) . '</a>';
		} else {
			$html .= '<span class="tmtracker-title">' . esc_html( $title ) . '</span>';
		}

		$html .= ' <span class="tmtracker-meta">';
		$html .= '<time datetime="' . esc_attr( $datetime_attr ) . '">'
			. esc_html( $formatted ) . '</time>';

		if ( '' !== $session_time ) {
			$html .= '<span class="tmtracker-separator" aria-hidden="true"> · </span>';
			$html .= '<span class="tmtracker-time">' . esc_html( $session_time ) . '</span>';
		}

		$html .= '</span>';
		$html .= '</li>';

		return $html;
	}

	/**
	 * Past meetings list, grouped by year.
	 *
	 * @param array      $sessions List of sessions.
	 * @param string|int $years    'all' or positive number.
	 * @return string
	 */
	private function render_past_sessions( array $sessions, $years ) {
		// Group by year (from session_date).
		$grouped = array();
		foreach ( $sessions as $session ) {
			if ( empty( $session['session_date'] ) ) {
				continue;
			}
			$year = substr( $session['session_date'], 0, 4 );
			if ( ! isset( $grouped[ $year ] ) ) {
				$grouped[ $year ] = array();
			}
			$grouped[ $year ][] = $session;
		}

		if ( empty( $grouped ) ) {
			return '';
		}

		// Sort years descending.
		krsort( $grouped );

		// Limit to N years.
		if ( 'all' !== $years ) {
			$limit   = max( 1, (int) $years );
			$grouped = array_slice( $grouped, 0, $limit, true );
		}

		$heading_id = 'tmtracker-past-heading';

		$html  = '<section class="tmtracker-past" aria-labelledby="' . esc_attr( $heading_id ) . '">';
		$html .= '<h2 class="tmtracker-heading" id="' . esc_attr( $heading_id ) . '">'
			. esc_html__( 'Minutes', 'training-meeting-tracker' ) . '</h2>';

		foreach ( $grouped as $year => $year_sessions ) {
			usort(
				$year_sessions,
				static function ( $a, $b ) {
					return strcmp( $b['session_date'], $a['session_date'] );
				}
			);

			$html .= '<h3 class="tmtracker-year">' . esc_html( $year ) . '</h3>';
			$html .= '<ul class="tmtracker-list">';

			foreach ( $year_sessions as $session ) {
				$html .= $this->render_past_session_item( $session );
			}

			$html .= '</ul>';
		}

		$html .= '</section>';

		return $html;
	}

	/**
	 * Single minutes entry.
	 *
	 * @param array $session Session.
	 * @return string
	 */
	private function render_past_session_item( array $session ) {
		$title        = isset( $session['title'] ) ? $session['title'] : '';
		$url          = isset( $session['url'] ) ? $session['url'] : '';
		$session_date = isset( $session['session_date'] ) ? $session['session_date'] : '';

		$html = '<li class="tmtracker-item">';

		if ( '' !== $url ) {
			$html .= '<a class="tmtracker-link" href="' . esc_url( $url ) . '">'
				. esc_html( $title ) . '</a>';
		} else {
			$html .= '<span class="tmtracker-title">' . esc_html( $title ) . '</span>';
		}

		if ( '' !== $session_date ) {
			$formatted = $this->format_date( $session_date );
			$html     .= ' <span class="tmtracker-meta">'
				. sprintf(
					/* translators: %s: meeting date in DD.MM.YYYY format. */
					esc_html__( 'Meeting from %s', 'training-meeting-tracker' ),
					'<time datetime="' . esc_attr( $session_date ) . '">' . esc_html( $formatted ) . '</time>'
				)
				. '</span>';
		}

		$html .= '</li>';

		return $html;
	}

	/**
	 * Formats a YYYY-MM-DD date as DD.MM.YYYY.
	 *
	 * @param string $iso_date YYYY-MM-DD.
	 * @return string
	 */
	private function format_date( $iso_date ) {
		if ( ! preg_match( '/^(\d{4})-(\d{2})-(\d{2})$/', $iso_date, $m ) ) {
			return $iso_date;
		}
		return $m[3] . '.' . $m[2] . '.' . $m[1];
	}

	/**
	 * Builds the datetime attribute value for the time element.
	 *
	 * If a time (HH:MM) is present, returns YYYY-MM-DDTHH:MM, otherwise just
	 * the date.
	 *
	 * @param string $iso_date YYYY-MM-DD.
	 * @param string $iso_time HH:MM or empty.
	 * @return string
	 */
	private function build_datetime_attr( $iso_date, $iso_time = '' ) {
		if ( '' !== $iso_time && preg_match( '/^\d{2}:\d{2}$/', $iso_time ) ) {
			return $iso_date . 'T' . $iso_time;
		}
		return $iso_date;
	}
}
