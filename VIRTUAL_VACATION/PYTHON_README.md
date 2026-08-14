# PostfixAdmin Virtual Vacation for Python

This project is an initial Python modernization of
`VIRTUAL_VACATION/vacation.pl`.

> **Current status:** setup and diagnostics only. It does not process mail or
> send vacation replies yet. Do not configure it as the Postfix `vacation`
> transport.

## Installation file

The application is distributed as a single executable script:

```text
vacation.py
```

Copy `vacation.py` to the server. No project directory or package manifest is
required at runtime. The setup command creates the separate configuration file
needed by the installed script.

## 1. Requirements

- Python 3.6 or newer
- PHP CLI
- An existing PostfixAdmin installation
- A working PostfixAdmin database
- A working SMTP service, normally Postfix on `localhost:25`

Python 3.6 and 3.7 are accepted for compatibility with older server
distributions. A newer version maintained by the operating system is
recommended when available.

## 2. Install the script

Choose the installation directory, owner, and group according to the local
Postfix policy. For example, an installation whose pipe service runs as
`vmail` with access through the `mail` group can use:

```bash
install -d -o root -g mail -m 750 /opt/postfixadmin
install -o root -g mail -m 750 vacation.py /opt/postfixadmin/vacation.py
```

This is an example, not a required identity or path. Keeping the executable
owned by `root` prevents the service account from modifying it. Installing the
file does not change Postfix or replace the existing Perl transport.

The upstream Virtual Vacation documentation recommends a separate
`vacation` user and group. Existing installations can retain another service
identity, such as `vmail:mail`, provided that file access and database
permissions are configured accordingly. The Python script does not select or
change this administrative policy.

## 3. Identify the PostfixAdmin directory

The directory must contain both `config.inc.php` and `config.local.php`.
A common location is:

```text
/var/www/html/postfixadmin
```

Use the actual location on your server in the commands below.

If `--postfixadmin-root` is omitted, `vacation.py` searches the current
directory, parent directories, the script location, and common web application
locations. A directory is accepted only when both required configuration files
exist.

When exactly one installation is found, the script uses it and prints the
selected `config.local.php` path. When none is found, the check fails and asks
for `--postfixadmin-root`. When more than one is found, the script does not
choose silently; the administrator must select or provide the intended path.

PostfixAdmin configuration is loaded through PHP CLI using PostfixAdmin's
normal `config.inc.php` and `config.local.php` flow. The Python script does not
attempt to interpret PHP assignments itself.

## 4. Check Python dependencies

Run:

```bash
python3 /opt/postfixadmin/vacation.py --check-dependencies \
  --postfixadmin-root /var/www/html/postfixadmin
```

The command reads the effective PostfixAdmin configuration through PHP and
reports every missing Python module in one run.

Example successful discovery:

```text
Configuration:
  PostfixAdmin config          OK - /var/www/html/postfixadmin/config.local.php
```

Example when PostfixAdmin cannot be found:

```text
Configuration:
  PostfixAdmin config          MISSING - no installation found; use --postfixadmin-root /path/to/postfixadmin

Dependencies:
  Database driver              NOT TESTED - database type is unavailable because PostfixAdmin configuration was not loaded

Result: FAILED
```

Status meanings:

- `OK`: the individual check succeeded.
- `MISSING`: a required file or module was not found.
- `NOT TESTED`: a prerequisite failed, so the check could not be performed.
- `WARNING`: the item works but deserves administrative attention.
- `FAILED`: the item was tested and failed.

`--check-dependencies` only loads configuration and checks Python modules. It
does not connect to the database, inspect tables, contact SMTP, or send mail.
Use the complete `--check` command after creating `vacation-python.conf`.

An `OK` dependency result means that Python can locate and import the selected
driver module. The package constraint shown when a module is missing is an
installation suggestion selected for the running Python version; it is not
reported as the installed package version.

Prefer packages supplied by the operating system. Typical package names are:

```bash
# Debian or Ubuntu with MySQL/MariaDB
apt install python3-pymysql

# Debian or Ubuntu with PostgreSQL
apt install python3-psycopg2

# RHEL, Rocky Linux, or AlmaLinux with MySQL/MariaDB
dnf install python3-PyMySQL

# RHEL, Rocky Linux, or AlmaLinux with PostgreSQL
dnf install python3-psycopg2
```

