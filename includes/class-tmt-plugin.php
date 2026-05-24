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
final class TMT_Plugin {

	/**
	 * Singleton instance.
	 *
	 * @var TMT_Plugin|null
	 */
	private static $instance = null;

	/**
	 * Fetcher instance.
	 *
	 * @var TMT_Fetcher
	 */
	public $fetcher;

	/**
	 * Renderer instance.
	 *
	 * @var TMT_Renderer
	 */
	public $renderer;

	/**
	 * Shortcode instance.
	 *
	 * @var TMT_Shortcode
	 */
	public $shortcode;

	/**
	 * Settings instance.
	 *
	 * @var TMT_Settings
	 */
	public $settings;

	/**
	 * Singleton accessor.
	 *
	 * @return TMT_Plugin
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
		$this->fetcher   = new TMT_Fetcher();
		$this->renderer  = new TMT_Renderer();
		$this->shortcode = new TMT_Shortcode( $this->fetcher, $this->renderer );
		$this->settings  = new TMT_Settings();

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
			'tmt-frontend',
			TMT_PLUGIN_URL . 'assets/css/frontend.css',
			array(),
			TMT_VERSION
		);
	}
}
