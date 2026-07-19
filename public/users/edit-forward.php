<?php

/**
 * Redirect users to the shared AliasHandler forwarding form.
 */

require_once(__DIR__ . '/../common.php');

authentication_require_role('user');

if (!Config::bool('edit_alias')) {
    header('Location: main.php');
    exit(0);
}

$username = authentication_get_username();
$query = http_build_query(array(
    'table' => 'alias',
    'edit' => $username,
));

header('Location: ../edit.php?' . $query);
exit(0);
