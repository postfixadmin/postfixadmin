<?php

// Test-only router: never place this fixture in the public document root.
if (PHP_SAPI !== 'cli-server' || !getenv('PFA_MFA_TEST_CONFIG')) {
    http_response_code(404);
    exit;
}
define('PHPUNIT_TEST', true);
require dirname(__DIR__, 2) . '/vendor/autoload.php';
$CONF = array_replace($CONF, json_decode(file_get_contents(getenv('PFA_MFA_TEST_CONFIG')), true, 512, JSON_THROW_ON_ERROR));
require dirname(__DIR__, 2) . '/public/common.php';
$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
if ($path === '/__session') {
    init_session('test@mfa-http.example.com', ($_GET['role'] ?? '') === 'admin', false);
    header('Content-Type: application/json');
    echo json_encode(['token' => CsrfToken::generate()]);
} elseif ($path === '/__state') {
    header('Content-Type: application/json');
    echo json_encode($_SESSION['sessid'] ?? []);
} elseif (in_array($path, ['/login-mfa.php', '/users/login-mfa.php', '/edit.php'], true)) {
    $file = dirname(__DIR__, 2) . '/public' . $path;
    chdir(dirname($file));
    require $file;
} else {
    http_response_code(404);
}
