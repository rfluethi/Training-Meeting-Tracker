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
delete_option( 'tmt_settings' );
delete_option( 'tmt_last_good_data' );

// Remove transients.
delete_transient( 'tmt_session_data' );

// Multisite variant.
if ( is_multisite() ) {
	$tmt_sites = get_sites( array( 'fields' => 'ids' ) );
	foreach ( $tmt_sites as $tmt_site_id ) {
		switch_to_blog( (int) $tmt_site_id );
		delete_option( 'tmt_settings' );
		delete_option( 'tmt_last_good_data' );
		delete_transient( 'tmt_session_data' );
		restore_current_blog();
	}
}
