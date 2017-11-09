<?php
/**
 * Postfixadmin (http://postfixadmin.sf.net) integration with Squirrelmail.
 * See http://squirrelmail-postfixadmin.palepurple.co.uk
 * @author David Goodwin and many others
 */


function do_header() {
    global $color;
    displayPageHeader($color, 'None');
}

function do_footer() {
    echo "</body></html>";
}

function _display_password_form() {
    bindtextdomain('postfixadmin', SM_PATH . 'plugins/postfixadmin/locale');
    textdomain('postfixadmin');
    do_header('Postfixadmin Squirrelmail - Login');
    echo _('The PostfixAdmin plugin needs your current mailbox password');
    echo "<form action='' method='post'>";
    echo _('Password for');
    echo " " . $_SESSION['username'] . " :";
    echo "<input type='password' name='password' value=''>";
    echo "<input type='submit' value='" . _('Submit') . "'></form>";
    do_footer();
}

/**
 * This returns a Zend_XmlRpc_Client instance - unless we can't log you in...
 */
function get_xmlrpc() {
    global $CONF;
    require_once('Zend/XmlRpc/Client.php');
    $client = new Zend_XmlRpc_Client($CONF['xmlrpc_url']);
    $http_client = $client->getHttpClient();
    $http_client->setCookieJar();

    $login_object = $client->getProxy('login');

    if (empty($_SESSION['password'])) {
        if (empty($_POST['password'])) {
            _display_password_form();
            exit(0);
        } else {
            try {
                $success = $login_object->login($_SESSION['username'], $_POST['password']);
            } catch (Exception $e) {
                //var_dump($client->getHttpClient()->getLastResponse()->getBody());
                error_log("Failed to login to xmlrpc instance - " . $e->getMessage());
                die('Failed to login to xmlrpc instance');
            }
            if ($success) {
                $_SESSION['password'] = $_POST['password'];
                // reload the current page as a GET request.
                header("Location: {$_SERVER['REQUEST_URI']}");
                exit(0);
            } else {
                _display_password_form();
                exit(0);
            }
        }
    } else {
        $success = $login_object->login($_SESSION['username'], $_SESSION['password']);
    }

    if (!$success) {
        unset($_SESSION['password']);
        die("Invalid details cached... refresh this page and re-enter your mailbox password");
    }
    return $client;
}

function include_if_exists($filename) {
    if (file_exists($filename)) {
        include_once($filename);
    }
    return;
}
global $optmode;
$optmode = 'display';

//
// check_email
// Action: Checks if email is valid and returns TRUE if this is the case.
// Call: check_email (string email)
//
function check_email($email) {
    $return = filter_var($email, FILTER_VALIDATE_EMAIL);
    if ($return === false) {
        return false;
    }
    return true;
}
