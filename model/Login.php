<?php

class Login {
    private $key_table;
    private $table;

    public function __construct(string $tableName) {
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
    public function login($username, $password): bool {
        $active = db_get_boolean(true);
        $query = "SELECT password FROM {$this->key_table} WHERE username = :username AND active = :active";

        $values = array('username' => $username, 'active' => $active);

        $result = db_query_all($query, $values);

        if (sizeof($result) == 1 && strlen($password) > 0) {
            $row = $result[0];

            try {
                $crypt_password = pacrypt($password, $row['password']);
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
    public function generatePasswordRecoveryCode(string $username) {
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
     * @return mixed|null
     * @throws Exception
     */
    protected function getUserDomain($username) {
        $sql = "SELECT domain FROM {$this->table} WHERE username = :username AND active = :active";

        $active = db_get_boolean(true);

        $values = [
            'username' => $username,
            'active' => $active,
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
    public function changePassword($username, $new_password, $old_password): bool {
        list(/*NULL*/, $domain) = explode('@', $username);

        if (!$this->login($username, $old_password)) {
            throw new \Exception(Config::Lang('pPassword_password_current_text_error'));
        }

        $set = array(
            'password' => pacrypt($new_password),
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
        return true;
    }
}
