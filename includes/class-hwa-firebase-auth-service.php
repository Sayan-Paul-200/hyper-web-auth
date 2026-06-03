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
use Firebase\JWT\JWT;
use Firebase\JWT\JWK;
use Firebase\JWT\Key;

/**
 * The Firebase Authentication Service.
 *
 * Responsible for verifying Firebase ID tokens server-side, 
 * extracting claims (phone number, UID), and interacting with Firebase Auth.
 *
 * Supports two verification strategies:
 * 1. Kreait Admin SDK (if Service Account JSON is provided) — full-featured.
 * 2. Manual JWT verification using Google's public keys (if only Project ID
 *    is provided) — lightweight, no credentials needed.
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
	 * The verification mode: 'kreait' or 'manual'.
	 *
	 * @since  1.0.0
	 * @access private
	 * @var    string
	 */
	private $mode = 'none';

	/**
	 * The Firebase Project ID (needed for manual verification).
	 *
	 * @since  1.0.0
	 * @access private
	 * @var    string
	 */
	private $project_id = '';

	/**
	 * Google's public key endpoint for Firebase token verification.
	 */
	const GOOGLE_PUBLIC_KEYS_URL = 'https://www.googleapis.com/robot/v1/metadata/x509/securetoken@system.gserviceaccount.com';

	/**
	 * Initialize the class and set its properties.
	 *
	 * @since 1.0.0
	 */
	public function __construct() {
		$this->project_id = HWA_Settings::get_setting( 'firebase_project_id' );
		$this->initialize_firebase();
	}

	/**
	 * Initializes Firebase verification.
	 *
	 * Strategy:
	 * 1. If a Service Account is available, use the Kreait Admin SDK (full power).
	 * 2. If only a Project ID is available, use manual JWT verification with
	 *    Google's public keys (no credentials needed).
	 *
	 * @since  1.0.0
	 * @access private
	 */
	private function initialize_firebase() {
		// Strategy 1: Try Kreait Admin SDK with Service Account
		$service_account = $this->get_service_account_config();

		if ( ! empty( $service_account ) && class_exists( '\Kreait\Firebase\Factory' ) ) {
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
				$this->mode         = 'kreait';
				return;
			} catch ( \Throwable $e ) {
				error_log( '[HWA Firebase] Kreait SDK init failed: ' . $e->getMessage() . ' — Trying manual mode.' );
			}
		}

		// Strategy 2: Manual JWT verification (only needs Project ID)
		if ( ! empty( $this->project_id ) && class_exists( '\Firebase\JWT\JWT' ) ) {
			$this->is_available = true;
			$this->mode         = 'manual';
			return;
		}

		// Neither strategy available
		$this->is_available = false;
		$this->mode         = 'none';
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
		return $this->is_available;
	}

	/**
	 * Returns the current verification mode.
	 *
	 * @since  1.0.0
	 * @return string 'kreait', 'manual', or 'none'.
	 */
	public function get_mode() {
		return $this->mode;
	}

	/**
	 * Verifies a Firebase ID token.
	 *
	 * Dispatches to the appropriate strategy based on the current mode.
	 *
	 * @since  1.0.0
	 * @param  string $id_token The raw JWT string.
	 * @return array The verified claims.
	 * @throws \Exception If the token is invalid, expired, or verification fails.
	 */
	public function verify_id_token( $id_token ) {
		if ( ! $this->is_available() ) {
			throw new \Exception( 'Firebase Auth service is not available. Configure a Service Account JSON or ensure your Project ID is set.' );
		}

		if ( 'kreait' === $this->mode ) {
			return $this->verify_with_kreait( $id_token );
		}

		return $this->verify_manually( $id_token );
	}

	/**
	 * Verifies a token using the Kreait Admin SDK.
	 *
	 * @since  1.0.0
	 * @access private
	 * @param  string $id_token
	 * @return array  Verified claims.
	 * @throws \Exception
	 */
	private function verify_with_kreait( $id_token ) {
		try {
			$verified_token = $this->auth->verifyIdToken( $id_token );
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
	 * Verifies a Firebase ID token manually using Google's public keys.
	 *
	 * This method does NOT require a Service Account. It:
	 * 1. Fetches Google's public X.509 certificates from a well-known URL.
	 * 2. Uses firebase/php-jwt to decode and cryptographically verify the JWT signature.
	 * 3. Validates the issuer (`iss`), audience (`aud`), subject (`sub`), and expiry.
	 *
	 * @since  1.0.0
	 * @access private
	 * @param  string $id_token The raw JWT string.
	 * @return array  Verified claims as an associative array.
	 * @throws \Exception If verification fails for any reason.
	 */
	private function verify_manually( $id_token ) {
		// 1. Fetch Google's public keys (cached in a transient for performance).
		$keys = $this->get_google_public_keys();

		if ( empty( $keys ) ) {
			throw new \Exception( 'Unable to fetch Google public keys for token verification.' );
		}

		// 2. Decode and verify the JWT
		try {
			// Build an array of Key objects from the X.509 certificates
			$key_objects = array();
			foreach ( $keys as $kid => $cert ) {
				$key_objects[ $kid ] = new Key( $cert, 'RS256' );
			}

			$decoded = JWT::decode( $id_token, $key_objects );
			$claims  = (array) $decoded;

		} catch ( \Firebase\JWT\ExpiredException $e ) {
			throw new \Exception( 'Firebase ID token has expired.' );
		} catch ( \Firebase\JWT\SignatureInvalidException $e ) {
			throw new \Exception( 'Firebase ID token signature is invalid.' );
		} catch ( \Throwable $e ) {
			error_log( '[HWA Firebase] Manual JWT verification failed: ' . $e->getMessage() );
			throw new \Exception( 'Invalid Firebase ID token.' );
		}

		// 3. Validate issuer
		$expected_issuer = 'https://securetoken.google.com/' . $this->project_id;
		if ( empty( $claims['iss'] ) || $claims['iss'] !== $expected_issuer ) {
			throw new \Exception( 'Firebase ID token has an invalid issuer.' );
		}

		// 4. Validate audience
		if ( empty( $claims['aud'] ) || $claims['aud'] !== $this->project_id ) {
			throw new \Exception( 'Firebase ID token has an invalid audience.' );
		}

		// 5. Validate subject (Firebase UID)
		if ( empty( $claims['sub'] ) ) {
			throw new \Exception( 'Firebase ID token is missing the subject (UID) claim.' );
		}

		// 6. Validate auth_time is in the past
		if ( ! empty( $claims['auth_time'] ) && $claims['auth_time'] > time() + 300 ) {
			throw new \Exception( 'Firebase ID token auth_time is in the future.' );
		}

		return $claims;
	}

	/**
	 * Fetches Google's public keys for Firebase token verification.
	 *
	 * Keys are cached in a WordPress transient. The cache duration is derived
	 * from Google's Cache-Control header (typically ~6 hours).
	 *
	 * @since  1.0.0
	 * @access private
	 * @return array Associative array of kid => certificate.
	 */
	private function get_google_public_keys() {
		$transient_key = 'hwa_google_firebase_public_keys';
		$cached        = get_transient( $transient_key );

		if ( false !== $cached ) {
			return $cached;
		}

		$response = wp_remote_get( self::GOOGLE_PUBLIC_KEYS_URL, array(
			'timeout' => 10,
		) );

		if ( is_wp_error( $response ) ) {
			error_log( '[HWA Firebase] Failed to fetch Google public keys: ' . $response->get_error_message() );
			return array();
		}

		$body = wp_remote_retrieve_body( $response );
		$keys = json_decode( $body, true );

		if ( empty( $keys ) || ! is_array( $keys ) ) {
			error_log( '[HWA Firebase] Google public keys response was empty or invalid.' );
			return array();
		}

		// Cache based on Google's Cache-Control max-age header
		$cache_control = wp_remote_retrieve_header( $response, 'cache-control' );
		$max_age       = 3600; // Default: 1 hour
		if ( preg_match( '/max-age=(\d+)/', $cache_control, $matches ) ) {
			$max_age = (int) $matches[1];
		}

		set_transient( $transient_key, $keys, $max_age );

		return $keys;
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
