<?php

/**
 * Fired when the plugin is uninstalled.
 *
 * @link       https://github.com/Sayan-Paul-200
 * @since      1.0.0
 *
 * @package    Hyper_Web_Auth
 */

// If uninstall not called from WordPress, then exit.
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

/**
 * Cleanup database tables and options on uninstall, but ONLY if the
 * administrator has explicitly enabled the "Delete Data on Uninstall" setting.
 */
// Clear scheduled cron jobs
wp_clear_scheduled_hook( 'hwa_hourly_cleanup' );

$settings = get_option( 'hwa_settings', array() );

if ( is_array( $settings ) && isset( $settings['delete_data_on_uninstall'] ) && 'yes' === $settings['delete_data_on_uninstall'] ) {
	global $wpdb;

	// Drop custom tables.
	$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}hwa_identities" );
	$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}hwa_oauth_states" );
	$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}hwa_auth_logs" );

	// Delete options.
	delete_option( 'hwa_settings' );
	delete_option( 'hwa_db_version' );

	// Note: We deliberately NEVER delete user accounts or WooCommerce orders here.
	// We only drop our plugin-specific tracking tables.
}
