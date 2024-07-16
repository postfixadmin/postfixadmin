<?php

class PaCryptTest extends \PHPUnit\Framework\TestCase
{
    public function testMd5Crypt()
    {
        $hash = _pacrypt_md5crypt('test', '');

        $this->assertNotEmpty($hash);
        $this->assertNotEquals('test', $hash);

        $this->assertEquals($hash, _pacrypt_md5crypt('test', $hash));
    }

    public function testCrypt()
    {
        // E_NOTICE if we pass in '' for the salt
        $hash = _pacrypt_crypt('test', 'sa');

        $this->assertNotEmpty($hash);
        $this->assertNotEquals('test', $hash);

        $this->assertEquals($hash, _pacrypt_crypt('test', $hash));
    }

    public function testMySQLEncrypt()
    {
        if (!db_mysql()) {
            $this->markTestSkipped('Not using MySQL');
        }

        $hash = _pacrypt_mysql_encrypt('test1');

        $hash2 = _pacrypt_mysql_encrypt('test2');

        $this->assertNotEquals($hash, $hash2);

        $this->assertNotEmpty($hash);
        $this->assertNotEquals('test', $hash);
        $this->assertNotEquals('test', $hash2);

        $this->assertTrue(hash_equals($hash, _pacrypt_mysql_encrypt('test1', $hash)), "hashes should equal....");
    }

    public function testAuthlib()
    {
        global $CONF;

        // too many options!
        foreach (
            [
                'md5raw' => '098f6bcd4621d373cade4e832627b4f6',
                'md5' => 'CY9rzUYh03PK3k6DJie09g==',
                // crypt requires salt ...
                'SHA' => 'qUqP5cyxm6YcTAhz05Hph5gvu9M='
            ] as $flavour => $hash
        ) {
            $CONF['authlib_default_flavour'] = $flavour;

            $stored = "{" . $flavour . "}$hash";
            $hash = _pacrypt_authlib('test', $stored);

            $this->assertEquals($hash, $stored, "Hash: $hash vs Stored: $stored");
            //var_dump("Hash: $hash from $flavour");
        }
    }

    public function testPacryptDovecot()
    {
        global $CONF;

        if (!file_exists('/usr/bin/doveadm')) {
            $this->markTestSkipped("No /usr/bin/doveadm");
        }

        $CONF['encrypt'] = 'dovecot:SHA1';

        $expected_hash = '{SHA1}qUqP5cyxm6YcTAhz05Hph5gvu9M=';

        $this->assertEquals($expected_hash, _pacrypt_dovecot('test', ''));
        $this->assertEquals($expected_hash, _pacrypt_dovecot('test', $expected_hash));

        // This should also work.
        $sha512 = '{SHA512}ClAmHr0aOQ/tK/Mm8mc8FFWCpjQtUjIElz0CGTN/gWFqgGmwElh89WNfaSXxtWw2AjDBmyc1AO4BPgMGAb8kJQ=='; // foobar
        $this->assertEquals($sha512, _pacrypt_dovecot('foobar', $sha512));

        $sha512 = '{SHA512}ClAmHr0aOQ/tK/Mm8mc8FFWCpjQtUjIElz0CGTN/gWFqgGmwElh89WNfaSXxtWw2AjDBmyc1AO4BPgMGAb8kJQ=='; // foobar
        $this->assertNotEquals($sha512, _pacrypt_dovecot('foobarbaz', $sha512));

        $CONF['encrypt'] = 'dovecot:DIGEST-MD5';

        $expected_hash = '{DIGEST-MD5}dad736686b7d1f1db09f3dc9ff538e03';
        $username = 'test@mail.com';

        $this->assertEquals($expected_hash, _pacrypt_dovecot('test', '', $username));
    }


