# Dovecot 2.4 configuration notes for PostfixAdmin

This document provides a Dovecot 2.4-style SQL configuration baseline for
PostfixAdmin installations using virtual mailboxes, multi-domain storage, quota,
LMTP, IMAP, POP3 and optional ManageSieve.

The older `DOCUMENTS/DOVECOT.txt` example is still useful background, but it
uses older Dovecot configuration syntax. Dovecot 2.4 also makes password scheme
compatibility more explicit, so deployments with old hashes should plan the
migration before changing `default_password_scheme`.

## Scope

The example files in this document cover:

* Dovecot 2.4 SQL `passdb` / `userdb`
* PostfixAdmin 4.x and current master-style schemas
* multi-domain virtual mailbox paths
* Maildir baseline storage
* optional per-user Maildir to mdbox migration
* optional post-login password hash migration to `{ARGON2ID}`
* quota and quota clone support
* quota warning delivery to the user's INBOX
* optional IMAPSieve spam/ham learning hooks
* LMTP, IMAP, POP3 and ManageSieve listeners

Review all paths, users, groups, database names and service ownership before
using the examples in production.

## Loading the example configuration

The example file is named `examples/dovecot-2.4-local.conf`. Dovecot will not
load it automatically just because it exists; it must be included by the active
Dovecot configuration used by your package or service.

Dovecot 2.4 packages may not use the same `dovecot.conf` layout that older 2.3
installations used. Before installing the example, check which configuration
file is active:

```sh
doveconf -n
```

For this Dovecot 2.4 proposal, the recommended deployment style is a single
local override file. Keeping the PostfixAdmin SQL auth, storage, quota, Sieve
and migration settings together makes the example easier to audit and avoids
missing one required fragment in a package-specific `conf.d` layout.

If your installation has a main `/etc/dovecot/dovecot.conf`, add a local
include near the end:

```conf
!include_try /etc/dovecot/local.conf
```

On Dovecot 2.4 installations where you intentionally want this single-file
layout instead of the older `conf.d` split, the end of `dovecot.conf` can be
kept explicit:

```conf
#!include_try conf.d/*.conf
!include_try local.conf
```

Only disable the package `conf.d` include after reviewing the existing package
defaults and moving any required local settings into `local.conf`.

Then install the example as:

```sh
install -m 0640 -o root -g dovecot examples/dovecot-2.4-local.conf /etc/dovecot/local.conf
```

If your package uses a `conf.d` layout and you prefer to keep it, install the
example with a late name so it is loaded after package defaults:

```sh
install -m 0640 -o root -g dovecot examples/dovecot-2.4-local.conf /etc/dovecot/conf.d/99-postfixadmin-local.conf
```

After installing or including the file, always validate the effective
configuration before reloading Dovecot:

```sh
doveconf -n
doveadm reload
```

Adjust ownership/group names if your distribution does not use a `dovecot`
group.

## Important Dovecot 2.4 differences

### Password schemes

If old `MD5`, `PLAIN-MD5` or `MD5-CRYPT` hashes still exist, Dovecot 2.4 may
require weak schemes to be explicitly enabled:

```conf
auth_allow_weak_schemes = yes
```

Treat this as a temporary migration setting only. After active users have been
upgraded to stronger hashes such as `{ARGON2ID}`, disable weak schemes again:

```conf
auth_allow_weak_schemes = no
```

PostfixAdmin password hash settings are documented in `DOCUMENTS/HASHING.md`.

### Calling doveadm from PostfixAdmin

When PostfixAdmin is configured to use Dovecot's `doveadm` for password hashing,
Dovecot 2.4 may need `-O` so `doveadm pw` does not try to read full Dovecot
configuration files:

```php
$CONF['dovecotpw'] = "/usr/bin/doveadm -O pw";
```

This avoids common permissions problems when the web server user cannot read
Dovecot certificates or private configuration.

## Baseline SQL authentication

For installations that do not need post-login password migration, keep the
password query simple:

```conf
passdb sql {
  default_password_scheme = ARGON2ID
  query = SELECT username AS user, password FROM mailbox WHERE username = '%{user}' AND active = '1'
}
```

For Maildir-only installations, a simple `userdb` query is enough:

```conf
userdb sql {
  query = SELECT \
    '/var/vmail/%{user | domain}/%{user | username}' AS home, \
    150 AS uid, \
    12 AS gid, \
    CONCAT(quota, 'B') AS quota_storage_size \
    FROM mailbox \
    WHERE username = '%{user}' AND active = '1'

  iterate_query = SELECT username AS user FROM mailbox WHERE active = '1'
}
```

See `examples/dovecot-2.4-local.conf` for a full working example.

## Optional password hash migration to ARGON2ID

If users still have legacy hashes, one migration pattern is to re-hash the
password after a successful login. Dovecot authenticates the user with the
currently stored hash, then a local post-login script generates an `{ARGON2ID}`
hash and updates the mailbox row.

