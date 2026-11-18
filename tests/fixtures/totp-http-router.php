<?php

// Test-only router: never place this fixture in the public document root.
if (PHP_SAPI !== 'cli-server' || !getenv('PFA_TOTP_TEST_CONFIG')) {
    http_response_code(404);
    exit;
}
define('PHPUNIT_TEST', true);
require dirname(__DIR__, 2) . '/vendor/autoload.php';
$CONF = array_replace($CONF, json_decode(file_get_contents(getenv('PFA_TOTP_TEST_CONFIG')), true, 512, JSON_THROW_ON_ERROR));
require dirname(__DIR__, 2) . '/public/common.php';
$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
if ($path === '/__session') {
    init_session('test@totp-http.example.com', in_array($_GET['role'] ?? '', ['admin', 'global-admin'], true), true);
    if (($_GET['role'] ?? '') === 'global-admin') {
        $_SESSION['sessid']['roles'][] = 'global-admin';
    }
    header('Content-Type: application/json');
    echo json_encode(['token' => CsrfToken::generate()]);
} elseif ($path === '/users/totp.php') {
    chdir(dirname(__DIR__, 2) . '/public/users');
    require 'totp.php';
} elseif (in_array($path, ['/edit.php', '/list.php', '/list-virtual.php'], true)) {
    chdir(dirname(__DIR__, 2) . '/public');
    require basename($path);
} else {
    http_response_code(404);
}