    public function testPhpCrypt()
    {
        $config = Config::getInstance();
        Config::write('encrypt', 'php_crypt');

        $CONF = Config::getInstance()->getAll();

        $sha512_crypt = '$6$ijF8bgunALqnEHTo$LHVa6XQBpM5Gt16RMFQuXqrGAS0y0ymaLS8pnkeVUTSx3t2DrGqWwRj6q4ef3V3SWYkb5xkuN9bv7joxNd8kA1';

        $enc = _pacrypt_php_crypt('foo', $sha512_crypt);

        $this->assertEquals($enc, $sha512_crypt);

        $fail = _pacrypt_php_crypt('bar', $sha512_crypt);

        $this->assertNotEquals($fail, $sha512_crypt);
    }

    public function testPhpCryptMd5()
    {
        global $CONF;

        $config = Config::getInstance();
        Config::write('encrypt', 'php_crypt:MD5');
        $CONF['encrypt'] = 'php_crypt:MD5';

        $new = _pacrypt_php_crypt('foo', '');
        $pac = pacrypt('foo', '');

        $this->assertEquals(1, preg_match('!^\$1\$!', $new), $new);

        $this->assertEquals(1, preg_match('!^\$1\$!', $pac), $pac);

        $CONF = Config::getInstance()->getAll();

        $expected = '$1$z2DG4z9d$jBu3Cl3BPQZrkNqnflnSO.';

        $enc = _pacrypt_php_crypt('foo', $expected);

        $this->assertEquals($enc, $expected);

        $fail = _pacrypt_php_crypt('bar', $expected);
    }

    public function testPhpCryptHandlesPrefixAndOrRounds()
    {
        // try with 1000 rounds
        Config::write('encrypt', 'php_crypt:SHA256:1000');
        $password = 'hello';

        $randomHash = '$5$VhqhhsXJtPFeBX9e$kz3/CMIEu80bKdtDAcISIrDfdwtc.ilR68Vb3hNhu/7';
        $randomHashWithPrefix = '{SHA256-CRYPT}' . $randomHash;

        $new = _pacrypt_php_crypt($password, '');

        $this->assertNotEquals($randomHash, $new); // salts should be different.

        $enc = _pacrypt_php_crypt($password, $randomHash);
        $this->assertEquals($enc, $randomHash);

        $this->assertEquals($randomHash, _pacrypt_php_crypt("hello", $randomHash));
        $this->assertEquals($randomHash, _pacrypt_crypt("hello", $randomHash));

        Config::write('encrypt', 'php_crypt:SHA256::{SHA256-CRYPT}');

        $enc = _pacrypt_php_crypt("hello", $randomHash);
        $this->assertEquals($randomHash, $enc); // we passed in something lacking the prefix, so we shouldn't have added it in.
        $this->assertTrue(hash_equals($randomHash, $enc));

        // should cope with this :
        $enc = _pacrypt_php_crypt($password, '');

        $this->assertEquals($enc, _pacrypt_php_crypt($password, $enc));

        $this->assertMatchesRegularExpression('/^\{SHA256-CRYPT\}/', $enc);
        $this->assertGreaterThan(20, strlen($enc));
    }

    public function testPhpCryptRandomString()
    {
        $str1 = _php_crypt_random_string('abcdefg123456789', 2);
        $str2 = _php_crypt_random_string('abcdefg123456789', 2);
        $str3 = _php_crypt_random_string('abcdefg123456789', 2);

        $this->assertNotEmpty($str1);
        $this->assertNotEmpty($str2);
        $this->assertNotEmpty($str3);

        // it should be difficult for us to get three salts of the same value back...
        // not impossible though.
        $this->assertFalse(strcmp($str1, $str2) == 0 && strcmp($str1, $str3) == 0);
    }


