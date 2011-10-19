<?php
# $Id$

/**
 * Handlers User level alias actions - e.g. add alias, get aliases, update etc.
 */
class DomainHandler extends PFAHandler {

    protected $username = null; # actually it's the domain - variable name kept for consistence with the other classes
    protected $db_table = null;
    protected $id_field = null;
    protected $struct = array();
    protected $new = 0; # 1 on create, otherwise 0
    protected $values = array();
    protected $values_valid = false;

    public $errormsg = array();

    # messages used in various functions
    # (stored separately to make the functions reuseable)
    protected $msg = array();

    /**
     * Constructor: fill $struct etc.
     * @param string $new
     */
    public function __construct($new = 0) {
        if ($new) $this->new = 1;
        $this->initStruct();
        $this->initMsg();
    }

    /**
     * initialize with $username and check if it is valid
     * @param string $username
     */
    public function init($username) {
        $this->username = strtolower($username);

        $exists = $this->view(false);

        if ($this->new) {
            if ($exists) {
                $this->errormsg[] = Lang::read($this->msg['error_already_exists']);
                return false;
            } elseif (!$this->validate_id() ) {
                # errormsg filled by validate_id()
                return false;
            } else {
                return true;
            }
        } else { # edit mode
            if (!$exists) {
                $this->errormsg[] = Lang::read($this->msg['error_does_not_exist']);
                return false;
            } else {
                return true;
            }
        }
    }

   protected function validate_id() {
       $valid = check_domain($this->username);

       if ($valid) {
            return true;
       } else {
            $this->errormsg[] = Lang::read('pAdminCreate_domain_domain_text_error2'); # TODO: half of the errormsg is currently delivered via flash_error() in check_domain
            return false;
       }
   }

    # init $this->struct, $this->db_table and $this->id_field
    protected function initStruct() {
        $this->db_table = 'domain';
        $this->id_field = 'domain';

        # TODO: shorter PALANG labels ;-)
        # TODO: hardcode 'default' to Config::read in pacol()?

        $transp = boolconf('transport')     ? 1 : 0; # TOOD: use a function or write a Config::intbool function
        $quota  = boolconf('quota')         ? 1 : 0; # TOOD: use a function or write a Config::intbool function
        $dom_q  = boolconf('domain_quota')  ? 1 : 0; # TOOD: use a function or write a Config::intbool function

        $this->struct=array(
            # field name                allow       display in...   type    $PALANG label                    $PALANG description                 default / options / not in database
            #                           editing?    form    list
           'domain'          => pacol(  $this->new, 1,      1,      'text', 'pAdminEdit_domain_domain'     , ''                                 ),
           'description'     => pacol(  1,          1,      1,      'text', 'pAdminEdit_domain_description', ''                                 ),
           'aliases'         => pacol(  1,          1,      1,      'num' , 'pAdminEdit_domain_aliases'    , 'pAdminEdit_domain_aliases_text'   , Config::read('aliases')   ),
           'mailboxes'       => pacol(  1,          1,      1,      'num' , 'pAdminEdit_domain_mailboxes'  , 'pAdminEdit_domain_mailboxes_text' , Config::read('mailboxes') ),
           'maxquota'        => pacol(  $quota,     $quota, $quota, 'num' , 'pAdminEdit_domain_maxquota'   , 'pAdminEdit_domain_maxquota_text'  , Config::read('maxquota')  ),
           'quota'           => pacol(  $dom_q,     $dom_q, $dom_q, 'num' , 'pAdminEdit_domain_quota'      , 'pAdminEdit_domain_maxquota_text'  , Config::read('domain_quota_default') ),
           'transport'       => pacol(  $transp,    $transp,$transp,'enum', 'pAdminEdit_domain_transport'  , 'pAdminEdit_domain_transport_text' , Config::read('transport_default')     ,
                                                                                                                                /*options*/ $this->getTransports()     ),
           'backupmx'        => pacol(  1,          1,      1,      'bool', 'pAdminEdit_domain_backupmx'   , ''                                 ),
           'active'          => pacol(  1,          1,      1,      'bool', 'pAdminEdit_domain_active'     , ''                                 , 1                         ),
           'default_aliases' => pacol(  $this->new, 1,      0,      'bool', 'pAdminCreate_domain_defaultaliases ', ''                           , 1,'', /*not in db*/ 1     ),
           'created'         => pacol(  0,          0,      1,      'ts',    '' /* TODO: "created" label */ , ''                                 ),
           'modified'        => pacol(  0,          0,      1,      'ts',   'pAdminList_domain_modified'   , ''                                 ),
        );

    }

