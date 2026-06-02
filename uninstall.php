<?php
/**
 * Uninstall cleanup.
 *
 * Runs when the user deletes the plugin from the WordPress admin. Removes the
 * options, user meta, and transients the plugin creates. Multisite-safe.
 *
 * @package Hogpress
 */

// Bail if WordPress did not invoke this via the uninstall flow.
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

/**
 * Remove all plugin data for the current site.
 *
 * @return void
 */
function hogpress_uninstall_cleanup_site() {
	delete_option( 'hogpress_settings' );

	// Remove the per-user "setup notice dismissed" flag for every user.
	delete_metadata( 'user', 0, 'hogpress_setup_notice_dismissed', '', true );

	// Remove any leftover flash transients.
	global $wpdb;
	$wpdb->query( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		"DELETE FROM {$wpdb->options} WHERE option_name LIKE '\_transient\_hogpress\_%' OR option_name LIKE '\_transient\_timeout\_hogpress\_%'"
	);
}

if ( is_multisite() ) {
	$hogpress_site_ids = get_sites(
		array(
			'fields' => 'ids',
			'number' => 0,
		)
	);
	foreach ( $hogpress_site_ids as $hogpress_site_id ) {
		switch_to_blog( (int) $hogpress_site_id );
		hogpress_uninstall_cleanup_site();
		restore_current_blog();
	}
	unset( $hogpress_site_ids, $hogpress_site_id );
} else {
	hogpress_uninstall_cleanup_site();
}
