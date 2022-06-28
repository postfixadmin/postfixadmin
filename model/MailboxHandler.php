<?php

# $Id$

/**
 * Simple class to represent a user.
 */
class MailboxHandler extends PFAHandler {
    protected $db_table = 'mailbox';
    protected $id_field = 'username';
    protected $domain_field = 'domain';
    protected $searchfields = array('username');

    # init $this->struct, $this->db_table and $this->id_field
    protected function initStruct() {
        $passwordReset = (int) Config::bool('forgotten_user_password_reset');
        $reset_by_sms = 0;
        if ($passwordReset && Config::read_string('sms_send_function')) {
            $reset_by_sms = 1;
        }

        $this->struct = array(

            # field name                allow       display in...   type    $PALANG label                     $PALANG description                 default / options / ...
            #                           editing?    form    list
            'username'         => pacol($this->new, 1,      1,      'mail', 'pEdit_mailbox_username'        , ''                                , '' ),
            'local_part'       => pacol($this->new, 0,      0,      'text', 'pEdit_mailbox_username'        , ''                                , '' ),
            'domain'           => pacol($this->new, 0,      1,      'enum', ''                              , ''                                , '',
                /*options*/ $this->allowed_domains      ),
            # TODO: maildir: display in list is needed to include maildir in SQL result (for post_edit hook)
            # TODO:          (not a perfect solution, but works for now - maybe we need a separate "include in SELECT query" field?)
            'maildir'          => pacol($this->new, 0,      1,      'text', ''                              , ''                                , '' ),
            'password'         => pacol(1,          1,      0,      'pass', 'password'                      , 'pCreate_mailbox_password_text'   , '' ),
            'password2'        => pacol(1,          1,      0,      'pass', 'password_again'                , ''                                 , '',
                /*options*/ array(),
                /*not_in_db*/ 0,
                /*dont_write_to_db*/ 1,
                /*select*/ 'password as password2'
            ),
            'name'             => pacol(1,          1,      1,      'text', 'name'                          , 'pCreate_mailbox_name_text'       , '' ),
            'quota'            => pacol(1,          1,      1,      'int' , 'pEdit_mailbox_quota'           , 'pEdit_mailbox_quota_text'        , '' ), # in MB
            # read_from_db_postprocess() also sets 'quotabytes' for use in init()
            # TODO: read used quota from quota/quota2 table
            'active'           => pacol(1,          1,      1,      'bool', 'active'                        , ''                                 , 1 ),
            'welcome_mail'     => pacol($this->new, $this->new, 0,  'bool', 'pCreate_mailbox_mail'          , ''                                 , 1,
                /*options*/ array(),
                /*not_in_db*/ 1             ),
            'phone'            => pacol(1,  $reset_by_sms,  0,      'text', 'pCreate_mailbox_phone'         , 'pCreate_mailbox_phone_desc'       , ''),
            'email_other'      => pacol(1,  $passwordReset, 0,      'mail', 'pCreate_mailbox_email'         , 'pCreate_mailbox_email_desc'       , ''),
            'token'            => pacol(1,          0,      0,      'text', ''                              , ''                                 ),
            'token_validity'   => pacol(1,          0,      0,      'ts',   ''                              , '', date("Y-m-d H:i:s",time())),
            'created'          => pacol(0,          0,      1,      'ts',   'created'                       , ''                                 ),
            'modified'         => pacol(0,          0,      1,      'ts',   'last_modified'                 , ''                                 ),
            'password_expiry'  => pacol(0,          0,      1,      'ts',   'password_expiration'           , ''                                 ),
            # TODO: add virtual 'notified' column and allow to display who received a vacation response?
        );

        # update allowed quota
        if (count($this->struct['domain']['options']) > 0) {
            $this->prefill('domain', $this->struct['domain']['options'][0]);
        }
    }

    public function init(string $id): bool {
        if (!parent::init($id)) {
            return false;
        }

        if ($this->new) {
            $currentquota = 0;
        } else {
            $currentquota = $this->result['quotabytes']; # parent::init called ->view()
        }

        $this->updateMaxquota($this->domain, $currentquota);

        return true; # still here? good.
    }

    protected function domain_from_id() {
        list(/*NULL*/, $domain) = explode('@', $this->id);
        return $domain;
    }

