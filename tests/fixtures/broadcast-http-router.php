<?php

// Test-only router: never place this fixture in the public document root.
if (PHP_SAPI !== 'cli-server' || !getenv('PFA_BROADCAST_TEST_CONFIG')) {
    http_response_code(404);
    exit;
}

define('PHPUNIT_TEST', true);
require dirname(__DIR__, 2) . '/vendor/autoload.php';
$CONF = array_replace($CONF, json_decode(file_get_contents(getenv('PFA_BROADCAST_TEST_CONFIG')), true, 512, JSON_THROW_ON_ERROR));
require dirname(__DIR__, 2) . '/public/common.php';

$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
if ($path === '/__session') {
    init_session('broadcast-http-admin@example.test', true, true);
    $_SESSION['sessid']['roles'][] = 'global-admin';
    header('Content-Type: application/json');
    echo json_encode(['token' => CsrfToken::generate()]);
} elseif ($path === '/broadcast-status.php') {
    chdir(dirname(__DIR__, 2) . '/public');
    require 'broadcast-status.php';
} else {
    http_response_code(404);
}
