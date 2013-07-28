<?php
# $Id$ 

/**
 * Simple class to represent a user.
 */
class MailboxHandler extends PFAHandler {

    protected $db_table = 'mailbox';
    protected $id_field = 'username';
    protected $domain_field = 'domain';

    # init $this->struct, $this->db_table and $this->id_field
    protected function initStruct() {
        $this->struct=array(
            # field name                allow       display in...   type    $PALANG label                     $PALANG description                 default / options / ...
            #                           editing?    form    list
            'username'      => pacol(   $this->new, 1,      1,      'mail', 'pEdit_mailbox_username'        , ''                                , '' ),
            'local_part'    => pacol(   $this->new, 0,      0,      'text', 'pEdit_mailbox_username'        , ''                                , '' ),
            'domain'        => pacol(   $this->new, 0,      0,      'enum', ''                              , ''                                , '', 
                /*options*/ $this->allowed_domains      ),
            # TODO: maildir: display in list is needed to include maildir in SQL result (for post_edit hook)
            # TODO:          (not a perfect solution, but works for now - maybe we need a separate "include in SELECT query" field?)
            'maildir'       => pacol(   $this->new, 0,      1,      'text', ''                              , ''                                , '' ),
            'password'      => pacol(   1,          1,      0,      'pass', 'password'                      , 'pCreate_mailbox_password_text'   , '' ),
            'password2'     => pacol(   1,          1,      0,      'pass', 'pCreate_mailbox_password2'    , ''                                 , '', 
                /*options*/ '',
                /*not_in_db*/ 0,
                /*dont_write_to_db*/ 1,
                /*select*/ 'password as password2'
            ),
            'name'          => pacol(   1,          1,      1,      'text', 'pEdit_mailbox_name'            , 'pCreate_mailbox_name_text'       , '' ),
            'quota'         => pacol(   1,          1,      1,      'int' , 'pEdit_mailbox_quota'           , 'pEdit_mailbox_quota_text'        , '' ), # in MB
            # read_from_db_postprocess() also sets 'quotabytes' for use in init()
            'active'        => pacol(   1,          1,      1,      'bool', 'active'                        , ''                                 , 1 ),
            'welcome_mail'  => pacol(   $this->new, $this->new, 0,  'bool', 'pCreate_mailbox_mail'          , ''                                 , 1, 
                /*options*/ '',
                /*not_in_db*/ 1             ),
            'created'       => pacol(   0,          0,      1,      'ts',   'created'                       , ''                                 ),
            'modified'      => pacol(   0,          0,      1,      'ts',   'last_modified'                 , ''                                 ),
            # TODO: add virtual 'notified' column and allow to display who received a vacation response?
        );

        # update allowed quota
        if (count($this->struct['domain']['options']) > 0) $this->prefill('domain', $this->struct['domain']['options'][0]);
    }

    public function init($id) {
        if (!parent::init($id)) {
            return false;
        }

        list(/*NULL*/,$domain) = explode('@', $this->id);

        if ($this->new) {
            $currentquota = 0;
        } else {
            $currentquota = $this->return['quotabytes']; # parent::init called ->view()
        }

        $this->updateMaxquota($domain, $currentquota);

        return true; # still here? good.
    }

    /**
     * show max allowed quota in quota field description
     * @param string - domain
     * @param int - current quota
     */
    protected function updateMaxquota ($domain, $currentquota) {
        if ($domain == '') return false;

        $maxquota = allowed_quota($domain, $currentquota);

        if ($maxquota == 0) {
            # TODO: show 'unlimited'
        # } elseif ($maxquota < 0) {
            # TODO: show 'disabled' - at the moment, just shows '-1'
        } else {
            $this->struct['quota']['desc'] = Lang::read_f('mb_max', $maxquota);
        }
    }

    protected function initMsg() {
        $this->msg['error_already_exists'] = 'email-address-already-exists';
        $this->msg['error_does_not_exist'] = 'pCreate_mailbox_username_text_error1';
        if ($this->new) {
            $this->msg['logname'] = 'create_mailbox';
            $this->msg['store_error'] = 'pCreate_mailbox_result_error';
            $this->msg['successmessage'] = 'pCreate_mailbox_result_success';
        } else {
            $this->msg['logname'] = 'edit_mailbox';
            $this->msg['store_error'] = 'pCreate_mailbox_result_error'; # TODO: better error message
            $this->msg['successmessage'] = 'pCreate_mailbox_result_success'; # TODO: better message
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
            'create_button' => 'pCreate_mailbox_button',

            # various settings
            'required_role' => 'admin',
            'listview'      => 'list-virtual.php',
            'early_init'    => 0,
            'prefill'       => array('domain'),
        );
    }