    # messages used in various functions.
    # always list the key to hand over to Lang::read
    # the only exception is 'logname' which uses the key for db_log
    protected function initMsg() {
        $this->msg['error_already_exists'] = 'pAdminCreate_domain_domain_text_error';
        $this->msg['error_does_not_exist'] = 'domain_does_not_exist';
        if ($this->new) {
            $this->msg['logname'] = 'create_domain';
            $this->msg['store_error'] = 'pAdminCreate_domain_result_error';
        } else {
            $this->msg['logname'] = 'edit_domain';
            $this->msg['store_error'] = 'pAdminEdit_domain_result_error';
        }
    }

    public function getStruct() {
        return $this->struct;
    }

    public function getId_field() {
        return $this->id_field;
    }

    public function getTransports() {
        return Config::read('transport_options');
    }

    # TODO: specific for CLI? If yes, move to CLI code
    public function getTransport($id) {
        $transports = Config::read('transport_options');
        return $transports[$id-1];
    }

    public function set($values) {
        # TODO: make this a generic function for add and edit

        if ($this->new == 1) {
            $values[$this->id_field] = $this->username;
        }

        # base validation
        $this->values = array();
        $this->values_valid = false;
        foreach($this->struct as $key=>$row) {
            if ($row['editable'] == 0) { # not editable
                if ($this->new == 1) {
                    $this->values[$key] = $row['default'];
                }
            } else {
                if (isset($values[$key])) {
                    if ($row['type'] != "password" || strlen($values[$key]) > 0 || $this->new == 1) { # skip on empty (aka unchanged) password on edit
                        $valid = true; # trust input unless validator objects

                        $func="_inp_".$row['type'];
                        if (method_exists($this, $func) ) {
                            if (!$this->{$func}($key, $values[$key])) $valid = false;
                        } else {
                            # TODO: warning if no validation function exists?
                        }

                        # TODO: more validation (_field_$fieldname() ?)

                        if ($valid) {
                            $this->values[$key] = $values[$key];
                        }
                    }
                } elseif ($this->new) { # new, field not set in input data
                    $this->errormsg[] = "field $key is missing";
                    # echo "MISSING / not set: $key\n";
                } else { # edit, field unchanged
                    # echo "skipped / not set: $key\n";
                }
            }
        }

        if (count($this->errormsg) == 0) {
            $this->values_valid = true;
        }
        return $this->values_valid;
    }

