<?php

class CsrfToken
{
    public function generate(): string
    {
        $this->cleanUp();
        $token = bin2hex(random_bytes(16));
        $_SESSION['CSRF_Tokens'][$token] = (time() + 3600); // tokens last for an hour?
        return $token;
    }

    public function cleanUp(): void
    {
        if (!isset($_SESSION['CSRF_Tokens']) || !is_array($_SESSION['CSRF_Tokens'])) {
            $_SESSION['CSRF_Tokens'] = [];
        }

        foreach ($_SESSION['CSRF_Tokens'] as $key => $value) {
            if ($value < (time())) {
                unset($_SESSION['CSRF_Tokens'][$key]);
            }
        }
    }

    public function assertValid(string $token): void
    {
        if (!isset($_SESSION['CSRF_Tokens'][$token])) {
            http_response_code(419);
            die("Invalid token (session timeout; refresh the page and try again?)");
        }
        // token can only be used once.
        unset($_SESSION['CSRF_Tokens'][$token]);
    }

}

