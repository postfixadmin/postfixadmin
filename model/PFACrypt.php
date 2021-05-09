<?php

class PFACrypt
{
    private $algorithm;

    const DOVECOT_NATIVE = [
        'SHA1', 'SHA1.HEX', 'SHA1.B64',
        'SSHA',
        'BLF-CRYPT', 'BLF-CRYPT.B64',
        'SHA512-CRYPT', 'SHA512-CRYPT.B64',
        'ARGON2I',
        'ARGON2I.B64',
        'ARGON2ID',
        'ARGON2ID.B64',
        'SHA256', 'SHA256-CRYPT', 'SHA256-CRYPT.B64',
        'SHA512', 'SHA512.B64',
        'MD5',
        'PLAIN-MD5',
        'CRYPT',
    ];

    public function __construct(string $algorithm)
    {
        $this->algorithm = $algorithm;
    }

    public function hash(string $pw, string $pw_db = ''): string
    {
        $algorithm = $this->algorithm;

        // try and 'upgrade' some dovecot commands to use local algorithms (rather tnan a dependency on the dovecot binary).
        if (preg_match('/^dovecot:/', $algorithm)) {
            $tmp = preg_replace('/^dovecot:/', '', $algorithm);
            if (in_array($tmp, self::DOVECOT_NATIVE)) {
                $algorithm = $tmp;
            } else {
                error_log("Warning: using algorithm that requires proc_open: $algorithm, consider using one of : " . implode(', ', self::DOVECOT_NATIVE));
            }
        }

        switch ($algorithm) {

            case 'SHA1':
            case 'SHA1.B64':
            case 'SHA1.HEX':
                return $this->hashSha1($pw, $pw_db, $algorithm);

            case 'BLF-CRYPT':
            case 'BLF-CRYPT.B64':
                return $this->blowfishCrypt($pw, $pw_db, $algorithm);

            case 'SHA512-CRYPT':
            case 'SHA512-CRYPT.B64':
                return $this->sha512Crypt($pw, $pw_db, $algorithm);

            case 'ARGON2I':
            case 'ARGON2I.B64':
                return $this->argon2iCrypt($pw, $pw_db, $algorithm);

            case 'ARGON2ID':
            case 'ARGON2ID.B64':
                return $this->argon2idCrypt($pw, $pw_db, $algorithm);

            case 'SSHA':
            case 'courier:ssha':
                return $this->hashSha1Salted($pw, $pw_db);

            case 'SHA256':
            case 'courier:sha256':
                return $this->hashSha256($pw);

            case 'SHA256-CRYPT':
            case 'SHA256-CRYPT.B64':
                return $this->sha256Crypt($pw, $pw_db, $algorithm);

            case 'SHA512':
            case 'sha512':
            case 'sha512b.b64':
            case 'SHA512.B64':
                return $this->hashSha512($pw, $algorithm);

            case 'md5':
            case 'PLAIN-MD5':
                return $this->hashMd5($pw, $algorithm);

            case 'courier:md5':
                return '{MD5}' . base64_encode(md5($pw, true));

            case 'courier:md5raw':
                return '{MD5RAW}' . bin2hex(md5($pw, true));

            case 'MD5':
            case 'MD5-CRYPT':
                return $this->cryptMd5($pw, $pw_db, $algorithm);


            case 'CRYPT':
                if (!empty($pw_db)) {
                    $pw_db = preg_replace('/^{CRYPT}/', '', $pw_db);
                }
                if (empty($pw_db)) {
                    $pw_db = '$2y$10$' . substr(sha1(random_bytes(8)), 0, 22);
                }
                return '{CRYPT}' . crypt($pw, $pw_db);

            case 'system':
                return crypt($pw, $pw_db);

            case 'cleartext':
                return $pw;
            case 'CLEAR':
            case 'PLAIN':
            case 'CLEARTEXT':
                if (!empty($pw_db)) {
                    $pw_db = preg_replace('/^{.*}}/', '', $pw_db);
                    if (password_verify($pw, $pw_db)) {
                        return '{' . $algorithm . '}' . $pw;
                    }
                }
                return '{' . $algorithm . '}' . $pw;
            case 'mysql_encrypt':
                return _pacrypt_mysql_encrypt($pw, $pw_db);

            // these are all supported by the above (SHA,
            case 'authlib':
                return _pacrypt_authlib($pw, $pw_db);

            case 'sha512crypt.b64':
                return $this->pacrypt_sha512crypt_b64($pw, $pw_db);


        }

        if (preg_match("/^dovecot:/", $algorithm)) {
            return _pacrypt_dovecot($pw, $pw_db);
        }

        if (substr($algorithm, 0, 9) === 'php_crypt') {
            return _pacrypt_php_crypt($pw, $pw_db);
        }

        throw new Exception('unknown/invalid $CONF["encrypt"] setting: ' . $algorithm);
    }


