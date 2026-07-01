<?php

require_once(dirname(__FILE__) . '/../common.php');

function pfa_handle_invalid_token(): void
{
    http_response_code(419); // HTTP 419 Page Expired.

    $loginUrl = 'login.php';
    $title = Config::lang('session_expired_title') ?: 'Session expired';
    $message = Config::lang('session_expired_message') ?: 'Your session has expired. Please log in again.';
    $redirect = Config::lang('session_expired_redirect') ?: 'You will be redirected to the login page in 5 seconds.';
    $button = Config::lang('session_expired_button') ?: 'Go to login';

    echo <<<HTML
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta http-equiv="refresh" content="5;url={$loginUrl}">
    <title>{$title}</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 0; background: #f6f7f9; color: #222; }
        main { max-width: 520px; margin: 12vh auto; padding: 2rem; background: #fff; border: 1px solid #ddd; border-radius: .5rem; text-align: center; }
        h1 { margin-top: 0; font-size: 1.5rem; }
        p { line-height: 1.45; }
        a { display: inline-block; margin-top: 1rem; padding: .65rem 1rem; background: #337ab7; color: #fff; text-decoration: none; border-radius: 4px; }
    </style>
</head>
<body>
<main>
    <h1>{$title}</h1>
    <p>{$message}</p>
    <p>{$redirect}</p>
    <a href="{$loginUrl}">{$button}</a>
</main>
</body>
</html>
HTML;
    exit(0);
}
