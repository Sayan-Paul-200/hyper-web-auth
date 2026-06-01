<?php

/**
 * Service for Google OAuth/OpenID Connect.
 *
 * @link       https://github.com/Sayan-Paul-200
 * @since      1.0.0
 *
 * @package    Hyper_Web_Auth
 * @subpackage Hyper_Web_Auth/includes
 */

use Firebase\JWT\JWT;
use Firebase\JWT\JWK;

/**
 * Google OAuth Service Class.
 *
 * Handles generating authorization URLs, exchanging codes for tokens,
 * and securely validating Google ID tokens using JWKS.
 *
 * @package    Hyper_Web_Auth
 * @subpackage Hyper_Web_Auth/includes
 * @author     Sayan Paul <sayanpaul666.ap@gmail.com>
 */
class HWA_Google_OAuth_Service {

	/**
	 * The state repository instance.
	 *
	 * @var HWA_OAuth_State_Repository
	 */
	private $state_repo;

	/**
	 * Google OAuth endpoints.
	 */
	const AUTH_URL  = 'https://accounts.google.com/o/oauth2/v2/auth';
	const TOKEN_URL = 'https://oauth2.googleapis.com/token';
	const CERTS_URL = 'https://www.googleapis.com/oauth2/v3/certs';

	/**
	 * Initialize the service.
	 *
	 * @since 1.0.0
	 * @param HWA_OAuth_State_Repository $state_repo
	 */
	public function __construct( $state_repo ) {
		$this->state_repo = $state_repo;
	}

	/**
	 * Generates the Google authorization URL for the user to visit.
	 *
	 * @since  1.0.0
	 * @param  string $context   The flow context ('login', 'register', 'link_google').
	 * @param  string $return_to The URL to return to after completion.
	 * @param  int|null $user_id Optional user ID for linking.
	 * @return string|WP_Error The URL, or WP_Error if settings are missing.
	 */
	public function get_authorization_url( $context, $return_to = '', $user_id = null ) {
		$client_id    = HWA_Settings::get_setting( 'google_client_id' );
		$redirect_uri = HWA_Settings::get_setting( 'google_redirect_uri' );

		if ( empty( $client_id ) || empty( $redirect_uri ) ) {
			return new WP_Error( 'missing_config', __( 'Google OAuth Client ID or Redirect URI is missing.', 'hyper-web-auth' ) );
		}

		// Generate and store secure state hash.
		$raw_state = $this->state_repo->create_state( 'google', $context, $return_to, $user_id );

		$args = array(
			'client_id'     => $client_id,
			'redirect_uri'  => $redirect_uri,
			'response_type' => 'code',
			'scope'         => 'openid email profile',
			'state'         => $raw_state,
			'access_type'   => 'online',
			'prompt'        => 'select_account',
		);

		return add_query_arg( urlencode_deep( $args ), self::AUTH_URL );
	}

