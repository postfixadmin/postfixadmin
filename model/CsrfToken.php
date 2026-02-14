<?php

class CsrfToken
{
    public static function generate(): string
    {
        if (!isset($_SESSION['CSRF_Tokens']) || !is_array($_SESSION['CSRF_Tokens'])) {
            $_SESSION['CSRF_Tokens'] = [];
        }

        // remove any expired tokens
        foreach ($_SESSION['CSRF_Tokens'] as $key => $value) {
            if ($value < (time())) {
                unset($_SESSION['CSRF_Tokens'][$key]);
            }
        }
        $token = bin2hex(random_bytes(16));
        $_SESSION['CSRF_Tokens'][$token] = (time() + 3600); // is an hour an acceptable expiry time?
        return $token;
    }

    /**
     * @param string $token
     * @return bool
     * @throws CsrfInvalidException
     */
    public static function assertValid(string $token): bool
    {
        if (!is_array($_SESSION['CSRF_Tokens'])) {
            $_SESSION['CSRF_Tokens'] = [];
        }

        $value = (int)($_SESSION['CSRF_Tokens'][$token] ?? 0);

        // token can only be used once.
        unset($_SESSION['CSRF_Tokens'][$token]);

        // token cannot have expired
        if ($value < time()) {
            http_response_code(419);
            die("Invalid CSRF token - expired session or idle timeout. Refresh the page and try again.");
        }

    }

}
