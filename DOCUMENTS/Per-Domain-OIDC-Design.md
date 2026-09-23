# Per-Domain OIDC — Design Proposal

**Status:** Draft (revised after maintainer review — folded into DomainHandler)
**Author:** @doneisgood
**Related:** PR #1144 (global OIDC support), PR #1147 (this PR)

## Problem

PR #1144 adds global OIDC — one IdP for all admins. This works for single-tenant deployments but doesn't support:

1. **Per-domain IdPs** — Domain A uses Keycloak, Domain B uses Google
2. **Domain-scoped auto-provisioning** — OIDC users should get domain-admin rights, not super-admin
3. **Multi-tenant hosting** — one PostfixAdmin instance serving multiple organizations with their own IdPs

## Goals

| Goal | Description |
|------|-------------|
| Global OIDC | Config-file IdP lets any admin log in (permissions assigned by super-admin) |
| Domain OIDC | Database-configured per-domain IdP grants domain-scoped permissions |
| Migration path | Existing global OIDC users are not broken |
| UI-managed | Super-admin configures per-domain OIDC through the web UI |

## Authentication Methods

PostfixAdmin supports multiple authentication methods that can be combined:

| Method | Status | Configured Via | Notes |
|--------|--------|----------------|-------|
| **Local Password** | Built-in | Always available | Default admin method, always enabled |
| **OIDC** | Implemented | `$CONF['additional_auth'] = ['oidc']` | Per-domain or global IdP (Keycloak, Google, etc.) |
| **LDAP** | Planned | `$CONF['additional_auth'][] = 'ldap'` | Not yet implemented; aspirational |
| **SAML** | Planned | `$CONF['additional_auth'][] = 'saml'` | Not yet implemented; aspirational |

The `additional_auth` array is designed for extensibility. Any combination can be enabled:
```php
// Examples:
$CONF['additional_auth'] = ['oidc'];                    // OIDC only
$CONF['additional_auth'] = ['oidc', 'ldap'];             // OIDC + LDAP
$CONF['additional_auth'] = ['ldap'];                    // LDAP only (local password always available)
$CONF['additional_auth'] = [];                          // Local password only (default)
```

Local password auth is **always available** regardless of `additional_auth` — it cannot be disabled.

## Configuration

### Global OIDC — Config File

```php
// config.local.php — global OIDC for any admin
$CONF['oidc'] = [
    'client_id'     => 'postfixadmin',
    'client_secret' => '...',
    'issuer_url'    => 'https://keycloak.example.com/realms/master',
    'scopes'        => 'openid email profile',
    'login_button_text' => 'Login with Keycloak',
];
```

- Stays in `config.local.php` (not UI-managed)
- Users authenticating via this IdP get permissions that the super-admin already assigned them
- If user doesn't exist yet and `oidc_auto_provision` is enabled, they get created as regular admin (not super-admin)

### OIDC Identity Method

```php
// OIDC identity method: 'email' (legacy, backward compat) or 'issuer_sub' (recommended).
// 'email' — same email = same account across all IdPs (legacy behavior)
// 'issuer_sub' — each IdP is a separate identity space (secure, recommended)
$CONF['oidc_identity'] = 'issuer_sub';
```

- `email` — legacy behavior, same email = same account (backward compat)
- `issuer_sub` — secure, recommended, each IdP is its own identity space

### Per-Domain OIDC — Database Columns

Per maintainer feedback (cboltz): when a relation is truly 1:1, add fields to the existing table rather than creating a separate one. OIDC config fields are added directly to the `domain` table:

