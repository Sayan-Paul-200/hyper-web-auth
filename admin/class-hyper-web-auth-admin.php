<?php

/**
 * The admin-specific functionality of the plugin.
 *
 * @link       https://github.com/Sayan-Paul-200
 * @since      1.0.0
 *
 * @package    Hyper_Web_Auth
 * @subpackage Hyper_Web_Auth/admin
 */

/**
 * The admin-specific functionality of the plugin.
 *
 * Defines the plugin name, version, and two examples hooks for how to
 * enqueue the admin-specific stylesheet and JavaScript.
 *
 * @package    Hyper_Web_Auth
 * @subpackage Hyper_Web_Auth/admin
 * @author     Sayan Paul <sayanpaul666.ap@gmail.com>
 */
class Hyper_Web_Auth_Admin {

	/**
	 * The ID of this plugin.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      string    $plugin_name    The ID of this plugin.
	 */
	private $plugin_name;

	/**
	 * The version of this plugin.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      string    $version    The current version of this plugin.
	 */
	private $version;

	/**
	 * Initialize the class and set its properties.
	 *
	 * @since    1.0.0
	 * @param      string    $plugin_name       The name of this plugin.
	 * @param      string    $version    The version of this plugin.
	 */
	public function __construct( $plugin_name, $version ) {

		$this->plugin_name = $plugin_name;
		$this->version = $version;

	}

	/**
	 * Register the stylesheets for the admin area.
	 *
	 * @since    1.0.0
	 */
	public function enqueue_styles() {

		/**
		 * This function is provided for demonstration purposes only.
		 *
		 * An instance of this class should be passed to the run() function
		 * defined in Hyper_Web_Auth_Loader as all of the hooks are defined
		 * in that particular class.
		 *
		 * The Hyper_Web_Auth_Loader will then create the relationship
		 * between the defined hooks and the functions defined in this
		 * class.
		 */

		wp_enqueue_style( $this->plugin_name, plugin_dir_url( __FILE__ ) . 'css/hyper-web-auth-admin.css', array(), $this->version, 'all' );

	}

	/**
	 * Register the JavaScript for the admin area.
	 *
	 * @since    1.0.0
	 */
	public function enqueue_scripts() {

		/**
		 * This function is provided for demonstration purposes only.
		 *
		 * An instance of this class should be passed to the run() function
		 * defined in Hyper_Web_Auth_Loader as all of the hooks are defined
		 * in that particular class.
		 *
		 * The Hyper_Web_Auth_Loader will then create the relationship
		 * between the defined hooks and the functions defined in this
		 * class.
		 */

		wp_enqueue_script( $this->plugin_name, plugin_dir_url( __FILE__ ) . 'js/hyper-web-auth-admin.js', array( 'jquery' ), $this->version, false );

	}

	/**
	 * Register the admin menu for Auth Logs.
	 *
	 * @since 1.0.0
	 */
	public function add_plugin_admin_menu() {
		add_users_page(
			__( 'Authentication Logs', 'hyper-web-auth' ),
			__( 'Auth Logs', 'hyper-web-auth' ),
			'edit_users',
			'hwa-auth-logs',
			array( $this, 'display_auth_logs_page' )
		);
	}

	/**
	 * Render the Auth Logs page content.
	 *
	 * @since 1.0.0
	 */
	public function display_auth_logs_page() {
		require_once plugin_dir_path( __FILE__ ) . 'class-hwa-auth-logs-list-table.php';
		
		$list_table = new HWA_Auth_Logs_List_Table();
		$list_table->prepare_items();

		?>
		<div class="wrap">
			<h1 class="wp-heading-inline"><?php esc_html_e( 'Authentication Logs', 'hyper-web-auth' ); ?></h1>
			<hr class="wp-header-end">
			
			<form id="hwa-auth-logs-filter" method="get">
				<input type="hidden" name="page" value="<?php echo esc_attr( $_REQUEST['page'] ); ?>" />
				<?php $list_table->display(); ?>
			</form>
		</div>
		<?php
	}

	/**
	 * Display admin notices (success/error) for unlinking.
	 *
	 * @since 1.0.0
	 */
	public function display_admin_notices() {
		if ( isset( $_GET['hwa_unlink_error'] ) && 'lockout' === $_GET['hwa_unlink_error'] ) {
			$message = __( 'Lockout Prevention: You cannot unlink this identity because it is the only login method, and the user does not have a real email address set for password recovery. Please update their email first.', 'hyper-web-auth' );
			printf( '<div class="notice notice-error is-dismissible"><p>%s</p></div>', esc_html( $message ) );
		}

		if ( isset( $_GET['hwa_unlink_error'] ) && 'failed' === $_GET['hwa_unlink_error'] ) {
			$message = __( 'Failed to unlink identity due to a database error.', 'hyper-web-auth' );
			printf( '<div class="notice notice-error is-dismissible"><p>%s</p></div>', esc_html( $message ) );
		}

		if ( isset( $_GET['hwa_unlink_success'] ) && '1' === $_GET['hwa_unlink_success'] ) {
			$message = __( 'Identity successfully unlinked.', 'hyper-web-auth' );
			printf( '<div class="notice notice-success is-dismissible"><p>%s</p></div>', esc_html( $message ) );
		}
	}

