<?php

/**
 * REST API Controller for Hyper Web Auth.
 *
 * @link       https://github.com/Sayan-Paul-200
 * @since      1.0.0
 *
 * @package    Hyper_Web_Auth
 * @subpackage Hyper_Web_Auth/rest
 */

/**
 * The REST Controller Class.
 *
 * Handles API endpoints for initiating OAuth flows and processing callbacks.
 *
 * @package    Hyper_Web_Auth
 * @subpackage Hyper_Web_Auth/rest
 * @author     Sayan Paul <sayanpaul666.ap@gmail.com>
 */
class HWA_REST_Controller extends WP_REST_Controller {

	/**
	 * The Google OAuth Service instance.
	 *
	 * @var HWA_Google_OAuth_Service
	 */
	private $google_service;

	/**
	 * The Identity Repository instance.
	 *
	 * @var HWA_Identity_Repository
	 */
	private $identity_repo;

	/**
	 * The Customer Service instance.
	 *
	 * @var HWA_Customer_Service
	 */
	private $customer_service;

	/**
	 * The Firebase Auth Service instance.
	 *
	 * @var HWA_Firebase_Auth_Service
	 */
	private $firebase_service;

	/**
	 * The Rate Limiter instance.
	 *
	 * @var HWA_Rate_Limiter
	 */
	private $rate_limiter;

	/**
	 * Initialize the controller.
	 *
	 * @param HWA_Google_OAuth_Service  $google_service
	 * @param HWA_Identity_Repository   $identity_repo
	 * @param HWA_Customer_Service      $customer_service
	 * @param HWA_Firebase_Auth_Service $firebase_service
	 * @param HWA_Rate_Limiter          $rate_limiter
	 */
	public function __construct( $google_service, $identity_repo, $customer_service, $firebase_service, $rate_limiter ) {
		$this->namespace        = 'hyper-web-auth/v1';
		$this->google_service   = $google_service;
		$this->identity_repo    = $identity_repo;
		$this->customer_service = $customer_service;
		$this->firebase_service = $firebase_service;
		$this->rate_limiter     = $rate_limiter;
	}

