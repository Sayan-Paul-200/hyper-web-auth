# HyperWeb Customer Authentication for WooCommerce

## Agentic AI Master Implementation Plan — Firebase Phone Auth Updated Version

**Project:** `hyper-web-auth`  
**Plugin name:** HyperWeb Customer Authentication for WooCommerce  
**Primary stack:** WordPress, WooCommerce, PHP, JavaScript, REST API, Google OAuth/OpenID Connect, Firebase Authentication Phone Number Sign-In  
**Implementation priority:**

1. Google Social OAuth / OpenID Connect authentication
2. Firebase Phone Authentication for SMS OTP registration/login
3. Combined identity/account-linking system
4. Checkout integration and production hardening

---

## 0. Purpose of This Document

This document is written for an intelligent coding agent working inside an IDE. It explains the current scaffold, target architecture, implementation order, file-level responsibilities, phase-by-phase tasks, acceptance criteria, and guardrails.

The plugin must evolve from the current WordPress Plugin Boilerplate scaffold into a production-grade WooCommerce customer authentication plugin supporting:

- Google OAuth/OpenID Connect registration and login
- Firebase Phone Authentication for phone/SMS verification
- WooCommerce customer login/registration bridge after external identity verification
- My Account identity linking
- Duplicate-account prevention
- Customer-safe login/register redirects
- WooCommerce-compatible customer creation and authentication
- Admin-configurable Google and Firebase settings
- Audit logging, rate limiting, and security hardening

The implementation must be incremental. Do **not** attempt to build Google OAuth, Firebase Phone Auth, and account linking in a single large patch.

Important change from the previous plan:

```text
Phone OTP is now Firebase-based.
Do not build a custom SMS gateway, custom OTP generator, custom OTP hashing flow, or custom OTP challenge table for the first production implementation.
Firebase sends/verifies the SMS OTP on the frontend.
WordPress verifies the Firebase ID token server-side before creating, logging in, or linking a WooCommerce customer.
```

---

## 1. Current Scaffold Analysis

The current uploaded codebase is a WordPress Plugin Boilerplate-style plugin scaffold.

### 1.1 Current File Structure

```text
hyper-web-auth/
├── hyper-web-auth.php
├── uninstall.php
├── README.txt
├── LICENSE.txt
├── index.php
├── admin/
│   ├── class-hyper-web-auth-admin.php
│   ├── css/hyper-web-auth-admin.css
│   ├── js/hyper-web-auth-admin.js
│   └── partials/hyper-web-auth-admin-display.php
├── includes/
│   ├── class-hyper-web-auth.php
│   ├── class-hyper-web-auth-loader.php
│   ├── class-hyper-web-auth-activator.php
│   ├── class-hyper-web-auth-deactivator.php
│   └── class-hyper-web-auth-i18n.php
├── public/
│   ├── class-hyper-web-auth-public.php
│   ├── css/hyper-web-auth-public.css
│   ├── js/hyper-web-auth-public.js
│   └── partials/hyper-web-auth-public-display.php
└── languages/
    └── hyper-web-auth.pot
```

The scaffold also contains a `.git/` directory in the uploaded zip. Do **not** include `.git/` inside distributable plugin builds.

---

## 2. Current File Responsibilities

### 2.1 `hyper-web-auth.php`

Current role:

- Defines plugin metadata
- Defines `HYPER_WEB_AUTH_VERSION`
- Registers activation and deactivation hooks
- Loads `includes/class-hyper-web-auth.php`
- Instantiates and runs `Hyper_Web_Auth`

Required evolution:

- Add stable plugin constants
- Add safe dependency checks
- Keep bootstrap lightweight
- Do not put business logic here

Required constants:

```php
define( 'HYPER_WEB_AUTH_VERSION', '1.0.0' );
define( 'HYPER_WEB_AUTH_FILE', __FILE__ );
define( 'HYPER_WEB_AUTH_PATH', plugin_dir_path( __FILE__ ) );
define( 'HYPER_WEB_AUTH_URL', plugin_dir_url( __FILE__ ) );
define( 'HYPER_WEB_AUTH_BASENAME', plugin_basename( __FILE__ ) );
```

### 2.2 `includes/class-hyper-web-auth.php`

Current role:

- Main plugin orchestrator
- Loads dependencies
- Sets locale
- Registers admin/public hooks
- Runs loader

Required evolution:

- Load new domain classes
- Register REST routes
- Register WooCommerce hooks
- Register My Account endpoint hooks
- Register settings hooks
- Keep actual business logic out of this class

### 2.3 `includes/class-hyper-web-auth-loader.php`

Current role:

- Stores actions and filters
- Registers them with WordPress when `run()` is called

Required evolution:

- Keep as orchestration utility
- Continue using for hooks
- Do not place auth logic here

### 2.4 `includes/class-hyper-web-auth-activator.php`

Current role:

- Empty activation placeholder

Required evolution:

- Create custom database tables
- Add default settings
- Register and flush rewrite endpoints
- Store plugin database version

### 2.5 `includes/class-hyper-web-auth-deactivator.php`

Current role:

- Empty deactivation placeholder

Required evolution:

- Flush rewrite rules
- Unschedule cleanup cron events
- Do not delete plugin/customer data here

### 2.6 `includes/class-hyper-web-auth-i18n.php`

Current role:

- Loads text domain

Required evolution:

- Keep as-is
- Ensure all user-facing strings are translation-ready

### 2.7 `admin/class-hyper-web-auth-admin.php`

Current role:

- Enqueues admin CSS/JS globally

Required evolution:

- Register WooCommerce-native settings page/section
- Register admin settings fields
- Render settings partial
- Enqueue admin assets only on plugin admin pages
- Add clear diagnostics for missing Google/Firebase configuration

### 2.8 `admin/partials/hyper-web-auth-admin-display.php`

Current role:

- Placeholder admin display partial

Required evolution:

- Settings page UI
- Google OAuth settings
- Firebase client settings
- Firebase Admin SDK/service-account settings
- Phone auth/default-country settings
- Security settings
- Debug/audit settings

### 2.9 `public/class-hyper-web-auth-public.php`

Current role:

- Enqueues public CSS/JS globally

Required evolution:

- Render Google login/register buttons
- Render Firebase Phone Auth forms later
- Inject UI into WooCommerce login/register pages
- Localize REST URLs/nonces for frontend JS
- Localize Firebase web app config only when phone auth is enabled and only on relevant pages
- Enqueue assets only on relevant pages

### 2.10 `public/js/hyper-web-auth-public.js`

Current role:

- Empty boilerplate JS

Required evolution:

- Phase 1: handle Google button redirects only if needed
- Phase 2: load/use Firebase Web SDK phone auth flow
- Phase 2: initialize Firebase app, `RecaptchaVerifier`, `signInWithPhoneNumber`, confirmation result, and ID-token handoff to WordPress
- Phase 3: handle account-linking UI interactions

### 2.11 `uninstall.php`

Current role:

- Empty uninstall guard

Required evolution:

- Only delete tables/options if admin setting allows data deletion on uninstall
- Never delete user accounts
- Never delete WooCommerce orders

