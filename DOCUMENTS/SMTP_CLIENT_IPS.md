# Binding unauthenticated SMTP clients to domains

A common setup: one central mail server (Postfix, Dovecot, PostfixAdmin)
handles SMTP, IMAP and POP3 for all domains. The websites of these domains run
on separate hosts - containers or VMs that only run an HTTP server and are
usually reached through a reverse proxy. They have no mail server of their
own; outgoing mail (contact forms, notifications, cron output) is forwarded
to the central mail server by a relay-only MTA such as nullmailer, msmtp or a
Postfix null client.

These web hosts typically forward without SMTP authentication, because their
addresses are listed in the mail server's `mynetworks` and
`permit_mynetworks` lets them relay. That raises the question which host may
send with which domain: without further restrictions, every web host can use
any sender address of any domain - the container of `example.com` can send as
`info@example.org`, and a compromised website can send in the name of all
domains on the server.

With `$CONF['domain_smtp_client_ips']` enabled, a superadmin stores the IP
addresses or networks of these hosts per domain. The policy service
`scripts/postfixadmin-smtp-client-policy.php` then lets a listed host send
only with sender addresses of the domain(s) it is listed for.

```
 web host example.com  (10.0.2.10, nullmailer) --\
                                                   >--> central mail server --> Internet
 web host example.org  (10.0.2.11, nullmailer) --/     Postfix + PostfixAdmin

 domain example.com: smtp_client_ips = 10.0.2.10
 domain example.org: smtp_client_ips = 10.0.2.11

 10.0.2.10 sends as noreply@example.com  -> accepted
 10.0.2.10 sends as info@example.org     -> 554 5.7.1
```

## Native Postfix alternative: restriction classes

The same binding can be built with Postfix alone: a `check_client_access`
table maps each host to a restriction class, and each class runs
`check_sender_access` against a table of permitted sender domains, followed by
`reject`. This works well for a small, rarely changing set of hosts.

The policy service does not add a capability Postfix lacks; it avoids having
to generate and maintain these classes. The trade-off:

| | restriction classes | policy service |
|---|---|---|
| data | `main.cf` (`smtpd_restriction_classes`) + one client map + one sender map per class | `domain.smtp_client_ips`, edited in PostfixAdmin |
| new host or domain | edit `main.cf`, `postmap`, `postfix reload` | edit the domain; effective after `cache_ttl` (default 30 s) |
| alias domains | must be added to each sender map | follow the PostfixAdmin alias domains |
| "sender must exist" | additional lookups needed | built in (`require_existing_sender`) |
| deactivated domain | stays permitted until the maps are changed | rejected |
| moving parts | none besides Postfix | a spawned PHP process and its database access |
| failure mode | depends on the selected map backend (e.g. an SQL map); no additional PHP policy process | database or PHP error -> temporary failure (4xx) for the forwarding hosts only, see below |

A single SQL lookup table cannot replace either: Postfix passes one key per
lookup (client address *or* sender), whereas the policy protocol provides both
in one request.

## Enable the field in PostfixAdmin

Add to `config.local.php`:

```php
$CONF['domain_smtp_client_ips'] = 'YES';
```

The database column `domain.smtp_client_ips` is created by the normal
database update: open `setup.php` once after installing a PostfixAdmin
version that contains this feature (it runs `upgrade.php`).

The field *SMTP client IPs* then appears when a superadmin edits a domain and
as a column in the domain list.

* Accepted: IPv4/IPv6 addresses and CIDR networks, separated by spaces,
  commas or semicolons, e.g. `10.0.2.10, 10.0.2.32/28, 2001:db8::10`.
* Networks must be given by their network address: `10.0.2.10/24` is
  rejected (use `10.0.2.0/24`). `/0` and hostnames are rejected.
* Entries are stored in canonical form, equivalent entries only once
  (`10.0.2.10/32` and `::ffff:10.0.2.10` are stored as `10.0.2.10`).
* A host or network may be listed for several domains, and networks may
  overlap: a client matching several entries may use the domains of **all**
  matching entries.
* Alias domains of a listed domain are permitted automatically.

## Configure the policy service

Options in `config.local.php` (defaults in `config.inc.php`); they are
validated when the service starts:

