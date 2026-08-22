<?php

$method_index = array_search('-s', $argv, true);
$method = $method_index === false ? '' : ($argv[$method_index + 1] ?? '');
$test_index = array_search('-t', $argv, true);
$stored_password = $test_index === false ? '' : ($argv[$test_index + 1] ?? '');

stream_get_contents(STDIN);

if ($method === 'FIXTUREEMPTY') {
    exit(0);
}

if ($method === 'FIXTUREINVALID') {
    echo "not a password hash\n";
    exit(0);
}

$password = empty($stored_password) ? "{{$method}}fixture-hash" : "$stored_password (verified)";
echo "$password\n";

if ($method === 'FIXTUREWARN') {
    fwrite(STDERR, "fixture warning\n");
}

exit($method === 'FIXTUREFAIL' ? 1 : 0);
