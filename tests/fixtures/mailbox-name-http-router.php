<?php

// Test-only router; never deploy it in the public document root.
if (PHP_SAPI !== 'cli-server' || !getenv('PFA_NAME_TEST_CONFIG')) {
    http_response_code(404);
    exit;
}
define('PHPUNIT_TEST', true);
require dirname(__DIR__, 2) . '/vendor/autoload.php';
$CONF = array_replace($CONF, json_decode(file_get_contents(getenv('PFA_NAME_TEST_CONFIG')), true, 512, JSON_THROW_ON_ERROR));
require dirname(__DIR__, 2) . '/public/common.php';
$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
if ($path === '/__session') {
    init_session('http@name-http.example', false, true);
    if (($_GET['anonymous'] ?? '') === 'yes') {
        unset($_SESSION['sessid']);
    }
    echo 'Session ready';
    return;
}
$pages = ['/edit.php' => 'edit.php', '/users/main.php' => 'users/main.php'];
if (!isset($pages[$path])) {
    http_response_code(404);
    exit;
}
chdir(dirname(__DIR__, 2) . '/public' . ($path === '/users/main.php' ? '/users' : ''));
require basename($pages[$path]);