	/**
	 * Register the REST routes.
	 *
	 * @since 1.0.0
	 */
	public function register_routes() {
		// Route: /wp-json/hyper-web-auth/v1/google/start
		register_rest_route(
			$this->namespace,
			'/google/start',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'google_start' ),
				'permission_callback' => '__return_true', // Public route
			)
		);

		// Route: /wp-json/hyper-web-auth/v1/google/callback
		register_rest_route(
			$this->namespace,
			'/google/callback',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'google_callback' ),
				'permission_callback' => '__return_true', // Public route
			)
		);

		// --- Firebase Phone Routes ---

		// Route: /wp-json/hyper-web-auth/v1/firebase-phone/login/preflight
		register_rest_route(
			$this->namespace,
			'/firebase-phone/login/preflight',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'firebase_phone_login_preflight' ),
				'permission_callback' => '__return_true', // Public route
			)
		);

		// Route: /wp-json/hyper-web-auth/v1/firebase-phone/login/complete
		register_rest_route(
			$this->namespace,
			'/firebase-phone/login/complete',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'firebase_phone_login_complete' ),
				'permission_callback' => '__return_true', // Public route
			)
		);

		// Route: /wp-json/hyper-web-auth/v1/firebase-phone/register/preflight
		register_rest_route(
			$this->namespace,
			'/firebase-phone/register/preflight',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'firebase_phone_register_preflight' ),
				'permission_callback' => '__return_true', // Public route
			)
		);

		// Route: /wp-json/hyper-web-auth/v1/firebase-phone/register/complete
		register_rest_route(
			$this->namespace,
			'/firebase-phone/register/complete',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'firebase_phone_register_complete' ),
				'permission_callback' => '__return_true', // Public route
			)
		);

		// Route: /wp-json/hyper-web-auth/v1/firebase-phone/link/preflight
		register_rest_route(
			$this->namespace,
			'/firebase-phone/link/preflight',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'firebase_phone_link_preflight' ),
				'permission_callback' => 'is_user_logged_in', // Must be logged in
			)
		);

		// Route: /wp-json/hyper-web-auth/v1/firebase-phone/link/complete
		register_rest_route(
			$this->namespace,
			'/firebase-phone/link/complete',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'firebase_phone_link_complete' ),
				'permission_callback' => 'is_user_logged_in', // Must be logged in
			)
		);

		// Route: /wp-json/hyper-web-auth/v1/unlink
		register_rest_route(
			$this->namespace,
			'/unlink',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'unlink_identity' ),
				'permission_callback' => 'is_user_logged_in', // Must be logged in
			)
		);
	}

	/**
	 * Initiates the Google OAuth flow.
	 *
	 * @since 1.0.0
	 * @param WP_REST_Request $request
	 */
	public function google_start( $request ) {
		$is_enabled = HWA_Settings::get_setting( 'google_enabled' );
		if ( 'yes' !== $is_enabled ) {
			return new WP_Error( 'google_disabled', __( 'Google login is disabled.', 'hyper-web-auth' ), array( 'status' => 403 ) );
		}

		$context   = $request->get_param( 'context' ) ?: 'login';
		$return_to = $request->get_param( 'return_to' ) ?: '';

		// Validate context
		$valid_contexts = array( 'login', 'register', 'link_google' );
		if ( ! in_array( $context, $valid_contexts, true ) ) {
			return new WP_Error( 'invalid_context', __( 'Invalid login context.', 'hyper-web-auth' ), array( 'status' => 400 ) );
		}

		$user_id = null;
		if ( 'link_google' === $context ) {
			if ( ! is_user_logged_in() ) {
				// Must be logged in to link an account
				return new WP_Error( 'not_logged_in', __( 'You must be logged in to link a Google account.', 'hyper-web-auth' ), array( 'status' => 401 ) );
			}
			$user_id = get_current_user_id();
		}

		$auth_url = $this->google_service->get_authorization_url( $context, $return_to, $user_id );

		if ( is_wp_error( $auth_url ) ) {
			return $auth_url;
		}

		// Perform the redirect to Google.
		// CRITICAL: Prevent all caching of this redirect response.
		// Each request generates a unique, single-use state token. If the browser
		// or a server-side proxy (CDN, Nginx, LiteSpeed) caches this 302 response,
		// subsequent clicks will reuse a stale/consumed state token and fail.
		nocache_headers();
		header( 'Cache-Control: no-store, no-cache, must-revalidate, max-age=0, private' );
		header( 'Pragma: no-cache' );
		header( 'Expires: 0' );
		wp_redirect( $auth_url, 302 );
		exit;
	}

	/**
	 * Handles the Google OAuth callback and executes the Phase 1.9 Flow Rules.
	 *
	 * @since 1.0.0
	 * @param WP_REST_Request $request
	 */
	public function google_callback( $request ) {
		error_log( '[HWA] google_callback ENTERED' );

		try {
			$code  = $request->get_param( 'code' );
			$state = $request->get_param( 'state' );
			$error = $request->get_param( 'error' );
			$ip    = HWA_Security::get_client_ip();

			error_log( '[HWA] code=' . ( $code ? 'present' : 'MISSING' ) . ' state=' . ( $state ? 'present' : 'MISSING' ) . ' error=' . ( $error ?: 'none' ) );

			if ( ! empty( $error ) ) {
				error_log( '[HWA] Google returned error: ' . $error );
				HWA_Database::log_auth_event( null, 'google', 'login_failed', 'failed', $ip, 'Google returned error: ' . $error );
				return $this->redirect_with_error( __( 'Google login was cancelled or failed.', 'hyper-web-auth' ) );
			}

			if ( empty( $code ) || empty( $state ) ) {
				error_log( '[HWA] Missing code or state' );
				HWA_Database::log_auth_event( null, 'google', 'login_failed', 'failed', $ip, 'Missing code or state.' );
				return $this->redirect_with_error( __( 'Invalid response from Google.', 'hyper-web-auth' ) );
			}

			$callback_data = $this->google_service->handle_callback( $code, $state );

			if ( is_wp_error( $callback_data ) ) {
				error_log( '[HWA] handle_callback error: ' . $callback_data->get_error_message() );
				HWA_Database::log_auth_event( null, 'google', 'login_failed', 'failed', $ip, $callback_data->get_error_message() );
				return $this->redirect_with_error( $callback_data->get_error_message() );
			}

			$profile    = $callback_data['profile'];
			$state_data = $callback_data['context'];

			error_log( '[HWA] Profile email: ' . $profile['email'] . ' | Context: ' . $state_data['context'] );

			// Resolve User (Phase 1.9 Flow Rules)
			$user_id = $this->resolve_user_from_profile( $profile, $state_data );

			if ( is_wp_error( $user_id ) ) {
				error_log( '[HWA] resolve_user_from_profile error: ' . $user_id->get_error_message() );
				HWA_Database::log_auth_event( null, 'google', 'login_failed', 'failed', $ip, $user_id->get_error_message() );
				return $this->redirect_with_error( $user_id->get_error_message() );
			}

			error_log( '[HWA] Resolved user_id: ' . $user_id );

			// Set authentication cookies to log the user in.
			$this->customer_service->login_customer( $user_id );
			
			error_log( '[HWA] login_customer called for user_id: ' . $user_id );
			
			// Log success
			HWA_Database::log_auth_event( $user_id, 'google', 'login_success', 'success', $ip, 'Google OAuth flow completed.' );
			
			// Update last login timestamp in identities table.
			$identity = $this->identity_repo->find_google_identity( $profile['sub'] );
			if ( $identity ) {
				$this->identity_repo->update_last_login( $identity->id );
			}

			// Redirect home or to requested path.
			$redirect_url = $this->customer_service->get_default_redirect_url( $state_data['context'] );
			if ( ! empty( $state_data['return_to'] ) ) {
				// Ensure it's a safe local URL.
				$redirect_url = HWA_Security::safe_redirect_url( $state_data['return_to'], $redirect_url );
			}

			error_log( '[HWA] Redirecting to: ' . $redirect_url );

			// Use nocache_headers to prevent caching of the redirect.
			nocache_headers();
			wp_safe_redirect( $redirect_url );
			exit;

		} catch ( \Throwable $e ) {
			error_log( '[HWA] UNCAUGHT EXCEPTION in google_callback: ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine() );
			error_log( '[HWA] Stack trace: ' . $e->getTraceAsString() );
			return $this->redirect_with_error( __( 'An unexpected error occurred during authentication.', 'hyper-web-auth' ) );
		}
	}

	/**
	 * Implements the Flow Rules to determine which user logs in.
	 *
	 * @param array $profile
	 * @param array $state_data
	 * @return int|WP_Error User ID on success, WP_Error on failure.
	 */
	private function resolve_user_from_profile( $profile, $state_data ) {
		$context = $state_data['context'];

		// Context: link_google
		if ( 'link_google' === $context ) {
			$current_user_id = $state_data['user_id'];
			if ( ! $current_user_id ) {
				return new WP_Error( 'invalid_link', __( 'Invalid linking context. You must be logged in.', 'hyper-web-auth' ) );
			}

			// Check if this Google sub is already linked to someone else
			$existing = $this->identity_repo->find_google_identity( $profile['sub'] );
			if ( $existing && (int) $existing->user_id !== (int) $current_user_id ) {
				return new WP_Error( 'already_linked', __( 'This Google account is already linked to another user.', 'hyper-web-auth' ) );
			}

			if ( ! $existing ) {
				$this->identity_repo->create_google_identity( $current_user_id, $profile['sub'], $profile['email'], $profile['email_verified'] );
			}

			return $current_user_id;
		}

		// Look up existing identity mapping
		$identity = $this->identity_repo->find_google_identity( $profile['sub'] );

		// Case A: Google sub already exists in hwa_identities -> Login linked user.
		if ( $identity ) {
			// Ensure the WordPress user actually still exists (in case it was deleted manually via WP Admin).
			$user_exists = get_userdata( (int) $identity->user_id );
			if ( $user_exists ) {
				return (int) $identity->user_id;
			}
			
			// The user was deleted from WordPress, but the identity row remained. Orphaned record detected!
			// Delete the orphaned identity record and proceed as if they are a new user.
			$this->identity_repo->delete_identity( $identity->id );
		}

		// Sub does not exist. Check if email exists in WP.
		if ( empty( $profile['email'] ) ) {
			return new WP_Error( 'no_email', __( 'Google did not provide an email address.', 'hyper-web-auth' ) );
		}

		$existing_customer = $this->customer_service->find_customer_by_email( $profile['email'] );

		if ( $existing_customer ) {
			// Email exists in WordPress.
			
			// We strictly require email_verified to link automatically.
			if ( ! $profile['email_verified'] ) {
				return new WP_Error( 'unverified_email', __( 'Your Google email is unverified. Cannot link account.', 'hyper-web-auth' ) );
			}

			$match_enabled = HWA_Settings::get_setting( 'google_match_existing_email' );

			if ( 'yes' === $match_enabled ) {
				// Case B: Match setting enabled -> Link Google identity to existing customer, then login.
				$this->identity_repo->create_google_identity( $existing_customer->ID, $profile['sub'], $profile['email'], true );
				return $existing_customer->ID;
			} else {
				// Case C: Match setting disabled -> Do not link silently.
				return new WP_Error( 'email_exists', __( 'An account with this email already exists. Please log in with your password and link Google from your My Account page.', 'hyper-web-auth' ) );
			}
		}

		// Email does not exist in WP.
		$auto_create = HWA_Settings::get_setting( 'google_auto_create_customer' );

		if ( 'yes' === $auto_create ) {
			// Case D: Auto-create enabled -> Create customer, link identity, login.
			$new_user_id = $this->customer_service->create_customer_from_google_profile( $profile );

			if ( is_wp_error( $new_user_id ) ) {
				return $new_user_id;
			}

			$this->identity_repo->create_google_identity( $new_user_id, $profile['sub'], $profile['email'], $profile['email_verified'] );
			
			return $new_user_id;
		}

		// Auto-create is disabled, and email doesn't exist.
		return new WP_Error( 'registration_disabled', __( 'Registration via Google is currently disabled.', 'hyper-web-auth' ) );
	}

	/**
	 * Renders a safe error and redirects back.
	 *
	 * @since 1.0.0
	 * @param string $message
	 * @param string $return_to
	 * @param string $code
	 */
	private function redirect_with_error( $message, $return_to = '', $code = 'hwa_error' ) {
		if ( empty( $return_to ) ) {
			$return_to = wc_get_page_permalink( 'myaccount' );
		}

		// Initialize the WooCommerce session if it doesn't exist, to safely use wc_add_notice.
		// WC()->session might be null in REST API requests.
		if ( class_exists( 'WooCommerce' ) ) {
			if ( ! WC()->session ) {
				WC()->session = new WC_Session_Handler();
				WC()->session->init();
			}
			wc_add_notice( $message, 'error' );
		}

		// Fallback to URL parameters if notice doesn't stick
		// Encode the message in base64 so it can safely pass through the URL and be caught by HWA_Public::handle_url_errors
		$redirect_url = add_query_arg(
			'hwa_error',
			base64_encode( $message ),
			$return_to
		);

		wp_redirect( $redirect_url );
		exit;
	}

	// -------------------------------------------------------------------------
	// FIREBASE PHONE ENDPOINTS
	// -------------------------------------------------------------------------

	/**
	 * Preflight check for phone login.
	 *
	 * @since 1.0.0
	 * @param WP_REST_Request $request
	 */
	public function firebase_phone_login_preflight( $request ) {
		$phone = $request->get_param( 'phone' );
		if ( empty( $phone ) ) {
			return new WP_Error( 'missing_phone', __( 'Phone number is required.', 'hyper-web-auth' ), array( 'status' => 400 ) );
		}

		$phone_e164 = HWA_Security::normalize_phone( $phone );
		if ( ! HWA_Security::is_valid_phone( $phone_e164 ) ) {
			return new WP_Error( 'invalid_phone', __( 'Invalid phone number format.', 'hyper-web-auth' ), array( 'status' => 400 ) );
		}

		$ip = HWA_Security::get_client_ip();

		// Check rate limits
		$limit_check = $this->rate_limiter->check_firebase_preflight_limit( $phone_e164, $ip );
		if ( is_wp_error( $limit_check ) ) {
			return $limit_check;
		}

		// Check database identity
		$identity = $this->identity_repo->find_firebase_phone_by_phone( $phone_e164 );

		if ( ! $identity ) {
			return new WP_Error( 'phone_not_found', __( 'Phone number does not exist. Please sign up.', 'hyper-web-auth' ), array( 'status' => 404 ) );
		}

		// Success. Record attempt and allow frontend to proceed.
		$this->rate_limiter->record_firebase_preflight_attempt( $phone_e164, $ip );

		return rest_ensure_response( array(
			'success' => true,
			'message' => 'Preflight passed. SMS allowed.',
		) );
	}

	/**
	 * Completes the phone login after Firebase SMS verification.
	 *
	 * @since 1.0.0
	 * @param WP_REST_Request $request
	 */
	public function firebase_phone_login_complete( $request ) {
		$phone             = $request->get_param( 'phone' );
		$firebase_id_token = $request->get_param( 'firebase_id_token' );
		$return_to         = $request->get_param( 'return_to' ) ?: wc_get_page_permalink( 'myaccount' );
		$return_to         = HWA_Security::safe_redirect_url( $return_to );

		if ( empty( $phone ) || empty( $firebase_id_token ) ) {
			return new WP_Error( 'missing_params', __( 'Phone and token are required.', 'hyper-web-auth' ), array( 'status' => 400 ) );
		}

		$ip = HWA_Security::get_client_ip();

		// Check IP verification limit
		$verify_limit = $this->rate_limiter->check_firebase_verification_limit( $ip );
		if ( is_wp_error( $verify_limit ) ) {
			return clone clone $verify_limit;
		}

		$phone_e164 = HWA_Security::normalize_phone( $phone );

		try {
			// Verify token server-side
			$claims = $this->firebase_service->verify_id_token( $firebase_id_token );
			
			// Extract claims
			$token_phone = $this->firebase_service->get_phone_from_verified_token( $claims );
			
			// Assert phones match
			$this->firebase_service->assert_phone_matches_expected( $token_phone, $phone_e164 );

		} catch ( \Exception $e ) {
			$this->rate_limiter->record_firebase_verification_failure( $ip );
			return new WP_Error( 'verification_failed', $e->getMessage(), array( 'status' => 401 ) );
		}

		// Token is valid and matches requested phone. Find the user.
		$identity = $this->identity_repo->find_firebase_phone_by_phone( $phone_e164 );

		if ( ! $identity ) {
			return new WP_Error( 'phone_not_found', __( 'Phone number does not exist. Please sign up.', 'hyper-web-auth' ), array( 'status' => 404 ) );
		}

		// Verify user exists in WP
		$user = get_userdata( (int) $identity->user_id );
		if ( ! $user ) {
			// Orphaned record
			$this->identity_repo->delete_identity( $identity->id );
			return new WP_Error( 'user_not_found', __( 'User account deleted or corrupted. Please sign up again.', 'hyper-web-auth' ), array( 'status' => 404 ) );
		}

		// Login successful!
		$this->rate_limiter->clear_firebase_verification_failures( $ip );
		$this->identity_repo->update_firebase_phone_last_login( $identity->id );

		wp_set_current_user( $user->ID );
		wp_set_auth_cookie( $user->ID, true );

		HWA_Database::log_auth_event(
			$user->ID,
			'firebase_phone',
			'login_success',
			'success',
			$ip,
			'Phone login: ' . HWA_Security::mask_phone( $phone_e164 )
		);

		return rest_ensure_response( array(
			'success'      => true,
			'redirect_url' => $return_to,
		) );
	}

	/**
	 * Preflight check for phone registration.
	 *
	 * @since 1.0.0
	 * @param WP_REST_Request $request
	 */
	public function firebase_phone_register_preflight( $request ) {
		// Check if registration is enabled
		if ( 'yes' !== HWA_Settings::get_setting( 'firebase_phone_registration_enabled' ) ) {
			return new WP_Error( 'registration_disabled', __( 'Phone registration is disabled.', 'hyper-web-auth' ), array( 'status' => 403 ) );
		}

		$phone = $request->get_param( 'phone' );
		if ( empty( $phone ) ) {
			return new WP_Error( 'missing_phone', __( 'Phone number is required.', 'hyper-web-auth' ), array( 'status' => 400 ) );
		}

		$phone_e164 = HWA_Security::normalize_phone( $phone );
		if ( ! HWA_Security::is_valid_phone( $phone_e164 ) ) {
			return new WP_Error( 'invalid_phone', __( 'Invalid phone number format.', 'hyper-web-auth' ), array( 'status' => 400 ) );
		}

		$ip = HWA_Security::get_client_ip();

		// Check rate limits
		$limit_check = $this->rate_limiter->check_firebase_preflight_limit( $phone_e164, $ip );
		if ( is_wp_error( $limit_check ) ) {
			return clone $limit_check;
		}

		// Check database identity
		$identity = $this->identity_repo->find_firebase_phone_by_phone( $phone_e164 );

		if ( $identity ) {
			return new WP_Error( 'phone_exists', __( 'Phone number already exists. Please login.', 'hyper-web-auth' ), array( 'status' => 409 ) );
		}

		// Success. Record attempt and allow frontend to proceed.
		$this->rate_limiter->record_firebase_preflight_attempt( $phone_e164, $ip );

		return rest_ensure_response( array(
			'success' => true,
			'message' => 'Preflight passed. SMS allowed.',
		) );
	}

	/**
	 * Completes the phone registration after Firebase SMS verification.
	 *
	 * @since 1.0.0
	 * @param WP_REST_Request $request
	 */
	public function firebase_phone_register_complete( $request ) {
		// Check if registration is enabled
		if ( 'yes' !== HWA_Settings::get_setting( 'firebase_phone_registration_enabled' ) ) {
			return new WP_Error( 'registration_disabled', __( 'Phone registration is disabled.', 'hyper-web-auth' ), array( 'status' => 403 ) );
		}

		$phone             = $request->get_param( 'phone' );
		$firebase_id_token = $request->get_param( 'firebase_id_token' );
		$email             = $request->get_param( 'email' );
		$first_name        = $request->get_param( 'first_name' );
		$last_name         = $request->get_param( 'last_name' );
		$return_to         = $request->get_param( 'return_to' ) ?: wc_get_page_permalink( 'myaccount' );
		$return_to         = HWA_Security::safe_redirect_url( $return_to );

		if ( empty( $phone ) || empty( $firebase_id_token ) ) {
			return new WP_Error( 'missing_params', __( 'Phone and token are required.', 'hyper-web-auth' ), array( 'status' => 400 ) );
		}

		if ( empty( $first_name ) || empty( $last_name ) ) {
			return new WP_Error( 'missing_fields', __( 'First name and last name are required for registration.', 'hyper-web-auth' ), array( 'status' => 400 ) );
		}

		// If email is not provided, generate a placeholder based on the phone number.
		// The user can update their real email later from My Account.
		if ( empty( $email ) ) {
			$phone_e164_for_hash = HWA_Security::normalize_phone( $phone );
			$hash               = substr( hash( 'sha256', $phone_e164_for_hash ), 0, 12 );
			$site_domain         = wp_parse_url( home_url(), PHP_URL_HOST );
			$email               = 'phone.' . $hash . '@' . $site_domain;
		}

		// Validate email (whether user-provided or generated)
		if ( ! is_email( $email ) ) {
			return new WP_Error( 'invalid_email', __( 'Invalid email address.', 'hyper-web-auth' ), array( 'status' => 400 ) );
		}

		// Check if email already exists in WooCommerce
		if ( email_exists( $email ) ) {
			return new WP_Error( 'email_exists', __( 'An account is already registered with your email address. Please log in.', 'hyper-web-auth' ), array( 'status' => 409 ) );
		}

		$ip = HWA_Security::get_client_ip();

		// Check IP verification limit
		$verify_limit = $this->rate_limiter->check_firebase_verification_limit( $ip );
		if ( is_wp_error( $verify_limit ) ) {
			return clone clone clone clone $verify_limit;
		}

		$phone_e164 = HWA_Security::normalize_phone( $phone );

		try {
			// Verify token server-side
			$claims = $this->firebase_service->verify_id_token( $firebase_id_token );
			
			// Extract claims
			$firebase_uid = $this->firebase_service->get_uid_from_verified_token( $claims );
			$token_phone  = $this->firebase_service->get_phone_from_verified_token( $claims );
			
			// Assert phones match
			$this->firebase_service->assert_phone_matches_expected( $token_phone, $phone_e164 );

		} catch ( \Exception $e ) {
			$this->rate_limiter->record_firebase_verification_failure( $ip );
			return new WP_Error( 'verification_failed', $e->getMessage(), array( 'status' => 401 ) );
		}

		// Double check that phone and UID do not already exist (race condition prevention)
		if ( $this->identity_repo->find_firebase_phone_by_phone( $phone_e164 ) ) {
			return new WP_Error( 'phone_exists', __( 'Phone number already exists. Please login.', 'hyper-web-auth' ), array( 'status' => 409 ) );
		}
		
		if ( $this->identity_repo->find_firebase_phone_by_uid( $firebase_uid ) ) {
			return new WP_Error( 'uid_exists', __( 'Firebase UID is already linked to an account.', 'hyper-web-auth' ), array( 'status' => 409 ) );
		}

		// Provision the WooCommerce customer
		$user_id = $this->customer_service->create_customer( $email, $first_name, $last_name );

		if ( is_wp_error( $user_id ) ) {
			return $user_id;
		}

		// Link the identity
		$identity_id = $this->identity_repo->create_firebase_phone_identity( $user_id, $firebase_uid, $phone_e164, true );

		if ( ! $identity_id ) {
			return new WP_Error( 'identity_creation_failed', __( 'Failed to link phone number to new account.', 'hyper-web-auth' ), array( 'status' => 500 ) );
		}

		// Registration and link successful. Log them in!
		$this->rate_limiter->clear_firebase_verification_failures( $ip );
		
		wp_set_current_user( $user_id );
		wp_set_auth_cookie( $user_id, true );

		HWA_Database::log_auth_event(
			$user_id,
			'firebase_phone',
			'register_success',
			'success',
			$ip,
			'Phone registration: ' . HWA_Security::mask_phone( $phone_e164 )
		);

		return rest_ensure_response( array(
			'success'      => true,
			'redirect_url' => $return_to,
		) );
	}

	/**
	 * Preflight check for phone linking.
	 *
	 * @since 1.0.0
	 * @param WP_REST_Request $request
	 */
	public function firebase_phone_link_preflight( $request ) {
		$user_id = get_current_user_id();
		if ( ! $user_id ) {
			return new WP_Error( 'not_logged_in', __( 'You must be logged in to link a phone number.', 'hyper-web-auth' ), array( 'status' => 401 ) );
		}

		$phone = $request->get_param( 'phone' );
		if ( empty( $phone ) ) {
			return new WP_Error( 'missing_phone', __( 'Phone number is required.', 'hyper-web-auth' ), array( 'status' => 400 ) );
		}

		$phone_e164 = HWA_Security::normalize_phone( $phone );
		if ( ! HWA_Security::is_valid_phone( $phone_e164 ) ) {
			return new WP_Error( 'invalid_phone', __( 'Invalid phone number format.', 'hyper-web-auth' ), array( 'status' => 400 ) );
		}

		$ip = HWA_Security::get_client_ip();

		// Check rate limits
		$limit_check = $this->rate_limiter->check_firebase_preflight_limit( $phone_e164, $ip );
		if ( is_wp_error( $limit_check ) ) {
			return $limit_check;
		}

		// Check database identity
		$identity = $this->identity_repo->find_firebase_phone_by_phone( $phone_e164 );

		if ( $identity ) {
			if ( (int) $identity->user_id === (int) $user_id ) {
				return new WP_Error( 'phone_already_linked', __( 'This phone number is already linked to your account.', 'hyper-web-auth' ), array( 'status' => 400 ) );
			} else {
				return new WP_Error( 'phone_taken', __( 'This phone number is already linked to another account.', 'hyper-web-auth' ), array( 'status' => 400 ) );
			}
		}

		// Success. Record attempt and allow frontend to proceed.
		HWA_Database::log_auth_event( null, 'firebase_phone', 'link_preflight', 'success', $ip, 'Phone link preflight passed: ' . HWA_Security::mask_phone( $phone_e164 ) );

		return rest_ensure_response( array(
			'success' => true,
		) );
	}

	/**
	 * Completes the phone linking flow.
	 *
	 * @since 1.0.0
	 * @param WP_REST_Request $request
	 */
	public function firebase_phone_link_complete( $request ) {
		$user_id = get_current_user_id();
		if ( ! $user_id ) {
			return new WP_Error( 'not_logged_in', __( 'You must be logged in to link a phone number.', 'hyper-web-auth' ), array( 'status' => 401 ) );
		}

		$phone             = $request->get_param( 'phone' );
		$firebase_id_token = $request->get_param( 'firebase_id_token' );
		$ip                = HWA_Security::get_client_ip();

		if ( empty( $phone ) || empty( $firebase_id_token ) ) {
			HWA_Database::log_auth_event( $user_id, 'firebase_phone', 'link_failed', 'failed', $ip, 'Missing phone or token.' );
			return new WP_Error( 'missing_data', __( 'Missing phone number or verification token.', 'hyper-web-auth' ), array( 'status' => 400 ) );
		}

		$phone_e164 = HWA_Security::normalize_phone( $phone );

		$verify_limit = $this->rate_limiter->check_firebase_verification_limit( $phone_e164, $ip );
		if ( is_wp_error( $verify_limit ) ) {
			HWA_Database::log_auth_event( $user_id, 'firebase_phone', 'link_failed', 'failed', $ip, 'Verification rate limit exceeded.' );
			return $verify_limit;
		}

		$verified_uid = $this->firebase_service->verify_id_token( $firebase_id_token, $phone_e164 );

		if ( is_wp_error( $verified_uid ) ) {
			$this->rate_limiter->record_verification_failure( $phone_e164, $ip );
			HWA_Database::log_auth_event( $user_id, 'firebase_phone', 'link_failed', 'failed', $ip, 'Token verification failed: ' . $verified_uid->get_error_message() );
			return new WP_Error( 'verification_failed', $verified_uid->get_error_message(), array( 'status' => 401 ) );
		}

		// Token is valid and phone matches!
		$this->rate_limiter->clear_firebase_verification_failures( $ip );

		// Final check to prevent race conditions
		$identity = $this->identity_repo->find_firebase_phone_by_phone( $phone_e164 );
		if ( $identity ) {
			if ( (int) $identity->user_id === (int) $user_id ) {
				return rest_ensure_response( array(
					'success' => true,
					'message' => __( 'Phone already linked.', 'hyper-web-auth' ),
				) );
			} else {
				return new WP_Error( 'phone_taken', __( 'This phone number is already linked to another account.', 'hyper-web-auth' ), array( 'status' => 400 ) );
			}
		}

		// Create identity mapping
		$identity_id = $this->identity_repo->create_firebase_phone_identity( $user_id, $verified_uid, $phone_e164, true );

		if ( ! $identity_id ) {
			HWA_Database::log_auth_event( $user_id, 'firebase_phone', 'link_failed', 'failed', $ip, 'Failed to insert identity row.' );
			return new WP_Error( 'internal_error', __( 'Could not link phone to account due to a database error.', 'hyper-web-auth' ), array( 'status' => 500 ) );
		}

		HWA_Database::log_auth_event( $user_id, 'firebase_phone', 'link_success', 'success', $ip, 'Phone linking complete.' );

		return rest_ensure_response( array(
			'success'  => true,
		) );
	}

	/**
	 * Unlinks an identity from the current user.
	 *
	 * @since 1.0.0
	 * @param WP_REST_Request $request
	 */
	public function unlink_identity( $request ) {
		$user_id = get_current_user_id();
		if ( ! $user_id ) {
			return new WP_Error( 'not_logged_in', __( 'You must be logged in to unlink an account.', 'hyper-web-auth' ), array( 'status' => 401 ) );
		}

		$provider = $request->get_param( 'provider' );
		if ( ! in_array( $provider, array( 'google', 'firebase_phone' ), true ) ) {
			return new WP_Error( 'invalid_provider', __( 'Invalid authentication provider.', 'hyper-web-auth' ), array( 'status' => 400 ) );
		}

		$ip = HWA_Security::get_client_ip();

		// Fetch all identities for this user to evaluate lockout risk.
		$google_identity = $this->identity_repo->find_user_google_identity( $user_id );
		$phone_identity  = $this->identity_repo->find_user_firebase_phone_identity( $user_id );

		$identities_count = 0;
		if ( $google_identity ) $identities_count++;
		if ( $phone_identity )  $identities_count++;

		// Determine which identity the user wants to unlink.
		$target_identity_id = null;
		if ( 'google' === $provider && $google_identity ) {
			$target_identity_id = $google_identity->id;
		} elseif ( 'firebase_phone' === $provider && $phone_identity ) {
			$target_identity_id = $phone_identity->id;
		}

		if ( ! $target_identity_id ) {
			return new WP_Error( 'identity_not_found', __( 'This login method is not linked to your account.', 'hyper-web-auth' ), array( 'status' => 404 ) );
		}

		// --- Lockout Prevention Safety Check ---
		if ( $identities_count <= 1 ) {
			// This is their ONLY external identity. Check their email.
			$user = get_userdata( $user_id );
			$email = $user->user_email;
			
			// Does the email start with our auto-generated placeholder format?
			if ( strpos( $email, 'phone.' ) === 0 && strpos( $email, '@' ) !== false ) {
				HWA_Database::log_auth_event( $user_id, $provider, 'unlink_failed', 'failed', $ip, 'Unlink blocked by lockout prevention.' );
				return new WP_Error( 'lockout_risk', __( 'You cannot unlink your only login method because you do not have an email address set for password recovery. Please link another method or update your email first.', 'hyper-web-auth' ), array( 'status' => 403 ) );
			}
		}

		// Proceed with unlinking.
		$deleted = $this->identity_repo->delete_identity( $target_identity_id );

		if ( ! $deleted ) {
			return new WP_Error( 'delete_failed', __( 'Could not remove the linked account due to a database error.', 'hyper-web-auth' ), array( 'status' => 500 ) );
		}

		HWA_Database::log_auth_event( $user_id, $provider, 'unlink_success', 'success', $ip, 'Successfully unlinked ' . $provider . ' identity.' );

		return rest_ensure_response( array(
			'success' => true,
		) );
	}

}