    public function testNewDovecotStuff()
    {
        global $CONF;

        // should all be from 'test123', generated via dovecot.

        $algo_to_example = [
            'SHA1' => '{SHA1}cojt0Pw//L6ToM8G41aOKFIWh7w=',
            'SHA1.B64' => '{SHA1.B64}cojt0Pw//L6ToM8G41aOKFIWh7w=',
            'BLF-CRYPT' => '{BLF-CRYPT}$2y$05$cEEZv2h/NtLXII.emi2TP.rMZyB7VRSkyToXWBqqz6cXDoyay166q',
            'BLF-CRYPT.B64' => '{BLF-CRYPT.B64}JDJ5JDA1JEhlR0lBeGFHR2tNUGxjRWpyeFc0eU9oRjZZZ1NuTWVOTXFxNWp4bmFwVjUwdGU3c2x2L1VT',
            'SHA512-CRYPT' => '{SHA512-CRYPT}$6$MViNQUSbWyXWL9wZ$63VsBU2a/ZFb9f/dK4EmaXABE9jAcNltR7y6a2tXLKoV5F5jMezno.2KpmtD3U0FDjfa7A.pkCluVMlZJ.F64.',
            'SHA512-CRYPT.B64' => '{SHA512-CRYPT.B64}JDYkR2JwY3NiZXNMWk9DdERXbiRYdXlhdEZTdy9oa3lyUFE0d24wenpGQTZrSlpTUE9QVWdPcjVRUC40bTRMTjEzdy81aWMvWTdDZllRMWVqSWlhNkd3Q2Z0ZnNjZEFpam9OWjl3OU5tLw==',
            'SHA512' => '{SHA512}2u9JU7l4M2XK1mFSI3IFBsxGxRZ80Wq1APpZeqCP+WTrJPsZaH8012Zfd4/LbFNY/ApbgeFmLPkPc6JnHFP5kQ==',

            // postfixadmin 'incorrectly' classes sha512.b64 as a sha512-crypted string that's b64 encoded.
            // really SHA512.B64 should be base64_encode(hash('sha512', 'something', true));
            'SHA512.B64' => '{SHA512-CRYPT.B64}JDYkMDBpOFJXQ0JwMlFMMDlobCRFMVFWLzJjbENPbEo4OTg0SjJyY1oxeXNTaFJIYVhJeVdFTDdHRGl3aHliYkhQUHBUQjZTM0lFMlYya2ZXczZWbHY0aDVNa3N0anpud0xuRTBWZVRELw==',
            'CRYPT' => '{CRYPT}$2y$05$ORqzr0AagWr25v3ixHD5QuMXympIoNTbipEFZz6aAmovGNoij2vDO',
            'MD5-CRYPT' => '{MD5-CRYPT}$1$AIjpWveQ$2s3eEAbZiqkJhMYUIVR240',
            'PLAIN-MD5' => '{PLAIN-MD5}cc03e747a6afbbcbf8be7668acfebee5',
            'SSHA' => '{SSHA}ZkqrSEAhvd0FTHaK1IxAQCRa5LWbxGQY',
            'PLAIN' => '{PLAIN}test123',
            'CLEAR' => '{CLEAR}test123',
            'CLEARTEXT' => '{CLEARTEXT}test123',
            'ARGON2I' => '{ARGON2I}$argon2i$v=19$m=32768,t=4,p=1$xoOcAGa27k0Sr6ZPbA9ODw$wl/KAZVmJooD/35IFG5oGwyQiAREXrLss5BPS1PDKfA',
            'ARGON2ID' => '{ARGON2ID}$argon2id$v=19$m=65536,t=3,p=1$eaXP376O9/VxleLw9OQIxg$jOoDyECeRRV4eta3eSN/j0RdBgqaA1VBGAA/pbviI20',
            'ARGON2ID.B64' => '{ARGON2ID.B64}JGFyZ29uMmlkJHY9MTkkbT02NTUzNix0PTMscD0xJEljdG9DWko1T04zWlYzM3I0TVMrNEEkMUVtNTJRWkdsRlJzNnBsRXpwVmtMeVd4dVNPRUZ2dUZnaVNhTmNlb08rOA==',
            'SHA256' => '{SHA256}7NcYcNGWMxapfjrDQIyYNa2M8PPBvHA1J8MCZVNPda4=',
            'SHA256-CRYPT' => '{SHA256-CRYPT}$5$CFly6wzfn2az3U8j$EhfQPTdjpMGAisfCjCKektLke5GGEmtdLVaCZSmsKw2',
            'SHA256-CRYPT.B64' => '{SHA256-CRYPT.B64}JDUkUTZZS1ZzZS5sSVJoLndodCR6TWNOUVFVVkhtTmM1ME1SQk9TR3BEeGpRY2M1TzJTQ1lkbWhPN1YxeHlD',
        ];

        // php 7.3 and below do not support these.
        if (phpversion() < '7.3') {
            unset($algo_to_example['ARGON2ID']);
            unset($algo_to_example['ARGON2ID.B64']);
        }

        foreach ($algo_to_example as $algorithm => $example_hash) {
            $CONF['encrypt'] = $algorithm;
            $pfa_new_hash = pacrypt('test123');

            $pacrypt_check = pacrypt('test123', $example_hash);
            $pacrypt_sanity = pacrypt('zzzzzzz', $example_hash);

            $this->assertNotEquals($example_hash, $pacrypt_sanity, "Should not match, zzzz password. $algorithm / $pacrypt_sanity");

            $this->assertEquals($example_hash, $pacrypt_check, "Should match, algorithm: $algorithm generated:{$pacrypt_check} vs example:{$example_hash}");

            $new_new = pacrypt('test123', $pfa_new_hash);

            $this->assertEquals($pfa_new_hash, $new_new, "Trying: $algorithm => gave: $new_new with $pfa_new_hash ... ");
        }
    }

