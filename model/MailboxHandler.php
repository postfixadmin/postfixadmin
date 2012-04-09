<?php
# $Id$ 

/**
 * Simple class to represent a user.
 */
class MailboxHandler extends PFAHandler {

    protected $domain_field = 'domain';

    # init $this->struct, $this->db_table and $this->id_field
    protected function initStruct() {
        $this->db_table = 'mailbox';
        $this->id_field = 'username';
    
        $this->struct=array(
            # field name                allow       display in...   type    $PALANG label                     $PALANG description                 default / options / ...
            #                           editing?    form    list
            'username'      => pacol(   $this->new, 1,      1,      'text', ''                              , ''                                , '' ),
            'local_part'    => pacol(   0,          0,      0,      'text', ''                              , ''                                , '' ),
            'domain'        => pacol(   0,          0,      0,      'enum', ''                              , ''                                , '' ),
            'maildir'       => pacol(   0,          0,      0,      'text', ''                              , ''                                , '' ),
            'password'      => pacol(   1,          1,      0,      'pass', ''                              , ''                                , '' ),
            'password2'     => pacol(   1,          1,      0,      'pass', ''                             , ''                                 , '', 
                /*options*/ '',
                /*not_in_db*/ 0,
                /*dont_write_to_db*/ 1,
                /*select*/ 'password as password2'
            ),
            'name'          => pacol(   1,          1,      1,      'text', ''                              , ''                                , '' ),
            'quota'         => pacol(   1,          1,      1,      'int' , ''                              , ''                                , '' ),
            'active'        => pacol(   1,          1,      1,      'bool', ''                              , ''                                 , 1 ),
            'created'       => pacol(   0,          0,      1,      'ts',   ''                              , ''                                 ),
            'modified'      => pacol(   0,          0,      1,      'ts',   ''                              , ''                                 ),
            # TODO: add virtual 'notified' column and allow to display who received a vacation response?
        );
    }

    # messages used in various functions.
    # always list the key to hand over to Lang::read
    # the only exception is 'logname' which uses the key for db_log
    protected function initMsg() {
        $this->msg['error_already_exists'] = 'pCreate_mailbox_username_text_error2';
        $this->msg['error_does_not_exist'] = 'pCreate_mailbox_username_text_error1';
        if ($this->new) {
            $this->msg['logname'] = 'create_mailbox';
            $this->msg['store_error'] = 'pCreate_mailbox_result_error';
        } else {
            $this->msg['logname'] = 'edit_mailbox';
            $this->msg['store_error'] = 'pCreate_mailbox_result_error'; # TODO: better error message
        }
    }

    /*
     * Configuration for the web interface
     */
    public function webformConfig() {
        return array(
            # $PALANG labels
            'formtitle_create' => 'pCreate_mailbox_welcome',
            'formtitle_edit' => 'pEdit_mailbox_welcome',
            'create_button' => 'pCreate_mailbox_button',
            'successmessage' => 'pCreate_mailbox_result_success',

            # various settings
            'required_role' => 'admin',
            'listview' => 'list-virtual.php',
            'early_init' => 1, # 0 for create-domain
        );
    }


