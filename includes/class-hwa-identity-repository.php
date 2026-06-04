<?php

/**
 * Database repository for customer identities.
 *
 * @link       https://github.com/Sayan-Paul-200
 * @since      1.0.0
 *
 * @package    Hyper_Web_Auth
 * @subpackage Hyper_Web_Auth/includes
 */

/**
 * Identity Repository Class.
 *
 * Provides an abstraction layer over the hwa_identities database table,
 * handling all CRUD operations securely with prepared SQL statements.
 *
 * @package    Hyper_Web_Auth
 * @subpackage Hyper_Web_Auth/includes
 * @author     Sayan Paul <sayanpaul666.ap@gmail.com>
 */
class HWA_Identity_Repository {

	/**
	 * Finds an identity record by provider and raw identity string.
	 *
	 * @since  1.0.0
	 * @param  string $provider     The provider slug (e.g., 'google').
	 * @param  string $raw_identity The raw identity string (e.g., Google sub).
	 * @return object|null The database row object, or null if not found.
	 */
	public function find_by_provider_identity( $provider, $raw_identity ) {
		global $wpdb;

		$identity_hash = HWA_Security::hash_identity( $raw_identity );

		$table_name = $wpdb->prefix . 'hwa_identities';

		$query = $wpdb->prepare(
			"SELECT * FROM $table_name WHERE provider = %s AND identity_hash = %s LIMIT 1",
			$provider,
			$identity_hash
		);

		return $wpdb->get_row( $query );
	}

	/**
	 * Convenience wrapper to find a Google identity by its sub ID.
	 *
	 * @since  1.0.0
	 * @param  string $google_sub The raw Google sub ID.
	 * @return object|null
	 */
	public function find_google_identity( $google_sub ) {
		return $this->find_by_provider_identity( 'google', $google_sub );
	}

	/**
	 * Creates a new Google identity record in the database.
	 *
	 * @since  1.0.0
	 * @param  int    $user_id    The WooCommerce user ID.
	 * @param  string $google_sub The raw Google sub ID.
	 * @param  string $email      The verified email address from Google.
	 * @param  bool   $verified   Whether the email is verified (usually true for Google).
	 * @return int|false The inserted row ID, or false on failure.
	 */
	public function create_google_identity( $user_id, $google_sub, $email, $verified ) {
		global $wpdb;

		$table_name    = $wpdb->prefix . 'hwa_identities';
		$identity_hash = HWA_Security::hash_identity( $google_sub );
		$now           = current_time( 'mysql' );

		$data = array(
			'user_id'         => $user_id,
			'provider'        => 'google',
			'identity_hash'   => $identity_hash,
			'identity_display'=> sanitize_email( $email ), // Good for admin context
			'provider_uid'    => '', // Stored as hash, leaving this empty or generic if preferred. But plan says "provider_uid VARCHAR(191) NULL", we should store the hash only to be safe, or leave empty to enforce privacy. Let's leave empty as the hash is what we search on.
			'email'           => sanitize_email( $email ),
			'is_verified'     => $verified ? 1 : 0,
			'status'          => 'active',
			'linked_at'       => $now,
		);

		$format = array( '%d', '%s', '%s', '%s', '%s', '%s', '%d', '%s', '%s' );

		$result = $wpdb->insert( $table_name, $data, $format );

		if ( false === $result ) {
			return false;
		}

		return $wpdb->insert_id;
	}

	/**
	 * Updates the last login timestamp for an identity record.
	 *
	 * @since  1.0.0
	 * @param  int $identity_id The primary key ID of the identity row.
	 * @return bool True on success, false on failure.
	 */
	public function update_last_login( $identity_id ) {
		global $wpdb;

		$table_name = $wpdb->prefix . 'hwa_identities';
		$now        = current_time( 'mysql' );

		$result = $wpdb->update(
			$table_name,
			array( 'last_login_at' => $now ),
			array( 'id' => $identity_id ),
			array( '%s' ),
			array( '%d' )
		);

		return false !== $result;
	}

	/**
	 * Checks if a specific WooCommerce user already has an identity linked from a specific provider.
	 *
	 * @since  1.0.0
	 * @param  int    $user_id  The WooCommerce user ID.
	 * @param  string $provider The provider slug (e.g., 'google').
	 * @return bool True if linked, false otherwise.
	 */
	public function identity_exists_for_user( $user_id, $provider ) {
		global $wpdb;

		$table_name = $wpdb->prefix . 'hwa_identities';

		$query = $wpdb->prepare(
			"SELECT id FROM $table_name WHERE user_id = %d AND provider = %s LIMIT 1",
			$user_id,
			$provider
		);

		$result = $wpdb->get_var( $query );

		return ! empty( $result );
	}

	/**
	 * Finds the Google identity record for a specific WooCommerce user.
	 *
	 * @since  1.0.0
	 * @param  int $user_id The WooCommerce user ID.
	 * @return object|null
	 */
	public function find_user_google_identity( $user_id ) {
		global $wpdb;

		$table_name = $wpdb->prefix . 'hwa_identities';

		$query = $wpdb->prepare(
			"SELECT * FROM $table_name WHERE user_id = %d AND provider = 'google' LIMIT 1",
			$user_id
		);

		return $wpdb->get_row( $query );
	}

