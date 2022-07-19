<?php
/*
* to do :   - add pagination
*	          - sort by mounth
* 	        - remove old logs
*/

require_once('common.php');

authentication_require_role('admin');

if (!is_dir(__DIR__ . '/../maillog')) {
    die("../maillog does not exist");
}

$smarty = PFASmarty::getInstance();

$SESSID_USERNAME = authentication_get_username();
if (authentication_has_role('global-admin')) {
    $list_domains = list_domains();
} else {
    $list_domains = list_domains_for_admin($SESSID_USERNAME);
}

if (empty($list_domains) || !is_array($list_domains)) {
    die("You can't see any domains");
}

// Some sort of default
$fDomain = $list_domains[0];

function assert_domain_is_ok($string, array $domains)
{
    if (!in_array($string, $domains)) {
        die("Unknown domain");
    }

    // should not be able to contain a /
    if (strpos($string, '/') !== false) {
        die("Unknown domain");
    }
}

/* if downloading log */
if ($_SERVER['REQUEST_METHOD'] == "GET") {
    $fDomain = $_GET['fDomain'] ?? $fDomain;

    assert_domain_is_ok($fDomain, $list_domains);

    //check if file exists
    if (isset($_GET['get_log'])) {
        // do not allow $_GET['get_log'] to contain a /
        if (strpos($_GET['get_log'], '/') !== false) {
            die("Unknown file");
        }

        $file = __DIR__ . '/../maillog/' . $fDomain . '/' . $_GET['get_log'];

        if (!file_exists($file)) {
            die("The file does not exists");
        }

        header('Content-Description: File Transfer');
        header('Content-Type: application/octet-stream');
        header('Content-Disposition: attachment; filename=' . basename($file));
        header('Content-Transfer-Encoding: binary');
        header('Expires: 0');
        header('Cache-Control: must-revalidate, post-check=0, pre-check=0');
        header('Pragma: public');
        header('Content-Length: ' . filesize($file));
        ob_clean();
        flush();
        readfile($file);
    }
} elseif ($_SERVER['REQUEST_METHOD'] == "POST") {
    if (isset($_POST['fDomain'])) {
        $fDomain = $_POST['fDomain'];

        assert_domain_is_ok($fDomain, $list_domains);
    }
} else {
    die('Unknown request method');
}


//check if folder exists
$path = __DIR__ . '/../maillog/' . $fDomain;

$logs = [];
if (is_dir($path)) {

    //read logs from path
    $files = scandir($path, 1);
    //remove . and ..  from result

    // limit the number returned to the last 60.
    $files = array_slice($files, 0, 60);

    $i = 0;

    foreach ($files as $file) {
        if ($file === '.' || $file === '..') {
            continue;
        }

        $i++;
        $logs[] = [
            'name' => basename($file),
            'size' => round(filesize($path . '/' . $file) / 1024, 3),
            'number' => $i
        ];
    }
}

$smarty->assign("domain_list", $list_domains);
$smarty->assign('domain_selected', $fDomain);

$smarty->assign('logs', $logs);

$smarty->assign('smarty_template', 'maillog');
$smarty->display('index.tpl');