    public function testWeCopeWithDifferentMethodThanConfigured()
    {
        global $CONF;
        $CONF['encrypt'] = 'MD5-CRYPT';

        $md5Crypt = '{MD5-CRYPT}$1$AIjpWveQ$2s3eEAbZiqkJhMYUIVR240';

        $this->assertEquals($md5Crypt, pacrypt('test123', $md5Crypt));
        $CONF['encrypt'] = 'MD5-CRYPT';

        $this->assertEquals($md5Crypt, pacrypt('test123', $md5Crypt));
        $sha1Crypt = '{SHA1}cojt0Pw//L6ToM8G41aOKFIWh7w=';

        $this->assertEquals($sha1Crypt, pacrypt('test123', $sha1Crypt));
    }

    public function testSomeCourierHashes()
    {
        global $CONF;

        $options = [
            'courier:md5' => '{MD5}zAPnR6avu8v4vnZorP6+5Q==',
            'courier:md5raw' => '{MD5RAW}cc03e747a6afbbcbf8be7668acfebee5',
            'courier:ssha' => '{SSHA}pJTac1QSIHoi0qBPdqnBvgPdjfFtDRVY',
            'courier:sha256' => '{SHA256}7NcYcNGWMxapfjrDQIyYNa2M8PPBvHA1J8MCZVNPda4=',
        ];

        foreach ($options as $algorithm => $example_hash) {
            $CONF['encrypt'] = $algorithm;

            $pacrypt_check = pacrypt('test123', $example_hash);
            $pacrypt_sanity = pacrypt('zzzzz', $example_hash);
            $pfa_new_hash = pacrypt('test123');

            $this->assertNotEquals($pacrypt_sanity, $pfa_new_hash);
            $this->assertNotEquals($pacrypt_sanity, $example_hash);

            $this->assertEquals($example_hash, $pacrypt_check, "Should match, algorithm: $algorithm generated:{$pacrypt_check} vs example:{$example_hash}");

            $new = pacrypt('test123', $pfa_new_hash);

            $this->assertEquals($new, $pfa_new_hash, "Trying: $algorithm => gave: $new with $pfa_new_hash");
        }
    }