    /**
     * show max allowed quota in quota field description
     * @param string - domain
     * @param int - current quota
     */
    protected function updateMaxquota($domain, $currentquota) {
        if ($domain == '') {
            return false;
        }

        $maxquota = $this->allowed_quota($domain, $currentquota);

        if ($maxquota == 0) {
            # TODO: show 'unlimited'
        # } elseif ($maxquota < 0) {
            # TODO: show 'disabled' - at the moment, just shows '-1'
        } else {
            $this->struct['quota']['desc'] = Config::lang_f('mb_max', "" . $maxquota);
        }
    }

    protected function initMsg() {
        $this->msg['error_already_exists'] = 'email_address_already_exists';
        $this->msg['error_does_not_exist'] = 'pCreate_mailbox_username_text_error1';
        $this->msg['confirm_delete'] = 'confirm_delete_mailbox';

        if ($this->new) {
            $this->msg['logname'] = 'create_mailbox';
            $this->msg['store_error'] = 'pCreate_mailbox_result_error';
            $this->msg['successmessage'] = 'pCreate_mailbox_result_success';
        } else {
            $this->msg['logname'] = 'edit_mailbox';
            $this->msg['store_error'] = 'mailbox_update_failed';
            $this->msg['successmessage'] = 'mailbox_updated';
        }
    }

    public function webformConfig() {
        if ($this->new) { # the webform will display a local_part field + domain dropdown on $new
            $this->struct['username']['display_in_form'] = 0;
            $this->struct['local_part']['display_in_form'] = 1;
            $this->struct['domain']['display_in_form'] = 1;
        }

        return array(
            # $PALANG labels
            'formtitle_create' => 'pCreate_mailbox_welcome',
            'formtitle_edit' => 'pEdit_mailbox_welcome',
            'create_button' => 'add_mailbox',

            # various settings
            'required_role' => 'admin',
            'listview'      => 'list-virtual.php',
            'early_init'    => 0,
            'prefill'       => array('domain'),
        );
    }


    protected function validate_new_id() {
        if ($this->id == '') {
            $this->errormsg[$this->id_field] = Config::lang('pCreate_mailbox_username_text_error1');
            return false;
        }

        $email_check = check_email($this->id);
        if ($email_check != '') {
            $this->errormsg[$this->id_field] = $email_check;
            return false;
        }

        list(/*NULL*/, $domain) = explode('@', $this->id);

        if (!$this->create_allowed($domain)) {
            $this->errormsg[] = Config::lang('pCreate_mailbox_username_text_error3');
            return false;
        }

        # check if an alias with this name already exists - if yes, don't allow to create the mailbox
        $handler = new AliasHandler(1);
        $handler->calledBy('MailboxHandler'); # make sure mailbox creation still works if the alias limit for the domain is hit

        if (!$handler->init($this->id)) {
            # TODO: keep original error message from AliasHandler
            $this->errormsg[] = Config::lang('email_address_already_exists');
            return false;
        }

        return true; # still here? good!
    }

    /**
     * check number of existing mailboxes for this domain - is one more allowed?
     */
    private function create_allowed($domain) {
        $limit = get_domain_properties($domain);

        if ($limit['mailboxes'] == 0) {
            return true;
        } # unlimited
        if ($limit['mailboxes'] < 0) {
            return false;
        } # disabled
        if ($limit['mailbox_count'] >= $limit['mailboxes']) {
            return false;
        }
        return true;
    }

    /**
     * merge local_part and domain to address
     * called by edit.php (if id_field is editable and hidden in editform) _before_ ->init
     */
    public function mergeId($values) {
        if ($this->struct['local_part']['display_in_form'] == 1 && $this->struct['domain']['display_in_form']) { # webform mode - combine to 'address' field
            return $values['local_part'] . '@' . $values['domain'];
        } else {
            return $values[$this->id_field];
        }
    }


    protected function read_from_db_postprocess($db_result) {
        foreach ($db_result as $key => $row) {
            if (isset($row['quota']) && is_numeric($row['quota']) && $row['quota'] > -1) { # quota could be disabled in $struct
                $db_result[$key]['quotabytes'] = $row['quota'];
                $db_result[$key]['quota'] = divide_quota( (int) $row['quota']); # convert quota to MB
            } else {
                $db_result[$key]['quotabytes'] = -1;
                $db_result[$key]['quota'] = -1;
            }
        }
        return $db_result;
    }


