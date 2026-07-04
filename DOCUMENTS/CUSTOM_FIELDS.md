# Custom Fields with `*_struct_hook`

This guide walks through adding your own field to one of PostfixAdmin's
built-in entities (domains, mailboxes, aliases, admins, ...) **without patching
any PostfixAdmin code**. It uses the `*_struct_hook` configuration callbacks,
which let you add, change or remove fields on a Handler's `$struct` at runtime.

Because the Handler builds its SQL and its edit form directly from `$struct`,
a field you add through the hook gets full add / edit / list support in the web
UI for free.

The worked example below adds a per-domain **`backend_host`** field and then
wires it into Postfix (`transport_maps`) and Dovecot (proxy) so a front-end
PostfixAdmin box can route each domain to the downstream server that actually
holds its mailboxes. The same recipe works for any custom field — skip to
[Just the field](#just-the-field-minimal-recipe) if you only need the UI part.

## How the hook works

During construction, every Handler reads a config key named after its primary
database table plus `_struct_hook`, and — if it names a real function — passes
its `$struct` through it (`model/PFAHandler.php`):

```php
$struct_hook = Config::read($this->db_table . '_struct_hook');
if (!empty($struct_hook) && is_string($struct_hook) && $struct_hook != 'NO' && function_exists($struct_hook)) {
    $this->struct = $struct_hook($this->struct);
}
```

One hook is available per Handler. The config keys are defined (empty by
default) in `config.inc.php`:

| Config key | Entity / table |
|------------|----------------|
| `admin_struct_hook` | admins |
| `domain_struct_hook` | domains |
| `alias_struct_hook` | aliases |
| `mailbox_struct_hook` | mailboxes |
| `alias_domain_struct_hook` | alias domains |
| `fetchmail_struct_hook` | fetchmail |
| `dkim_struct_hook` | DKIM keys |
| `dkim_signing_struct_hook` | DKIM signing |

Your hook is a plain function that receives `$struct` and must **return** the
modified `$struct`.

## Before you start: two rules

1. **The hook does not touch the database.** Adding a field to `$struct` teaches
   PostfixAdmin how to *handle* the column; it does not *create* it. You must
   add the column to the table yourself with `ALTER TABLE`.
2. **Prefix your custom columns** to avoid clashing with fields that future
   PostfixAdmin versions might introduce. See the custom-fields naming policy:
   <https://sourceforge.net/p/postfixadmin/wiki/Custom_fields/>. This guide uses
   `backend_host` for readability; in production prefer something like
   `x_backend_host`.

## Step 1 — Add the database column

```sql
ALTER TABLE domain ADD COLUMN backend_host VARCHAR(255) NULL DEFAULT 'backend1.example.com';
```

## Step 2 — Surface the field with a hook

In `config.local.php` (preferred over editing `config.inc.php` so upgrades
don't clobber it), point the config key at a function and define it. The
recommended way to build the column definition is `PFAHandler::pacol()`, which
keeps you in step with how the core defines its own fields:

```php
$CONF['domain_struct_hook'] = 'domain_struct_hook';

function domain_struct_hook($struct) {
    $struct['backend_host'] = PFAHandler::pacol(
        1,                 // editable
        1,                 // display_in_form
        1,                 // display_in_list
        'text',            // type
        'Backend Host',    // label (or a PALANG key)
        'Downstream mail server that hosts this domain', // description
        'backend1.example.com'                           // default
    );
    return $struct;
}
```

`pacol()`'s full parameter list (editable, display_in_form, display_in_list,
type, label, description, default, options, ...) is documented in
[HANDLER_CLASSES.md](HANDLER_CLASSES.md#pacol-parameters).

You can also modify or hide existing fields the same way, e.g.
`$struct['transport']['display_in_form'] = 0;`.

That's all the UI needs — the edit/create form now shows **Backend Host** and
writes it back to the column.

> **List view note:** the edit form is updated from `$struct` automatically.
> If you use the *virtual* list templates and want the new column to appear in
> the list, you may also need to add it to the corresponding list template. See
> the comment above the `*_struct_hook` block in `config.inc.php`.

### Just the field (minimal recipe)

If all you want is a custom column in the UI, Steps 1 and 2 are the whole story.
Everything below is specific to the front-end mail-routing use case.

## Step 3 — Route Postfix mail to the backend

Point `transport_maps` at a SQL query that turns the stored hostname into a
Postfix transport on the fly:

```
# /etc/postfix/mysql/transport_domains.cf
query = SELECT CONCAT('smtp:[', backend_host, ']:25') FROM domain WHERE domain='%s' AND active=1
```

The `[...]` brackets tell Postfix to skip the MX lookup and deliver straight to
that host.

## Step 4 — Proxy Dovecot logins to the same backend

Reuse the very same hostname as Dovecot's `proxy` target in your passdb/userdb
SQL, so an IMAP/POP login on the front end is proxied to the backend that owns
the mailbox:

```
# dovecot-sql.conf.ext
password_query = \
  SELECT m.username AS user, m.password, 'y' AS proxy, d.backend_host AS host \
  FROM mailbox m JOIN domain d ON m.domain = d.domain \
  WHERE m.username = '%{user}' AND m.active = 1 AND d.active = 1
```

## Why a dedicated column instead of `transport`?

PostfixAdmin already ships a `transport` column, and you *can* drive Postfix
routing from it. The reason this example adds a separate `backend_host` is that
the same value is reused as Dovecot's `proxy` host, and `transport` stores a
full `smtp:[host]:port` string that Dovecot cannot consume as-is. Keeping a
plain hostname in its own column lets both Postfix and Dovecot read it directly
without string-slicing on the Dovecot side. If you only need Postfix routing,
reusing `transport` (optionally massaged with SQL `CONCAT`/`SUBSTRING`) is a
perfectly good alternative.

## Summary

- `*_struct_hook` lets you add/modify/remove Handler fields from config alone —
  no core changes, no fork.
- Add the DB column yourself; the hook only handles it.
- Prefix custom columns per the naming policy.
- A single plain-hostname column can feed both Postfix `transport_maps` and
  Dovecot proxying, which is handy for front-end/back-end mail topologies.