	/**
	 * Handles the OAuth callback from Google.
	 * Exchanges the authorization code for an ID token and validates it.
	 *
	 * @since  1.0.0
	 * @param  string $code  The authorization code from Google.
	 * @param  string $state The raw state parameter from Google.
	 * @return array|WP_Error Array containing profile and state context, or WP_Error on failure.
	 */
	public function handle_callback( $code, $state ) {
		// 1. Verify and consume the state.
		$state_data = $this->state_repo->consume_state( $state );
		if ( ! $state_data ) {
			return new WP_Error( 'invalid_state', __( 'Invalid or expired state parameter. Please try again.', 'hyper-web-auth' ) );
		}

		// 2. Exchange authorization code for tokens.
		$client_id     = HWA_Settings::get_setting( 'google_client_id' );
		$client_secret = HWA_Settings::get_setting( 'google_client_secret' );
		$redirect_uri  = HWA_Settings::get_setting( 'google_redirect_uri' );

		if ( empty( $client_secret ) ) {
			return new WP_Error( 'missing_secret', __( 'Google OAuth Client Secret is missing.', 'hyper-web-auth' ) );
		}

		$response = wp_remote_post( self::TOKEN_URL, array(
			'body' => array(
				'code'          => $code,
				'client_id'     => $client_id,
				'client_secret' => $client_secret,
				'redirect_uri'  => $redirect_uri,
				'grant_type'    => 'authorization_code',
			),
		) );

		if ( is_wp_error( $response ) ) {
			return new WP_Error( 'token_exchange_failed', __( 'Failed to exchange authorization code with Google.', 'hyper-web-auth' ) );
		}

		$body = wp_remote_retrieve_body( $response );
		$data = json_decode( $body, true );

		if ( isset( $data['error'] ) ) {
			return new WP_Error( 'google_error', $data['error_description'] ?? $data['error'] );
		}

		if ( empty( $data['id_token'] ) ) {
			return new WP_Error( 'missing_id_token', __( 'Google did not return an ID token.', 'hyper-web-auth' ) );
		}

		// 3. Validate the ID Token.
		$decoded_token = $this->validate_id_token( $data['id_token'] );
		if ( is_wp_error( $decoded_token ) ) {
			return $decoded_token; // Return the validation error.
		}

		return array(
			'profile' => array(
				'sub'            => $decoded_token->sub,
				'email'          => isset( $decoded_token->email ) ? $decoded_token->email : '',
				'email_verified' => isset( $decoded_token->email_verified ) ? (bool) $decoded_token->email_verified : false,
				'name'           => isset( $decoded_token->name ) ? $decoded_token->name : '',
				'given_name'     => isset( $decoded_token->given_name ) ? $decoded_token->given_name : '',
				'family_name'    => isset( $decoded_token->family_name ) ? $decoded_token->family_name : '',
			),
			'context' => $state_data,
		);
	}

	/**
	 * Cryptographically validates the Google ID token using JWKS.
	 *
	 * @since  1.0.0
	 * @param  string $id_token The JWT string.
	 * @return object|WP_Error The decoded token payload on success, WP_Error on failure.
	 */
	private function validate_id_token( $id_token ) {
		$client_id = HWA_Settings::get_setting( 'google_client_id' );
		$jwks      = $this->get_google_jwks();

		if ( is_wp_error( $jwks ) ) {
			return $jwks;
		}

		try {
			// In firebase/php-jwt ^6.0+, JWK::parseKeySet returns an array of Key objects.
			$keys = JWK::parseKeySet( $jwks );

			// Decode and mathematically verify RSA signature.
			// The JWT library handles expiration ('exp') and not-before ('nbf') automatically.
			$decoded = JWT::decode( $id_token, $keys );

			// Strict Audience and Issuer validation
			$valid_issuers = array( 'accounts.google.com', 'https://accounts.google.com' );
			if ( ! in_array( $decoded->iss, $valid_issuers, true ) ) {
				throw new Exception( 'Invalid token issuer.' );
			}

			if ( $decoded->aud !== $client_id ) {
				throw new Exception( 'Invalid token audience.' );
			}

			if ( empty( $decoded->sub ) ) {
				throw new Exception( 'Token is missing subject (sub).' );
			}

			return $decoded;

		} catch ( Exception $e ) {
			return new WP_Error( 'invalid_token', __( 'Invalid ID token: ', 'hyper-web-auth' ) . $e->getMessage() );
		}
	}

	/**
	 * Fetches Google's public JSON Web Keys (JWKS) with caching.
	 *
	 * @since  1.0.0
	 * @return array|WP_Error The decoded JWKS array, or WP_Error on failure.
	 */
	private function get_google_jwks() {
		$transient_key = 'hwa_google_jwks';
		$cached_jwks   = get_transient( $transient_key );

		if ( false !== $cached_jwks ) {
			return $cached_jwks;
		}

		$response = wp_remote_get( self::CERTS_URL );

		if ( is_wp_error( $response ) ) {
			return new WP_Error( 'jwks_fetch_failed', __( 'Failed to fetch Google public keys.', 'hyper-web-auth' ) );
		}

		$body = wp_remote_retrieve_body( $response );
		$jwks = json_decode( $body, true );

		if ( empty( $jwks ) || ! isset( $jwks['keys'] ) ) {
			return new WP_Error( 'invalid_jwks', __( 'Google returned invalid public keys.', 'hyper-web-auth' ) );
		}

		// Cache for 1 hour to balance performance and key rotation.
		set_transient( $transient_key, $jwks, HOUR_IN_SECONDS );

		return $jwks;
	}

}
