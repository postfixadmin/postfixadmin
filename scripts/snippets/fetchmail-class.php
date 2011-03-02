<?php

class pfaFetchmail extends postfixadminBaseclass {

    private $table = "fetchmail";

    protected function initStruct() {
        $extraopts = boolconf('fetchmail_extra_options');
        $display_status = 1;
        if ($new || $edit) $display_status = 0;
        $this->struct = array(
            //   list($editible,$viewinedit,$view,$type)
            # field                     allow       display in  display     type
            # name                      editing?    add/edit?   in list?
            "id"            => array(   0,          0,          0,          'id'        ),
            "mailbox"       => array(   1,          1,          1,          'enum'      ),
            "src_server"    => array(   1,          1,          1,          'text'      ),
            "src_auth"      => array(   1,          1,          1,          'enum'      ),
            "src_user"      => array(   1,          1,          1,          'text'      ),
            "src_password"  => array(   1,          1,          0,          'password'  ),
            "src_folder"    => array(   1,          1,          1,          'text'      ),
            "poll_time"     => array(   1,          1,          1,          'num'       ),
            "fetchall"      => array(   1,          1,          1,          'bool'      ),
            "keep"          => array(   1,          1,          1,          'bool'      ),
            "protocol"      => array(   1,          1,          1,          'enum'      ),
            "ssl"           => array(   1,          1,          1,          'bool'      ),
            "extra_options" => array(   $extraopts, $extraopts, $extraopts, 'longtext'  ),
            "mda"           => array(   $extraopts, $extraopts, $extraopts, 'longtext'  ),
            "date"          => array(   0,          0,          1,          'text'      ),
            "returned_text" => array(   0,          0,          1,          'longtext'  ),
        )   ;
    }

    protected function initDefaults() {
        $this->defaults = array(
            "id"        =>  0,
            "mailbox"   =>  array(), # filled below
            "poll_time" =>  10,
            "src_auth"  =>
                array('password','kerberos_v5','kerberos','kerberos_v4','gssapi','cram-md5','otp','ntlm','msn','ssh','any'),
            "protocol"  =>
                array('POP3','IMAP','POP2','ETRN','AUTO'),
        );

        $list_domains = list_domains_for_admin ($SESSID_USERNAME);
#        $user_domains=implode(", ",array_values($list_domains)); # for displaying
        $user_domains_sql=implode("','",escape_string(array_values($list_domains))); # for SQL
        $sql="SELECT username FROM mailbox WHERE domain in ('".$user_domains_sql."')"; # TODO: replace with domain selection dropdown

        $res = db_query ($sql);
        if ($res['rows'] > 0){
            $this->defaults["mailbox"]=array();
            while ($name = db_array ($res['result'])){
                $this->defaults["mailbox"][] = $name["username"];
            }
        } else {
            $this->defaults["mailbox"]=array();
            $this->defaults["mailbox"][]=$SESSID_USERNAME; # TODO: Does this really make sense? Or should we display a message "please create a mailbox first!"?
        }

    }

}

/* vim: set expandtab softtabstop=4 tabstop=4 shiftwidth=4: */
?>