```php
$CONF['smtp_client_policy'] = array(
    // networks of the forwarding hosts - required for 'reject', from the
    // Postfix map (recommended, see "Postfix configuration") ...
    'client_networks_map' => '/etc/postfix/smtp_client_networks.cidr',
    'client_networks_map_class' => 'pfa_smtp_client_policy',   // default
    // ... or listed here (not both)
    // 'client_networks' => array('10.0.2.0/24'),
    // clients inside client_networks that are not listed for any domain
    'unassigned_client_action' => 'reject',   // or 'dunno'
    'require_existing_sender' => true,         // sender must be an active mailbox or alias
    'reject_null_sender' => true,              // reject MAIL FROM:<> from listed clients
    'allow_subdomains' => false,               // also permit sub.example.com
    'cache_ttl' => 30,                         // seconds
    // read-only credentials instead of database_user/database_password,
    // see "Database credentials"
    'database_user' => 'pfa_smtp_policy',
    'database_password' => 'secret',
);
```

### Removing an assignment or deactivating a domain

The policy only knows the hosts listed in PostfixAdmin. What happens when a
host loses its listing:

| change in PostfixAdmin | `unassigned_client_action = 'reject'` (default) | `'dunno'` |
|---|---|---|
| last entry of a host removed | host inside `client_networks`: **rejected** | host is unknown to the policy; `permit_mynetworks` lets it send **with any sender** |
| host's only domain deactivated | **rejected** (the host is still listed) | **rejected** |
| host listed for an active and an inactive domain | may send for the active domain only | same |

Changes take effect after `cache_ttl`. With `'reject'`, the policy networks
(`client_networks_map` or `client_networks`) must cover every network of
forwarding hosts that are in `mynetworks`; hosts in `mynetworks` outside them
(e.g. the mail server itself)
are not affected by the policy. `'dunno'` is only meant for a gradual
introduction.

The service refuses to start with `'reject'` and an empty `client_networks`
(see *Failure behaviour*).

## Postfix configuration

### Query the policy only for the forwarding hosts

`check_policy_service` placed directly in `smtpd_sender_restrictions` would be
queried for **every** SMTP client - including all mail servers and spam
sources on the Internet. That has two drawbacks:

* **Load:** Postfix keeps one policy process per `smtpd` process. Every
  `smtpd` process would start a PHP process that loads PostfixAdmin, also for
  connections the policy has nothing to decide about.
* **Failure propagation:** while the database is unavailable, or after an
  incomplete PostfixAdmin update (missing `vendor/`, schema not yet updated),
  the service answers `DEFER_IF_PERMIT`. Placed for all clients, this would
  also defer all incoming mail from the Internet.

The policy only has something to decide for the forwarding hosts. Postfix can
route just those clients to it with a restriction class and a `cidr` table -
the same networks as `client_networks`:

`/etc/postfix/smtp_client_networks.cidr` (read directly, no `postmap`;
changes need `postfix reload`):

```
# networks of the forwarding hosts - same as smtp_client_policy / client_networks
10.0.2.0/24    pfa_smtp_client_policy
```

An example with comments is in `DOCUMENTS/smtp_client_networks.cidr.example`.

#### One list: read the networks from this map (recommended)

If PostfixAdmin runs on the Postfix host, let the policy read its networks
from this file instead of listing them again in `client_networks`:

```php
$CONF['smtp_client_policy']['client_networks_map'] = '/etc/postfix/smtp_client_networks.cidr';
```

* The policy uses the networks of all lines with the class
  `client_networks_map_class` (default `pfa_smtp_client_policy`). It reads
  the file when a policy process starts - like `smtpd`, which reads it when
  it starts - so after `postfix reload` both use the same state.
* `client_networks` must then stay empty; setting both is an error.
* The file is used strictly: lines this parser does not understand
  (`if`/`endif`, continuation lines, other patterns such as `0.0.0.0/0`),
  lines with host bits set, or no line for the class are a configuration
  error, because they could change what Postfix matches first.
* A missing or unreadable file, a relative path or a path outside
  `open_basedir` is a configuration error as well (see
  *Restricted PHP environments*). Like every configuration error it is logged
  with the reason, and the forwarding hosts get `DEFER_IF_PERMIT 4.3.0`.
