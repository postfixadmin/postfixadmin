
# BEFORE YOU START


**** ALL THESE SCRIPTS ARE CREATED BY THIRD PARTIES ****
     **** THEY ARE AS IS, USE AT YOUR OWN RISK! ****

# ADDITIONS

In this directory you will find additional scripts that are build by others.

## change_password.tgz

by George Vieira <george at citadelcomputer dot com dot au>
SquirrelMail plugin to change your passwor

## cleanupdirs.pl

by jared bell <jared at beol dot net>
Displays a list of mailboxes that need to be deleted

## mailbox_remover.pl

by Petr Znojemsky
Deletes all unused mailboxes

## mkeveryone.pl

by Joshua Preston
Generate an 'everybody' alias for a domain.

## pfa_maildir_cleanup.pl
by Stephen Fulton <sfulton at esoteric dot ca>
Deletes all unused mailboxes

## postfixadmin-0.3-1.4.tar.gz

by Florian Kimmerl <info at spacekoeln dot de>

The Postfixadmin SquirrelMail plugin let users change their virtual alias,
vacation status/message and password.

See also :  https://github.com/postfixadmin/postfixadmin/tree/master/ADDITIONS/squirrelmail-plugin


##  virtualmaildel.php

by George Vieira <george at citadelcomputer dot com dot au>
Deletes all unused mailboxes

## Example mailbox / domain scripts for PostfixAdmin

- postfixadmin-mailbox-postcreation.sh
- postfixadmin-mailbox-postdeletion.sh
- postfixadmin-domain-postdeletion.sh

by Troels Arvin <troels@arvin.dk>

Examples relevant to the optional `mailbox_postcreation_script`,
`mailbox_postdeletion_script` and `domain_postdeletion_script` configuration
options.

Review and adjust `basedir` and `trashbase` before installing the scripts. Run
them as the account that owns the Maildirs, not as root. If sudo is required,
grant the web server permission to run only the exact script as that account;
never grant access to a generic `mv` command. Install privileged hooks in a
root-owned directory that is not writable by the web server or mail users.

### Sudoers applies to every enabled hook

Sudoers is not specific to the optional Rspamd integration. Every hook enabled
in `config.local.php` that is invoked through sudo needs its own rule with the
exact target account and installed script path. For example, after replacing
the account names and paths to match the local system:

```sudoers
apache ALL=(courier) NOPASSWD: /usr/local/libexec/postfixadmin/postfixadmin-mailbox-postcreation.sh
apache ALL=(courier) NOPASSWD: /usr/local/libexec/postfixadmin/postfixadmin-mailbox-postdeletion.sh
apache ALL=(courier) NOPASSWD: /usr/local/libexec/postfixadmin/postfixadmin-domain-postdeletion.sh
apache ALL=(dovecot) NOPASSWD: /usr/local/libexec/postfixadmin/postfixadmin-mailbox-postpassword.sh
```

Only add rules for hooks that are actually configured. If the optional Rspamd
hooks are installed, their rules extend this base policy. The composite Rspamd
domain-deletion hook replaces the direct domain-deletion rule; unrelated
mailbox and Dovecot rules remain in effect. See
`DOCUMENTS/RSPAMD-DKIM-HOOKS.md` for the complete mapping.

The scripts validate the argument contracts documented in `config.inc.php`.
Deletion is idempotent: an already absent Maildir is reported as a successful
no-op so an interrupted hook can be retried safely.

`postfixadmin-mailbox-postpassword.sh` is an example for Dovecot mail-crypt. It
requires Dovecot 2.3.19 or newer. Its default private-password setting name is
for Dovecot 2.3; follow the comment in the script when using Dovecot 2.4.

## Optional Rspamd DKIM hooks

- postfixadmin-rspamd-dkim-postcreation.sh
- postfixadmin-rspamd-dkim-postdeletion.sh
- postfixadmin-domain-postdeletion-with-rspamd.sh

These examples keep Rspamd DKIM key management separate from the generic
Maildir hooks and run each operation as its owning service account. See
`DOCUMENTS/RSPAMD-DKIM-HOOKS.md` for installation, `config.local.php`, sudoers
and recovery guidance.


## Cyrus Quota Usage

See https://github.com/o-m-d/cyrus-quotausage-to-pfa
