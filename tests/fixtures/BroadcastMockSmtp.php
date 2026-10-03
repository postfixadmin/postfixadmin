<?php

$server = stream_socket_server('tcp://127.0.0.1:0', $errno, $error);
if ($server === false) {
    fwrite(STDERR, "Unable to start fixture SMTP server\n");
    exit(1);
}
$address = stream_socket_get_name($server, false);
echo substr($address, strrpos($address, ':') + 1) . "\n";
flush();
$client = stream_socket_accept($server, 15);
if ($client === false) {
    fclose($server);
    exit(1);
}
stream_set_timeout($client, 15);
fwrite($client, "220 fixture ready\r\n");
$capture = ['auth_user' => '', 'auth_password' => '', 'data' => ''];
$state = 'command';
while (($line = fgets($client)) !== false) {
    $command = rtrim($line, "\r\n");
    if ($state === 'auth_user') {
        $capture['auth_user'] = $command;
        $state = 'auth_password';
        fwrite($client, "334 UGFzc3dvcmQ6\r\n");
    } elseif ($state === 'auth_password') {
        $capture['auth_password'] = $command;
        $state = 'command';
        fwrite($client, "235 fixture authenticated\r\n");
    } elseif ($state === 'data') {
        if ($command === '.') {
            $state = 'command';
            fwrite($client, "250 fixture accepted\r\n");
        } else {
            $capture['data'] .= $line;
        }
    } elseif (str_starts_with($command, 'EHLO ')) {
        fwrite($client, "250 fixture ready\r\n");
    } elseif ($command === 'AUTH LOGIN') {
        $state = 'auth_user';
        fwrite($client, "334 VXNlcm5hbWU6\r\n");
    } elseif (str_starts_with($command, 'MAIL FROM:') || str_starts_with($command, 'RCPT TO:')) {
        fwrite($client, "250 fixture accepted\r\n");
    } elseif ($command === 'DATA') {
        $state = 'data';
        fwrite($client, "354 fixture data\r\n");
    } elseif ($command === 'QUIT') {
        fwrite($client, "221 fixture goodbye\r\n");
        break;
    } else {
        fwrite($client, "500 fixture unsupported command\r\n");
    }
}
fclose($client);
fclose($server);
echo json_encode($capture, JSON_THROW_ON_ERROR) . "\n";