* `--check` then verifies the same file without `--cidr-map`: shadowing by
  earlier lines and addresses assigned in PostfixAdmin outside the map.
  `--print-cidr-map` is not needed.

Keep `client_networks` (and the sync steps below) for installations where
PostfixAdmin cannot read the Postfix configuration, e.g. because it runs on
another host.

#### Restricted PHP environments

The file is read by `smtpd` (as root), by the policy (as its system user, PHP
CLI) and by `--check` (as the user running it). Besides the file permissions:

* `open_basedir`: if set for the PHP CLI (`/etc/php/*/cli/`), the map must be
  allowed. Entries are path prefixes, so the file itself can be listed:
  `open_basedir = /srv/postfixadmin/:/tmp/:/etc/postfix/smtp_client_networks.cidr`.
  A path outside `open_basedir` is reported as such.
* A symlink inside the PostfixAdmin directory does not help: PHP resolves
  symlinks before checking `open_basedir`. A hard link is not recommended
  either: tools that replace the file (`sed -i`, editors, configuration
  management) give `/etc/postfix/...` a new file, while the hard link keeps
  the old content without any error.
* Do not move the file into the PostfixAdmin directory and link it from
  `/etc/postfix`: Postfix would then read a file in the area of the web
  application.

#### Using `client_networks` instead: keep both lists in sync

> **Keep this map and `client_networks` in sync.** Only this map decides
> whether the policy is asked. A forwarding host whose address is not routed
> to the policy by this map is never checked and its assignments in
> PostfixAdmin have no effect; the remaining restrictions apply as without
> this feature: inside `mynetworks` it is accepted by `permit_mynetworks` and
> may relay **with any sender domain**; outside `mynetworks` it is refused by
> `reject_unauth_destination` (no open relay either way, but no binding to
> its domain). A network in the map but not in `client_networks` is checked,
> but its unassigned hosts are not rejected (`DUNNO`, then usually
> `permit_mynetworks`).

Generate the map from `client_networks` and verify it (also against all
addresses assigned in PostfixAdmin) - after changing `client_networks`, after
editing the map, and after updates:

```bash
php /srv/postfixadmin/scripts/postfixadmin-smtp-client-policy.php --print-cidr-map \
    > /etc/postfix/smtp_client_networks.cidr
postfix reload
php /srv/postfixadmin/scripts/postfixadmin-smtp-client-policy.php --check \
    --cidr-map=/etc/postfix/smtp_client_networks.cidr
```

The check reports as errors: a policy network or an assigned address that
is not in the map, only partly covered, or matched first by an earlier line
with another action (Postfix uses the first matching line), and lines with
host bits set. It warns about map entries outside `client_networks`, about
exceptions and about lines that are never used (see below).
`--cidr-class=NAME` selects another restriction class name. `if`/`endif`
blocks are not evaluated.

#### Exceptions inside a policy network

Postfix uses the **first** matching line, so a line with another action for
an address or a smaller network inside a policy network only has an effect
**before** the network line:

```
10.0.2.250      DUNNO                      # exception: not checked by the policy
10.0.2.0/24     pfa_smtp_client_policy
10.0.2.251      REJECT                     # never used: line 2 matches first
```

* Before the network line it is an **exception**: Postfix does not ask the
  policy for these clients, so their assignments in PostfixAdmin have no
  effect. With `DUNNO` the remaining restrictions decide (usually
  `permit_mynetworks`: any sender domain); `OK` accepts immediately and also
  skips the later `smtpd_sender_restrictions`, so prefer `DUNNO`; `REJECT`
  refuses the client.
* After the network line it is **never used** for addresses in the network.
* The policy itself only uses the lines with its class; it is simply not
  asked for exceptions. `--check` warns about both cases and reports an
  error if an address assigned in PostfixAdmin is covered by an exception.

Example - which entries of a domain take effect with this map:

