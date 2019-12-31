# Add 2fa to postfix.admin admins

1. create fields with ADDITIONS/create_2fa_fields.sql

```bash
alex@vosjod:/Applications/MAMP/htdocs/postfixadmin(master)$ mysqlmamp -u root -p postfix < ADDITIONS/create_2fa_fields.sql
Enter password:
alex@vosjod:/Applications/MAMP/htdocs/postfixadmin(master)$
```

2. use config.local.php.example.2fa example file enabling 2fa

```php
$CONF['2fa_enabled'] = true;
````

3. Add secret to admin from "**Edit a domain admin**" form (16 length)
4. Go to "Admin List" and scan QR-Code

