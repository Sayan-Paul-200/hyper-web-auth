# HyperWeb Auth — Updated Open Questions and Decisions

This document updates the previous implementation questions after the decision to use **Firebase Authentication Phone Number Sign-In** for Phone SMS OTP.

---

## Final Decisions Summary

| Topic | Decision |
|---|---|
| Google client library | Use lightweight Google OAuth/OIDC with safe JWT/JWK validation; fall back to official/maintained library if needed |
| Phone SMS OTP provider | Use Firebase Authentication Phone Number Sign-In |
| Custom SMS provider | Do not build in initial implementation |
| Custom OTP storage | Do not build in initial implementation |
| Backend Firebase verification | Use `kreait/firebase-php` or equivalent reliable Firebase token verification |
| Default country code | Admin-configurable, default `+91` |
| Settings page location | `WooCommerce → Settings → Hyper Web Auth` |
| Email for phone registration | Required in initial production version |
| Google existing-email match | Do not silently auto-link by default; setting disabled by default |
| Checkout integration | Defer until Google, Firebase Phone Auth, and My Account linking are stable |
| PHP minimum | PHP `8.0+`; recommend `8.1+` / ideally `8.3+` |

---

## 1. Google Client Library vs Lightweight HTTP

### Decision

Use a lightweight Google OAuth/OpenID Connect implementation using WordPress HTTP APIs plus safe JWT/JWK validation.

Do not use the full `google/apiclient` initially unless safe validation cannot be implemented confidently.

### Agent instruction

```text
Implement Google OAuth/OIDC with wp_remote_get/wp_remote_post and safe server-side ID-token validation.
Do not blindly trust decoded JWT payloads.
Validate issuer, audience/client ID, expiry, signature, email_verified, and Google sub.
Cache public JWKs with transients if implementing JWK validation directly.
If safe validation cannot be implemented confidently, use a maintained library or official Google client.
```

---

## 2. SMS Provider

### Previous question

Which SMS gateway should be used: Twilio, MSG91, Fast2SMS, custom Indian gateway, or generic HTTP provider?

### Updated decision

Use **Firebase Authentication Phone Number Sign-In**.

No Twilio, MSG91, Fast2SMS, Textlocal, custom SMS gateway, or generic HTTP SMS provider should be implemented in the first production version.

### Agent instruction

```text
Do not build HWA_SMS_Provider.
Do not build custom SMS API settings.
Do not generate OTPs in WordPress.
Do not store raw or hashed OTPs in WordPress.
Do not build hwa_otp_challenges for the initial Firebase implementation.
Firebase will send and verify SMS OTPs on the frontend.
WordPress must verify the Firebase ID token server-side before logging in, registering, or linking a WooCommerce customer.
```

---

## 3. Firebase Phone Auth Architecture

### Decision

Use Firebase Web SDK on the frontend and Firebase Admin/PHP verification on the backend.

### Required flow

```text
Customer enters phone number
↓
WordPress preflight endpoint checks context/business rules
↓
If allowed, frontend starts Firebase Phone Auth
↓
Firebase handles reCAPTCHA and sends SMS OTP
↓
Customer enters OTP
↓
Firebase confirms OTP and signs in Firebase user
↓
Frontend gets Firebase ID token
↓
Frontend sends ID token to WordPress complete endpoint
↓
WordPress verifies ID token server-side
↓
WordPress confirms token phone matches expected phone
↓
WordPress logs in/registers/links WooCommerce customer
```

### Agent instruction

```text
Create HWA_Firebase_Auth_Service.
Use kreait/firebase-php or another reliable Firebase token verification library.
Verify Firebase ID tokens server-side.
Extract Firebase UID and verified phone number from token claims.
Reject invalid, expired, wrong-project, missing-phone, or mismatched-phone tokens.
Never trust frontend Firebase success directly.
```

---

## 4. Default Country Code

### Decision

Make it admin-configurable. Default value: `+91`.

### Agent instruction

```text
Add setting firebase_default_country_code with default +91.
If phone starts with +, treat as E.164-like input and validate.
If phone does not start with +, apply the configured default country code.
Store phones internally as normalized E.164.
Use HMAC hash of normalized phone for lookup.
```

---

## 5. Settings Page Location

### Decision

Use:

```text
WooCommerce → Settings → Hyper Web Auth
```

### Agent instruction

```text
Implement final settings as WooCommerce-native settings.
A temporary standalone settings page is acceptable only during early development, but final target is WooCommerce → Settings → Hyper Web Auth.
```

Recommended sections:

```text
General
Google OAuth
Firebase Phone Auth
Security
Advanced / Debug
```

