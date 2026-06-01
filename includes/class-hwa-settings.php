<?php

/**
 * Settings functionality of the plugin.
 *
 * @link       https://github.com/Sayan-Paul-200
 * @since      1.0.0
 *
 * @package    Hyper_Web_Auth
 * @subpackage Hyper_Web_Auth/includes
 */

/**
 * Defines the settings framework.
 *
 * Hooks into WooCommerce settings tabs to render the UI using native WooCommerce
 * field arrays, but intercepts the load/save process to store all values inside
 * a single array option (`hwa_settings`).
 *
 * @package    Hyper_Web_Auth
 * @subpackage Hyper_Web_Auth/includes
 * @author     Sayan Paul <sayanpaul666.ap@gmail.com>
 */
class HWA_Settings {

	/**
	 * The single option key in wp_options.
	 *
	 * @var string
	 */
	const OPTION_KEY = 'hwa_settings';

	/**
	 * Initialize the class and set its properties.
	 *
	 * @since    1.0.0
	 */
	public function __construct() {
		// Register the new WooCommerce settings tab.
		add_filter( 'woocommerce_settings_tabs_array', array( $this, 'add_settings_tab' ), 50 );
		add_action( 'woocommerce_settings_tabs_hyper_web_auth', array( $this, 'render_settings' ) );
		add_action( 'woocommerce_update_options_hyper_web_auth', array( $this, 'save_settings' ) );

		// Intercept get_option() calls made by WooCommerce admin fields to supply
		// the values from our single `hwa_settings` array option.
		$fields = $this->get_settings_fields();
		foreach ( $fields as $field ) {
			if ( isset( $field['id'] ) && strpos( $field['id'], 'hwa_' ) === 0 ) {
				add_filter( 'pre_option_' . $field['id'], array( $this, 'intercept_option_get' ), 10, 3 );
			}
		}
	}

	/**
	 * Retrieves a specific setting securely.
	 *
	 * Priorities:
	 * 1. PHP Constant (e.g., HWA_GOOGLE_CLIENT_ID)
	 * 2. Saved Database Option inside `hwa_settings` array
	 * 3. Default fallback value
	 *
	 * @since  1.0.0
	 * @param  string $key     The setting key (e.g., 'google_client_id').
	 * @param  mixed  $default The default value if not set.
	 * @return mixed
	 */
	public static function get_setting( $key, $default = '' ) {
		// 1. Check for constant override.
		$constant_name = 'HWA_' . strtoupper( $key );
		if ( defined( $constant_name ) ) {
			return constant( $constant_name );
		}

		// Read-only computed values.
		if ( 'google_redirect_uri' === $key ) {
			return rest_url( 'hyper-web-auth/v1/google/callback' );
		}

		// 2. Check saved options array.
		$settings = get_option( self::OPTION_KEY, array() );
		if ( is_array( $settings ) && isset( $settings[ $key ] ) ) {
			return $settings[ $key ];
		}

		// 3. Check master plan defaults.
		$defaults = array(
			'google_enabled'                      => 'no',
			'google_auto_create_customer'         => 'yes',
			'google_match_existing_email'         => 'no',
			'firebase_phone_enabled'              => 'no',
			'firebase_phone_registration_enabled' => 'yes',
			'firebase_default_country_code'       => '+91',
			'firebase_recaptcha_mode'             => 'invisible',
			'firebase_use_test_numbers_notice'    => 'yes',
			'account_linking_enabled'             => 'no',
			'delete_data_on_uninstall'            => 'no',
			'debug_logging'                       => 'no',
		);

		if ( isset( $defaults[ $key ] ) ) {
			return $defaults[ $key ];
		}

		return $default;
	}

