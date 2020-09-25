<?php


class Login {
    private $table;
    private $id_field;

    public function __construct(string $tableName, string $idField) {
        $this->table = table_by_key($tableName);
        $this->id_field = $idField;
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
        $query = "SELECT password FROM {$this->table} WHERE {$this->id_field} = :username AND active = :active";

        $values = array('username' => $username, 'active' => $active);

        $result = db_query_all($query, $values);
        if (sizeof($result) == 1) {
            $row = $result[0];

            $crypt_password = pacrypt($password, $row['password']);

            return hash_equals($row['password'], $crypt_password);
        }

        // try and be near constant time regardless of whether the db user exists or not
        $x = pacrypt('abc', 'def');

        return hash_equals('not', 'comparable');
    }

    /**
     * Updates db with password recovery code, and returns it.
     * @param string $username
     * @return false|string
     * @throws Exception
     */
    public function generatePasswordRecoveryCode(string $username) {
        $sql = "SELECT count(1) FROM {$this->table} WHERE {$this->id_field} = :username AND active = :active";

        $active = db_get_boolean(true);

        $values = [
            'username' => $username,
            'active' => $active,
        ];

        $result = db_query_one($sql, $values);

        if ($result) {
            $token = generate_password();
            $updatedRows = db_update($this->table, $this->id_field, $username, array(
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

        $login = new Login($this->table, $this->id_field);

        if (!$login->login($username, $old_password)) {
            throw new \Exception(Config::Lang('pPassword_password_current_text_error'));
        }

        $set = array(
            'password' => pacrypt($new_password),
        );

        $result = db_update('mailbox', 'username', $username, $set);

        if ($result != 1) {
            db_log($domain, 'edit_password', "FAILURE: " . $username);
            throw new \Exception(Config::lang('pEdit_mailbox_result_error'));
        }

        db_log($domain, 'edit_password', $username);
        return true;
    }
}