```sql
ALTER TABLE domain ADD COLUMN oidc_enabled SMALLINT DEFAULT 0;
ALTER TABLE domain ADD COLUMN oidc_issuer_url TEXT DEFAULT NULL;
ALTER TABLE domain ADD COLUMN oidc_client_id VARCHAR(255) DEFAULT NULL;
ALTER TABLE domain ADD COLUMN oidc_client_secret VARCHAR(255) DEFAULT NULL;
ALTER TABLE domain ADD COLUMN oidc_scopes VARCHAR(255) DEFAULT 'openid email profile';
ALTER TABLE domain ADD COLUMN oidc_login_button_text VARCHAR(255) DEFAULT 'Login with SSO';
ALTER TABLE domain ADD COLUMN oidc_auto_provision SMALLINT DEFAULT 0;
ALTER TABLE domain ADD COLUMN oidc_mfa_policy VARCHAR(50) DEFAULT 'none';
ALTER TABLE domain ADD COLUMN oidc_mfa_methods TEXT DEFAULT NULL;
ALTER TABLE domain ADD COLUMN oidc_mfa_blacklist TEXT DEFAULT NULL;
```

- Managed through Domain Edit UI (super-admin only)
- Per-domain MFA policy override
- `oidc_enabled` is a real database column (boolean), not derived — the super-admin can explicitly enable/disable per-domain OIDC independently of whether `oidc_issuer_url` is set

### Admin Table

```sql
ALTER TABLE admin ADD COLUMN oidc_issuer TEXT;
ALTER TABLE admin ADD COLUMN oidc_sub VARCHAR(255);
```

- Records which IdP created the admin account
- NULL = local password user
- **Identity binding by issuer + sub** (stable, unique) instead of email

## Login Flow

```
User visits login page
    ↓
Sees "Login with SSO" button (global) and/or per-domain buttons
    ↓
Option A: Global IdP button → authenticate → user gets their existing permissions
Option B: Per-domain button (e.g., orgb.com) → domain IdP → domain-admin
    ↓
Callback validates token
    ↓
Look up user by configured identity method:
    ├── 'issuer_sub' mode → look up by (oidc_issuer, oidc_sub)
    │   ├── Found → use that account
    │   └── Not found → auto-provision if enabled
    └── 'email' mode → look up by username (email)
        ├── Found → upgrade old account with issuer+sub
        └── Not found → auto-provision if enabled
    ↓
Check if domain config exists:
    ├── Domain IdP → add to domain_admins for that domain
    └── Global IdP → use permissions already assigned by super-admin
```

## Identity Binding: issuer + sub (instead of email)

**Problem with email binding:**
- Email can change in IdP → loses access
- Email can be reused (old employee → new employee) → inherits access
- Email is not globally unique across IdPs

**Solution: issuer + sub**
- `iss` (issuer): which IdP issued the token
- `sub` (subject): stable unique user ID within that issuer
- Never changes, even if email changes
- Globally unique per issuer

**Migration:**
1. Add `oidc_issuer` and `oidc_sub` columns to `admin` table
2. On first OIDC login, store `iss + sub` alongside username
3. Look up users by `iss + sub` (or by username in legacy `email` mode)
4. Email becomes display attribute only

## The Username Uniqueness Problem

**The issue:** If the same email is used at different IdPs, and we store it as the username, we have a PK collision.

| username | oidc_issuer | oidc_sub | Notes |
|----------|-------------|----------|-------|
| doneisgood@example.com | NULL | NULL | Local password user |
| doneisgood@example.com | keycloak.example.com/realms/OrgA | user-123 | OrgA OIDC user |
| doneisgood@example.com | keycloak.example.com/realms/OrgB | user-456 | OrgB OIDC user |

**Option A: Composite key `(username, oidc_issuer)`**
- ✅ Clean per-domain separation
- ❌ Breaks everything that expects unique username (Login.php, AdminHandler, etc.)

**Option B: Linking table (minimal disruption)**
```sql
CREATE TABLE admin_oidc (
    id SERIAL PRIMARY KEY,
    admin_username varchar(255) NOT NULL REFERENCES admin(username),
    oidc_issuer text NOT NULL,
    oidc_sub varchar(255) NOT NULL,
    UNIQUE (oidc_issuer, oidc_sub)
);
```
- ✅ `admin` table unchanged — username stays unique PK
- ✅ Login flow: look up `admin_oidc` by issuer+sub → get `admin_username`

