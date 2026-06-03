<?php

/**
 * Rate Limiter for Firebase Authentication endpoints.
 *
 * @link       https://github.com/Sayan-Paul-200
 * @since      1.0.0
 *
 * @package    Hyper_Web_Auth
 * @subpackage Hyper_Web_Auth/includes
 */

/**
 * The Rate Limiter class.
 *
 * Protects preflight and verification endpoints from abuse by leveraging
 * WordPress transients.
 *
 * @package    Hyper_Web_Auth
 * @subpackage Hyper_Web_Auth/includes
 * @author     Sayan Paul <sayanpaul666.ap@gmail.com>
 */
class HWA_Rate_Limiter {

	/**
	 * Cooldown period before a specific phone number can request another SMS.
	 */
	const PHONE_COOLDOWN_SECONDS = 60;

	/**
	 * Maximum number of SMS requests allowed per IP address per hour.
	 */
	const IP_PREFLIGHT_LIMIT = 10;
	const IP_PREFLIGHT_WINDOW = HOUR_IN_SECONDS;

	/**
	 * Maximum number of failed verification attempts allowed per IP per 15 minutes.
	 */
	const IP_VERIFY_LIMIT = 5;
	const IP_VERIFY_WINDOW = 15 * MINUTE_IN_SECONDS;

	/**
	 * Initialize the rate limiter.
	 *
	 * @since 1.0.0
	 */
	public function __construct() {
		// Initialization if necessary.
	}

	/**
	 * Checks if the preflight request is allowed.
	 *
	 * Limits by both phone number cooldown and IP volume.
	 *
	 * @since  1.0.0
	 * @param  string $phone_e164 The normalized phone number.
	 * @param  string $ip         The client IP address.
	 * @return true|WP_Error      True if allowed, WP_Error if rate limited.
	 */
	public function check_firebase_preflight_limit( $phone_e164, $ip ) {
		$phone_hash = HWA_Security::hash_phone( $phone_e164 );
		$ip_hash    = HWA_Security::hash_ip( $ip );

		// 1. Check phone cooldown
		$phone_transient = 'hwa_pf_ph_' . substr( $phone_hash, 0, 32 );
		if ( get_transient( $phone_transient ) ) {
			return new WP_Error(
				'rate_limit_exceeded',
				__( 'Please wait before requesting another SMS code.', 'hyper-web-auth' ),
				array( 'status' => 429 )
			);
		}

		// 2. Check IP preflight limit
		$ip_transient = 'hwa_pf_ip_' . substr( $ip_hash, 0, 32 );
		$ip_count     = (int) get_transient( $ip_transient );
		if ( $ip_count >= self::IP_PREFLIGHT_LIMIT ) {
			return new WP_Error(
				'rate_limit_exceeded',
				__( 'Too many SMS requests from your IP address. Please try again later.', 'hyper-web-auth' ),
				array( 'status' => 429 )
			);
		}

		return true;
	}

	/**
	 * Records a successful preflight attempt, applying limits.
	 *
	 * @since  1.0.0
	 * @param  string $phone_e164 The normalized phone number.
	 * @param  string $ip         The client IP address.
	 */
	public function record_firebase_preflight_attempt( $phone_e164, $ip ) {
		$phone_hash = HWA_Security::hash_phone( $phone_e164 );
		$ip_hash    = HWA_Security::hash_ip( $ip );

		// 1. Set phone cooldown
		$phone_transient = 'hwa_pf_ph_' . substr( $phone_hash, 0, 32 );
		set_transient( $phone_transient, time(), self::PHONE_COOLDOWN_SECONDS );

		// 2. Increment IP count
		$ip_transient = 'hwa_pf_ip_' . substr( $ip_hash, 0, 32 );
		$ip_count     = (int) get_transient( $ip_transient );
		
		if ( $ip_count === 0 ) {
			// First attempt in window, set transient with full window expiry
			set_transient( $ip_transient, 1, self::IP_PREFLIGHT_WINDOW );
		} else {
			// Increment count. Note: this resets the expiry to IP_PREFLIGHT_WINDOW
			// which means it acts as a sliding window. This is acceptable for security.
			set_transient( $ip_transient, $ip_count + 1, self::IP_PREFLIGHT_WINDOW );
		}
	}

	/**
	 * Checks if the IP is blocked due to too many failed verifications.
	 *
	 * @since  1.0.0
	 * @param  string $ip The client IP address.
	 * @return true|WP_Error True if allowed, WP_Error if blocked.
	 */
	public function check_firebase_verification_limit( $ip ) {
		$ip_hash      = HWA_Security::hash_ip( $ip );
		$ip_transient = 'hwa_vf_ip_' . substr( $ip_hash, 0, 32 );
		$ip_count     = (int) get_transient( $ip_transient );

		if ( $ip_count >= self::IP_VERIFY_LIMIT ) {
			return new WP_Error(
				'rate_limit_exceeded',
				__( 'Too many failed verification attempts. Your IP has been temporarily blocked.', 'hyper-web-auth' ),
				array( 'status' => 429 )
			);
		}

		return true;
	}

	/**
	 * Records a failed verification attempt.
	 *
	 * @since  1.0.0
	 * @param  string $ip The client IP address.
	 */
	public function record_firebase_verification_failure( $ip ) {
		$ip_hash      = HWA_Security::hash_ip( $ip );
		$ip_transient = 'hwa_vf_ip_' . substr( $ip_hash, 0, 32 );
		$ip_count     = (int) get_transient( $ip_transient );

		if ( $ip_count === 0 ) {
			set_transient( $ip_transient, 1, self::IP_VERIFY_WINDOW );
		} else {
			set_transient( $ip_transient, $ip_count + 1, self::IP_VERIFY_WINDOW );
		}
	}

	/**
	 * Clears the failed verification count upon successful login.
	 *
	 * @since  1.0.0
	 * @param  string $ip The client IP address.
	 */
	public function clear_firebase_verification_failures( $ip ) {
		$ip_hash      = HWA_Security::hash_ip( $ip );
		$ip_transient = 'hwa_vf_ip_' . substr( $ip_hash, 0, 32 );
		delete_transient( $ip_transient );
	}

}
