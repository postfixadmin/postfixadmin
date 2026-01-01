![GitHubBuild](https://github.com/postfixadmin/postfixadmin/workflows/GitHubBuild/badge.svg)
[![Coverage Status](https://coveralls.io/repos/github/postfixadmin/postfixadmin/badge.svg?branch=master)](https://coveralls.io/github/postfixadmin/postfixadmin?branch=master)
![GitHub repo size](https://img.shields.io/github/repo-size/postfixadmin/postfixadmin)
[![IRC Chat - #postfixadmin](https://img.shields.io/badge/IRC%20libera-brightgreen.svg)](https://web.libera.chat/#postfixadmin)
  <a href="https://github.com/postfixadmin/postfixadmin/pulse" alt="Activity">
        <img src="https://img.shields.io/github/commit-activity/m/postfixadmin/postfixadmin" /></a>

# PostfixAdmin

An open source, web based interface for managing domains/mailboxes/aliases etc on a Postfix based mail server.

Integrates with :

 - Postfix
 - IMAP/POP3 server (e.g. Dovecot or Courier)
 - Database backend (choose one of: SQLite, MySQL or PostgreSQL)
 - Fetchmail (optional)

## Features

 - Unlimited domains/aliases/mailboxes
 - Optional storage quota support
 - Optional password expiry (beta)
 - Multiple password hashing formats
 - Supports PostgreSQL, MySQL, MariaDB, SQLite database backends (choose one)
 - Supports retrieval of mail from a remote POP3 server (via fetchmail)
 - Vacation / Autoresponder / Out Of Office support
 - Users can login, change their password and/or setup a vacation (out of office) auto responder.
 - Integration with Squirrelmail or Roundcube (via plugins)
 - Optional XMLRPC based API
 - Supports TOTP challenges (and optional IP address based exemptions) for login
 - Supports Application Specific Passwords (where you can have multiple passwords for a mailbox - e.g. one for your mobile device, and a different one for your desktop)
 - Supports Domain Key (DKIM) storage

[Some screenshots of Postfixadmin in action (as admin and user)](DOCUMENTS/screenshots/README.md)

## Hosting Requirements

 - Requires PHP 7.4+
 - Database (MySQL, PostgreSQL or Sqlite)
 - Requires Apache / Nginx webserver (that's capable of integration with PHP)

## Releases

 - While you can install PostfixAdmin from 'git' using the 'master' branch, 'master' is our main development version. It may work. It may contain funky new exciting stuff. It may "eat your data".
 - If you want an easy life, use a published release - see: https://github.com/postfixadmin/postfixadmin/releases or its branch (e.g. postfixadmin_3.3)
 - Latest significant changes should be listed in the appropriate CHANGELOG.TXT file.
 - From 2025/06 we should be using SemVer releases (e.g. 4.0.1-beta1, and not postfixadmin-4.0.1-beta1 for tags etc)
 - Before a new release, try to remember to edit `config.inc.php` and change the version variable. Also update db_min_version in functions.inc.php if there were changes in upgrade.php.

## Development Notes

Github actions is configured to run unit tests, once something is appropriately pushed, or * run `vendor/bin/phpunit tests` (but this will not test against different PHP versions). 

You should be able to use ACT (https://github.com/nektos/act) to run the tests locally, this will require docker etc.

 * `composer format` - should fix styling issues (spaces etc in the code)
 * `composer psalm`  - should detect some obvious errors (e.g. a typo in a function name, referencing an invalid class etc)

## Useful Links

 - [Probably all you need to read (pdf)](http://blog.cboltz.de/uploads/postfixadmin-30-english.pdf)
 - [Docker Images](https://github.com/postfixadmin/docker)
 - [What is it? (txt)](/DOCUMENTS/POSTFIXADMIN.txt)
 - [Installation instructions](/INSTALL.md)
 - [Wiki](https://sourceforge.net/p/postfixadmin/wiki/)
 - [IRC channel](irc://irc.libera.chat/#postfixadmin) (#postfixadmin on Libera.chat).

## Related Projects

 - https://github.com/aqeltech/Dockerised-GUI-Mailserver
 - https://github.com/mailserver2/mailserver
   mailserver2/mailserver is a simple and full-featured mail server build as a set of multiple docker images. Features:
   Postfix, PostfixAdmin, Dovecot, Rspamd, Clamav, Zeyple, Sieve, Fetchmail, Rainloop, Unbound/NSD, Træfik, {Let's Encrypt,custom,Self-signed Certificate} SSL, Supports PostgeSQL, MySQL, (beta) LDAP backends. Automated builds on DockerHub and Integration tests with Travis CI
