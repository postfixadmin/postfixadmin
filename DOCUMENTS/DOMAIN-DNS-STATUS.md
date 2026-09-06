# Domain DNS status

The domain overview can show a binary DNS status without performing DNS
lookups during page rendering.

## Installation requirements

After checking out or updating the source, run the normal installation command
documented in `INSTALL.md`:

```sh
/bin/bash install.sh
```

An installation that manages Composer itself may instead run
`composer install`; it must regenerate Composer's autoloader because model
classes use a classmap. Then run `public/upgrade.php` so the last result and
check time can be stored on each domain. The normal installation must also
leave `templates_c` writable by the web server.

The PHP runtime must provide `dns_get_record()` and working DNS resolution.
Zone mode additionally requires outbound UDP port 53 access directly to
authoritative nameservers. MX mode requires the configured resolver to answer
MX, A, and AAAA queries.

Select the check performed by adding this setting to `config.local.php`:

```php
$CONF['domain_dns_status_check'] = 1;
```

The supported values are `0` (disabled), `1` (authoritative zone check), and
`2` (MX check). MX mode requires at least one MX target with a resolvable A or
AAAA address. A null MX declaration is considered inactive.

Use the DNS refresh button in the domain overview for an on-demand check, or
schedule the following command if periodic updates are preferred:

```sh
php scripts/domain-dns-check.php
```

For example, add the following entry to the application service account's
crontab to check all domains every hour (adjust the executable and application
paths for the installation):

```cron
0 * * * * /usr/bin/flock -n /var/lib/postfixadmin/dns-check.lock /usr/bin/php /var/www/postfixadmin/scripts/domain-dns-check.php
```

Create `/var/lib/postfixadmin` with write access for that service account first.
The account also needs access to the application's configuration and database.
`flock` is supplied by util-linux on Linux and prevents overlapping cron runs.
Cron is optional: the web refresh button works without it. Both perform
sequential checks; a large group or unresponsive servers can take time. The
web server/PHP request timeout must accommodate a full manual check; otherwise
use the CLI or individual-domain refresh.

## Last-check times and individual refresh

The domain overview shows when the last complete batch for the selected
administrator's domain set finished. The CLI checks all domains and updates
the same timestamp when that complete set is selected. Batch timestamps are
stored separately from individual results, using the existing config table;
no additional schema upgrade is needed for this metadata. A changed domain set
has no complete-check timestamp until it is checked as a group.

The selected domain's virtual-address page shows its saved DNS status and
last-check timestamp beside the domain selector, with a button that refreshes
only that domain. Individual checks do not change the batch timestamp.
Timestamps use the PHP server's configured timezone. Domains without a saved
check show "Not checked".

Only a completed batch advances its timestamp. If a batch fails or is
interrupted, already completed domains retain their individual results but
the batch timestamp stays unchanged. Results describe the last check, not
continuous availability. Opening either page does not trigger DNS queries;
there is no TTL-based expiry or automatic background refresh.

The command exits with status 0 when every checked domain is active and 2 when
one or more domains are inactive. In zone mode, a domain is active when it has
NS delegation and at least one listed authoritative server answers a direct
SOA query with an authoritative, successful response. The host running a zone
check therefore needs outbound DNS access to authoritative servers on UDP port
53.

This is intentionally a binary health signal. It does not classify warnings or
provide advanced DNS diagnostics. A domain has no displayed status until its
first manual or scheduled refresh.