    protected function preSave(): bool {
        if (isset($this->values['quota']) && $this->values['quota'] != -1 && is_numeric($this->values['quota'])) {
            $multiplier = Config::read_string('quota_multiplier');
            if ($multiplier == 0 || !is_numeric($multiplier)) { // or empty string, or null, or false...
                $multiplier = 1;
            }
            $this->values['quota'] = $this->values['quota'] * $multiplier; # convert quota from MB to bytes
        }

        // Avoid trying to store '' in an integer field
        if ($this->values['quota'] === '') {
            $this->values['quota'] = 0;
        }

        $ah = new AliasHandler($this->new, $this->admin_username);

        $ah->calledBy('MailboxHandler');

        if (!$ah->init($this->id)) {
            $arraykeys = array_keys($ah->errormsg);
            $this->errormsg[] = $ah->errormsg[$arraykeys[0]]; # TODO: implement this as PFAHandler->firstErrormsg()
            return false;
        }

        $alias_data = array();

        if (isset($this->values['active'])) { # might not be set in edit mode
            $alias_data['active'] = $this->values['active'];
        }

        if ($this->new) {
            $alias_data['goto'] = array($this->id); # 'goto_mailbox' = 1; # would be technically correct, but setting 'goto' is easier
        }

        if (!$ah->set($alias_data)) {
            $this->errormsg[] = $ah->errormsg[0];
            return false;
        }

        if (!$ah->save()) {
            $this->errormsg[] = $ah->errormsg[0];
            return false;
        }

        if (!empty($this->values['password'])) {
            // provide some default value to keep MySQL etc happy.
            $this->values['password_expiry'] = date('Y-m-d H:i', strtotime("+365 days"));
            if (Config::bool('password_expiration')) {
                $domain_dirty = $this->domain_from_id();
                $domain = trim($domain_dirty, "`'"); // naive assumption it is ' escaping.
                $password_expiration_value = (int)get_password_expiration_value($domain);
                $this->values['password_expiry'] = date('Y-m-d H:i', strtotime("+$password_expiration_value day"));
            }
        }

        return true;
    }

    protected function setmore(array $values) {
        if (array_key_exists('quota', $this->values)) {
            $this->values['quota'] = (int)$this->values['quota'];
        }
    }

    // Could perhaps also use _validate_local_part($new_value) { .... }
    public function set(array $values) {
        // See: https://github.com/postfixadmin/postfixadmin/issues/282 - ensure the 'local_part' does not contain an @ sign.
        $ok = true;
        if (isset($values['local_part']) && strpos($values['local_part'], '@')) {
            $this->errormsg['local_part'] = Config::lang('pCreate_mailbox_local_part_error');
            $ok = false;
        }
        return $ok && parent::set($values);
    }

    protected function postSave(): bool {
        if ($this->new) {
            if (!$this->mailbox_post_script()) {
                # return false; # TODO: should this be fatal?
            }

            if ($this->values['welcome_mail'] == true) {
                if (!$this->send_welcome_mail()) {
                    # return false; # TODO: should this be fatal?
                }
            }

            if (!$this->create_mailbox_subfolders()) {
                $this->infomsg[] = Config::lang_f('pCreate_mailbox_result_succes_nosubfolders', $this->id);
            }
        } else { # edit mode
            # alias active status is updated in before_store()

            # postedit hook
            # TODO: implement a poststore() function? - would make handling of old and new values much easier...

            $old_mh = new MailboxHandler();

            if (!$old_mh->init($this->id)) {
                $this->errormsg[] = $old_mh->errormsg[0];
            } elseif (!$old_mh->view()) {
                $this->errormsg[] = $old_mh->errormsg[0];
            } else {
                $oldvalues = $old_mh->result();

                $this->values['maildir'] = $oldvalues['maildir'];

                if (isset($this->values['quota'])) {
                    $quota = $this->values['quota'];
                } else {
                    $quota = $oldvalues['quota'];
                }

                if (!$this->mailbox_post_script()) {
                    # TODO: should this be fatal?
                }
            }
        }
        return true; # even if a hook failed, mark the overall operation as OK
    }

    public function delete() {
        if (! $this->view()) {
            $this->errormsg[] = Config::Lang('pFetchmail_invalid_mailbox'); # TODO: can users hit this message at all? init() should already fail...
            return false;
        }

        # the correct way would be to delete the alias and fetchmail entries with *Handler before
        # deleting the mailbox, but it's easier and a bit faster to do it on the database level.
        # cleaning up all tables doesn't hurt, even if vacation or displaying the quota is disabled

        db_delete('fetchmail',              'mailbox',       $this->id);
        db_delete('vacation',               'email',         $this->id);
        db_delete('vacation_notification',  'on_vacation',   $this->id); # should be caught by cascade, if PgSQL
        db_delete('quota',                  'username',      $this->id);
        db_delete('quota2',                 'username',      $this->id);
        db_delete('alias',                  'address',       $this->id);
        db_delete($this->db_table,          $this->id_field, $this->id); # finally delete the mailbox

        if (!$this->mailbox_postdeletion()) {
            $this->errormsg[] = Config::Lang('mailbox_postdel_failed');
        }

        list(/*NULL*/, $domain) = explode('@', $this->id);
        db_log($domain, 'delete_mailbox', $this->id);
        $this->infomsg[] = Config::Lang_f('pDelete_delete_success', $this->id);
        return true;
    }



