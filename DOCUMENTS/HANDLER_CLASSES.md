# Handler Classes - Developer Guide

This document explains how Handler classes work in PostfixAdmin, and how to
create new ones.

## Overview

Handler classes extend `PFAHandler` and provide a standardised way to manage
database entities. Once a Handler is created, it automatically works with:

- `list.php` - list/search view
- `edit.php` - create/edit form
- `delete.php` - deletion
- `editactive.php` - toggle active status
- CLI via `postfixadmin-cli`

Existing examples: `DkimHandler`, `FetchmailHandler`, `MailboxHandler`,
`AliasHandler`, `DomainHandler`, `AdminHandler`.

## Creating a Handler

### 1. File and Class Naming

The class name must match the URL parameter: `list.php?table=foo` loads
`FooHandler` from `model/FooHandler.php`. Note that only the first letter is
capitalised:

- `table=dkim` -> `DkimHandler`
- `table=aliasdomain` -> `AliasdomainHandler`

The class autoloader scans `model/` via the `classmap` in `composer.json`.

### 2. Required Properties

```php
class FooHandler extends PFAHandler
{
    protected string $db_table = 'foo';          // database table name
    protected string $id_field = 'id';           // primary key column
    protected string $label_field = 'name';      // column used as display label
    protected ?string $domain_field = 'domain';  // column containing domain (or '' if none)
    protected string $order_by = 'name';         // ORDER BY clause
}
```

If the table has no domain column, set `$domain_field = ''` and override
`no_domain_field()`:

```php
protected function no_domain_field()
{
    // Prevent the default die() - handle domain filtering yourself
    $this->allowed_domains = [];
}
```

### 3. User Mode

If the Handler should be accessible to non-admin users (via `users/`), set
`$user_field` to the column that identifies the owning user:

```php
protected ?string $user_field = 'username';
```

Also add `user_hardcoded_field` to `webformConfig()` (see below). If
`$user_field` is empty and a user (non-admin) tries to access the Handler,
the default `no_user_field()` will call `die()`.

### 4. initStruct() - Field Definitions

Define each field using `pacol()`:

```php
protected function initStruct()
{
    $this->struct = array(
        'id'   => self::pacol($allow_editing, $display_in_form, $display_in_list,
                              $type, $PALANG_label, $PALANG_desc, $default,
                              $options, $multiopt),
    );
}
```

**pacol() parameters:**

| Parameter | Type | Description |
|-----------|------|-------------|
| `$allow_editing` | int | 1 = editable, 0 = read-only |
| `$display_in_form` | int | 1 = show in edit form, 0 = hide |
| `$display_in_list` | int | 1 = show in list view, 0 = hide |
| `$type` | string | Field type (see below) |
| `$PALANG_label` | string | Language key for the field label |
| `$PALANG_desc` | string | Language key for the help/description text |
| `$default` | mixed | Default value |
| `$options` | array | Options for `enum`/`list` types |
| `$multiopt` | array or int | Named array for additional options, or `$not_in_db` as int |

**Field types:**

| Type | Description |
|------|-------------|
| `text` | Text input |
| `mail` | Email address (validated) |
| `pass` | Password (hashed via `pacrypt()` before storage) |
| `b64p` | Base64-encoded password |
| `num` | Numeric |
| `bool` | Boolean (converted to database-specific boolean) |
| `enum` | Dropdown from `$options` array |
| `list` | Multi-select list |
| `txta` | Textarea |
| `txtl` | Text, displayed as list in list view |
| `ts` | Timestamp (auto-formatted per locale) |
| `vnum` | Virtual numeric (not stored in DB) |
| `vtxt` | Virtual text (not stored in DB) |
| `quot` | Quota display |

**Additional options via `$multiopt` array:**

```php
self::pacol(0, 0, 1, 'num', 'label', '', '', array(),
    array('dont_write_to_db' => 1))
```

| Key | Description |
|-----|-------------|
| `not_in_db` | Field doesn't exist in the database table |
| `dont_write_to_db` | Field exists but should not be written on save |
| `select` | Custom SQL expression replacing the column name in SELECT |
| `extrafrom` | SQL fragment added after FROM (for JOINs) |
| `linkto` | Make the value a link (`%s` replaced with ID) |

### 5. initMsg() - Messages

Define messages used for logging and user feedback:

```php
protected function initMsg()
{
    $this->msg['error_already_exists'] = 'PALANG_key';
    $this->msg['error_does_not_exist'] = 'PALANG_key';
    $this->msg['confirm_delete'] = 'PALANG_key';

    if ($this->new) {
        $this->msg['logname'] = 'create_foo';
        $this->msg['store_error'] = 'PALANG_error_key';
        $this->msg['successmessage'] = 'PALANG_success_key';
    } else {
        $this->msg['logname'] = 'edit_foo';
        $this->msg['store_error'] = 'PALANG_error_key';
        $this->msg['successmessage'] = 'PALANG_success_key';
    }
}
```

### 6. webformConfig() - Form/List Configuration

Controls how `list.php` and `edit.php` render the Handler:

```php
public function webformConfig()
{
    return array(
        'formtitle_create' => 'PALANG_create_title',
        'formtitle_edit' => 'PALANG_edit_title',
        'create_button' => 'PALANG_create_button',

        'required_role' => 'admin',           // 'admin' or 'global-admin'
        'listview' => 'list.php?table=foo',   // redirect target after edit
        'early_init' => 0,                    // 1 = call init() before set()
        'user_hardcoded_field' => 'username', // required if users can access
    );
}
```

The `user_hardcoded_field` tells `list.php` / `edit.php` / `delete.php` which
field to automatically set to the logged-in username when in user mode.

### 7. Auto-increment IDs

If your table uses auto-increment for the primary key:

```php
protected function validate_new_id()
{
    if ($this->id != '') {
        $this->errormsg[$this->id_field] = 'auto_increment value, you must pass an empty string!';
        return false;
    }
    return true;
}
```

### 8. Field Validation

Create methods named `_validate_FIELDNAME()` for custom validation:

```php
protected function _validate_ip(string $field, string $value): bool
{
    if (!filter_var($value, FILTER_VALIDATE_IP)) {
        $this->errormsg[$field] = Config::Lang('invalid_ip');
        return false;
    }
    return true;
}
```

These are called automatically by `PFAHandler::set()` when processing input.

### 9. Hooks: preSave() and postSave()

Override these to add logic before/after database writes:

```php
protected function preSave(): bool
{
    // Modify $this->values before save
    // Return false to abort
    return true;
}

protected function postSave(): bool
{
    // Run after successful save (e.g. hook scripts)
    return true;
}
```

### 10. Custom delete()

Override to add permission checks or post-delete actions:

```php
public function delete()
{
    if (!$this->view()) {
        $this->errormsg[] = Config::Lang($this->msg['error_does_not_exist']);
        return false;
    }

    // Custom permission checks using $this->result

    db_delete($this->db_table, $this->id_field, $this->id);

    db_log($this->domain, 'delete_foo', $this->id);
    $this->infomsg[] = Config::Lang_f('pDelete_delete_success', $this->result['label']);
    return true;
}
```

### 11. Post-processing Results

Override `read_from_db_postprocess()` to filter or modify results after they
are read from the database:

```php
protected function read_from_db_postprocess($db_result)
{
    // Filter, modify, or augment results
    return $db_result;
}
```

This is preferred over overriding `read_from_db()` directly.

## Key Internal Variables

| Variable | Description |
|----------|-------------|
| `$this->id` | Current item ID (set by `init()`) |
| `$this->new` | 1 = create mode, 0 = edit mode |
| `$this->values` | Validated values (set by `set()`) |
| `$this->result` | Current item data (set by `view()`) |
| `$this->is_admin` | 1 = admin/superadmin, 0 = user |
| `$this->is_superadmin` | 1 = global admin, 0 = regular admin or user |
| `$this->admin_username` | Admin username (empty for users) |
| `$this->username` | Username (set in user mode) |
| `$this->allowed_domains` | Domains this admin can manage |
| `$this->domain` | Domain of current item |
| `$this->struct` | Field structure definitions |

## Menu Integration

Add URL mappings to `configs/menu.conf`:

```ini
# admin menu
url_foo = list.php?table=foo
url_foo_new = edit.php?table=foo

# user menu (note the ../ prefix for accessing public/ from public/users/)
url_user_foo = ../list.php?table=foo
```

Then reference them in templates using `{#url_foo#}`.

## Lifecycle

1. `list.php`/`edit.php` instantiates the Handler: `new FooHandler($new, $username)`
2. Constructor calls `initStruct()`, `initMsg()`, sets up `$allowed_domains`
3. For edit: `init($id)` loads the item, `set($values)` validates input, `save()` writes to DB
4. `save()` calls `preSave()`, does the INSERT/UPDATE, then calls `postSave()`
5. For list: `getList($condition)` calls `build_select_query()` -> `read_from_db()` -> `read_from_db_postprocess()`
