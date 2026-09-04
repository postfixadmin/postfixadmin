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

The command exits with status 0 when every checked domain is active and 2 when
one or more domains are inactive. In zone mode, a domain is active when it has
NS delegation and at least one listed authoritative server answers a direct
SOA query with an authoritative, successful response. The host running a zone
check therefore needs outbound DNS access to authoritative servers on UDP port
53.

This is intentionally a binary health signal. It does not classify warnings or
provide advanced DNS diagnostics. A domain has no displayed status until its
first manual or scheduled refresh.
