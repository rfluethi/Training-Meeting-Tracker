<?php
/**
 * Bootstrap class for the plugin.
 *
 * @package TrainingMeetingTracker
 */

defined( 'ABSPATH' ) || exit;

/**
 * Main class. Manages the sub components.
 */
final class TMTracker_Plugin {

	/**
	 * Singleton instance.
	 *
	 * @var TMTracker_Plugin|null
	 */
	private static $instance = null;

	/**
	 * Fetcher instance.
	 *
	 * @var TMTracker_Fetcher
	 */
	public $fetcher;

	/**
	 * Renderer instance.
	 *
	 * @var TMTracker_Renderer
	 */
	public $renderer;

	/**
	 * Shortcode instance.
	 *
	 * @var TMTracker_Shortcode
	 */
	public $shortcode;

	/**
	 * Settings instance.
	 *
	 * @var TMTracker_Settings
	 */
	public $settings;

	/**
	 * Singleton accessor.
	 *
	 * @return TMTracker_Plugin
	 */
	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Private constructor.
	 */
	private function __construct() {}

	/**
	 * Initialisation. Called by the plugins_loaded hook.
	 *
	 * @return void
	 */
	public function init() {
		$this->fetcher   = new TMTracker_Fetcher();
		$this->renderer  = new TMTracker_Renderer();
		$this->shortcode = new TMTracker_Shortcode( $this->fetcher, $this->renderer );
		$this->settings  = new TMTracker_Settings();

		$this->shortcode->register();
		$this->settings->register();

		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_assets' ) );
	}

	/**
	 * Register frontend assets.
	 *
	 * Loaded only when the shortcode is used on the current page.
	 *
	 * @return void
	 */
	public function enqueue_assets() {
		wp_register_style(
			'tmtracker-frontend',
			TMTRACKER_PLUGIN_URL . 'assets/css/frontend.css',
			array(),
			TMTRACKER_VERSION
		);
	}
}
