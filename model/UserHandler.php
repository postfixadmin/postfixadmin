<?php

/**
 * Simple class to represent a user.
 */
class UserHandler {

    protected $username = null;

    public function __construct($username) {
        $this->username = $username;
    }

    /**
     * @return boolean true on success; false on failure 
     * @param string $username
     * @param string $old_password
     * @param string $new_passwords
     *
     * All passwords need to be plain text; they'll be hashed appropriately
     * as per the configuration in config.inc.php
     */
    public function change_pass($old_password, $new_password) {
        global $config;
        $username = $this->username;
        $tmp = preg_split ('/@/', $username);
        $USERID_DOMAIN = $tmp[1];

        $username = escape_string($username);
        $table_mailbox = table_by_key('mailbox');

        $active = db_get_boolean(True);
        $result = db_query("SELECT * FROM $table_mailbox WHERE username='$username' AND active='$active'");
        $new_db_password = escape_string(pacrypt($new_password));

        $result = db_query ("UPDATE $table_mailbox SET password='$new_db_password',modified=NOW() WHERE username='$username'");

        db_log ($username, $USERID_DOMAIN, 'edit_password', "$username");
        return true;
    }

    /**
     * Attempt to log a user in.
     * @param string $username
     * @param string $password
     * @return boolean true on successful login (i.e. password matches etc)
     */
    public static function login($username, $password) {
        global $config;
        $username = escape_string($username);

        $table_mailbox = table_by_key('mailbox');
        $active = db_get_boolean(True);
        $query = "SELECT password FROM $table_mailbox WHERE username='$username' AND active='$active'";

        $result = db_query ($query);
        if ($result['rows'] == 1)
        {
            $row = db_array ($result['result']);
            $crypt_password = pacrypt ($password, $row['password']);

            if($row['password'] == $crypt_password) {
                return true;
            }
        }
        return false;
    }
}

/* vim: set expandtab softtabstop=4 tabstop=4 shiftwidth=4: */
