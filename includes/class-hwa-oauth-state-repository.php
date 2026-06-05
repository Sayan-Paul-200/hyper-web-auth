<?php

/**
 * Database repository for OAuth states.
 *
 * @link       https://github.com/Sayan-Paul-200
 * @since      1.0.0
 *
 * @package    Hyper_Web_Auth
 * @subpackage Hyper_Web_Auth/includes
 */

/**
 * OAuth State Repository Class.
 *
 * Provides an abstraction layer over the hwa_oauth_states database table.
 * Manages short-lived, single-use state tokens to prevent CSRF and replay
 * attacks during OAuth flows.
 *
 * @package    Hyper_Web_Auth
 * @subpackage Hyper_Web_Auth/includes
 * @author     Sayan Paul <sayanpaul666.ap@gmail.com>
 */
class HWA_OAuth_State_Repository {

	/**
	 * Creates a new OAuth state and stores its cryptographic hash in the database.
	 *
	 * @since  1.0.0
	 * @param  string $provider  The provider slug (e.g., 'google').
	 * @param  string $context   The flow context (e.g., 'login', 'register', 'link_google').
	 * @param  string $return_to The URL to redirect to after successful authentication.
	 * @param  int|null $user_id The WooCommerce user ID (if initiating a linking flow while logged in).
	 * @return string The raw, unhashed state string to be passed to the OAuth provider in the URL.
	 */
	public function create_state( $provider, $context, $return_to = '', $user_id = null ) {
		global $wpdb;

		// Generate a secure random 64-character hex string.
		$raw_state = HWA_Security::generate_state_hash();
		
		// Hash it again before storing in the database.
		// If the database is compromised, active states cannot be forged.
		$state_hash = hash( 'sha256', $raw_state );

		$table_name = $wpdb->prefix . 'hwa_oauth_states';
		
		$now        = current_time( 'timestamp' );
		$created_at = gmdate( 'Y-m-d H:i:s', $now );
		// State expires strictly in 10 minutes.
		$expires_at = gmdate( 'Y-m-d H:i:s', $now + ( 10 * 60 ) );

		// Resolve magic keywords (e.g. 'checkout') to full URLs before sanitization.
		// esc_url_raw() will strip bare keywords since they lack a protocol scheme.
		if ( 'checkout' === $return_to && function_exists( 'wc_get_page_permalink' ) ) {
			$return_to = wc_get_page_permalink( 'checkout' );
		}

		$data = array(
			'state_hash' => $state_hash,
			'provider'   => sanitize_key( $provider ),
			'context'    => sanitize_key( $context ),
			'return_to'  => esc_url_raw( $return_to ),
			'user_id'    => $user_id ? (int) $user_id : null,
			'status'     => 'pending',
			'created_at' => $created_at,
			'expires_at' => $expires_at,
		);

		$format = array( '%s', '%s', '%s', '%s', '%d', '%s', '%s', '%s' );

		$wpdb->insert( $table_name, $data, $format );

		return $raw_state;
	}

	/**
	 * Verifies and consumes a state token returning from an OAuth provider.
	 *
	 * @since  1.0.0
	 * @param  string $raw_state The raw state string returned in the callback URL.
	 * @return array|null An array of state data if valid, or null if missing/expired/consumed.
	 */
	public function consume_state( $raw_state ) {
		global $wpdb;

		if ( empty( $raw_state ) ) {
			return null;
		}

		$state_hash = hash( 'sha256', $raw_state );
		$table_name = $wpdb->prefix . 'hwa_oauth_states';
		$now_sql    = gmdate( 'Y-m-d H:i:s', current_time( 'timestamp' ) );

		// Find the state where it hasn't expired and hasn't been consumed.
		$query = $wpdb->prepare(
			"SELECT * FROM $table_name 
			WHERE state_hash = %s 
			AND consumed_at IS NULL 
			AND expires_at > %s 
			LIMIT 1",
			$state_hash,
			$now_sql
		);

		$row = $wpdb->get_row( $query, ARRAY_A );

		if ( empty( $row ) ) {
			return null;
		}

		// Mark it as consumed immediately to prevent replay attacks.
		$wpdb->update(
			$table_name,
			array( 
				'consumed_at' => $now_sql,
				'status'      => 'consumed',
			),
			array( 'id' => $row['id'] ),
			array( '%s', '%s' ),
			array( '%d' )
		);

		return $row;
	}

	/**
	 * Cleans up expired states from the database.
	 * Should be hooked into a WordPress cron job (e.g., daily).
	 *
	 * @since  1.0.0
	 * @return int The number of rows deleted.
	 */
	public function cleanup_expired_states() {
		global $wpdb;

		$table_name = $wpdb->prefix . 'hwa_oauth_states';
		$now_sql    = gmdate( 'Y-m-d H:i:s', current_time( 'timestamp' ) );

		$query = $wpdb->prepare(
			"DELETE FROM $table_name WHERE expires_at < %s",
			$now_sql
		);

		$deleted = $wpdb->query( $query );

		return $deleted !== false ? (int) $deleted : 0;
	}

}
