# PostfixAdmin Virtual Vacation for PHP CLI

This is a PHP CLI implementation of
`VIRTUAL_VACATION/vacation.pl`. It preserves the original Postfix transport
model and credits its historical contributors in
`VIRTUAL_VACATION/Contributions.txt`.

> **Current status:** complete Vacation transport plus setup, diagnostics,
> SMTP testing, and read-only message inspection. It reads Postfix pipe input,
> resolves aliases, enforces notification intervals, builds the automatic
> reply, and delivers it through SMTP or an optional `sendmail` executable.

## 1. Install the single runtime file

Production installation needs only:

```text
vacation.php
```

Choose the path, owner, and group according to the local Postfix policy. For
example, a transport running as `vmail` with access through group `mail` could
use:

```bash
install -d -o root -g mail -m 750 /opt/postfixadmin
install -o root -g mail -m 750 vacation.php /opt/postfixadmin/vacation.php
```

This is an example, not a required identity or path. The script does not
create service users, change ownership, or modify Postfix.

## 2. Requirements

- PHP CLI 8.2 or newer, matching the current PostfixAdmin `master` requirement
- PDO and the driver used by PostfixAdmin: `pdo_mysql`, `pdo_pgsql`, or
  `pdo_sqlite`
- `mbstring`
- `mailparse`
- `openssl` only when SMTP TLS/SSL is enabled
- `proc_open` only when the optional `sendmail` transport is selected
- An existing PostfixAdmin installation and database
- An SMTP service, normally Postfix on `localhost:25`, or an absolute
  `sendmail`-compatible executable path

`mailparse` is a PECL extension and is not bundled with PHP. It requires
`mbstring` to be loaded first. Typical package names include:

```bash
# RHEL, Rocky Linux, or AlmaLinux, depending on the selected repository
dnf install php-pecl-mailparse

# Debian or Ubuntu
apt install php-mailparse
```

Package names can vary with versioned PHP streams. Verify the PHP CLI used by
Postfix rather than only the web server runtime:

```bash
php --ini
php -m | grep -E 'PDO|pdo_mysql|pdo_pgsql|pdo_sqlite|mbstring|mailparse'
php --ri mailparse
```

The message inspection action uses `mailparse` to process the incoming stream
and applies the safety decisions used by `vacation.pl`.

## 3. Locate PostfixAdmin

The selected directory must contain both `config.inc.php` and
`config.local.php`. A common path is:

```text
/var/www/html/postfixadmin
```

If `--postfixadmin-root` is omitted, `vacation.php` searches the current
directory, its parent directories, the script directory and its parents, and
common installation locations. `POSTFIXADMIN_ROOT` can also provide the path.

When exactly one installation is found, it is selected automatically. When
none is found, the command fails and requests `--postfixadmin-root`. When more
than one is found, interactive setup asks the administrator to select one;
non-interactive checks require an explicit path.

PHP loads PostfixAdmin's normal `config.inc.php` and `config.local.php` flow.
It does not parse PHP assignments as text, and it never displays the database
password.

## 4. Check dependencies

Run:

```bash
php /opt/postfixadmin/vacation.php --check-dependencies \
  --postfixadmin-root /var/www/html/postfixadmin
```

This checks:

- PHP version
- `mbstring` and `mailparse`
- PDO
- The PDO driver selected by PostfixAdmin

It does not connect to the database, inspect tables, contact SMTP, or send a
message.

If PostfixAdmin cannot be found, the database driver cannot be selected and is
reported as `NOT TESTED`:

```text
Configuration:
  PostfixAdmin config          MISSING - no installation found; use --postfixadmin-root /path/to/postfixadmin

Dependencies:
  Database driver              NOT TESTED - database type is unavailable because PostfixAdmin configuration was not loaded

Result: FAILED
```

Status meanings:

- `OK`: the individual check succeeded.
- `MISSING`: a required file or extension was not found.
- `NOT TESTED`: a prerequisite failed.
- `WARNING`: the item works but needs administrative attention.
- `FAILED`: the item was tested and failed.

## 5. Create the PHP configuration

Run:

