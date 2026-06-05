<?php
/**
 * Unified Multi-Step Auth Form (Login or Signup)
 *
 * This template handles the 3-step authentication flow:
 * 1. Phone + Google
 * 2. OTP Verification
 * 3. Profile Completion (for new users)
 *
 * @link       https://github.com/Sayan-Paul-200
 * @since      1.1.0
 *
 * @package    Hyper_Web_Auth
 * @subpackage Hyper_Web_Auth/public/partials
 */

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>



<div id="hwa-unified-auth-container" class="hwa-unified-auth-wrapper">
	
	<!-- STEP 1: Phone & Google -->
	<div id="hwa-step-1-phone" class="hwa-auth-step hwa-active">
		<h3 style="margin-top: 0;"><?php esc_html_e( 'Login or Signup', 'hyper-web-auth' ); ?></h3>
		
		<div class="hwa-phone-input-group">
			<span class="hwa-country-code"><?php echo esc_html( HWA_Settings::get_setting( 'firebase_default_country_code', '+91' ) ); ?></span>
			<input type="tel" id="hwa-unified-phone" placeholder="<?php esc_attr_e( 'Mobile Number*', 'hyper-web-auth' ); ?>" autocomplete="tel-national">
		</div>
		
		<p style="font-size: 0.85em; color: #64748b; margin-bottom: 1.5em;">
			<?php esc_html_e( 'By continuing, I agree to the Terms of Use & Privacy Policy', 'hyper-web-auth' ); ?>
		</p>
		
		<button type="button" id="hwa-btn-unified-continue" class="button hwa-btn-full-width" style="width: 100%;">
			<?php esc_html_e( 'CONTINUE', 'hyper-web-auth' ); ?>
		</button>
		
		<?php if ( 'yes' === HWA_Settings::get_setting( 'google_enabled' ) ) : ?>
			<div class="hwa-divider"><?php esc_html_e( 'OR', 'hyper-web-auth' ); ?></div>
			
			<div class="hwa-google-auth-container">
				<?php $this->render_google_button( 'login', __( 'Continue with Google', 'hyper-web-auth' ) ); ?>
			</div>
		<?php endif; ?>
		
		<!-- Firebase Recaptcha Container -->
		<div id="hwa-unified-recaptcha-container" style="margin-top: 15px;"></div>
	</div>
	
	<!-- STEP 2: OTP Verification -->
	<div id="hwa-step-2-otp" class="hwa-auth-step">
		<h3 style="margin-top: 0;"><?php esc_html_e( 'Verify with OTP', 'hyper-web-auth' ); ?></h3>
		<p style="color: #64748b; margin-bottom: 1.5em;">
			<?php esc_html_e( 'Sent to', 'hyper-web-auth' ); ?> <strong id="hwa-masked-phone-display"></strong>
		</p>
		
		<div class="hwa-otp-boxes">
			<input type="text" class="hwa-otp-digit" maxlength="1" pattern="\d*" data-index="0" autocomplete="one-time-code">
			<input type="text" class="hwa-otp-digit" maxlength="1" pattern="\d*" data-index="1">
			<input type="text" class="hwa-otp-digit" maxlength="1" pattern="\d*" data-index="2">
			<input type="text" class="hwa-otp-digit" maxlength="1" pattern="\d*" data-index="3">
			<input type="text" class="hwa-otp-digit" maxlength="1" pattern="\d*" data-index="4">
			<input type="text" class="hwa-otp-digit" maxlength="1" pattern="\d*" data-index="5">
		</div>
		
		<div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5em; font-size: 0.9em;">
			<button type="button" id="hwa-resend-otp-btn" disabled style="background: none; border: none; color: #2563eb; padding: 0; cursor: pointer; text-decoration: underline;">
				<?php esc_html_e( 'RESEND OTP', 'hyper-web-auth' ); ?>
			</button>
			<span id="hwa-resend-timer" style="color: #64748b;">00:60</span>
		</div>
		
		<button type="button" id="hwa-btn-unified-verify" class="button hwa-btn-full-width" style="width: 100%;">
			<?php esc_html_e( 'VERIFY & PROCEED', 'hyper-web-auth' ); ?>
		</button>
	</div>
	
	<!-- STEP 3: Profile Completion (New Users Only) -->
	<div id="hwa-step-3-profile" class="hwa-auth-step">
		<h3 style="margin-top: 0;"><?php esc_html_e( 'Complete Your Profile', 'hyper-web-auth' ); ?></h3>
		<p style="color: #64748b; margin-bottom: 1.5em; font-size: 0.9em;">
			<?php esc_html_e( 'Welcome! Please provide your details to create your account.', 'hyper-web-auth' ); ?>
		</p>
		
		<div style="display: flex; gap: 10px; margin-bottom: 1em;">
			<p class="form-row form-row-first" style="margin-bottom: 0; flex: 1;">
				<label for="hwa-unified-first-name" class="screen-reader-text"><?php esc_html_e( 'First Name', 'hyper-web-auth' ); ?></label>
				<input type="text" class="input-text" name="hwa_first_name" id="hwa-unified-first-name" placeholder="<?php esc_attr_e( 'First Name*', 'hyper-web-auth' ); ?>" autocomplete="given-name" required />
			</p>
			
			<p class="form-row form-row-last" style="margin-bottom: 0; flex: 1;">
				<label for="hwa-unified-last-name" class="screen-reader-text"><?php esc_html_e( 'Last Name', 'hyper-web-auth' ); ?></label>
				<input type="text" class="input-text" name="hwa_last_name" id="hwa-unified-last-name" placeholder="<?php esc_attr_e( 'Last Name*', 'hyper-web-auth' ); ?>" autocomplete="family-name" required />
			</p>
		</div>
		
		<p class="form-row form-row-wide" style="margin-bottom: 0.5em;">
			<label for="hwa-unified-email" class="screen-reader-text"><?php esc_html_e( 'Email address', 'hyper-web-auth' ); ?></label>
			<input type="email" class="input-text" name="hwa_email" id="hwa-unified-email" placeholder="<?php esc_attr_e( 'Email Address*', 'hyper-web-auth' ); ?>" autocomplete="email" required />
		</p>
		
		<p style="font-size: 0.8em; color: #64748b; margin-bottom: 1.5em; line-height: 1.3;">
			<?php esc_html_e( 'Your email is required for order confirmations, shipping updates, and account recovery.', 'hyper-web-auth' ); ?>
		</p>
		
		<button type="button" id="hwa-btn-unified-create" class="button hwa-btn-full-width" style="width: 100%;">
			<?php esc_html_e( 'CREATE ACCOUNT', 'hyper-web-auth' ); ?>
		</button>
	</div>

</div>
