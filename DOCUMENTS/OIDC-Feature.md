## Summary

Adds native OpenID Connect (OIDC) authentication as an alternative to local username/password auth. Users can log in via an external OIDC provider (e.g., Keycloak, Auth0, Okta) with auto-provisioning of admin accounts and optional MFA enforcement.

Closes: (no existing issue — new feature)

## Features

- **OIDC login** — users click "Login with SSO" and authenticate via external provider
- **Auto-provisioning** — new admin accounts created automatically on first OIDC login (atomic, race-condition-free)
- **JWT validation** — uses `firebase/php-jwt` with JWKS key fetching and X.509 chain validation
- **CSRF protection** — state parameter prevents cross-site request forgery
- **Nonce verification** — prevents token replay attacks (nonce must be present and match)
- **Issuer validation** — ID token `iss` claim must match configured issuer URL
- **Audience validation** — ID token `aud` claim must match configured client ID
- **MFA enforcement** — optional MFA via IdP `amr` claim or local TOTP fallback
- **Configurable button text** — customize the SSO button label (e.g., "FrogPond.Cloud Keycloak")
- **Graceful fallback** — local password auth remains available as default
- **Active account check** — disabled admin accounts cannot log in via OIDC

## How it works

1. User clicks "Login with SSO" on login page
2. PostfixAdmin discovers OIDC provider configuration (`.well-known/openid-configuration`)
3. User is redirected to provider's authorization endpoint
4. After authentication, provider redirects back to `oidc_callback.php`
5. Callback validates state, exchanges code for tokens
6. ID token is validated using `firebase/php-jwt` with JWKS keys from provider
7. Issuer (`iss`) and audience (`aud`) claims are validated against configuration
8. MFA is checked via `amr` claim — if absent, falls back to local TOTP (based on `oidc_mfa` setting)
9. If auto-provisioning is enabled and user doesn't exist, admin account is created atomically
10. Session is initialized and user is redirected to main page

## Files changed

- `model/OIDC.php` — OIDC class (discovery, authorize, callback, JWT validation)
- `public/oidc_login.php` — initiates redirect to OIDC provider
- `public/oidc_callback.php` — handles callback, validates tokens, auto-provisions users
- `public/login.php` — added OIDC login button assignment
- `templates/login.tpl` — added OIDC button to login form
- `config.inc.php` — added `additional_auth`, `oidc_auto_provision`, `oidc_mfa`, and `oidc` config options
- `languages/en.lang` — added `pLogin_oidc_button` for the SSO button text
- `composer.json` — added `ext-curl` dependency

## Configuration

In `config.local.php`:

```php
$CONF['additional_auth'] = ['oidc'];  // Enable OIDC (local password auth always available)
$CONF['oidc_auto_provision'] = true;  // auto-create admin users on first login

// OIDC MFA policy: 'required' (IdP MFA or TOTP fallback, reject if neither),
// 'totp_fallback' (use TOTP if IdP lacks MFA, allow if neither), or 'none' (no MFA check)
$CONF['oidc_mfa'] = 'none';

$CONF['oidc'] = array(
    'client_id'     => 'your-client-id',
    'client_secret' => 'your-client-secret',
    'issuer_url'    => 'https://keycloak.example.com/realms/your-realm',
    'redirect_uri'  => 'https://mailadmin.example.com/oidc_callback.php',
    'scopes'        => 'openid email profile',
    'login_button_text' => 'Login with SSO',
);
```

## MFA Policy for OIDC Users

The `$CONF['oidc_mfa']` setting controls MFA enforcement for OIDC users:

| Value | Behavior |
|-------|----------|
| `'none'` | No MFA check. OIDC users log in with just IdP credentials. If user has TOTP configured, they are prompted for it. |
| `'mfa_or_totp'` | MFA required. IdP MFA first, local TOTP as fallback. Rejects login if neither available. Only amr methods in `oidc_mfa_methods` whitelist count (minus blacklist). |
| `'idp_mfa'` | IdP MFA only. TOTP is NOT a fallback. Rejects login if IdP lacks MFA. |

### MFA Method Whitelist/Blacklist

Control which `amr` methods count as MFA:

```php
// Whitelist: only these count as MFA
$CONF['oidc_mfa_methods'] = [
    'mfa', 'otp', 'totp', 'hotp', 'hwk', 'fido',
    'face', 'retina', 'wia', 'sc',
];

// Blacklist overrides whitelist (if in both, it's excluded)
$CONF['oidc_mfa_blacklist'] = [];
```

Example: If IdP sends `["pwd", "email"]` and `email` is not in the whitelist, the user is treated as having NO MFA.

When `$CONF['totp'] = 'YES'`, password login users get TOTP prompt as normal. The `oidc_mfa` setting is independent — it only affects OIDC users.

