<?php

/**
 * The file that defines the core plugin class
 *
 * A class definition that includes attributes and functions used across both the
 * public-facing side of the site and the admin area.
 *
 * @link       https://github.com/Sayan-Paul-200
 * @since      1.0.0
 *
 * @package    Hyper_Web_Auth
 * @subpackage Hyper_Web_Auth/includes
 */

/**
 * The core plugin class.
 *
 * This is used to define internationalization, admin-specific hooks, and
 * public-facing site hooks.
 *
 * Also maintains the unique identifier of this plugin as well as the current
 * version of the plugin.
 *
 * @since      1.0.0
 * @package    Hyper_Web_Auth
 * @subpackage Hyper_Web_Auth/includes
 * @author     Sayan Paul <sayanpaul666.ap@gmail.com>
 */
class Hyper_Web_Auth {

	/**
	 * The loader that's responsible for maintaining and registering all hooks that power
	 * the plugin.
	 *
	 * @since    1.0.0
	 * @access   protected
	 * @var      Hyper_Web_Auth_Loader    $loader    Maintains and registers all hooks for the plugin.
	 */
	protected $loader;

	/**
	 * The unique identifier of this plugin.
	 *
	 * @since    1.0.0
	 * @access   protected
	 * @var      string    $plugin_name    The string used to uniquely identify this plugin.
	 */
	protected $plugin_name;

	/**
	 * The current version of the plugin.
	 *
	 * @since    1.0.0
	 * @access   protected
	 * @var      string    $version    The current version of the plugin.
	 */
	protected $version;

	/**
	 * The settings functionality of the plugin.
	 *
	 * @since    1.0.0
	 * @access   protected
	 * @var      HWA_Settings    $settings    The settings instance.
	 */
	protected $settings;

	/**
	 * The Google OAuth service.
	 *
	 * @since    1.0.0
	 * @access   public
	 * @var      HWA_Google_OAuth_Service
	 */
	public $google_service;

	/**
	 * The Identity Repository.
	 *
	 * @since    1.0.0
	 * @access   public
	 * @var      HWA_Identity_Repository
	 */
	public $identity_repo;

	/**
	 * The Customer service.
	 *
	 * @since    1.0.0
	 * @access   public
	 * @var      HWA_Customer_Service
	 */
	public $customer_service;


	/**
	 * Define the core functionality of the plugin.
	 *
	 * Set the plugin name and the plugin version that can be used throughout the plugin.
	 * Load the dependencies, define the locale, and set the hooks for the admin area and
	 * the public-facing side of the site.
	 *
	 * @since    1.0.0
	 */
	public function __construct() {
		if ( defined( 'HYPER_WEB_AUTH_VERSION' ) ) {
			$this->version = HYPER_WEB_AUTH_VERSION;
		} else {
			$this->version = '1.0.0';
		}
		$this->plugin_name = 'hyper-web-auth';

		$this->load_dependencies();
		$this->set_locale();
		$this->define_admin_hooks();
		$this->define_rest_hooks();
		$this->define_cron_hooks();
		$this->define_public_hooks();

	}

	/**
	 * Load the required dependencies for this plugin.
	 *
	 * Include the following files that make up the plugin:
	 *
	 * - Hyper_Web_Auth_Loader. Orchestrates the hooks of the plugin.
	 * - Hyper_Web_Auth_i18n. Defines internationalization functionality.
	 * - Hyper_Web_Auth_Admin. Defines all hooks for the admin area.
	 * - Hyper_Web_Auth_Public. Defines all hooks for the public side of the site.
	 *
	 * Create an instance of the loader which will be used to register the hooks
	 * with WordPress.
	 *
	 * @since    1.0.0
	 * @access   private
	 */
	private function load_dependencies() {

		/**
		 * The class responsible for orchestrating the actions and filters of the
		 * core plugin.
		 */
		require_once HYPER_WEB_AUTH_PATH . 'includes/class-hyper-web-auth-loader.php';

		/**
		 * The class responsible for defining internationalization functionality
		 * of the plugin.
		 */
		require_once HYPER_WEB_AUTH_PATH . 'includes/class-hyper-web-auth-i18n.php';

		/**
		 * The class responsible for defining all actions that occur in the admin area.
		 */
		require_once HYPER_WEB_AUTH_PATH . 'admin/class-hyper-web-auth-admin.php';

		/**
		 * The class responsible for defining all actions that occur in the public-facing
		 * side of the site.
		 */
		require_once HYPER_WEB_AUTH_PATH . 'public/class-hyper-web-auth-public.php';

		/**
		 * The class responsible for cryptographic utilities and security.
		 */
		require_once HYPER_WEB_AUTH_PATH . 'includes/class-hwa-security.php';

		/**
		 * The class responsible for database installation and logging.
		 */
		require_once HYPER_WEB_AUTH_PATH . 'includes/class-hwa-database.php';

		/**
		 * The class responsible for handling the plugin settings via WooCommerce.
		 */
		require_once HYPER_WEB_AUTH_PATH . 'includes/class-hwa-settings.php';

		/**
		 * Database repository classes.
		 */
		require_once HYPER_WEB_AUTH_PATH . 'includes/class-hwa-identity-repository.php';
		require_once HYPER_WEB_AUTH_PATH . 'includes/class-hwa-oauth-state-repository.php';

		/**
		 * Authentication services.
		 */
		require_once HYPER_WEB_AUTH_PATH . 'includes/class-hwa-google-oauth-service.php';
		require_once HYPER_WEB_AUTH_PATH . 'includes/class-hwa-customer-service.php';

		/**
		 * REST API Controllers.
		 */
		require_once HYPER_WEB_AUTH_PATH . 'rest/class-hwa-rest-controller.php';

		$this->loader   = new Hyper_Web_Auth_Loader();
		$this->settings = new HWA_Settings();

		$this->identity_repo    = new HWA_Identity_Repository();
		$state_repo             = new HWA_OAuth_State_Repository();
		$this->google_service   = new HWA_Google_OAuth_Service( $state_repo );
		$this->customer_service = new HWA_Customer_Service();

	}

