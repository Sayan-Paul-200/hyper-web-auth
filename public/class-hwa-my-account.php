<?php

/**
 * Handles WooCommerce My Account integrations.
 *
 * @link       https://github.com/Sayan-Paul-200
 * @since      1.0.0
 *
 * @package    Hyper_Web_Auth
 * @subpackage Hyper_Web_Auth/public
 */

/**
 * The public-facing functionality of the plugin related to My Account.
 *
 * @package    Hyper_Web_Auth
 * @subpackage Hyper_Web_Auth/public
 * @author     Sayan Paul <sayanpaul666.ap@gmail.com>
 */
class HWA_My_Account {

	/**
	 * @var HWA_Identity_Repository
	 */
	private $identity_repo;

	/**
	 * @var HWA_Google_OAuth_Service
	 */
	private $google_service;

	/**
	 * Initialize the class and set its properties.
	 *
	 * @since 1.0.0
	 * @param HWA_Identity_Repository $identity_repo
	 * @param HWA_Google_OAuth_Service $google_service
	 */
	public function __construct( $identity_repo, $google_service ) {
		$this->identity_repo  = $identity_repo;
		$this->google_service = $google_service;
	}

	/**
	 * Registers the WooCommerce endpoint for login methods.
	 * Hooked to 'init'.
	 *
	 * @since 1.0.0
	 */
	public function add_endpoint() {
		add_rewrite_endpoint( 'linked-accounts', EP_ROOT | EP_PAGES );
	}

	/**
	 * Adds the "Login Methods" item to the WooCommerce My Account menu.
	 * Hooked to 'woocommerce_account_menu_items'.
	 *
	 * @since 1.0.0
	 * @param array $items
	 * @return array
	 */
	public function add_menu_item( $items ) {
		// Insert before the 'customer-logout' item if it exists.
		$new_items = array();
		
		foreach ( $items as $key => $value ) {
			if ( 'customer-logout' === $key ) {
				$new_items['linked-accounts'] = __( 'Login Methods', 'hyper-web-auth' );
			}
			$new_items[ $key ] = $value;
		}

		// Fallback in case 'customer-logout' isn't there
		if ( ! isset( $new_items['linked-accounts'] ) ) {
			$new_items['linked-accounts'] = __( 'Login Methods', 'hyper-web-auth' );
		}

		return $new_items;
	}

	/**
	 * Renders the content for the linked-accounts endpoint.
	 * Hooked to 'woocommerce_account_linked-accounts_endpoint'.
	 *
	 * @since 1.0.0
	 */
	public function render_page() {
		$user_id = get_current_user_id();

		if ( ! $user_id ) {
			return;
		}

		// Fetch linked identities
		$google_identity = $this->identity_repo->find_user_google_identity( $user_id );
		$phone_identity  = $this->identity_repo->find_user_firebase_phone_identity( $user_id );

		// Generate link URL for Google
		$google_link_url = $this->google_service->get_authorization_url( 'link_google', wc_get_account_endpoint_url( 'linked-accounts' ), $user_id );

		// Pass data to template
		$data = array(
			'google_identity' => $google_identity,
			'phone_identity'  => $phone_identity,
			'google_link_url' => $google_link_url,
		);

		extract( $data );
		require HYPER_WEB_AUTH_PATH . 'public/partials/account-login-methods.php';
	}

}
