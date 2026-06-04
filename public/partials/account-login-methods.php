<?php
/**
 * Provide a public-facing view for the My Account > Login Methods endpoint.
 *
 * This file is used to markup the public-facing aspects of the plugin.
 *
 * @link       https://github.com/Sayan-Paul-200
 * @since      1.0.0
 *
 * @package    Hyper_Web_Auth
 * @subpackage Hyper_Web_Auth/public/partials
 */

// Ensure variables are set
$google_identity = isset( $google_identity ) ? $google_identity : null;
$phone_identity  = isset( $phone_identity ) ? $phone_identity : null;
$google_link_url = isset( $google_link_url ) ? $google_link_url : '#';
?>

<div class="hwa-login-methods-container">
	
	<h3><?php esc_html_e( 'Linked Login Methods', 'hyper-web-auth' ); ?></h3>
	<p><?php esc_html_e( 'Manage the external accounts and phone numbers you can use to log in to your account.', 'hyper-web-auth' ); ?></p>

	<table class="woocommerce-table hwa-login-methods-table">
		<thead>
			<tr>
				<th><?php esc_html_e( 'Method', 'hyper-web-auth' ); ?></th>
				<th><?php esc_html_e( 'Status', 'hyper-web-auth' ); ?></th>
				<th><?php esc_html_e( 'Actions', 'hyper-web-auth' ); ?></th>
			</tr>
		</thead>
		<tbody>
			
			<!-- Google Row -->
			<tr>
				<td>
					<strong><?php esc_html_e( 'Google', 'hyper-web-auth' ); ?></strong>
				</td>
				<td>
					<?php if ( $google_identity ) : ?>
						<span class="hwa-status-linked">
							<?php esc_html_e( 'Linked', 'hyper-web-auth' ); ?> 
							(<?php echo esc_html( HWA_Security::mask_email( $google_identity->email ) ); ?>)
						</span>
					<?php else : ?>
						<span class="hwa-status-unlinked"><?php esc_html_e( 'Not Linked', 'hyper-web-auth' ); ?></span>
					<?php endif; ?>
				</td>
				<td>
					<?php if ( $google_identity ) : ?>
						<!-- Unlinking to be implemented in Phase 3.4 -->
						<button class="button" disabled><?php esc_html_e( 'Unlink Google', 'hyper-web-auth' ); ?></button>
					<?php else : ?>
						<a href="<?php echo esc_url( $google_link_url ); ?>" class="button"><?php esc_html_e( 'Link Google', 'hyper-web-auth' ); ?></a>
					<?php endif; ?>
				</td>
			</tr>

			<!-- Phone Row -->
			<tr>
				<td>
					<strong><?php esc_html_e( 'Phone Number (SMS)', 'hyper-web-auth' ); ?></strong>
				</td>
				<td>
					<?php if ( $phone_identity ) : ?>
						<span class="hwa-status-linked">
							<?php esc_html_e( 'Linked', 'hyper-web-auth' ); ?> 
							(<?php echo esc_html( HWA_Security::mask_phone( $phone_identity->phone_e164 ) ); ?>)
						</span>
					<?php else : ?>
						<span class="hwa-status-unlinked"><?php esc_html_e( 'Not Linked', 'hyper-web-auth' ); ?></span>
					<?php endif; ?>
				</td>
				<td>
					<?php if ( $phone_identity ) : ?>
						<!-- Unlinking/Changing to be implemented in Phase 3.4 -->
						<button class="button" disabled><?php esc_html_e( 'Change Phone', 'hyper-web-auth' ); ?></button>
					<?php else : ?>
						<!-- Linking phone to be implemented in Phase 3.3 -->
						<button class="button" disabled><?php esc_html_e( 'Link Phone', 'hyper-web-auth' ); ?></button>
					<?php endif; ?>
				</td>
			</tr>

		</tbody>
	</table>

</div>