    protected function _prefill_domain($field, $val) {
        if (in_array($val, $this->struct[$field]['options'])) {
            $this->struct[$field]['default'] = $val;
            $this->updateMaxquota($val, 0);
        }
    }

    /**
     * check if quota is allowed
     */
    protected function _validate_quota($field, $val) {
        if (!$this->check_quota($val)) {
            $this->errormsg[$field] = Config::lang('pEdit_mailbox_quota_text_error');
            return false;
        }
        return true;
    }

    /**
     * - compare password / password2 field (error message will be displayed at password2 field)
     * - autogenerate password if enabled in config and $new
     * - display password on $new if enabled in config or autogenerated
     */
    protected function _validate_password($field, $val) {
        if (!$this->_validate_password2($field, $val)) {
            return false;
        }

        if ($this->new && Config::read('generate_password') == 'YES' && $val == '') {
            # auto-generate new password
            unset($this->errormsg[$field]); # remove "password too short" error message
            $val = generate_password();
            $this->values[$field] = $val; # we are doing this "behind the back" of set()
            $this->infomsg[] = Config::Lang('password') . ": $val";
            return false; # to avoid that set() overwrites $this->values[$field]
        } elseif ($this->new && Config::read('show_password') == 'YES') {
            $this->infomsg[] = Config::Lang('password') . ": $val";
        }

        return true; # still here? good.
    }

    /**
     * compare password / password2 field
     * error message will be displayed at the password2 field
     */
    protected function _validate_password2($field, $val) {
        return $this->compare_password_fields('password', 'password2');
    }

    /**
     * on $this->new, set localpart based on address
     */
    protected function _missing_local_part($field) {
        list($local_part, $domain) = explode('@', $this->id);
        $this->RAWvalues['local_part'] = $local_part;
    }

    /**
     * on $this->new, set domain based on address
     */
    protected function _missing_domain($field) {
        list($local_part, $domain) = explode('@', $this->id);
        $this->RAWvalues['domain'] = $domain;
    }

    # TODO: read used quota from quota/quota2 table, then enable _formatted_quota()
    # public function _formatted_quota    ($item) { return $item['used_quota']   . ' / ' . $item['quota']    ; }



    /**
    * calculate maildir path for the mailbox
    */
    protected function _missing_maildir($field) {
        list($local_part, $domain) = explode('@', $this->id);

        $maildir_name_hook = Config::read('maildir_name_hook');

        if (is_string($maildir_name_hook) && $maildir_name_hook != 'NO' && function_exists($maildir_name_hook)) {
            $maildir = $maildir_name_hook($domain, $this->id);
        } elseif (Config::bool('domain_path')) {
            if (Config::bool('domain_in_mailbox')) {
                $maildir = $domain . "/" . $this->id . "/";
            } else {
                $maildir = $domain . "/" . $local_part . "/";
            }
        } else {
            # If $CONF['domain_path'] is set to NO, $CONF['domain_in_mailbox] is forced to YES.
            # Otherwise user@example.com and user@foo.bar would be mixed up in the same maildir "user/".
            $maildir = $this->id . "/";
        }
        $this->RAWvalues['maildir'] = $maildir;
    }

    private function send_welcome_mail() {
        $fTo = $this->id;
        $fFrom = smtp_get_admin_email();
        if (empty($fFrom) || $fFrom == 'CLI') {
            $fFrom = $this->id;
        }
        $fSubject = Config::lang('pSendmail_subject_text');
        $fBody = Config::read('welcome_text');

        if (!smtp_mail($fTo, $fFrom, $fSubject, smtp_get_admin_password(), $fBody)) {
            $this->errormsg[] = Config::lang_f('pSendmail_result_error', $this->id);
            return false;
        }

        return true;
    }


