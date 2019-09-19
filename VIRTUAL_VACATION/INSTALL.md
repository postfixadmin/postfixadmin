# About Virtual Vacation

AKA 'An out of office' automated email response.

The vacation script runs as service within Postfix's master.cf configuration file.
Mail is sent to the vacation service via a transport table mapping.
When users mark themselves as away on vacation, an alias is added to their account 
sending a copy of all mail to them to the vacation service.

e.g. mail to billy@goat.com will be delivered to 

 * billy@goat.com AND 
 * billy#goat.com@autoreply.goat.com

Mail to @autoreply.goat.com is caught by the vacation.pl script and a reply 
will be sent based on various settings. By default a reply is only sent once.

# Dependencies / Requirements

There are a bunch of Perl modules which need installing, depending on your 
distribution these may be available through your package management tool, or
will need installing through CPAN.

```
Email::Valid
Email::Sender
Email::Simple
Email::Valid
Try::Tiny
MIME::Charset
MIME::EncWords
Log::Log4perl
Log::Dispatch
GetOpt::Std
Net::DNS;
```

You may install these via CPAN, or through your package tool.

CPAN: 'perl -MCPAN -e shell', then 'install Module::Whatever'


## Debian Systems 


```bash
  apt-get install libemail-sender-perl libemail-simple-perl libemail-valid-perl libtry-tiny-perl libdbd-pg-perl libemail-mime-perl liblog-log4perl-perl liblog-dispatch-perl libgetopt-argvfile-perl libmime-charset-perl libmime-encwords-perl libnet-dns-perl
```

and one of : 
```bash
libdbd-pg-perl  or libdbd-mysql-perl 
```


# Installing Virtual Vacation

## 1. Create a local account

Create a dedicated local user account called "vacation". 
This user handles all potentially dangerous mail content - that is why it
should be a separate account.

Do not use "nobody", and most certainly do not use "root" or "postfix".  The
user will never log in, and can be given a "*" password and non-existent
shell and home directory.

Also create a separate "vacation" group.

This should look like this:

```raw
#/etc/passwd
vacation:*:65501:65501:Virtual Vacation:/nonexistent:/sbin/nologin
```

```raw
#/etc/group
vacation:*:65501:
```

## 2. Create a log directory or log file

If you want to log to a file ($log\_to\_file), create a log directory or an 
empty log file.

This file or directory needs to be writeable for the "vacation" user.

Note: If you are logging to syslog, you can skip this step.


## 3. Install vacation.pl

Create a directory /usr/lib/postfixadmin/ and copy the vacation.pl file to it:

```bash
  mkdir /usr/lib/postfixadmin
  cp vacation.pl /usr/lib/postfixadmin/vacation.pl
  chown -R root:vacation /usr/lib/postfixadmin
  chmod 750 /usr/lib/postfixadmin/ /usr/lib/postfixadmin/vacation.pl
```

Which will then look something like:

```raw
-rwxr-x---   1 root  vacation  3356 Dec 21 00:00 vacation.pl*
```


## 4. Setup the transport type

Define the transport type in the Postfix master file:

```raw
#/etc/postfix/master.cf:
vacation    unix  -       n       n       -       -       pipe
  flags=Rq user=vacation argv=/usr/lib/postfixadmin/vacation.pl -f ${sender} -- ${recipient}
```

## 5. Setup the transport maps file

Tell Postfix to use a transport maps file, so add the following to your
Postfix main.cf:

```raw
#/etc/postfix/main.cf:
transport_maps = hash:/etc/postfix/transport
```

Then add the transport definition to the newly created transport file.
Obviously, change yourdomain.com to your own domain. This can be any
arbitrary domain, and it is easiest if you just choose one that will be used
for all your domains.

```raw
#/etc/postfix/transport
autoreply.yourdomain.com	vacation:
```


(You may need to create an entry in /etc/hosts for your non-existant domain)

Execute 
```bash
postmap /etc/postfix/transport
```

Execute 
```bash
postfix reload
```
 to complete the change.


## 6. Configure vacation.pl

The perl vacation.pl script needs to know which database you are using, and also
how to connect to the database.

Namely :

Change any variables starting with '$db\_' and '$db\_type' to either 'mysql' or 'pgsql'.

Change the $vacation\_domain variable to match what you entered in your /etc/postfix/transport 
file.

You can do this in two ways:

a) edit vacation.pl directly (not recommended!)

b) create /etc/postfixadmin/vacation.conf and enter your settings there

   Just use perl syntax there to fill the config variables listed in vacation.pl
   (without the "our" keyword). Example:
```perl
$db_username = 'mail';
1; # required final line - keeps perl happy.
```

   To make sure nobody except vacation.pl can read your vacation.conf (including the
   database password), run

```bash
chown root:vacation /etc/postfixadmin/vacation.conf
chmod 640 /etc/postfixadmin/vacation.conf
```


## 7. Check the alias expansion

Depending on your setup, you may have multiple 'smtpd' service definitions within 
your postfix master.cf file. This is especially the case if you are also using AMAVIS or
another content filtering system when mail is re-injected into Postfix using the smtpd daemon.

If you are, it's likely that alias expansion may happen more than once, in which case you 
may see vacation-style responses duplicated. To suppress this behaviour, you need to add:

```raw
  -o receive_override_options=no_address_mappings
```


For example :

```raw
smtp      inet  n       -       -       -       12       smtpd
    -o content_filter=amavis:[127.0.0.50]:10024
    -o receive_override_options=no_address_mappings

127.0.0.1:10025 inet    n   -   -   -   - smtpd
    -o smtpd_autorized_xforward_hosts=127.0.0.0/8
    -o smtpd_client_restrictions=
    -o smtpd_helo_restrictions=
    -o smtpd_sender_restrictions=
    -o smtpd_recipient_restrictions=permit_mynetworks,reject
    -o mynetworks=127.0.0.0/8
    -o receive_override_options=no_header_body_checks

```

	^^^ Alias expansion occurs here, so we don't want it to happen again for the
		first smtpd daemon (above). If you have per-user settings in amavis,
		you might want to have no_address_mappings in the smtpd on port 10025
		instead.


## 8. Security

If security is an issue for you, read ../DOCUMENTS/Security.txt

# Postfixadmin

When this is all in place you need to have a look at the Postfix Admin
config.inc.php. Here you need to enable Virtual Vacation for the site.


# Help ! It's not working

When something is not working there are a couple of files that you can have
a look at. The most important one is your maillog (usually in /var/log/).

Vacation.pl also has some debugging and logging capabilties. Check the top
of vacation.pl.


