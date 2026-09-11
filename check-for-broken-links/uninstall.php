<?php
/**
 * Uninstall Check for Broken Links plugin.
 *
 * @package WPCBL_Check_Broken_Links
 * @author  Norse Digital Group LLC
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit; // Exit if uninstall not called from WordPress.
}

/*
 * Only remove plugin data if the WP_UNINSTALL_PLUGIN constant is set to true in user's
 * wp-config.php. This is to prevent data loss when deleting the plugin from the backend
 * and to ensure only the site owner can perform this action.
 */
if ( defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	/*
	 * Delete plugin options.
	 */
	delete_option( 'wpcbl_check_for_broken_links_settings' );
	delete_option( 'wpcbl_check_for_broken_links_links' );
	delete_option( 'wpcbl_last_scan_summary' );
	delete_option( 'wpcbl_check_for_broken_links_show_phpver_notice' );
	delete_option( 'wpcbl_check_for_broken_links_show_wpver_notice' );
	delete_option( 'wpcbl_check_for_broken_links_version' );
	delete_option( 'wpcbl_completed_scans' );
	delete_option( 'wpcbl_scan_progress' );
	delete_option( 'wpcbl_site_token' );
	delete_option( 'wpcbl_connection' );
	delete_option( 'wpcbl_entitlements_grace' );
	delete_transient( 'wpcbl_entitlements' );
	delete_transient( 'wpcbl_connect_nonce' );

	/*
	 * Delete the per-user screen option (results per page) and banner dismissal.
	 */
	delete_metadata( 'user', 0, 'links_per_page', '', true );
	delete_metadata( 'user', 0, 'wpcbl_ttswp_banner_dismissed', '', true );

	/*
	 * Delete plugin cron jobs.
	 */
	wp_clear_scheduled_hook( 'wpcbl_check_for_broken_links_scheduled_event' );
	wp_clear_scheduled_hook( 'wpcbl_check_for_broken_links_scan_step' );
}
