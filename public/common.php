<?php

require_once(dirname(__FILE__) . '/../common.php');

set_exception_handler(function ($exception) {

    // we could do something much better here - e.g. if the user is logged in, and an admin, show a full stakc trace or something.
    if ($exception instanceof CsrfInvalidException) {
        http_response_code(419); // HTTP 419 Page Expired.
        echo "<p><strong>PostfixAdmin Error:</strong></p><p>Invalid CSRF token. Try going back, and refreshing the page before trying again.</p>\n";
        exit(0);
    }

    error_log("PostfixAdmin : untrapped exception. " . $exception->getMessage() . " - " . $exception->getTraceAsString());
    http_response_code(500);
    echo "<p><strong>PostfixAdmin Error:</strong></p><p>PostfixAdmin encountered an error. More details are in the server log files (e.g. /var/log/apache2/error.log).</p>";
});

