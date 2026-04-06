<?php

/**
 * Handler for TOTP exception addresses.
 *
 * Manages IP addresses that are exempt from TOTP requirements.
 * Exceptions can be scoped to a specific user, a domain, or global (NULL username).
 *
 * Visibility rules:
 * - Superadmins see all exceptions
 * - Admins see exceptions for users/domains they manage
 * - Users see exceptions for their username, domain, and global (NULL)
 */
class TotpexceptionHandler extends PFAHandler
{
    protected string $db_table = 'totp_exception_address';
    protected string $id_field = 'id';
    protected string $label_field = 'ip';
    protected ?string $domain_field = '';
    protected string $order_by = 'username, ip';
    protected ?string $user_field = 'username';

    /**
     * TOTP exceptions are not domain-scoped in the traditional sense.
     * Visibility is handled in read_from_db_postprocess().
     */
    protected function no_domain_field()
    {
        $this->allowed_domains = [];
    }

    /** @return void */
    protected function initStruct()
    {
        $this->struct = array(
            # field name              allow       display in...   type    $PALANG label                     $PALANG description  default / options / ...
            #                         editing?    form    list
            'id'          => self::pacol(0,       0,      1,      'num',  'pFetchmail_field_id',            '', '', array(), array('dont_write_to_db' => 1)),
            'username'    => self::pacol(1,       1,      1,      'text', 'pTotp_exceptions_user',          ''),
            'ip'          => self::pacol(1,       1,      1,      'text', 'pTotp_exceptions_address',       ''),
            'description' => self::pacol(1,       1,      1,      'text', 'pTotp_exceptions_description',   ''),
        );
    }

    protected function initMsg()
    {
        $this->msg['error_already_exists'] = 'pTotp_exception_result_error';
        $this->msg['error_does_not_exist'] = 'pTotp_exception_result_error';
        $this->msg['confirm_delete'] = 'confirm';

        if ($this->new) {
            $this->msg['logname'] = 'add_totp_exception';
            $this->msg['store_error'] = 'pTotp_exception_result_error';
            $this->msg['successmessage'] = 'pTotp_exception_result_success';
        } else {
            $this->msg['logname'] = 'edit_totp_exception';
            $this->msg['store_error'] = 'pEdit_totp_exception_result_error';
            $this->msg['successmessage'] = 'pTotp_exception_result_success';
        }
    }

    /** @return array<string, mixed> */
    public function webformConfig()
    {
        return array(
            'formtitle_create' => 'pTotp_exceptions_welcome',
            'formtitle_edit' => 'pTotp_exceptions_welcome',
            'create_button' => 'pTotp_exceptions_add',

            'required_role' => 'admin',
            'listview' => 'list.php?table=totpexception',
            'early_init' => 0,
            'user_hardcoded_field' => 'username',
        );
    }

    /** @return bool */
    protected function validate_new_id()
    {
        if ($this->id != '') {
            $this->errormsg[$this->id_field] = 'auto_increment value, you must pass an empty string!';
            return false;
        }
        return true;
    }

    /**
     * Validate IP address field.
     *
     * @param string $field field name
     * @param string $value submitted value
     * @return bool true if valid
     */
    protected function _validate_ip(string $field, string $value): bool
    {
        if (trim($value) === '') {
            $this->errormsg[$field] = Config::Lang('pException_ip_empty_error');
            return false;
        }
        if (!filter_var($value, FILTER_VALIDATE_IP)) {
            $this->errormsg[$field] = Config::Lang('pException_ip_error');
            return false;
        }
        return true;
    }

    /**
     * Validate username field and enforce permissions based on role.
     * Empty username is normalised to NULL (global exception, superadmin only).
     *
     * @param string $field field name
     * @param string $value submitted value
     * @return bool true if valid
     */
    protected function _validate_username(string $field, string $value): bool
    {
        // Normalize empty string to NULL for global exceptions
        if ($value === '') {
            if ($this->is_superadmin) {
                $this->values['username'] = null;
                return true;
            }
            $this->errormsg[$field] = Config::Lang('pException_user_global_error');
            return false;
        }

        // Users can only set exceptions for themselves
        if (!$this->is_admin) {
            $this->values['username'] = $this->username;
            return true;
        }

        // Superadmins can set for anyone
        if ($this->is_superadmin) {
            return true;
        }

        // Admins can set for users/domains they manage
        if (strpos($value, '@')) {
            list($_, $exception_domain) = explode('@', $value);
        } else {
            $exception_domain = $value;
        }

        if (!in_array($exception_domain, $this->allowed_domains)) {
            $this->errormsg[$field] = Config::Lang('pException_user_entire_domain_error');
            return false;
        }

        return true;
    }

