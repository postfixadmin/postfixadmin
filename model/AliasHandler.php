<?php
# $Id$ 

/** 
 * Handlers User level alias actions - e.g. add alias, get aliases, update etc.
 * @property $username name of alias
 * @property $return return of methods
 */
class AliasHandler extends PFAHandler {

    protected $domain_field = 'domain';
    
    /**
     *
     * @public
     */
    public $return = null;

    protected function initStruct() {
        $this->db_table = 'alias';
        $this->id_field = 'address';

        $this->struct=array(
            # field name                allow       display in...   type    $PALANG label                     $PALANG description                 default / ...
            #                           editing?    form    list
            'address'       => pacol(   $this->new, 1,      1,      'mail', 'pEdit_alias_address'           , 'pCreate_alias_catchall_text'     ),
            'localpart'     => pacol(   $this->new, 0,      0,      'text', 'pEdit_alias_address'           , 'pCreate_alias_catchall_text'     , '', 
                /*options*/ '', 
                /*not_in_db*/ 1                         ),
            'domain'        => pacol(   $this->new, 0,      0,      'enum', ''                              , ''                                , '', 
                /*options*/ $this->allowed_domains      ),
            'goto'          => pacol(   1,          1,      1,      'txtl', 'pEdit_alias_goto'              , 'pEdit_alias_help'                ),
            'on_vacation'   => pacol(   1,          0,      1,      'bool', ''                              , ''                                , 0 ,
                /*options*/ '', 
                /*not_in_db*/ 1                         ),

# target (forwardings)
# is_mailbox (alias belongs to mailbox)
# mailbox_target (is_mailbox and mailbox is (part of the) target
# vacation (active? 0/1)
            'active'        => pacol(   1,          1,      1,      'bool', 'pAdminEdit_domain_active'      , ''                                , 1     ),
            'created'       => pacol(   0,          0,      1,      'ts',   'created'                       , ''                                ),
            'modified'      => pacol(   0,          0,      1,      'ts',   'pAdminList_domain_modified'    , ''                                ),
        );
    }

    protected function initMsg() {
        $this->msg['error_already_exists'] = 'pCreate_alias_address_text_error2';
        $this->msg['error_does_not_exist'] = 'pCreate_alias_address_text_error1'; # TODO: better error message
        if ($this->new) {
            $this->msg['logname'] = 'create_alias';
            $this->msg['store_error'] = 'pCreate_alias_result_error';
        } else {
            $this->msg['logname'] = 'edit_alias';
            $this->msg['store_error'] = 'pEdit_alias_result_error';
        }
    }


    public function webformConfig() {
        if ($this->new) { # the webform will display a localpart field + domain dropdown on $new
            $this->struct['address']['display_in_form'] = 0;
            $this->struct['localpart']['display_in_form'] = 1;
            $this->struct['domain']['display_in_form'] = 1;
        }

        return array(
            # $PALANG labels
            'formtitle_create'  => 'pCreate_alias_welcome',
            'formtitle_edit'    => 'pEdit_alias_welcome',
            'create_button'     => 'pCreate_alias_button',
            'successmessage'    => 'pCreate_alias_result_success', # TODO: better message for edit

            # various settings
            'required_role' => 'admin',
            'listview' => 'list-virtual.php',
            'early_init' => 0,
        );
    }


    public function init($id) {
        @list($local_part,$domain) = explode ('@', $id); # supress error message if $id doesn't contain '@'

        if ($local_part == '*') { # catchall - postfix expects '@domain', not '*@domain'
            $id = '@' . $domain;
        }

        return parent::init($id);
    }

    protected function validate_new_id() {
        if ($this->id == '') {
            $this->errormsg[] = Lang::read('pCreate_alias_address_text_error1');
            return false;
        }

        list($local_part,$domain) = explode ('@', $this->id);

        if(!$this->create_allowed($domain)) {
            $this->errormsg[] = Lang::read('pCreate_alias_address_text_error3');
            return false;
        }
 
        # TODO: already checked in set() - does it make sense to check it here also? Only advantage: it's an early check
#        if (!in_array($domain, $this->allowed_domains)) { 
#            $this->errormsg[] = Lang::read('pCreate_alias_address_text_error1');
#            return false;
#        }

        if ($local_part == '') { # catchall
            $valid = true;
        } else {
            $valid = check_email($this->id); # TODO: check_email should return error message instead of using flash_error itsself
        }

        return $valid;
    }

    /**
     * check number of existing aliases for this domain - is one more allowed?
     */
    private function create_allowed($domain) {
        $limit = get_domain_properties ($domain);

        if ($limit['aliases'] == 0) return true; # unlimited
        if ($limit['aliases'] < 0) return false; # disabled
        if ($limit['alias_count'] >= $limit['aliases']) return false;
        return true;
    }


   /**
    * merge localpart and domain to address
    * called by edit.php (if id_field is editable and hidden in editform) _before_ ->init
    */
    public function mergeId($values) {
        if ($this->struct['localpart']['display_in_form'] == 1 && $this->struct['domain']['display_in_form']) { # webform mode - combine to 'address' field
            if (empty($values['localpart']) || empty($values['domain']) ) { # localpart or domain not set
                return "";
            }
            if ($values['localpart'] == '*') $values['localpart'] = ''; # catchall
            return $values['localpart'] . '@' . $values['domain'];
        } else {
            return $values[$this->id_field];
        }
    }

    protected function setmore($values) {
        if ($this->new) {
            if ($this->struct['address']['display_in_form'] == 1) { # default mode - split off 'domain' field from 'address' # TODO: do this unconditional?
                list(/*NULL*/,$domain) = explode('@', $values['address']);
                $this->values['domain'] = $domain;
            }
        }

        $this->values['goto'] = join(',', $values['goto']); # TODO: add mailbox and vacation aliases
    }

