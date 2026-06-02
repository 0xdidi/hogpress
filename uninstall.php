<?php
/**
 * Uninstall cleanup.
 *
 * Runs when the user deletes the plugin from the WordPress admin. Phase 7 fills
 * this in fully (remove options unless the user opted to retain data). For now
 * it is a safe no-op guarded against direct access.
 *
 * @package Hogpress
 */

// Bail if WordPress did not invoke this via the uninstall flow.
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

// Phase 7 implements data removal with an opt-in to retain settings.
