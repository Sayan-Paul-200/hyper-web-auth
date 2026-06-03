<?php
/**
 * Provide a public-facing view for the Phone Register form.
 *
 * This template is injected via woocommerce_register_form_end, which means
 * it renders INSIDE WooCommerce's own <form>. We must NOT use a nested
 * <form> tag — browsers silently strip nested forms, breaking our JS.
 *
 * The Email field is intentionally removed here because WooCommerce already
 * renders its own email field (#reg_email) above us. Our JavaScript reads
 * from that native field to avoid duplication.
 *
 * @link       https://github.com/Sayan-Paul-200
 * @since      1.0.0
 *
 * @package    Hyper_Web_Auth
 * @subpackage Hyper_Web_Auth/public/partials
 */

?>

<div class="hwa-phone-auth-container hwa-phone-register">
	<p class="hwa-phone-separator"><?php esc_html_e( '— OR —', 'hyper-web-auth' ); ?></p>
	
	<div id="hwa-phone-register-form" class="hwa-phone-form">
		<div id="hwa-phone-register-error" class="woocommerce-error hwa-hidden" role="alert"></div>

		<!-- Step 1: Request SMS -->
		<div id="hwa-phone-register-step-1">
			<p class="woocommerce-form-row woocommerce-form-row--first form-row form-row-first">
				<label for="hwa_register_first_name"><?php esc_html_e( 'First name', 'hyper-web-auth' ); ?>&nbsp;<span class="required">*</span></label>
				<input type="text" class="woocommerce-Input woocommerce-Input--text input-text" name="hwa_register_first_name" id="hwa_register_first_name" />
			</p>

			<p class="woocommerce-form-row woocommerce-form-row--last form-row form-row-last">
				<label for="hwa_register_last_name"><?php esc_html_e( 'Last name', 'hyper-web-auth' ); ?>&nbsp;<span class="required">*</span></label>
				<input type="text" class="woocommerce-Input woocommerce-Input--text input-text" name="hwa_register_last_name" id="hwa_register_last_name" />
			</p>
			<div class="clear"></div>

			<p class="woocommerce-form-row woocommerce-form-row--wide form-row form-row-wide">
				<label for="hwa_register_phone"><?php esc_html_e( 'Phone number', 'hyper-web-auth' ); ?>&nbsp;<span class="required">*</span></label>
				<input type="tel" class="woocommerce-Input woocommerce-Input--text input-text" name="hwa_register_phone" id="hwa_register_phone" placeholder="+1234567890" />
			</p>

			<?php 
			$consent = HWA_Settings::get_setting( 'firebase_consent_text' );
			if ( ! empty( $consent ) ) : 
			?>
				<p class="hwa-consent-text"><?php echo esc_html( $consent ); ?></p>
			<?php endif; ?>

			<p class="form-row">
				<button type="button" class="woocommerce-button button" id="hwa-btn-send-register-sms"><?php esc_html_e( 'Continue with Phone', 'hyper-web-auth' ); ?></button>
			</p>
			<div id="hwa-recaptcha-register-container"></div>
		</div>

		<!-- Step 2: Verify OTP -->
		<div id="hwa-phone-register-step-2" class="hwa-hidden">
			<p class="woocommerce-form-row woocommerce-form-row--wide form-row form-row-wide">
				<label for="hwa_register_otp"><?php esc_html_e( 'Verification Code', 'hyper-web-auth' ); ?>&nbsp;<span class="required">*</span></label>
				<input type="text" class="woocommerce-Input woocommerce-Input--text input-text" name="hwa_register_otp" id="hwa_register_otp" placeholder="123456" />
				<small class="hwa-help-text"><?php esc_html_e( 'Enter the 6-digit code sent to your phone.', 'hyper-web-auth' ); ?></small>
			</p>
			<p class="form-row">
				<button type="button" class="woocommerce-button button" id="hwa-btn-verify-register-otp"><?php esc_html_e( 'Verify and Register', 'hyper-web-auth' ); ?></button>
			</p>
		</div>
	</div>
</div>
