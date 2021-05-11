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
        'MD5-CRYPT',
        'PLAIN-MD5',
        'CRYPT',
    ];

    public function __construct(string $algorithm)
    {
        $this->algorithm = $algorithm;
    }

    /**
     * When called with a 'pw' and a 'pw_db' (hash from e.g. database).
     *
     * If the return value matches $pw_db, then the plain text password ('pw') is correct.
     *
     * @param string $pw - plain text password
     * @param string $pw_db - hash from e.g. database (what we're comparing $pw to).
     * @return string if $pw is correct (hashes to $pw_db) then we return $pw_db. Else we return a new hash.
     *
     * @throws Exception
     */
    public function pacrypt(string $pw, string $pw_db = ''): string
    {
        $algorithm = strtoupper($this->algorithm);

        // try and 'upgrade' some dovecot commands to use local algorithms (rather tnan a dependency on the dovecot binary).
        if (preg_match('/^DOVECOT:/i', $algorithm)) {
            $tmp = preg_replace('/^DOVECOT:/i', '', $algorithm);
            if (in_array($tmp, self::DOVECOT_NATIVE)) {
                $algorithm = $tmp;
            } else {
                error_log("Warning: using algorithm that requires proc_open: $algorithm, consider using one of : " . implode(', ', self::DOVECOT_NATIVE));
            }
        }

        if (!empty($pw_db) && preg_match('/^{([0-9a-z-\.]+)}/i', $pw_db, $matches)) {
            $method_in_hash = $matches[1];
            if ('COURIER:' . strtoupper($method_in_hash) == $algorithm) {
                // don't try and be clever.
            } elseif ($algorithm != $method_in_hash) {
                error_log("Hey, you fed me a password using {$method_in_hash}, but the system is configured to use {$algorithm}");
                $algorithm = $method_in_hash;
            }
        }
        if ($algorithm == 'SHA512CRYPT.B64') {
            $algorithm = 'SHA512-CRYPT.B64';
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
            case 'COURIER:SSHA':
                return $this->hashSha1Salted($pw, $pw_db);

            case 'SHA256':
            case 'COURIER:SHA256':
                return $this->hashSha256($pw);

            case 'SHA256-CRYPT':
            case 'SHA256-CRYPT.B64':
                return $this->sha256Crypt($pw, $pw_db, $algorithm);

            case 'SHA512':
            case 'SHA512B.b64':
            case 'SHA512.B64':
                return $this->hashSha512($pw, $algorithm);

            case 'PLAIN-MD5': // {PLAIN-MD5} prefix
            case 'MD5':       // no prefix
                return $this->hashMd5($pw, $algorithm); // this is hex encoded.

            case 'COURIER:MD5':
                return '{MD5}' . base64_encode(md5($pw, true)); // seems to need to be base64 encoded.

            case 'COURIER:MD5RAW':
                return '{MD5RAW}' . md5($pw);

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

            case 'SYSTEM':
                return crypt($pw, $pw_db);

            case 'CLEAR':
            case 'PLAIN':
            case 'CLEARTEXT':
                if (!empty($pw_db)) {
                    if ($pw_db == "{{$algorithm}}$pw") {
                        return $pw_db;
                    }
                    return $pw;
                }
                return '{' . $algorithm . '}' . $pw;
            case 'MYSQL_ENCRYPT':
                return _pacrypt_mysql_encrypt($pw, $pw_db);

            // these are all supported by the above (SHA,
            case 'AUTHLIB':
                return _pacrypt_authlib($pw, $pw_db);



        }

        if (preg_match("/^DOVECOT:/", $algorithm)) {
            return _pacrypt_dovecot($pw, $pw_db);
        }

        if (substr($algorithm, 0, 9) === 'PHP_CRYPT') {
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
}
