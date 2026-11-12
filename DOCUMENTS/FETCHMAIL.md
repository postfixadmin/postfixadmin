# Fetching external mail with fetchmail.pl

PostfixAdmin lets users and admins store accounts on remote POP3/IMAP servers
in the *Fetch Email* tab. PostfixAdmin itself only saves these entries in the
`fetchmail` table. The mail is fetched by `ADDITIONS/fetchmail.pl`, which you
run periodically (e.g. from cron).

You don't need a `.fetchmailrc` per user. For every active entry that is due,
the script:

1. writes a temporary fetchmail configuration file (in `/tmp`) containing that
   one account,
2. runs `/usr/bin/fetchmail -f <tempfile>`,
3. stores fetchmail's output in the `returned_text` column and the current time
   in the `date` column.

Both columns are shown in the fetchmail list in PostfixAdmin, so you can check
whether each account works.

## Enabling the tab

The *Fetch Email* tab is enabled by default:

```php
$CONF['fetchmail'] = 'YES';
```

The fields *MDA*, *extra options*, *SSL certificate path* and *SSL fingerprint*
are only shown if `$CONF['fetchmail_extra_options'] = 'YES';`. Their values are
copied into the fetchmail configuration unchanged, so only enable this if you
trust all users who can edit fetchmail entries.

## Requirements

- `fetchmail` installed as `/usr/bin/fetchmail`
- Perl with `DBI` and the driver for your database (`DBD::Pg` or `DBD::mysql`),
  `MIME::Base64`, `File::Temp`, `Sys::Syslog` and `LockFile::Simple`.
  On Debian/Ubuntu:

  ```sh
  apt install fetchmail libdbi-perl libdbd-pg-perl liblockfile-simple-perl
  # or libdbd-mysql-perl instead of libdbd-pg-perl
  ```

## Configuration

Settings are read from `/etc/mail/postfixadmin/fetchmail.conf` if that file
exists, so you don't need to edit the script. Use Perl syntax:

```perl
$db_type     = 'mysql';      # 'Pg' or 'mysql'
$db_host     = 'localhost';
$db_name     = 'postfix';
$db_username = 'postfix';
$db_password = 'secret';
$run_dir     = '/var/lock/fetchmail';   # must exist, used for the lock and pid file
```

A different config file can be passed with `--config /path/to/file`.

The database user only needs `SELECT` and `UPDATE` on the `fetchmail` table.

## Running it

Copy `ADDITIONS/fetchmail.pl` to e.g. `/usr/local/bin/fetchmail.pl`, make it
executable and run it every minute, e.g. from `/etc/cron.d/postfixadmin-fetchmail`:

```
* * * * * fetchmail /usr/local/bin/fetchmail.pl >/dev/null 2>&1
```

Use a dedicated user (here `fetchmail`) that can read the config file and write
to `$run_dir`. Running it every minute is fine. Each entry has a *poll time* (in
minutes, default 10), and the script skips entries that were fetched more
recently than that. A lock file prevents overlapping runs.

The script logs to syslog (facility `mail`, tag `fetchmail-all`).

## Delivery

Fetched mail is passed to the local MTA for the selected PostfixAdmin mailbox
(`is '<mailbox>' here`), so it goes through Postfix like any other incoming mail.

To deliver directly to Dovecot instead, set the entry's *MDA* to the keyword
`dovecot` (the *MDA* field needs `fetchmail_extra_options`, see above). The script then uses `$dovecot_deliver -d <mailbox>`. Set
`$dovecot_deliver` in the config file if your `deliver`/`dovecot-lda` binary
is not at `/usr/lib/dovecot/deliver`.
