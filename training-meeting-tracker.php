<?php
/**
 * Plugin Name:       Training Meeting Tracker
 * Plugin URI:        https://github.com/rfluethi/Training-Meeting-Tracker
 * Description:       WordPress plugin that displays the DACH training team's meetings on a WordPress page, sourced from GitHub issues.
 * Version:           0.1.2
 * Requires at least: 6.4
 * Tested up to:      7.0
 * Requires PHP:      7.4
 * Author:            Learn WP DACH Team
 * Author URI:        https://github.com/rfluethi/learn-wp-dach-team
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       training-meeting-tracker
 * Domain Path:       /languages
 *
 * @package TrainingMeetingTracker
 */

// Prevent direct access.
defined( 'ABSPATH' ) || exit;

/**
 * Plugin constants.
 */
define( 'TMTRACKER_VERSION', '0.1.2' );
define( 'TMTRACKER_PLUGIN_FILE', __FILE__ );
define( 'TMTRACKER_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'TMTRACKER_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'TMTRACKER_PLUGIN_BASENAME', plugin_basename( __FILE__ ) );

/**
 * Default JSON URL (data branch of the team repository).
 */
define( 'TMTRACKER_DEFAULT_JSON_URL', 'https://raw.githubusercontent.com/rfluethi/learn-wp-dach-team/data/sitzungen.json' );

/**
 * Default cache duration in hours.
 */
define( 'TMTRACKER_DEFAULT_CACHE_HOURS', 12 );

/**
 * Option keys.
 */
define( 'TMTRACKER_OPTION_SETTINGS', 'tmtracker_settings' );
define( 'TMTRACKER_OPTION_LAST_GOOD', 'tmtracker_last_good_data' );
define( 'TMTRACKER_TRANSIENT_DATA', 'tmtracker_session_data' );

// Load classes.
require_once TMTRACKER_PLUGIN_DIR . 'includes/class-tmtracker-plugin.php';
require_once TMTRACKER_PLUGIN_DIR . 'includes/class-tmtracker-fetcher.php';
require_once TMTRACKER_PLUGIN_DIR . 'includes/class-tmtracker-renderer.php';
require_once TMTRACKER_PLUGIN_DIR . 'includes/class-tmtracker-shortcode.php';
require_once TMTRACKER_PLUGIN_DIR . 'includes/class-tmtracker-settings.php';

/**
 * Bootstrap.
 */
add_action(
	'plugins_loaded',
	static function () {
		TMTracker_Plugin::instance()->init();
	}
);

// Translations are loaded automatically from the languages/ directory since WP 4.6.
// An explicit load_plugin_textdomain() call is no longer required.
