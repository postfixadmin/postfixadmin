# Postfix Admin Installation Guide

Originally by Mischa Peters <mischa at high5 dot net>.

Copyright (c) 2002 - 2005 High5!

Licensed under GPL for more info check LICENSE.TXT

# Requirements

- Postfix
- Apache / Lighttpd
- PHP 8.2 or greater (for web server)
- one of the following databases:
    - MariaDB/MySQL
    - PostgreSQL
    - SQLite

# READ THIS FIRST!

When this is an upgrade from a previous version of Postfix Admin, please read DOCUMENTS/UPGRADE.TXT also!

If you need to setup Postfix to be able to handle Virtual Domains and Virtual Users check out:

- the PostfixAdmin documentation in the DOCUMENTS/ directory
- our wiki at https://sourceforge.net/p/postfixadmin/wiki/

There are also lots of HOWTOs around the web. Be warned that many of them
(even those listed below) may be outdated or incomplete.
Please stick to the PostfixAdmin documentation, and use those HOWTOs only if
you need some additional information that is missing in the PostfixAdmin
DOCUMENTS/ folder.

- https://www.linuxbabe.com/redhat/postfixadmin-create-virtual-mailboxes-centos-mail-server (
  Postfix+MySQL+Postfixadmin+Dovecot)

# Installation steps

## 1. Unarchive new Postfix Admin

(if you installed PostfixAdmin as RPM or DEB package, you can skip this step.)

Assuming we are installing Postfixadmin into /srv/postfixadmin, then something like this should work. Please
check https://github.com/postfixadmin/postfixadmin/releases to get the latest stable release first (the 4.0.1
version/url below is probably stale)

```shell
cd /srv/
wget -O postfixadmin.tgz https://github.com/postfixadmin/postfixadmin/archive/refs/tags/v4.0.1.tar.gz
tar -zxvf postfixadmin.tgz
mv postfixadmin-4.0.1 postfixadmin
```

Alternatively :

```shell
cd /srv
git clone https://github.com/postfixadmin/postfixadmin.git
cd postfixadmin
git checkout postfixadmin-4.0
```

If you're happy to try out newer functionality and perhaps hit unfixed bugs, you can try the 'master' branch by a
`git checkout master` (or don't run the final git checkout in the above).

Since PostfixAdmin 4.0, you'll need to run the `install.sh` script to install necessary third party libraries.

```bash
/bin/bash install.sh
```

Which will

* install the 'composer' tool locally (see https://getcomposer.org, creates: composer.phar) and
* download dependent PHP libraries (`composer install`)
* create a templates_c directory if one does not exist.

## 2.Setup Web Server

Assuming /var/www/html is where your webserver reads from, either create a symlink:

`$ ln -s /srv/postfixadmin/public /var/www/html/postfixadmin`

or setup an alias in your webserver config. For Apache, use:

```apacheconf
Alias /postfixadmin /srv/postfixadmin/public
```

## 3. Setup a Database

With your chosen/preferred database server (i.e. MySQL or PostgreSQL),
you need to create a new database. A good name for this could be:

postfix

The mechanics of creating the database vary depending on which server
you are using. Most users will find using phpMyAdmin or phpPgAdmin the
easiest route.

If you wish to use the command line, you'll need to do something like:

For MySQL:
```mysql
CREATE DATABASE postfix;
CREATE USER 'postfix'@'localhost' IDENTIFIED BY 'choose_a_password';
GRANT ALL PRIVILEGES ON `postfix` . * TO 'postfix'@'localhost';
FLUSH PRIVILEGES;
```


For PostgreSQL:
```postgresql
CREATE USER postfix WITH PASSWORD 'whatever';
CREATE DATABASE postfix OWNER postfix ENCODING 'unicode';
```

For SQLite:

```shell
mkdir /srv/postfixadmin/database
touch /srv/postfixadmin/database/postfixadmin.db
sudo chown -R www-data:www-data /srv/postfixadmin/database
```

(both the directory and the database need to be writeable)

## 4. Configure PostfixAdmin

The **/srv/postfixadmin/config.inc.php** file contains all available config options and their default values.

While you can edit config.inc.php directly, doing so will make future updates harder and is not recommended.

Instead, you should create a **config.local.php** file instead. An example is below.

The most important settings are those for your database server, and the hashing mechanism to be used to store passwords
in your database.

### Example config.local.php

e.g. /srv/postfixadmin/config.local.php :

```PHP
<?php
// values in here override what is in config.inc.php.
$CONF['database_type'] = 'mysqli';
$CONF['database_host'] = 'some-server.domain.com';
$CONF['database_user'] = 'postfix';
$CONF['database_password'] = 'postfixadmin';
$CONF['database_name'] = 'postfix';
$CONF['encrypt'] = 'dovecot:SHA512';
$CONF['configured'] = true;
```

PostfixAdmin does not require write access to any files except the templates_c
directory (smarty cache). You can therefore leave the files owned by root (or
another user); as long as the web server user (e.g. www-data) can read them, it
will be fine.

For templates_c/, allow write access (only) for the web server user (e. g. www-data).
The easiest way to do this is

$ mkdir -p /srv/postfixadmin/templates_c
$ chown -R www-data /srv/postfixadmin/templates_c

## 4a. SELinux (CentOS/Fedora etc)

If you're using e.g. CentOS (or another distribution) which enables SELinux, something like the following will be
necessary:

```shell
semanage fcontext -a -t httpd_sys_content_t "/srv/postfixadmin(/.*)?"
semanage fcontext -a -t httpd_sys_rw_content_t "/srv/postfixadmin/templates_c(/.*)?"
restorecon -R /srv/postfixadmin
```

(Allow the webserver to read /srv/postfixadmin/* and write to /srv/postfixadmin/templates_c/*)

And if the webserver (PHP) needs to make network connections out to a database server, you'll probably need this:

```shell
semanage boolean -m --on httpd_can_network_connect_db
````

## 5. Check settings, and create Admin user

Visit http://yourserver.tld/postfixadmin/setup.php in a web browser.

You need to generate a 'setup_password' which is your way of proving you are the 'admin' responsible for this install. 

The setup.php page will prompt you to generate a setup_password. Alternatively you can generate one manually using : 

```shell
php -r "echo password_hash('some password here', PASSWORD_DEFAULT);"
```

and put the output of that into your config.local.php file - e.g.

```php
$CONF['setup_password'] = '$2y$10$3ybxsh278eAlZKlLf8Zp9e4hmuDaW/TCYd5IZagV7coeAfzBW/GzC';
```

You need to specify that same password in the setup.php page, and click 'Login with setup_password'

You should then see a list of 'OK' messages.

The setup.php script will attempt to create the database structure (or upgrade it if you're coming from a previous
version).

You can then create an Superadmin user (or add another), using the form displayed (you'll need to re-enter the setup
password).

## 6. Use PostfixAdmin

This is all that is needed. Fire up your browser and go to the site that you specified to host Postfix Admin. Login with
the Superadmin user you've just created.

## 7. Integration with Postfix, Dovecot etc.

Now that PostfixAdmin is working, you need to do some configuration in Postfix,
Dovecot etc. so that they use the domains, mailboxes and aliases you setup in
PostfixAdmin.

The files in the DOCUMENTS/ directory explain which settings you need to
do/change.

# And finally 

If you need help, we'd suggest checking the issue tracker on GitHub: https://github.com/postfixadmin/postfixadmin and/or

IRC - a community of people may be able to help in #postfixadmin on Libera.Chat.  See https://web.libera.chat/