```bash
php /opt/postfixadmin/vacation.php --init-config \
  --postfixadmin-root /var/www/html/postfixadmin
```

The command:

1. Loads effective PostfixAdmin configuration.
2. Shows the selected database while hiding its password.
3. Prompts for SMTP server, port, and HELO; Enter accepts each proposed value.
4. Creates `/etc/postfixadmin/vacation-php.conf` with mode `0640`.
5. Leaves owner and group selection to the administrator.

Example generated file:

```ini
[postfixadmin]
root = "/var/www/html/postfixadmin"

[smtp]
server = "localhost"
port = 25
helo = "mail.example.org"

[logging]
syslog = true
level = info
```

Database settings, table names, prefixes, mappings, and `vacation_domain` are
inherited from PostfixAdmin and are not duplicated by default.

The PHP configuration is deliberately separate from the Perl file:

```text
/etc/postfixadmin/vacation.conf       existing Perl configuration
/etc/postfixadmin/vacation-php.conf   PHP configuration
```

Creating `vacation-php.conf` never modifies `vacation.conf`.

## 6. General configuration reference

The following example contains every accepted key. `[database]` and
`[vacation]` are optional overrides and should normally be omitted so that
PostfixAdmin remains the source of truth.

```ini
[postfixadmin]
root = "/var/www/html/postfixadmin"

[smtp]
server = "localhost"
port = 25
helo = "mail.example.org"
security = "none"
timeout = 120
local_address = ""
username = ""
password = ""
# Leave empty to use SMTP; otherwise use an absolute executable path.
sendmail = ""

# Optional dedicated database account. Normally omit this section.
[database]
type = "mysqli"
host = "127.0.0.1"
port = 3306
socket = ""
name = "postfix"
user = "postfix_vacation"
password = "change-me"

# Optional override. Normally inherit this from PostfixAdmin.
[vacation]
domain = "autoreply.example.org"

# Optional message-policy overrides. Normally inherit recipient_delimiter
# from PostfixAdmin and use the built-in no-reply protection.
[message]
recipient_delimiter = "+"
no_vacation_pattern = ""
custom_noreply_pattern = false
noreply_pattern = "bounce|do-not-reply|facebook|linkedin|list-|myspace|twitter"
default_noreply_pattern = "^(noreply|no-reply|do_not_reply|no_reply|postmaster|mailer-daemon|listserv|majordomo|owner-|request-|bounces-)|(-(owner|request|bounces)@)"

[reply]
friendly_from = ""
account_name = false
replace_from = "<%From_Date>"
replace_until = "<%Until_Date>"
date_format = "Y-m-d"

[logging]
syslog = true
level = info
file_enabled = false
file = "/var/log/vacation.log"
```

Defaults and behavior:

- `smtp.server`: `localhost`
- `smtp.port`: `25`
- `smtp.helo`: detected machine FQDN when empty; prompted only if detection
  also fails
- `smtp.security`: `none`, `ssl`, `starttls`, or `maybestarttls`; the default
  is `none`. These preserve the existing `vacation.pl` SMTP modes.
- `smtp.timeout`: connection and response timeout in seconds; default `120`
- `smtp.local_address`: optional local source address
- `smtp.username` and `smtp.password`: optional SMTP authentication. The PHP
  client supports the server-advertised `PLAIN` and `LOGIN` mechanisms.
  `VACATION_SMTP_PASSWORD` can supply the password to the process without
  storing it in the file and takes precedence over `smtp.password`.
- `smtp.sendmail`: absolute executable path used instead of SMTP when set.
- `message.recipient_delimiter`: optional override for the value inherited
  from PostfixAdmin.
- `message.no_vacation_pattern`: optional regular-expression fragment for To
  addresses that must never trigger a Vacation reply.
- `message.custom_noreply_pattern` and `message.noreply_pattern`: enable and
  define an additional sender exclusion pattern.
- `message.default_noreply_pattern`: optional override for the built-in
  protection against bounce, daemon, list, owner, and request addresses. Omit
  it to retain the built-in expression; an explicitly empty value disables
  that protection and is not recommended.
- `reply.friendly_from`: optional display name used in the From header.
- `reply.account_name`: use the mailbox `name` value instead of
  `reply.friendly_from` when the database value is not empty.