### How it works at the IdP

After OIDC callback, PostfixAdmin checks the `amr` (Authentication Methods References) claim from the IdP token:
- Contains `"mfa"`, `"otp"`, or `"hwk"` → MFA completed at IdP, full session granted
- None of those → check for local TOTP fallback based on `oidc_mfa` setting

### Example configuration

```php
// Require MFA for OIDC users (IdP MFA or local TOTP fallback, reject otherwise)
$CONF['oidc_mfa'] = 'required';

// Optional: also require TOTP for password logins
$CONF['totp'] = 'YES';
```

## Test plan

- [ ] Verify OIDC login redirect works (provider discovery + authorization URL)
- [ ] Verify callback validates state and nonce correctly
- [ ] Verify JWT validation with real provider tokens
- [ ] Verify issuer and audience claim validation
- [ ] Verify auto-provisioning creates admin account on first login
- [ ] Verify existing admin accounts can still log in via OIDC
- [ ] Verify session initialization and redirect to main page
- [ ] Verify local password auth still works when `additional_auth` is empty
- [ ] Verify `config.local.php` is preserved during any future upgrades
- [ ] Verify MFA enforcement with `oidc_mfa = 'required'`
- [ ] Verify TOTP fallback when IdP lacks MFA and `oidc_mfa = 'totp_fallback'`
- [ ] Verify disabled admin accounts cannot log in via OIDC

## Known Issues

- **SameSite=Strict session cookie** — PostfixAdmin uses `SameSite=Strict` for its session cookie. Cross-site callbacks from external providers (Microsoft Entra, Google, Okta, Auth0) may lose the session containing `oidc_state`. This is only a problem if the OIDC provider is on a different domain than PostfixAdmin. Same-site deployments (Keycloak on the same domain) are unaffected.
- **Auto-provisioning uses direct db_insert** — New admin accounts are created via `db_insert()` rather than `AdminHandler::add()`. This is a known limitation; future work should use the handler.
- **No per-domain/multitenant OIDC** — One global OIDC provider for all domains. Different issuers per domain are not supported.
- **Discovery document issuer** — The `.well-known/openid-configuration` document's `issuer` field is validated against the configured issuer during discovery.
- **UserInfo endpoint** — When used, the `sub` claim from UserInfo is verified against the ID token's `sub` before merging claims.
- **Identity binding by email** — OIDC accounts are linked using the `email` claim. This is problematic because: (1) email can change in the IdP, breaking access; (2) email can be reused (old employee → new employee), inheriting access; (3) email is not globally unique across IdPs. The reviewer recommends binding by `issuer + sub` instead, which is stable and unique. This requires schema changes (new columns in the `admin` table) and a migration strategy for existing users.

## Deviations from Upstream Patterns

- **Atomic upsert for auto-provisioning** — PostfixAdmin's standard pattern for creating records is check-then-insert (`db_query_one` → `db_insert`). This code uses database-specific atomic upsert syntax instead:
  - PostgreSQL/SQLITE: `INSERT ... ON CONFLICT DO NOTHING`
  - MySQL: `INSERT IGNORE`
  
  **Rationale:** The check-then-insert pattern has a race condition when two concurrent OIDC logins attempt to create the same admin account simultaneously. The second insert would fail with a duplicate key error. Atomic upsert eliminates this race condition at the database level.
  
  **Trade-off:** This introduces database-specific SQL branching (`db_pgsql()` / `db_sqlite()` check) instead of using the database-agnostic `db_insert()` wrapper. The upstream pattern is preferred for maintainability, but the race condition is a real concern for OIDC auto-provisioning where concurrent first-logins are plausible.

## Local QA Results

| Check | Result |
| --- | --- |
| PHP lint | Pass |
| OIDC redirect to Keycloak | Verified |
| OIDC callback + session init | Verified (manual login) |
| Auto-provisioning | Verified (new user created on first login) |
| SMTP AUTH (Dovecot SQL) | Verified (password change → IMAP/SMTP works) |
| Local password auth fallback | Verified (mailadmin@ still logs in with password) |
| Issuer validation | Verified |
| Audience validation | Verified |
| Nonce required | Verified |
| Inactive account blocked | Verified |

## Use Case

This was developed for a homelab/family mail server (FrogPond.Cloud) where family members need to manage their own mailboxes without sharing the main admin account. OIDC via Keycloak SSO provides secure, individual access.

## Test Environment

- **OIDC Provider:** Keycloak (LXC 103, FrogPond.Cloud realm)
- **Database:** PostgreSQL with pgBouncer
- **SMTP:** Postfix with Dovecot SASL
- **IMAP:** Dovecot

## Development

Code was written by AI (Claude) and tested by human. All changes were deployed to a live system and verified with real user logins.
