<?php
/**
 * Privacy and Data Export/Erasure functionality.
 *
 * @package Hyper_Web_Auth
 * @subpackage Hyper_Web_Auth/includes
 */

/**
 * Privacy Class.
 *
 * Integrates with WordPress core privacy tools to support GDPR/CCPA.
 */
class HWA_Privacy {

	/**
	 * Register hooks.
	 *
	 * @since 1.0.0
	 */
	public function init() {
		add_action( 'admin_init', array( $this, 'add_privacy_policy_content' ) );
		add_filter( 'wp_privacy_personal_data_exporters', array( $this, 'register_exporter' ) );
		add_filter( 'wp_privacy_personal_data_erasers', array( $this, 'register_eraser' ) );
	}

	/**
	 * Suggests content for the site's Privacy Policy page.
	 *
	 * @since 1.0.0
	 */
	public function add_privacy_policy_content() {
		if ( ! function_exists( 'wp_add_privacy_policy_content' ) ) {
			return;
		}

		$content = sprintf(
			'<p>%s</p>',
			__( 'When you log in or register using a third-party provider (such as Google or Phone Number), we securely store a cryptographic hash of your identity and your linked email address or phone number. If you use phone number authentication, your phone number may be shared securely with Firebase Authentication solely for the purpose of SMS rate-limiting and verification.', 'hyper-web-auth' )
		);

		wp_add_privacy_policy_content(
			__( 'Hyper Web Auth', 'hyper-web-auth' ),
			wp_kses_post( wpautop( $content, false ) )
		);
	}

	/**
	 * Registers the data exporter.
	 *
	 * @since 1.0.0
	 * @param array $exporters
	 * @return array
	 */
	public function register_exporter( $exporters ) {
		$exporters['hyper-web-auth'] = array(
			'exporter_friendly_name' => __( 'Hyper Web Auth', 'hyper-web-auth' ),
			'callback'               => array( $this, 'export_data' ),
		);
		return $exporters;
	}

	/**
	 * Registers the data eraser.
	 *
	 * @since 1.0.0
	 * @param array $erasers
	 * @return array
	 */
	public function register_eraser( $erasers ) {
		$erasers['hyper-web-auth'] = array(
			'eraser_friendly_name' => __( 'Hyper Web Auth', 'hyper-web-auth' ),
			'callback'             => array( $this, 'erase_data' ),
		);
		return $erasers;
	}

	/**
	 * Exports the customer's linked identities.
	 *
	 * @since 1.0.0
	 * @param string $email_address
	 * @param int    $page
	 * @return array
	 */
	public function export_data( $email_address, $page = 1 ) {
		$export_items = array();
		
		$user = get_user_by( 'email', $email_address );
		if ( ! $user ) {
			return array(
				'data' => $export_items,
				'done' => true,
			);
		}

		$repo = new HWA_Identity_Repository();
		$identities = $repo->get_all_identities_for_user( $user->ID );

		if ( ! empty( $identities ) ) {
			foreach ( $identities as $identity ) {
				$data = array(
					array(
						'name'  => __( 'Provider', 'hyper-web-auth' ),
						'value' => ucfirst( str_replace( '_', ' ', $identity->provider ) ),
					),
					array(
						'name'  => __( 'Identity', 'hyper-web-auth' ),
						'value' => $identity->identity_display, // Export raw to user
					),
					array(
						'name'  => __( 'Linked At', 'hyper-web-auth' ),
						'value' => $identity->linked_at,
					),
					array(
						'name'  => __( 'Last Login', 'hyper-web-auth' ),
						'value' => $identity->last_login_at ? $identity->last_login_at : __( 'Never', 'hyper-web-auth' ),
					),
				);

				$export_items[] = array(
					'group_id'    => 'hwa-identities',
					'group_label' => __( 'Linked Auth Identities', 'hyper-web-auth' ),
					'item_id'     => 'identity-' . $identity->id,
					'data'        => $data,
				);
			}
		}

		return array(
			'data' => $export_items,
			'done' => true,
		);
	}

	/**
	 * Erases the customer's linked identities and auth logs.
	 *
	 * @since 1.0.0
	 * @param string $email_address
	 * @param int    $page
	 * @return array
	 */
	public function erase_data( $email_address, $page = 1 ) {
		$user = get_user_by( 'email', $email_address );
		if ( ! $user ) {
			return array(
				'items_removed'  => false,
				'items_retained' => false,
				'messages'       => array(),
				'done'           => true,
			);
		}

		$repo = new HWA_Identity_Repository();
		$identities_deleted = $repo->delete_all_identities_for_user( $user->ID );
		$logs_deleted       = HWA_Database::delete_logs_for_user( $user->ID );

		return array(
			'items_removed'  => $identities_deleted || $logs_deleted,
			'items_retained' => false,
			'messages'       => array(),
			'done'           => true,
		);
	}

}