	/**
	 * Adds the "Hyper Web Auth" tab to WooCommerce settings.
	 *
	 * @since  1.0.0
	 * @param  array $settings_tabs Dictionary of tabs.
	 * @return array
	 */
	public function add_settings_tab( $settings_tabs ) {
		$settings_tabs['hyper_web_auth'] = __( 'Hyper Web Auth', 'hyper-web-auth' );
		return $settings_tabs;
	}

	/**
	 * Uses the WooCommerce Admin Fields API to output the settings.
	 *
	 * @since  1.0.0
	 */
	public function render_settings() {
		woocommerce_admin_fields( $this->get_settings_fields() );
	}

	/**
	 * Intercepts `get_option` for WooCommerce fields and maps it to our array.
	 *
	 * @since  1.0.0
	 * @param  mixed  $pre_option The pre_option filter value.
	 * @param  string $option     The option name being requested.
	 * @param  mixed  $default    The default value.
	 * @return mixed
	 */
	public function intercept_option_get( $pre_option, $option, $default ) {
		$key = str_replace( 'hwa_', '', $option );
		return self::get_setting( $key, $default );
	}

	/**
	 * Saves the settings into the single `hwa_settings` option array.
	 *
	 * @since  1.0.0
	 */
	public function save_settings() {
		$settings = get_option( self::OPTION_KEY, array() );
		if ( ! is_array( $settings ) ) {
			$settings = array();
		}

		$fields = $this->get_settings_fields();
		foreach ( $fields as $field ) {
			if ( ! isset( $field['id'] ) || ! isset( $field['type'] ) || in_array( $field['type'], array( 'title', 'sectionend' ), true ) ) {
				continue;
			}

			// Read-only fields shouldn't be saved.
			if ( 'hwa_google_redirect_uri' === $field['id'] ) {
				continue;
			}

			$id  = $field['id'];
			$key = str_replace( 'hwa_', '', $id );

			// WooCommerce POSTs the fields by their native ID.
			if ( 'checkbox' === $field['type'] ) {
				$settings[ $key ] = isset( $_POST[ $id ] ) ? 'yes' : 'no';
			} elseif ( isset( $_POST[ $id ] ) ) {
				$val = wp_unslash( $_POST[ $id ] );
				// Basic sanitization based on type.
				if ( 'text' === $field['type'] || 'password' === $field['type'] ) {
					$settings[ $key ] = sanitize_text_field( $val );
				} else {
					$settings[ $key ] = wc_clean( $val );
				}
			}
		}

		update_option( self::OPTION_KEY, $settings );
	}