- `reply.replace_from` and `reply.replace_until`: placeholders replaced in the
  body with the Vacation start and end dates.
- `reply.date_format`: PHP date format for those replacements; default `Y-m-d`.
- `logging.syslog`: send transport events to syslog; default `true`.
- `logging.level`: `error`, `info`, or `debug`; default `info`.
- `logging.file_enabled` and `logging.file`: optional append-only file logging.

Without `--config`, the script searches in this order:

1. `/etc/mail/postfixadmin/vacation-php.conf`
2. `/etc/postfixadmin/vacation-php.conf`
3. `vacation-php.conf` in the current working directory

Show the selected path with:

```bash
php /opt/postfixadmin/vacation.php --show-config-path
```

## 7. Optional dedicated database account

The `[database]` section can select a dedicated account instead of the main
PostfixAdmin credentials. Read access is sufficient for diagnostics, while the
production transport follows `vacation.pl` and requires:

- `SELECT` on `vacation`, `alias`, `alias_domain`, and `mailbox`.
- `SELECT`, `INSERT`, `UPDATE`, and `DELETE` on
  `vacation_notification`.
- No schema or privilege-management rights.

Example for standard MySQL or MariaDB names:

```sql
GRANT SELECT ON postfix.vacation TO 'postfix_vacation'@'localhost';
GRANT SELECT ON postfix.alias TO 'postfix_vacation'@'localhost';
GRANT SELECT ON postfix.alias_domain TO 'postfix_vacation'@'localhost';
GRANT SELECT ON postfix.mailbox TO 'postfix_vacation'@'localhost';
GRANT SELECT, INSERT, UPDATE, DELETE ON postfix.vacation_notification
    TO 'postfix_vacation'@'localhost';
```

Adapt the database name, account host, prefix, table mappings, and engine. The
script never creates accounts or grants privileges.

## 8. Optionally import settings from Perl

To read supported SMTP values from the existing Perl configuration:

```bash
php /opt/postfixadmin/vacation.php --init-config \
  --postfixadmin-root /var/www/html/postfixadmin \
  --import-legacy /etc/postfixadmin/vacation.conf
```

The importer reads only simple assignments. In addition to `smtp_server`,
`smtp_server_port`, and `smtp_helo`, it preserves `smtp_ssl`, `smtp_timeout`,
`smtp_client`, `smtp_authid`, and `smtp_authpwd` when present. It does not
execute the Perl file and does not modify it. It also preserves simple
`recipient_delimiter`, `no_vacation_pattern`, `custom_noreply_pattern`,
`noreply_pattern`, and `default_noreply_pattern` assignments. Perl `smtp_ssl`
values map as follows: `0` to `none`, `1` to `ssl`, while `starttls` and
`maybestarttls` retain their names. It also preserves `sendmail_bin`,
`friendly_from`, `accountname_check`, `replace_from`, `replace_until`, and
`date_format`, together with `syslog`, `log_level`, `log_to_file`, and
`logfile`. Perl `strftime` date tokens are converted to their PHP equivalents
when the native configuration is generated.

If `vacation-php.conf` already exists, setup stops without changing it. Use
`--force` only when replacing the PHP configuration is intentional. A file
recognized as Perl configuration is never overwritten, even with `--force`.

## 9. Run the complete check

```bash
php /opt/postfixadmin/vacation.php --check \
  --config /etc/postfixadmin/vacation-php.conf
```

The complete check validates PHP, extensions, effective PostfixAdmin
configuration, database connectivity, the five required tables, and the
configured SMTP or `sendmail` delivery path. It also displays the effective
Vacation domain inherited from PostfixAdmin or selected by the optional
`[vacation]` override. It does not send a message.

## 10. Send a simple SMTP test

```bash
php /opt/postfixadmin/vacation.php --test \
  --config /etc/postfixadmin/vacation-php.conf
```

SMTP server, port, and configured HELO come from `vacation-php.conf` and are
not prompted again. HELO is prompted only when it is neither configured nor
detectable. The test asks only for the envelope addresses:

