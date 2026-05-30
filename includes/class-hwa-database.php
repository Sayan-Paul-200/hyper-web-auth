<?php

/**
 * Database installation and management functionality.
 *
 * @link       https://github.com/Sayan-Paul-200
 * @since      1.0.0
 *
 * @package    Hyper_Web_Auth
 * @subpackage Hyper_Web_Auth/includes
 */

/**
 * Database installer.
 *
 * Responsible for creating and updating the custom database tables
 * using WordPress's native dbDelta() function.
 *
 * @package    Hyper_Web_Auth
 * @subpackage Hyper_Web_Auth/includes
 * @author     Sayan Paul <sayanpaul666.ap@gmail.com>
 */
class HWA_Database {

	/**
	 * The current database version.
	 *
	 * @since    1.0.0
	 */
	const DB_VERSION = '1.0.0';

	/**
	 * Creates or updates the custom database tables.
	 *
	 * This method is called during plugin activation. It uses dbDelta()
	 * to safely create or upgrade tables without destroying existing data.
	 *
	 * @since    1.0.0
	 */
	public static function create_tables() {
		global $wpdb;

		$charset_collate = $wpdb->get_charset_collate();

		// Ensure dbDelta is available.
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		$sql = self::get_schema( $charset_collate );

		dbDelta( $sql );

		// Store the current database version.
		update_option( 'hwa_db_version', self::DB_VERSION );
	}

	/**
	 * Returns the database schema string for dbDelta().
	 *
	 * @since    1.0.0
	 * @param    string $charset_collate The database charset and collate string.
	 * @return   string The SQL statements.
	 */
	private static function get_schema( $charset_collate ) {
		global $wpdb;

		$sql = "
CREATE TABLE {$wpdb->prefix}hwa_identities (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  user_id BIGINT UNSIGNED NOT NULL,
  provider VARCHAR(30) NOT NULL,
  identity_hash CHAR(64) NOT NULL,
  identity_display VARCHAR(191) NULL,
  provider_uid VARCHAR(191) NULL,
  email VARCHAR(191) NULL,
  phone_e164 VARCHAR(30) NULL,
  phone_hash CHAR(64) NULL,
  is_verified TINYINT(1) NOT NULL DEFAULT 0,
  status VARCHAR(20) NOT NULL DEFAULT 'active',
  linked_at DATETIME NOT NULL,
  last_login_at DATETIME NULL,
  PRIMARY KEY  (id),
  UNIQUE KEY provider_identity (provider, identity_hash),
  UNIQUE KEY provider_phone (provider, phone_hash),
  KEY user_id (user_id),
  KEY provider_user (provider, user_id),
  KEY phone_hash (phone_hash)
) {$charset_collate};

CREATE TABLE {$wpdb->prefix}hwa_oauth_states (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  state_hash CHAR(64) NOT NULL,
  provider VARCHAR(30) NOT NULL,
  context VARCHAR(30) NOT NULL,
  return_to TEXT NULL,
  user_id BIGINT UNSIGNED NULL,
  nonce_hash CHAR(64) NULL,
  status VARCHAR(20) NOT NULL DEFAULT 'pending',
  expires_at DATETIME NOT NULL,
  created_at DATETIME NOT NULL,
  consumed_at DATETIME NULL,
  PRIMARY KEY  (id),
  UNIQUE KEY state_hash (state_hash),
  KEY expires_at (expires_at),
  KEY provider_context (provider, context)
) {$charset_collate};

CREATE TABLE {$wpdb->prefix}hwa_auth_logs (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  user_id BIGINT UNSIGNED NULL,
  event_type VARCHAR(50) NOT NULL,
  provider VARCHAR(30) NULL,
  status VARCHAR(20) NOT NULL,
  ip_hash CHAR(64) NULL,
  message TEXT NULL,
  context TEXT NULL,
  created_at DATETIME NOT NULL,
  PRIMARY KEY  (id),
  KEY user_id (user_id),
  KEY event_type (event_type),
  KEY created_at (created_at)
) {$charset_collate};
";

		return $sql;
	}
}
