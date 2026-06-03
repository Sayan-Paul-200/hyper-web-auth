<?php
/**
 * Provide a public-facing view for the Phone Login form.
 *
 * @link       https://github.com/Sayan-Paul-200
 * @since      1.0.0
 *
 * @package    Hyper_Web_Auth
 * @subpackage Hyper_Web_Auth/public/partials
 */

?>

<div class="hwa-phone-auth-container hwa-phone-login">
	<p class="hwa-phone-separator"><?php esc_html_e( '— OR —', 'hyper-web-auth' ); ?></p>
	
	<form id="hwa-phone-login-form" class="hwa-phone-form" autocomplete="off">
		<div id="hwa-phone-login-error" class="woocommerce-error hwa-hidden" role="alert"></div>

		<!-- Step 1: Request SMS -->
		<div id="hwa-phone-login-step-1">
			<p class="woocommerce-form-row woocommerce-form-row--wide form-row form-row-wide">
				<label for="hwa_login_phone"><?php esc_html_e( 'Phone number', 'hyper-web-auth' ); ?>&nbsp;<span class="required">*</span></label>
				<input type="tel" class="woocommerce-Input woocommerce-Input--text input-text" name="hwa_login_phone" id="hwa_login_phone" placeholder="+1234567890" />
			</p>

			<?php 
			$consent = HWA_Settings::get_setting( 'firebase_consent_text' );
			if ( ! empty( $consent ) ) : 
			?>
				<p class="hwa-consent-text"><?php echo esc_html( $consent ); ?></p>
			<?php endif; ?>

			<p class="form-row">
				<button type="submit" class="woocommerce-button button" id="hwa-btn-send-login-sms" value="<?php esc_attr_e( 'Continue with Phone', 'hyper-web-auth' ); ?>"><?php esc_html_e( 'Continue with Phone', 'hyper-web-auth' ); ?></button>
			</p>
			<div id="hwa-recaptcha-login-container"></div>
		</div>

		<!-- Step 2: Verify OTP -->
		<div id="hwa-phone-login-step-2" class="hwa-hidden">
			<p class="woocommerce-form-row woocommerce-form-row--wide form-row form-row-wide">
				<label for="hwa_login_otp"><?php esc_html_e( 'Verification Code', 'hyper-web-auth' ); ?>&nbsp;<span class="required">*</span></label>
				<input type="text" class="woocommerce-Input woocommerce-Input--text input-text" name="hwa_login_otp" id="hwa_login_otp" placeholder="123456" />
				<small class="hwa-help-text"><?php esc_html_e( 'Enter the 6-digit code sent to your phone.', 'hyper-web-auth' ); ?></small>
			</p>
			<p class="form-row">
				<button type="submit" class="woocommerce-button button" id="hwa-btn-verify-login-otp" value="<?php esc_attr_e( 'Verify and Login', 'hyper-web-auth' ); ?>"><?php esc_html_e( 'Verify and Login', 'hyper-web-auth' ); ?></button>
			</p>
		</div>
	</form>
</div>
