<?php

class TotpexceptionHandler extends PFAHandler
{
    protected string $db_table = 'totp_exception_address';
    protected string $id_field = 'id';
    protected string $label_field = 'ip';
    protected ?string $domain_field = '';
    protected string $order_by = 'username, ip';
    protected ?string $user_field = 'username';

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

    public function webformConfig()
    {
        return array(
            'formtitle_create' => 'pTotp_exceptions_welcome',
            'formtitle_edit' => 'pTotp_exceptions_welcome',
            'create_button' => 'pTotp_exceptions_add',

            'required_role' => 'user',
            'listview' => 'list.php?table=totpexception',
            'early_init' => 0,
        );
    }

    protected function validate_new_id()
    {
        # auto_increment - any non-empty ID is an error
        if ($this->id != '') {
            $this->errormsg[$this->id_field] = 'auto_increment value, you must pass an empty string!';
            return false;
        }
        return true;
    }

    /**
     * Restrict visibility based on user role:
     * - Global admins see all exceptions
     * - Admins see exceptions for domains they manage
     * - Users see exceptions for their username, domain, and global (NULL) exceptions
     */
    protected function read_from_db($condition, $searchmode = array(), $limit = -1, $offset = -1): array
    {
        if (authentication_has_role('global-admin')) {
            return parent::read_from_db($condition, $searchmode, $limit, $offset);
        }

        $table = table_by_key($this->db_table);
        $username = $this->admin_username;

        if (authentication_has_role('admin')) {
            $domains = list_domains_for_admin($username);
            $params = ['username' => $username];
            $domain_conditions = [];
            foreach ($domains as $i => $domain) {
                $key = '_domain_' . $i;
                $domain_conditions[] = "username = :$key";
                $params[$key] = $domain;
            }
            $domain_sql = implode(' OR ', $domain_conditions);
            $query = "SELECT * FROM $table WHERE username = :username OR $domain_sql ORDER BY $this->order_by";
        } else {
            list($_, $domain) = explode('@', $username);
            $params = ['username' => $username, 'domain' => $domain];
            $query = "SELECT * FROM $table WHERE username = :username OR username = :domain OR username IS NULL ORDER BY $this->order_by";
        }

        if ($limit > -1 && $offset > -1) {
            $query .= " LIMIT $limit OFFSET $offset";
        }

        $db_result = array();
        $result = db_query_all($query, $params);
        foreach ($result as $row) {
            $db_result[$row[$this->id_field]] = $row;
        }
        return $db_result;
    }

    protected function preSave(): bool
    {
        // Validate IP address
        if (isset($this->values['ip']) && !filter_var($this->values['ip'], FILTER_VALIDATE_IP)) {
            $this->errormsg['ip'] = Config::Lang('pException_ip_empty_error');
            return false;
        }

        // Regular users can only add exceptions for their own username
        if (!authentication_has_role('admin') && !authentication_has_role('global-admin')) {
            $this->values['username'] = $this->admin_username;
        }

        // Admins can only add exceptions for domains they manage
        if (authentication_has_role('admin') && !authentication_has_role('global-admin')) {
            $exception_username = $this->values['username'] ?? '';
            if ($exception_username !== $this->admin_username) {
                if (strpos($exception_username, '@')) {
                    list($_, $exception_domain) = explode('@', $exception_username);
                } else {
                    $exception_domain = $exception_username;
                }
                $domains = list_domains_for_admin($this->admin_username);
                if (!in_array($exception_domain, $domains)) {
                    $this->errormsg['username'] = Config::Lang('pException_user_entire_domain_error');
                    return false;
                }
            }
        }

        return true;
    }

    protected function postSave(): bool
    {
        if ($this->new) {
            $cmd = Config::read('mailbox_post_totp_exception_add_script');
            $warnmsg = Config::Lang('mailbox_post_totp_exception_add_failed');
        } else {
            return true;
        }

        if (empty($cmd)) {
            return true;
        }

        $cmdarg1 = escapeshellarg($this->admin_username);
        $cmdarg2 = escapeshellarg($this->values['ip'] ?? '');
        $command = "$cmd $cmdarg1 $cmdarg2 2>&1";

        $spec = [0 => ["pipe", "r"], 1 => ["pipe", "w"]];
        $proc = proc_open($command, $spec, $pipes);
        if (!$proc) {
            error_log("can't proc_open $cmd");
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

    public function delete()
    {
        if (!$this->view()) {
            $this->errormsg[] = Config::Lang($this->msg['error_does_not_exist']);
            return false;
        }

        // Check permissions
        $exception = $this->result;
        $username = $this->admin_username;

        if (!authentication_has_role('global-admin')) {
            if (authentication_has_role('admin')) {
                $domains = list_domains_for_admin($username);
                $exception_username = $exception['username'] ?? '';
                if ($exception_username !== $username) {
                    if (strpos($exception_username, '@')) {
                        list($_, $exception_domain) = explode('@', $exception_username);
                    } else {
                        $exception_domain = $exception_username;
                    }
                    if (!in_array($exception_domain, $domains)) {
                        $this->errormsg[] = Config::Lang('pException_user_entire_domain_error');
                        return false;
                    }
                }
            } else {
                if ($exception['username'] !== $username) {
                    $this->errormsg[] = Config::lang('pEdit_totp_exception_result_error');
                    return false;
                }
            }
        }

        db_delete($this->db_table, $this->id_field, $this->id);

        // Run post-delete script
        $cmd = Config::read('mailbox_post_totp_exception_delete_script');
        if (!empty($cmd)) {
            $cmdarg1 = escapeshellarg($username);
            $cmdarg2 = escapeshellarg($exception['ip'] ?? '');
            $command = "$cmd $cmdarg1 $cmdarg2 2>&1";

            $spec = [0 => ["pipe", "r"], 1 => ["pipe", "w"]];
            $proc = proc_open($command, $spec, $pipes);
            if ($proc) {
                fclose($pipes[0]);
                $output = stream_get_contents($pipes[1]);
                fclose($pipes[1]);
                $retval = proc_close($proc);
                if (0 != $retval) {
                    error_log("Running $command yielded return value=$retval, output was: " . json_encode($output));
                    $this->errormsg[] = Config::Lang('mailbox_post_totp_exception_delete_failed');
                }
            }
        }

        db_log($this->admin_username, 'delete_totp_exception', $exception['ip'] ?? '');
        $this->infomsg[] = Config::Lang_f('pDelete_delete_success', $exception['ip'] ?? $this->id);
        return true;
    }

    public function domain_from_id()
    {
        return '';
    }
}
