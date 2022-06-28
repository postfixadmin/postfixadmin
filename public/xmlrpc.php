<?php
/**
 * Requires the Zend framework is installed and in the include path.
 *
 * Usage example:
 * require_once('Zend/XmlRpc/Client.php');
 * $xmlrpc = new Zend_XmlRpc_Client('https://server/xmlrpc.php');
 *
 * $http_client = $xmlrpc->getHttpClient();
 * $http_client->setCookieJar();
 *
 * $login_object = $xmlrpc->getProxy('login');
 * $success = $login_object->login($email_address, $password);
 *
 * if($success) {
 *     echo "We're logged in";
 * }
 * else {
 *     die("Auth failed");
 * }
 * $user = $xmlrpc->getProxy('user');
 * $alias = $xmlrpc->getProxy('alias');
 * $vacation = $xmlrpc->getProxy('vacation');
 *
 * if($vacation->checkVacation()) {
 *     echo "Vacation turned on for user";
 * }
 *
 * Note, the requirement that your XmlRpc client provides cookies with each request.
 * If it does not do this, then your authentication details will not persist across requests, and
 * this XMLRPC interface will not work.
 */
require_once(dirname(__FILE__) . '/common.php');

if ($CONF['xmlrpc_enabled'] == false) {
    die("xmlrpc support disabled");
}

require_once('Zend/XmlRpc/Server.php');
$server = new Zend_XmlRpc_Server();

/**
 * @param string $username
 * @param string $password
 * @return boolean true on success, else false.
 */
function login($username, $password) {
    $login = new Login('mailbox');
    if ($login->login($username, $password)) {
        session_regenerate_id();
        $_SESSION['authenticated'] = true;
        $_SESSION['sessid'] = array();
        $_SESSION['sessid']['username'] = $username;
        return true;
    }
    return false;
}

if (!isset($_SESSION['authenticated'])) {
    $server->addFunction('login', 'login');
} else {
    $server->setClass('UserProxy', 'user');
    $server->setClass('VacationProxy', 'vacation');
    $server->setClass('AliasProxy', 'alias');
}
echo $server->handle();


class UserProxy {
    /**
     * @param string $old_password
     * @param string $new_password
     * @return boolean true on success
     */
    public function changePassword($old_password, $new_password) {
        $uh = new MailboxHandler();
        $username = $_SESSION['sessid']['username'] ?? '';

        if (empty($username)) {
            throw new \Exception("not logged in? invalid session");
        }

        if (!$uh->init($username)) {
            return false; // user doesn't exist.
        }

        $login = new Login('mailbox');

        try {
            return $login->changePassword($username, $new_password, $old_password);
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * @param string $username
     * @param string $password
     * @return boolean true if successful.
     */
    public function login($username, $password) {
        $login = new Login('mailbox');
        return $login->login($username, $password);
    }
}

class VacationProxy {
    /**
     * @return boolean true if the vacation is removed successfully. Else false.
     */
    public function remove() {
        $vh = new VacationHandler($_SESSION['sessid']['username']);
        return $vh->remove();
    }

    /**
     * @return boolean true if vacation stuff is enabled in this instance of postfixadmin
     * and the user has the ability to make changes to it.
     */
    public function isVacationSupported() {
        $vh = new VacationHandler($_SESSION['sessid']['username']);
        return $vh->vacation_supported();
    }

    /**
     * @return boolean true if the user has an active vacation record etc.
     */
    public function checkVacation() {
        $vh = new VacationHandler($_SESSION['sessid']['username']);
        return $vh->check_vacation();
    }

    /**
     * @return array|bool - either array of vacation details or boolean false if the user has none.
     */
    public function getDetails() {
        $vh = new VacationHandler($_SESSION['sessid']['username']);
        return $vh->get_details();
    }

    /**
     * @param string $subject
     * @param string $body
     * @param int $interval_time
     * @param string $activeFrom
     * @param string $activeUntil
     * @return boolean true on success.
     * Whatiis @replyType?? for
     */
    public function setAway($subject, $body, $interval_time = 0, $activeFrom = '2000-01-01', $activeUntil = '2099-12-31') {
        $vh = new VacationHandler($_SESSION['sessid']['username']);
        return $vh->set_away($subject, $body, $interval_time, $activeFrom, $activeUntil);
    }
}

class AliasProxy {
    /**
     * @return array - array of aliases this user has. Array may be empty.
     */
    public function get() {
        $ah = new AliasHandler();
        $ah->init($_SESSION['sessid']['username']);
        /* I see no point in returning special addresses to the user. */
        $ah->view();
        $result = $ah->result;
        return $result['goto'];
    }

    /**
     * @param array of email addresses (Strings)
     * @param string flag to set ('forward_and_store' or 'remote_only')
     * @return boolean true
     */
    public function update($addresses, $flags) {
        $ah = new AliasHandler();
        $ah->init($_SESSION['sessid']['username']);

        $values = ['goto' => $addresses];

        if ($flags == 'forward_and_store') {
            $values['goto_mailbox'] = 1;
        } elseif ($flags == 'remote_only') {
            $values['goto_mailbox'] = 0;
        } else {
            return false; # invalid parameter
        }

        if (!$ah->set($values)) {
            //error_log('ah->set failed' . print_r($values, true));
            return false;
        }
        $store = $ah->save();
        return $store;
    }

    /**
     * @return boolean true if the user has 'store_and_forward' set.
     * (i.e. their email address is also in the alias table). IF it returns false, then it's 'remote_only'
     */
    public function hasStoreAndForward() {
        $ah = new AliasHandler();
        $ah->init($_SESSION['sessid']['username']);
        $ah->view();
        $result = $ah->result;
        return $result['goto_mailbox'] == 1;
    }
}
/* vim: set expandtab softtabstop=4 tabstop=4 shiftwidth=4: */