    protected function validate_new_id() {
        if ($this->id == '') {
            $this->errormsg[$this->id_field] = Lang::read('pCreate_mailbox_username_text_error1');
            return false;
        }

        $email_check = check_email($this->id);
        if ( $email_check != '' ) {
            $this->errormsg[$this->id_field] = $email_check;
            return false;
        }

        list(/*NULL*/,$domain) = explode ('@', $this->id);

        if(!$this->create_allowed($domain)) {
            $this->errormsg[] = Lang::read('pCreate_mailbox_username_text_error3');
            return false;
        }

        # check if an alias with this name already exists - if yes, don't allow to create the mailbox
        $handler = new AliasHandler(1);
        if (!$handler->init($this->id)) {
            $this->errormsg[] = Lang::read('email-address-already-exists');
            return false;
        }

        return true; # still here? good!
    }

    /**
     * check number of existing mailboxes for this domain - is one more allowed?
     */
    private function create_allowed($domain) {
        $limit = get_domain_properties ($domain);

        if ($limit['mailboxes'] == 0) return true; # unlimited
        if ($limit['mailboxes'] < 0) return false; # disabled
        if ($limit['mailbox_count'] >= $limit['mailboxes']) return false;
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
            $db_result[$key]['quotabytes'] = $row['quota'];
            $db_result[$key]['quota'] = divide_quota($row['quota']); # convert quota to MB
        }
        return $db_result;
    }


    protected function beforestore() {

        if ( isset($this->values['quota']) && $this->values['quota'] != -1 ) {
            $this->values['quota'] = $this->values['quota'] * Config::read('quota_multiplier'); # convert quota from MB to bytes
        }

        $ah = new AliasHandler($this->new, $this->admin_username);

        $ah->calledBy('MailboxHandler');

        if ( !$ah->init($this->id) ) {
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

        if (!$ah->store()) {
            $this->errormsg[] = $ah->errormsg[0];
            return false;
        }

        return true; # still here? good!
    }
    
    protected function storemore() {
        if ($this->new) {

            list(/*NULL*/,$domain) = explode('@', $this->id);

            if ( !mailbox_postcreation($this->id,$domain,$this->values['maildir'], $this->values['quota']) ) {
                $this->errormsg[] = Lang::read('pCreate_mailbox_result_error') . " ($this->id)";
                # return false; # TODO: should this be fatal?
            }

            if ($this->values['welcome_mail'] == true) {
                if ( !$this->send_welcome_mail() ) {
                    # return false; # TODO: should this be fatal?
                }
            }

            if ( !create_mailbox_subfolders($this->id,$this->values['password'])) {
                # TODO: implement $tShowpass
                flash_info(Lang::read('pCreate_mailbox_result_succes_nosubfolders') . " ($fUsername$tShowpass)"); # TODO: don't use flash_info
            } else { # everything ok
                # TODO: implement $tShowpass
                # flash_info(Lang::read('pCreate_mailbox_result_success']) . " ($fUsername$tShowpass)*"); # TODO: don't use flash_info
                # TODO: currently edit.php displays the default success message from webformConfig
            } 

        } else { # edit mode
            # alias active status is updated in before_store()

            # postedit hook
            # TODO: implement a poststore() function? - would make handling of old and new values much easier...
            list(/*NULL*/,$domain) = explode('@', $this->id);

            $old_mh = new MailboxHandler();

            if (!$old_mh->init($this->id)) {
                $this->errormsg[] = $old_mh->errormsg[0];
            } elseif (!$old_mh->view()) {
                $this->errormsg[] = $old_mh->errormsg[0];
            } else {
                $oldvalues = $old_mh->result();

                $maildir = $oldvalues['maildir'];
                if (isset($this->values['quota'])) {
                    $quota = $this->values['quota'];
                } else {
                    $quota = $oldvalues['quota'];
                }

                if ( !mailbox_postedit($this->id,$domain,$maildir, $quota)) {
                    $this->errormsg[] = $PALANG['pEdit_mailbox_result_error']; # TODO: more specific error message
                }
            }
        }
        return true; # even if a hook failed, mark the overall operation as OK
    }


/* function already exists (see old code below 
    public function delete() {
        $this->errormsg[] = '*** deletion not implemented yet ***';
        return false; # XXX function aborts here! XXX
    }
*/

    protected function _prefill_domain($field, $val) {
        if (in_array($val, $this->struct[$field]['options'])) {
            $this->struct[$field]['default'] = $val;
            $this->updateMaxquota($val, 0);
        }
    }

    /**
     * check if quota is allowed
     */
    protected function _field_quota($field, $val) {
        list(/*NULL*/,$domain) = explode('@', $this->id);

        if ( !check_quota ($val, $domain, $this->id) ) {
            $this->errormsg[$field] = Lang::Read('pEdit_mailbox_quota_text_error');
            return false;
        }
        return true;
    }

    /**
     * - compare password / password2 field (error message will be displayed at password2 field)
     * - autogenerate password if enabled in config and $new
     * - display password on $new if enabled in config or autogenerated
     */
    protected function _field_password($field, $val) {
        if (!$this->_field_password2($field, $val)) return false;

        if ($this->new && Config::read('generate_password') == 'YES' && $val == '') {
            # auto-generate new password
            unset ($this->errormsg[$field]); # remove "password too short" error message
            $val = generate_password();
            $this->values[$field] = $val; # we are doing this "behind the back" of set()
            $this->infomsg[] = "Password: $val"; # TODO: make translateable
            return false; # to avoid that set() overwrites $this->values[$field]
        } elseif ($this->new && Config::read('show_password') == 'YES') {
            $this->infomsg[] = "Password: $val"; # TODO: make translateable
        }

        return true; # still here? good.
    }

    /**
     * compare password / password2 field
     * error message will be displayed at the password2 field
     */
    protected function _field_password2($field, $val) {
        return $this->compare_password_fields('password', 'password2');
    }

        /**
         * on $this->new, set localpart based on address
         */
        protected function _missing_local_part ($field) {
            list($local_part,$domain) = explode ('@', $this->id);
            $this->RAWvalues['local_part'] = $local_part;
        }

        /**
         * on $this->new, set domain based on address
         */
        protected function _missing_domain ($field) {
            list($local_part,$domain) = explode ('@', $this->id);
            $this->RAWvalues['domain'] = $domain;
        }


    /**
    * calculate maildir path for the mailbox
    */
    protected function _missing_maildir($field) {
        list($local_part,$domain) = explode('@', $this->id);                                                                                   

        #TODO: 2nd clause should be the first for self explaining code.
        #TODO: When calling config::Read with parameter we sould be right that read return false if the parameter isn't in our config file.

        if(Config::read('maildir_name_hook') != 'NO' && function_exists(Config::read('maildir_name_hook')) ) {
            $hook_func = $CONF['maildir_name_hook'];
            $maildir = $hook_func ($domain, $this->id);
        } elseif (Config::read('domain_path') == "YES") {
            if (Config::read('domain_in_mailbox') == "YES") {
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
        if(empty($fFrom) || $fFrom == 'CLI') $fFrom = $this->id;
        $fSubject = Lang::read('pSendmail_subject_text');
        $fBody = Config::read('welcome_text');

        if (!smtp_mail ($fTo, $fFrom, $fSubject, $fBody)) {
            $this->errormsg[] = Lang::read('pSendmail_result_error');
            return false;
        } else {
# TODO            flash_info($PALANG['pSendmail_result_success']); 
        }

        return true;
    }


/********************************************************************************************************************
     old functions - we'll see what happens to them
     (at least they should use the *Handler functions instead of doing SQL)
/********************************************************************************************************************/

    /**
     * @return boolean true on success; false on failure
     * @param string $old_password
     * @param string $new_passwords
     * @param bool $match = true
     *
     * All passwords need to be plain text; they'll be hashed appropriately
     * as per the configuration in config.inc.php
     */
    public function change_pw($new_password, $old_password, $match = true) {
        list(/*NULL*/,$domain) = explode('@', $this->id);

        if ($match == true) {
            if (!$this->login($this->id, $old_password)) {
                      db_log ($domain, 'edit_password', "MATCH FAILURE: " . $this->id);
                      $this->errormsg[] = 'Passwords do not match'; # TODO: make translatable
                      return false;
            }
        }

        $set = array(
            'password' => pacrypt($new_password) ,
        );

        $result = db_update('mailbox', 'username', $this->id, $set );

        if ($result != 1) {
            db_log ($domain, 'edit_password', "FAILURE: " . $this->id);
            $this->errormsg[] = Lang::read('pEdit_mailbox_result_error');
            return false;
        }

        db_log ($domain, 'edit_password', $this->id);
        return true;
    }

# remaining comments from add():
# FIXME: default value of $quota (-999) is intentionally invalid. Add fallback to default quota.
# Solution: Invent an sub config class with additional informations about domain based configs like default qouta.
# FIXME: Should the parameters be optional at all?
# TODO: check if parameters are valid/allowed (quota?).
# TODO: most code should live in a separate function that can be used by add and edit.
# TODO: On the longer term, the web interface should also use this class.


#TODO: more self explaining language strings!

# TODO: if we want to have the encryption method in the encrypted password string, it should be done in pacrypt(). No special handling here!
#        if ( preg_match("/^dovecot:/", Config::read('encrypt')) ) {
#            $split_method = preg_split ('/:/', Config::read('encrypt'));
#            $method       = strtoupper($split_method[1]);
#            $password = '{' . $method . '}' . $password;
#        }

    public function delete() {
        $username = $this->id;
        list(/*$local_part*/,$domain) = explode ('@', $username);

        $E_username = escape_string($username);
        $E_domain = escape_string($domain);

#TODO:  At this level of table by key calls we should think about a solution in our query function and drupal like {mailbox} {alias}.
#       Pseudocode for db_query etc.
#       if {} in query then
#           table_by_key( content between { and } )
#       else error

        $table_mailbox = table_by_key('mailbox');
        $table_alias = table_by_key('alias');
        $table_vacation = table_by_key('vacation');
        $table_vacation_notification = table_by_key('vacation_notification');

        db_begin();

#TODO: true/false replacement!
        $error = 0;

        $result = db_query("SELECT * FROM $table_alias WHERE address = '$E_username' AND domain = '$E_domain'");
        if($result['rows'] == 1) {
            $result = db_delete('alias', 'address', $username);
            db_log ($domain, 'delete_alias', $username);
        } else {
            $this->errormsg[] = "no alias $username"; # todo: better message, make translatable
            $error = 1;
        }

        /* is there a mailbox? if do delete it from orbit; it's the only way to be sure */
        $result = db_query ("SELECT * FROM $table_mailbox WHERE username='$E_username' AND domain='$E_domain'");
        if ($result['rows'] == 1) {
            $result = db_delete('mailbox', 'username', $username);
            $postdel_res=mailbox_postdeletion($username,$domain);
            if ($result != 1 || !$postdel_res) {

                $tMessage = Lang::read('pDelete_delete_error') . "$username (";
                if ($result['rows']!=1) { # TODO: invalid test, $result is from db_delete and only contains the number of deleted rows
                    $tMessage.='mailbox';
                    if (!$postdel_res) $tMessage.=', ';
                    $this->errormsg[] = "no mailbox $username"; # todo: better message, make translatable
                    $error = 1;
                }
                if (!$postdel_res) {
                    $tMessage.='post-deletion';
                    $this->errormsg[] = "post-deletion script failed"; # todo: better message, make translatable
                    $error = 1;
                }
                $this->errormsg[] = $tMessage.')';
                # TODO: does db_rollback(); make sense? Not sure because mailbox_postdeletion was already called (move the call checking the db_delete result?)
                # TODO: maybe mailbox_postdeletion should be run after all queries, just before commit/rollback
                $error = 1;
#                return false; # TODO: does this make sense? Or should we still cleanup vacation and vacation_notification?
            }
            db_log ($domain, 'delete_mailbox', $username);
        } else {
            $this->errormsg[] = "no mailbox $username"; # TODO: better message, make translatable
            $error = 1;
        }
        $result = db_query("SELECT * FROM $table_vacation WHERE email = '$E_username' AND domain = '$E_domain'");
        if($result['rows'] == 1) {
            db_delete('vacation', 'email', $username);
            db_delete('vacation_notification', 'on_vacation', $username); # TODO: delete vacation_notification independent of vacation? (in case of "forgotten" vacation_notification entries)
        }
        db_commit();
        if ($error != 0) return false;
        return true;
    }

}

/* vim: set expandtab softtabstop=4 tabstop=4 shiftwidth=4: */