    public function hashSha1(string $pw, string $pw_db = '', string $algorithm = 'SHA1'): string
    {
        $hash = hash('sha1', $pw, true);

        if (preg_match('/\.HEX$/', $algorithm)) {
            $hash = bin2hex($hash);
        } else {
            $hash = base64_encode($hash);
        }
        return "{{$algorithm}}{$hash}";
    }

    public function hashSha1Salted(string $pw, string $pw_db = ''): string
    {
        if (empty($pw_db)) {
            $salt = base64_encode(random_bytes(3)); // 4 char salt.
        } else {
            $salt = substr(base64_decode(substr($pw_db, 6)), 20);
        }
        return '{SSHA}' . base64_encode(sha1($pw . $salt, true) . $salt);
    }

    public function hashSha512(string $pw, string $algorithm = 'SHA512')
    {
        $prefix = '{SHA512}';

        if ($algorithm == 'SHA512.B64' || $algorithm == 'sha512b.b64') {
            $prefix = '{SHA512.B64}';
        }

        return $prefix . base64_encode(hash('sha512', $pw, true));
    }

    public function hashMd5(string $pw, string $algorithm = 'PLAIN-MD5'): string
    {
        if ($algorithm == 'PLAIN-MD5') {
            return '{PLAIN-MD5}' . md5($pw);
        }
        return md5($pw);
    }

    public function hashSha256(string $pw): string
    {
        return '{SHA256}' . base64_encode(hash('sha256', $pw, true));
    }

    public function cryptMd5(string $pw, string $pw_db = '', $algorithm = 'MD5-CRYPT')
    {
        if (!empty($pw_db)) {
            $pw_db = preg_replace('/^{MD5.*}/', '', $pw_db);
        }
        if (empty($pw_db)) {
            $pw_db = '$1$' . substr(sha1(random_bytes(8)), 0, 16);
        }
        return "{{$algorithm}}" . crypt($pw, $pw_db);
    }

    public function blowfishCrypt(string $pw, string $pw_db = '', string $algorithm = 'BLF-CRYPT'): string
    {
        if (!empty($pw_db)) {
            if ($algorithm == 'BLF-CRYPT') {
                $pw_db = preg_replace('/^{BLF-CRYPT}/', '', $pw_db);
            }
            if ($algorithm == 'BLF-CRYPT.B64') {
                $pw_db = base64_decode(preg_replace('/^{BLF-CRYPT.B64}/', '', $pw_db));
            }
            $hash = crypt($pw, $pw_db);

            if ($algorithm == 'BLF-CRYPT.B64') {
                $hash = base64_encode($hash);
            }
            return "{{$algorithm}}{$hash}";
        }

        $r = password_hash($pw, PASSWORD_BCRYPT);
        if (!is_string($r)) {
            throw new \RuntimeException("Failed to generate password");
        }
        if ($algorithm == 'BLF-CRYPT.B64') {
            return '{BLF-CRYPT.B64}' . base64_encode($r);
        }
        return '{BLF-CRYPT}' . $r;
    }

    public function sha256Crypt(string $pw, string $pw_db = '', string $algorithm = 'SHA256-CRYPT'): string
    {
        if (!empty($pw_db)) {
            $pw_db = preg_replace('/^{SHA256-CRYPT(\.B64)?}/', '', $pw_db);

            if ($algorithm == 'SHA256-CRYPT.B64') {
                $pw_db = base64_decode($pw_db);
            }
        }

        if (empty($pw_db)) {
            $pw_db = '$5$' . substr(sha1(random_bytes(8)), 0, 16);
        }

        $hash = crypt($pw, $pw_db);

        if ($algorithm == 'SHA256-CRYPT.B64') {
            return '{SHA256-CRYPT.B64}' . base64_encode($hash);
        }
        return "{SHA256-CRYPT}" . $hash;
    }