| SMTP client IPs of the domain | effect |
|---|---|
| `10.0.2.10` | checked by the policy |
| `10.0.2.250` | **no effect**: line 1 (`DUNNO`) matches first, the policy is not asked |
| `10.0.2.251` | checked by the policy (line 3 is never used) |
| `10.0.2.0/24` | checked, **except** `10.0.2.250` |
| `10.20.0.5` | **no effect**: not in the map, Postfix does not ask the policy |

#### Warnings in the web interface

When a domain is saved, PostfixAdmin compares its SMTP client IPs with the
policy configuration and shows a warning (the entries are saved anyway):

* entries the policy is not asked for - outside the map, or matched first by
  a line with another action (with `client_networks` instead of a map: outside
  `client_networks`);
* networks that contain exceptions or are only partly in a policy network;
* if the check itself is not possible, e.g. the map is not readable for the
  web server user, outside `open_basedir`, or the `smtp_client_policy`
  options are incomplete on the web host.

The web interface reads the map on every save (the policy reads it when its
processes start), so the warning shows the state of the **file**; changes
take effect for Postfix and the policy after `postfix reload`. The command
line (`postfixadmin-cli domain update ...`) prints the same warnings.

#### When changes take effect

| what | read | change effective |
|---|---|---|
| the cidr map (Postfix) | by each `smtpd` process when it starts, then kept in memory; Postfix does not watch the file | after `postfix reload`. Without it only new `smtpd` processes use the new file (old ones end after `max_idle` / `max_use`), so old and new state are mixed for a while |
| the cidr map (policy, with `client_networks_map`) | by each policy process when it starts | like the options |
| `client_networks` and the other options | by each policy process when it starts (one per `smtpd` process) | after `postfix reload`, otherwise when the policy processes end (`smtpd_policy_service_max_idle`, `smtpd_policy_service_max_ttl`) |
| addresses of a domain (PostfixAdmin) | from the database, cached per policy process | after `cache_ttl`, no reload needed |

Consequences:

* Change the map (and, without `client_networks_map`, `client_networks`),
  run `postfix reload`, then `--check` (`--cidr-map=...` without
  `client_networks_map`). The check reads the **file**; it cannot see
  whether Postfix has already loaded it.
* The map is the authority: an address assigned in PostfixAdmin outside the
  map is not enforced, and `--check` reports it as an error. PostfixAdmin
  itself does not validate entries against the map or `client_networks`.
* The check runs on demand only - e.g. from a timer or cron job, or after
  every change; nothing checks it per request (clients missing from the map
  never reach the policy, so they cannot be detected there).
* Postfix, the policy user and `--check` must be able to read the map
  (default: `root:root`, mode `0644`).

#### Changing the network of the forwarding hosts

The policy compares the current client address with the stored entries, so
renumbering the forwarding hosts needs the database and the map changed in
the right order. A rejection (`5.7.1`) is permanent: the forwarding host does
not retry; depending on the software the mail is bounced or dropped.

| situation | result |
|---|---|
| hosts already use the new network, database still has the old addresses | new addresses are unassigned -> **rejected** (inside `client_networks`), or not checked at all if the new network is not yet in the map (then `permit_mynetworks` lets them send with any sender) |
| old addresses stay in the database after the change | not used while the old network is outside the map; `--check` reports them. They become effective again for whichever host later gets such an address once the old network is added to the map again - remove them |
| old network removed from the map but still in `mynetworks` | hosts there are no longer checked and may send with any sender - remove it from `mynetworks` as well |

Recommended order:

1. Add the new network to the map (and to `client_networks`, if used; keep
   the old one), `postfix reload`.
2. Add the new address of each host to its domains, in addition to the old
   one.
3. Move the hosts to their new addresses.
4. Remove the old addresses from the domains.
5. Remove the old network from the map, `client_networks` (if used) and
   `mynetworks`, `postfix reload`, and run `--check` (no errors = no stale
   entries left).

`main.cf` - the lookup must come **before** `permit_mynetworks`:

```
smtpd_restriction_classes = pfa_smtp_client_policy
pfa_smtp_client_policy = check_policy_service unix:private/pfa-smtp-client-policy
pfa-smtp-client-policy_time_limit = 3600

smtpd_sender_restrictions =
    check_client_access cidr:/etc/postfix/smtp_client_networks.cidr
    permit_mynetworks
    reject_authenticated_sender_login_mismatch
    permit_sasl_authenticated
    ...
```

