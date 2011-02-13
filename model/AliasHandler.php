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
        $this->username = strtolower($username);
    }

    /**
     * @return array - list of email addresses the user's mail is forwarded to.
     * (may be an empty list, especially if $CONF['alias_control'] is turned off...)
     * @param boolean - by default we don't return special addresses (e.g. vacation and mailbox alias); pass in true here if you wish to.
     */
    public function get($all=false) {
        $username = escape_string($this->username);
        $table_alias = table_by_key('alias');

        $sql = "SELECT * FROM $table_alias WHERE address='$username'";
        $result = db_query($sql);
        if($result['rows'] != 1) {
            return false;
        }

        $row = db_array ($result['result']);
        // At the moment Postfixadmin stores aliases in it's database in a comma seperated list; this may change one day.
        $list = explode(',', $row['goto']);
        if($all) {
            $this->return = $list;
            return true;
        }

        $filtered_list = array();
        /* if !$all, remove vacation & mailbox aliases */
        foreach($list as $address) {
            if($address != '' ) {
                if($this->is_vacation_address($address) || $this->is_mailbox_alias($address)) {
                    # TODO: store "vacation_active" and "mailbox" status - should be readable public
                }
                else {
                    $filtered_list[] = $address;
                }
            }
        }
        $this->return = $filtered_list;
        return true;
    }

   /** 
    * @param string $address
    * @param string $username
    * @return boolean true if the username is an alias for the mailbox AND we have alias_control turned off.
    * TODO: comment for @return: does alias_control really matter here?
    */
    public function is_mailbox_alias($address) {
        global $CONF;

        if($address != $this->username) { # avoid false positives if $address is a mailbox
            return false;
        }

        $table_mailbox = table_by_key('mailbox');
        $E_address = escape_string($address);
        $sql = "SELECT * FROM $table_mailbox WHERE username='$E_address'";
        $result = db_query($sql);
        if($result['rows'] != 1) {
           return false;
        } else { 
           return true;
        }
    }

    /**
     * @param string $address
     * @return boolean true if the address contains the vacation domain
     */
    public function is_vacation_address($address) {
        global $CONF;
        if($CONF['vacation'] == 'YES') {
            if(stripos($address, '@' . $CONF['vacation_domain'])) { # TODO: check full vacation address user#domain.com@vacation_domain
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
     * Set the user's aliases to those provided. If $addresses ends up being empty the alias record is removed. # TODO: deleting that's buggy behaviour, error out instead
     */
    public function update($addresses, $flags = '', $vacation_persist=true) {
        // find out if the user is on vacation or not; if they are, 
        // then the vacation alias needs adding to the db (as we strip it out in the get method) 
        // likewise with the alias_control address.

        # TODO: move all validation from edit-alias/create-alias and users/edit-alias here

        $valid_flags = array('', 'forward_and_store', 'remote_only');
        if(!in_array($flags, $valid_flags)) {
            die("Invalid flag passed into update()... : $flag - valid options are :" . implode(',', $valid_flags));
        } 
        $addresses = array_unique($addresses);

        list (/*NULL*/, $domain) = explode('@', $this->username);

        if ( ! $this->get(true) ) die("Alias not existing?"); # TODO: better error behaviour

        foreach($this->return as $address) {
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
            foreach($addresses as $address) { # TODO: write a remove_from_array function, see http://tech.petegraham.co.uk/2007/03/22/php-remove-values-from-array/
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
                $new_list[] = $address; # TODO use remove_from_array, see above
            }
        } 
        $addresses = array_unique($new_list);
        $E_username = escape_string($this->username);
        $goto = implode(',', $addresses);
        if(sizeof($addresses) == 0) {
            # $result = db_delete('alias', 'address', $this->username); # '"DELETE FROM $table_alias WHERE address = '$username'"; # TODO: should never happen and causes broken behaviour
            error_log("Alias set to empty / Attemp to delete: " . $this->username); # TODO: more/better error handling - maybe just return false?
        }
        if($this->hasAliasRecord() == false) { # TODO should never happen in update() - see also the comments on handling DELETE above
            $alias_data = array(
                'address'   => $this->username,
                'goto'      => $goto,
                'domain'    => $domain,
                'active'    => db_get_boolean(True),
            );
            $result = db_insert('alias', $alias_data);
        } else {
            $alias_data = array(
                'goto' => $goto,
            );
            $result = db_update('alias', 'address', $this->username, $alias_data);
        }
        if($result != 1) {
            return false;
        }
        db_log($this->username, $domain, 'edit_alias', "$E_username -> $goto");
        return true;
    }

    /** 
     * Determine whether a local delivery address is present. This is 
     * stores as an alias with the same name as the mailbox name (username)
     * @return boolean true if local delivery is enabled
     */
    public function hasStoreAndForward() {
        $result = $this->get(true); # TODO: error checking?
        if(in_array($this->username, $this->return)) {
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
    
    /**
     *  @param alias address
     *  @return true on success false on failure
     */
    public function delete(){
        if( ! $this->get() ) {
            $this->errormsg[] = 'An alias with that address does not exist.'; # TODO: make translatable
            return false;
        }

        if ($this->is_mailbox_alias($this->username) ) {
            $this->errormsg[] = 'This alias belongs to a mailbox and can\'t be deleted.'; # TODO: make translatable
            return false;
        }

        $result = db_delete('alias', 'address', $this->username);
        if( $result == 1 ) {
            list(/*NULL*/,$domain) = explode('@', $this->username);
            db_log ('CLI', $domain, 'delete_alias', $this->username); # TODO: replace hardcoded CLI
            return true;
        }
    }

    /**
     * @return return value of previously called method
     */
    public function result() {
        return $this->return;
    }
 }

/* vim: set expandtab softtabstop=4 tabstop=4 shiftwidth=4: */