    protected function read_from_db_postprocess($db_result) {
        foreach ($db_result as $key => $value) {
            $db_result[$key]['goto'] = explode(',', $db_result[$key]['goto']);

            $vh = new VacationHandler($this->id);
            $vacation_alias = $vh->getVacationAlias(); # TODO: move getVacationAlias to functions.inc.php to avoid the need
                                                       # for lots of VacationHandler instances (performance)?
            list($db_result[$key]['on_vacation'], $db_result[$key]['goto']) = remove_from_array($db_result[$key]['goto'], $vacation_alias);
        }
#print_r($db_result); exit;
        return $db_result;
    }

/* delete is already implemented in the "old functions" section
    public function delete() {
        $this->errormsg[] = '*** Alias domain deletion not implemented yet ***';
        return false; # XXX function aborts here until TODO below is implemented! XXX
        # TODO: move the needed code from delete.php here
    }
*/

    protected function _field_goto($field, $val) {
        if (count($val) == 0) {
            # TODO: empty is ok for mailboxes - mailbox alias is in a separate field
            $this->errormsg[$field] = 'empty goto'; # TODO: better error message
            return false;
        }

        $errors = array();

        foreach ($val as $singlegoto) {
            if (substr($singlegoto, 0, 1) == '@') { # domain-wide forward - check only the domain part
                # Note: alias domains are better, but we should keep this way supported for backward compatibility
                #       and because alias domains can't forward to external domains
                list (/*NULL*/, $domain) = explode('@', $singlegoto);
                if (!check_domain($domain)) {
                     $errors[] = "invalid: $singlegoto"; # TODO: better error message
                }
            } elseif (!check_email($singlegoto)) {
                $errors[] = "invalid: $singlegoto"; # TODO: better error message
            }
        }

        if (count($errors)) {
            $this->errormsg[$field] = join("   ", $errors);
            return false;
        } else {
            return true;
        }
    }

/**********************************************************************************************************************************************************
  old function from non-PFAHandler times of AliasHandler
  They still work, but are deprecated and will be removed.
 **********************************************************************************************************************************************************/

    /**
     * @return bool true if succeed
     * (may be an empty list, especially if $CONF['alias_control'] is turned off...)
     * @param boolean - by default we don't return special addresses (e.g. vacation and mailbox alias); pass in true here if you wish to.
     */
    public function get($all=false) {
        $E_username = escape_string($this->id);
        $table_alias = table_by_key('alias');

        $sql = "SELECT * FROM $table_alias WHERE address='$E_username'";
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

        if($address != $this->id) { # avoid false positives if $address is a mailbox
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

        list (/*NULL*/, $domain) = explode('@', $this->id);

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
                if($address != $this->id) {
                    $new_list[] = $address;            
                }
            }
            $addresses = $new_list;
        }
        
        if($flags == 'forward_and_store') {
            if(!in_array($this->id, $addresses)) {
                $addresses[] = $this->id;
            }
        }
        $new_list = array();
        foreach($addresses as $address) {
            if($address != '') {
                $new_list[] = $address; # TODO use remove_from_array, see above
            }
        } 
        $addresses = array_unique($new_list);
        $E_username = escape_string($this->id);
        $goto = implode(',', $addresses);
        if(sizeof($addresses) == 0) {
            # $result = db_delete('alias', 'address', $this->id); # '"DELETE FROM $table_alias WHERE address = '$username'"; # TODO: should never happen and causes broken behaviour
            error_log("Alias set to empty / Attemp to delete: " . $this->id); # TODO: more/better error handling - maybe just return false?
        }
        if($this->hasAliasRecord() == false) { # TODO should never happen in update() - see also the comments on handling DELETE above
            $alias_data = array(
                'address'   => $this->id,
                'goto'      => $goto,
                'domain'    => $domain,
                'active'    => db_get_boolean(True),
            );
            $result = db_insert('alias', $alias_data);
        } else {
            $alias_data = array(
                'goto' => $goto,
            );
            $result = db_update('alias', 'address', $this->id, $alias_data);
        }
        if($result != 1) {
            return false;
        }
        db_log ($domain, 'edit_alias', "$E_username -> $goto");
        return true;
    }

    /** 
     * Determine whether a local delivery address is present. This is 
     * stores as an alias with the same name as the mailbox name (username)
     * @return boolean true if local delivery is enabled
     */
    public function hasStoreAndForward() {
        $result = $this->get(true); # TODO: error checking?
        if(in_array($this->id, $this->return)) {
            return true;
        }
        return false;
    }

    /**
     * @return boolean true if the user has an alias record (i.e row in alias table); else false.
     */
    private function hasAliasRecord() { # only used by update() in this class
        $username = escape_string($this->id);
        $table_alias = table_by_key('alias');
        $sql = "SELECT * FROM $table_alias WHERE address = '$username'";
        $result = db_query($sql);
        if($result['rows'] == 1) {
            return true;
        }
        return false;
    }
    
    /**
     *  @return true on success false on failure
     */
    public function delete(){
        if( ! $this->get() ) {
            $this->errormsg[] = 'An alias with that address does not exist.'; # TODO: make translatable
            return false;
        }

        if ($this->is_mailbox_alias($this->id) ) {
            $this->errormsg[] = 'This alias belongs to a mailbox and can\'t be deleted.'; # TODO: make translatable
            return false;
        }

        $result = db_delete('alias', 'address', $this->id);
        if( $result == 1 ) {
            list(/*NULL*/,$domain) = explode('@', $this->id);
            db_log ($domain, 'delete_alias', $this->id);
            return true;
        }
    }

 }

/* vim: set expandtab softtabstop=4 tabstop=4 shiftwidth=4: */
