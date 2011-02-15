<?php

/**
 * Simple class to represent a user.
 */
class UserHandler {

    protected $username = null;
    
    public $errormsg = array();

    public function __construct($username) {
        $this->username = strtolower($username);
    }

    public function change_pass($old_password, $new_password) {
        error_log('UserHandler->change_pass is deprecated. Please use UserHandler->change_pw!');
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
        list(/*NULL*/,$domain) = explode('@', $username);

        $E_username = escape_string($this->username);
        $table_mailbox = table_by_key('mailbox');
        
        if ($match == true) {
                $active = db_get_boolean(True);
                $result = db_query("SELECT password FROM $table_mailbox WHERE username='$E_username' AND active='$active'");
                $result = db_assoc($result['result']);
                
                if (pacrypt($old_password, $result['password']) != $result['password']) {
                      db_log ($domain, 'edit_password', "MATCH FAILURE: " . $this->username);
                      $this->errormsg[] = 'Passwords do not match'; # TODO: make translatable
                      return false;
                }
        }
        
        $set = array(
            'password' => pacrypt($new_password) ,
        );

        $result = db_update('mailbox', 'username', $this->username, $set );

        if ($result != 1) {
            db_log ($domain, 'edit_password', "FAILURE: " . $this->username);
            $this->errormsg[] = Lang::read('pEdit_mailbox_result_error');
            return false;
        }
        
        db_log ($domain, 'edit_password', $this->username);
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

        $username = $this->username;
        list($local_part,$domain) = explode ('@', $username);


#TODO: more self explaining language strings!
        if(!check_mailbox ($domain)) {
            $this->errormsg[] = Lang::read('pCreate_mailbox_username_text_error3');
            return false;
        }

        # check if an alias with this name already exists
        $result = db_query ("SELECT * FROM " . table_by_key('alias') . " WHERE address='" . escape_string($username) . "'");
        if ($result['rows'] == 1){
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
        }
        elseif (Config::read('domain_path') == "YES")
        {
            if (Config::read('domain_in_mailbox') == "YES")
            {
                $maildir = $domain . "/" . $username . "/";
            }
            else
            {
                $maildir = $domain . "/" . $local_part . "/";
            }
        }
        else
        {
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
        if ($result != 1)
        {
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


            if ($mail == true)
            {
                # TODO: move "send the mail" to a function
                $fTo = $username;
                $fFrom = Config::read('admin_email');
                $fSubject = Lang::read('pSendmail_subject_text');
                $fBody = Config::read('welcome_text');

                if (!smtp_mail ($fTo, $fFrom, $fSubject, $fBody))
                {
                    $this->errormsg[] = Lang::read('pSendmail_result_error');
                    return false;
                }
            }

            create_mailbox_subfolders($username,$plain);

        }
        return true;
    }
    
    
    
    
    public function view() {

        $username = $this->username;
        $table_mailbox = table_by_key('mailbox');
       
# TODO: check if DATE_FORMAT works in MySQL and PostgreSQL
# TODO: maybe a more fine-grained date format would be better for non-CLI usage
        $result = db_query("SELECT username, name, maildir, quota, local_part, domain, DATE_FORMAT(created, '%d.%m.%y') AS created, DATE_FORMAT(modified, '%d.%m.%y') AS modified, active FROM $table_mailbox WHERE username='$username'");
        if ($result['rows'] != 0) {
          $this->return = db_array($result['result']);
          return true;
        }
        $this->errormsg = $result['error'];
        return false;
    }
    
    public function delete() {
        $username = $this->username;
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
        
#TODO: ture/false replacement!
        $error = 0;

        $result = db_query("SELECT * FROM $table_alias WHERE address = '$E_username' AND domain = '$domain'");
        if($result['rows'] == 1) {
            $result = db_delete('alias', 'address', $username);
            db_log ($domain, 'delete_alias', $username);
        } else {
            $this->errormsg[] = "no alias $username"; # todo: better message, make translatable
            $error = 1;
        }

        /* is there a mailbox? if do delete it from orbit; it's the only way to be sure */
        $result = db_query ("SELECT * FROM $table_mailbox WHERE username='$E_username' AND domain='$domain'");
        if ($result['rows'] == 1)
        {
            $result = db_delete('mailbox', 'username', $username);
            $postdel_res=mailbox_postdeletion($username,$domain);
            if ($result != 1 || !$postdel_res)
            {

                $tMessage = Lang::read('pDelete_delete_error') . "$username (";
                if ($result['rows']!=1) # TODO: invalid test, $result is from db_delete and only contains the number of deleted rows
                {
                    $tMessage.='mailbox';
                    if (!$postdel_res) $tMessage.=', ';
                    $this->errormsg[] = "no mailbox $username"; # todo: better message, make translatable
                    $error = 1;
                }
                if (!$postdel_res)
                {
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
        $result = db_query("SELECT * FROM $table_vacation WHERE email = '$E_username' AND domain = '$domain'");
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