The temporary query exposes the clear password only to the local Dovecot login
environment:

```conf
passdb sql {
  default_password_scheme = ARGON2ID

  # Normal query after migration:
  # query = SELECT username AS user, password FROM mailbox WHERE username = '%{user}' AND active = '1'

  # Temporary query used only by the post-login migration script:
  query = SELECT username AS user, password, '%{password}' AS userdb_plain_pass FROM mailbox WHERE username = '%{user}' AND active = '1'
}
```

Use this only during a controlled migration window. Review local permissions,
logs and process environment exposure before enabling it. After migration, revert
to the normal query without `userdb_plain_pass`.

The related post-login script is provided in
`examples/dovecot-upgrade-pass.sh`.

Keep verbose password logging disabled in production:

```conf
# Debug only. WARNING: enabling this may log plaintext passwords.
# Never enable it on production systems or shared logs.
#auth_verbose = yes
#auth_verbose_passwords = plain
```

## Optional per-user Maildir to mdbox migration

Dovecot 2.4 can receive `mail_driver` and `mail_path` from SQL. This makes it
possible to migrate users gradually from Maildir to mdbox instead of changing
the mailbox format for everyone at once.

This requires a local extension such as:

```sql
ALTER TABLE mailbox
  ADD COLUMN mail_format VARCHAR(16) NOT NULL DEFAULT 'maildir';
```

Then the `userdb` query can return the mailbox driver per user:

```conf
userdb sql {
  query = SELECT \
    '/var/vmail/%{user | domain}/%{user | username}' AS home, \
    150 AS uid, \
    12 AS gid, \
    CONCAT(quota, 'B') AS quota_storage_size, \
    CASE WHEN mail_format = 'mdbox' THEN 'mdbox' ELSE 'maildir' END AS mail_driver, \
    CONCAT('/var/vmail/', '%{user | domain}/', '%{user | username}') AS mail_path \
    FROM mailbox \
    WHERE username = '%{user}' AND active = '1'

  iterate_query = SELECT username AS user FROM mailbox WHERE active = '1'
}
```

Installations that do not need per-user mailbox format selection should use the
simpler Maildir-only query.

The related migration script is provided in
`examples/migrate-maildir-to-mdbox.sh`. Treat it as an operational example:
test with dry-run first, back up mail storage and the database, and review
paths, ownership and Dovecot permissions before running with `--real`.

## Optional quota warning delivery

The full configuration example uses Dovecot quota warnings:

```conf
quota "User Quota" {
  warning warn-95 {
    quota_storage_percentage = 95
    execute quota-warning {
      args = 95 %{user}
    }
  }
}

service quota-warning {
  executable = script /usr/local/bin/quota-warning.sh
  user = vmail
}
```

`examples/quota-warning.sh` is a sanitized helper that delivers a warning
message directly to the user's INBOX with `doveadm submaster deliver`.

Review the sender address, server name, support signature, script ownership and
Dovecot permissions before using it. Keep all production hostnames and domains
out of public examples.

## Optional IMAPSieve spam and ham learning

The full configuration example references two IMAPSieve scripts:

```conf
path = /var/vmail/sieve/bin/report-spam.sieve
path = /var/vmail/sieve/bin/report-ham.sieve
```

These are optional hooks for spam/ham learning, commonly used with Rspamd. The
example files are:

```text
examples/sieve/report-spam.sieve
examples/sieve/report-ham.sieve
examples/sieve/rspamd-learn-spam.sh
examples/sieve/rspamd-learn-ham.sh
```

The `.sieve` files use `vnd.dovecot.pipe`, so the helper shell scripts must be
installed under the configured `sieve_pipe_bin_dir`:

```conf
sieve_pipe_bin_dir = /var/vmail/sieve/bin
```

Review Rspamd paths, script ownership and execution permissions before enabling
these hooks. Installations that do not use IMAPSieve or Rspamd can remove the
`sieve_script spam` and `imapsieve_from Junk` blocks from the example config.

## Recommended migration sequence

1. Back up the PostfixAdmin database and mail storage.
2. Update Dovecot 2.4 configuration using the baseline SQL queries.
3. If old MD5 hashes exist, temporarily enable `auth_allow_weak_schemes = yes`.
4. Enable the post-login hash migration query and script if migrating passwords.
5. Let active users migrate to `{ARGON2ID}` after successful logins.
6. Revert the password query to the normal form without `userdb_plain_pass`.
7. Disable weak schemes again with `auth_allow_weak_schemes = no`.
8. If migrating mailbox format, add `mailbox.mail_format`, switch to the
   per-user `userdb` query, and run the mdbox migration script in dry-run first.
9. Migrate selected users, selected domains, or all mailboxes with `--real`.
10. Keep old Maildir backups until users and indexes have been validated.