### 2.12 `README.txt`

Current role:

- Boilerplate placeholder content

Required evolution:

- Replace plugin placeholder metadata
- Add setup instructions
- Add Google OAuth setup instructions
- Add Firebase Phone Auth setup instructions
- Add Firebase authorized-domain and test-phone-number setup instructions

---

## 3. Development Principles for the Agent

### 3.1 General Rules

- Make small, reviewable patches.
- Preserve the existing plugin boilerplate style unless there is a clear reason to refactor.
- Do not rename the main plugin class unless absolutely necessary.
- Do not put auth business logic in templates.
- Do not put database SQL inside REST callbacks.
- Do not hard-code credentials.
- Do not store Google client secret, Firebase service-account credentials, or API keys in source code.
- Do not trust client-side Firebase success alone.
- Do not trust client-side phone numbers alone.
- Do not log sensitive tokens, authorization codes, Firebase ID tokens, service-account JSON, API keys, or secrets.

### 3.2 Security Rules

- Validate and sanitize all input.
- Escape all output.
- Use WordPress nonces for forms/AJAX/REST requests, but do not treat nonces as authorization.
- Use server-side permission checks.
- Use `current_user_can()` for admin actions.
- Use `is_user_logged_in()` for My Account linking routes.
- Use `wp_safe_redirect()` for internal redirects.
- Use allowlisted redirect destinations.
- Use prepared SQL queries through `$wpdb->prepare()`.
- Use `dbDelta()` for table creation/updates.
- Use Google `sub` as the stable Google identity identifier.
- Validate Google ID tokens server-side.
- For Firebase Phone Auth, verify the Firebase ID token server-side before trusting the Firebase UID or phone number.
- Confirm the phone number inside the verified Firebase token matches the expected phone number submitted for login/register/linking.
- Use `wc_create_new_customer()` for WooCommerce customer creation where possible.
- Use `wc_set_customer_auth_cookie()` or WordPress auth-cookie helpers for login.

### 3.3 Firebase-Specific Security Rules

- Firebase Web SDK may verify phone ownership on the frontend, but WordPress must still verify the Firebase ID token server-side.
- Do not create, log in, or link a WooCommerce customer from a frontend-only Firebase response.
- Use Firebase Authentication test phone numbers only in development/staging.
- Production domains must be configured as Firebase authorized domains.
- The frontend must use Firebase `RecaptchaVerifier` / phone auth anti-abuse flow.
- Backend must verify the token issuer, audience/project ID, expiry, signature, Firebase UID, and phone number claim.
- Backend should optionally check token revocation later if required for higher security.
- Add privacy/consent language because phone numbers used for Firebase Authentication are processed by Google/Firebase.

### 3.4 UX Rules

The default customer screen should be login.

Login form behavior:

- Existing Google-linked customer using Google: login.
- New Google customer using Google: register and login.
- Existing Firebase-phone-linked customer using phone: run Firebase Phone Auth, verify backend token, login.
- Unknown phone using phone login: redirect/register prompt with error.

Registration form behavior:

- New Google customer using Google: register and login.
- Already Google-linked customer using Google from registration form: login.
- New phone using phone registration: run Firebase Phone Auth, verify backend token, register, login.
- Existing phone using phone registration: redirect/login prompt with error.

---

## 4. Target Architecture

The scaffold should evolve into this structure.

```text
hyper-web-auth/
├── hyper-web-auth.php
├── uninstall.php
├── composer.json
├── vendor/
├── admin/
│   ├── class-hyper-web-auth-admin.php
│   ├── css/hyper-web-auth-admin.css
│   ├── js/hyper-web-auth-admin.js
│   └── partials/
│       └── hyper-web-auth-admin-display.php
├── includes/
│   ├── class-hyper-web-auth.php
│   ├── class-hyper-web-auth-loader.php
│   ├── class-hyper-web-auth-activator.php
│   ├── class-hyper-web-auth-deactivator.php
│   ├── class-hyper-web-auth-i18n.php
│   ├── class-hwa-database.php
│   ├── class-hwa-settings.php
│   ├── class-hwa-security.php
│   ├── class-hwa-identity-repository.php
│   ├── class-hwa-oauth-state-repository.php
│   ├── class-hwa-google-oauth-service.php
│   ├── class-hwa-firebase-auth-service.php
│   ├── class-hwa-customer-service.php
│   ├── class-hwa-audit-logger.php
│   └── class-hwa-rate-limiter.php
├── rest/
│   └── class-hwa-rest-controller.php
├── public/
│   ├── class-hyper-web-auth-public.php
│   ├── class-hwa-my-account.php
│   ├── css/hyper-web-auth-public.css
│   ├── js/hyper-web-auth-public.js
│   └── partials/
│       ├── google-button.php
│       ├── phone-login-form.php
│       ├── phone-register-form.php
│       └── account-login-methods.php
└── languages/
    └── hyper-web-auth.pot
```

Removed from the previous target architecture:

```text
class-hwa-otp-repository.php
class-hwa-otp-service.php
class-hwa-sms-provider.php
otp-verify-form.php as a server-generated OTP verification artifact
```

Reason: Firebase handles OTP sending and code confirmation. The plugin only needs Firebase frontend integration, backend token verification, identity lookup, WooCommerce customer creation/login, and account linking.

---

## 5. Target Domain Model

### 5.1 Customer Account

A WordPress/WooCommerce customer account is the canonical user record.

A customer can have one or more login identities:

```text
Customer Account
├── Google identity
└── Firebase phone identity
```

### 5.2 Identity

An identity is a verified external login method connected to a WordPress user.

Providers:

```text
google
firebase_phone
```

Google identity example:

```text
provider = google
provider_user_id = Google sub
identity_hash = HMAC-SHA256(Google sub)
user_id = WordPress user ID
email = verified Google email, if available
```

Firebase phone identity example:

```text
provider = firebase_phone
provider_user_id = Firebase UID
identity_hash = HMAC-SHA256(Firebase UID)
phone_e164 = +919876543210
phone_hash = HMAC-SHA256(+919876543210), if separate column exists
user_id = WordPress user ID
```

### 5.3 Why Store Firebase UID and Phone

Firebase UID is the stable provider identity inside the Firebase project. The verified phone number is the business identity used for login/register rules.

Recommended logic:

- Use `firebase_uid` / Firebase `sub` as provider identity.
- Store normalized `phone_e164` for display and phone lookup.
- Store `phone_hash` or an indexed phone identity mapping so the plugin can check whether a phone already belongs to a WooCommerce customer before starting the Firebase flow.

### 5.4 Account Linking

Account linking must be handled only after identity verification.

Safe linking rules:

- Google can link only after Google ID token validation.
- Phone can link only after Firebase ID token validation and phone-number match.
- Existing linked identity cannot be linked to another user.
- Existing linked phone cannot be linked to another user.
- Existing linked identity for same user should show “already linked,” not create duplicates.

---

## 6. Database Tables

### 6.1 Phase 1 Minimum Table: Identities

Create this table before Google OAuth goes live. This version also supports Firebase phone identities later.

