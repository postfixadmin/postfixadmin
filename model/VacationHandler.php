<?php

class VacationHandler {
    protected $username = null;
    function __construct($username) {
        $this->username = $username;
    }

    /**
     * Removes the autoreply alias etc for this user; namely, if they're away we remove their vacation alias and 
     * set the vacation table record to false.
     * @return boolean true on success.
     */
    function remove() {
        $ah = new AliasHandler($this->username);
        $aliases = $ah->get(true); // fetch all.
        $new_aliases = array();
        $table_vacation = table_by_key('vacation');
        $table_vacation_notification = table_by_key('vacation_notification');

        /* go through the user's aliases and remove any that look like a vacation address */
        foreach($aliases as $alias) {
            if(!$ah->is_vacation_address($alias)) {
                $new_aliases[] = $alias;
            }
        }
        $ah->update($new_aliases, '', false);

        // tidy up vacation table.
        $active = db_get_boolean(False);
        $username = escape_string($this->username);
        $result = db_query("UPDATE $table_vacation SET active = '$active' WHERE email='$username'");
        $result = db_query("DELETE FROM $table_vacation_notification WHERE on_vacation='$username'");
        /* crap error handling; oh for exceptions... */
        return true;
    }

    /**
     * @return boolean true indicates this server supports vacation messages, and users are able to change their own.
     * @global array $CONF
     */
    function vacation_supported() {
        global $CONF;
        return $CONF['vacation'] == 'YES' && $CONF['vacation_control'] == 'YES';
    }

    /**
     * @return boolean true if on vacation, otherwise false
     * Why do we bother storing true/false in the vacation table if the alias dictates it anyway?
     */
    function check_vacation() {
        $ah = new AliasHandler($this->username);
        $aliases = $ah->get(true); // fetch all.
        foreach($aliases as $alias) {
            if($ah->is_vacation_address($alias)) {
                return true;
            }
        }
        return false;
    }

    /**
     * Retrieve information on someone who is on vacation
     * @return struct|boolean stored information on vacation - array(subject - string, message - string, active - boolean) 
     * will return false if no existing data 
     */
    function get_details() {
        $table_vacation = table_by_key('vacation');
        $username = escape_string($this->username);

        $sql = "SELECT * FROM $table_vacation WHERE email = '$username'";
        $result = db_query($sql);
        if($result['rows'] == 1) {
            $row = db_array($result['result']);
            $boolean = ($row['active'] == db_get_boolean(true));
            return array( 'subject' => $row['subject'],
                          'body' => $row['body'],
                          'active'  => $boolean );
        }
        return false;
    }
    /**
     * @param string $subject
     * @param string $body
     */
    function set_away($subject, $body) {
        $this->remove(); // clean out any notifications that might already have been sent.
        // is there an entry in the vacaton table for the user, or do we need to insert?
        $table_vacation = table_by_key('vacation');
        $username = escape_string($this->username);
        $body = escape_string($body);
        $subject = escape_string($subject);

        $result = db_query("SELECT * FROM $table_vacation WHERE email = '$username'");
        $active = db_get_boolean(True);
        // check if the user has a vacation entry already, if so just update it
        if($result['rows'] == 1) {
            $result = db_query("UPDATE $table_vacation SET active = '$active', body = '$body', subject = '$subject', created = NOW() WHERE email = '$username'");
        }
        else {
            $tmp = preg_split ('/@/', $username);
            $domain = escape_string($tmp[1]);
            $result = db_query ("INSERT INTO $table_vacation (email,subject,body,domain,created,active) VALUES ('$username','$subject','$body','$domain',NOW(),'$active')");
        }

        $ah = new AliasHandler($this->username); 
        $aliases = $ah->get(true);
        $vacation_address = $this->getVacationAlias();
        $aliases[] = $vacation_address;
        return $ah->update($aliases, '', false);
    }

    /**
     * Returns the vacation alias for this user. 
     * i.e. if this user's username was roger@example.com, and the autoreply domain was set to
     * autoreply.fish.net in config.inc.php we'd return roger#example.com@autoreply.fish.net
     * @return string an email alias.
     */
    public function getVacationAlias() {
        global $CONF;
        $vacation_domain = $CONF['vacation_domain']; 
        $vacation_goto = preg_replace('/@/', '#', $this->username); 
        $vacation_goto = "{$vacation_goto}@{$vacation_domain}"; 
        return $vacation_goto;
    }
}

/* vim: set expandtab softtabstop=4 tabstop=4 shiftwidth=4: */
