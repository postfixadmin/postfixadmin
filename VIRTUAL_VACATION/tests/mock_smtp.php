<?php

declare(strict_types=1);

if ($argc !== 3) {
    fwrite(STDERR, "Usage: mock_smtp.php PORT_FILE MESSAGE_FILE\n");
    exit(64);
}

$server = stream_socket_server('tcp://127.0.0.1:0', $errorCode, $errorMessage);
if ($server === false) {
    fwrite(STDERR, "Could not start mock SMTP server ({$errorCode}): {$errorMessage}\n");
    exit(1);
}
$address = stream_socket_get_name($server, false);
if (!is_string($address) || !str_contains($address, ':')) {
    fwrite(STDERR, "Could not determine mock SMTP server port\n");
    exit(1);
}
[, $port] = explode(':', $address, 2);
if (file_put_contents($argv[1], $port) === false) {
    fwrite(STDERR, "Could not publish mock SMTP server port\n");
    exit(1);
}

$connection = stream_socket_accept($server, 10);
if ($connection === false) {
    fwrite(STDERR, "Mock SMTP server timed out waiting for a connection\n");
    exit(1);
}
fwrite($connection, "220 vacation.test ESMTP\r\n");
$message = '';
$receivingData = false;
while (($line = fgets($connection)) !== false) {
    $command = rtrim($line, "\r\n");
    if ($receivingData) {
        if ($command === '.') {
            if (file_put_contents($argv[2], $message) === false) {
                fwrite($connection, "451 Could not store message\r\n");
                exit(1);
            }
            $receivingData = false;
            fwrite($connection, "250 Message accepted\r\n");
            continue;
        }
        $message .= (str_starts_with($command, '..') ? substr($command, 1) : $command) . "\r\n";
        continue;
    }
    if (preg_match('/^(?:EHLO|HELO)\b/i', $command)) {
        fwrite($connection, "250-vacation.test\r\n250 8BITMIME\r\n");
    } elseif (preg_match('/^(?:MAIL FROM|RCPT TO):/i', $command)) {
        fwrite($connection, "250 OK\r\n");
    } elseif (strcasecmp($command, 'DATA') === 0) {
        $receivingData = true;
        fwrite($connection, "354 End data with <CR><LF>.<CR><LF>\r\n");
    } elseif (strcasecmp($command, 'QUIT') === 0) {
        fwrite($connection, "221 Bye\r\n");
        break;
    } else {
        fwrite($connection, "500 Unsupported command\r\n");
    }
}

fclose($connection);
fclose($server);
