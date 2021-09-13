# Example configuration

The below covers some default(ish) configuration things for using Postfix, Dovecot with PostgreSQL. 

# Postfix

Assumptions :

 * Mail is delivered into /var/mail/vmail/foo@example.com/
 * The user with id 8 is used for ownership of mail files. 
 * PostgreSQL is running on the local server 
 * Dovecot is running on the local server, and SASL is used to allow authenticated clients to mail out.


## /etc/postfix/main.cf 

The proxy: bits are optional, you may need to install an additional postfix package on your server to enable them.

i.e. proxy:pgsql:/path/to/file is equivalent to pgsql:/path/to/file. Use of 'proxy:' may lead to a small performance boost.


```
relay_domains = $mydestination, proxy:pgsql:/etc/postfix/pgsql/relay_domains.cf
virtual_alias_maps = proxy:pgsql:/etc/postfix/pgsql/virtual_alias_maps.cf
virtual_mailbox_domains = proxy:pgsql:/etc/postfix/pgsql/virtual_domains_maps.cf
virtual_mailbox_maps = proxy:pgsql:/etc/postfix/pgsql/virtual_mailbox_maps.cf
virtual_mailbox_base = /var/mail/vmail
virtual_mailbox_limit = 512000000
virtual_minimum_uid = 8
virtual_transport = virtual
virtual_uid_maps = static:8
virtual_gid_maps = static:8
local_transport = virtual
local_recipient_maps = $virtual_mailbox_maps
```

and for Postfix SASL support :

```
# SASL Auth for SMTP relaying
smtpd_sasl_type = dovecot
smtpd_sasl_path = private/auth
smtpd_sasl_authenticated_header = yes
smtpd_sasl_auth_enable = yes
smtpd_sasl_security_options = noanonymous
broken_sasl_auth_clients = yes
```

## /etc/postfix/pgsql/relay_domains.cf

```
user = postfix
password = whatever
hosts = localhost
dbname = postfix
query = SELECT domain FROM domain WHERE domain='%s' and backupmx = true
```

## /etc/postfix/pgsql/virtual_alias_maps.cf

```
user = postfix
password = whatever
hosts = localhost
dbname = postfix
query = SELECT goto FROM alias WHERE address='%s' AND active = true
```

## /etc/postfix/pgsql/virtual_domains_maps.cf

```
user = postfix
password = whatever
hosts = localhost
dbname = postfix
#query = SELECT domain FROM domain WHERE domain='%s'
#optional query to use when relaying for backup MX
query = SELECT domain FROM domain WHERE domain='%s' and backupmx = false and active = true
```

## /etc/postfix/pgsql/virtual_mailbox_limits.cf

```
# Used for quota
user = postfix
password = whatever
hosts = localhost
dbname = postfix
query = SELECT quota FROM mailbox WHERE username='%s'
```

## /etc/postfix/pgsql/virtual_mailbox_maps.cf

```
user = postfix
password = whatever
hosts = localhost
dbname = postfix
query = SELECT maildir FROM mailbox WHERE username='%s' AND active = true
```


# Dovecot

(This is from version 2.2.27, Debian Stretch)


## /etc/dovecot/dovecot.conf 
```
mail_location = maildir:/var/mail/vmail/%u/

namespace inbox {
  type = private
  inbox = yes
  location =
  mailbox Drafts {
    special_use = \Drafts
  }
  mailbox Junk {
    special_use = \Junk
  }
  mailbox Sent {
    special_use = \Sent
  }
  mailbox "Sent Messages" {
    special_use = \Sent
  }
  mailbox Trash {
    special_use = \Trash
  }
  prefix =
}

protocols = "imap pop3"

# Requires certificates ...
#ssl = yes
#ssl_cert = </etc/dovecot/private/something.pem
#ssl_key =  </etc/letsencrypt/certs/something.key

login_greeting = My Mail Server
# http://wiki2.dovecot.org/Authentication/Mechanisms
# login is for outlook express ...
auth_mechanisms = plain login
#auth_debug = yes
#auth_debug_passwords=yes

# Postfix - Sasl auth support.
service auth {
  # Postfix smtp-auth
  unix_listener /var/spool/postfix/private/auth {
    mode = 0660
    user = postfix
    group = postfix
  }
  # Auth process is run as this user.
  user = postfix
  group = postfix
}

service imap {
    executable = imap
}

userdb {
    driver = sql
    args = /etc/dovecot/dovecot-sql.conf
}

passdb {
    driver = sql
    args = /etc/dovecot/dovecot-sql.conf
}

# Needs to match Postfix virtual_uid_maps
first_valid_uid = 8

# disallow or allow plaintext auth.
disable_plaintext_auth = yes

mail_plugins = $mail_plugins zlib

plugin {
    zlib_save_level = 6
    zlib_save = gz
}
protocol imap {
    mail_plugins = $mail_plugins imap_zlib
}

mail_max_userip_connections = 50
log_path = /var/log/dovecot.log

```


## /etc/dovecot/dovecot-sql.conf

Ideally dovecot has a different read only database user.


```
connect = host=localhost dbname=postfix user=dovecot password=whatever

driver = pgsql

# Default password scheme - change to match your Postfixadmin setting.
# depends on your $CONF['encrypt'] setting:
# md5crypt  -> MD5-CRYPT
# md5       -> PLAIN-MD5
# cleartext -> PLAIN
default_pass_scheme = MD5-CRYPT

# Query to retrieve password. user can be used to retrieve username in other
# formats also.

password_query = SELECT username AS user,password FROM mailbox WHERE username = '%u' AND active='1'

# Query to retrieve user information, note uid matches dovecot.conf AND Postfix virtual_uid_maps parameter.
user_query = SELECT '/var/mail/vmail/' || maildir AS home, 8 as uid, 8 as gid FROM mailbox WHERE username = '%u' AND active = '1'
```
