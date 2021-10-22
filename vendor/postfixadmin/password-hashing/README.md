# PostfixAdmin\PasswordHashing

Standalone PHP library for creating various password hashes

Note, this library is still quite new (2021/07/02). It's quite likely to have major refactoring / renaming as it's reviewed.


## Supported Mechanisms (hash formats) 

See : https://github.com/postfixadmin/pacrypt/blob/46ddd21a28433faa8ea279e4161f7853a69b40cb/src/Crypt.php#L12

Some mechanisms support either HEX (.HEX)  encoded or BASE64 (.B64) encoded output.
 
Currently : 

 * SHA1, SHA1.HEX, SHA1.B64
 * SSHA
 * BLF-CRYPT, BLF-CRYPT.B64
 * SHA512-CRYPT, SHA512-CRYPT.B64
 * ARGON2I, ARGON2I.B64
 * ARGON2ID, ARGON2ID.B64
 * SHA256, SHA256-CRYPT, SHA256-CRYPT.B64
 * SHA512, SHA512.B64
 * MD5-CRYPT, MD5
 * PLAIN-MD5
 * CRYPT ({CRYPT} prefix, defaults to use Blowfish)
 * SYSTEM (DES CRYPT, best avoid)
 * PLAIN, CLEAR, CLEARTEXT (useful for testing)
 * COURIER:MD5
 * COURIER:MD5RAW
 * COURIER:SSHA (same as SSHA)
 * COURIER:SHA256 (same as SHA256)

## Example usage

The main functionality reflects legacy behaviour in PostfixAdmin with a 'pacrypt' function, which when given ...

 * one argument - clear text password - returns a hash.
 * two arguments - clear text password and stored hash - if the if the return value matches the stored hash, then the clear text password was a match for the hash we have.

```PHP
$tool = new \PostfixAdmin\PasswordHashing\Crypt('ARGON2I');

// should output something to indicate what your system supports (may be dependent on PHP variant, PHP modules etc)

$hash = $tool->crypt('fishbeans');

echo "Hash is : $hash \n";

echo "Verify : " . json_encode($hash == $tool->crypt('fishbeans', $hash)) . "\n";
echo "Verify fails with : " . json_encode('cat' == $tool->crypt('fishbeans', $hash)) . "\n";

```

the above code will output something similar to : 

```text
Hash is : {ARGON2I}$argon2i$v=19$m=65536,t=4,p=1$d1JMUXVHSUtLTGhxYnowVQ$4raz6DDUbtRysi+1ZTdNL3L5j4tcSYnzWxyLVDtFjKc 
Verify : true
Verify fails with : false
```


## ChangeLog

2021/07/03 - Initial release / copying of code from https://github.com/postfixadmin/postfixadmin ...
