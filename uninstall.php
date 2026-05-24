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

// Remove options.
delete_option( 'tmtracker_settings' );
delete_option( 'tmtracker_last_good_data' );

// Remove transients.
delete_transient( 'tmtracker_session_data' );

// Multisite variant.
if ( is_multisite() ) {
	$tmtracker_sites = get_sites( array( 'fields' => 'ids' ) );
	foreach ( $tmtracker_sites as $tmtracker_site_id ) {
		switch_to_blog( (int) $tmtracker_site_id );
		delete_option( 'tmtracker_settings' );
		delete_option( 'tmtracker_last_good_data' );
		delete_transient( 'tmtracker_session_data' );
		restore_current_blog();
	}
}