```sql
CREATE TABLE {$wpdb->prefix}hwa_identities (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  user_id BIGINT UNSIGNED NOT NULL,
  provider VARCHAR(30) NOT NULL,
  identity_hash CHAR(64) NOT NULL,
  identity_display VARCHAR(191) NULL,
  provider_uid VARCHAR(191) NULL,
  email VARCHAR(191) NULL,
  phone_e164 VARCHAR(30) NULL,
  phone_hash CHAR(64) NULL,
  is_verified TINYINT(1) NOT NULL DEFAULT 0,
  status VARCHAR(20) NOT NULL DEFAULT 'active',
  linked_at DATETIME NOT NULL,
  last_login_at DATETIME NULL,
  PRIMARY KEY (id),
  UNIQUE KEY provider_identity (provider, identity_hash),
  UNIQUE KEY provider_phone (provider, phone_hash),
  KEY user_id (user_id),
  KEY provider_user (provider, user_id),
  KEY phone_hash (phone_hash)
) {$charset_collate};
```

Notes:

- For `provider = google`, `identity_hash = HMAC(Google sub)`.
- For `provider = firebase_phone`, `identity_hash = HMAC(Firebase UID)` and `phone_hash = HMAC(E.164 phone)`.
- If MySQL uniqueness with nullable `phone_hash` creates portability concerns, enforce uniqueness in repository logic plus a non-null sentinel strategy. The preferred production target is database-level uniqueness.

### 6.2 Phase 1 Recommended Table: OAuth State

OAuth state may be stored in transients, but a table is cleaner for logging and expiry. Either approach is acceptable. If using a table:

```sql
CREATE TABLE {$wpdb->prefix}hwa_oauth_states (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  state_hash CHAR(64) NOT NULL,
  provider VARCHAR(30) NOT NULL,
  context VARCHAR(30) NOT NULL,
  return_to TEXT NULL,
  user_id BIGINT UNSIGNED NULL,
  nonce_hash CHAR(64) NULL,
  status VARCHAR(20) NOT NULL DEFAULT 'pending',
  expires_at DATETIME NOT NULL,
  created_at DATETIME NOT NULL,
  consumed_at DATETIME NULL,
  PRIMARY KEY (id),
  UNIQUE KEY state_hash (state_hash),
  KEY expires_at (expires_at),
  KEY provider_context (provider, context)
) {$charset_collate};
```

### 6.3 Firebase Phone Attempt Table — Optional, Not an OTP Table

Do **not** create a custom OTP challenge table for Firebase Phone Auth.

Firebase handles:

```text
SMS delivery
OTP generation
OTP expiry/confirmation
reCAPTCHA-backed anti-abuse flow
Firebase authenticated user session on frontend
```

The plugin may optionally create a lightweight attempt table for observability/rate limiting, but it must not store OTP codes.

Optional table:

```sql
CREATE TABLE {$wpdb->prefix}hwa_phone_auth_attempts (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  context VARCHAR(30) NOT NULL,
  phone_e164 VARCHAR(30) NOT NULL,
  phone_hash CHAR(64) NOT NULL,
  firebase_uid VARCHAR(191) NULL,
  ip_hash CHAR(64) NULL,
  user_agent_hash CHAR(64) NULL,
  status VARCHAR(30) NOT NULL DEFAULT 'started',
  message TEXT NULL,
  created_at DATETIME NOT NULL,
  completed_at DATETIME NULL,
  PRIMARY KEY (id),
  KEY phone_hash (phone_hash),
  KEY context_status (context, status),
  KEY created_at (created_at)
) {$charset_collate};
```

Attempt contexts:

```text
phone_login
phone_register
phone_link
checkout_phone_login_later
```

### 6.4 Audit Log Table

```sql
CREATE TABLE {$wpdb->prefix}hwa_auth_logs (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  user_id BIGINT UNSIGNED NULL,
  event_type VARCHAR(50) NOT NULL,
  provider VARCHAR(30) NULL,
  status VARCHAR(20) NOT NULL,
  ip_hash CHAR(64) NULL,
  message TEXT NULL,
  context TEXT NULL,
  created_at DATETIME NOT NULL,
  PRIMARY KEY (id),
  KEY user_id (user_id),
  KEY event_type (event_type),
  KEY created_at (created_at)
) {$charset_collate};
```

Do not store secrets, raw Firebase ID tokens, Google tokens, authorization codes, Firebase service-account JSON, or full sensitive payloads in the audit log.

---

## 7. Settings Model

Use one option initially:

```text
hwa_settings
```

Recommended shape:

```php
array(
    'google_enabled'                   => 'no',
    'google_client_id'                 => '',
    'google_client_secret'             => '',
    'google_redirect_uri'              => '',
    'google_auto_create_customer'      => 'yes',
    'google_match_existing_email'      => 'no',

    'firebase_phone_enabled'           => 'no',
    'firebase_phone_registration_enabled' => 'yes',
    'firebase_api_key'                 => '',
    'firebase_auth_domain'             => '',
    'firebase_project_id'              => '',
    'firebase_app_id'                  => '',
    'firebase_messaging_sender_id'     => '',
    'firebase_measurement_id'          => '',
    'firebase_service_account_mode'    => 'constant_or_path',
    'firebase_service_account_path'    => '',
    'firebase_tenant_id'               => '',
    'firebase_default_country_code'    => '+91',
    'firebase_recaptcha_mode'          => 'invisible',
    'firebase_use_test_numbers_notice' => 'yes',

    'account_linking_enabled'          => 'no',
    'login_methods_endpoint'           => 'login-methods',
    'delete_data_on_uninstall'         => 'no',
    'debug_logging'                    => 'no',
)
```

For production secrets, support constants as overrides:

```php
HWA_GOOGLE_CLIENT_ID
HWA_GOOGLE_CLIENT_SECRET
HWA_FIREBASE_API_KEY
HWA_FIREBASE_PROJECT_ID
HWA_FIREBASE_SERVICE_ACCOUNT_PATH
HWA_FIREBASE_SERVICE_ACCOUNT_JSON
```

Settings retrieval should prefer constants when defined.

Important frontend rule:

```text
Firebase Web SDK config values like apiKey, authDomain, projectId, appId are not secret in the same way as service-account credentials.
Firebase service-account JSON/private key must never be exposed to frontend JavaScript.
```

---

# 8. Master Implementation Roadmap

## Phase 0: Scaffold Hardening and Foundation

Goal: Prepare the scaffold for safe feature implementation without adding user-facing authentication behavior yet.

### Phase 0.1: Clean Project Packaging

Tasks:

- Ensure `.git/` is not included in release zips.
- Keep `index.php` guards.
- Update `README.txt` plugin metadata.
- Add `composer.json` placeholder for optional backend token-verification dependencies.
- Add `.gitignore` if missing.

Acceptance criteria:

- Plugin zip contains only plugin source and required dependencies.
- No `.git/`, local IDE files, node_modules, or secrets in package.

### Phase 0.2: Add Plugin Constants

Tasks:

- Update `hyper-web-auth.php` with constants:
  - `HYPER_WEB_AUTH_FILE`
  - `HYPER_WEB_AUTH_PATH`
  - `HYPER_WEB_AUTH_URL`
  - `HYPER_WEB_AUTH_BASENAME`
