<?php

define('SHA1_RESULTLEN', (160/8));
define('SHA256_RESULTLEN', (256 / 8));
define('CRAM_MD5_CONTEXTLEN', 32);
define('MD5_RESULTLEN', (128/8));
define('MD4_RESULTLEN', (128/8));
define('LM_HASH_SIZE', 16);
define('NTLMSSP_HASH_SIZE', 16);


class DovecotCrypt extends Crypt {
    private $errormsg = [];

    private $salt_chars = "./0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz";


    /**
     * Array
     * Crypt type and which function handles it.
     * array('alogrithm' => array('encoding', 'length', 'verify', 'function'))
     */
    public $password_schemes = array(
        'CRYPT'      => array('NONE', 0, 'crypt_verify', 'crypt_generate'),
        'MD5'        => array('NONE', 0, 'md5_verify', 'md5_generate'),
        //'MD5-CRYPT'  => array('NONE', 0, 'md5_crypt_verify', 'md5_crypt_generate'),
        'SHA'        => array('BASE64', SHA1_RESULTLEN, null, 'sha1_generate'),
        'SHA1'       => array('BASE64', SHA1_RESULTLEN, null, 'sha1_generate'),
        //'SHA256'     => array('BASE64', SHA256_RESULTLEN, NULL, 'sha256_generate'),
        //'SMD5'       => array('BASE64', 0, 'smd5_verify', 'smd5_generate'),
        //'SSHA'       => array('BASE64', 0, 'ssha_verify', 'ssha_generate'),
        //'SSHA256'    => array('BASE64', 0, 'ssha356_verify', 'ssha256_generate'),
        'PLAIN'      => array('NONE', 0, null, 'plain_generate'),
        'CLEARTEXT'  => array('NONE', 0, null, 'plain_generate'),
        'CRAM-MD5'   => array('HEX', CRAM_MD5_CONTEXTLEN, null, 'cram_md5_generate'),
        //'HMAC-MD5'   => array('HEX', CRAM_MD5_CONTEXTLEN, NULL, 'cram_md5_generate'),
        //'DIGEST-MD5' => array('HEX', MD5_RESULTLEN, NULL, 'digest_md5_generate'),
        //'PLAIN-MD4'  => array('HEX', MD4_RESULTLEN, NULL, 'plain_md4_generate'),
        //'PLAIN-MD5'  => array('HEX', MD5_RESULTLEN, NULL, 'plain_md5_generate'),
        //'LDAP-MD5'   => array('BASE64', MD5_RESULTLEN, NULL, 'plain_md5_generate'),
        //'LANMAN'     => array('HEX', LM_HASH_SIZE, NULL, 'lm_generate'),
        //'NTLM'       => array('HEX', NTLMSSP_HASH_SIZE, NULL, 'ntlm_generate'),
        //'OTP'        => array('NONE', 0, 'otp_verify', 'otp_generate'),
        //'SKEY'       => array('NONE', 0, 'otp_verify', 'skey_generate'),
        //'RPA'        => array('HEX', MD5_RESULTLEN, NULL, 'rpa_generate'),
    );



    public function crypt($algorithm) {
        if (!array_key_exists($algorithm, $this->password_schemes)) {
            $this->errormsg[] = "This password scheme isn't supported. Check our Wiki!";
            return false;
        }

        $scheme = $this->password_schemes[$algorithm];
        $func = '__'.$scheme[3];

        $this->password = $this->$func($this->plain);
        //$this->plain = '';
        return true;
    }

    public function verify($algorithm, $password) {
        if (!array_key_exists($algorithm, $this->password_schemes)) {
            $this->errormsg[] = "This password scheme isn't supported. Check our Wiki!";
            return false;
        }

        $scheme = $this->password_schemes[$algorithm];
        if ($scheme[2] == null) {
            $this->errormsg[] = "This password scheme doesn't support verification";
            return false;
        }

        $func = '__'.$scheme[2];
        return  $this->$func($this->plain, $password);
    }

    private function __crypt_verify($plaintext, $password) {
        $crypted = crypt($plaintext, $password);
        return strcmp($crypted, $password) == 0;
    }
    private function __crypt_generate($plaintext) {
        $password = crypt($plaintext);
        return $password;
    }
    private function __md5_generate($plaintext) {
        return $plaintext;
    }
    private function __sha1_generate() {
    }
    private function __plain_generate() {
    }
    private function __cram_md5_generate($plaintext) {

        #http://hg.dovecot.org/dovecot-1.2/file/84373d238073/src/lib/hmac-md5.c
        #http://hg.dovecot.org/dovecot-1.2/file/84373d238073/src/auth/password-scheme.c cram_md5_generate
        #am i right that the hmac salt is the plaintext password itself?
        $salt = $plaintext;
        if (function_exists('hash_hmac')) {  //Some providers doesn't offers hash access.
            return hash_hmac('md5', $plaintext, $salt);
        } else {
            return custom_hmac('md5', $plaintext, $salt);
        }
    }


    /**
     * @return string
     */
    public function custom_hmac($algo, $data, $key, $raw_output = false) {
        $algo = strtolower($algo);
        $pack = 'H'.strlen($algo('test'));
        $size = 64;
        $opad = str_repeat(chr(0x5C), $size);
        $ipad = str_repeat(chr(0x36), $size);

        if (strlen($key) > $size) {
            $key = str_pad(pack($pack, $algo($key)), $size, chr(0x00));
        } else {
            $key = str_pad($key, $size, chr(0x00));
        }

        for ($i = 0; $i < strlen($key) - 1; $i++) {
            $opad[$i] = $opad[$i] ^ $key[$i];
            $ipad[$i] = $ipad[$i] ^ $key[$i];
        }

        $output = $algo($opad.pack($pack, $algo($ipad.$data)));

        return ($raw_output) ? pack($pack, $output) : $output;
    }
}
