# Mailbox user display-name editing

Mailbox users can edit their own display name through the shared handler-based
editor when the installation administrator enables it in `config.local.php`:

```php
// All domains:
$CONF['edit_mailbox_name'] = 'YES';

// Or only these domains (use lowercase domain names):
$CONF['edit_mailbox_name'] = ['example.org', 'example.net'];
```

The default is `'NO'`. An empty domain list also disables the feature. This is
one central policy; there are no separate per-domain settings or approvals.
Administrator mailbox editing is unaffected by this user policy.

Only `mailbox.name` and the normal modification timestamp are updated. Users
cannot change their address, password, quota, forwarding, activation or TOTP
through this form. The handler checks ownership and policy again before saving.
Display names accept Unicode text and at most 255 characters; control characters
are rejected. Empty names remain permitted, as in administrative mailbox editing.

The existing `mailbox_postedit_script` still runs with its usual mailbox, domain,
maildir and stored quota arguments. Script failures retain the existing warning
behavior: the database change is already saved. Alias records are not updated.
No welcome message, administrator notification or schema upgrade is needed.

External applications may read this field as a display name, including SOGo in
installations configured with the PostfixAdmin database as a user source. How
quickly a change appears depends on the application's lookup and cache settings.
Changing this field does not change the authenticated mailbox address or enforce
sender names in other mail clients.