- Replace repeated `plugin_dir_path( dirname( __FILE__ ) )` usages where appropriate.

Acceptance criteria:

- Plugin still activates.
- Existing CSS/JS still enqueue.
- No fatal errors.

### Phase 0.3: WooCommerce Dependency Handling

Tasks:

- Add dependency check class or method.
- If WooCommerce inactive:
  - Do not initialize WooCommerce-specific hooks.
  - Show admin notice.
- Do not hard-fail site frontend.

Acceptance criteria:

- Plugin does not fatal if WooCommerce inactive.
- Admin sees clear notice: WooCommerce is required.

### Phase 0.4: Add Settings Skeleton

Files to add:

```text
includes/class-hwa-settings.php
```

Tasks:

- Register settings under `WooCommerce → Settings → Hyper Web Auth`.
- Register `hwa_settings` option.
- Add basic sections:
  - General
  - Google OAuth
  - Firebase Phone Auth placeholder
  - Security
- Implement sanitization callback.

Acceptance criteria:

- Admin can save settings.
- Settings are sanitized.
- No secrets are printed back in plain text unnecessarily.

### Phase 0.5: Add Database Installer

Files to add:

```text
includes/class-hwa-database.php
```

Tasks:

- Implement `create_tables()` with `dbDelta()`.
- Create `hwa_identities`, `hwa_oauth_states`, and `hwa_auth_logs`.
- Do not create the old `hwa_otp_challenges` table.
- Add DB version option: `hwa_db_version`.
- Call from activator.

Acceptance criteria:

- Tables are created on activation.
- Re-activation does not duplicate or break tables.
- DB version is stored.

### Phase 0.6: Add Utility/Security Helpers

Files to add:

```text
includes/class-hwa-security.php
```

Tasks:

- Add `hash_identity( string $value ): string` using HMAC with WordPress salts.
- Add `hash_phone( string $phone_e164 ): string`.
- Add `hash_ip()`.
- Add `safe_redirect_url()` allowlisting.
- Add `get_client_ip()` carefully.
- Add `normalize_phone()` and `is_valid_phone()` early enough for Firebase preflight checks.

Acceptance criteria:

- Identity hashing is deterministic.
- Phone normalization is deterministic.
- No raw identity is required for lookup except normalized source.

---

## Phase 1: Google Social OAuth Authentication

Goal: Implement production-ready Google registration/login before Firebase Phone Auth exists.

This phase must complete fully before beginning Firebase Phone Auth.

### Phase 1.1: Google OAuth Dependency Strategy

Preferred implementation:

```text
Use a lightweight Google OAuth/OpenID Connect service using WordPress HTTP APIs plus safe JWT/JWK token validation.
Do not blindly trust decoded JWT payloads.
```

Fallback:

```text
If safe ID-token validation cannot be implemented confidently, use a well-maintained library or the official Google client.
```

Tasks:

- Add Composer support if a token-validation library is used.
- Require Composer autoload safely if present.
- If required dependency is missing, show admin notice instead of fatal error.

Acceptance criteria:

- Plugin loads with dependency installed.
- Plugin shows helpful admin notice if dependency is missing.
- ID token validation is server-side and safe.

### Phase 1.2: Google OAuth Settings

Tasks:

- Add settings fields:
  - Enable Google login
  - Client ID
  - Client Secret
  - Redirect URI display/copy field
  - Auto-create WooCommerce customer
  - Match existing verified email, disabled by default
- Compute redirect URI automatically:

```text
/wp-json/hyper-web-auth/v1/google/callback
```

Recommended route:

```text
https://example.com/wp-json/hyper-web-auth/v1/google/callback
```

Acceptance criteria:

- Admin can configure Google credentials.
- Redirect URI is visible and copyable.
- Client secret field does not expose value unnecessarily.

### Phase 1.3: Add Identity Repository

Files to add:

```text
includes/class-hwa-identity-repository.php
```

Methods:

```php
find_by_provider_identity( string $provider, string $raw_identity )
find_google_identity( string $google_sub )
create_google_identity( int $user_id, string $google_sub, string $email, bool $verified )
update_last_login( int $identity_id )
identity_exists_for_user( int $user_id, string $provider )
find_user_google_identity( int $user_id )
find_firebase_phone_by_phone( string $phone_e164 ) // implemented in Phase 2
find_firebase_phone_by_uid( string $firebase_uid ) // implemented in Phase 2
```

Rules:

- Store hashed Google `sub` in `identity_hash`.
- Store email only as supporting display/context.
- Unique key must prevent duplicate provider identity.

Acceptance criteria:

- Same Google `sub` cannot be linked twice.
- Repository methods use prepared SQL.

### Phase 1.4: Add OAuth State Service/Repository

Files to add:

```text
includes/class-hwa-oauth-state-repository.php
```

Methods:

```php
create_state( string $provider, string $context, string $return_to, ?int $user_id ): string
consume_state( string $state ): ?array
cleanup_expired_states(): int
```

State payload contexts:

```text
login
register
link_google
```

Rules:

- Generate random state using `wp_generate_password()` or `random_bytes()`.
- Store only hashed state.
- Expire state within 10 minutes.
- Mark state consumed after successful callback.
- Reject reused state.

Acceptance criteria:

- Callback fails if state is missing, expired, invalid, or reused.
- State contains enough context to distinguish login/register/linking.

### Phase 1.5: Add Google OAuth Service

Files to add:

```text
includes/class-hwa-google-oauth-service.php
```

Responsibilities:

- Build Google authorization URL.
- Exchange authorization code for tokens.
- Validate ID token.
- Extract:
  - `sub`
  - `email`
  - `email_verified`
  - `name`
  - `given_name`
  - `family_name`
  - `picture`, if needed

Required methods:

```php
get_authorization_url( string $context, string $return_to = '', ?int $user_id = null ): string
handle_callback( string $code, string $state ): array
validate_id_token( string $id_token ): array
```

Validation rules:

- Validate issuer.
- Validate audience/client ID.
- Validate expiry.
- Require `sub`.
- Require verified email before matching existing customer by email.

Acceptance criteria:

- Invalid tokens are rejected.
- Authorization code and tokens are not logged.
- Google `sub` is used for identity matching.

### Phase 1.6: Add Customer Service

Files to add:

```text
includes/class-hwa-customer-service.php
```

Responsibilities:

- Find WooCommerce customer by email.
- Create WooCommerce customer from Google profile.
- Create WooCommerce customer from Firebase phone registration later.
- Login WooCommerce customer.
- Redirect after login.

Required methods:

```php
find_customer_by_email( string $email ): ?WP_User
create_customer_from_google_profile( array $google_profile ): int
create_customer_from_phone_registration( array $registration_data ): int
login_customer( int $user_id, bool $remember = true ): void
get_default_redirect_url( string $context ): string
```

Rules:

- Use `wc_create_new_customer()` where possible.
- Generate strong random password for Google-created customer.
- Set first name/last name if available.
- Set role/customer behavior through WooCommerce helpers.
- Do not create duplicate customers if email already exists and matching is disabled.

