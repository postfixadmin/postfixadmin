<?php

namespace PostfixAdmin\PasswordHashing;

class Crypt
{
    /**
     * @var string
     */
    private $algorithm;

    public const SUPPORTED = [
        'COURIER:MD5', // binary md5 base64 encoded
        'COURIER:MD5RAW', // hex-encoded md5 with {MD5RAW} prefix
        'COURIER:SSHA', // same as SSHA
        'COURIER:SHA256', // same as SHA256
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
        'MD5-CRYPT', // crypt with $1$ prefix.
        'PLAIN-MD5', 'MD5',
        'CRYPT',  // anything crypt() can cope with, optional {CRYPT} prefix, new hashes will use CRYPT_BLOWFISH
        'SYSTEM', // DES CRYPT, probably best to avoid
        'PLAIN', 'CLEAR', 'CLEARTEXT',
    ];

    public function __construct(string $algorithm)
    {
        $algorithm = strtoupper($algorithm);
        if (!in_array($algorithm, self::SUPPORTED)) {
            throw new \InvalidArgumentException("Unsupported hashing scheme / algorith : $algorithm");
        }
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
    public function crypt(string $clearText, string $passwordHash = null): string
    {
        $algorithm = $this->algorithm;

        switch ($this->algorithm) {

            case 'SHA1':
            case 'SHA1.B64':
            case 'SHA1.HEX':
                return $this->hashSha1($clearText, $algorithm);

            case 'BLF-CRYPT':
            case 'BLF-CRYPT.B64':
                return $this->blowfishCrypt($clearText, $passwordHash, $algorithm);

            case 'SHA512-CRYPT':
            case 'SHA512-CRYPT.B64':
                return $this->sha512Crypt($clearText, $passwordHash, $algorithm);

            case 'ARGON2I':
            case 'ARGON2I.B64':
                return $this->argon2iCrypt($clearText, $passwordHash, $algorithm);

            case 'ARGON2ID':
            case 'ARGON2ID.B64':
                return $this->argon2idCrypt($clearText, $passwordHash, $algorithm);

            case 'SSHA':
            case 'COURIER:SSHA':
                return $this->hashSha1Salted($clearText, $passwordHash);

            case 'SHA256':
            case 'COURIER:SHA256':
                return $this->hashSha256($clearText);

            case 'SHA256-CRYPT':
            case 'SHA256-CRYPT.B64':
                return $this->sha256Crypt($clearText, $passwordHash, $algorithm);

            case 'SHA512':
            case 'SHA512B.b64':
            case 'SHA512.B64':
                return $this->hashSha512($clearText, $algorithm);

            case 'PLAIN-MD5': // {PLAIN-MD5} prefix
            case 'MD5':       // no prefix
                return $this->hashMd5($clearText, $algorithm); // this is hex encoded.

            case 'COURIER:MD5':
                return '{MD5}' . base64_encode(md5($clearText, true)); // seems to need to be base64 encoded.

            case 'COURIER:MD5RAW':
                return '{MD5RAW}' . md5($clearText);

            case 'MD5-CRYPT':
                return $this->cryptMd5($clearText, $passwordHash, $algorithm);

            case 'CRYPT':
                $prefix = false;
                if (!empty($passwordHash)) {
                    $prefix = (substr($passwordHash, 0, 7) == '{CRYPT}');
                    $passwordHash = preg_replace('/^{CRYPT}/', '', $passwordHash);
                }
                if (empty($passwordHash)) {
                    $passwordHash = '$2y$10$' . substr(sha1(random_bytes(8)), 0, 22);
                }
                $str = crypt($clearText, $passwordHash);
                return $prefix ? '{CRYPT}' . $str : $str;


            // legacy / older crypt variant (weaker salt, weaker mechanism, DES)
            case 'SYSTEM':
                if (empty($passwordHash)) {
                    $passwordHash = bin2hex(random_bytes(1)); // CRYPT_STD_DES
                }
                return crypt($clearText, $passwordHash);

            case 'CLEAR':
            case 'PLAIN':
            case 'CLEARTEXT':
                if (!empty($passwordHash)) {
                    if ($passwordHash == "{{$algorithm}}$clearText") {
                        return $passwordHash;
                    }
                    return $clearText;
                }
                return '{' . $algorithm . '}' . $clearText;
        }

        throw new \LogicException("Supported hash, but not implemented?");
    }

    public function hashSha1(string $clearText, string $algorithm = 'SHA1'): string
    {
        $hash = hash('sha1', $clearText, true);

        if (preg_match('/\.HEX$/', $algorithm)) {
            $hash = bin2hex($hash);
        } else {
            $hash = base64_encode($hash);
        }
        return "{{$algorithm}}{$hash}";
    }

    public function hashSha1Salted(string $clearText, string $hash = null): string
    {
        if (empty($hash)) {
            $salt = base64_encode(random_bytes(3)); // 4 char salt.
        } else {
            $salt = substr(base64_decode(substr($hash, 6)), 20);
        }
        return '{SSHA}' . base64_encode(sha1($clearText . $salt, true) . $salt);
    }

    public function hashSha512(string $clearText, string $algorithm = 'SHA512')
    {
        $prefix = '{SHA512}';

        if ($algorithm == 'SHA512.B64' || $algorithm == 'sha512b.b64') {
            $prefix = '{SHA512.B64}';
        }

        return $prefix . base64_encode(hash('sha512', $clearText, true));
    }

    public function hashMd5(string $clearText, string $algorithm = 'PLAIN-MD5'): string
    {
        if ($algorithm == 'PLAIN-MD5') {
            return '{PLAIN-MD5}' . md5($clearText);
        }
        return md5($clearText);
    }

    public function hashSha256(string $clearText): string
    {
        return '{SHA256}' . base64_encode(hash('sha256', $clearText, true));
    }

    public function cryptMd5(string $clearText, string $hash = null, $algorithm = 'MD5-CRYPT')
    {
        if (!empty($hash)) {
            $hash = preg_replace('/^{MD5.*}/', '', $hash);
        }
        if (empty($hash)) {
            $hash = '$1$' . substr(sha1(random_bytes(8)), 0, 16);
        }
        return "{{$algorithm}}" . crypt($clearText, $hash);
    }

    public function blowfishCrypt(string $clearText, string $hash = null, string $algorithm = 'BLF-CRYPT'): string
    {
        if (!empty($hash)) {
            if ($algorithm == 'BLF-CRYPT') {
                $hash = preg_replace('/^{BLF-CRYPT}/', '', $hash);
            }
            if ($algorithm == 'BLF-CRYPT.B64') {
                $hash = base64_decode(preg_replace('/^{BLF-CRYPT.B64}/', '', $hash));
            }
            $generated = crypt($clearText, $hash);

            if ($algorithm == 'BLF-CRYPT.B64') {
                $generated = base64_encode($generated);
            }
            return "{{$algorithm}}{$generated}";
        }

        /**
         * @psalm-suppress InvalidScalarArgument
         */
        $r = password_hash($clearText, PASSWORD_BCRYPT);
        if (!is_string($r)) {
            throw new Exception("Failed to generate password, using $algorithm");
        }
        if ($algorithm == 'BLF-CRYPT.B64') {
            return '{BLF-CRYPT.B64}' . base64_encode($r);
        }
        return '{BLF-CRYPT}' . $r;
    }

    public function sha256Crypt(string $clearText, string $hash = null, string $algorithm = 'SHA256-CRYPT'): string
    {
        if (!empty($hash)) {
            $hash = preg_replace('/^{SHA256-CRYPT(\.B64)?}/', '', $hash);

            if ($algorithm == 'SHA256-CRYPT.B64') {
                $hash = base64_decode($hash);
            }
        }

        if (empty($hash)) {
            $hash = '$5$' . substr(sha1(random_bytes(8)), 0, 16);
        }

        $generated = crypt($clearText, $hash);

        if ($algorithm == 'SHA256-CRYPT.B64') {
            return '{SHA256-CRYPT.B64}' . base64_encode($generated);
        }
        return "{SHA256-CRYPT}" . $generated;
    }

    public function sha512Crypt(string $pw, string $hash = null, string $algorithm = 'SHA512-CRYPT'): string
    {
        if (!empty($hash)) {
            $hash = preg_replace('/^{SHA512-CRYPT(\.B64)?}/', '', $hash);

            if ($algorithm == 'SHA512-CRYPT.B64') {
                $hash = base64_decode($hash);
            }
        }

        if (empty($hash)) {
            $hash = '$6$' . substr(sha1(random_bytes(8)), 0, 16);
        }

        $generated = crypt($pw, $hash);

        if ($algorithm == 'SHA512-CRYPT.B64') {
            $generated = base64_encode($generated);
            return "{SHA512-CRYPT.B64}{$generated}";
        }

        return "{SHA512-CRYPT}$generated";
    }

    public function argon2ICrypt(string $clearText, string $hash = null, $algorithm = 'ARGON2I'): string
    {
        if (!empty($hash)) {
            $hash = preg_replace('/^{ARGON2I(\.B64)?}/', '', $hash);
            $orig_pwdb = $hash;
            if ($algorithm == 'ARGON2I.B64') {
                $hash = base64_decode($hash);
            }

            if (password_verify($clearText, $hash)) {
                return "{{$algorithm}}" . $orig_pwdb;
            }
            /**
             * @psalm-suppress InvalidScalarArgument
             */
            $generated = password_hash($clearText, PASSWORD_ARGON2I);
            if ($algorithm == 'ARGON2I') {
                return '{ARGON2I}' . $generated;
            }
            return '{ARGON2I.B64}' . base64_encode($generated);
        }


        /**
         * @psalm-suppress InvalidScalarArgument
         */
        $generated = password_hash($clearText, PASSWORD_ARGON2I);

        if ($algorithm == 'ARGON2I') {
            return '{ARGON2I}' . $generated;
        }

        return "{ARGON2I.B64}" . base64_encode($generated);
    }

    public function argon2idCrypt(string $clearText, string $hash = null, string $algorithm = 'ARGON2ID'): string
    {
        if (!defined('PASSWORD_ARGON2ID')) {
            throw new Exception("$algorithm is not supported; requires PHP 7.3+");
        }

        if (!empty($hash)) {
            $hash = preg_replace('/^{ARGON2ID(\.B64)?}/', '', $hash);

            $orig_pwdb = $hash;

            if ($algorithm == 'ARGON2ID.B64') {
                $hash = base64_decode($hash);
            }

            if (password_verify($clearText, $hash)) {
                return "{{$algorithm}}" . $orig_pwdb;
            }

            /**
             * @psalm-suppress InvalidScalarArgument
             */
            $generated = password_hash($clearText, PASSWORD_ARGON2ID);

            if ($algorithm == 'ARGON2ID') {
                return '{ARGON2ID}' . $generated;
            }
            // if($algorithm == 'ARGON2ID.B64') {
            return '{ARGON2ID.B64}' . base64_encode($generated);
        }

        /**
         * @psalm-suppress InvalidScalarArgument
         */
        $generated = password_hash($clearText, PASSWORD_ARGON2ID);

        if ($algorithm == 'ARGON2ID') {
            return '{ARGON2ID}' . $generated;
        }
        return '{ARGON2ID.B64}' . base64_encode($generated);
    }
}
