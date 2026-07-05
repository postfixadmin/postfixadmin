# Custom Fields with `*_struct_hook`

This is a walkthrough for adding your own field to one of PostfixAdmin's
built-in entities (domains, mailboxes, aliases, admins, and so on) without
patching any PostfixAdmin code. It uses the `*_struct_hook` config callbacks,
which let you add, change, or remove fields on a Handler's `$struct` at runtime.

The handy part is that the Handler builds both its SQL and its edit form
straight from `$struct`, so a field you bolt on through the hook gets full add,
edit, and list support in the web UI without any extra work. It also gets picked
up automatically by `postfixadmin-cli`, so the same field is manageable from the
command line, not just the browser.

The worked example below adds a per-domain `x_backend_host` field and then wires
it into Postfix (`transport_maps`) and Dovecot (proxy). The end result is a
front-end PostfixAdmin box that routes each domain to whichever downstream
server actually holds its mailboxes. The same recipe works for any custom
field, so if you only care about the UI part, jump to
[Just the field](#just-the-field-minimal-recipe) and stop there.

## How the hook works

When a Handler is constructed it reads a config key named after its primary
database table plus `_struct_hook`. If that key names a real function, the
Handler runs `$struct` through it (see `model/PFAHandler.php`):

```php
$struct_hook = Config::read($this->db_table . '_struct_hook');
if (!empty($struct_hook) && is_string($struct_hook) && $struct_hook != 'NO' && function_exists($struct_hook)) {
    $this->struct = $struct_hook($this->struct);
}
```

There's one hook per Handler. The config keys all live (empty by default) in
`config.inc.php`:

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

Your hook is just a function that takes `$struct` and returns it back, modified.
Don't forget to return it.

## Two things to know before you start

1. The hook doesn't touch the database. Adding a field to `$struct` tells
   PostfixAdmin how to *handle* the column, but it won't *create* it for you.
   You add the column yourself with `ALTER TABLE`.
2. Prefix your custom columns with `x_` so they don't collide with anything a
   future PostfixAdmin release might add. There's a naming policy for exactly
   this: <https://sourceforge.net/p/postfixadmin/wiki/Custom_fields/>. That's
   why the example below uses `x_backend_host` rather than a bare
   `backend_host`. The same `x_` rule applies to any custom `$PALANG` strings
   you add (see Step 2).

## Step 1: add the database column

```sql
ALTER TABLE domain ADD COLUMN x_backend_host VARCHAR(255) NULL DEFAULT 'backend1.example.com';
```

## Step 2: surface the field with a hook

Put this in `config.local.php` rather than `config.inc.php`, so an upgrade
won't wipe it out. Point the config key at a function and define that function.
The tidiest way to build the column definition is `PFAHandler::pacol()`, since
that's how the core defines its own fields. Using PHP 8's named arguments keeps
it readable, so you're not left counting positional parameters:

```php
$CONF['domain_struct_hook'] = 'domain_struct_hook';

function domain_struct_hook($struct) {
    $struct['x_backend_host'] = PFAHandler::pacol(
        allow_editing:   1,
        display_in_form: 1,
        display_in_list: 1,
        type:            'text',
        PALANG_label:    'x_backend_host',       // a $PALANG key, not literal text
        PALANG_desc:     'x_backend_host_desc',   // ditto
        default:         'backend1.example.com',
    );
    return $struct;
}
```

`PALANG_label` and `PALANG_desc` are **language keys**, not the visible text.
PostfixAdmin looks them up in `$PALANG` (the interface-strings array), so define
your own strings with a language hook, again prefixing the keys with `x_`:

```php
$CONF['language_hook'] = 'my_language_hook';

function my_language_hook($PALANG, $language) {
    // Set the strings for every language. Add `case "de":` etc. before the
    // default if you want translations. Just make sure the keys exist in
    // every branch (including default) so they're never missing.
    $PALANG['x_backend_host']      = 'Backend Host';
    $PALANG['x_backend_host_desc'] = 'Downstream mail server that hosts this domain';
    return $PALANG;
}
```

(If you'd rather not bother with `$PALANG`, passing literal text as the label
still renders (PostfixAdmin shows the key as-is when it isn't found), but the
language hook is the clean, translatable way and keeps you consistent with core.)

The full `pacol()` parameter list (allow_editing, display_in_form,
display_in_list, type, PALANG_label, PALANG_desc, default, options, and the
rest) is over in [HANDLER_CLASSES.md](HANDLER_CLASSES.md#pacol-parameters).

The same trick works for tweaking or hiding existing fields, for example
`$struct['transport']['display_in_form'] = 0;`.

And that's the whole UI part. The create/edit form now shows a Backend Host box
and writes whatever you type back to the column.

> Heads up on the list view: the edit form updates itself from `$struct`
> automatically, but if you're using the *virtual* list templates and want the
> new column to show up in the list too, you may also have to add it to the
> matching list template. There's a note about this above the `*_struct_hook`
> block in `config.inc.php`.

### Just the field (minimal recipe)

If all you wanted was a custom column in the UI, Steps 1 and 2 are the whole
job. Everything past here is specific to the front-end mail-routing case.

## Step 3: route Postfix mail to the backend

Point `transport_maps` at a query that turns the stored hostname into a Postfix
transport on the fly:

```
# /etc/postfix/mysql/transport_domains.cf
query = SELECT CONCAT('smtp:[', x_backend_host, ']:25') FROM domain WHERE domain='%s' AND active=1
```

The square brackets tell Postfix to skip the MX lookup and hand the mail
straight to that host.

## Step 4: proxy Dovecot logins to the same backend

Reuse the exact same hostname as Dovecot's `proxy` target in your passdb and
userdb SQL. Now an IMAP or POP login that lands on the front end gets proxied
to the backend that actually owns the mailbox:

```
# dovecot-sql.conf.ext
password_query = \
  SELECT m.username AS user, m.password, 'y' AS proxy, d.x_backend_host AS host \
  FROM mailbox m JOIN domain d ON m.domain = d.domain \
  WHERE m.username = '%{user}' AND m.active = 1 AND d.active = 1
```

## Why a separate column instead of `transport`?

Fair question, since PostfixAdmin already ships a `transport` column and you can
absolutely drive Postfix routing from it. The reason this example uses a
separate `x_backend_host` is that the same value gets reused as Dovecot's proxy
host, and `transport` stores a full `smtp:[host]:port` string that Dovecot
can't swallow as-is. Keeping a plain hostname in its own column means both
Postfix and Dovecot read it directly, with no string-slicing on the Dovecot
side. If you only need the Postfix routing and don't care about Dovecot, then
reusing `transport` (maybe with a bit of SQL `CONCAT`/`SUBSTRING` to reshape it)
works just as well.

## The short version

- `*_struct_hook` lets you add, change, or drop Handler fields from config
  alone. No core edits, no fork.
- Add the DB column yourself. The hook only handles it, it doesn't create it.
- Prefix your custom columns (and any custom `$PALANG` keys) with `x_`, per
  the naming policy.
- Define field labels/descriptions as `$PALANG` strings via
  `$CONF['language_hook']`, rather than hardcoding text.
- One plain-hostname column can feed both Postfix `transport_maps` and Dovecot
  proxying, which is exactly what you want for a front-end/back-end mail setup.