If `smtpd_restriction_classes` already lists other classes, add
`pfa_smtp_client_policy` to that list.

For a client inside the table, Postfix runs the class, i.e. asks the policy;
its `REJECT` ends the evaluation, its `DUNNO` continues with
`permit_mynetworks`. All other clients never reach the policy - their mail
does not depend on PHP, PostfixAdmin or the database. A failure of the policy
service then only delays mail from the forwarding hosts; they retry.

`smtpd_delay_reject = yes` (the default) is required so that the sender is
known when the policy is queried.

`master.cf`:

```
pfa-smtp-client-policy unix  -  n  n  -  0  spawn
  user=pfa-smtp-policy argv=/usr/bin/php /srv/postfixadmin/scripts/postfixadmin-smtp-client-policy.php
```

The service runs as its own system user, which needs read access to the
PostfixAdmin installation including `config.local.php`, e.g. as a member of
the group that owns the installation:

```bash
useradd --system --no-create-home --shell /usr/sbin/nologin -G www-data pfa-smtp-policy
```

### Database credentials

`config.local.php` normally contains the database user of PostfixAdmin, which
has write access. The policy only reads; it should neither use nor be able to
read those credentials - a compromise of the policy process (its input comes
from SMTP clients) would otherwise give write access to the mail database.

1. Create a read-only user (MySQL/MariaDB example; PostgreSQL accordingly):

   ```sql
   CREATE USER 'pfa_smtp_policy'@'localhost' IDENTIFIED BY 'secret';
   GRANT SELECT (domain, active, smtp_client_ips) ON postfix.domain TO 'pfa_smtp_policy'@'localhost';
   GRANT SELECT (alias_domain, target_domain, active) ON postfix.alias_domain TO 'pfa_smtp_policy'@'localhost';
   GRANT SELECT (address, active)  ON postfix.alias   TO 'pfa_smtp_policy'@'localhost';
   GRANT SELECT (username, active) ON postfix.mailbox TO 'pfa_smtp_policy'@'localhost';
   ```

2. Move the PostfixAdmin credentials into a file only the web server user can
   read, and load it from `config.local.php` if readable:

   ```bash
   # /etc/postfixadmin/db-credentials.php
   # <?php
   # $CONF['database_user'] = 'postfixadmin';
   # $CONF['database_password'] = '...';
   chown www-data: /etc/postfixadmin/db-credentials.php
   chmod 0400 /etc/postfixadmin/db-credentials.php     # owner only - not the www-data group
   ```

   ```php
   // config.local.php
   if (is_readable('/etc/postfixadmin/db-credentials.php')) {
       require '/etc/postfixadmin/db-credentials.php';
   }
   $CONF['smtp_client_policy']['database_user'] = 'pfa_smtp_policy';
   $CONF['smtp_client_policy']['database_password'] = 'secret';
   ```

   The web interface (running as `www-data`) reads the file; the policy user
   (only a member of the `www-data` group) cannot and uses the read-only user.

3. Check as the policy user:

   ```bash
   sudo -u pfa-smtp-policy php /srv/postfixadmin/scripts/postfixadmin-smtp-client-policy.php --check
   ```

   `--check` validates the configuration and the database query and prints the
   number of assignments (exit code 1 on errors); with `--cidr-map` it also
   verifies the Postfix map (see above). It warns if no
   `database_user` is set for the policy, or if the PostfixAdmin database
   password is readable by the user running the check. Run it after every
   PostfixAdmin update as well.

