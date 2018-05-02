<?php

require_once('common.php');

class PaCryptTest extends PHPUnit_Framework_TestCase {
    public function testMd5Crypt() {
        $hash = _pacrypt_md5crypt('test', '');

        $this->assertNotEmpty($hash);
        $this->assertNotEquals('test', $hash);

        $this->assertEquals($hash, _pacrypt_md5crypt('test', $hash));
    }

    public function testCrypt() {

        // E_NOTICE if we pass in '' for the salt
        $hash = _pacrypt_crypt('test', 'sa');

        $this->assertNotEmpty($hash);
        $this->assertNotEquals('test', $hash);

        $this->assertEquals($hash, _pacrypt_crypt('test', $hash));
    }

    public function testMySQLEncrypt() {
        if (!db_mysql()) {
            $this->markTestSkipped('Not using MySQL');
        }

        $hash = _pacrypt_mysql_encrypt('test', '');

        $this->assertNotEmpty($hash);
        $this->assertNotEquals('test', $hash);

        $this->assertEquals($hash, _pacrypt_mysql_encrypt('test', $hash));

        $hash2 = _pacrypt_mysql_encrypt('test', 'salt');

        $this->assertNotEmpty($hash2);
        $this->assertNotEquals($hash, $hash2);

        $this->assertEquals($hash2, _pacrypt_mysql_encrypt('test', 'salt'));
    }

    public function testAuthlib() {

        // too many options!
        foreach (
            ['md5raw' => '098f6bcd4621d373cade4e832627b4f6',
                'md5' => 'CY9rzUYh03PK3k6DJie09g==',
                // crypt requires salt ...
                'SHA' => 'qUqP5cyxm6YcTAhz05Hph5gvu9M='] as $flavour => $hash) {
            $CONF['authlib_default_flavour'] = $flavour;

            $stored = "{" . $flavour . "}$hash";
            $hash = _pacrypt_authlib('test', $stored);

            $this->assertEquals($hash, $stored, "Hash: $hash vs Stored: $stored");
            //var_dump("Hash: $hash from $flavour");
        }
    }

    public function testPacryptDovecot() {
        global $CONF;
        if (!file_exists('/usr/bin/doveadm')) {
            $this->markTestSkipped("No /usr/bin/doveadm");
        }


        $CONF['encrypt'] = 'dovecot:SHA1';

        $expected_hash = '{SHA1}qUqP5cyxm6YcTAhz05Hph5gvu9M=';

        $this->assertEquals($expected_hash, _pacrypt_dovecot('test', ''));

        $this->assertEquals($expected_hash, _pacrypt_dovecot('test', $expected_hash));
    }

    public function testPhpCrypt() {
        global $CONF;

        $config = Config::getInstance();
        Config::write('encrypt', 'php_crypt:MD5');


        $CONF = Config::getInstance()->getAll();

        $expected = '$1$z2DG4z9d$jBu3Cl3BPQZrkNqnflnSO.';

        $enc = _pacrypt_php_crypt('foo', $expected);

        $this->assertEquals($enc, $expected);

        $fail = _pacrypt_php_crypt('bar', $expected);


        $this->assertNotEquals($fail, $expected);
    }

    public function testPhpCryptRandomString() {
        $str1 = _php_crypt_random_string('abcdefg123456789', 2);
        $str2 = _php_crypt_random_string('abcdefg123456789', 2);

        $this->assertNotEmpty($str1);
        $this->assertNotEmpty($str2);
        $this->assertNotEquals($str1, $str2);
    }
}

/* vim: set expandtab softtabstop=4 tabstop=4 shiftwidth=4: */
