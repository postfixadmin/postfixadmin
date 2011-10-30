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
                $this->errormsg[$this->id_field] = Lang::read($this->msg['error_already_exists']);
                return false;
            } elseif (!$this->validate_id() ) {
                # errormsg filled by validate_id()
                return false;
            } else {
                return true;
            }
        } else { # edit mode
            if (!$exists) {
                $this->errormsg[$this->id_field] = Lang::read($this->msg['error_does_not_exist']);
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
            $this->errormsg[$this->id_field] = Lang::read('pAdminCreate_domain_domain_text_error2'); # TODO: half of the errormsg is currently delivered via flash_error() in check_domain
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

        # values for the "type" column:
        # text  one line of text
        # num   number
        # vnum  "virtual" number, coming from JOINs etc.
        # bool  boolean (converted to 0/1, additional column _$field with yes/no)
        # ts    timestamp (created/modified)
        # enum  list of options, must be given in column "options" as array

        # NOTE: There are dependencies between alias_count, mailbox_count and total_quota.
        # NOTE: If you disable "display in list" for one of them, the SQL query for the others might break.
        # NOTE: (Disabling all of them shouldn't be a problem.)

        $this->struct=array(
            # field name                allow       display in...   type    $PALANG label                    $PALANG description                 default / options / ...
            #                           editing?    form    list
           'domain'          => pacol(  $this->new, 1,      1,      'text', 'pAdminEdit_domain_domain'     , ''                                 ),
           'description'     => pacol(  1,          1,      1,      'text', 'pAdminEdit_domain_description', ''                                 ),
           'aliases'         => pacol(  1,          1,      1,      'num' , 'pAdminEdit_domain_aliases'    , 'pAdminEdit_domain_aliases_text'   , Config::read('aliases')   ),
           'alias_count'     => pacol(  0,          0,      1,      'vnum', ''                             , ''                                 , '', '',
               /*not_in_db*/ 0,
               /*dont_write_to_db*/ 1,
               /*select*/ 'coalesce(__alias_count,0) - coalesce(__mailbox_count,0)  as alias_count',
               /*extrafrom*/ 'left join ( select count(*) as __alias_count, domain as __alias_domain from ' . table_by_key('alias') .
                             ' group by domain) as __alias on domain = __alias_domain'),
           'mailboxes'       => pacol(  1,          1,      1,      'num' , 'pAdminEdit_domain_mailboxes'  , 'pAdminEdit_domain_mailboxes_text' , Config::read('mailboxes') ),
           'mailbox_count'   => pacol(  0,          0,      1,      'vnum', ''                             , ''                                 , '', '',
               /*not_in_db*/ 0,
               /*dont_write_to_db*/ 1,
               /*select*/ 'coalesce(__mailbox_count,0) as mailbox_count',
               /*extrafrom*/ 'left join ( select count(*) as __mailbox_count, sum(quota) as __total_quota, domain as __mailbox_domain from ' . table_by_key('mailbox') .
                             ' group by domain) as __mailbox on domain = __mailbox_domain'),
           'maxquota'        => pacol(  $quota,     $quota, $quota, 'num' , 'pAdminEdit_domain_maxquota'   , 'pAdminEdit_domain_maxquota_text'  , Config::read('maxquota')  ),
           'total_quota'     => pacol(  0,          0,      1,      'vnum', ''                             , ''                                 , '', '',
               /*not_in_db*/ 0,
               /*dont_write_to_db*/ 1,
               /*select*/ 'round(coalesce(__total_quota/' . intval(Config::read('quota_multiplier')) . ',0)) as total_quota' /*extrafrom*//* already in mailbox_count */ ),
           'quota'           => pacol(  $dom_q,     $dom_q, $dom_q, 'num' , 'pAdminEdit_domain_quota'      , 'pAdminEdit_domain_maxquota_text'  , Config::read('domain_quota_default') ),
           'transport'       => pacol(  $transp,    $transp,$transp,'enum', 'pAdminEdit_domain_transport'  , 'pAdminEdit_domain_transport_text' , Config::read('transport_default')     ,
                                                                                                                                /*options*/ $this->getTransports()     ),
           'backupmx'        => pacol(  1,          1,      1,      'bool', 'pAdminEdit_domain_backupmx'   , ''                                 ),
           'active'          => pacol(  1,          1,      1,      'bool', 'pAdminEdit_domain_active'     , ''                                 , 1                         ),
           'default_aliases' => pacol(  $this->new, $this->new, 0,  'bool', 'pAdminCreate_domain_defaultaliases', ''                            , 1,'', /*not in db*/ 1     ),
           'created'         => pacol(  0,          0,      1,      'ts',    '' /* TODO: "created" label */ , ''                                 ),
           'modified'        => pacol(  0,          0,      1,      'ts',   'pAdminList_domain_modified'   , ''                                 ),
        );

        # TODO: hook to modify $this->struct
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

                        # validate based on field type (_inp_$type)
                        $func="_inp_".$row['type'];
                        if (method_exists($this, $func) ) {
                            if (!$this->{$func}($key, $values[$key])) $valid = false;
                        } else {
                            # TODO: warning if no validation function exists?
                        }

                        # validate based on field name (_field_$fieldname)
                        $func="_field_".$key;
                        if (method_exists($this, $func) ) {
                            if (!$this->{$func}($key, $values[$key])) $valid = false;
                        }

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

    /**
     * store $this->values in the database
     * calls $this->storemore() where additional things can be done
     */
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
            if ($this->struct[$key]['dont_write_to_db'] == 1) unset ($db_values[$key]); # remove 'dont_write_to_db' columns
        }

        if ($this->new) {
            $result = db_insert($this->db_table, $db_values);
        } else {
            $result = db_update($this->db_table, $this->id_field, $this->username, $db_values);
        }
        if ($result != 1) {
            $this->errormsg[] = Lang::read($this->msg['store_error']) . "\n(" . $this->username . ")\n"; # TODO: change message + use sprintf
            return false;
        }

        $result = $this->storemore();

        if ($result) {
            db_log ($this->username, $this->msg['logname'], "");
        }
        return $result;
    }

    /**
     * called by $this->store() after storing $this->values in the database
     * can be used to update additional tables, call scripts etc.
     */
    protected function storemore() {
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

        if ($this->new) {
            if (!domain_postcreation($this->username)) {
                $this->errormsg[] = Lang::read('pAdminCreate_domain_error');
            }
        } else {
            # we don't have domain_postedit()
        }
        return true; # TODO: don't hardcode
    }

    /**
     * read_from_db
     * @param array or string - condition (an array will be AND'ed using db_where_clause, a string will be directly used)
     * @return array - rows
     */
    protected function read_from_db($condition) {
        $select_cols = array();

        $yes = escape_string(Lang::read('YES'));
        $no  = escape_string(Lang::read('NO'));

        # TODO: replace hardcoded %Y-%m-%d with a country-specific date format via *.lang?
        # TODO: (not too easy because pgsql uses a different formatstring format :-/ )
        if (Config::read('database_type') == 'pgsql') {
            $formatted_date = "TO_DATE(text(###KEY###), 'YYYY-mm-dd')";
        } else {
            $formatted_date = "DATE_FORMAT(###KEY###, '%Y-%m-%d')";
        }

        $colformat = array(
            'ts' => "$formatted_date AS ###KEY###, ###KEY### AS _###KEY###",
            'bool' => "CASE ###KEY### WHEN '" . db_get_boolean(true) . "' THEN '1'    WHEN '" . db_get_boolean(false) . "' THEN '0'   END as ###KEY###," .
                      "CASE ###KEY### WHEN '" . db_get_boolean(true) . "' THEN '$yes' WHEN '" . db_get_boolean(false) . "' THEN '$no' END as _###KEY###",
        );

        # get list of fields to display
        $extrafrom = "";
        foreach($this->struct as $key=>$row) {
            if ( $row['display_in_list'] != 0 && $row['not_in_db'] == 0 ) {
                if ($row['select'] != '') $key = $row['select'];

                if ($row['extrafrom'] != '') $extrafrom = $extrafrom . " " . $row['extrafrom'] . "\n";

                if (isset($colformat[$row['type']])) {
                    $select_cols[] = str_replace('###KEY###', $key, $colformat[$row['type']] );
                } else {
                    $select_cols[] = $key;
                }

            }
        }

        $cols = join(',', $select_cols);
        $table = table_by_key($this->db_table);

        if (is_array($condition)) {
            $where = db_where_clause($condition, $this->struct);
        } else {
            $where = " WHERE $condition ";
        }

        $query = "SELECT $cols FROM $table $extrafrom $where ORDER BY " . $this->id_field;
        $result = db_query($query);

        $db_result = array();
        if ($result['rows'] != 0) {
            while ($row = db_assoc ($result['result'])) {
                $db_result[] = $row;
            }
        }

        return $db_result;
    }

    /**
     * get the settings of a domain
     * @param array or string $condition
     * @return bool - true if at least one domain was found
     * The data is stored in $this->return (as associative array of column => value)
     */
    public function view($errors=true) {
        $result = $this->read_from_db(array($this->id_field => $this->username) );
        if (count($result) == 1) {
            $this->return = $result[0];
            return true;
        }

        if ($errors) $this->errormsg[] = Lang::read($this->msg['error_does_not_exist']);
#        $this->errormsg[] = $result['error'];
        return false;
    }

    /**
     * get a list of one or more domains with all settings
     * @param array or string $condition
     * @return bool - true if at least one domain was found
     * The data is stored in $this->return (as array of rows, each row is an associative array of column => value)
     */
    public function getList($condition) {
        $result = $this->read_from_db($condition);
        if (count($result) >= 1) {
            $this->return = $result;
            return true;
        }

#        $this->errormsg[] = Lang::read($this->msg['error_does_not_exist']);
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