    /**
     * Check if the user is creating a mailbox within the quota limits of the domain
     *
     * @param int $quota - quota wanted for the mailbox
     * @return boolean - true if requested quota is OK, otherwise false
     * @todo merge with allowed_quota?
     */
    protected function check_quota($quota) {
        if (!Config::bool('quota')) {
            return true; # enforcing quotas is disabled - just allow it
        }

        $quota = (int) $quota;

        list(/*NULL*/, $domain) = explode('@', $this->id);
        $limit = get_domain_properties($domain);

        if (($limit['maxquota'] < 0) and ($quota < 0)) {
            return true; # maxquota and $quota are both disabled -> OK, no need for more checks
        }

        if (($limit['maxquota'] > 0) and ($quota == 0)) {
            return false; # mailbox with unlimited quota on a domain with maxquota restriction -> not allowed, no more checks needed
        }

        if ($limit['maxquota'] != 0 && $quota > $limit['maxquota']) {
            return false; # mailbox bigger than maxquota restriction (and maxquota != unlimited) -> not allowed, no more checks needed
        }

        # TODO: detailed error message ("domain quota exceeded", "mailbox quota too big" etc.) via flash_error? Or "available quota: xxx MB"?
        if (!Config::bool('domain_quota')) {
            return true; # enforcing domain_quota is disabled - just allow it
        } elseif ($limit['quota'] <= 0) { # TODO: CHECK - 0 (unlimited) is fine, not sure about <= -1 (disabled)...
            $rval = true;
        } elseif ($quota == 0) { # trying to create an unlimited mailbox, but domain quota is set
            return false;
        } else {
            $table_mailbox = table_by_key('mailbox');
            $query = "SELECT SUM(quota) as sum FROM $table_mailbox WHERE domain = ? AND username != ?";

            $rows = db_query_all($query, array($domain, $this->id));

            $cur_quota_total = divide_quota($rows[0]['sum']); # convert to MB
            if (($quota + $cur_quota_total) > $limit['quota']) {
                $rval = false;
            } else {
                $rval = true;
            }
        }

        return $rval;
    }


    /**
     * Get allowed maximum quota for a mailbox
     *
     * @param string $domain
     * @param int $current_user_quota (in bytes)
     * @return int allowed maximum quota (in MB)
     */
    protected function allowed_quota($domain, $current_user_quota) {
        if (!Config::bool('quota')) {
            return 0; # quota disabled means no limits - no need for more checks
        }

        $domain_properties = get_domain_properties($domain);

        $tMaxquota = $domain_properties['maxquota'];

        if (Config::bool('domain_quota') && $domain_properties['quota']) {
            $dquota = $domain_properties['quota'] - $domain_properties['total_quota'] + divide_quota($current_user_quota);
            if ($dquota < $tMaxquota) {
                $tMaxquota = $dquota;
            }

            if ($tMaxquota == 0) {
                $tMaxquota = $dquota;
            }
        }
        return $tMaxquota;
    }


    /**
     * Called after a mailbox has been created or edited in the DBMS.
     *
     * @return boolean success/failure status
     */
    protected function mailbox_post_script() {
        if ($this->new) {
            $cmd = Config::read_string('mailbox_postcreation_script');
            $warnmsg = Config::Lang('mailbox_postcreate_failed');
        } else {
            $cmd = Config::read_string('mailbox_postedit_script');
            $warnmsg = Config::Lang('mailbox_postedit_failed');
        }

        if (empty($cmd)) {
            return true;
        } # nothing to do

        list(/*NULL*/, $domain) = explode('@', $this->id);
        $quota = $this->values['quota'];

        if (empty($this->id) || empty($domain) || empty($this->values['maildir'])) {
            trigger_error('In '.__FUNCTION__.': empty username, domain and/or maildir parameter', E_USER_ERROR);
            return false;
        }

        $cmdarg1=escapeshellarg($this->id);
        $cmdarg2=escapeshellarg($domain);
        $cmdarg3=escapeshellarg($this->values['maildir']);
        if ($quota <= 0) {
            $quota = 0;
        } # TODO: check if this is correct behaviour
        $cmdarg4 = escapeshellarg("" . $quota);
        $command= "$cmd $cmdarg1 $cmdarg2 $cmdarg3 $cmdarg4";
        $retval=0;
        $output=array();
        $firstline='';
        $firstline=exec($command, $output, $retval);
        if (0!=$retval) {
            error_log("Running $command yielded return value=$retval, first line of output=$firstline");
            $this->errormsg[] = $warnmsg;
            return false;
        }

        return true;
    }

