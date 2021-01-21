#
# Postfix Admin
# by Mischa Peters <mischa at high5 dot net>
# Copyright (c) 2002 - 2005 High5!
# Licensed under GPL for more info check GPL-LICENSE.TXT
#

REQUIREMENTS
------------
- Postfix
- Apache / Lighttpd 
- PHP 7.0 or greater (for web server)
- one of the following databases:
  - MariaDB/MySQL
  - PostgreSQL
  - SQLite


READ THIS FIRST!
----------------
When this is an upgrade from a previous version of Postfix Admin, please read 
DOCUMENTS/UPGRADE.TXT also!

If you need to setup Postfix to be able to handle Virtual Domains and Virtual
Users check out:

  - the PostfixAdmin documentation in the DOCUMENTS/ directory
  - our wiki at https://sourceforge.net/p/postfixadmin/wiki/

There are also lots of HOWTOs around the web. Be warned that many of them 
(even those listed below) may be outdated or incomplete. 
Please stick to the PostfixAdmin documentation, and use those HOWTOs only if
you need some additional information that is missing in the PostfixAdmin 
DOCUMENTS/ folder.
  - https://www.linuxbabe.com/redhat/postfixadmin-create-virtual-mailboxes-centos-mail-server (Postfix+MySQL+Postfixadmin+Dovecot)
  - http://en.gentoo-wiki.com/wiki/Virtual_mail_server_using_Postfix,_Courier_and_PostfixAdmin (Postfix+MySQL+Postfixadmin+Courier)


1. Unarchive new Postfix Admin
------------------------------

(if you installed PostfixAdmin as RPM or DEB package, you can obviously skip this step.)

Assuming we are installing Postfixadmin into /srv/postfixadmin, then something like this should work. Please check https://github.com/postfixadmin/postfixadmin/releases to get the latest stable release first (the 3.2.4 version/url below is probably stale)

  $ cd /srv/
  $ wget -O postfixadmin.tgz https://github.com/postfixadmin/postfixadmin/archive/postfixadmin-3.2.4.tar.gz
  $ tar -zxvf postfixadmin.tgz
  $ mv postfixadmin-postfixadmin-3.2 postfixadmin

Alternatively :

  $ cd /srv
  $ git clone https://github.com/postfixadmin/postfixadmin.git
  $ cd postfixadmin
  $ git checkout postfixadmin-3.2.4

2. Setup Web Server
-------------------

Assuming /var/www/html is where your webserver reads from, either create a symlink:

  $ ln -s /srv/postfixadmin/public /var/www/html/postfixadmin

or setup an alias in your webserver config. For Apache, use:

  Alias /postfixadmin /srv/postfixadmin/public

3. Setup a Database
-------------------

With your chosen/preferred database server (i.e. MySQL or PostgreSQL), 
you need to create a new database. A good name for this could be :

  postfix

The mechanics of creating the database vary depending on which server 
you are using. Most users will find using phpMyAdmin or phpPgAdmin the
easiest route.

If you wish to use the command line, you'll need to do something like :

For MySQL:
  CREATE DATABASE postfix;
  CREATE USER 'postfix'@'localhost' IDENTIFIED BY 'choose_a_password';
  GRANT ALL PRIVILEGES ON `postfix` . * TO 'postfix'@'localhost';
  FLUSH PRIVILEGES;

For PostgreSQL:
  CREATE USER postfix WITH PASSWORD 'whatever';
  CREATE DATABASE postfix OWNER postfix ENCODING 'unicode';

For SQLite:

```bash
  mkdir /srv/postfixadmin/database
  touch /srv/postfixadmin/database/postfixadmin.db
  sudo chown -R www-data:www-data /srv/postfixadmin/database
```
  (both the directory and the database need to be writeable)

4. Configure PostfixAdmin so it can find the database
-----------------------------------------------------

Create /srv/postfixadmin/config.local.php file for your local configuration:

<?php
$CONF['database_type'] = 'mysqli';
$CONF['database_user'] = 'postfix';
$CONF['database_password'] = 'postfixadmin';
$CONF['database_name'] = 'postfix';

$CONF['configured'] = true;
?>

See config.inc.php for all available config options and their default value.

You can also edit config.inc.php instead of creating a config.local.php,
but this will make updates harder and is therefore not recommended.

The most important settings are those for your database server.

You must also change the line that says :

$CONF['configured'] = false;

to

$CONF['configured'] = true;


PostfixAdmin does not require write access to any files except the templates_c 
directory (smarty cache). You can therefore leave the files owned by root (or
another user); as long as the web server user (e.g. www-data) can read them, it
will be fine.

For templates_c/, allow write access (only) for the web server user (e. g. www-data).
The easiest way to do this is   

 $ mkdir -p /srv/postfixadmin/templates_c
 $ chown -R www-data /srv/postfixadmin/templates_c

(If you're using e.g. CentOS or another distribution which enables SELinux, something like the following may be necessary as well :

```bash
    chcon -R -t httpd_sys_content_rw_t /usr/share/postfixadmin/templates_c
```
)

5. Check settings, and create Admin user
----------------------------------------

Hit http://yourserver.tld/postfixadmin/setup.php in a web browser.

You need to generate a 'setup_password' which is your way of proving you are the 'admin' responsible for this install. Alternatively, run :

```bash
    php -r "echo password_hash('some password here', PASSWORD_DEFAULT);"
```

and put the output of that into your config.local.php file - e.g. 

```PHP
$CONF['setup_password'] = '$2y$10$3ybxsh278eAlZKlLf8Zp9e4hmuDaW/TCYd5IZagV7coeAfzBW/GzC';
```

You need to specify that same password in the setup.php page, and click 'Login with setup_password'

You should then see a list of 'OK' messages. 

The setup.php script will attempt to create the database structure 
(or upgrade it if you're coming from a previous version). 

You can then create an Superadmin user (or add another), using the form displayed (you'll need to re-enter the your setup password).

6. Use PostfixAdmin
-------------------

This is all that is needed. Fire up your browser and go to the site that you
specified to host Postfix Admin.

7. Integration with Postfix, Dovecot etc.
-----------------------------------------

Now that PostfixAdmin is working, you need to do some configuration in Postfix,
Dovecot etc. so that they use the domains, mailboxes and aliases you setup in
PostfixAdmin.

The files in the DOCUMENTS/ directory explain which settings you need to
do/change.

7. XMLRPC Integration (OPTIONAL!)
--------------------------------

See ADDITIONS/squirrelmail-plugin
See xmlrpc.php - only a subset of Postfixadmin's functionality is currently exposed.
See config.inc.php - see xmlrpc_enabled key (defaults to off).

You'll need to install a copy of the Zend Framework (version 1.12.x) within Postfixadmin
or your PHP include_path (see header within xmlrpc.php).
NOTE: The XMLRPC interface is _not compatible_ with Zend Framework version 2.x.
You'll need to enable the xmlrpc link (see config.inc.php)

8. More information
-------------------

The code and issue tracker is on GitHub:
    https://github.com/postfixadmin/postfixadmin

IRC - a community of people may be able to help in #postfixadmin on irc.freenode.net. 
    See http://webchat.freenode.net/

Legacy forum posts are on SourceForce at 
    https://sourceforge.net/projects/postfixadmin
