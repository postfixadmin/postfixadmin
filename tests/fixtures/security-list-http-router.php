<?php

// Test-only router: never place this fixture in the public document root.
if (PHP_SAPI !== 'cli-server' || !getenv('PFA_SECURITY_LIST_TEST_CONFIG')) {
    http_response_code(404);
    exit;
}
define('PHPUNIT_TEST', true);
require dirname(__DIR__, 2) . '/vendor/autoload.php';
$CONF = array_replace($CONF, json_decode(file_get_contents(getenv('PFA_SECURITY_LIST_TEST_CONFIG')), true, 512, JSON_THROW_ON_ERROR));
require dirname(__DIR__, 2) . '/public/common.php';
$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
if ($path === '/__session') {
    $globalAdmin = ($_GET['role'] ?? '') === 'global-admin';
    init_session($globalAdmin ? 'admin@security-list-http.example.com' : 'test@security-list-http.example.com', $globalAdmin, true);
    if ($globalAdmin) {
        $_SESSION['sessid']['roles'][] = 'global-admin';
    }
    echo 'Session ready';
} else {
    $pages = [
        '/app-passwords.php' => 'app-passwords.php',
        '/users/app-passwords.php' => 'app-passwords.php',
        '/totp-exceptions.php' => 'totp-exceptions.php',
        '/users/totp-exceptions.php' => 'totp-exceptions.php',
    ];
    if (!isset($pages[$path])) {
        http_response_code(404);
        exit;
    }
    // Both public aliases and user routes execute these complete controllers.
    chdir(dirname(__DIR__, 2) . '/public/users');
    require $pages[$path];
}
