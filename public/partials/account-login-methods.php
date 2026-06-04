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
						<button class="button hwa-btn-unlink" data-provider="google"><?php esc_html_e( 'Unlink Google', 'hyper-web-auth' ); ?></button>
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
						<button class="button hwa-btn-unlink" data-provider="firebase_phone" style="margin-right: 8px;"><?php esc_html_e( 'Unlink Phone', 'hyper-web-auth' ); ?></button>
						<!-- Changing phone to be implemented later -->
						<button class="button" disabled><?php esc_html_e( 'Change Phone', 'hyper-web-auth' ); ?></button>
					<?php else : ?>
						<!-- Linking phone to be implemented in Phase 3.3 -->
						<button class="button" id="hwa-btn-show-link-phone"><?php esc_html_e( 'Link Phone', 'hyper-web-auth' ); ?></button>
					<?php endif; ?>
				</td>
			</tr>

		</tbody>
	</table>

	<!-- Inline Phone Link Form (Hidden by default) -->
	<div id="hwa-phone-link-container" class="hwa-hidden" style="margin-top: 30px; padding: 20px; border: 1px solid #e2e8f0; border-radius: 8px; background: #f8fafc;">
		<h4><?php esc_html_e( 'Link a Phone Number', 'hyper-web-auth' ); ?></h4>
		<p><?php esc_html_e( 'Enter your phone number below to receive a verification code.', 'hyper-web-auth' ); ?></p>
		
		<div id="hwa-phone-link-error" class="hwa-error-message hwa-hidden" style="color: #dc2626; margin-bottom: 15px;"></div>
		<div id="hwa-phone-link-success" class="hwa-success-message hwa-hidden" style="color: #16a34a; margin-bottom: 15px;"></div>

		<!-- Step 1: Phone Input -->
		<div id="hwa-phone-link-step-1">
			<p class="form-row form-row-wide">
				<label for="hwa_link_phone"><?php esc_html_e( 'Phone Number', 'hyper-web-auth' ); ?>&nbsp;<span class="required">*</span></label>
				<input type="tel" class="woocommerce-Input woocommerce-Input--phone input-text" name="hwa_link_phone" id="hwa_link_phone" autocomplete="tel" placeholder="+1234567890" />
			</p>
			
			<div id="hwa-recaptcha-link-container"></div>
			
			<p class="form-row">
				<button type="button" class="woocommerce-Button button" id="hwa-btn-send-link-sms"><?php esc_html_e( 'Send Code', 'hyper-web-auth' ); ?></button>
			</p>
		</div>

		<!-- Step 2: OTP Input -->
		<div id="hwa-phone-link-step-2" class="hwa-hidden">
			<p class="form-row form-row-wide">
				<label for="hwa_link_otp"><?php esc_html_e( 'Verification Code', 'hyper-web-auth' ); ?>&nbsp;<span class="required">*</span></label>
				<input type="text" class="woocommerce-Input woocommerce-Input--text input-text" name="hwa_link_otp" id="hwa_link_otp" autocomplete="one-time-code" placeholder="123456" />
			</p>
			
			<p class="form-row">
				<button type="button" class="woocommerce-Button button" id="hwa-btn-verify-link-otp"><?php esc_html_e( 'Verify & Link', 'hyper-web-auth' ); ?></button>
			</p>
		</div>
	</div>

</div>