	/**
	 * Define the locale for this plugin for internationalization.
	 *
	 * Uses the Hyper_Web_Auth_i18n class in order to set the domain and to register the hook
	 * with WordPress.
	 *
	 * @since    1.0.0
	 * @access   private
	 */
	private function set_locale() {

		$plugin_i18n = new Hyper_Web_Auth_i18n();

		$this->loader->add_action( 'plugins_loaded', $plugin_i18n, 'load_plugin_textdomain' );

	}

	/**
	 * Register all of the hooks related to the admin area functionality
	 * of the plugin.
	 *
	 * @since    1.0.0
	 * @access   private
	 */
	private function define_admin_hooks() {

		$plugin_admin = new Hyper_Web_Auth_Admin( $this->get_plugin_name(), $this->get_version() );

		$this->loader->add_action( 'admin_enqueue_scripts', $plugin_admin, 'enqueue_styles' );
		$this->loader->add_action( 'admin_enqueue_scripts', $plugin_admin, 'enqueue_scripts' );

		// Admin notices for settings
		$this->loader->add_action( 'admin_notices', $this->settings, 'admin_notices' );

	}

	/**
	 * Register all of the REST API routes.
	 *
	 * @since    1.0.0
	 * @access   private
	 */
	private function define_rest_hooks() {
		$rest_controller = new HWA_REST_Controller(
			$this->google_service,
			$this->identity_repo,
			$this->customer_service
		);

		$this->loader->add_action( 'rest_api_init', $rest_controller, 'register_routes' );
	}

	/**
	 * Define hooks for automated background tasks.
	 *
	 * @since    1.0.0
	 * @access   private
	 */
	private function define_cron_hooks() {
		// Hook our hourly cleanup event to the state repository
		$state_repo = new HWA_OAuth_State_Repository();
		$this->loader->add_action( 'hwa_hourly_cleanup', $state_repo, 'cleanup_expired_states' );
	}

	/**
	 * Register all of the hooks related to the public-facing functionality
	 * of the plugin.
	 *
	 * @since    1.0.0
	 * @access   private
	 */
	private function define_public_hooks() {

		$plugin_public = new Hyper_Web_Auth_Public( $this->get_plugin_name(), $this->get_version() );

		$this->loader->add_action( 'wp_enqueue_scripts', $plugin_public, 'enqueue_styles' );
		$this->loader->add_action( 'wp_enqueue_scripts', $plugin_public, 'enqueue_scripts' );

		// WooCommerce Frontend Hooks for Google Button
		$this->loader->add_action( 'woocommerce_login_form_end', $plugin_public, 'render_google_login_button' );
		$this->loader->add_action( 'woocommerce_register_form_end', $plugin_public, 'render_google_register_button' );

		// Handle OAuth error redirects on the frontend
		$this->loader->add_action( 'template_redirect', $plugin_public, 'handle_url_errors' );

	}

	/**
	 * Run the loader to execute all of the hooks with WordPress.
	 *
	 * @since    1.0.0
	 */
	public function run() {
		$this->loader->run();
	}

	/**
	 * The name of the plugin used to uniquely identify it within the context of
	 * WordPress and to define internationalization functionality.
	 *
	 * @since     1.0.0
	 * @return    string    The name of the plugin.
	 */
	public function get_plugin_name() {
		return $this->plugin_name;
	}

	/**
	 * The reference to the class that orchestrates the hooks with the plugin.
	 *
	 * @since     1.0.0
	 * @return    Hyper_Web_Auth_Loader    Orchestrates the hooks of the plugin.
	 */
	public function get_loader() {
		return $this->loader;
	}

	/**
	 * Retrieve the version number of the plugin.
	 *
	 * @since     1.0.0
	 * @return    string    The version number of the plugin.
	 */
	public function get_version() {
		return $this->version;
	}

}
