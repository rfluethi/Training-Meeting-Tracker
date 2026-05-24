<?php
/**
 * Shortcode [training_meeting_tracker].
 *
 * @package TrainingMeetingTracker
 */

defined( 'ABSPATH' ) || exit;

/**
 * Registers and renders the shortcode.
 */
class TMT_Shortcode {

	/**
	 * Shortcode tag.
	 */
	const TAG = 'training_meeting_tracker';

	/**
	 * Fetcher.
	 *
	 * @var TMT_Fetcher
	 */
	private $fetcher;

	/**
	 * Renderer.
	 *
	 * @var TMT_Renderer
	 */
	private $renderer;

	/**
	 * Constructor.
	 *
	 * @param TMT_Fetcher  $fetcher  Fetcher instance.
	 * @param TMT_Renderer $renderer Renderer instance.
	 */
	public function __construct( TMT_Fetcher $fetcher, TMT_Renderer $renderer ) {
		$this->fetcher  = $fetcher;
		$this->renderer = $renderer;
	}

	/**
	 * Register hooks.
	 *
	 * @return void
	 */
	public function register() {
		add_shortcode( self::TAG, array( $this, 'render' ) );
	}

	/**
	 * Shortcode callback.
	 *
	 * @param array|string $atts Shortcode attributes.
	 * @return string HTML.
	 */
	public function render( $atts ) {
		$atts = shortcode_atts(
			array(
				// New attributes (schema v2).
				'show_upcoming'    => 'true',
				'show_in_progress' => 'true',
				'show_past'        => 'true',
				'years'            => 'all',
				// Backwards-compat alias: maps to show_upcoming.
				'show_next'        => null,
			),
			is_array( $atts ) ? $atts : array(),
			self::TAG
		);

		// Honor show_next as a synonym for show_upcoming if the caller used it.
		$show_upcoming = $atts['show_upcoming'];
		if ( null !== $atts['show_next'] ) {
			$show_upcoming = $atts['show_next'];
		}

		$normalized = array(
			'show_upcoming'    => $this->to_bool( $show_upcoming ),
			'show_in_progress' => $this->to_bool( $atts['show_in_progress'] ),
			'show_past'        => $this->to_bool( $atts['show_past'] ),
			'years'            => $this->normalize_years( $atts['years'] ),
		);

		$result = $this->fetcher->get_data();

		wp_enqueue_style( 'tmt-frontend' );

		return $this->renderer->render(
			$result['data'],
			$normalized,
			$result['stale'],
			$result['error']
		);
	}

	/**
	 * String/bool to bool.
	 *
	 * @param mixed $value Input.
	 * @return bool
	 */
	private function to_bool( $value ) {
		if ( is_bool( $value ) ) {
			return $value;
		}
		$value = strtolower( (string) $value );
		return in_array( $value, array( '1', 'true', 'yes', 'ja' ), true );
	}

	/**
	 * 'all' or positive integer.
	 *
	 * @param mixed $value Input.
	 * @return string|int
	 */
	private function normalize_years( $value ) {
		if ( 'all' === $value ) {
			return 'all';
		}
		$int = (int) $value;
		return $int > 0 ? $int : 'all';
	}
}
