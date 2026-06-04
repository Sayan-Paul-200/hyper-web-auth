<?php

/**
 * Fired during plugin activation
 *
 * @link       https://github.com/Sayan-Paul-200
 * @since      1.0.0
 *
 * @package    Hyper_Web_Auth
 * @subpackage Hyper_Web_Auth/includes
 */

/**
 * Fired during plugin activation.
 *
 * This class defines all code necessary to run during the plugin's activation.
 *
 * @since      1.0.0
 * @package    Hyper_Web_Auth
 * @subpackage Hyper_Web_Auth/includes
 * @author     Sayan Paul <sayanpaul666.ap@gmail.com>
 */
class Hyper_Web_Auth_Activator {

	/**
	 * Fired when the plugin is activated.
	 *
	 * @since    1.0.0
	 */
	public static function activate() {

		/**
		 * Creates or updates custom database tables using dbDelta.
		 */
		require_once HYPER_WEB_AUTH_PATH . 'includes/class-hwa-database.php';
		HWA_Database::create_tables();

		// Schedule hourly cleanup for OAuth states.
		if ( ! wp_next_scheduled( 'hwa_hourly_cleanup' ) ) {
			wp_schedule_event( time(), 'hourly', 'hwa_hourly_cleanup' );
		}

		// Add WooCommerce endpoint and flush rewrite rules so it doesn't 404
		add_rewrite_endpoint( 'linked-accounts', EP_ROOT | EP_PAGES );
		flush_rewrite_rules();

	}

}
