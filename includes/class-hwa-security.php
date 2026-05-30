<?php

/**
 * Security and cryptographic utility functions.
 *
 * @link       https://github.com/Sayan-Paul-200
 * @since      1.0.0
 *
 * @package    Hyper_Web_Auth
 * @subpackage Hyper_Web_Auth/includes
 */

/**
 * Security Helper Class.
 *
 * Provides static methods for identity hashing, state generation,
 * and request security.
 *
 * @package    Hyper_Web_Auth
 * @subpackage Hyper_Web_Auth/includes
 * @author     Sayan Paul <sayanpaul666.ap@gmail.com>
 */
class HWA_Security {

	/**
	 * Hashes an identity string (like a Google sub ID or phone number)
	 * securely for database storage.
	 *
	 * We use HMAC-SHA256 with the WordPress auth salt to ensure
	 * the hash cannot be reversed or subjected to rainbow table attacks.
	 *
	 * @since  1.0.0
	 * @param  string $raw_string The raw identity string.
	 * @return string The 64-character hex hash.
	 */
	public static function hash_identity( $raw_string ) {
		if ( empty( $raw_string ) ) {
			return '';
		}

		$salt = wp_salt( 'auth' );
		return hash_hmac( 'sha256', (string) $raw_string, $salt );
	}

	/**
	 * Hashes a phone number for database storage and lookups.
	 *
	 * @since  1.0.0
	 * @param  string $phone_e164 The normalized E.164 phone number.
	 * @return string The hashed phone number.
	 */
	public static function hash_phone( $phone_e164 ) {
		return self::hash_identity( $phone_e164 );
	}

	/**
	 * Hashes an IP address for privacy-conscious audit logging.
	 *
	 * @since  1.0.0
	 * @param  string $ip The raw IP address.
	 * @return string The hashed IP address.
	 */
	public static function hash_ip( $ip ) {
		return self::hash_identity( $ip );
	}

	/**
	 * Generates a cryptographically secure random string for use as
	 * an OAuth state or nonce to prevent CSRF attacks.
	 *
	 * @since  1.0.0
	 * @return string A 64-character hex string.
	 */
	public static function generate_state_hash() {
		// Use random_bytes if available (PHP 7+), otherwise fallback to wp_generate_password.
		if ( function_exists( 'random_bytes' ) ) {
			try {
				$random_data = random_bytes( 32 );
			} catch ( Exception $e ) {
				$random_data = wp_generate_password( 64, true, true );
			}
		} else {
			$random_data = wp_generate_password( 64, true, true );
		}

		return hash( 'sha256', $random_data );
	}

	/**
	 * Verifies a Firebase ID token.
	 *
	 * Placeholder for Phase 2: Firebase Integration.
	 *
	 * @since  1.0.0
	 * @param  string $id_token The Firebase ID token JWT.
	 * @return array|WP_Error Array of token claims on success, WP_Error on failure.
	 */
	public static function verify_firebase_jwt( $id_token ) {
		// TODO: Implement with Kreait Firebase PHP SDK in Phase 2.
		return new WP_Error(
			'not_implemented',
			__( 'Firebase JWT verification is not yet implemented.', 'hyper-web-auth' ),
			array( 'status' => 501 )
		);
	}

	/**
	 * Safely retrieves the client's IP address.
	 *
	 * Checks common proxy headers (like Cloudflare) but falls back to
	 * REMOTE_ADDR. This is used primarily for the audit logger.
	 *
	 * @since  1.0.0
	 * @return string The IP address, or '0.0.0.0' if not found.
	 */
	public static function get_client_ip() {
		$ip = '0.0.0.0';

		$headers = array(
			'HTTP_CLIENT_IP',
			'HTTP_X_FORWARDED_FOR',
			'HTTP_X_FORWARDED',
			'HTTP_X_CLUSTER_CLIENT_IP',
			'HTTP_FORWARDED_FOR',
			'HTTP_FORWARDED',
			'REMOTE_ADDR',
		);

		foreach ( $headers as $header ) {
			if ( ! empty( $_SERVER[ $header ] ) ) {
				$ips = explode( ',', $_SERVER[ $header ] );
				$ip  = trim( $ips[0] ); // Always take the first IP in a proxy chain.
				
				// Validate it's a real IP.
				if ( filter_var( $ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE ) !== false ) {
					return $ip;
				}
			}
		}

		// Fallback to REMOTE_ADDR even if it's a private range (e.g. localhost testing).
		if ( ! empty( $_SERVER['REMOTE_ADDR'] ) ) {
			$ip = trim( $_SERVER['REMOTE_ADDR'] );
		}

		return $ip;
	}

	/**
	 * Normalizes a phone number to E.164 format.
	 * Strips spaces, dashes, and parentheses. Ensures leading +.
	 *
	 * @since  1.0.0
	 * @param  string $phone Raw phone string.
	 * @return string Normalized phone string.
	 */
	public static function normalize_phone( $phone ) {
		// Remove everything except plus sign and digits
		$normalized = preg_replace( '/[^+\d]/', '', $phone );
		
		// If it doesn't start with a +, and it's not empty, we assume it needs a default country code later,
		// but for pure normalization we just ensure we only have valid chars.
		return $normalized;
	}

	/**
	 * Validates if a phone number matches basic E.164 requirements
	 * (starts with +, followed by 1 to 15 digits).
	 *
	 * @since  1.0.0
	 * @param  string $phone Normalized phone string.
	 * @return bool True if valid E.164 format.
	 */
	public static function is_valid_phone( $phone ) {
		return preg_match( '/^\+[1-9]\d{1,14}$/', $phone ) === 1;
	}

	/**
	 * Safely validates a redirect URL.
	 * Ensures the redirect stays within the current site domain to prevent open redirects.
	 *
	 * @since  1.0.0
	 * @param  string $url The requested redirect URL.
	 * @param  string $fallback The fallback URL if invalid.
	 * @return string A safe URL.
	 */
	public static function safe_redirect_url( $url, $fallback = '' ) {
		if ( empty( $fallback ) ) {
			$fallback = home_url();
		}
		return wp_validate_redirect( $url, $fallback );
	}

}