	/**
	 * Render the Linked Identities table on the user profile page.
	 *
	 * @since 1.0.0
	 * @param WP_User $user The WP_User object.
	 */
	public function render_user_profile_identities( $user ) {
		if ( ! current_user_can( 'edit_user', $user->ID ) ) {
			return;
		}

		$repo = new HWA_Identity_Repository();
		$identities = $repo->get_all_identities_for_user( $user->ID );

		?>
		<h2><?php esc_html_e( 'Hyper Web Auth - Linked Identities', 'hyper-web-auth' ); ?></h2>
		<table class="form-table" role="presentation">
			<tbody>
				<tr>
					<th><label><?php esc_html_e( 'Linked Accounts', 'hyper-web-auth' ); ?></label></th>
					<td>
						<?php if ( empty( $identities ) ) : ?>
							<p><?php esc_html_e( 'No identities are linked to this user.', 'hyper-web-auth' ); ?></p>
						<?php else : ?>
							<table class="widefat striped" style="max-width: 600px;">
								<thead>
									<tr>
										<th><?php esc_html_e( 'Provider', 'hyper-web-auth' ); ?></th>
										<th><?php esc_html_e( 'Identity', 'hyper-web-auth' ); ?></th>
										<th><?php esc_html_e( 'Linked At', 'hyper-web-auth' ); ?></th>
										<th><?php esc_html_e( 'Actions', 'hyper-web-auth' ); ?></th>
									</tr>
								</thead>
								<tbody>
									<?php foreach ( $identities as $identity ) : ?>
										<tr>
											<td><?php echo esc_html( ucfirst( str_replace( '_', ' ', $identity->provider ) ) ); ?></td>
											<td>
												<?php
												// Mask for privacy even in admin, unless they need to see it?
												// Let's mask it for security best practices.
												if ( 'firebase_phone' === $identity->provider ) {
													echo esc_html( HWA_Security::mask_phone( $identity->identity_display ) );
												} else {
													echo esc_html( HWA_Security::mask_email( $identity->identity_display ) );
												}
												?>
											</td>
											<td><?php echo esc_html( get_date_from_gmt( $identity->linked_at, get_option( 'date_format' ) . ' ' . get_option( 'time_format' ) ) ); ?></td>
											<td>
												<?php
												$unlink_url = add_query_arg(
													array(
														'action'      => 'hwa_admin_unlink_identity',
														'identity_id' => $identity->id,
														'user_id'     => $user->ID,
													),
													admin_url( 'admin-post.php' )
												);
												$unlink_url = wp_nonce_url( $unlink_url, 'hwa_admin_unlink_' . $identity->id );
												?>
												<a href="<?php echo esc_url( $unlink_url ); ?>" class="button button-small" onclick="return confirm('<?php esc_attr_e( 'Are you sure you want to unlink this identity?', 'hyper-web-auth' ); ?>');" style="color: #dc3232; border-color: #dc3232;">
													<?php esc_html_e( 'Unlink', 'hyper-web-auth' ); ?>
												</a>
											</td>
										</tr>
									<?php endforeach; ?>
								</tbody>
							</table>
						<?php endif; ?>
					</td>
				</tr>
			</tbody>
		</table>
		<?php
	}

	/**
	 * Handle the secure POST request to unlink an identity from the admin.
	 *
	 * @since 1.0.0
	 */
	public function handle_admin_unlink_identity() {
		if ( ! current_user_can( 'edit_users' ) ) {
			wp_die( esc_html__( 'Unauthorized.', 'hyper-web-auth' ) );
		}

		$identity_id = isset( $_REQUEST['identity_id'] ) ? absint( $_REQUEST['identity_id'] ) : 0;
		$user_id     = isset( $_REQUEST['user_id'] ) ? absint( $_REQUEST['user_id'] ) : 0;

		check_admin_referer( 'hwa_admin_unlink_' . $identity_id );

		if ( ! current_user_can( 'edit_user', $user_id ) ) {
			wp_die( esc_html__( 'Unauthorized to edit this user.', 'hyper-web-auth' ) );
		}

		$redirect_url = get_edit_user_link( $user_id );

		$repo = new HWA_Identity_Repository();
		$identities = $repo->get_all_identities_for_user( $user_id );
		
		$target_identity = null;
		foreach ( $identities as $identity ) {
			if ( (int) $identity->id === $identity_id ) {
				$target_identity = $identity;
				break;
			}
		}

		if ( ! $target_identity ) {
			wp_redirect( add_query_arg( 'hwa_unlink_error', 'failed', $redirect_url ) );
			exit;
		}

		// --- Lockout Prevention Safety Check ---
		if ( count( $identities ) <= 1 ) {
			$user = get_userdata( $user_id );
			$email = $user->user_email;
			
			// Does the email start with our auto-generated placeholder format?
			if ( strpos( $email, 'phone.' ) === 0 && strpos( $email, '@' ) !== false ) {
				$ip = HWA_Security::get_client_ip();
				HWA_Database::log_auth_event( $user_id, $target_identity->provider, 'admin_unlink_failed', 'failed', $ip, 'Admin unlink blocked by lockout prevention.' );
				
				wp_redirect( add_query_arg( 'hwa_unlink_error', 'lockout', $redirect_url ) );
				exit;
			}
		}

		// Proceed with unlinking.
		$deleted = $repo->delete_identity( $identity_id );

		if ( $deleted ) {
			$ip = HWA_Security::get_client_ip();
			HWA_Database::log_auth_event( $user_id, $target_identity->provider, 'admin_unlink_success', 'success', $ip, 'Admin successfully unlinked identity.' );
			wp_redirect( add_query_arg( 'hwa_unlink_success', '1', $redirect_url ) );
		} else {
			wp_redirect( add_query_arg( 'hwa_unlink_error', 'failed', $redirect_url ) );
		}
		exit;
	}

}
