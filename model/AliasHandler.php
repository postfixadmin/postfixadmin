<?php

/** 
 * Handlers User level alias actions - e.g. add alias, get aliases, update etc.
 */
class AliasHandler {

    private $username = null;

    /**
     * @param string $username
     */
    public function __construct($username) {
        $this->username = $username;
    }

    /**
     * @return array - list of email addresses the user's mail is forwarded to.
     * (may be an empty list, especially if $CONF['alias_control'] is turned off...
     * @param boolean - by default we don't return special addresses (e.g. vacation and mailbox alias); pass in true here if you wish to.
     */
    public function get($all=false) {
        $username = escape_string($this->username);
        $table_alias = table_by_key('alias');

        $sql = "SELECT * FROM $table_alias WHERE address='$username'";
        $result = db_query($sql);
        if($result['rows'] == 1) {
            $row = db_array ($result['result']);
            // At the moment Postfixadmin stores aliases in it's database in a comma seperated list; this may change one day.
            $list = explode(',', $row['goto']);
            if($all) {
                return $list;
            }

            $new_list = array();
            /* if !$all, remove vacation & mailbox aliases */
            foreach($list as $address) {
                if($address != '' ) {
                    if($this->is_vacation_address($address) || $this->is_mailbox_alias($address)) {
                    }
                    else {
                        $new_list[] = $address;
                    }
                }
            }
            $list = $new_list;
            return $list;
        }
        return array();
    }

   /** 
    * @param string $address
    * @param string $username
    * @return boolean true if the username is an alias for the mailbox AND we have alias_control turned off.
    */
    public function is_mailbox_alias($address) {
        global $CONF;
        $username = $this->username;
        if($address == $username) {
            return true;
        }
        return false;
    }

    /**
     * @param string $address
     * @return boolean true if the address contains the vacation domain
     */
    public function is_vacation_address($address) {
        global $CONF;
        if($CONF['vacation'] == 'YES') {
            if(stripos($address, '@' . $CONF['vacation_domain'])) {
                return true;
            }
        }
        return false;
    }
    /**
     * @return boolean true on success
     * @param string $username
     * @param array $addresses - list of aliases to set for the user.
     * @param string flags - forward_and_store or remote_only or ''
     * @param boolean $vacation_persist - set to false to stop the vacation address persisting across updates
     * Set the user's aliases to those provided. If $addresses ends up being empty the alias record is removed.
     */
    public function update($addresses, $flags = '', $vacation_persist=true) {
        // find out if the user is on vacation or not; if they are, 
        // then the vacation alias needs adding to the db (as we strip it out in the get method) 
        // likewise with the alias_control address.

        $valid_flags = array('', 'forward_and_store', 'remote_only');
        if(!in_array($flags, $valid_flags)) {
            die("Invalid flag passed into update()... : $flag - valid options are :" . implode(',', $valid_flags));
        } 
        $addresses = array_unique($addresses);

        $original = $this->get(true);
        $tmp = preg_split('/@/', $this->username);
        $domain = $tmp[1];

        foreach($original as $address) {
            if($vacation_persist) {
                if($this->is_vacation_address($address)) {
                    $addresses[] = $address;
                }
            }
            if($flags != 'remote_only') {
                if($this->is_mailbox_alias($address)) {
                    $addresses[] = $address;
                }
            }
        }
        $addresses = array_unique($addresses);

        $new_list = array();
        if($flags == 'remote_only') {
            foreach($addresses as $address) {
                // strip out our username... if it's in the list given.
                if($address != $this->username) {
                    $new_list[] = $address;            
                }
            }
            $addresses = $new_list;
        }
        
        if($flags == 'forward_and_store') {
            if(!in_array($this->username, $addresses)) {
                $addresses[] = $this->username;
            }
        }
        $new_list = array();
        foreach($addresses as $address) {
            if($address != '') {
                $new_list[] = $address;
            }
        } 
        $addresses = array_unique($new_list);
        $username = escape_string($this->username);
        $goto = escape_string(implode(',', $addresses));
        $table_alias = table_by_key('alias');
        if(sizeof($addresses) == 0) {
            $sql = "DELETE FROM $table_alias WHERE address = '$username'";
        }
        if($this->hasAliasRecord() == false) {
            $true = db_get_boolean(True);
            $sql = "INSERT INTO $table_alias (address, goto, domain, created, modified, active) VALUES ('$username', '$goto', '$domain', NOW(), NOW(), '$true')";
        }
        else {
            $sql = "UPDATE $table_alias SET goto = '$goto', modified = NOW() WHERE address = '$username'";
        }
        $result = db_query($sql);
        if($result['rows'] != 1) {
            return false;
        }
        db_log($username, $domain, 'edit_alias', "$username -> $goto");
        return true;
    }

    /** 
     * Determine whether a local delivery address is present. This is 
     * stores as an alias with the same name as the mailbox name (username)
     * @return boolean true if local delivery is enabled
     */
    public function hasStoreAndForward() {
        $aliases = $this->get(true);
        if(in_array($this->username, $aliases)) {
            return true;
        }
        return false;
    }

    /**
     * @return boolean true if the user has an alias record (i.e row in alias table); else false.
     */
    public function hasAliasRecord() {
        $username = escape_string($this->username);
        $table_alias = table_by_key('alias');
        $sql = "SELECT * FROM $table_alias WHERE address = '$username'";
        $result = db_query($sql);
        if($result['rows'] == 1) {
            return true;
        }
        return false;
    }
}

/* vim: set expandtab softtabstop=4 tabstop=4 shiftwidth=4: */