Acceptance criteria:

- New Google account creates WooCommerce customer.
- Existing verified email match does not auto-link unless setting explicitly allows it.
- Already linked Google account logs in directly.

### Phase 1.7: Add REST Routes for Google

Files to add:

```text
rest/class-hwa-rest-controller.php
```

Routes:

```text
GET /wp-json/hyper-web-auth/v1/google/start
GET /wp-json/hyper-web-auth/v1/google/callback
```

`google/start` params:

```text
context = login|register|link_google
return_to = optional relative URL
```

`google/start` behavior:

- Validate Google is enabled.
- Validate context.
- If context is `link_google`, require logged-in user.
- Generate authorization URL.
- Redirect to Google.

`google/callback` behavior:

- Validate state.
- Exchange code.
- Validate ID token.
- Resolve user according to rules.
- Set auth cookie.
- Redirect to safe return URL.

Acceptance criteria:

- Google login route works from My Account login form.
- Google registration route works from registration form.
- Google callback handles errors with WooCommerce notices or safe query messages.

### Phase 1.8: Google Login/Register UI

Files to update:

```text
public/class-hyper-web-auth-public.php
public/partials/google-button.php
public/css/hyper-web-auth-public.css
```

Hooks:

```php
woocommerce_login_form_end
woocommerce_register_form_end
```

Behavior:

- Show “Continue with Google” on login form.
- Show “Sign up with Google” or “Continue with Google” on registration form.
- Use different `context` query value for login/register.

Acceptance criteria:

- Button appears on WooCommerce My Account login/register forms.
- Button does not appear for logged-in users.
- Button uses configured REST start route.

### Phase 1.9: Google Flow Rules

Implement exactly:

```text
Case A: Google sub already exists in hwa_identities
→ Login linked user.

Case B: Google sub does not exist, email_verified=true, email matches exactly one WP/WooCommerce customer, matching setting enabled
→ Link Google identity to that customer, then login.

Case C: Google sub does not exist, email_verified=true, email matches exactly one WP/WooCommerce customer, matching setting disabled
→ Do not silently link. Redirect with message asking customer to log in normally and link Google from My Account.

Case D: Google sub does not exist and no matching customer exists, auto-create enabled
→ Create WooCommerce customer, link Google identity, login.

Case E: Google sub does not exist and auto-create disabled
→ Redirect with error: account not found.

Case F: User is logged in and context=link_google
→ Link Google identity to current user only if not linked to another user.
```

Acceptance criteria:

- No duplicate account is created for an already-linked Google user.
- No Google identity can link to two accounts.
- New Google customer is registered and logged in.
- Existing linked Google customer using registration form is logged in directly.

### Phase 1.10: Phase 1 Production Hardening

Tasks:

- Add audit logs for Google events.
- Add error handling and admin debug toggle.
- Add admin notices for missing settings.
- Add safe redirects.
- Add cleanup for expired OAuth states.
- Test on staging with real Google OAuth credentials.

Acceptance criteria:

- Google OAuth is production-stable before Phase 2 starts.
- All Google auth errors fail closed.
- No token/code/secret appears in logs.

---

## Phase 2: Firebase Phone Authentication

Goal: After Google is production-stable, implement phone registration/login through Firebase Authentication Phone Number Sign-In.

This phase replaces the old custom SMS OTP plan.

### Phase 2.1: Firebase Project Setup Requirements

Before coding production behavior, the agent must document and support these Firebase Console prerequisites:

```text
Firebase project exists
Authentication enabled
Phone provider enabled
Web app created in Firebase project
Production domain added to Firebase authorized domains
Staging domain added to Firebase authorized domains
Development/test phone numbers configured for staging
Billing/quota considerations understood
Privacy/consent language prepared
```

Acceptance criteria:

- Settings page clearly states required Firebase Console setup.
- Admin can see/copy the site domain to add to Firebase authorized domains.

### Phase 2.2: Firebase Backend Dependency Strategy

Preferred backend implementation:

```text
Use kreait/firebase-php to verify Firebase ID tokens server-side.
```

Composer command:

```bash
composer require kreait/firebase-php
```

Tasks:

- Add dependency through Composer.
- Load Composer autoload safely.
- If `vendor/` or Kreait classes are missing, show admin notice.
- Support service-account configuration by secure path or constant.
- Never expose service-account JSON/private key in HTML or JS.

Acceptance criteria:

- Backend can initialize Firebase Auth service.
- Missing service-account configuration fails closed with safe admin notice.

### Phase 2.3: Firebase Phone Settings

Settings fields:

```text
Enable Firebase Phone Auth
Enable phone registration
Firebase API Key
Firebase Auth Domain
Firebase Project ID
Firebase App ID
Firebase Messaging Sender ID
Firebase Measurement ID, optional
Firebase Tenant ID, optional
Firebase service-account path or constant override
Default country code, default +91
reCAPTCHA mode: invisible or visible
Privacy/consent message text
```

Constants override support:

```php
HWA_FIREBASE_API_KEY
HWA_FIREBASE_AUTH_DOMAIN
HWA_FIREBASE_PROJECT_ID
HWA_FIREBASE_APP_ID
HWA_FIREBASE_MESSAGING_SENDER_ID
HWA_FIREBASE_SERVICE_ACCOUNT_PATH
HWA_FIREBASE_SERVICE_ACCOUNT_JSON
```

Acceptance criteria:

- Admin can configure Firebase frontend config.
- Admin can configure backend token verification credentials securely.
- Service-account secrets are not printed back to the page.

### Phase 2.4: Add Firebase Auth Service

Files to add:

```text
includes/class-hwa-firebase-auth-service.php
```

Responsibilities:

- Initialize Firebase Auth backend client.
- Verify Firebase ID tokens.
- Extract verified claims:
  - Firebase UID / `sub`
  - phone number
  - issuer
  - audience/project ID
  - auth time, issued at, expiry
- Optionally retrieve Firebase user record by UID when needed.
- Normalize phone from token.

Required methods:

```php
verify_id_token( string $id_token ): array
get_uid_from_verified_token( array $verified_claims ): string
get_phone_from_verified_token( array $verified_claims ): string
assert_phone_matches_expected( string $token_phone, string $expected_phone_e164 ): void
```

Rules:

- Reject invalid, expired, wrongly signed, wrong-project, or missing-phone tokens.
- Do not log the raw ID token.
- Do not trust a submitted phone unless it matches the verified token phone.

Acceptance criteria:

- Valid Firebase ID token verifies.
- Invalid/expired token fails.
- Token from wrong project fails.
- Missing phone number fails for phone auth routes.

### Phase 2.5: Add Phone Normalization Helper

Files to update:

```text
includes/class-hwa-security.php
```

Tasks:

- Add `normalize_phone()`.
- Add `is_valid_phone()`.
- Prefer E.164 format.
- Support configured default country code.

Rules:

- If phone starts with `+`, treat as E.164-like input and validate.
- If phone does not start with `+`, apply configured default country code.
- Store normalized phone in `phone_e164`.
- Store HMAC hash in `phone_hash`.

Acceptance criteria:

