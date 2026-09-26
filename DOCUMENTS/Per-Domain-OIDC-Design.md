# Per-Domain OIDC — Design Proposal

**Status:** Draft for discussion
**Author:** @doneisgood (FrogPond.Cloud)
**Related:** PR #1144 (global OIDC support)

## Problem

PR #1144 adds global OIDC — one IdP for all admins. This works for single-tenant deployments but doesn't support:

1. **Per-domain IdPs** — Domain A uses Keycloak, Domain B uses Google
2. **Domain-scoped auto-provisioning** — OIDC users should get domain-admin rights, not super-admin
3. **Multi-tenant hosting** — one PostfixAdmin instance serving multiple organizations with their own IdPs

## Goals

| Goal | Description |
|------|-------------|
| Master admin | Global IdP in config file grants super-admin (manage all domains) |
| Domain admin | Per-domain IdP in database grants domain-scoped permissions |
| Migration path | Existing global OIDC users are not broken |
| UI-managed | Super-admin configures per-domain OIDC through the web UI |

## Configuration

### Master (Global) OIDC — Config File

```php
// config.local.php — master IdP for super-admins
$CONF['oidc'] = [
    'client_id'     => 'postfixadmin',
    'client_secret' => '...',
    'issuer_url'    => 'https://keycloak.example.com/realms/master',
    'scopes'        => 'openid email profile',
    'login_button_text' => 'Login with Keycloak',
];
```

- Stays in `config.local.php` (not UI-managed)
- Users authenticating via this IdP become **super-admins**
- Same behavior as PR #1144 today

### Per-Domain OIDC — Database

```sql
CREATE TABLE domain_oidc (
    domain VARCHAR(255) NOT NULL PRIMARY KEY REFERENCES domain(domain) ON DELETE CASCADE,
    issuer_url TEXT NOT NULL,
    client_id VARCHAR(255) NOT NULL,
    client_secret VARCHAR(255) NOT NULL,
    scopes VARCHAR(255) DEFAULT 'openid email profile',
    login_button_text VARCHAR(255) DEFAULT 'Login with SSO',
    auto_provision SMALLINT DEFAULT 0,
    mfa_policy VARCHAR(50) DEFAULT 'none'
);
```

- Managed through Domain Edit UI
- Only super-admins can configure
- Per-domain MFA policy override

### Admin Tracking

```sql
ALTER TABLE admin ADD COLUMN oidc_issuer TEXT;
```

- Records which IdP created the admin account
- NULL = local password user
- Master issuer = super-admin
- Domain issuer = domain-admin

## Login Flow

```
User visits login page
    ↓
Sees "Login with SSO" button(s)
    ↓
Option A: Master IdP button → authenticate → super-admin
Option B: Email entered → domain detected → domain IdP → domain-admin
    ↓
Callback validates token
    ↓
Check issuer:
    ├── Master issuer → superadmin = 1
    └── Domain issuer → add to domain_admins for that domain
```

## Auto-Provisioning

| Source | Behavior |
|--------|----------|
| Master IdP | Create admin with `superadmin = 1` (existing behavior) |
| Domain IdP | Create admin + insert into `domain_admins` for that domain |
| Domain IdP + no auto_provision | Reject login, "Contact administrator" |

## UI Changes

### Domain List
- New "OIDC" column showing enabled/disabled status
- "Configure OIDC" link per domain

### Domain Edit Page
- New "OIDC Authentication" section (visible to super-admins)
- Fields: issuer URL, client ID, client secret, scopes, button text, auto-provision, MFA policy
- Enable/disable toggle

### Login Page
- If per-domain OIDC configured: show domain selector or email-based detection
- If only master OIDC: single button (current behavior)

## Migration

Existing global OIDC users:
1. Their `oidc_issuer` is set to the master issuer
2. They retain super-admin status
3. No action required

## Open Questions

1. **Email-based domain detection** — what if email domain doesn't match any configured domain?
2. **Multiple IdPs per domain** — should we support fallback IdPs?
3. **Group/role mapping** — should IdP groups map to PostfixAdmin permissions?
4. **Discovery document** — validate `issuer` in `.well-known/openid-configuration` before saving?

## Scope

This is a follow-up to PR #1144. The global OIDC in that PR becomes the "master admin" path. This proposal adds the per-domain layer.

## Implementation Estimate

| Component | Effort |
|-----------|--------|
| Database schema + migration | 1 day |
| Domain Edit UI | 1-2 days |
| Login flow changes | 1-2 days |
| Auto-provisioning logic | 1 day |
| Tests | 1-2 days |
| **Total** | **5-8 days** |
