<?php
/**
 * Uninstall routine: remove plugin options and transients.
 *
 * @package TrainingMeetingTracker
 */

// Abort if WordPress did not call this file.
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

/**
 * Removes the plugin data of the current site.
 *
 * The keys are written out because the main plugin file, which defines
 * them as constants, is not loaded during uninstall.
 *
 * @return void
 */
function tmtracker_uninstall_site() {
	delete_option( 'tmtracker_settings' );
	delete_option( 'tmtracker_last_good_data' );
	delete_option( 'tmtracker_fetch_status' );
	delete_transient( 'tmtracker_session_data' );
	delete_transient( 'tmtracker_fetch_backoff' );
}

if ( is_multisite() ) {
	// number 0 lifts the default limit of 100 sites.
	$tmtracker_site_ids = get_sites(
		array(
			'fields' => 'ids',
			'number' => 0,
		)
	);

	foreach ( $tmtracker_site_ids as $tmtracker_site_id ) {
		switch_to_blog( (int) $tmtracker_site_id );
		tmtracker_uninstall_site();
		restore_current_blog();
	}
} else {
	tmtracker_uninstall_site();
}
