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

    /**
     * @return boolean true on success; false on failure 
     * @param string $username
     * @param string $old_password
     * @param string $new_passwords
     *
     * All passwords need to be plain text; they'll be hashed appropriately
     * as per the configuration in config.inc.php
     */
    public function change_pw($new_password, $old_password, $match = true) {
        global $config;
        $username = $this->username;
        $tmp = preg_split ('/@/', $username);
        $domain = $tmp[1];

        $username = escape_string($username);
        $table_mailbox = table_by_key('mailbox');
        
        $new_db_password = escape_string(pacrypt($new_password));

        if ($match == true) {
                $active = db_get_boolean(True);
                $result = db_query("SELECT * FROM $table_mailbox WHERE username='$username' AND active='$active'");
                $result = $result['result'];
                if ($new_db_password != $result['password']) {
                      $this->errormsg[] = 'Passwords do not Match';
                      return 1;
                }
        }
        
        $set = array(
                'password' => $new_db_password
              );
        
        $result = db_update('mailbox', 'username=\''.$username.'\'', $set, array('modified') );

        db_log ('CONSOLE', $domain, 'edit_password', "$username");
        if ($result != 1) {
            $this->errormsg[] = Lang::read('pEdit_mailbox_result_error');
            return 1;
        }
        
        return 0;
        
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
/**
 * Add mailbox
 * @param password string password of account 
 * @param gen boolean
 * @param name string
 *
 */
    public function add($password, $name = '', $quota = 0, $active = true, $mail = true  ) {
        global $config;
        $username = $this->username;
        $tmp = preg_split ('/@/', $username);
        $domain = $tmp[1];
        $address = escape_string($username);
        $username = $tmp[0];

        $table_mailbox = table_by_key('mailbox');
        $table_alias = table_by_key('alias');
        
        $active = db_get_boolean($active);
        
        if(!check_mailbox ($domain)) {
            $this->errormsg[] = Lang::read('pCreate_mailbox_username_text_error3');
            return 1;
        }
        $result = db_query ("SELECT * FROM $table_alias WHERE address='$address'");
        if ($result['rows'] == 1){
            $this->errormsg[] = Lang::read('pCreate_mailbox_username_text_error2');
            return 1;
        }
        
        
        $plain = $password;
        $password = pacrypt ($password);
	
	      if ( preg_match("/^dovecot:/", Config::read('encrypt')) ) {
	        $split_method = preg_split ('/:/', Config::read('encrypt'));
          $method       = strtoupper($split_method[1]);
          $password = '{' . $method . '}' . $password;
        }

        if (Config::read('domain_path') == "YES")
        {
            if (Config::read('domain_in_mailbox') == "YES")
            {
                $maildir = $domain . "/" . $address . "/";
            }
            else
            {
                $maildir = $domain . "/" . $username . "/";
            }
        }
        else
        {
            $maildir = $address . "/";
        }

            $quota = multiply_quota ($quota);


        if ('pgsql'== Config::read('database_type'))
        {
            db_query('BEGIN');
        }

        //$result = db_query ("INSERT INTO $table_alias (address,goto,domain,created,modified,active) VALUES ('$address','$address','$domain',NOW(),NOW(),'$active')");
        $arr = array(
            'address' => $address,
            'goto' => $address,
            'domain' => $domain,
            'active' => $active,
            );
        
        $result = db_insert($table_alias, $arr, array('created', 'modified') );
        if ($result != 1)
        {
            $this->errormsg[] = Lang::read('pAlias_result_error') . "\n($address -> $address)\n";
            return 1;
        }

        // apparently uppercase usernames really confuse some IMAP clients.
        $local_part = '';
        if(preg_match('/^(.*)@/', $address, $matches)) {
            $local_part = $matches[1];
        }

        //$result = db_query ("INSERT INTO $table_mailbox (username,password,name,maildir,local_part,quota,domain,created,modified,active) VALUES ('$username','$password','$name','$maildir','$local_part','$quota','$domain',NOW(),NOW(),'$active')");
                                                          

        $arr2 = array(
            'username' => $address,
            'password' => $password,
            'name' => $name,
            'maildir' => $maildir,
            'local_part' => $local_part,
            'quota' => $quota,
            'domain' => $domain,
            'active' => $active,
            );
        $result = db_insert($table_mailbox, $arr2, array('created', 'modified') );
        if ($result != 1 || !mailbox_postcreation($address,$domain,$maildir, $quota))
        {
            $this->errormsg[] = Lang::read('pCreate_mailbox_result_error') . "\n($address)\n";
            db_query('ROLLBACK');
            return 1;
        }
        else
        {
            db_query('COMMIT');
            db_log ('CONSOLE', $domain, 'create_mailbox', "$address");


            if ($mail == true)
            {
                $fTo = $address;
                $fFrom = Config::read('admin_email');
                $fHeaders = "To: " . $fTo . "\n";
                $fHeaders .= "From: " . $fFrom . "\n";

                $fHeaders .= "Subject: " . encode_header (Lang::read('pSendmail_subject_text')) . "\n";
                $fHeaders .= "MIME-Version: 1.0\n";
                $fHeaders .= "Content-Type: text/plain; charset=utf-8\n";
                $fHeaders .= "Content-Transfer-Encoding: 8bit\n";

                $fHeaders .= Config::read('welcome_text');

                if (!smtp_mail ($fTo, $fFrom, $fHeaders))
                {
                    $this->errormsg[] = Lang::read('pSendmail_result_error');
                    return 1;
                }
            }

            create_mailbox_subfolders($address,$plain);

        }
        return 0;
    }
    
    
    
    
    public function view() {
        global $config;

        

        $username = $this->username;
        $table_mailbox = table_by_key('mailbox');
        
        $result = db_query("SELECT username, name, maildir, quota, local_part, domain, DATE_FORMAT(created, '%d.%m.%y') AS created, DATE_FORMAT(modified, '%d.%m.%y') AS modified, active FROM $table_mailbox WHERE username='$username'");
        if ($result['rows'] != 0) {
          $this->return = db_array($result['result']);
          return 0;
        }
        $this->errormsg = $result['error'];
        return 1;
    }
    
    public function delete() {
        global $config;
        $username = $this->username;
        $tmp = preg_split ('/@/', $username);
        $domain = $tmp[1];
        $username = escape_string($username);
        


        $table_mailbox = table_by_key('mailbox');
        $table_alias = table_by_key('alias');
        $table_vacation = table_by_key('vacation');
        $table_vacation_notification = table_by_key('vacation_notification');

         if (Config::read('database_type') == "pgsql") db_query('BEGIN');
        /* there may be no aliases to delete */
        $result = db_query("SELECT * FROM $table_alias WHERE address = '$username' AND domain = '$domain'");
        if($result['rows'] == 1) {
            //$result = db_query ("DELETE FROM $table_alias WHERE address='$username' AND domain='$domain'");
            $result = db_delete($table_alias, 'address', $username);
            db_log ('CONSOLE', $domain, 'delete_alias', $username);
        }

        /* is there a mailbox? if do delete it from orbit; it's the only way to be sure */
        $result = db_query ("SELECT * FROM $table_mailbox WHERE username='$username' AND domain='$domain'");
        if ($result['rows'] == 1)
        {
            //$result = db_query ("DELETE FROM $table_mailbox WHERE username='$username' AND domain='$domain'");
            $result = db_delete($table_mailbox, 'username', $username);
            $postdel_res=mailbox_postdeletion($username,$domain);
            if ($result != 1 || !$postdel_res)
            {

                $tMessage = Lang::read('pDelete_delete_error') . "$username (";
                if ($result['rows']!=1)
                {
                    $tMessage.='mailbox';
                    if (!$postdel_res) $tMessage.=', ';
                }
                if (!$postdel_res)
                {
                    $tMessage.='post-deletion';
                }
                $this->errormsg[] = $tMessage.')';
                return 1;
            }
            db_log ('CONSOLE', $domain, 'delete_mailbox', $username);
        }
        $result = db_query("SELECT * FROM $table_vacation WHERE email = '$username' AND domain = '$domain'");
        if($result['rows'] == 1) {
            //db_query ("DELETE FROM $table_vacation WHERE email='$username' AND domain='$domain'");
            db_delete($table_vacation, 'email', $username);
            //db_query ("DELETE FROM $table_vacation_notification WHERE on_vacation ='$username' "); /* should be caught by cascade, if PgSQL */
            db_delete($table_vacation_notification, 'on_vacation', $username);
        }
        return 0;
    }

}

/* vim: set expandtab softtabstop=4 tabstop=4 shiftwidth=4: */
