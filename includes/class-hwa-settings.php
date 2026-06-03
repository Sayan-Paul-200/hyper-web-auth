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

		// Hook the setup of pre_option filters to 'init' to prevent 
		// "translation loaded too early" notices when calling get_settings_fields().
		add_action( 'init', array( $this, 'setup_pre_option_filters' ) );
	}

	/**
	 * Sets up the pre_option filters to intercept option gets.
	 * Hooked to 'init' to ensure translations are loaded.
	 *
	 * @since 1.0.0
	 */
	public function setup_pre_option_filters() {
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
		if ( 'firebase_authorized_domain' === $key ) {
			return parse_url( home_url(), PHP_URL_HOST );
		}
		if ( 'firebase_service_account_status' === $key ) {
			if ( defined( 'HWA_FIREBASE_SERVICE_ACCOUNT_JSON' ) && ! empty( HWA_FIREBASE_SERVICE_ACCOUNT_JSON ) ) {
				return __( '✅ Loaded from wp-config.php constant (HWA_FIREBASE_SERVICE_ACCOUNT_JSON)', 'hyper-web-auth' );
			}
			if ( defined( 'HWA_FIREBASE_SERVICE_ACCOUNT_PATH' ) && ! empty( HWA_FIREBASE_SERVICE_ACCOUNT_PATH ) ) {
				if ( file_exists( HWA_FIREBASE_SERVICE_ACCOUNT_PATH ) ) {
					return __( '✅ Loaded from wp-config.php constant path (HWA_FIREBASE_SERVICE_ACCOUNT_PATH)', 'hyper-web-auth' );
				}
				return __( '❌ Constant path defined but file does not exist', 'hyper-web-auth' );
			}
			$setting_path = get_option( self::OPTION_KEY, array() )['firebase_service_account_path'] ?? '';
			if ( ! empty( $setting_path ) ) {
				if ( file_exists( $setting_path ) ) {
					return __( '✅ Loaded from settings path', 'hyper-web-auth' );
				}
				return __( '❌ Settings path defined but file does not exist', 'hyper-web-auth' );
			}
			return __( '❌ Not configured — Firebase token verification will not work', 'hyper-web-auth' );
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
			'firebase_api_key'                    => '',
			'firebase_auth_domain'                => '',
			'firebase_project_id'                 => '',
			'firebase_app_id'                     => '',
			'firebase_messaging_sender_id'        => '',
			'firebase_measurement_id'             => '',
			'firebase_service_account_path'       => '',
			'firebase_default_country_code'       => '+91',
			'firebase_recaptcha_mode'             => 'invisible',
			'firebase_consent_text'               => __( 'By continuing, you agree to receive SMS verification codes. Message and data rates may apply.', 'hyper-web-auth' ),
			
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
			if ( in_array( $field['id'], array( 'hwa_google_redirect_uri', 'hwa_firebase_authorized_domain', 'hwa_firebase_service_account_status' ), true ) ) {
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
				'desc'  => __( '<strong>Phase 2 Setup:</strong> Configure your Firebase project before enabling this feature.<br/><br/>
					1. Go to the <a href="https://console.firebase.google.com/" target="_blank">Firebase Console</a> and create a project.<br/>
					2. Under <strong>Authentication > Sign-in method</strong>, enable the <strong>Phone</strong> provider.<br/>
					3. Under <strong>Authentication > Settings > Authorized domains</strong>, add the domain shown below.<br/>
					4. Under <strong>Project Settings > General</strong>, create a Web App and copy the SDK configuration values below.', 'hyper-web-auth' ),
				'id'    => 'hwa_firebase_title',
			),
			array(
				'title'   => __( 'Enable Firebase Phone Auth', 'hyper-web-auth' ),
				'desc'    => __( 'Allow users to log in using an SMS OTP.', 'hyper-web-auth' ),
				'id'      => 'hwa_firebase_phone_enabled',
				'type'    => 'checkbox',
				'default' => 'no',
			),
			array(
				'title'   => __( 'Enable Phone Registration', 'hyper-web-auth' ),
				'desc'    => __( 'Allow new users to create accounts using their phone number.', 'hyper-web-auth' ),
				'id'      => 'hwa_firebase_phone_registration_enabled',
				'type'    => 'checkbox',
				'default' => 'yes',
			),
			array(
				'title'             => __( 'Your Site Domain', 'hyper-web-auth' ),
				'desc'              => __( 'Add this domain to your Firebase Authorized Domains list.', 'hyper-web-auth' ),
				'id'                => 'hwa_firebase_authorized_domain',
				'type'              => 'text',
				'css'               => 'min-width:400px; background-color:#f0f0f1;',
				'custom_attributes' => array(
					'readonly' => 'readonly',
				),
			),
			array(
				'title' => __( 'Firebase API Key', 'hyper-web-auth' ),
				'desc'  => __( 'Found in your Firebase Web App config.', 'hyper-web-auth' ),
				'id'    => 'hwa_firebase_api_key',
				'type'  => 'text',
				'css'   => 'min-width:400px;',
			),
			array(
				'title' => __( 'Firebase Auth Domain', 'hyper-web-auth' ),
				'desc'  => __( 'e.g., your-project.firebaseapp.com', 'hyper-web-auth' ),
				'id'    => 'hwa_firebase_auth_domain',
				'type'  => 'text',
				'css'   => 'min-width:400px;',
			),
			array(
				'title' => __( 'Firebase Project ID', 'hyper-web-auth' ),
				'desc'  => __( 'Found in Firebase Project Settings.', 'hyper-web-auth' ),
				'id'    => 'hwa_firebase_project_id',
				'type'  => 'text',
				'css'   => 'min-width:400px;',
			),
			array(
				'title' => __( 'Firebase App ID', 'hyper-web-auth' ),
				'id'    => 'hwa_firebase_app_id',
				'type'  => 'text',
				'css'   => 'min-width:400px;',
			),
			array(
				'title' => __( 'Messaging Sender ID', 'hyper-web-auth' ),
				'id'    => 'hwa_firebase_messaging_sender_id',
				'type'  => 'text',
				'css'   => 'min-width:400px;',
			),
			array(
				'title' => __( 'Measurement ID', 'hyper-web-auth' ),
				'desc'  => __( '(Optional)', 'hyper-web-auth' ),
				'id'    => 'hwa_firebase_measurement_id',
				'type'  => 'text',
				'css'   => 'min-width:400px;',
			),
			array(
				'title' => __( 'Service Account JSON Path', 'hyper-web-auth' ),
				'desc'  => __( 'Absolute path on the server to your Firebase service account JSON file. This is required for verifying tokens securely. Recommended to place outside the web root.', 'hyper-web-auth' ),
				'id'    => 'hwa_firebase_service_account_path',
				'type'  => 'text',
				'css'   => 'min-width:400px;',
			),
			array(
				'title'             => __( 'Service Account Status', 'hyper-web-auth' ),
				'desc'              => __( 'Status of the backend token verification credentials.', 'hyper-web-auth' ),
				'id'                => 'hwa_firebase_service_account_status',
				'type'              => 'text',
				'css'               => 'min-width:400px; background-color:#f0f0f1;',
				'custom_attributes' => array(
					'readonly' => 'readonly',
				),
			),
			array(
				'title'   => __( 'Default Country Code', 'hyper-web-auth' ),
				'desc'    => __( 'The default country dialing code if the user does not enter one.', 'hyper-web-auth' ),
				'id'      => 'hwa_firebase_default_country_code',
				'type'    => 'text',
				'default' => '+91',
			),
			array(
				'title'   => __( 'reCAPTCHA Mode', 'hyper-web-auth' ),
				'desc'    => __( 'Choose how Firebase reCAPTCHA is displayed during phone login.', 'hyper-web-auth' ),
				'id'      => 'hwa_firebase_recaptcha_mode',
				'type'    => 'select',
				'options' => array(
					'invisible' => __( 'Invisible (Recommended)', 'hyper-web-auth' ),
					'normal'    => __( 'Visible Checkbox', 'hyper-web-auth' ),
				),
				'default' => 'invisible',
			),
			array(
				'title'   => __( 'Privacy / Consent Text', 'hyper-web-auth' ),
				'desc'    => __( 'Text displayed below the phone number input informing users about SMS processing.', 'hyper-web-auth' ),
				'id'      => 'hwa_firebase_consent_text',
				'type'    => 'textarea',
				'css'     => 'width:100%; height:80px;',
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

	/**
	 * Display admin notices if providers are enabled but credentials are missing.
	 *
	 * @since 1.0.0
	 */
	public function admin_notices() {
		$settings_url = admin_url( 'admin.php?page=wc-settings&tab=hyper_web_auth' );

		// Check Google OAuth configuration
		if ( 'yes' === self::get_setting( 'google_enabled' ) ) {
			$google_client = self::get_setting( 'google_client_id' );
			$google_secret = self::get_setting( 'google_client_secret' );

			if ( empty( $google_client ) || empty( $google_secret ) ) {
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

		// Check Firebase Phone Auth configuration
		if ( 'yes' === self::get_setting( 'firebase_phone_enabled' ) ) {
			$firebase_key     = self::get_setting( 'firebase_api_key' );
			$firebase_domain  = self::get_setting( 'firebase_auth_domain' );
			$firebase_project = self::get_setting( 'firebase_project_id' );

			if ( empty( $firebase_key ) || empty( $firebase_domain ) || empty( $firebase_project ) ) {
				?>
				<div class="notice notice-error is-dismissible">
					<p>
						<strong><?php esc_html_e( 'Hyper Web Auth Configuration Error:', 'hyper-web-auth' ); ?></strong>
						<?php esc_html_e( 'Firebase Phone Auth is enabled, but critical API keys/domains are missing. Phone login will not work.', 'hyper-web-auth' ); ?>
						<a href="<?php echo esc_url( $settings_url ); ?>"><?php esc_html_e( 'Configure Settings', 'hyper-web-auth' ); ?></a>
					</p>
				</div>
				<?php
			}

			// Check service account status
			$sa_status = self::get_setting( 'firebase_service_account_status' );
			if ( strpos( (string) $sa_status, '❌' ) !== false ) {
				?>
				<div class="notice notice-error is-dismissible">
					<p>
						<strong><?php esc_html_e( 'Hyper Web Auth Configuration Error:', 'hyper-web-auth' ); ?></strong>
						<?php esc_html_e( 'Firebase Phone Auth is enabled, but the Service Account is not properly configured. Server-side token verification will fail.', 'hyper-web-auth' ); ?>
						<a href="<?php echo esc_url( $settings_url ); ?>"><?php esc_html_e( 'Configure Settings', 'hyper-web-auth' ); ?></a>
					</p>
				</div>
				<?php
			}
		}
	}

}