	/**
	 * Get all settings fields for the plugin.
	 *
	 * @since  1.0.0
	 * @return array
	 */
	private function get_settings_fields() {
		return array(
			array(
				'title' => __( 'Google OAuth', 'hyper-web-auth' ),
				'type'  => 'title',
				'desc'  => __( 'Configure Google login credentials. Your Redirect URI must be added to your Google Cloud Console.', 'hyper-web-auth' ),
				'id'    => 'hwa_google_title',
			),
			array(
				'title'   => __( 'Enable Google Login', 'hyper-web-auth' ),
				'desc'    => __( 'Allow customers to log in and register using their Google account.', 'hyper-web-auth' ),
				'id'      => 'hwa_google_enabled',
				'type'    => 'checkbox',
				'default' => 'no',
			),
			array(
				'title'    => __( 'Client ID', 'hyper-web-auth' ),
				'desc'     => __( 'Your Google OAuth Client ID.', 'hyper-web-auth' ),
				'id'       => 'hwa_google_client_id',
				'type'     => 'text',
				'css'      => 'min-width:300px;',
				'desc_tip' => true,
			),
			array(
				'title'    => __( 'Client Secret', 'hyper-web-auth' ),
				'desc'     => __( 'Your Google OAuth Client Secret.', 'hyper-web-auth' ),
				'id'       => 'hwa_google_client_secret',
				'type'     => 'password',
				'css'      => 'min-width:300px;',
				'desc_tip' => true,
			),
			array(
				'title'             => __( 'Redirect URI', 'hyper-web-auth' ),
				'desc'              => __( 'Copy this URL and add it to your Google Cloud Console authorized redirect URIs.', 'hyper-web-auth' ),
				'id'                => 'hwa_google_redirect_uri',
				'type'              => 'text',
				'css'               => 'min-width:400px; background-color:#f0f0f1;',
				'custom_attributes' => array(
					'readonly' => 'readonly',
				),
			),
			array(
				'title'   => __( 'Auto-create Customers', 'hyper-web-auth' ),
				'desc'    => __( 'Automatically create a WooCommerce customer when a new Google user logs in.', 'hyper-web-auth' ),
				'id'      => 'hwa_google_auto_create_customer',
				'type'    => 'checkbox',
				'default' => 'yes',
			),
			array(
				'title'   => __( 'Match Existing Email', 'hyper-web-auth' ),
				'desc'    => __( 'If a new Google login matches an existing customer email, automatically link them. WARNING: Leaving this disabled is safer (requires manual linking from My Account).', 'hyper-web-auth' ),
				'id'      => 'hwa_google_match_existing_email',
				'type'    => 'checkbox',
				'default' => 'no',
			),
			array(
				'type' => 'sectionend',
				'id'   => 'hwa_google_title',
			),

			array(
				'title' => __( 'Firebase Phone Auth', 'hyper-web-auth' ),
				'type'  => 'title',
				'desc'  => __( 'Configure Firebase Phone Number Sign-In settings. (Implemented in Phase 2)', 'hyper-web-auth' ),
				'id'    => 'hwa_firebase_title',
			),
			array(
				'type' => 'sectionend',
				'id'   => 'hwa_firebase_title',
			),

			array(
				'title' => __( 'Security & Advanced', 'hyper-web-auth' ),
				'type'  => 'title',
				'desc'  => __( 'General security and logging settings.', 'hyper-web-auth' ),
				'id'    => 'hwa_security_title',
			),
			array(
				'title'   => __( 'Debug Logging', 'hyper-web-auth' ),
				'desc'    => __( 'Enable verbose debug logging for troubleshooting.', 'hyper-web-auth' ),
				'id'      => 'hwa_debug_logging',
				'type'    => 'checkbox',
				'default' => 'no',
			),
			array(
				'title'   => __( 'Delete Data on Uninstall', 'hyper-web-auth' ),
				'desc'    => __( 'Erase all identities and plugin data when uninstalling this plugin.', 'hyper-web-auth' ),
				'id'      => 'hwa_delete_data_on_uninstall',
				'type'    => 'checkbox',
				'default' => 'no',
			),
			array(
				'type' => 'sectionend',
				'id'   => 'hwa_security_title',
			),
		);
	}
	}

	/**
	 * Display admin notices if Google is enabled but credentials are missing.
	 *
	 * @since 1.0.0
	 */
	public function admin_notices() {
		if ( 'yes' !== self::get_setting( 'google_enabled' ) ) {
			return;
		}

		$client_id = self::get_setting( 'google_client_id' );
		$secret    = self::get_setting( 'google_client_secret' );

		if ( empty( $client_id ) || empty( $secret ) ) {
			$settings_url = admin_url( 'admin.php?page=wc-settings&tab=hyper_web_auth' );
			?>
			<div class="notice notice-error is-dismissible">
				<p>
					<strong><?php esc_html_e( 'Hyper Web Auth Configuration Error:', 'hyper-web-auth' ); ?></strong>
					<?php esc_html_e( 'Google Login is enabled, but the Client ID or Client Secret is missing. Google Login will not work on the frontend.', 'hyper-web-auth' ); ?>
					<a href="<?php echo esc_url( $settings_url ); ?>"><?php esc_html_e( 'Configure Settings', 'hyper-web-auth' ); ?></a>
				</p>
			</div>
			<?php
		}
	}

}