    protected function validate_new_id() {
        if ($this->id == '') {
            $this->errormsg[] = Lang::read('pCreate_mailbox_username_text_error1');
            return false;
        }

        list($local_part,$domain) = explode ('@', $this->id);

        if(!$this->create_allowed($domain)) {
            $this->errormsg[] = Lang::read('pCreate_mailbox_username_text_error3');
            return false;
        }

        # check if an alias with this name already exists - if yes, don't allow to create the mailbox
        $handler = new AliasHandler(1);
        if (!$handler->init($this->id)) {
            $this->errormsg[] = Lang::read('pCreate_mailbox_username_text_error2');
            return false;
        }

        return check_email($this->id); # TODO: check_email should return error message instead of using flash_error itsself
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


/* function already exists (see old code below 
    public function delete() {
        $this->errormsg[] = '*** deletion not implemented yet ***';
        return false; # XXX function aborts here! XXX
    }
*/



/********************************************************************************************************************
     old functions - we'll see what happens to them
     (at least they should use the *Handler functions instead of doing SQL)
/********************************************************************************************************************/

    public function change_pass($old_password, $new_password) {
        error_log('MailboxHandler->change_pass is deprecated. Please use MailboxHandler->change_pw!');
        return $this->change_pw($new_password, $old_password);
    }

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

    /**
     * Attempt to log a user in.
     * @param string $username
     * @param string $password
     * @return boolean true on successful login (i.e. password matches etc)
     */
    public static function login($username, $password) {
        $username = escape_string($username);

        $table_mailbox = table_by_key('mailbox');
        $active = db_get_boolean(True);
        $query = "SELECT password FROM $table_mailbox WHERE username='$username' AND active='$active'";

        $result = db_query ($query);
        if ($result['rows'] == 1) {
            $row = db_array ($result['result']);
            $crypt_password = pacrypt ($password, $row['password']);

            if($row['password'] == $crypt_password) {
                return true;
            }
        }
        return false;
    }
/**
 * Add mailbox
 * @param password string password of account
 * @param gen boolean
 * @param name string
 *
 */
    public function add($password, $name = '', $quota = -999, $active = true, $mail = true  ) {
# FIXME: default value of $quota (-999) is intentionally invalid. Add fallback to default quota.
# Solution: Invent an sub config class with additional informations about domain based configs like default qouta.
# FIXME: Should the parameters be optional at all?
# TODO: check if parameters are valid/allowed (quota?).
# TODO: most code should live in a separate function that can be used by add and edit.
# TODO: On the longer term, the web interface should also use this class.

# TODO: copy/move all checks and validations from create-mailbox.php here

        $username = $this->id;
        list($local_part,$domain) = explode ('@', $username);


#TODO: more self explaining language strings!
        if(!check_mailbox ($domain)) {
            $this->errormsg[] = Lang::read('pCreate_mailbox_username_text_error3');
            return false;
        }

        # check if an alias with this name already exists
        $result = db_query ("SELECT * FROM " . table_by_key('alias') . " WHERE address='" . escape_string($username) . "'");
        if ($result['rows'] == 1) {
            $this->errormsg[] = Lang::read('pCreate_mailbox_username_text_error2');
            return false;
        }

        $plain = $password;
        $password = pacrypt ($password);

# TODO: if we want to have the encryption method in the encrypted password string, it should be done in pacrypt(). No special handling here!
#        if ( preg_match("/^dovecot:/", Config::read('encrypt')) ) {
#            $split_method = preg_split ('/:/', Config::read('encrypt'));
#            $method       = strtoupper($split_method[1]);
#            $password = '{' . $method . '}' . $password;
#        }

#TODO: 2nd clause should be the first for self explaining code.
#TODO: When calling config::Read with parameter we sould be right that read return false if the parameter isn't in our config file.
        if(Config::read('maildir_name_hook') != 'NO' && function_exists(Config::read('maildir_name_hook')) ) {
            $hook_func = $CONF['maildir_name_hook'];
            $maildir = $hook_func ($fDomain, $fUsername);
        } elseif (Config::read('domain_path') == "YES") {
            if (Config::read('domain_in_mailbox') == "YES") {
                $maildir = $domain . "/" . $username . "/";
            } else {
                $maildir = $domain . "/" . $local_part . "/";
            }
        } else {
            # If $CONF['domain_path'] is set to NO, $CONF['domain_in_mailbox] is forced to YES.
            # Otherwise user@example.com and user@foo.bar would be mixed up in the same maildir "user/".
            $maildir = $username . "/";
        }

        db_begin();

        $active = db_get_boolean($active);
        $quota = multiply_quota ($quota);

        $alias_data = array(
            'address' => $username,
            'goto' => $username,
            'domain' => $domain,
            'active' => $active,
        );

        $result = db_insert('alias', $alias_data);
#MARK: db_insert returns true/false??
        if ($result != 1) {
            $this->errormsg[] = Lang::read('pAlias_result_error') . "\n($username -> $username)\n";
            return false;
        }

        $mailbox_data = array(
            'username' => $username,
            'password' => $password,
            'name' => $name,
            'maildir' => $maildir,
            'local_part' => $local_part,
            'quota' => $quota,
            'domain' => $domain,
            'active' => $active,
        );
        $result = db_insert('mailbox', $mailbox_data);
#MARK: Same here!
        if ($result != 1 || !mailbox_postcreation($username,$domain,$maildir, $quota)) {
            $this->errormsg[] = Lang::read('pCreate_mailbox_result_error') . "\n($username)\n";
            db_rollback();
            return false;
        } else {
            db_commit();
            db_log ($domain, 'create_mailbox', $username);


            if ($mail == true) {
                # TODO: move "send the mail" to a function
                $fTo = $username;
                $fFrom = smtp_get_admin_email();
                if(empty($fFrom) || $fFrom == 'CLI') $fFrom = $this->id;
                $fSubject = Lang::read('pSendmail_subject_text');
                $fBody = Config::read('welcome_text');

                if (!smtp_mail ($fTo, $fFrom, $fSubject, $fBody)) {
                    $this->errormsg[] = Lang::read('pSendmail_result_error');
                    return false;
                }
            }

            create_mailbox_subfolders($username,$plain);
        }
        return true;
    }


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