SQLite has no database users; the policy user then needs write access to the
database file (required by PostfixAdmin's SQLite connection), so this
separation only applies to MySQL/MariaDB and PostgreSQL.

The service uses the PostfixAdmin database layer, so it works with every
supported backend (MySQL/MariaDB, PostgreSQL, SQLite) and honours
`database_prefix` and `database_tables`.

## Behaviour

| Situation | Result |
|---|---|
| SASL authenticated session | `DUNNO` - `smtpd_sender_login_maps` applies |
| client not listed, inside `client_networks` | `REJECT 5.7.1` (or `DUNNO` with `'dunno'`) |
| client not listed, outside `client_networks` | `DUNNO` |
| client listed only for inactive domains | `REJECT 5.7.1` |
| sender domain is one of the client's active domains or their alias domains | `DUNNO` (with `require_existing_sender`: only if the address exists; a catch-all alias counts) |
| other sender domain | `REJECT 5.7.1` |
| null sender | `REJECT 5.7.1` (unless `reject_null_sender` is false) |

`DUNNO` means the remaining restrictions decide - for a listed host usually
`permit_mynetworks`.

### Failure behaviour

| Failure | Result |
|---|---|
| invalid configuration (including `client_networks_map` missing, unreadable, outside `open_basedir` or not usable) | the service keeps running and answers every request with `DEFER_IF_PERMIT 4.3.0`; the reason is logged |
| database error (e.g. database server stopped or restarted) | `DEFER_IF_PERMIT 4.3.0` for the affected request; the process then exits, so the next request is handled by a new process with a new database connection |
| invalid request: `request` is not `smtpd_access_policy`, `client_address` missing or not an IP address, `sender` missing (it is empty for the null sender), or an attribute line without `=` | `DEFER_IF_PERMIT 4.3.0 SMTP client policy: invalid request` for that request (Postfix always sends these attributes) |
| over-long attribute line (more than 8 KB) | `DEFER_IF_PERMIT 4.3.0` for that request |
| service exits or does not answer within `smtpd_policy_service_timeout` | Postfix answers `451 4.3.5` (`smtpd_policy_service_default_action`) |

In all cases nothing is permitted unchecked; senders retry later. With the
restriction class shown above only the forwarding hosts are affected; if the
policy were queried for all clients, the temporary failure would also defer
mail from the Internet.

Rejections are logged via syslog (facility `mail`, tag
`postfix/postfixadmin-smtp-client-policy`).

## Web host configuration (nullmailer example)

On each web host, forward all mail to the internal address of the central
mail server and make sure the envelope sender is an address of the domain the
host is listed for:

```bash
apt install nullmailer
echo "10.0.1.2 smtp --port=25"   > /etc/nullmailer/remotes      # central mail server
echo "example.com"               > /etc/nullmailer/defaultdomain
echo "example.com"               > /etc/nullmailer/defaulthost
echo "webmaster@example.com"     > /etc/nullmailer/adminaddr     # where root's mail goes
```

With `require_existing_sender` the sender (e.g. `noreply@example.com`) must
exist in PostfixAdmin as a mailbox or alias; an alias also makes sure bounces
reach someone.

The web hosts must connect to the mail server directly (internal network).
If they reach it through NAT, a proxy or the public address of the same
host, Postfix sees the address of that device instead of the web host and
the binding cannot work.

## Tests

* PHPUnit (part of `composer test`, all database backends of the CI):
  `tests/SmtpClientIpListTest.php` (parsing, canonical form, matching),
  `tests/SmtpClientPolicyTest.php` (decisions, assignment removal and domain
  deactivation, overlapping networks, configuration validation, protocol
  handling, database failure), `tests/DomainSmtpClientIpsTest.php` (form
  field, migration).

  ```bash
  vendor/bin/phpunit tests/SmtpClientIpListTest.php tests/SmtpClientPolicyTest.php tests/DomainSmtpClientIpsTest.php
  ```

* Integration test with a real Postfix (not part of the CI; needs root,
  `postfix`, `swaks`, `php-sqlite3`; run on a disposable machine):

  ```bash
  bash tests/integration/smtp-client-policy/run.sh
  # with a real database server, which is stopped and started during the test
  # (needs mariadb-server and php-mysql; creates/drops pfa_policy_test):
  PFA_TEST_DB=mariadb bash tests/integration/smtp-client-policy/run.sh
  ```

  It starts a separate Postfix instance on 127.0.0.1:2525 with the
  restriction class setup shown above, simulates client addresses with
  XCLIENT and checks restriction ordering, allowed and forbidden senders,
  unassigned clients, assignment removal, domain deactivation, null sender,
  database failure and recovery (with MariaDB: server stopped and started),
  termination / timeout of the policy service, and that clients outside the
  policy networks are not affected by these failures.
