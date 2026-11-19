<?php

require_once(dirname(__FILE__) . '/../common.php');

set_exception_handler(function ($exception) {

    // we could do something much better here - e.g. if the user is logged in, and an admin, show a full stack trace etc.
    if ($exception instanceof CsrfInvalidException) {
        // 419 is not a registered status code: without an explicit reason phrase
        // Apache replaces the status line with "500 Internal Server Error".
        header(($_SERVER['SERVER_PROTOCOL'] ?? 'HTTP/1.1') . ' 419 Page Expired', true, 419);
        $title = Config::lang('session_expired_title') ?: 'Session expired';
        $message = Config::lang('session_expired_message') ?: 'The page token is no longer valid. Please return and try again.';
        $button = Config::lang('session_expired_button') ?: 'Continue';

        echo "<!doctype html>\n";
        echo "<html><head><meta charset=\"utf-8\"><title>" . htmlentities($title) . "</title></head><body>\n";
        echo "<p><strong>" . htmlentities($title) . "</strong></p>\n";
        echo "<p>" . htmlentities($message) . "</p>\n";
        echo "<p><a href=\"main.php\">" . htmlentities($button) . "</a></p>\n";
        echo "</body></html>\n";
        exit(0);
    }

    error_log("PostfixAdmin : untrapped exception. " . $exception->getMessage() . " - " . $exception->getTraceAsString());
    http_response_code(500);
    echo "<p><strong>PostfixAdmin Error:</strong></p><p>PostfixAdmin encountered an error. More details are in the server log files (e.g. /var/log/apache2/error.log).</p>";
});