    /**
     * Called after a mailbox has been deleted
     *
     * @return boolean true on success, false on failure
     * also adds a detailed error message to $this->errormsg[]
     */
    protected function mailbox_postdeletion() {
        $cmd = Config::read_string('mailbox_postdeletion_script');

        if (empty($cmd)) {
            return true;
        }

        list(/*NULL*/, $domain) = explode('@', $this->id);

        if (empty($this->id) || empty($domain)) {
            $this->errormsg[] = 'Empty username and/or domain parameter in mailbox_postdeletion';
            return false;
        }

        $cmdarg1=escapeshellarg($this->id);
        $cmdarg2=escapeshellarg($domain);
        $command = "$cmd $cmdarg1 $cmdarg2";
        $retval=0;
        $output=array();
        $firstline='';
        $firstline=exec($command, $output, $retval);
        if (0!=$retval) {
            error_log("Running $command yielded return value=$retval, first line of output=$firstline");
            $this->errormsg[] = 'Problems running mailbox postdeletion script!';
            return false;
        }

        return true;
    }



    /**
     * Called by postSave() after a mailbox has been created.
     * Immediately returns, unless configuration indicates
     * that one or more sub-folders should be created.
     *
     * Triggers E_USER_ERROR if configuration error is detected.
     *
     * If IMAP login fails, the problem is logged to the system log
     * (such as /var/log/httpd/error_log), and the function returns
     * FALSE.
     *
     * Doesn't clean up, if only some of the folders could be
     * created.
     *
     * @return boolean TRUE if everything succeeds, FALSE on all errors
     */
    protected function create_mailbox_subfolders() {
        $create_mailbox_subdirs = Config::read('create_mailbox_subdirs');
        if (empty($create_mailbox_subdirs)) {
            return true;
        }

        if (!function_exists('imap_open')) {
            trigger_error('imap_open function not present; cannot create_mailbox_subdirs');
            return false;
        }

        if (!is_array($create_mailbox_subdirs)) {
            trigger_error('create_mailbox_subdirs must be an array', E_USER_ERROR);
            return false;
        }

        $s_host = Config::read_string('create_mailbox_subdirs_host');
        if (empty($s_host)) {
            trigger_error('An IMAP/POP server host ($CONF["create_mailbox_subdirs_host"]) must be configured, if sub-folders are to be created', E_USER_ERROR);
            return false;
        }

        $s_options='';

        $create_mailbox_subdirs_hostoptions = Config::read('create_mailbox_subdirs_hostoptions');
        if (!empty($create_mailbox_subdirs_hostoptions)) {
            if (!is_array($create_mailbox_subdirs_hostoptions)) {
                trigger_error('The $CONF["create_mailbox_subdirs_hostoptions"] parameter must be an array', E_USER_ERROR);
                return false;
            }
            foreach ($create_mailbox_subdirs_hostoptions as $o) {
                $s_options.='/'.$o;
            }
        }

        $s_port='';
        if (Config::has('create_mailbox_subdirs_hostport')) {
            $create_mailbox_subdirs_hostport = Config::read('create_mailbox_subdirs_hostport');
            if (!empty($create_mailbox_subdirs_hostport)) {
                $s_port = $create_mailbox_subdirs_hostport;
                if (intval($s_port)!=$s_port) {
                    trigger_error('The $CONF["create_mailbox_subdirs_hostport"] parameter must be an integer', E_USER_ERROR);
                    return false;
                }
                $s_port=':'.$s_port;
            }
        }

        $s='{'.$s_host.$s_port.$s_options.'}';

        sleep(1); # give the mail triggering the mailbox creation a chance to do its job

        $i=@imap_open($s, $this->id, $this->values['password']);
        if (false==$i) {
            error_log('Could not log into IMAP/POP server: ' . $this->id . ': ' . imap_last_error());
            return false;
        }

        $s_prefix = Config::read_string('create_mailbox_subdirs_prefix');
        foreach ($create_mailbox_subdirs as $f) {
            $f='{'.$s_host.'}'.$s_prefix.$f;
            $res=imap_createmailbox($i, $f);
            if (!$res) {
                error_log('Could not create IMAP folder $f: ' . $this->id . ': ' . imap_last_error());
                @imap_close($i);
                return false;
            }
            @imap_subscribe($i, $f);
        }

        @imap_close($i);
        return true;
    }

    #TODO: more self explaining language strings!
}

/* vim: set expandtab softtabstop=4 tabstop=4 shiftwidth=4: */