---

## 6. Email Requirement for Phone Registration

### Decision

Email is required for phone registration in the initial production version.

### Reason

WooCommerce customer flows depend heavily on email for account creation, order notifications, receipts, password reset, customer support, and admin search.

### Agent instruction

```text
Phone registration fields for initial production:
- phone number
- email
- first name
- last name

Do not generate fake emails.
Do not allow phone-only WooCommerce accounts in the initial implementation.
```

Optional future setting:

```text
Allow phone-only accounts: disabled by default
```

---

## 7. Google “Match Existing Email” Behavior

### Decision

Do not silently auto-link by default.

### Default behavior

```text
Google sub already linked:
    login directly

Google sub not linked and verified Google email is new:
    create customer and link Google

Google sub not linked and verified Google email matches existing customer:
    do not silently link by default
    ask customer to log in normally and link Google from My Account
```

### Optional admin setting

```text
Auto-link Google by verified email: disabled by default
```

If enabled:

```text
Only auto-link when email_verified = true
Only auto-link when exactly one WordPress/WooCommerce customer has that email
Log the event
```

---

## 8. Checkout Integration Priority

### Decision

Defer full checkout UI integration.

Initial production scope:

```text
WooCommerce My Account login
WooCommerce registration
Google login/register
Firebase phone login/register
My Account login-method linking
```

Later scope:

```text
Google login at checkout
Firebase phone login at checkout
Return-to-checkout preservation
Billing phone verification before order placement
```

### Agent instruction

```text
Do not implement checkout UI injection in Phase 1 or Phase 2 core.
Preserve safe return_to URLs so a customer who started from checkout can return there after login.
Full checkout integration is a later phase after login/register/linking are stable.
```

---

## 9. PHP Minimum Version

### Decision

Set minimum PHP to `8.0`.

Recommended production environment:

```text
PHP 8.1+
Ideally PHP 8.3+
Current WordPress
Current WooCommerce
```

### Agent instruction

```text
Set Requires PHP: 8.0.
Code for PHP 8.0+ compatibility.
Avoid PHP 8.1-only syntax unless the project confirms PHP 8.1 as minimum.
```

---

## 10. New Firebase-Specific Open Questions

These should be clarified during configuration or before production deployment, but they should not block the architecture.

### 10.1 Firebase project credentials

Required:

```text
Firebase API Key
Firebase Auth Domain
Firebase Project ID
Firebase App ID
Firebase service-account JSON/path/constant for backend verification
```

Question for project owner:

```text
Will the Firebase service-account credential be provided as a secure file path, environment variable, wp-config.php constant, or admin-uploaded file?
```

Recommendation:

```text
Use wp-config.php constants or a secure server file path. Avoid storing raw service-account JSON in wp_options when possible.
```

### 10.2 Firebase authorized domains

Question for project owner:

```text
What are the exact staging and production domains that must be added to Firebase authorized domains?
```

Recommendation:

```text
Add both staging and production domains in Firebase Console before testing real phone numbers.
```

### 10.3 Firebase test phone numbers

Question for project owner:

```text
Which test phone numbers/codes should be configured in Firebase for staging/development?
```

Recommendation:

```text
Use Firebase test phone numbers for development and staging to avoid SMS quota/cost issues.
Never treat test numbers as production customer shortcuts.
```

### 10.4 Privacy/consent language

Question for project owner:

```text
What privacy-policy wording should be shown before Firebase Phone Auth is used?
```

Recommendation:

```text
Add a short consent note near the phone auth form and update the site's privacy policy to mention Firebase/Google processing of phone authentication data.
```

---

## 11. Final Updated Clarification to Give the Agent

```text
We will use Firebase Authentication Phone Number Sign-In for phone SMS OTP.

Do not build a custom SMS provider, custom SMS API settings, custom OTP generator, custom OTP hash verification, or hwa_otp_challenges table in the initial version.

Use Firebase Web SDK on the frontend to send and confirm the SMS OTP. Use Firebase reCAPTCHA/RecaptchaVerifier as required by Firebase web phone auth.

After Firebase confirms the OTP, get the Firebase ID token from the Firebase user and send it to WordPress.

WordPress must verify the Firebase ID token server-side using kreait/firebase-php or another reliable Firebase token verification library.

Only after backend verification succeeds should WordPress create, log in, or link a WooCommerce customer.

The backend must confirm that the verified Firebase token phone number matches the expected normalized phone number submitted in the login/register/linking flow.

Keep the priority order:
1. Google OAuth first
2. Firebase Phone Auth second
3. Account linking third
4. Checkout integration later
```
