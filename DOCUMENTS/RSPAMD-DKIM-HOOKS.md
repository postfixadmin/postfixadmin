# Optional Rspamd DKIM hooks

PostfixAdmin can invoke scripts after creating or deleting a domain. The
scripts in this guide add optional per-domain Rspamd DKIM key management while
keeping the generic Maildir hooks independent of Rspamd.

These examples intentionally separate privileges:

* the virtual-mail user owns and moves Maildirs;
* the Rspamd service user owns and moves DKIM keys;
* neither account receives permission to run commands as root.

The example paths and account names must be reviewed for each operating
system. In particular, `_rspamd`, `/var/db/rspamd` and `/usr/bin/sudo` are not
portable defaults.

## Included scripts

`ADDITIONS/postfixadmin-rspamd-dkim-postcreation.sh`
: Generates a private key and DNS record without overwriting existing files.
  Run it as the Rspamd service user.

`ADDITIONS/postfixadmin-rspamd-dkim-postdeletion.sh`
: Moves every selector file for a domain into an Rspamd-owned recovery
  directory. Run it as the Rspamd service user.

`ADDITIONS/postfixadmin-domain-postdeletion-with-rspamd.sh`
: Runs the generic Maildir deletion hook as the current virtual-mail user and
  delegates DKIM cleanup to the Rspamd helper.

All deletion operations are idempotent. Missing Maildirs or DKIM files are
successful no-ops, which allows an interrupted domain hook to be retried.

## Installation

The scripts executed through sudo must be installed outside the web root in a
root-owned directory that is not writable by the web server, virtual-mail or
Rspamd accounts. For example:

```text
/usr/local/libexec/postfixadmin/
```

Create the runtime directories once as an administrator. Example commands for
a system whose Rspamd account is `_rspamd` are:

```console
install -d -o _rspamd -g _rspamd -m 0700 /var/db/rspamd/dkim
install -d -o _rspamd -g _rspamd -m 0700 /var/db/rspamd/deleted-dkim
install -d -o vmail -g vmail -m 0700 /var/vmail/deleted-maildirs
```

Review the constants at the beginning of every script. A `/var/vmail` layout,
for example, requires changing `basedir` in the generic Maildir scripts. On a
system where sudo is `/usr/local/bin/sudo`, update `sudo_path` in the composite
hook.

The creation helper stores the private key as mode `0600` and the DNS record
as mode `0644`. It writes temporary files in the DKIM directory and publishes
them without replacing an existing key. The `.txt` file contains the standard
zone-record output produced by `rspamadm dkim_keygen`.

## PostfixAdmin configuration

For an installation using Apache, `vmail` and `_rspamd`, the relevant
`config.local.php` settings can look like:

```php
$CONF['domain_postcreation_script'] =
    '/usr/bin/sudo -n -u _rspamd -- /usr/local/libexec/postfixadmin/postfixadmin-rspamd-dkim-postcreation.sh';

$CONF['domain_postdeletion_script'] =
    '/usr/bin/sudo -n -u vmail -- /usr/local/libexec/postfixadmin/postfixadmin-domain-postdeletion-with-rspamd.sh';

$CONF['mailbox_postdeletion_script'] =
    '/usr/bin/sudo -n -u vmail -- /usr/local/libexec/postfixadmin/postfixadmin-mailbox-postdeletion.sh';
```

The `-n` option makes a sudo configuration error fail immediately instead of
waiting for an interactive password prompt.

An installation without Rspamd should configure the generic
`postfixadmin-domain-postdeletion.sh` directly and does not need any of the
Rspamd helpers.

## Sudoers example

Use `visudo` to edit and validate the rules. Restrict both the target account
and executable path:

```sudoers
apache ALL=(_rspamd) NOPASSWD: /usr/local/libexec/postfixadmin/postfixadmin-rspamd-dkim-postcreation.sh
apache ALL=(vmail) NOPASSWD: /usr/local/libexec/postfixadmin/postfixadmin-domain-postdeletion-with-rspamd.sh
apache ALL=(vmail) NOPASSWD: /usr/local/libexec/postfixadmin/postfixadmin-mailbox-postdeletion.sh
vmail ALL=(_rspamd) NOPASSWD: /usr/local/libexec/postfixadmin/postfixadmin-rspamd-dkim-postdeletion.sh
```

Do not use `(ALL)` as the run-as account and never grant a service account
permission to run a general-purpose command such as `/usr/bin/mv`. A rule that
names a command without arguments permits arbitrary arguments, so every helper
must retain its own strict validation even when sudoers also constrains the
command.

After installation, verify ownership and permissions on every script and all
parent directories. A script writable by `apache`, `vmail` or `_rspamd` must
not be referenced by sudoers.

## Recovery and operations

Maildirs and DKIM keys use separate recovery locations because they have
different owners and sensitivity:

```text
/var/vmail/deleted-maildirs/
/var/db/rspamd/deleted-dkim/
```

The composite hook attempts both operations even if one fails, then returns a
failure status if either helper reported a real error. PostfixAdmin can report
that failure, and an administrator can safely rerun the hook after correcting
permissions or storage problems.

Before enabling the hooks in production, test creation, deletion, repeated
deletion and restoration with a non-production domain. Also confirm the local
`rspamadm dkim_keygen` output and publish the generated DNS record before
enabling DKIM signing for the domain.