    /**
     * Filter results based on user role after reading from DB.
     * - Superadmins see all
     * - Admins see exceptions for their managed domains + own username
     * - Users see exceptions for their username, domain, and global (NULL)
     *
     * @param array $db_result rows keyed by ID
     * @return array filtered rows
     */
    protected function read_from_db_postprocess($db_result)
    {
        if ($this->is_superadmin) {
            return $db_result;
        }

        $filtered = [];

        if ($this->is_admin) {
            foreach ($db_result as $key => $row) {
                $ex_username = $row['username'] ?? '';
                if ($ex_username === $this->admin_username) {
                    $filtered[$key] = $row;
                    continue;
                }
                if (strpos($ex_username, '@')) {
                    list($_, $ex_domain) = explode('@', $ex_username);
                } else {
                    $ex_domain = $ex_username;
                }
                if (in_array($ex_domain, $this->allowed_domains)) {
                    $filtered[$key] = $row;
                }
            }
        } else {
            // User mode
            list($_, $domain) = explode('@', $this->username);
            foreach ($db_result as $key => $row) {
                $ex_username = $row['username'] ?? null;
                if ($ex_username === $this->username || $ex_username === $domain || $ex_username === null) {
                    $filtered[$key] = $row;
                }
            }
        }

        return $filtered;
    }

    /**
     * Run post-creation script if configured.
     *
     * @return bool true on success
     */
    protected function postSave(): bool
    {
        if (!$this->new) {
            return true;
        }

        $cmd = Config::read('mailbox_post_totp_exception_add_script');
        if (empty($cmd)) {
            return true;
        }

        $cmdarg1 = escapeshellarg($this->values['username'] ?? '');
        $cmdarg2 = escapeshellarg($this->values['ip'] ?? '');
        $command = "$cmd $cmdarg1 $cmdarg2 2>&1";

        return $this->run_post_script($command, Config::Lang('mailbox_post_totp_exception_add_failed'));
    }

    /**
     * Delete a TOTP exception with role-based permission checks.
     *
     * @return bool true on success
     */
    public function delete()
    {
        if (!$this->view()) {
            $this->errormsg[] = Config::Lang($this->msg['error_does_not_exist']);
            return false;
        }

        $exception = $this->result;
        $ex_username = $exception['username'] ?? '';

        // Check permissions
        if ($this->is_superadmin) {
            // can delete anything
        } elseif ($this->is_admin) {
            if ($ex_username !== $this->admin_username) {
                if (strpos($ex_username, '@')) {
                    list($_, $ex_domain) = explode('@', $ex_username);
                } else {
                    $ex_domain = $ex_username;
                }
                if (!in_array($ex_domain, $this->allowed_domains)) {
                    $this->errormsg[] = Config::Lang('pException_user_entire_domain_error');
                    return false;
                }
            }
        } else {
            if ($ex_username !== $this->username) {
                $this->errormsg[] = Config::lang('pEdit_totp_exception_result_error');
                return false;
            }
        }

        db_delete($this->db_table, $this->id_field, $this->id);

        // Run post-delete script
        $cmd = Config::read('mailbox_post_totp_exception_delete_script');
        if (!empty($cmd)) {
            $cmdarg1 = escapeshellarg($ex_username);
            $cmdarg2 = escapeshellarg($exception['ip'] ?? '');
            $command = "$cmd $cmdarg1 $cmdarg2 2>&1";
            $this->run_post_script($command, Config::Lang('mailbox_post_totp_exception_delete_failed'));
        }

        db_log($this->admin_username ?: $this->username, 'delete_totp_exception', $exception['ip'] ?? '');
        $this->infomsg[] = Config::Lang_f('pDelete_delete_success', $exception['ip'] ?? $this->id);
        return true;
    }

    /**
     * Run a post-save/delete script via proc_open.
     *
     * @param string $command full shell command to execute
     * @param string $warnmsg error message to display on failure
     * @return bool true on success
     */
    private function run_post_script(string $command, string $warnmsg): bool
    {
        $spec = [0 => ["pipe", "r"], 1 => ["pipe", "w"]];
        $proc = proc_open($command, $spec, $pipes);
        if (!$proc) {
            error_log("can't proc_open: $command");
            $this->errormsg[] = $warnmsg;
            return false;
        }

        fclose($pipes[0]);
        $output = stream_get_contents($pipes[1]);
        fclose($pipes[1]);
        $retval = proc_close($proc);

        if (0 != $retval) {
            error_log("Running $command yielded return value=$retval, output was: " . json_encode($output));
            $this->errormsg[] = $warnmsg;
            return false;
        }

        return true;
    }

    /**
     * @return string empty string, TOTP exceptions are not domain-scoped
     */
    public function domain_from_id()
    {
        return '';
    }
}
