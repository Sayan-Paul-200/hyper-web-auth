<?php

/**
 * The plugin bootstrap file
 *
 * This file is read by WordPress to generate the plugin information in the plugin
 * admin area. This file also includes all of the dependencies used by the plugin,
 * registers the activation and deactivation functions, and defines a function
 * that starts the plugin.
 *
 * @link              https://github.com/Sayan-Paul-200
 * @since             1.0.0
 * @package           Hyper_Web_Auth
 *
 * @wordpress-plugin
 * Plugin Name:       HyperWeb Customer Authentication for WooCommerce
 * Plugin URI:        https://hyperweblabs.in/
 * Description:       Custom WooCommerce authentication plugin enabling secure Phone SMS OTP login, Google OAuth registration, account linking, and seamless customer access from login, signup, and My Account pages.
 * Version:           1.0.0
 * Author:            Sayan Paul
 * Author URI:        https://github.com/Sayan-Paul-200/
 * License:           GPL-2.0+
 * License URI:       http://www.gnu.org/licenses/gpl-2.0.txt
 * Text Domain:       hyper-web-auth
 * Domain Path:       /languages
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}

/**
 * Currently plugin version.
 * Start at version 1.0.0 and use SemVer - https://semver.org
 * Rename this for your plugin and update it as you release new versions.
 */
define( 'HYPER_WEB_AUTH_VERSION', '1.0.0' );

/**
 * The code that runs during plugin activation.
 * This action is documented in includes/class-hyper-web-auth-activator.php
 */
function activate_hyper_web_auth() {
	require_once plugin_dir_path( __FILE__ ) . 'includes/class-hyper-web-auth-activator.php';
	Hyper_Web_Auth_Activator::activate();
}

/**
 * The code that runs during plugin deactivation.
 * This action is documented in includes/class-hyper-web-auth-deactivator.php
 */
function deactivate_hyper_web_auth() {
	require_once plugin_dir_path( __FILE__ ) . 'includes/class-hyper-web-auth-deactivator.php';
	Hyper_Web_Auth_Deactivator::deactivate();
}

register_activation_hook( __FILE__, 'activate_hyper_web_auth' );
register_deactivation_hook( __FILE__, 'deactivate_hyper_web_auth' );

/**
 * The core plugin class that is used to define internationalization,
 * admin-specific hooks, and public-facing site hooks.
 */
require plugin_dir_path( __FILE__ ) . 'includes/class-hyper-web-auth.php';

/**
 * Begins execution of the plugin.
 *
 * Since everything within the plugin is registered via hooks,
 * then kicking off the plugin from this point in the file does
 * not affect the page life cycle.
 *
 * @since    1.0.0
 */
function run_hyper_web_auth() {

	$plugin = new Hyper_Web_Auth();
	$plugin->run();

}
run_hyper_web_auth();
