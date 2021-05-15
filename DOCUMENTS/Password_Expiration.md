# Description

This extension adds support for password expiration.
It is designed to have expiration on users passwords. An email is sent when the password is expiring in 30 days, then 14 days, then 7 days.
It is strongly inspired by https://abridge2devnull.com/posts/2014/09/29/dovecot-user-password-expiration-notifications-updated-4122015/, and adapted to fit with Postfix Admin & Roundcube's password plugin

Expiration unit is day

Expiration value for domain is set through Postfix Admin GUI

# Installation

Password Expiration is merged with PostfixAdmin - so no additional database changes should be necessary.


## Database Fields 

 * mailbox.password_expiry - timestamp, when the mailbox password expires.
 * domain.password_expiry - default duration for when a password will expire

Changes in MySQL/MariaDB mailbox table (as defined in `$CONF['database_tables']` from config.inc.php):

## Changes in Postfix Admin :

To enable password expiration, add the following to your config.inc.php file:

`$CONF['password_expiration'] = 'YES';`

## RoundCube Password Plugin

If you are using Roundcube's password plugin, you should also adapt the `$config['password_query']` value.

I recommend to use:

`$config['password_query'] = 'UPDATE mailbox SET password=%c, modified = now(), password_expiry = now() + interval 90 day';`

of course, you may adapt to the expiration value to suit.


## Changes in Dovecot (adapt if you use another LDA)

Edit dovecot-mysql.conf file, and replace the user_query (and only this one) to be based on this query:

```
password_query = SELECT username as user, password, concat('/var/vmail/', maildir) as userdb_var, concat('maildir:/var/vmail/', maildir) as userdb_mail, 20001 as userdb_uid, 20001 as userdb_gid, m.domain FROM mailbox m, domain d where d.domain = m.domain and m.username = '%u' AND m.active = '1' AND (m.password_expiry > now() or d.password_expiry = 0)
```


Of course, you may require to adapt the uid, gid, maildir and table to your setup.


## Changes in system

You need to have a script running on a daily basis to check password expiration and send emails 30, 14 and 7 days before password expiration. An example is given below.

Edit the script to adapt the variables to your setup.

This script is using `postfixadmin.my.cnf` to read credentials, which might look a bit like : 

```ini
[client]
user = me
password = secret
host = hostname
```

Edit this file to enter a DB user that is allowed to access (read-write) your database. This file should be protected from any user (chmod 400).

### Expiration Script 

```bash
#!/bin/bash

#Adapt to your setup

POSTFIX_DB="postfixadmin"
MYSQL_CREDENTIALS_FILE="postfixadmin.my.cnf"

REPLY_ADDRESS=noreply@example.com

# Change this list to change notification times and when ...
for INTERVAL in 30 14 7
do
    LOWER=$(( $INTERVAL - 1 ))

    QUERY="SELECT username,password_expiry FROM mailbox WHERE password_expiry > now() + interval $LOWER DAY AND password_expiry < NOW() + interval $INTERVAL DAY"

    mysql --defaults-extra-file="$MYSQL_CREDENTIALS_FILE" "$POSTFIX_DB" -B -N -e "$QUERY" | while IFS=$'\t' read -a RESULT ; do
        echo -e "Dear User, \n Your password will expire on ${RESULT[1]}" | mail -s "Password 30 days before expiration notication" -r $REPLY_ADDRESS  ${RESULT[0]} 
    done
done

```
