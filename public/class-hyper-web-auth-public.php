<?php

/**
 * The public-facing functionality of the plugin.
 *
 * @link       https://github.com/Sayan-Paul-200
 * @since      1.0.0
 *
 * @package    Hyper_Web_Auth
 * @subpackage Hyper_Web_Auth/public
 */

/**
 * The public-facing functionality of the plugin.
 *
 * Defines the plugin name, version, and two examples hooks for how to
 * enqueue the public-facing stylesheet and JavaScript.
 *
 * @package    Hyper_Web_Auth
 * @subpackage Hyper_Web_Auth/public
 * @author     Sayan Paul <sayanpaul666.ap@gmail.com>
 */
class Hyper_Web_Auth_Public {

	/**
	 * The ID of this plugin.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      string    $plugin_name    The ID of this plugin.
	 */
	private $plugin_name;

	/**
	 * The version of this plugin.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      string    $version    The current version of this plugin.
	 */
	private $version;

	/**
	 * Initialize the class and set its properties.
	 *
	 * @since    1.0.0
	 * @param      string    $plugin_name       The name of the plugin.
	 * @param      string    $version    The version of this plugin.
	 */
	public function __construct( $plugin_name, $version ) {

		$this->plugin_name = $plugin_name;
		$this->version = $version;

	}

	/**
	 * Register the stylesheets for the public-facing side of the site.
	 *
	 * @since    1.0.0
	 */
	public function enqueue_styles() {

		/**
		 * This function is provided for demonstration purposes only.
		 *
		 * An instance of this class should be passed to the run() function
		 * defined in Hyper_Web_Auth_Loader as all of the hooks are defined
		 * in that particular class.
		 *
		 * The Hyper_Web_Auth_Loader will then create the relationship
		 * between the defined hooks and the functions defined in this
		 * class.
		 */

		wp_enqueue_style( $this->plugin_name, plugin_dir_url( __FILE__ ) . 'css/hyper-web-auth-public.css', array(), $this->version, 'all' );

	}

	/**
	 * Register the JavaScript for the public-facing side of the site.
	 *
	 * @since    1.0.0
	 */
	public function enqueue_scripts() {

		/**
		 * This function is provided for demonstration purposes only.
		 *
		 * An instance of this class should be passed to the run() function
		 * defined in Hyper_Web_Auth_Loader as all of the hooks are defined
		 * in that particular class.
		 *
		 * The Hyper_Web_Auth_Loader will then create the relationship
		 * between the defined hooks and the functions defined in this
		 * class.
		 */

		wp_enqueue_script( $this->plugin_name, plugin_dir_url( __FILE__ ) . 'js/hyper-web-auth-public.js', array( 'jquery' ), $this->version, false );

	}

	/**
	 * Renders the Google Login button on the WooCommerce login form.
	 *
	 * @since 1.0.0
	 */
	public function render_google_login_button() {
		$this->render_google_button( 'login', __( 'Continue with Google', 'hyper-web-auth' ) );
	}

	/**
	 * Renders the Google Register button on the WooCommerce registration form.
	 *
	 * @since 1.0.0
	 */
	public function render_google_register_button() {
		$this->render_google_button( 'register', __( 'Sign up with Google', 'hyper-web-auth' ) );
	}

	/**
	 * Core logic to render the Google OAuth button.
	 *
	 * @since 1.0.0
	 * @param string $context The flow context ('login' or 'register').
	 * @param string $button_text The text to display on the button.
	 */
	private function render_google_button( $context, $button_text ) {
		// Do not show if the user is already logged in.
		if ( is_user_logged_in() ) {
			return;
		}

		// Do not show if Google login is disabled in settings.
		if ( 'yes' !== HWA_Settings::get_setting( 'google_enabled' ) ) {
			return;
		}

		$auth_url = rest_url( 'hyper-web-auth/v1/google/start?context=' . $context );

		// Optionally pass the current URL so we can return them to the exact page they were on.
		global $wp;
		if ( ! empty( $wp->request ) ) {
			$return_to = home_url( $wp->request );
			$auth_url = add_query_arg( 'return_to', urlencode( $return_to ), $auth_url );
		}

		include plugin_dir_path( __FILE__ ) . 'partials/google-button.php';
	}

	/**
	 * Intercepts errors passed via URL parameter (from REST API redirects)
	 * and adds them to the active WooCommerce session so they display properly.
	 *
	 * @since 1.0.0
	 */
	public function handle_url_errors() {
		// Only run if WooCommerce and its notice functions are active
		if ( ! function_exists( 'wc_add_notice' ) ) {
			return;
		}

		if ( isset( $_GET['hwa_error'] ) && ! empty( $_GET['hwa_error'] ) ) {
			$encoded_message = sanitize_text_field( wp_unslash( $_GET['hwa_error'] ) );
			$message = base64_decode( $encoded_message, true );

			if ( $message ) {
				// Add the notice. Since we're in template_redirect, the WC session is active.
				wc_add_notice( wp_kses_post( $message ), 'error' );
				
				// Strip the error from the URL to prevent it from showing again on refresh
				$clean_url = remove_query_arg( 'hwa_error' );
				wp_safe_redirect( $clean_url );
				exit;
			}
		}
	}

}
