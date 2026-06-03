<?php

/**
 * Service for Firebase Authentication operations.
 *
 * @link       https://github.com/Sayan-Paul-200
 * @since      1.0.0
 *
 * @package    Hyper_Web_Auth
 * @subpackage Hyper_Web_Auth/includes
 */

use Kreait\Firebase\Factory;
use Kreait\Firebase\Exception\AuthException;
use Kreait\Firebase\Exception\FirebaseException;

/**
 * The Firebase Authentication Service.
 *
 * Responsible for verifying Firebase ID tokens server-side, 
 * extracting claims (phone number, UID), and interacting with Firebase Auth.
 *
 * @package    Hyper_Web_Auth
 * @subpackage Hyper_Web_Auth/includes
 * @author     Sayan Paul <sayanpaul666.ap@gmail.com>
 */
class HWA_Firebase_Auth_Service {

	/**
	 * The configured Kreait Firebase Auth instance.
	 *
	 * @since  1.0.0
	 * @access private
	 * @var    \Kreait\Firebase\Contract\Auth|null
	 */
	private $auth = null;

	/**
	 * Whether the service is properly configured and available.
	 *
	 * @since  1.0.0
	 * @access private
	 * @var    bool
	 */
	private $is_available = false;

	/**
	 * Initialize the class and set its properties.
	 *
	 * @since 1.0.0
	 */
	public function __construct() {
		$this->initialize_firebase();
	}

	/**
	 * Initializes the Kreait Firebase Factory.
	 *
	 * Checks for the service account configuration via constants or settings.
	 *
	 * @since  1.0.0
	 * @access private
	 */
	private function initialize_firebase() {
		if ( ! class_exists( '\Kreait\Firebase\Factory' ) ) {
			return; // Soft failure: dependencies not installed
		}

		$service_account = $this->get_service_account_config();

		if ( empty( $service_account ) ) {
			return; // Soft failure: not configured — Service Account JSON is required
		}

		try {
			$factory = ( new Factory() );

			// Determine if $service_account is a JSON string or a file path.
			if ( is_string( $service_account ) && strpos( trim( $service_account ), '{' ) === 0 ) {
				$factory = $factory->withServiceAccount( json_decode( $service_account, true ) );
			} else {
				$factory = $factory->withServiceAccount( $service_account );
			}

			$this->auth         = $factory->createAuth();
			$this->is_available = true;
		} catch ( \Throwable $e ) {
			error_log( '[HWA Firebase] Failed to initialize Firebase Factory: ' . $e->getMessage() );
			$this->is_available = false;
		}
	}

	/**
	 * Resolves the service account configuration.
	 *
	 * Order of precedence:
	 * 1. HWA_FIREBASE_SERVICE_ACCOUNT_JSON (constant)
	 * 2. HWA_FIREBASE_SERVICE_ACCOUNT_PATH (constant)
	 * 3. hwa_firebase_service_account_path (setting)
	 *
	 * @since  1.0.0
	 * @access private
	 * @return string|null The JSON string or absolute path.
	 */
	private function get_service_account_config() {
		if ( defined( 'HWA_FIREBASE_SERVICE_ACCOUNT_JSON' ) && ! empty( HWA_FIREBASE_SERVICE_ACCOUNT_JSON ) ) {
			return HWA_FIREBASE_SERVICE_ACCOUNT_JSON;
		}

		if ( defined( 'HWA_FIREBASE_SERVICE_ACCOUNT_PATH' ) && ! empty( HWA_FIREBASE_SERVICE_ACCOUNT_PATH ) ) {
			if ( file_exists( HWA_FIREBASE_SERVICE_ACCOUNT_PATH ) ) {
				return HWA_FIREBASE_SERVICE_ACCOUNT_PATH;
			}
		}

		$setting_path = HWA_Settings::get_setting( 'firebase_service_account_path' );
		if ( ! empty( $setting_path ) && file_exists( $setting_path ) ) {
			return $setting_path;
		}

		return null;
	}

	/**
	 * Checks if the Firebase Auth service is available for use.
	 *
	 * @since  1.0.0
	 * @return bool
	 */
	public function is_available() {
		return $this->is_available && null !== $this->auth;
	}

	/**
	 * Verifies a Firebase ID token.
	 *
	 * @since  1.0.0
	 * @param  string $id_token The raw JWT string.
	 * @return array The verified claims.
	 * @throws \Exception If the token is invalid, expired, or verification fails.
	 */
	public function verify_id_token( $id_token ) {
		if ( ! $this->is_available() ) {
			throw new \Exception( 'Firebase Auth service is not available or misconfigured.' );
		}

		try {
			// Kreait verifies the signature, issuer, audience, and expiry automatically.
			$verified_token = $this->auth->verifyIdToken( $id_token );
			
			// Return claims as an array
			return $verified_token->claims()->all();

		} catch ( AuthException $e ) {
			error_log( '[HWA Firebase] Token verification failed (AuthException): ' . $e->getMessage() );
			throw new \Exception( 'Invalid Firebase ID token.' );
		} catch ( FirebaseException $e ) {
			error_log( '[HWA Firebase] Token verification failed (FirebaseException): ' . $e->getMessage() );
			throw new \Exception( 'Firebase error during token verification.' );
		} catch ( \Throwable $e ) {
			error_log( '[HWA Firebase] Unexpected token verification error: ' . $e->getMessage() );
			throw new \Exception( 'An unexpected error occurred during token verification.' );
		}
	}

	/**
	 * Extracts the Firebase UID (subject) from verified claims.
	 *
	 * @since  1.0.0
	 * @param  array $verified_claims
	 * @return string
	 * @throws \Exception If UID is missing.
	 */
	public function get_uid_from_verified_token( $verified_claims ) {
		if ( empty( $verified_claims['sub'] ) ) {
			throw new \Exception( 'Firebase token is missing the UID (sub) claim.' );
		}
		return $verified_claims['sub'];
	}

	/**
	 * Extracts the phone number from verified claims.
	 *
	 * @since  1.0.0
	 * @param  array $verified_claims
	 * @return string
	 * @throws \Exception If phone number is missing.
	 */
	public function get_phone_from_verified_token( $verified_claims ) {
		if ( empty( $verified_claims['phone_number'] ) ) {
			throw new \Exception( 'Firebase token is missing the phone_number claim.' );
		}
		return $verified_claims['phone_number'];
	}

	/**
	 * Asserts that the token's phone number matches the expected phone number.
	 *
	 * Both phone numbers should ideally be normalized to E.164.
	 *
	 * @since  1.0.0
	 * @param  string $token_phone
	 * @param  string $expected_phone_e164
	 * @throws \Exception If they do not match.
	 */
	public function assert_phone_matches_expected( $token_phone, $expected_phone_e164 ) {
		if ( $token_phone !== $expected_phone_e164 ) {
			error_log( sprintf( '[HWA Firebase] Phone mismatch! Token phone: %s | Expected phone: %s', $token_phone, $expected_phone_e164 ) );
			throw new \Exception( 'The phone number verified by Firebase does not match the expected phone number.' );
		}
	}

}
