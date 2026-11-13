<?php
/**
 * Plugin Name:       Training Meeting Tracker
 * Plugin URI:        https://github.com/rfluethi/Training-Meeting-Tracker
 * Description:       WordPress plugin that displays the DACH training team's meetings on a WordPress page, sourced from GitHub issues.
 * Version:           0.2.0
 * Requires at least: 6.7
 * Requires PHP:      7.4
 * Author:            Learn WP DACH Team
 * Author URI:        https://github.com/learn-wp-dach/learn-wp-dach-team
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       training-meeting-tracker
 * Domain Path:       /languages
 * Update URI:        https://github.com/rfluethi/Training-Meeting-Tracker
 *
 * @package TrainingMeetingTracker
 */

// Prevent direct access.
defined( 'ABSPATH' ) || exit;

/**
 * Plugin constants.
 */
define( 'TMTRACKER_VERSION', '0.2.0' );
define( 'TMTRACKER_PLUGIN_FILE', __FILE__ );
define( 'TMTRACKER_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'TMTRACKER_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'TMTRACKER_PLUGIN_BASENAME', plugin_basename( __FILE__ ) );

/**
 * Default JSON URL (data branch of the team repository).
 */
define( 'TMTRACKER_DEFAULT_JSON_URL', 'https://raw.githubusercontent.com/learn-wp-dach/learn-wp-dach-team/data/sitzungen.json' );

/**
 * Default cache duration in hours.
 */
define( 'TMTRACKER_DEFAULT_CACHE_HOURS', 12 );

/**
 * Minutes without a new request after a failed fetch.
 */
define( 'TMTRACKER_BACKOFF_MINUTES', 10 );

/**
 * Option and transient keys. uninstall.php lists the same keys.
 */
define( 'TMTRACKER_OPTION_SETTINGS', 'tmtracker_settings' );
define( 'TMTRACKER_OPTION_LAST_GOOD', 'tmtracker_last_good_data' );
define( 'TMTRACKER_OPTION_FETCH_STATUS', 'tmtracker_fetch_status' );
define( 'TMTRACKER_TRANSIENT_DATA', 'tmtracker_session_data' );
define( 'TMTRACKER_TRANSIENT_BACKOFF', 'tmtracker_fetch_backoff' );

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

// Since WordPress 6.7 the translations in languages/ are found through the
// "Text Domain" and "Domain Path" headers and loaded when first needed. An
// explicit load_plugin_textdomain() call is not required. Older versions do
// not load bundled translations on their own, hence "Requires at least: 6.7".
