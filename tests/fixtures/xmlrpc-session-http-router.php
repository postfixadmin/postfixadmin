<?php

// Test-only router: never place this fixture in the public document root.
if (PHP_SAPI !== 'cli-server' || !getenv('PFA_XMLRPC_TEST_CONFIG')) {
    http_response_code(404);
    exit;
}
define('PHPUNIT_TEST', true);
require dirname(__DIR__, 2) . '/vendor/autoload.php';
$CONF = array_replace($CONF, json_decode(file_get_contents(getenv('PFA_XMLRPC_TEST_CONFIG')), true, 512, JSON_THROW_ON_ERROR));
require dirname(__DIR__, 2) . '/public/common.php';
$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
if ($path === '/__legacy-pending') {
    // Reproduce a cookie left by the old implementation before deployment.
    init_session('b@xmlrpc-http.example.com', false, false);
    $_SESSION['authenticated'] = true;
    echo 'Legacy session ready';
} elseif ($path === '/__state') {
    header('Content-Type: application/json');
    echo json_encode([
        'token' => CsrfToken::generate(),
        'username' => $_SESSION['sessid']['username'] ?? null,
        'mfa_complete' => $_SESSION['sessid']['mfa_complete'] ?? null,
        'rpc_authorized' => $_SESSION['authenticated'] ?? false,
    ]);
} elseif (in_array($path, ['/xmlrpc.php', '/users/login.php', '/users/login-mfa.php'], true)) {
    $page = dirname(__DIR__, 2) . '/public' . $path;
    chdir(dirname($page));
    require $page;
} else {
    http_response_code(404);
}
