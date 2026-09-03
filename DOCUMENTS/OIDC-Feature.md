## Summary

Adds native OpenID Connect (OIDC) authentication as an alternative to local username/password auth. Users can log in via an external OIDC provider (e.g., Keycloak, Auth0, Okta) with auto-provisioning of admin accounts.

Closes: (no existing issue — new feature)

## Features

- **OIDC login** — users click "Login with SSO" and authenticate via external provider
- **Auto-provisioning** — new admin accounts created automatically on first OIDC login
- **JWT validation** — uses `firebase/php-jwt` with JWKS key fetching and X.509 chain validation
- **CSRF protection** — state parameter prevents cross-site request forgery
- **Nonce verification** — prevents token replay attacks
- **Configurable button text** — customize the SSO button label (e.g., "FrogPond.Cloud Keycloak")
- **Graceful fallback** — local password auth remains available as default

## How it works

1. User clicks "Login with SSO" on login page
2. PostfixAdmin discovers OIDC provider configuration (`.well-known/openid-configuration`)
3. User is redirected to provider's authorization endpoint
4. After authentication, provider redirects back to `oidc_callback.php`
5. Callback validates state, exchanges code for tokens
6. ID token is validated using `firebase/php-jwt` with JWKS keys from provider
7. If auto-provisioning is enabled and user doesn't exist, admin account is created
8. Session is initialized and user is redirected to main page

## Files changed

- `model/OIDC.php` — OIDC class (discovery, authorize, callback, JWT validation)
- `public/oidc_login.php` — initiates redirect to OIDC provider
- `public/oidc_callback.php` — handles callback, validates tokens, auto-provisions users
- `public/login.php` — added OIDC login button assignment
- `templates/login.tpl` — added OIDC button to login form
- `config.inc.php` — added `auth_provider`, `oidc_auto_provision`, and `oidc` config options

## Configuration

In `config.local.php`:

```php
$CONF['auth_provider'] = 'oidc';  // 'local' or 'oidc'
$CONF['oidc_auto_provision'] = true;  // auto-create admin users on first login
$CONF['oidc'] = array(
    'client_id'     => 'your-client-id',
    'client_secret' => 'your-client-secret',
    'issuer_url'    => 'https://keycloak.example.com/realms/your-realm',
    'redirect_uri'  => 'https://mailadmin.example.com/oidc_callback.php',
    'scopes'        => 'openid email profile',
    'login_button_text' => 'Login with SSO',
);
```

## Test plan

- [ ] Verify OIDC login redirect works (provider discovery + authorization URL)
- [ ] Verify callback validates state and nonce correctly
- [ ] Verify JWT validation with real provider tokens
- [ ] Verify auto-provisioning creates admin account on first login
- [ ] Verify existing admin accounts can still log in via OIDC
- [ ] Verify session initialization and redirect to main page
- [ ] Verify local password auth still works when `auth_provider = 'local'`
- [ ] Verify `config.local.php` is preserved during any future upgrades

## Security

- **State validation** — prevents CSRF attacks
- **Nonce verification** — prevents token replay attacks (nonce must be present and match)
- **Issuer validation** — ID token `iss` claim must match configured issuer URL
- **Audience validation** — ID token `aud` claim must match configured client ID
- **JWT signature validation** — via firebase/php-jwt with JWKS key fetching
- **Active account check** — disabled admin accounts cannot log in via OIDC

## Known Issues

- **SameSite=Strict session cookie** — PostfixAdmin uses `SameSite=Strict` for its session cookie. Cross-site callbacks from external providers (Microsoft Entra, Google, Okta, Auth0) may lose the session containing `oidc_state`. This is only a problem if the OIDC provider is on a different domain than PostfixAdmin. Same-site deployments (Keycloak on the same domain) are unaffected.
- **Auto-provisioning uses direct db_insert** — New admin accounts are created via `db_insert()` rather than `AdminHandler::add()`. This is a known limitation; future work should use the handler.
- **No per-domain/multitenant OIDC** — One global OIDC provider for all domains. Different issuers per domain are not supported.
- **No discovery document issuer validation** — The `.well-known/openid-configuration` document's `issuer` field is not validated against the configured issuer. This is only a risk if the discovery endpoint can be manipulated.
- **UserInfo endpoint** — When used, the `sub` claim from UserInfo is not verified against the ID token's `sub`. This is only a risk if the UserInfo endpoint is compromised.

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
