=== HyperWeb Customer Authentication for WooCommerce ===
Contributors: sayanpaul200
Donate link: https://hyperweblabs.in/
Tags: woocommerce, authentication, google-login, phone-login, firebase
Requires at least: 6.0
Tested up to: 6.8
Requires PHP: 8.0
Stable tag: 1.0.0
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html

Secure WooCommerce customer authentication with Google OAuth login and Firebase Phone SMS OTP — plus unified account linking from My Account.

== Description ==

HyperWeb Customer Authentication for WooCommerce provides secure, modern authentication methods for your WooCommerce customers:

* **Google OAuth / OpenID Connect** — One-click "Continue with Google" login and registration on WooCommerce My Account forms.
* **Firebase Phone Authentication** — SMS OTP login and registration powered by Firebase Authentication Phone Number Sign-In.
* **Account Linking** — Customers can link both Google and phone identities from the My Account > Login Methods page.
* **Duplicate Prevention** — Identity-level constraints prevent duplicate accounts and unsafe identity hijacking.
* **Admin Settings** — Configure Google OAuth credentials, Firebase project settings, and security options from WooCommerce > Settings > Hyper Web Auth.
* **Audit Logging** — Masked, privacy-safe authentication event logging for admin troubleshooting.

= Requirements =

* WordPress 6.0 or later
* WooCommerce 7.0 or later
* PHP 8.0 or later (8.1+ recommended)
* HTTPS enabled (required for Google OAuth and Firebase)
* Google Cloud Console OAuth credentials (for Google login)
* Firebase project with Phone Authentication enabled (for phone login)

== Installation ==

1. Upload the `hyper-web-auth` folder to `/wp-content/plugins/`.
2. Activate the plugin through the **Plugins** menu in WordPress.
3. Navigate to **WooCommerce > Settings > Hyper Web Auth** to configure.

= Google OAuth Setup =

1. Create a project in the [Google Cloud Console](https://console.cloud.google.com/).
2. Enable the Google Identity / OAuth 2.0 API.
3. Create OAuth 2.0 credentials (Web application type).
4. Copy the Redirect URI from the plugin settings page and add it to your Google OAuth credentials.
5. Enter the Client ID and Client Secret in the plugin settings.

= Firebase Phone Auth Setup =

1. Create a project in the [Firebase Console](https://console.firebase.google.com/).
2. Enable Authentication and add the Phone provider.
3. Add your site domain to Firebase authorized domains.
4. Create a Web App and copy the Firebase config values to the plugin settings.
5. Configure the Firebase service-account credential path for backend token verification.

== Frequently Asked Questions ==

= Does this plugin require WooCommerce? =

Yes. This plugin extends WooCommerce customer authentication. It will show an admin notice if WooCommerce is not active.

= Is HTTPS required? =

Yes. Google OAuth requires HTTPS for redirect URIs, and Firebase Phone Authentication requires a secure origin.

= Can customers use both Google and phone login? =

Yes. Customers can link both identities from My Account > Login Methods after the account-linking feature is enabled.

== Screenshots ==

1. Google "Continue with Google" button on WooCommerce login form.
2. Firebase Phone Authentication OTP flow on registration form.
3. My Account > Login Methods page showing linked identities.
4. WooCommerce > Settings > Hyper Web Auth admin configuration.

== Changelog ==

= 1.0.0 =
* Initial release.
* Google OAuth / OpenID Connect login and registration.
* Firebase Phone Authentication login and registration.
* My Account identity linking.
* Admin settings under WooCommerce > Settings.
* Audit logging.

== Upgrade Notice ==

= 1.0.0 =
Initial release.