```text
MAIL FROM [noreply@example.org]:
RCPT TO: admin@example.org
Sending test message from noreply@example.org to admin@example.org using localhost:25...
Test message sent successfully.
```

Enter accepts the proposed `MAIL FROM`. Test values are not saved. The message
is predefined, and the test neither queries vacation records nor reads or
modifies `vacation_notification`.

After SMTP accepts the message, queueing, routing, MX policy, retries, and final
delivery belong to the SMTP server. `vacation.php` does not implement MX policy.

The normal default remains plain local SMTP on `localhost:25`. Optional
implicit TLS/SSL, required STARTTLS, opportunistic STARTTLS, source-address
binding, timeouts, and SMTP authentication preserve the corresponding
`vacation.pl` delivery choices. TLS certificate and hostname verification are
enabled.

## 11. Inspect an incoming message without sending a reply

The inspection action reads a message, evaluates the same header protections
that start at [line 746 of `vacation.pl`](https://github.com/postfixadmin/postfixadmin/blob/master/VIRTUAL_VACATION/vacation.pl#L746),
and reports whether it is eligible for later Vacation processing:

```bash
php /opt/postfixadmin/vacation.php \
  --inspect-message /path/to/message.eml \
  --config /etc/postfixadmin/vacation-php.conf \
  -f sender@example.org -- user#example.org@autoreply.example.org
```

Use `--inspect-message -` to read the message from standard input. The action:

- reads MIME headers with `mailparse` and does not inspect message-body content
- requires From, To, Message-ID, envelope sender, and envelope recipient
- restores the real recipient from the configured `vacation_domain`
- honors the configured recipient delimiter
- rejects spam, virus, bulk, list, automatic-response, and Vacation-loop headers
- rejects invalid, no-reply, self-addressed, and configured excluded addresses
- performs no database writes and sends no message

An intentionally ignored message exits successfully, matching the safe pipe
behavior of `vacation.pl`, and prints the reason:

```text
Message inspection: IGNORED - bulk or list Precedence header found
```

An eligible message reports the normalized envelope information but is not
sent or recorded:

```text
Message inspection: ELIGIBLE - message is eligible for Vacation processing
```

This action validates parsing and safety decisions without invoking the normal
database and delivery stages.

## 12. Production Vacation transport

The normal pipe contract is compatible with `vacation.pl`:

```bash
php /opt/postfixadmin/vacation.php \
  --config /etc/postfixadmin/vacation-php.conf \
  -f sender@example.org -- user#example.org@autoreply.example.org < message.eml
```

The production path:

- parses and rejects unsafe automatic, list, spam, virus, loop, and no-reply mail
- restores the original recipient from `vacation_domain`
- resolves direct Vacation records, aliases, alias domains, and catch-all aliases
- requires an active record within `activefrom` and `activeuntil`
- inserts or updates `vacation_notification` and honors `interval_time`
- releases a newly claimed notification when generation or delivery fails so a
  Postfix retry is not suppressed
- substitutes `$SUBJECT` and configured date placeholders
- applies the configured friendly name or mailbox account name
- builds the UTF-8 plain-text reply with the historical loop-prevention headers
- delivers through SMTP, TLS/SSL/STARTTLS and authentication, or `sendmail`

Ignored messages and recipients without active Vacation exit successfully.
Operational failures exit with status `75` so Postfix can treat them as
temporary failures instead of silently losing the reply.

The legacy `-t yes` transport option generates and prints the reply without
delivery. As in `vacation.pl`, it still performs the database and notification
checks, so use it only with a sender whose notification state may be updated.

Running without an action or pipe arguments prints one usage line and exits
with status `69`.

## 13. Postfix `master.cf` example

After `--check` succeeds and the administrator has validated the transport on
the target installation, the PHP service can replace the Perl command:

```text
vacation    unix  -       n       n       -       -       pipe
  flags=Rq user=vmail argv=/usr/bin/php /opt/postfixadmin/vacation.php -f ${sender} -- ${recipient}
```

This is an example, not an automatic configuration change. The administrator
chooses the service user, path, process limit, rollout, and rollback procedure.
Keep the Perl command available until the local PHP transport has been observed
successfully in production.
