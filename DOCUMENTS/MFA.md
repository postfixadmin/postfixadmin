# MFA status and administrative reset

The administrator list shows whether each account has an MFA secret configured.
The domain list marks a domain with an MFA badge when at least one of its
mailboxes has a configured secret. The mailbox list shows a translated Yes/No
MFA column only when its complete authorized search result contains such a
mailbox. Pagination does not change the column's visibility. Inactive accounts
retain their configured-MFA status. No secrets are included in these indicators.

Administrative MFA reset through the web interface is disabled by default.
To enable it, set this in `config.local.php`:

```php
$CONF['totp_allow_admin_reset'] = 'YES';
```

Existing permissions still apply: global administrators can reset administrator
accounts; domain administrators can reset mailboxes within their managed domains.
TOTP must also be enabled for the web reset action to be available. When web
reset is disabled, direct reset requests are rejected as well as hiding the action.
This setting does not affect account holders managing their own MFA.

With local access to the installation, reset an existing account using:

```sh
scripts/postfixadmin-cli admin resetmfa admin@example.com
scripts/postfixadmin-cli mailbox resetmfa user@example.com
```

The CLI reset remains available when web reset or TOTP is disabled. It removes
the stored TOTP secret, including for inactive accounts. The account holder can
configure MFA again. As with the existing administrative web reset, this does
not revoke application passwords or exceptions and does not invoke the mailbox
TOTP-change hook. Reset commands must be run by someone authorized to administer
the installation and with access to its configuration and database.