Package names can vary by distribution and selected Python stream. The check
selects a compatible driver from the Python version and installed modules. For
PostgreSQL it prefers Psycopg 3 on Python 3.8 or newer and falls back to
Psycopg 2 when appropriate. It also prints a compatible `pip` requirement when
using `pip` is possible.

The script never installs packages automatically. Dependency installation is
an explicit administrator action.

## 5. Create the Python configuration

Run:

```bash
python3 /opt/postfixadmin/vacation.py --init-config \
  --postfixadmin-root /var/www/html/postfixadmin
```

The command:

1. Loads PostfixAdmin's effective configuration through PHP CLI.
2. Shows the detected database without displaying its password.
3. Asks for the SMTP server, port, and HELO name.
4. Creates `/etc/postfixadmin/vacation-python.conf`.
5. Applies mode `0640`.

The administrator must select a file group that allows the service user
configured in Postfix `master.cf` to read it. For example, a service running as
`vmail` with group `mail` can use:

```bash
chown root:mail /etc/postfixadmin/vacation-python.conf
chmod 640 /etc/postfixadmin/vacation-python.conf
```

The generated file contains only Python-specific settings:

```ini
[postfixadmin]
root = /var/www/html/postfixadmin

[smtp]
server = localhost
port = 25
helo = mail.example.org

[logging]
syslog = true
level = info
```

Database settings, table names, table prefixes, and `vacation_domain` are
inherited from PostfixAdmin. They are not duplicated in this file.

### General configuration example

The following example shows every accepted configuration key. The generated
file normally needs only `[postfixadmin]`, `[smtp]`, and `[logging]` as shown
above. The `[database]` and `[vacation]` sections are optional overrides and
should usually be omitted so that PostfixAdmin remains the source of truth.

```ini
[postfixadmin]
root = /var/www/html/postfixadmin

[smtp]
server = localhost
port = 25
helo = mail.example.org

# Optional PostfixAdmin overrides. Normally omit this entire section.
[database]
type = mysql
host = 127.0.0.1
port = 3306
name = postfix
user = postfix
password = change-me

# Optional PostfixAdmin override. Normally omit this entire section.
[vacation]
domain = autoreply.example.org

[logging]
syslog = true
level = info
```

Configuration keys:

- `postfixadmin.root`: PostfixAdmin directory containing `config.inc.php` and
  `config.local.php`. The generated file always records it.
- `smtp.server`: SMTP server. Optional; defaults to `localhost`.
- `smtp.port`: SMTP port. Optional; defaults to `25`.
- `smtp.helo`: SMTP HELO name. Optional. If it is empty, the test attempts to
  detect the machine FQDN and prompts only when detection fails.
- `database.type`, `host`, `port`, `name`, `user`, and `password`: optional
  overrides for values inherited from PostfixAdmin. Avoid duplicating them
  unless an installation deliberately needs different database access.
- `vacation.domain`: optional override for PostfixAdmin's `vacation_domain`.
- `logging.syslog`: optional; defaults to `true`.
- `logging.level`: optional; defaults to `info`.

The logging values are reserved for the production mail-processing stage; the
current setup, checks, and SMTP test write their output to the terminal.

Without `--config`, the script searches for `vacation-python.conf` in this
order:

1. `/etc/mail/postfixadmin/vacation-python.conf`
2. `/etc/postfixadmin/vacation-python.conf`
3. `vacation-python.conf` in the current working directory

To display the selected file without running a check, use:

```bash
python3 /opt/postfixadmin/vacation.py --show-config-path
```

Use `--config /another/path/vacation-python.conf` to select a different file
explicitly.

### Optional dedicated database account

An administrator may use the optional `[database]` section with a dedicated
account instead of the main PostfixAdmin credentials. This is useful on systems
that require strict privilege separation. It is an advanced option; by default,
the script inherits the effective PostfixAdmin database configuration.

For the current setup and diagnostic commands, read access is sufficient. The
SMTP test does not access the database. The future production mail processor,
however, cannot use a completely read-only account because it must maintain the
notification history, following the existing `vacation.pl` behavior:

- `SELECT` on `vacation`, `alias`, `alias_domain`, and `mailbox`.
- `SELECT`, `INSERT`, `UPDATE`, and `DELETE` on `vacation_notification`.
- No schema creation, schema modification, or privilege-management rights.

For example, a MySQL or MariaDB installation using the standard database and
table names could grant:

```sql
GRANT SELECT ON postfix.vacation TO 'postfix_vacation'@'localhost';
GRANT SELECT ON postfix.alias TO 'postfix_vacation'@'localhost';
GRANT SELECT ON postfix.alias_domain TO 'postfix_vacation'@'localhost';
GRANT SELECT ON postfix.mailbox TO 'postfix_vacation'@'localhost';
GRANT SELECT, INSERT, UPDATE, DELETE ON postfix.vacation_notification
    TO 'postfix_vacation'@'localhost';
```

This is only an example. The administrator must adapt the database name,
account host, configured table prefix, custom table mappings, and database
engine. The account and password are created and managed outside
`vacation.py`; the script never creates users or grants privileges.

The Python configuration uses a different filename from the existing Perl
configuration:

```text
/etc/postfixadmin/vacation.conf          existing Perl configuration
/etc/postfixadmin/vacation-python.conf   new Python configuration
```

Creating the Python configuration does not replace, rename, or modify
`vacation.conf`.

## 6. Import SMTP defaults from the existing Perl file

This step is optional. To reuse `smtp_server`, `smtp_server_port`, and
`smtp_helo` from the existing Perl configuration, run:

```bash
python3 /opt/postfixadmin/vacation.py --init-config \
  --postfixadmin-root /var/www/html/postfixadmin \
  --import-legacy /etc/postfixadmin/vacation.conf
```

Python reads only supported, simple Perl assignments. It does not execute the
Perl file and does not modify it. The output remains the native INI file
`vacation-python.conf`.

If the legacy file does not exist, omit `--import-legacy`:

```bash
python3 /opt/postfixadmin/vacation.py --init-config \
  --postfixadmin-root /var/www/html/postfixadmin
```

The setup will propose `localhost`, port `25`, and the server hostname as SMTP
defaults. Database settings and `vacation_domain` are still inherited from
PostfixAdmin.

If the Python configuration already exists, the command stops without changing
it. Use `--force` only when replacement of `vacation-python.conf` is
intentional. The script refuses to overwrite a file recognized as Perl
configuration even when `--force` is supplied.

## 7. Run the complete check

Run:

```bash
python3 /opt/postfixadmin/vacation.py --check \
  --config /etc/postfixadmin/vacation-python.conf
```

The command checks:

- Python version
- Required Python modules
- PostfixAdmin configuration loading
- Database connection
- Required PostfixAdmin tables
- SMTP connectivity

The check does not send an email.

## 8. Send a simple test message

Run:

```bash
python3 /opt/postfixadmin/vacation.py --test
```

The SMTP server and port are read from `vacation-python.conf` and are not
prompted again. The test asks for the envelope sender and recipient. The sender
has a proposed value between brackets; press Enter to accept it. Nothing
entered during the test is saved.

```text
MAIL FROM [noreply@example.org]:
RCPT TO: admin@example.org
Sending test message from noreply@example.org to admin@example.org using localhost:25...
Test message sent successfully.
```

This sends a real, predefined plain-text message through the SMTP server in
`vacation-python.conf`. The sender is calculated dynamically as
`noreply@<base-domain>` and is not stored in configuration. For example, the
HELO name `mail.example.org` produces `noreply@example.org`.

The test uses `smtp_helo` from `vacation-python.conf`. If it is not declared,
it attempts to detect the machine FQDN. It prompts for `SMTP HELO` only when
neither value is available.

The test is intentionally temporary: it does not add test settings, does not
query vacation records, and does not read or modify `vacation_notification`.
After the configured SMTP server accepts the test message, queueing, routing,
remote-domain policy, retries, and final delivery are the SMTP server's
responsibility. `vacation.py` does not perform MX policy validation.

## 9. Running without an action

Running `vacation.py` without an action prints one status line and exits without
changing configuration, connecting to the database, contacting SMTP, or
sending mail:

```text
This initial version provides setup and diagnostics only; it does not send vacation replies. Use --init-config, --check, or --test; do not install it in Postfix master.cf yet.
```

## 10. Keep the existing Postfix transport

This prototype does not yet implement mail processing. Keep Postfix pointing
to the existing Perl script. For example:

```text
vacation    unix  -       n       n       -       -       pipe
  flags=Rq user=vmail argv=/opt/postfixadmin/vacation.pl -f ${sender} -- ${recipient}
```

Do not replace that line with `vacation.py` yet.