- Same phone normalizes consistently.
- Invalid phone numbers are rejected before Firebase flow starts.
- Default `+91` works for Indian 10-digit numbers.

### Phase 2.6: Add Rate Limiter / Preflight Guard

Files to add/update:

```text
includes/class-hwa-rate-limiter.php
```

Rules:

- Limit Firebase phone-auth start attempts by phone hash.
- Limit Firebase phone-auth start attempts by IP hash.
- Limit Firebase token verification failures by IP hash.
- Add cooldown before allowing another Firebase SMS start attempt.

Important:

```text
Firebase has its own quota/anti-abuse controls, but WordPress should still preflight business rules and rate-limit abuse before allowing the frontend to call Firebase.
```

Acceptance criteria:

- Attacker cannot hammer the plugin preflight endpoints indefinitely.
- Repeated verification failures are blocked temporarily.

### Phase 2.7: Phone Identity Repository Methods

Update:

```text
includes/class-hwa-identity-repository.php
```

Add methods:

```php
find_firebase_phone_by_phone( string $phone_e164 )
find_firebase_phone_by_uid( string $firebase_uid )
create_firebase_phone_identity( int $user_id, string $firebase_uid, string $phone_e164, bool $verified = true )
find_user_firebase_phone_identity( int $user_id )
update_firebase_phone_last_login( int $identity_id )
```

Rules:

- Same Firebase UID cannot be linked to multiple users.
- Same phone number cannot be linked to multiple users.
- Phone lookup must use normalized E.164 phone and phone hash.

Acceptance criteria:

- Existing phone lookup works reliably.
- Duplicate phone/Firebase UID linking is prevented.

### Phase 2.8: Firebase Phone REST Routes

Routes:

```text
POST /wp-json/hyper-web-auth/v1/firebase-phone/login/preflight
POST /wp-json/hyper-web-auth/v1/firebase-phone/login/complete
POST /wp-json/hyper-web-auth/v1/firebase-phone/register/preflight
POST /wp-json/hyper-web-auth/v1/firebase-phone/register/complete
```

Why preflight/complete?

```text
Preflight checks business rules before Firebase sends SMS.
Complete verifies Firebase ID token after Firebase confirms OTP.
```

### Phase 2.9: Phone Login Preflight

Login preflight behavior:

```text
Input: phone
Normalize phone
Check rate limit
Check identity table

Phone exists + verified:
    return success and allow frontend to start Firebase Phone Auth

Phone does not exist:
    return code phone_not_found and redirect/register instruction

Phone exists but inactive/unverified/conflict:
    return safe error
```

Required error message:

```text
Phone number does not exist. Please sign up.
```

Acceptance criteria:

- Existing phone can proceed to Firebase SMS.
- Unknown phone cannot trigger Firebase SMS from login flow.
- Unknown phone receives registration redirect instruction.

### Phase 2.10: Phone Login Complete

Login complete behavior:

```text
Input: phone, firebase_id_token, return_to
Normalize expected phone
Verify Firebase ID token server-side
Extract Firebase UID and token phone number
Confirm token phone matches expected phone
Find firebase_phone identity by phone
Find linked WooCommerce user
Login linked user
Redirect to My Account or safe return URL
```

Acceptance criteria:

- Valid Firebase token logs in linked customer.
- Token phone mismatch fails.
- Token from wrong Firebase project fails.
- Unknown phone cannot create account through login endpoint.

### Phase 2.11: Phone Registration Preflight

Registration preflight behavior:

```text
Input: phone
Normalize phone
Check rate limit
Check identity table

Phone does not exist:
    return success and allow frontend to start Firebase Phone Auth

Phone already exists:
    return code phone_exists and redirect/login instruction
```

Required error message:

```text
Phone number already exists. Please login.
```

Acceptance criteria:

- New phone can proceed to Firebase SMS.
- Existing phone cannot trigger registration Firebase SMS.
- Existing phone receives login redirect instruction.

### Phase 2.12: Phone Registration Complete

Registration complete behavior:

```text
Input: phone, firebase_id_token, email, first_name, last_name, return_to
Normalize expected phone
Verify Firebase ID token server-side
Extract Firebase UID and token phone number
Confirm token phone matches expected phone
Check phone still does not exist
Check Firebase UID still does not exist
Validate required email/name fields
Create WooCommerce customer
Create firebase_phone identity
Login customer
Redirect to My Account or safe return URL
```

Recommended registration fields:

```text
phone
email
first_name
last_name
```

Rules:

- Email is required for initial production version.
- Do not generate fake emails in initial version.
- Do not create WooCommerce customer until Firebase ID token is verified.

Acceptance criteria:

- New phone can register.
- Existing phone is redirected to login.
- Valid Firebase token creates customer and logs in.

### Phase 2.13: Firebase Phone Frontend UI

Files:

```text
public/partials/phone-login-form.php
public/partials/phone-register-form.php
public/js/hyper-web-auth-public.js
public/css/hyper-web-auth-public.css
```

Login form UI:

```text
Phone number
Continue with Phone
Firebase reCAPTCHA container
OTP input rendered by JS after Firebase sends SMS
Verify and Login
```

Register form UI:

```text
Phone number
Email
First name
Last name
Continue with Phone
Firebase reCAPTCHA container
OTP input rendered by JS after Firebase sends SMS
Verify and Register
```

JS responsibilities:

- Initialize Firebase app from localized config.
- Initialize Firebase Auth.
- Initialize `RecaptchaVerifier`.
- Call login/register preflight endpoint before calling Firebase.
- Call Firebase `signInWithPhoneNumber()`.
- Store confirmation result in memory only.
- Confirm OTP code entered by user.
- Get Firebase ID token from the signed-in Firebase user.
- Submit Firebase ID token to WordPress complete endpoint.
- Handle redirect instructions.
- Display messages.
- Avoid duplicate submissions.

Acceptance criteria:

- Firebase phone login/register UX works without a full-page reload where practical.
- Phone SMS is not attempted before WordPress preflight approves the context.
- Firebase config is only printed on pages where phone auth is active.

### Phase 2.14: Phone Flow Rules

Implement exactly:

```text
Phone login:
- Phone exists and verified → allow Firebase Phone Auth → verify Firebase ID token backend → login.
- Phone does not exist → redirect/register message: “Phone number does not exist. Please sign up.”
- Phone conflict/multiple records → block and show support message.

Phone registration:
- Phone does not exist → allow Firebase Phone Auth → verify Firebase ID token backend → create customer → link Firebase phone identity → login.
- Phone already exists → redirect/login message: “Phone number already exists. Please login.”
```

Acceptance criteria:

- Login endpoint never creates new user.
- Registration endpoint never logs in existing phone without redirecting to login.
- Firebase token verification is required before any WooCommerce auth cookie is set.

### Phase 2.15: Phase 2 Production Hardening

Tasks:

- Add audit logs for Firebase phone events.
- Add admin diagnostics for missing Firebase config.
- Add masked phone display in logs/UI.
- Add rate-limit messages.
- Test with Firebase test phone numbers on staging.
- Test with real phone numbers on production/staging domain after authorized-domain setup.

Acceptance criteria:

- Firebase Phone Auth is production-stable before Phase 3 starts.
- Phone auth abuse is meaningfully limited.
- No Firebase ID token/service-account credential appears in logs.

---

## Phase 3: Combined Google + Firebase Phone Account Linking

Goal: Once Google and Firebase Phone Auth work independently, combine them into one coherent multi-identity customer system.

### Phase 3.1: Add My Account Endpoint

Files to add:

```text
public/class-hwa-my-account.php
public/partials/account-login-methods.php
```

Endpoint:

```text
/my-account/login-methods/
```

Hooks:

```php
add_action( 'init', array( $this, 'add_endpoint' ) );
add_filter( 'woocommerce_account_menu_items', array( $this, 'add_menu_item' ) );
add_action( 'woocommerce_account_login-methods_endpoint', array( $this, 'render_page' ) );
```

Activation/deactivation:

- Register endpoint before flushing rewrite rules.
- Flush rewrite rules on activation/deactivation.

Acceptance criteria:

- Logged-in customer can access My Account > Login Methods.
- Endpoint does not 404 after activation.

### Phase 3.2: Login Methods Page UI

Display:

```text
Google Login
Status: Linked / Not Linked
Action: Link Google / Unlink Google

Phone Login
Status: Linked / Not Linked
Action: Link Phone / Change Phone
```

Rules:

- Show masked phone number.
- Show masked Google email if available.
- Do not show raw identity hashes.
- Do not show Firebase UID.

Acceptance criteria:

- Customer sees accurate linked/unlinked status.

### Phase 3.3: Link Google for Logged-In Phone Customer

Flow:

```text
User logged in
Clicks Link Google
Redirect to Google OAuth with context=link_google
Validate callback
Check Google sub is not linked to another user
Link Google to current user
Redirect to /my-account/login-methods/
```

Rules:

- If Google identity already linked to current user: show already linked.
- If Google identity linked to another user: block.

Acceptance criteria:

- Phone-registered user can add Google login.
- Google already linked elsewhere cannot be stolen.

### Phase 3.4: Link Phone for Logged-In Google Customer

Routes:

```text
POST /wp-json/hyper-web-auth/v1/firebase-phone/link/preflight
POST /wp-json/hyper-web-auth/v1/firebase-phone/link/complete
```

Rules:

- Require logged-in user.
- If phone linked to another user: block.
- If phone linked to current user: show already linked.
- If phone unlinked: allow Firebase Phone Auth.
- After Firebase ID-token verification and phone match: link phone to current user.

Acceptance criteria:

- Google-registered user can add phone login.
- Phone already linked elsewhere cannot be stolen.

### Phase 3.5: Combined Login/Registration Conflict Rules

Implement final global behavior:

```text
Google login/register:
- Google sub linked → login.
- Google sub unlinked + verified email matches one user → link and login only if setting enabled.
- Google sub unlinked + verified email matches one user and setting disabled → ask user to log in normally and link from My Account.
- Google sub unlinked + no matching user → create user and login if auto-create enabled.
- Google sub linked to another user during logged-in linking → block.

Firebase phone login:
- Phone linked → allow Firebase Phone Auth → verify backend token → login.
- Phone unlinked → redirect/register message.

Firebase phone registration:
- Phone unlinked → allow Firebase Phone Auth → verify backend token → create customer → link phone → login.
- Phone linked → redirect/login message.

My Account linking:
- Identity unlinked → verify provider ownership → link to current user.
- Identity linked to same user → show already linked.
- Identity linked to another user → block.
```

Acceptance criteria:

- No accidental duplicate accounts.
- No account takeover through linking.

### Phase 3.6: Optional Unlinking Rules

Unlinking is risky. Implement only after linking is stable.

Recommended rule:

- Do not allow unlinking the only login method unless the account has a password or another linked identity.
- Require reauthentication or nonce-confirmation for unlinking.
- Log unlink events.

Acceptance criteria:

- Customer cannot lock themselves out.

### Phase 3.7: Checkout Integration

Optional after My Account linking.

Hooks:

```text
woocommerce_before_checkout_form
woocommerce_checkout_process
```

Possible behavior:

- Show Google login button on checkout login prompt.
- Show Firebase phone login on checkout login prompt.
- Return customer to checkout after successful login.

Acceptance criteria:

- Checkout login works and returns to checkout.
- No cart/session loss.

---

## Phase 4: Admin, Observability, and Production Operations

Goal: Make the plugin maintainable for real client production use.

### Phase 4.1: Admin Audit Log Viewer

Tasks:

- Add table/list view for auth logs.
- Filter by event type, provider, status, user.
- Mask phone/email values.

Acceptance criteria:

- Admin can debug login issues without exposing secrets.

### Phase 4.2: Identity Management Admin Tools

Tasks:

- On user profile page, show linked identities.
- Allow admin to unlink identity only with capability check and confirmation.
- Do not allow arbitrary reassignment without a dedicated migration tool.

Acceptance criteria:

- Admin can resolve legitimate support cases safely.

### Phase 4.3: Cron Cleanup

Tasks:

- Schedule cleanup for:
  - expired OAuth states
  - old phone auth attempts, if optional attempt table is used
  - old audit logs if retention setting enabled

Acceptance criteria:

- Tables do not grow endlessly.

### Phase 4.4: Privacy and Data Export/Delete

Tasks:

- Consider WordPress personal data export/erase hooks.
- Export linked identity metadata safely.
- Erase plugin-specific identity rows if user is deleted.
- Add clear privacy disclosure for Firebase phone authentication.

Acceptance criteria:

- Plugin is compatible with privacy workflows.

---

# 9. Required REST Route Summary

## Phase 1 Google Routes

```text
GET /wp-json/hyper-web-auth/v1/google/start
GET /wp-json/hyper-web-auth/v1/google/callback
```

## Phase 2 Firebase Phone Routes

```text
POST /wp-json/hyper-web-auth/v1/firebase-phone/login/preflight
POST /wp-json/hyper-web-auth/v1/firebase-phone/login/complete
POST /wp-json/hyper-web-auth/v1/firebase-phone/register/preflight
POST /wp-json/hyper-web-auth/v1/firebase-phone/register/complete
```

## Phase 3 Linking Routes

```text
POST /wp-json/hyper-web-auth/v1/firebase-phone/link/preflight
POST /wp-json/hyper-web-auth/v1/firebase-phone/link/complete
GET  /wp-json/hyper-web-auth/v1/google/start?context=link_google
GET  /wp-json/hyper-web-auth/v1/google/callback
```

---

# 10. Hook Summary

## Admin Hooks

```php
admin_menu
admin_init
admin_enqueue_scripts
woocommerce_get_settings_pages or WooCommerce settings-section filters/actions
```

## REST Hooks

```php
rest_api_init
```

## WooCommerce Public Hooks

```php
woocommerce_login_form_end
woocommerce_register_form_end
woocommerce_before_customer_login_form
woocommerce_after_customer_login_form
woocommerce_before_checkout_form
```

## My Account Hooks

```php
init
woocommerce_account_menu_items
woocommerce_account_login-methods_endpoint
```

## Activation/Deactivation

```php
register_activation_hook
register_deactivation_hook
```

