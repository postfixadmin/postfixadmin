# Domain DNS status

The domain overview can show a binary DNS status without performing DNS
lookups during page rendering.

## Installation requirements

After checking out or updating the source, run `/bin/bash install.sh` as
documented in `INSTALL.md`.

An installation that manages Composer itself may instead run
`composer install`; it must regenerate Composer's autoloader because model
classes use a classmap. Then run `public/upgrade.php` so the last result and
check time can be stored on each domain. The normal installation must also
leave `templates_c` writable by the web server.

The PHP runtime must provide `dns_get_record()` and working DNS resolution.
Zone mode additionally requires outbound UDP port 53 access directly to
authoritative nameservers. MX mode requires the configured resolver to answer
MX, A, and AAAA queries.

## Configuration

Add `$CONF['domain_dns_status_check'] = 1;` to `config.local.php`.

The supported values are `0` (disabled), `1` (authoritative zone check), and
`2` (MX check). MX mode requires at least one MX target with a resolvable A or
AAAA address. A null MX declaration is considered inactive.

In zone mode, a domain is active when it has NS delegation and at least one
listed authoritative server answers a direct SOA query with an authoritative,
successful response. This does not check whether email delivery succeeds.

## Manual refresh: no cron required

The application works without cron or a background worker:

- In the domain overview, the DNS refresh button checks the selected
  administrator's domain set.
- Beside the domain selector on the virtual-address page, the DNS refresh
  button checks only that domain.
- The red `DNS (N)` alert appears only when saved results include inactive
  domains. Clicking it filters the affected domains.

To check all domains from a terminal instead, run
`php scripts/domain-dns-check.php` from the PostfixAdmin installation directory.
Exit code 0 means no checked domain is inactive; exit code 2 means at least
one is inactive. This is a global check, not a check limited to a web user's
selected group. Keep configuration value `0` if checking should be disabled.

## Optional automatic refresh with cron

Use cron only if results should be updated periodically without clicking the
button. No cron entry is created automatically.

1. Choose the account that will run the command. It must be able to read
   PostfixAdmin and its `config.local.php`, and connect to its database.
   Root is not required.
2. Identify the absolute paths to PHP CLI and the PostfixAdmin directory.
   PHP CLI must satisfy the DNS requirements above, just as web PHP does.
3. As that account, open `crontab -e` and add the following line, replacing
   `/usr/bin/php` if necessary and `/path/to/postfixadmin` with the real path:

```cron
0 * * * * /usr/bin/php /path/to/postfixadmin/scripts/domain-dns-check.php
```

The five schedule fields mean: minute `0`, every hour, every day of the month,
every month, every day of the week. The command runs once an hour at minute 0,
using cron's configured timezone. This example is for a user's crontab, so
there is no additional username field.

Copy only the command line into crontab. If reading this Markdown file as
plain text, the triple-backtick lines around the example are formatting
markers, not part of the command.

### Optional protection against overlapping scheduled runs

No lock directory or `flock` installation is required for the basic setup
above. Consider this extra protection only if one check might still be running
when cron starts the next one.

`flock` is a Linux command-line utility, commonly provided by the util-linux
package, that holds a lock while another command runs. Processes using the
same lock-file path cannot hold that exclusive lock at the same time. For
example, if the 10:00 check is still running at 11:00, a second scheduled
invocation protected by the same lock can be skipped instead of starting
another DNS check.

The `-n` option means "do not wait": if the lock is already held, exit without
running the PHP command. Otherwise, run it and release the lock when it
finishes. The lock file may remain on disk afterward; its existence alone
does not mean a check is still running.

To use this optional wrapper, first check that `flock` is available with
`command -v flock`. Choose a lock-file path in an existing directory writable
by the cron account, and place `flock -n /path/to/dns-check.lock` before the
PHP command in the cron entry. Replace both executable and file paths with
the actual installation paths. If `flock` is unavailable, keep the basic cron
entry; it is not a PostfixAdmin dependency.

This protection only coordinates commands using the same lock. The web
refresh button does not use it, so it does not prevent a user from starting
a simultaneous manual refresh.

## Execution time

Checks are sequential. A large group or unresponsive nameservers can take
time; scheduling them does not make the check itself faster. For large sets,
prefer CLI/cron or refresh a single domain. A global web refresh must finish
within the PHP/web server request timeout.

## Last-check times and individual refresh

The domain overview shows the oldest saved check time in the selected
administrator's domain set. This makes the least recent result visible without
storing duplicate batch metadata. If any domain has not been checked, the group
shows "Not checked".

The selected domain's virtual-address page shows its saved DNS status and
last-check timestamp beside the domain selector, with a button that refreshes
only that domain. Its refresh can advance the oldest group time when it was the
least recently checked domain.
Timestamps use the PHP server's configured timezone. Domains without a saved
check show "Not checked".

If a group check fails or is interrupted, already completed domains retain
their individual results and the oldest saved time exposes any older or
unchecked result. Results describe the last check, not continuous availability.
Opening either page does not trigger DNS queries;
there is no TTL-based expiry or automatic background refresh.

This is intentionally a binary health signal. It does not classify warnings or
provide advanced DNS diagnostics. A domain has no displayed status until its
first manual or scheduled refresh.
