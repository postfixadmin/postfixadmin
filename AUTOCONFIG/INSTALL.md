Installation procedure for Autodiscovery configuration tool
============================================================
* Author: [Jacques Deguest](mailto:jack@deguest.jp)
* Created: 2020-03-10
* License: Same as Postfix Admin itself

## Quick background & overview

Autodiscovery is a somewhat standardised features that makes it possible for mail client to find out the proper configuration for a mail account, and to prevent the every day user from guessing the right parameters.

Let's take the example of joe@example.com.

When creating an account on Thurderbird and other who use the same configuration, the mail client will make a http query to <https://www.example.com/.well-known/autoconfig/mail/config-v1.1.xml?emailaddress=joe%2540example.com>

See this page from Mozilla for more information: <https://wiki.mozilla.org/Thunderbird:Autoconfiguration:ConfigFileFormat>

If a dns record exist

For Outlook, the mail client will attempt a POST request to: <https://www.example.com/autodiscover/autodiscover.xml> and submit a xml-based request

For Mac mail and iOS, the user needs to download a `mobileconfig` file, which is basically an xml file, that can be signed.

Unfortunately, there is no auto discovery system for Mac/iOS mail, so you need to redirect your users to the `autoconfig.pl` cgi script under the Postfix Admin web root. You need to pass a `emailaddress` parameter for example <https://www.example.com/postfixadmin/autoconfig.pl?mac_mail=1&emailaddress=joe@example.com>

## Installation

### Dependencies

#### SQL

You need to activate the `uuid-ossp` PostgreSQL extension to use the UUID_V4. You can do that, as an admin logged on PostgreSQL, with `CREATE EXTENSION IF NOT EXISTS "uuid-ossp";`

If you cannot or do not want to do that, edit the sql script for PostgreSQL and comment line 9 and uncomment line 11, comment line 72 and uncoment line 74, comment line 84 and uncomment line 86, comment line 107 and uncomment line 109

#### Perl

The following perl modules are required. Most are standard core modules.

* strict
* IO::File
* CGI v4.44
* Email::Valid v1.202
* Email::Address v1.912
* XML::LibXML v2.0201
* XML::LibXML::PrettyPrint v0.006
* Data::Dumper v2.174
* Scalar::Util v1.50
* Data::UUID v1.224
* File::Basename v2.85
* Cwd v3.78
* File::Temp v0.2309
* File::Spec v3.78
* File::Which v1.23
* JSON v2.34
* DBI v1.642
* TryCatch v1.003002
* Devel::StackTrace v2.04

For PostgreSQL you need `DBD::Pg`. I use version 3.8.1.

For MySQL you need `DBD::mysql` any recent version should do.

For SQLite, you need `DBD::SQLite`. I used version 1.62.

You can install those module using `cpanm` (https://metacpan.org/pod/App::cpanminus) like:

`cpanm --interactive IO::File CGI Email::Valid Email::Address XML::LibXML XML::LibXML::PrettyPrint Data::Dumper Scalar::Util Data::UUID File::Basename Cwd File::Temp File::Spec File::Which JSON DBI TryCatch Devel::StackTrace`

#### Web

* jQuery v3.3.1 (loaded automatically from within the template by calling <https://code.jquery.com/jquery-3.3.1.min.js>)

#### Signature of mobileconfig files for Mac/iOS

You need to have `openssl` installed. I used version 1.0.2g. You would also need ssl certificates installed for server wide or per domain. I recommend using Let's Encrypt <https://letsencrypt.org/> by installing their command line too `certbot`

### SQL

Load the sql script `autoconfig.sql` into your Postfix Admin database. For exaple:

* PostgreSQL : psql -U postfixadmin postfixadmin < autoconfig.sql

* MySQL : mysql -u postfixadmin postfixadmin < autoconfig.sql

* SQLite : sqlite3 /path/to/database.sqlite < autoconfig.sql

This will create 4 separate tables. Rest assured that `autoconfig` does not alter in any way other areas of Postfix Admin database.

### PHP, perl and other web files

Move `AutoconfigHandler.php` under the `model` sub directory in the Postfix Admin root folder, and `autoconfig.php`, `autoconfig.pl`, `autoconfig.css`, `autoconfig.js` and `sprintf.js` under the Postfix Admin web root `public`:

```
mv ./AUTOCONFIG/autoconfig.{css,js,php,pl} ./public/
mv ./AUTOCONFIG/{autoconfig_languages.php,sprintf.js} ./public/
mv ./AUTOCONFIG/AutoconfigHandler.php ./model
mv ./AUTOCONFIG/*.tpl ./templates/
```

#### Additional notes :

`autoconfig.js` is a small file containing event handlers to make the use of the admin interface smooth, and also makes use of Ajax with jQuery 3.3.1. jQuery 3.3.1 is used, and not the latest 3.3.2, because the pseudo selector `:first` and `:last` have been deprecated and are needed here, at least until I can find an alternative solution. If you have one, please let me know!

The general use of javascript is light and only to support workflow, nothing more. Pure css is used whenever possible (such as the switch button). No other framework is used to keep things light.

FontAwesome version 5.12.1 is loaded as import in the css file

`autoconfig.pl` will guess the location of the `config.inc.php` based on the file path. You can change that, such as by specifiying `config.local.php` instead by editing the perl script and change the line `our $POSTFIXADMIN_CONF_FILE = File::Basename::dirname( __FILE__ ) . '/../config.inc.php';` for example into `our $POSTFIXADMIN_CONF_FILE = '/var/www/postfix/config.inc.php';`

`autoconfig.pl` will read the php file by converting it into a json file and save that conversion into a temporary file on the server until the modification time of `config.inc.php` changes.

### DNS

Not required, but to take full advaantage of the idea of auto discovery, you should set up the following dns record in your domain name zones:

```bind
_submission._tcp    IN  SRV 0 1 587 mail.example.com.
_imap._tcp          IN  SRV 0 0 143 mail.example.com.
_imaps._tcp         IN  SRV 0 0 993 mail.example.com.
_pop3._tcp          IN  SRV 0 0 110 mail.example.com.
```

If you want to use a dedicated autodisvoer sub domain, you could set up yoru dns zone with the following record:

```bind
_autodiscover._tcp  IN  SRV 0 10 443 autoconfig.example.com.
```

### Apache

Add the following tp the general config file or to the relevant Vitual Hosts. You can also add it as a conf file under `/etc/apache2/conf-available` if it exists and then issue `a2enconf autoconfig.conf` to activate it (assuming the file name was `autoconfig.conf`)

(Here I presumed Postfix Admin is installed under /var/www/postfixadmin)

```conf
Alias /autoconfig /var/www/postfixadmin/public

<Directory "/var/www/postfixadmin/public/">
	AllowOverride None
	Options Indexes FollowSymLinks ExecCGI
	Require all granted
	Allow from all
	AddHandler cgi-script .cgi .pl
</Directory>

RewriteEngine On
# For Thunderbird and others
RewriteRule "^/.well-known/autoconfig/mail/config-v1.1.xml" "/autoconfig/autoconfig.pl" [PT,L]

# For Outlook; POST request
RewriteRule "^/autodiscover/autodiscover.xml" "/autoconfig/autoconfig.pl?outlook=1" [PT,L]

# For autodiscovery settings in dns
RewriteRule "^/mail/config-v1\.1\.xml(.*)$" "/autoconfig/autoconfig.pl" [PT,L]
```