---

# 11. Error Message and Redirect Contract

REST errors should return structured payloads.

Example:

```json
{
  "success": false,
  "code": "phone_not_found",
  "message": "Phone number does not exist. Please sign up.",
  "redirect": "https://example.com/my-account/?hwa_form=register"
}
```

Common codes:

```text
google_disabled
google_config_missing
google_invalid_state
google_invalid_token
google_account_conflict
google_auto_create_disabled
google_email_match_requires_login
firebase_phone_disabled
firebase_config_missing
firebase_token_missing
firebase_token_invalid
firebase_phone_missing
firebase_phone_mismatch
firebase_uid_conflict
phone_invalid
phone_not_found
phone_exists
phone_auth_preflight_allowed
phone_auth_rate_limited
identity_conflict
not_logged_in
```

User-facing messages should be translatable.

---

# 12. Testing Strategy

## Phase 1 Google Tests

Manual tests:

- Google button appears on login form.
- Google button appears on registration form.
- New Google user creates WooCommerce customer.
- Same Google user logs in on next attempt.
- Existing customer with same verified email is not silently linked unless setting is enabled.
- Invalid state is rejected.
- Reused state is rejected.
- Missing credentials produce safe admin/user error.
- Logged-in user can link Google in Phase 3.

## Phase 2 Firebase Phone Tests

Manual tests:

- Firebase SDK loads only on relevant pages.
- reCAPTCHA container renders correctly.
- Firebase test phone numbers work on staging.
- Production domain is authorized in Firebase.
- Unknown phone login redirects to registration message before Firebase SMS starts.
- Existing phone login passes preflight and starts Firebase phone auth.
- Valid Firebase ID token logs in linked customer.
- Token phone mismatch fails.
- Token from wrong Firebase project fails.
- Existing phone registration redirects to login message before Firebase SMS starts.
- New phone registration creates customer only after Firebase ID token verification.
- Firebase quota/error responses show safe UI messages.
- Rate limit blocks repeated preflight attempts.

## Phase 3 Linking Tests

Manual tests:

- Phone-registered user links Google.
- Google-registered user links phone using Firebase Phone Auth.
- Google linked to another user cannot be linked.
- Phone linked to another user cannot be linked.
- Linked identities display correctly in My Account.
- User cannot unlink only login method if unlink feature exists.

---

# 13. Security Checklist

Before production:

- [ ] Google ID token is validated server-side.
- [ ] Google `sub` is used as primary identity key.
- [ ] OAuth state is single-use and expires.
- [ ] Safe redirect allowlist is enforced.
- [ ] No secrets/tokens/codes/Firebase ID tokens are logged.
- [ ] Firebase frontend config is localized only when needed.
- [ ] Firebase service-account credentials are never exposed to frontend.
- [ ] Firebase ID token is verified server-side before WooCommerce login/register/link.
- [ ] Firebase token phone number matches expected phone input.
- [ ] Phone login does not create accounts.
- [ ] Phone registration does not login existing accounts directly.
- [ ] Identity table has unique provider identity constraint.
- [ ] Phone identity uniqueness is enforced.
- [ ] My Account linking requires logged-in user.
- [ ] Identity conflict blocks account takeover.
- [ ] Admin settings are capability-protected.
- [ ] Public outputs are escaped.
- [ ] Inputs are sanitized and validated.
- [ ] Database queries use prepared statements.
- [ ] WooCommerce inactive state is handled gracefully.
- [ ] Firebase authorized domains are configured for staging and production.
- [ ] Firebase test phone numbers are not used as production customer shortcuts.

---

# 14. Recommended Initial Agent Task Sequence

Use this exact order for the first development sprint.

1. Add plugin constants.
2. Add WooCommerce dependency notice.
3. Add settings skeleton under WooCommerce settings.
4. Add database class and create `hwa_identities`, `hwa_oauth_states`, `hwa_auth_logs`.
5. Add security helper class.
6. Add identity repository.
7. Add OAuth state repository.
8. Add Google OAuth settings UI.
9. Add Google token-validation dependency strategy.
10. Add Google OAuth service.
11. Add customer service.
12. Add REST controller with Google start/callback.
13. Add Google button to WooCommerce login/register forms.
14. Test Google new registration.
15. Test Google existing login.
16. Test Google verified email matching behavior with setting disabled and enabled.
17. Harden errors, redirects, and logs.
18. Stop. Do not begin Firebase Phone Auth until Phase 1 is production-stable.

Second sprint after Phase 1:

1. Add Firebase Phone Auth settings.
2. Add Kreait/Firebase backend verification dependency.
3. Add `HWA_Firebase_Auth_Service`.
4. Add Firebase frontend SDK integration.
5. Add phone normalization and preflight routes.
6. Add login/register complete routes.
7. Add phone identity repository methods.
8. Add phone login/register UI.
9. Test with Firebase test numbers.
10. Test with real domain and production authorized domain.
11. Harden token verification, rate limits, and logs.
12. Stop. Do not begin account linking until Firebase Phone Auth is production-stable.

---

# 15. Final Definition of Done

The full plugin is complete when:

- Google OAuth registration/login works in production.
- Firebase Phone Authentication registration/login works in production.
- Firebase ID tokens are verified server-side before WooCommerce login/register/link.
- Customers can link Google and phone identities from My Account.
- Duplicate accounts are prevented as much as possible.
- Linked identities cannot be hijacked by another account.
- Admins can configure Google/Firebase providers without editing code.
- Admins can inspect masked logs.
- Expired OAuth states and old optional attempt records are cleaned.
- The plugin behaves safely when WooCommerce is inactive.
- The plugin does not expose secrets, tokens, service-account credentials, or identity hashes in UI/logs.
- The plugin zip excludes `.git/`, development artifacts, and local secrets.

---

## 16. Official Implementation References

Use official documentation while implementing:

- Google OpenID Connect: https://developers.google.com/identity/openid-connect/openid-connect
- Google ID token verification: https://developers.google.com/identity/gsi/web/guides/verify-google-id-token
- Firebase Web Phone Authentication: https://firebase.google.com/docs/auth/web/phone-auth
- Firebase Admin ID Token Verification: https://firebase.google.com/docs/auth/admin/verify-id-tokens
- Firebase Admin SDK for PHP / Kreait: https://firebase-php.readthedocs.io/
- WordPress REST custom endpoints: https://developer.wordpress.org/rest-api/extending-the-rest-api/adding-custom-endpoints/
- WordPress `register_rest_route()`: https://developer.wordpress.org/reference/functions/register_rest_route/
- WordPress plugin tables and `dbDelta()`: https://developer.wordpress.org/plugins/creating-tables-with-plugins/
- WordPress `dbDelta()`: https://developer.wordpress.org/reference/functions/dbdelta/
- WordPress rewrite endpoints: https://developer.wordpress.org/reference/functions/add_rewrite_endpoint/
- WooCommerce endpoints: https://developer.woocommerce.com/docs/best-practices/urls-and-routing/woocommerce-endpoints/
- WooCommerce customer functions: https://woocommerce.github.io/code-reference/files/woocommerce-includes-wc-user-functions.html
