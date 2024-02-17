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
 - Database backend (choose one of: sqlite, mysql, postgresql)
 - Fetchmail (optional)

## Features

 - Unlimited domains/aliases/mailboxes 
 - Optional storage quota support
 - Optional password expiry (beta)
 - Multiple password hashing formats 
 - Supports PostgreSQL, MySQL, MariaDB, SQLite database backends (choose one)
 - Supports retrieval of mail from a remote POP3 server (via fetchmail)
 - Vacation / Autoresponder / Out Of Office support
 - Users have the ability to login, change their password or vacation (out of office) status.
 - Integration with Squirrelmail / Roundcube (via plugins)
 - Optional XMLRPC based API 
 - Supports PHP7.2+ (older versions of PHP should work with older releases)

[Some screenshots of Postfixadmin in action (as admin and user)](DOCUMENTS/screenshots/README.md)

## Releases / Development note

 - While you can install PostfixAdmin from 'git' using the 'master' branch, 'master' is our main development version. It may work. It may contain funky new exciting stuff. It may "eat your data".
 - If you want an easy life, use a published release - see: https://github.com/postfixadmin/postfixadmin/releases or its branch (e.g. postfixadmin_3.3)
 - Latest significant changes should be listed in the appropriate CHANGELOG.TXT file.

## Useful Links

 - [Probably all you need to read (pdf)](http://blog.cboltz.de/uploads/postfixadmin-30-english.pdf)
 - [Docker Images](https://github.com/postfixadmin/docker)
 - [What is it? (txt)](/DOCUMENTS/POSTFIXADMIN.txt)
 - [Installation instructions](/INSTALL.TXT)
 - [Wiki](https://sourceforge.net/p/postfixadmin/wiki/)
 - [IRC channel](irc://irc.libera.chat/#postfixadmin) (#postfixadmin on Libera.chat).


## Related Projects

 - https://github.com/aqeltech/Dockerised-GUI-Mailserver 
 - https://github.com/mailserver2/mailserver 
   mailserver2/mailserver is a simple and full-featured mail server build as a set of multiple docker images. Features:
   Postfix, PostfixAdmin, Dovecot, Rspamd, Clamav, Zeyple, Sieve, Fetchmail, Rainloop, Unbound/NSD, Træfik, {Let's Encrypt,custom,Self-signed Certificate} SSL, Supports PostgeSQL, MySQL, (beta) LDAP backends. Automated builds on DockerHub and Integration tests with Travis CI





