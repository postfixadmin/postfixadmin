# PostfixAdmin Password Hash Support.

How are your passwords stored in the database.

They should not be stored in plain text.

Whatever format you choose will need to be supported by your IMAP server (and whatever provides SASL auth for Postfix)

## Configuration

See config.inc.php - look for

```php
$CONF['encrypt'] = 'something';
```

## Supported Formats

This document is probably not complete.

It possibly provides better documentation than was present before. This may not say much.

### cleartext

No hashing. May be useful for debugging.

Insecure. Try to avoid. May be useful for legacy purposes.

### mysql_encrypt

Uses the MYSQL ENCRYPT() function (this uses 'crypt' underneath).

Can be secure.

Requires MySQL.

Should use a sha512 salt for new values.

### md5crypt

md5crypt = uses md5crypt() function - in a 'crypt' like format.

e.g.

`$1$c9809462$M0zeLuOvixH61C2csGN.U0`

You should not use this for new installations

(it probably does not offer a high level of security)

### md5

PHP's md5() function.

You should not use this (it does not offer a high level of security), but is probably better than cleartext.

### system

Uses PHP's crypt function.

Probably throws an E_NOTICE. Avoid?

example : `$1$tWgqTIuF$1HFciCXrhVpACGjBMxNr/0`

### authlib

See source code. Presumably useful for Courier based installations.

#### With `$CONF['authlib_default_flavor'] = 'md5raw`;`

might give something like :

`{md5raw}3858f62230ac3c915f300c664312c63f`

Based on md5, so avoid.

#### With `$CONF['authlib_default_flavor'] = 'crypt`;`

Uses PHP Crypt.

`{crypt}blfqitzeBpyAE`

Presumably weak.

#### With `$CONF['authlib_default_flavor'] = 'SHA';`

Uses sha1, base64 encoded. Unsalted. Avoid.

### dovecot:CRYPT-METHOD

Uses dovecot binary to produce hash.

Pros -

* Minimal dependency on PostfixAdmin / PHP code.
* Hash should definitely work with dovecot!

Cons -

* file permissions and/or execution of doveadm by the web server may be problematic.
* requires: proc_open(...) - which might be blocked by e.g. safemode.
* doveadm may not be installed.
* possible issues with SELinux
* See https://github.com/postfixadmin/postfixadmin/issues/398 (file permissions)

#### Incomplete list of CRYPT-METHOD

* CRAM-MD5
* SHA
* SHA1
* SHA256
* SHA512
* CLEAR
* CLEARTEXT
* PLAIN
* PLAIN-TRUNC

If in doubt, try `dovecot:SHA512`

Dovecot generated passwords in your database should look a bit like :

`{SHA256}JMQi5oHxwb0IKGx6r10jpfCI3NsLIZgGs6nleSRPAMU=`

If you have problems, start by checking you can generate one on the command line using e..g

`doveadm pw -s SHA256`

### php_crypt

Potentially the most secure.

By default it will generate a SHA512 salt. Output in crypt format.

Other methods :

* BLOWFISH
* SHA512
* SHA256
* DES (avoid)
* MD5 (avoid)

e.g.

`$6$emcsNNrzGZSN64mI$A/bmacTGSp2UrdcPvaROrR2FPQS5KlnoU.a/0zmfpaubBO9o1ZcgyQIic4Qb59SMxA2H8YxgS1XILO1wZhjkZ0`

You can specify the salting method using a :METHOD in the specification.

e.g.

`$CONF['encrypt'] = 'php_crypt:SHA512';`

You can make the hashing more 'difficult' by specifying an additional parameter like :

`$CONF['encrypt'] = 'php_crypt:SHA512:5000';`

which should change the 'cost' (BLOWFISH) or rounds (SHA256, SHA512).

finally you can ask that the generated hash has a specific prefix (e.g. {SHA512} ) like :

`$CONF['encrypt'] = 'php_crypt:SHA512:5000:{SHA512-CRYPT}';`

### sha512.b64

See  https://github.com/postfixadmin/postfixadmin/issues/58

No dovecot dependency; should support migration from md5crypt

Output is base64 encoded i.e. a hash like :

* `$6$emcsNNrzGZSN64mI$A/bmacTGSp2UrdcPvaROrR2FPQS5KlnoU.a/0zmfpaubBO9o1ZcgyQIic4Qb59SMxA2H8YxgS1XILO1wZhjkZ0`

is base64 encoded into :

* JDYkZW1jc05OcnpHWlNONjRtSSRBL2JtY...

and then formatted to become :

* {SHA512-CRYPT.B64}JDYkZW1jc05OcnpHWlNONjRtSSRBL2JtY....

This format should support older passwords with a {MD5-CRYPT} prefix, to allow you to migrate.

