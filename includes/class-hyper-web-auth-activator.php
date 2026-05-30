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

	}

}
