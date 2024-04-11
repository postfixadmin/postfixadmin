<?php

class Login
{
    private $key_table;
    private $table;

    public function __construct(string $tableName)
    {
        $ok = ['mailbox', 'admin'];

        if (!in_array($tableName, $ok)) {
            throw new \InvalidArgumentException("Unsupported tableName for login: " . $tableName);
        }
        $this->table = $tableName;
        $this->key_table = table_by_key($tableName);
    }

    /**
     * Attempt to log a user in.
     * @param string $tablename
     * @param string $username
     * @param string $password
     * @return boolean true on successful login (i.e. password matches etc)
     */
    public function login($username, $password): bool
    {
        $active = db_get_boolean(true);
        $query = "SELECT password FROM {$this->key_table} WHERE username = :username AND active = :active";

        $values = array('username' => $username, 'active' => $active);

        $result = db_query_all($query, $values);

        if (sizeof($result) == 1 && strlen($password) > 0) {
            $row = $result[0];

            try {
                $crypt_password = pacrypt($password, $row['password'], $username);
            } catch (\Exception $e) {
                error_log("Error while trying to call pacrypt()");
                error_log("" . $e);
                hash_equals("not", "comparable");
                return false; // just refuse to login?
            }
            return hash_equals($row['password'], $crypt_password);
        }

        // try and be near constant time regardless of whether the db user exists or not
        try {
            // this causes errors with e.g. dovecot as there is no prefix.
            $x = pacrypt('abc', 'def');
        } catch (\Exception $e) {
            error_log("Error trying to call pacrypt()");
            error_log("" . $e);
        }

        return hash_equals('not', 'comparable');
    }

    /**
     * Updates db with password recovery code, and returns it.
     * @param string $username
     * @return false|string
     * @throws Exception
     */
    public function generatePasswordRecoveryCode(string $username)
    {
        $sql = "SELECT count(1) FROM {$this->key_table} WHERE username = :username AND active = :active";

        $active = db_get_boolean(true);

        $values = [
            'username' => $username,
            'active' => $active,
        ];

        $result = db_query_one($sql, $values);

        if ($result) {
            $token = generate_password();
            $updatedRows = db_update($this->table, 'username', $username, array(
                'token' => pacrypt($token),
                'token_validity' => date("Y-m-d H:i:s", strtotime('+ 1 hour')),
            ));

            if ($updatedRows == 1) {
                return $token;
            }
        }
        return false;
    }

    /**
     * returns user's domain name
     * @param $username
     * @return string|null
     * @throws Exception
     */
    protected function getUserDomain(string $username)
    {
        $sql = "SELECT domain FROM {$this->table} WHERE username = :username AND active = :active";

        $values = [
            'username' => $username,
            'active' => db_get_boolean(true),
        ];

        // Fetch the domain
        $result = db_query_one($sql, $values);

        if (is_array($result) && isset($result['domain'])) {
            return $result['domain'];
        } else {
            return null;
        }
    }

