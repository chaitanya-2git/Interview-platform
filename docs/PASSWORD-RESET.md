# Candidate password reset (Forgot password)

Candidates sign in with their phone number, so "Forgot password?" proves ownership of that number with an SMS one-time code from **Firebase Phone Authentication**. Firebase is used only for that proof; the application keeps its own accounts, sessions and password hashes.

## Flow

1. `/login` → **Forgot password?** → `/forgot-password`.
2. The candidate enters the registered number. `POST /forgot-password/lookup` normalizes it (same rules as registration) and checks that an active candidate uses it, so no SMS is spent on unknown numbers. Limits: 20 lookups per IP per 15 minutes, 5 per number per hour.
3. The browser (`public/assets/forgot-password.js`, Firebase JS SDK 10.14.1 from gstatic) runs an invisible reCAPTCHA and asks Firebase to send the code, then confirms the code and gets a Firebase **ID token**.
4. `POST /forgot-password/verify` sends the token. `FirebaseTokenVerifier` checks the RS256 signature against Google's published certificates (cached in `storage/cache/`), `aud`/`iss` = project, expiry, `auth_time` within 10 minutes and `sign_in_provider = phone`. The token's `phone_number` must belong to an active candidate.
5. The session gets a 10-minute reset grant bound to that user and phone; the session ID is regenerated.
6. `/reset-password` sets the new password (12–72 bytes, typed twice). The grant is single use. Audit events: `user.password_reset_verified`, `user.password_reset`.

Administrators do not use this flow; their passwords are reset by operations (`php bin/console.php admin:create` style provisioning).

## Configuration

`.env`:

```
FIREBASE_API_KEY=...
FIREBASE_AUTH_DOMAIN=<project-id>.firebaseapp.com
FIREBASE_PROJECT_ID=<project-id>
```

These are Firebase **web** config values and are public by design. Leave them empty to hide self-service reset (the page then tells candidates to contact an administrator).

The `/forgot-password` page relaxes the Content Security Policy for Google origins (gstatic, google.com, recaptcha.net, identitytoolkit/securetoken APIs, the auth domain). Every other page keeps the strict policy from `public/index.php`.

The server must be able to make outbound HTTPS requests to `www.googleapis.com` (signing keys) and needs the `openssl` extension.

## Firebase project (current: `blujay-interviews`)

Configured in the Firebase console:

- Authentication → Sign-in method → **Phone: enabled**.
- Test number for development: `+91 99999 00001`, code `123456` (no real SMS is sent).
- Authentication → Settings → **SMS region policy: Allow India (IN)**. New projects block SMS until a region is allowed (`auth/operation-not-allowed`). Add other countries here if candidates use foreign numbers.
- Authentication → Settings → **Authorised domains**: `localhost`, `blujaytech.com`.
- Billing plan: Spark (free). Firebase limits new Spark projects to **10 SMS per day**. For real traffic, link a billing account (Blaze plan) in the Firebase console; phone verification SMS are then charged per message.

Recommended hardening: in Google Cloud → APIs & Services → Credentials, restrict the browser API key to HTTP referrers `https://blujaytech.com/*` and `http://localhost:8000/*`.