**Option C: Same email = same account (simplest)**
- ✅ Username stays unique, no code breaks
- ❌ Same email at different IdP = same account (can't separate them)

**The core question for the team:** Same email at different IdP — do you want same account or different accounts?

## Auto-Provisioning

| Source | Behavior |
|--------|----------|
| Global IdP | If user exists, use their permissions. If not and `oidc_auto_provision` is enabled, create regular admin (not super-admin) |
| Domain IdP | Create admin + insert into `domain_admins` for that domain |
| Domain IdP + no auto_provision | Reject login, "Contact administrator" |

## Login Page — Domain-Specific Buttons

The login page queries domains where `oidc_enabled` is true and renders one button per domain:

```
Login with Keycloak (orgb.com)
Login with Google (customer-example.com)
```

**Why this works despite the "don't know the domain yet" concern:** We don't resolve the button to the user's domain dynamically. Instead, we pre-render one button per configured domain. Users click the button for their own domain. This avoids any pre-login domain detection while still providing domain-specific buttons with the configured `login_button_text`.

With few domains (1-5), this is clean. With many domains, it gets cluttered — an acceptable trade-off since per-domain OIDC is a multi-tenant feature used by hosting providers who want their customers to see their own branded button.

## Implementation

- No separate `domain_oidc` table — columns added to `domain` directly (migration 1859)
- No `DomainOidcHandler` class — `DomainHandler` handles OIDC via `pacol()` fields and PFAHandler's `save()`
- `oidc_enabled` column controls per-domain OIDC on/off in the login page
- `oidc_client_secret` uses `b64p` type (base64-encoded at rest)
- MFA accessors: `getMfaMethods()`, `getMfaBlacklist()`, `getMfaPolicy()` — per-domain values with fallback to `$CONF`

### Files changed
- `public/upgrade.php` — `upgrade_1859()` uses `_db_add_field()` for domain columns
- `model/DomainHandler.php` — pacol fields, `read_from_db_postprocess` decodes secret + derives `oidc_enabled`, `postSave` no longer has separate OIDC block, MFA methods added
- `model/DomainOidcHandler.php` — **deleted** (no longer needed)
- `public/login.php` — queries domain table directly for OIDC configs
- `public/oidc_login.php` — queries domain table directly for domain config
- `public/oidc_callback.php` — queries domain table directly for MFA policy/methods
- `templates/login.tpl` — uses `$config.oidc_login_button_text` (DB column name)
- `tests/bootstrap.php` — `_db_add_field()` for test schema
- `tests/DomainOidcTest.php` — replaces deleted `DomainOidcHandlerTest.php`, tests via DomainHandler
- `DOCUMENTS/OIDC-Feature.md` — update design notes to match

## UI Changes

### Domain List
- New "OIDC" column showing enabled/disabled status
- "Configure OIDC" link per domain

### Domain Edit Page
- New "OIDC Authentication" section (visible to super-admins)
- Fields: issuer URL, client ID, client secret, scopes, button text, auto-provision, MFA policy
- Enable/disable toggle
- Client secret is masked (password field) — leave blank to keep existing secret when editing

### Login Page
- If per-domain OIDC configured: show per-domain buttons (one per domain with OIDC configured)
- If only global OIDC: single button (current behavior)

## Migration

Existing global OIDC users:
1. Their `oidc_issuer` is set to the global issuer
2. Their `oidc_sub` is set from the token
3. They retain their existing permissions
4. No action required

## Open Questions

1. **Username uniqueness** — composite key vs linking table vs same-email-same-account (see above)
2. **Email-based domain detection** — what if email domain doesn't match any configured domain?
3. **Multiple IdPs per domain** — should we support fallback IdPs?
4. **Group/role mapping** — should IdP groups map to PostfixAdmin permissions?
5. **Discovery document** — validate `issuer` in `.well-known/openid-configuration` before saving?

## Scope

This is a follow-up to PR #1144. The global OIDC in that PR becomes the "global admin" path. This proposal adds the per-domain layer.