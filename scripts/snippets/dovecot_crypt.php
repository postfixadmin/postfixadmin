<?php

  define('SHA1_RESULTLEN', (160/8));
  define('SHA256_RESULTLEN', (256 / 8));
  define('CRAM_MD5_CONTEXTLEN', 32);
  define('MD5_RESULTLEN', (128/8));
  define('MD4_RESULTLEN', (128/8));
  define('LM_HASH_SIZE', 16);
  define('NTLMSSP_HASH_SIZE', 16);
  
  
class DovecotCrypt extends Crypt {

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
  'SHA'        => array('BASE64', SHA1_RESULTLEN, NULL, 'sha1_generate'),
  'SHA1'       => array('BASE64', SHA1_RESULTLEN, NULL, 'sha1_generate'),
  //'SHA256'     => array('BASE64', SHA256_RESULTLEN, NULL, 'sha256_generate'),
  //'SMD5'       => array('BASE64', 0, 'smd5_verify', 'smd5_generate'),
  //'SSHA'       => array('BASE64', 0, 'ssha_verify', 'ssha_generate'),
  //'SSHA256'    => array('BASE64', 0, 'ssha356_verify', 'ssha256_generate'),
  'PLAIN'      => array('NONE', 0, NULL, 'plain_generate'),
  'CLEARTEXT'  => array('NONE', 0, NULL, 'plain_generate'),
  'CRAM-MD5'   => array('HEX', CRAM_MD5_CONTEXTLEN, NULL, 'cram_md5_generate'),
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
      if( !array_key_exists($algorithm, $this->password_schemes) ) {
        $this->errormsg[] = "This password scheme isn't supported. Check our Wiki!";
        return false;
      }
      
      $scheme = $this->password_schemes[$algorithm];
      $func = '__'.$scheme[3];
      $this->password = $this->$func($this->plain, $this->size);
      //$this->plain = '';
  }
  
  public function verify($algorithm, $password) {
      if( !array_key_exists($algorithm, $this->password_schemes) ) {
        $this->errormsg[] = "This password scheme isn't supported. Check our Wiki!";
        return false;
      }
      
      $scheme = $this->password_schemes[$algorithm];
      if($scheme[2] == NULL) {
        $this->errormsg[] = "This password scheme doesn't support verification";
        return false;
      }
      
      $func = '__'.$scheme[2];
      return  $this->$func($this->plain, $password, $this->size);
      
  }
  
  private function __crypt_verify($plaintext, $password) {
    $password = substr($password, 0, $this->size);
    $crypted = crypt($plaintext, $password);
    
    
    return strcmp($crypted, $password) == 0;
  }
  private function __crypt_generate($plaintext, &$size) {
    $salt =  $this->__random_fill(2);
    
    $salt[0] = $this->salt_chars[$salt[0] % (strlen($this->salt_chars)-1)];
    $salt[1] = $this->salt_chars[$salt[1] % (strlen($this->salt_chars)-1)];
    $salt[2] = '\0';
    
    $password = strtoupper(crypt($plaintext, $salt));
    $size = strlen($password);
    return $password;
  }
  private function __md5_generate() {
  
  }
  private function __sha1_generate() {
  
  }
  private function __plain_generate() {
  
  }
  private function __cram_md5_generate() {
  
  }
  
  
  private function __random_fill($size) {
    $pos = 0;
    $tmp = array();
    while( $pos <= $size ) {
        $rand = mt_rand();
        $rand_l = strlen((string)$rand);
        $tmp[$pos] = substr((string)$rand, mt_rand(0, $rand_l - 1), 1);
        $pos++;
    }
    return join("", $tmp);
  }
}