	/**
	 * Finds a Firebase phone identity by phone number.
	 *
	 * @since  1.0.0
	 * @param  string $phone_e164 Normalized E.164 phone number.
	 * @return object|null
	 */
	public function find_firebase_phone_by_phone( $phone_e164 ) {
		global $wpdb;

		$table_name = $wpdb->prefix . 'hwa_identities';
		$phone_hash = HWA_Security::hash_phone( $phone_e164 );

		$query = $wpdb->prepare(
			"SELECT * FROM $table_name WHERE provider = 'firebase_phone' AND phone_hash = %s LIMIT 1",
			$phone_hash
		);

		return $wpdb->get_row( $query );
	}

	/**
	 * Finds a Firebase phone identity by Firebase UID.
	 *
	 * @since  1.0.0
	 * @param  string $firebase_uid Firebase UID.
	 * @return object|null
	 */
	public function find_firebase_phone_by_uid( $firebase_uid ) {
		global $wpdb;

		$table_name    = $wpdb->prefix . 'hwa_identities';
		$identity_hash = HWA_Security::hash_identity( $firebase_uid );

		$query = $wpdb->prepare(
			"SELECT * FROM $table_name WHERE provider = 'firebase_phone' AND identity_hash = %s LIMIT 1",
			$identity_hash
		);

		return $wpdb->get_row( $query );
	}

	/**
	 * Creates a new Firebase Phone identity linked to a user.
	 *
	 * @since  1.0.0
	 * @param  int    $user_id      The WooCommerce user ID.
	 * @param  string $firebase_uid The Firebase UID.
	 * @param  string $phone_e164   The normalized phone number.
	 * @param  bool   $verified     Whether the phone is verified (usually true).
	 * @return int|false The new identity ID, or false on failure.
	 */
	public function create_firebase_phone_identity( $user_id, $firebase_uid, $phone_e164, $verified = true ) {
		global $wpdb;

		$table_name = $wpdb->prefix . 'hwa_identities';

		$data = array(
			'user_id'          => $user_id,
			'provider'         => 'firebase_phone',
			'identity_hash'    => HWA_Security::hash_identity( $firebase_uid ),
			'identity_display' => $phone_e164,
			'provider_uid'     => $firebase_uid,
			'phone_e164'       => $phone_e164,
			'phone_hash'       => HWA_Security::hash_phone( $phone_e164 ),
			'is_verified'      => $verified ? 1 : 0,
			'status'           => 'active',
			'linked_at'        => current_time( 'mysql' ),
			'last_login_at'    => current_time( 'mysql' ),
		);

		$format = array(
			'%d', // user_id
			'%s', // provider
			'%s', // identity_hash
			'%s', // identity_display
			'%s', // provider_uid
			'%s', // phone_e164
			'%s', // phone_hash
			'%d', // is_verified
			'%s', // status
			'%s', // linked_at
			'%s', // last_login_at
		);

		$result = $wpdb->insert( $table_name, $data, $format );

		if ( false === $result ) {
			return false;
		}

		return $wpdb->insert_id;
	}

	/**
	 * Finds the Firebase phone identity linked to a specific user.
	 *
	 * @since  1.0.0
	 * @param  int $user_id The WooCommerce user ID.
	 * @return object|null
	 */
	public function find_user_firebase_phone_identity( $user_id ) {
		global $wpdb;

		$table_name = $wpdb->prefix . 'hwa_identities';

		$query = $wpdb->prepare(
			"SELECT * FROM $table_name WHERE user_id = %d AND provider = 'firebase_phone' LIMIT 1",
			$user_id
		);

		return $wpdb->get_row( $query );
	}

	/**
	 * Updates the last login timestamp for a Firebase phone identity.
	 *
	 * @since  1.0.0
	 * @param  int $identity_id The ID of the identity row.
	 * @return bool True on success, false on failure.
	 */
	public function update_firebase_phone_last_login( $identity_id ) {
		global $wpdb;

		$table_name = $wpdb->prefix . 'hwa_identities';

		$result = $wpdb->update(
			$table_name,
			array( 'last_login_at' => current_time( 'mysql' ) ),
			array( 'id' => $identity_id ),
			array( '%s' ),
			array( '%d' )
		);

		return false !== $result;
	}

	/**
	 * Deletes an identity record by ID.
	 * Useful for cleaning up orphaned records when a WP user is deleted.
	 *
	 * @since  1.0.0
	 * @param  int $identity_id The primary key ID of the identity row.
	 * @return bool True on success, false on failure.
	 */
	public function delete_identity( $identity_id ) {
		global $wpdb;

		$table_name = $wpdb->prefix . 'hwa_identities';

		$result = $wpdb->delete(
			$table_name,
			array( 'id' => $identity_id ),
			array( '%d' )
		);

		return false !== $result;
	}

	/**
	 * Retrieves all linked identities for a specific WordPress user ID.
	 *
	 * @since  1.0.0
	 * @param  int $user_id The WooCommerce user ID.
	 * @return array Array of identity objects.
	 */
	public function get_all_identities_for_user( $user_id ) {
		global $wpdb;

		$table_name = $wpdb->prefix . 'hwa_identities';

		$query = $wpdb->prepare(
			"SELECT * FROM $table_name WHERE user_id = %d ORDER BY linked_at ASC",
			$user_id
		);

		return $wpdb->get_results( $query );
	}

	/**
	 * Deletes all identity records associated with a specific WordPress user ID.
	 *
	 * @since  1.0.0
	 * @param  int $user_id The WooCommerce user ID.
	 * @return bool True on success, false on failure.
	 */
	public function delete_all_identities_for_user( $user_id ) {
		global $wpdb;

		$table_name = $wpdb->prefix . 'hwa_identities';

		$result = $wpdb->delete(
			$table_name,
			array( 'user_id' => $user_id ),
			array( '%d' )
		);

		return false !== $result;
	}

}
