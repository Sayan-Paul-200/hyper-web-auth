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
 * Requires at least: 6.0
 * Requires PHP:      8.0
 * Requires Plugins:  woocommerce
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
 * Plugin constants.
 *
 * HYPER_WEB_AUTH_VERSION  — Current plugin version (SemVer).
 * HYPER_WEB_AUTH_FILE     — Absolute path to this bootstrap file.
 * HYPER_WEB_AUTH_PATH     — Plugin root directory path (trailing slash).
 * HYPER_WEB_AUTH_URL      — Plugin root directory URL (trailing slash).
 * HYPER_WEB_AUTH_BASENAME — Plugin basename for hooks (e.g. hyper-web-auth/hyper-web-auth.php).
 */
define( 'HYPER_WEB_AUTH_VERSION', '1.0.0' );
define( 'HYPER_WEB_AUTH_FILE', __FILE__ );
define( 'HYPER_WEB_AUTH_PATH', plugin_dir_path( __FILE__ ) );
define( 'HYPER_WEB_AUTH_URL', plugin_dir_url( __FILE__ ) );
define( 'HYPER_WEB_AUTH_BASENAME', plugin_basename( __FILE__ ) );

/**
 * The code that runs during plugin activation.
 * This action is documented in includes/class-hyper-web-auth-activator.php
 */
function activate_hyper_web_auth() {
	require_once HYPER_WEB_AUTH_PATH . 'includes/class-hyper-web-auth-activator.php';
	Hyper_Web_Auth_Activator::activate();
}

/**
 * The code that runs during plugin deactivation.
 * This action is documented in includes/class-hyper-web-auth-deactivator.php
 */
function deactivate_hyper_web_auth() {
	require_once HYPER_WEB_AUTH_PATH . 'includes/class-hyper-web-auth-deactivator.php';
	Hyper_Web_Auth_Deactivator::deactivate();
}

register_activation_hook( __FILE__, 'activate_hyper_web_auth' );
register_deactivation_hook( __FILE__, 'deactivate_hyper_web_auth' );

/**
 * Begins execution of the plugin.
 *
 * Deferred to the `plugins_loaded` hook so that WooCommerce and all other
 * plugins are guaranteed to be loaded before we check dependencies and
 * register WooCommerce-specific hooks.
 *
 * @since    1.0.0
 */
function run_hyper_web_auth() {

	// WooCommerce must be active. The `Requires Plugins: woocommerce` header
	// handles this natively on WordPress 6.5+, but this runtime check covers
	// older WordPress versions and edge cases where WooCommerce is deactivated
	// while this plugin remains active.
	if ( ! class_exists( 'WooCommerce' ) ) {
		add_action( 'admin_notices', 'hwa_woocommerce_missing_notice' );
		return;
	}

	require HYPER_WEB_AUTH_PATH . 'includes/class-hyper-web-auth.php';

	$plugin = new Hyper_Web_Auth();
	$plugin->run();
}
add_action( 'plugins_loaded', 'run_hyper_web_auth' );

/**
 * Displays an admin notice when WooCommerce is not active.
 *
 * Non-dismissible because the plugin cannot function without WooCommerce.
 *
 * @since    1.0.0
 */
function hwa_woocommerce_missing_notice() {

	// Load text domain so the notice is translatable even though the
	// orchestrator (which normally handles i18n) was not loaded.
	load_plugin_textdomain(
		'hyper-web-auth',
		false,
		dirname( HYPER_WEB_AUTH_BASENAME ) . '/languages/'
	);

	$message = sprintf(
		/* translators: 1: plugin name, 2: WooCommerce */
		esc_html__( '%1$s requires %2$s to be installed and activated.', 'hyper-web-auth' ),
		'<strong>HyperWeb Customer Authentication</strong>',
		'<strong>WooCommerce</strong>'
	);

	printf( '<div class="notice notice-error"><p>%s</p></div>', $message );
}