    /**
     * @param string $username
     * @param string $new_password
     * @param string $old_password
     *
     * All passwords need to be plain text; they'll be hashed appropriately
     * as per the configuration in config.inc.php
     *
     * @return boolean true on success; false on failure
     * @throws \Exception if invalid user, or db update fails.
     */
    public function changePassword($username, $new_password, $old_password): bool
    {
        list(/*NULL*/, $domain) = explode('@', $username);

        if (!$this->login($username, $old_password)) {
            throw new \Exception(Config::Lang('pPassword_password_current_text_error'));
        }

        $set = array(
            'password' => pacrypt($new_password, '', $username),
        );

        if (Config::bool('password_expiration')) {
            $domain = $this->getUserDomain($username);
            if (!is_null($domain)) {
                $password_expiration_value = (int)get_password_expiration_value($domain);
                $set['password_expiry'] = date('Y-m-d H:i', strtotime("+$password_expiration_value day"));
            }
        }

        $result = db_update($this->table, 'username', $username, $set);

        if ($result != 1) {
            db_log($domain, 'edit_password', "FAILURE: " . $username);
            throw new \Exception(Config::lang('pEdit_mailbox_result_error'));
        }

        db_log($domain, 'edit_password', $username);

        $cmd_pw = Config::read('mailbox_postpassword_script');

        if (empty($cmd_pw)) {
            return true;
        }

        $warnmsg_pw = Config::Lang('mailbox_postpassword_failed');

        // If we have a mailbox_postpassword_script (dovecot only?)

        // Use proc_open call to avoid safe_mode problems and to prevent showing plain password in process table
        $spec = array(
            0 => array("pipe", "r"), // stdin
            1 => array("pipe", "w"), // stdout
        );

        $cmdarg1 = escapeshellarg($username);
        $cmdarg2 = escapeshellarg($domain);
        $command = "$cmd_pw $cmdarg1 $cmdarg2 2>&1";

        $proc = proc_open($command, $spec, $pipes);

        if (!$proc) {
            throw new \Exception("can't proc_open $cmd_pw");
        }

        // Write passwords through pipe to command stdin -- provide old password, then new password.
        fwrite($pipes[0], $old_password . "\0", 1 + strlen($old_password));
        fwrite($pipes[0], $new_password . "\0", 1 + strlen($new_password));
        $output = stream_get_contents($pipes[1]);
        fclose($pipes[0]);
        fclose($pipes[1]);

        $retval = proc_close($proc);

        if (0 != $retval) {
            error_log("Running $command yielded return value=$retval, output was: " . json_encode($output));
            throw new \Exception($warnmsg_pw);
        }

        return true;
    }

    /**
     * @param string $username - current user
     * @param string $password - password for $username
     * @param string $app_desc - desc. for the app we're adding
     * @param string $app_pass - password for the app we're adding
     *
     * All passwords need to be plain text; they'll be hashed appropriately
     * as per the configuration in config.inc.php
     *
     * @return boolean true on success; false on failure
     * @throws \Exception if invalid user, or db update fails.
     */
    public function addAppPassword(string $username, string $password, string $app_desc, string $app_pass): bool
    {
        list(/*NULL*/, $domain) = explode('@', $username);

        if (!$app_pass) {
            throw new \Exception(Config::Lang('pAppPassAdd_pass_empty_error'));
        }
        if (!$app_desc) {
            throw new \Exception(Config::Lang('pException_desc_empty_error'));
        }

        if (!$this->login($username, $password)) {
            throw new \Exception(Config::Lang('pPassword_password_current_text_error'));
        }

        $app_pass = pacrypt($app_pass, '', $username);


        $result = db_insert('mailbox_app_password', ['username' => $username, 'description' => $app_desc, 'password_hash' => $app_pass], []);

        if ($result != 1) {
            db_log($domain, 'add_app_password', "FAILURE: " . $username);
            throw new \Exception(Config::lang('pAdd_app_password_result_error'));
        }

        db_log($domain, 'add_app_password', $username);

        $cmd_pw = Config::read('mailbox_postapppassword_script');

        if (empty($cmd_pw)) {
            return true;
        }

        $warnmsg_pw = Config::Lang('mailbox_postapppassword_failed');

        // If we have a mailbox_postpppassword_script

        // Use proc_open call to avoid safe_mode problems and to prevent showing plain password in process table
        $spec = array(
            0 => array("pipe", "r"), // stdin
            1 => array("pipe", "w"), // stdout
        );

        $cmdarg1 = escapeshellarg($username);
        $cmdarg2 = escapeshellarg($app_desc);

        $command = "$cmd_pw $cmdarg1 $cmdarg2 2>&1";

        $proc = proc_open($command, $spec, $pipes);

        if (!$proc) {
            throw new \Exception("can't proc_open $cmd_pw");
        }

        // Write passwords through pipe to command stdin -- provide old password, then new password.
        fwrite($pipes[0], $app_pass . "\0", 1 + strlen($app_pass));
        $output = stream_get_contents($pipes[0]);
        fclose($pipes[0]);

        $retval = proc_close($proc);

        if (0 != $retval) {
            error_log("Running $command yielded return value=$retval, output was: " . json_encode($output));
            throw new \Exception($warnmsg_pw);
        }

        return true;
    }
}