    public function store() {
        if ($this->values_valid == false) {
            $this->errormsg[] = "one or more values are invalid!";
            return false;
        }

        $db_values = $this->values;

        foreach(array_keys($db_values) as $key) {
            switch ($this->struct[$key]['type']) { # modify field content for some types
                case 'bool':
                    $db_values[$key] = db_get_boolean($db_values[$key]);
                    break;
                # TODO: passwords -> pacrypt()
            }
            if ($this->struct[$key]['not_in_db'] == 1) unset ($db_values[$key]); # remove 'not in db' columns
        }

        if ($this->new) {
            $result = db_insert($this->db_table, $db_values);
        } else {
            $result = db_update($this->db_table, $this->id_field, $this->username, $db_values);
        }
        if ($result != 1) {
            $this->errormsg[] = Lang::read($this->msg['store_error']) . "\n(" . $this->username . ")\n"; # TODO: change message + use sprintf
            return false;
        } else {
# TODO: drop the "else {" - if $result != 1, the "return false" will already exit the function
# TODO: everything after this comment (= specific to domains) should be a separate function,
# TODO: because everything above is generic "write values to DB" code
            if ($this->new && $this->values['default_aliases']) {
                foreach (Config::read('default_aliases') as $address=>$goto) {
                    $address = $address . "@" . $this->username;
                    # TODO: use AliasHandler->add instead of writing directly to the alias table
                    $arr = array(
                        'address' => $address,
                        'goto' => $goto,
                        'domain' => $this->username,
                    );
                    $result = db_insert ('alias', $arr);
                    # TODO: error checking
                }
            }
            if ($this->new) {
                $tMessage = Lang::read('pAdminCreate_domain_result_success') . " (" . $this->username . ")"; # TODO: tMessage is not used/returned anywhere
            } else {
                # TODO: success message for edit
            }
        }

        if ($this->new) {
            if (!domain_postcreation($this->username)) {
                $this->errormsg[] = Lang::read('pAdminCreate_domain_error');
            }
        } else {
            # we don't have domain_postedit()
        }
        db_log ($this->username, $this->msg['logname'], "");
        return true;
    }

    public function view($errors=true) {
        $select_cols = array();
        $bool_fields = array();

        # get list of fields to display
        foreach($this->struct as $key=>$row) {
            if ( $row['display_in_list'] != 0 && $row['not_in_db'] == 0 ) {
                if ($row['type'] == 'ts') {
                    # TODO: replace hardcoded %Y-%m-%d with a country-specific date format via *.lang?
                    $select_cols[] = "DATE_FORMAT($key, '%Y-%m-%d') AS $key, $key AS _$key"; # timestamps formatted as date, raw data in _fieldname
                } elseif ($row['type'] == 'bool') {
                    $bool_fields[] = $key; # remember boolean fields (will be converted to integer 0/1 later)  - TODO: do this in the sql query with CASE?
                    $select_cols[] = $key;
                } else {
                    $select_cols[] = $key;
                }
            }
        }

        $cols = join(',', $select_cols);
        $table = table_by_key($this->db_table);
        $id_field = $this->id_field;
        $E_username = escape_string($this->username);

        $result = db_query("SELECT $cols FROM $table WHERE $id_field='$E_username'");
        if ($result['rows'] != 0) {
            $this->return = db_array($result['result']);
            foreach ($bool_fields as $field) {
                $this->return[$field] = db_boolean_to_int($this->return[$field]); # convert bool to integer (0/1)
            }
            return true;
        }
        if ($errors) $this->errormsg[] = Lang::read($this->msg['error_does_not_exist']);
#        $this->errormsg[] = $result['error'];
        return false;
    }
    /**
     *  @return true on success false on failure
     */
    public function delete() {
        if ( ! $this->view() ) {
            $this->errormsg[] = 'A domain with that name does not exist.'; # TODO: make translatable
            return false;
        }

        $this->errormsg[] = '*** Domain deletion not implemented yet ***';
        return false; # XXX function aborts here until TODO below is implemented! XXX

        # TODO: recursively delete mailboxes, aliases, alias_domains, fetchmail entries etc. before deleting the domain
        # TODO: move the needed code from delete.php here
        $result = db_delete($this->db_table, $this->id_field, $this->username);
        if ( $result == 1 ) {
            list(/*NULL*/,$domain) = explode('@', $this->username);
            db_log ($domain, 'delete_domain', $this->username); # TODO delete_domain is not a valid db_log keyword yet because we don't yet log add/delete domain
            return true;
        }
    }

}

/* vim: set expandtab softtabstop=4 tabstop=4 shiftwidth=4: */