    public function sha512Crypt(string $pw, string $pw_db = '', $algorithm = 'SHA512-CRYPT'): string
    {
        if (!empty($pw_db)) {
            $pw_db = preg_replace('/^{SHA512-CRYPT(\.B64)?}/', '', $pw_db);

            if ($algorithm == 'SHA512-CRYPT.B64') {
                $pw_db = base64_decode($pw_db);
            }
        }

        if (empty($pw_db)) {
            $pw_db = '$6$' . substr(sha1(random_bytes(8)), 0, 16);
        }

        $hash = crypt($pw, $pw_db);

        if ($algorithm == 'SHA512-CRYPT.B64') {
            $hash = base64_encode($hash);
            return "{SHA512-CRYPT.B64}{$hash}";
        }

        return "{SHA512-CRYPT}$hash";
    }

    public function argon2ICrypt(string $pw, string $pw_db = '', $algorithm = 'ARGON2I'): string
    {
        if (!empty($pw_db)) {
            $pw_db = preg_replace('/^{ARGON2I(\.B64)?}/', '', $pw_db);
            $orig_pwdb = $pw_db;
            if ($algorithm == 'ARGON2I.B64') {
                $pw_db = base64_decode($pw_db);
            }

            if (password_verify($pw, $pw_db)) {
                return "{{$algorithm}}" . $orig_pwdb;
            }
            $hash = password_hash($pw, PASSWORD_ARGON2I);
            if ($algorithm == 'ARGON2I') {
                return '{ARGON2I}' . $hash;
            }
            return '{ARGON2I.B64}' . base64_encode($hash);
            ;
        }

        $hash = password_hash($pw, PASSWORD_ARGON2I);

        if ($algorithm == 'ARGON2I') {
            return '{ARGON2I}' . $hash;
        }

        return "{ARGON2I.B64}" . base64_encode($hash);
    }

    public function argon2idCrypt(string $pw, string $pw_db = '', string $algorithm = 'ARGON2ID'): string
    {
        if (!defined('PASSWORD_ARGON2ID')) {
            throw new \Exception("Requires PHP 7.3+");
        }

        if (!empty($pw_db)) {
            $pw_db = preg_replace('/^{ARGON2ID(\.B64)?}/', '', $pw_db);

            $orig_pwdb = $pw_db;

            if ($algorithm == 'ARGON2ID.B64') {
                $pw_db = base64_decode($pw_db);
            }

            if (password_verify($pw, $pw_db)) {
                return "{{$algorithm}}" . $orig_pwdb;
            }

            $hash = password_hash($pw, PASSWORD_ARGON2ID);

            if ($algorithm == 'ARGON2ID') {
                return '{ARGON2ID}' . $hash;
            }
            // if($algorithm == 'ARGON2ID.B64') {
            return '{ARGON2ID.B64}' . base64_encode($hash);
        }

        $hash = password_hash($pw, PASSWORD_ARGON2ID);

        if ($algorithm == 'ARGON2ID') {
            return '{ARGON2ID}' . $hash;
        }
        return '{ARGON2ID.B64}' . base64_encode($hash);
    }


    /**
     * @see https://github.com/postfixadmin/postfixadmin/issues/58
     *
     * Note, this is really a base64 encoded CRYPT formatted hash; this isn't the same as a
     * sha512 hash that's been base64 encoded.
     */
    public function pacrypt_sha512crypt_b64($pw, $pw_db = "")
    {
        if (!function_exists('random_bytes') || !function_exists('crypt') || !defined('CRYPT_SHA512') || !function_exists('mb_substr')) {
            throw new Exception("sha512.b64 not supported!");
        }
        if (!$pw_db) {
            $salt = mb_substr(rtrim(base64_encode(random_bytes(16)), '='), 0, 16, '8bit');
            return '{SHA512-CRYPT.B64}' . base64_encode(crypt($pw, '$6$' . $salt));
        }

        $password = "#Thepasswordcannotbeverified";
        if (strncmp($pw_db, '{SHA512-CRYPT.B64}', 18) == 0) {
            $dcpwd = base64_decode(mb_substr($pw_db, 18, null, '8bit'), true);
            if ($dcpwd !== false && !empty($dcpwd) && strncmp($dcpwd, '$6$', 3) == 0) {
                $password = '{SHA512-CRYPT.B64}' . base64_encode(crypt($pw, $dcpwd));
            }
        } elseif (strncmp($pw_db, '{MD5-CRYPT}', 11) == 0) {
            $dcpwd = mb_substr($pw_db, 11, null, '8bit');
            if (!empty($dcpwd) && strncmp($dcpwd, '$1$', 3) == 0) {
                $password = '{MD5-CRYPT}' . crypt($pw, $dcpwd);
            }
        }
        return $password;
    }
}
