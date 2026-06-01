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
	 * Initialize the controller.
	 *
	 * @param HWA_Google_OAuth_Service $google_service
	 * @param HWA_Identity_Repository  $identity_repo
	 * @param HWA_Customer_Service     $customer_service
	 */
	public function __construct( $google_service, $identity_repo, $customer_service ) {
		$this->namespace        = 'hyper-web-auth/v1';
		$this->google_service   = $google_service;
		$this->identity_repo    = $identity_repo;
		$this->customer_service = $customer_service;
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
		// Use status 302 to prevent browsers from caching the redirect with the single-use state.
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
		$code  = $request->get_param( 'code' );
		$state = $request->get_param( 'state' );
		$error = $request->get_param( 'error' );

		if ( ! empty( $error ) ) {
			return $this->redirect_with_error( __( 'Google login was cancelled or failed.', 'hyper-web-auth' ) );
		}

		if ( empty( $code ) || empty( $state ) ) {
			return $this->redirect_with_error( __( 'Invalid response from Google.', 'hyper-web-auth' ) );
		}

		$callback_data = $this->google_service->handle_callback( $code, $state );

		if ( is_wp_error( $callback_data ) ) {
			return $this->redirect_with_error( $callback_data->get_error_message() );
		}

		$profile    = $callback_data['profile'];
		$state_data = $callback_data['context'];

		// Resolve User (Phase 1.9 Flow Rules)
		$user_id = $this->resolve_user_from_profile( $profile, $state_data );

		if ( is_wp_error( $user_id ) ) {
			return $this->redirect_with_error( $user_id->get_error_message() );
		}

		// Set authentication cookies to log the user in.
		$this->customer_service->login_customer( $user_id );
		
		// Update last login timestamp in identities table.
		$identity = $this->identity_repo->find_google_identity( $profile['sub'] );
		if ( $identity ) {
			$this->identity_repo->update_last_login( $identity->id );
		}

		// Redirect home or to requested path.
		$redirect_url = $this->customer_service->get_default_redirect_url( $state_data['context'] );
		if ( ! empty( $state_data['return_to'] ) ) {
			// Ensure it's a safe local URL.
			$redirect_url = wp_safe_redirect( $state_data['return_to'], $redirect_url );
		}

		wp_safe_redirect( $redirect_url );
		exit;
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
			return (int) $identity->user_id;
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
	 * Redirects the user to the WooCommerce login page with an error notice.
	 *
	 * @param string $message The error message.
	 */
	private function redirect_with_error( $message ) {
		// Attach the error to the session via WooCommerce so it shows up as a notice.
		if ( function_exists( 'wc_add_notice' ) ) {
			wc_add_notice( $message, 'error' );
		}
		
		$login_url = wc_get_page_permalink( 'myaccount' );
		wp_safe_redirect( $login_url );
		exit;
	}

}
