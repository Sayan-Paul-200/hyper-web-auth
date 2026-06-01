<?php

/**
 * Service for WooCommerce customer management.
 *
 * @link       https://github.com/Sayan-Paul-200
 * @since      1.0.0
 *
 * @package    Hyper_Web_Auth
 * @subpackage Hyper_Web_Auth/includes
 */

/**
 * Customer Service Class.
 *
 * Acts as the bridge between external identities and native WordPress/WooCommerce
 * users, handling customer creation, lookups, and authentication session management.
 *
 * @package    Hyper_Web_Auth
 * @subpackage Hyper_Web_Auth/includes
 * @author     Sayan Paul <sayanpaul666.ap@gmail.com>
 */
class HWA_Customer_Service {

	/**
	 * Finds a WordPress user by their email address.
	 *
	 * @since  1.0.0
	 * @param  string $email The email address to search for.
	 * @return WP_User|null The user object if found, null otherwise.
	 */
	public function find_customer_by_email( $email ) {
		$user = get_user_by( 'email', $email );
		return false !== $user ? $user : null;
	}

	/**
	 * Creates a new WooCommerce customer using data from a Google profile.
	 *
	 * @since  1.0.0
	 * @param  array $google_profile The validated profile array from Google OAuth.
	 * @return int|WP_Error The newly created user ID, or WP_Error on failure.
	 */
	public function create_customer_from_google_profile( $google_profile ) {
		$email = sanitize_email( $google_profile['email'] );
		
		if ( empty( $email ) ) {
			return new WP_Error( 'missing_email', __( 'Cannot create customer without an email address.', 'hyper-web-auth' ) );
		}

		// Generate a strong, highly secure random password.
		$password = wp_generate_password( 32, true, true );
		
		// Derive username from email prefix, WooCommerce handles uniqueness internally if needed.
		$username = sanitize_user( current( explode( '@', $email ) ), true );

		// Hook into WooCommerce's native customer creation to ensure roles and metadata are set properly.
		$user_id = wc_create_new_customer( $email, $username, $password );

		if ( is_wp_error( $user_id ) ) {
			return $user_id;
		}

		// Update user profile with name data if available.
		$user_data = array(
			'ID' => $user_id,
		);

		if ( ! empty( $google_profile['given_name'] ) ) {
			$user_data['first_name'] = sanitize_text_field( $google_profile['given_name'] );
		}

		if ( ! empty( $google_profile['family_name'] ) ) {
			$user_data['last_name'] = sanitize_text_field( $google_profile['family_name'] );
		}

		// Fallback to full name if given/family name are not separately available.
		if ( empty( $user_data['first_name'] ) && ! empty( $google_profile['name'] ) ) {
			$name_parts = explode( ' ', sanitize_text_field( $google_profile['name'] ) );
			$user_data['first_name'] = array_shift( $name_parts );
			if ( ! empty( $name_parts ) ) {
				$user_data['last_name'] = implode( ' ', $name_parts );
			}
		}

		wp_update_user( $user_data );

		// WooCommerce syncs billing first/last name with user meta automatically in some cases,
		// but let's be explicit for a complete profile.
		if ( isset( $user_data['first_name'] ) ) {
			update_user_meta( $user_id, 'billing_first_name', $user_data['first_name'] );
		}
		if ( isset( $user_data['last_name'] ) ) {
			update_user_meta( $user_id, 'billing_last_name', $user_data['last_name'] );
		}

		return $user_id;
	}

	/**
	 * Creates a new WooCommerce customer from a Firebase phone registration.
	 *
	 * @since  1.0.0
	 * @param  array $registration_data
	 * @return int|WP_Error
	 */
	public function create_customer_from_phone_registration( $registration_data ) {
		// TODO: Implement in Phase 2
		return 0;
	}

	/**
	 * Programmatically logs in a WordPress user and sets their authentication cookies.
	 *
	 * @since  1.0.0
	 * @param  int  $user_id  The ID of the user to log in.
	 * @param  bool $remember Whether to set a long-lasting persistent cookie.
	 * @return void
	 */
	public function login_customer( $user_id, $remember = true ) {
		$user = get_userdata( $user_id );
		if ( ! $user ) {
			return;
		}

		wp_clear_auth_cookie();
		wp_set_current_user( $user_id, $user->user_login );
		wp_set_auth_cookie( $user_id, $remember );

		// Fire the native wp_login action for compatibility with other plugins (e.g., security, analytics).
		do_action( 'wp_login', $user->user_login, $user );
	}

	/**
	 * Determines the default redirect URL based on the flow context.
	 *
	 * @since  1.0.0
	 * @param  string $context The flow context ('login', 'register', 'link_google').
	 * @return string The absolute URL to redirect to.
	 */
	public function get_default_redirect_url( $context ) {
		// By default, send them to the WooCommerce My Account dashboard.
		$redirect = wc_get_page_permalink( 'myaccount' );

		// We can expand this later (e.g., redirecting to checkout if they started the flow there).
		
		return apply_filters( 'hwa_default_redirect_url', $redirect, $context );
	}

}