    /**
     * @see https://github.com/postfixadmin/postfixadmin/issues/647
     */
    public function testSha512B64SupportsMd5CryptMigration()
    {
        global $CONF;

        Config::write('encrypt', 'sha512.b64');
        $CONF['encrypt'] = 'sha512.b64';

        $x = pacrypt('test123');

        $this->assertMatchesRegularExpression('/^\{SHA512-CRYPT\.B64/', $x);
        $this->assertTrue(strlen($x) > 50);

        $this->assertEquals($x, pacrypt('test123', $x));

        // while we're configured for SHA512-CRYPT.B64, we still support MD5-CRYPT format ...
        $md5crypt = '{MD5-CRYPT}$1$c9809462$fC8eUPU2lq7arWRvxChMu1';

        $x = pacrypt('test123', $md5crypt);
        $this->assertEquals($x, $md5crypt);
    }

    public function testObviousMechanisms()
    {
        global $CONF;

        $mechs = [
            'md5crypt' => ['$1$c9809462$fC8eUPU2lq7arWRvxChMu1', '{MD5-CRYPT}$1$rGTbP.KE$wimpECWs/wQa7rnSwCmHU.'],
            'md5' => 'cc03e747a6afbbcbf8be7668acfebee5',
            'cleartext' => 'test123',
            'mysql_encrypt' => '$6$$KMCDSuWNoVgNrK5P1zDS12ZZt.LV4z9v9NtD0AG0T5Rv/n0wWVvZmHMSKKZQciP7lrqrlbrBrBd4lhBSGy1BU0',
            'authlib' => '{MD5RAW}cc03e747a6afbbcbf8be7668acfebee5', // authpasswd md5raw (via courier-authdaemon package)
            'php_crypt:SHA512' => '{SHA512-CRYPT}$6$IeqpXtDIXF09ADdc$IsE.SSK3zuwtS9fdWZ0oVxXQjPDj834xqxTiv3Qfidq3AbAjPb0DNyI28JyzmDVlbfC9uSfNxD9RUyeO1.7FV/',
            'php_crypt:DES' => 'VXAXutUnpVYg6',
            'php_crypt:MD5' => ['$1$rGTbP.KE$wimpECWs/wQa7rnSwCmHU.', '{MD5-CRYPT}$1$rGTbP.KE$wimpECWs/wQa7rnSwCmHU.'],
            'php_crypt:SHA256' => '$5$UaZs6ZuaLkVPx3bM$4JwAqdphXVutFYw7COgAkp/vj09S1DfjIftxtjqDrr/',
            'php_crypt:BLOWFISH' => '$2y$10$4gbwQMAoJPcg.mWnENYNg.syH9mZNsbQu6KN7skK92g3tlPnvvBDW',
            'sha512.b64' => '{SHA512-CRYPT.B64}JDYkMDBpOFJXQ0JwMlFMMDlobCRFMVFWLzJjbENPbEo4OTg0SjJyY1oxeXNTaFJIYVhJeVdFTDdHRGl3aHliYkhQUHBUQjZTM0lFMlYya2ZXczZWbHY0aDVNa3N0anpud0xuRTBWZVRELw==',
        ];


        foreach ($mechs as $mech => $example_hash) {
            if ($mech == 'mysql_encrypt' && Config::read_string('database_type') != 'mysql') {
                continue;
            }


            if (is_string($example_hash)) {
                $example_hash = [$example_hash];
            }
            foreach ($example_hash as $hash) {
                Config::write('encrypt', $mech);

                $CONF['encrypt'] = $mech;

                $x = pacrypt('test123');
                $this->assertNotEmpty($x);

                $y = pacrypt('test123', $x);
                $this->assertEquals($x, $y); // $y == %x if the password was correct.

                // should be valid against what's in the lookup array above
                $x = pacrypt('test123', $hash);

                $this->assertEquals($hash, $x);
            }
        }
    }
}
