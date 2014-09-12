<?php
abstract class PFAHandler {

    /**
     * public variables
     */

    # array of error messages - if a method returns false, you'll find the error message(s) here
    public $errormsg = array();

    # array of info messages (for example success messages)
    public $infomsg = array();

    # array of tasks available in CLI
    public $taskNames = array('Help', 'Add', 'Update', 'Delete', 'View');

    /**
     * variables that must be defined in all *Handler classes
     */

    # (default) name of the database table
    # (can be overridden by $CONF[database_prefix] and $CONF[database_tables][*] via table_by_key())
    protected $db_table = null;

    # field containing the ID
    protected $id_field = null;

    # column containing the domain
    # if a table does not contain a domain column, leave empty and override no_domain_field())
    protected $domain_field = "";

    # skip empty password fields in edit mode
    # enabled by default to allow changing an admin, mailbox etc. without changing the password
    # disable for "edit password" forms
    protected $skip_empty_pass = true;

    /**
     * internal variables - filled by methods of *Handler
     */

    # if $domain_field is set, this is an array with the domain list
    # set in __construct()
    protected $allowed_domains = false;

    # if set, restrict $allowed_domains to this admin
    # set in __construct()
    protected $admin_username = "";


    # the ID of the current item (where item can be an admin, domain, mailbox, alias etc.)
    # filled in init()
    protected $id = null;

    # the domain of the current item (used for logging)
    # filled in domain_from_id() via init()
    protected $domain = null;

    # structure of the database table, list, edit form etc.
    # filled in initStruct()
    protected $struct = array();

    # new item or edit existing one?
    # set in __construct()
    protected $new = 0; # 1 on create, otherwise 0

    # validated values
    # filled in set()
    protected $values = array();

    # unchecked (!) input given to set() - use it carefully!
    # filled in set(), can be modified by _missing_$field()
    protected $RAWvalues = array();

    # are the values given to set() valid?
    # set by set(), checked by store()
    protected $values_valid = false;

    # messages used in various functions
    # (stored separately to make the functions reuseable)
    # filled by initMsg()
    protected $msg = array();

    # called via another *Handler class? (use calledBy() to set this information)
    protected $called_by = '';


    /**
     * Constructor: fill $struct etc.
     * @param integer - 0 is edit mode, set to 1 to switch to create mode
     * @param string - if an admin_username is specified, permissions will be restricted to the domains this admin may manage
     */
    public function __construct($new = 0, $admin_username = "") {
        if ($new) $this->new = 1;
        $this->admin_username = $admin_username;

        if ($this->domain_field == "") {
            $this->no_domain_field();
        } else {
            if ($admin_username != "") {
                $this->allowed_domains = list_domains_for_admin($admin_username);
            } else {
                $this->allowed_domains = list_domains();
            }
        }

        $this->initStruct();

        $struct_hook = Config::read($this->db_table . '_struct_hook');
        if ( $struct_hook != 'NO' && function_exists($struct_hook) ) {
            $this->struct = $struct_hook($this->struct);
        }

        $this->initMsg();
    }

    /**
     * ensure a lazy programmer can't give access to all items accidently
     *
     * to intentionally disable the check if $this->domain_field is empty, override this function
     */
    protected function no_domain_field() {
            if ($this->admin_username != "") die('Attemp to restrict domains without setting $this->domain_field!');
    }

    /**
     * init $this->struct (an array of pacol() results)
     * see pacol() in functions.inc.php for all available parameters
     *
     * available values for the "type" column:
     *    text  one line of text
     *    pass  password (will be encrypted with pacrypt())
     *    num   number
     *    txtl  text "list" - array of one line texts
     *    vnum  "virtual" number, coming from JOINs etc.
     *    bool  boolean (converted to 0/1, additional column _$field with yes/no)
     *    ts    timestamp (created/modified)
     *    enum  list of options, must be given in column "options" as array
     *    list  like enum, but allow multiple selections
     * You can use custom types, but you'll have to add handling for them in *Handler and the smarty templates
     *
     * All database tables should have a 'created' and a 'modified' column.
     *
     * Do not use one of the following field names:
     *    edit, delete, prefill, webroot, help
     * because those are used as parameter names in the web and/or commandline interface
     */
    abstract protected function initStruct();

    /**
     * init $this->msg[] with messages used in various functions.
     *
     * always list the key to hand over to Config::lang
     * the only exception is 'logname' which uses the key for db_log
     *
     * The values can depend on $this->new
     * TODO: use separate keys edit_* and new_* and choose the needed message at runtime
     */
    abstract protected function initMsg();

    /**
     * returns an array with some labels and settings for the web interface
     * can also change $this->struct to something that makes the web interface better
     * (for example, it can make local_part and domain editable as separate fields
     * so that users can choose the domain from a dropdown)
     * 
     * @return array
     */
    abstract public function webformConfig();

    /**
     * if you call one *Handler class from another one, tell the "child" *Handler as early as possible (before init())
     * The flag can be used to avoid logging, avoid loops etc. The exact handling is up to the implementation in *Handler
     *
     * @param string calling class
     */
    public function calledBy($calling_class) {
        $this->called_by = $calling_class;
    }

    /**
     * initialize with $id and check if it is valid
     * @param string $id
     */
    public function init($id) {
        $this->id = strtolower($id);

        $exists = $this->view(false);

        if ($this->new) {
            if ($exists) {
                $this->errormsg[$this->id_field] = Config::lang($this->msg['error_already_exists']);
                return false;
            } elseif (!$this->validate_new_id() ) {
                # errormsg filled by validate_new_id()
                return false;
#            } else {
#                return true;
            }
        } else { # edit mode
            if (!$exists) {
                $this->errormsg[$this->id_field] = Config::lang($this->msg['error_does_not_exist']);
                return false;
#            } else {
#                return true;
            }
        }

        $this->domain = $this->domain_from_id();

        return true;
    }

    /**
     * on $new, check if the ID is valid (for example, check if it is a valid mail address syntax-wise)
     * called by init()
     * @return boolean true/false
     * must also set $this->errormsg[$this->id_field] if ID is invalid
     */
    abstract protected function validate_new_id();

    /**
     * called by init() if $this->id != $this->domain_field
     * must be overridden if $id_field != $domain_field
     * @return string the domain to use for logging
     */
    protected function domain_from_id() {
        if ($this->id_field == $this->domain_field) {
            return $this->id;
        } elseif ($this->domain_field == "") {
            return "";
        } else {
            die('You must override domain_from_id()!');
        }
    }

    /**
     * web interface can prefill some fields
     * if a _prefill_$field method exists, call it (it can for example modify $struct)
     * @param string - field
     * @param string - prefill value
     */
    public function prefill($field, $val) {
        $func="_prefill_".$field;
        if (method_exists($this, $func) ) {
            $this->{$func}($field, $val); # call _missing_$fieldname()
        } else {
            $this->struct[$field]['default'] = $val;
        }
    }

    /**
     * set and verify values
     * @param array values - associative array with ($field1 => $value1, $field2 => $value2, ...)
     * @return bool - true if all values are valid, otherwise false
     * error messages (if any) are stored in $this->errormsg
     */
    public function set($values) {
        if ($this->new == 1) {
            $values[$this->id_field] = $this->id;
        }

        $this->RAWvalues = $values; # allows comparison of two fields before the second field is checked
        # Warning: $this->RAWvalues contains unchecked input data - use it carefully!

        if ($this->new) {
            foreach($this->struct as $key=>$row) {
                if ($row['editable'] && !isset($values[$key]) ) {
                    /**
                    * when creating a new item:
                    * if a field is editable and not set, 
                    * - if $this->_missing_$fieldname() exists, call it
                    *   (it can set $this->RAWvalues[$fieldname] - or do nothing if it can't set a useful value)
                    * - otherwise use the default value from $this->struct
                    *   (if you don't want this, create an empty _missing_$fieldname() function)
                    */
                    $func="_missing_".$key;
                    if (method_exists($this, $func) ) {
                        $this->{$func}($key); # call _missing_$fieldname()
                    } else { 
                        $this->set_default_value($key); # take default value from $this->struct
                    }
                }
            }
            $values = $this->RAWvalues;
        }


        # base validation
        $this->values = array();
        $this->values_valid = false;
        foreach($this->struct as $key=>$row) {
            if ($row['editable'] == 0) { # not editable
                if ($this->new == 1) {
                    # on $new, always set non-editable field to default value on $new (even if input data contains another value)
                    $this->values[$key] = $row['default'];
                }
            } else { # field is editable
                if (isset($values[$key])) {
                    if ($row['type'] != "pass" || strlen($values[$key]) > 0 || $this->new == 1 || $this->skip_empty_pass != true) { # skip on empty (aka unchanged) password on edit
# TODO: do not skip "password2" if "password" is filled, but "password2" is empty
                        $valid = true; # trust input unless validator objects

                        # validate based on field type ($this->_inp_$type)
                        $func="_inp_".$row['type'];
                        if (method_exists($this, $func) ) {
                            if (!$this->{$func}($key, $values[$key])) $valid = false;
                        } else {
                            # TODO: warning if no validation function exists?
                        }

                        # validate based on field name (_validate_$fieldname)
                        $func="_validate_".$key;
                        if (method_exists($this, $func) ) {
                            if (!$this->{$func}($key, $values[$key])) $valid = false;
                        }

                        if ($valid) {
                            $this->values[$key] = $values[$key];
                        }
                    }
                } elseif ($this->new) { # new, field not set in input data
                    $this->errormsg[$key] = Config::lang_f('missing_field', $key);
                } else { # edit, field unchanged
                    # echo "skipped / not set: $key\n";
                }
            }
        }

        $this->setmore($values);

        if (count($this->errormsg) == 0) {
            $this->values_valid = true;
        }
        return $this->values_valid;
    }

    /**
     * set more values
     * can be used to update additional columns etc.
     * hint: modify $this->values and $this->errormsg directly as needed
     */
    protected function setmore($values) {
        # do nothing
    }

    /**
     * store $this->values in the database
     *
     * converts values based on $this->struct[*][type] (boolean, password encryption)
     *
     * calls $this->storemore() where additional things can be done
     * @return bool - true if all values were stored in the database, otherwise false
     * error messages (if any) are stored in $this->errormsg
     */
    public function store() {
        if ($this->values_valid == false) {
            $this->errormsg[] = "one or more values are invalid!";
            return false;
        }

        if ( !$this->beforestore() ) {
            return false;
        }

        $db_values = $this->values;

        foreach(array_keys($db_values) as $key) {
            switch ($this->struct[$key]['type']) { # modify field content for some types
                case 'bool':
                    $db_values[$key] = db_get_boolean($db_values[$key]);
                    break;
                case 'pass':
                    $db_values[$key] = pacrypt($db_values[$key]);
                    break;
            }
            if ($this->struct[$key]['not_in_db'] == 1) unset ($db_values[$key]); # remove 'not in db' columns
            if ($this->struct[$key]['dont_write_to_db'] == 1) unset ($db_values[$key]); # remove 'dont_write_to_db' columns
        }

        if ($this->new) {
            $result = db_insert($this->db_table, $db_values);
        } else {
            $result = db_update($this->db_table, $this->id_field, $this->id, $db_values);
        }
        if ($result != 1) {
            $this->errormsg[] = Config::lang_f($this->msg['store_error'], $this->id);
            return false;
        }

        $result = $this->storemore();

        # db_log() even if storemore() failed
        db_log ($this->domain, $this->msg['logname'], $this->id);

        if ($result) {
            # return success message
            # TODO: add option to override the success message (for example to include autogenerated passwords)
            $this->infomsg['success'] = Config::lang_f($this->msg['successmessage'], $this->id);
        }

        return $result;
    }

    /**
     * called by $this->store() before storing the values in the database
     * @return bool - if false, store() will abort
     */
     protected function beforestore() {
        return true; # do nothing, successfully ;-)
     }

    /**
     * called by $this->store() after storing $this->values in the database
     * can be used to update additional tables, call scripts etc.
     */
    protected function storemore() {
        return true; # do nothing, successfully ;-)
    }


    /**
     * read_from_db
     *
     * reads all fields specified in $this->struct from the database
     * and auto-converts them to database-independent values based on the field type (see $colformat)
     *
     * calls $this->read_from_db_postprocess() to postprocess the result
     *
     * @param array or string - condition (an array will be AND'ed using db_where_clause, a string will be directly used)
     *                          (if you use a string, make sure it is correctly escaped!)
     * @param integer limit - maximum number of rows to return
     * @param integer offset - number of first row to return
     * @return array - rows (as associative array, with the ID as key)
     */
    protected function read_from_db($condition, $limit=-1, $offset=-1) {
        $select_cols = array();

        $yes = escape_string(Config::lang('YES'));
        $no  = escape_string(Config::lang('NO'));

        if (db_pgsql()) {
            $formatted_date = "TO_DATE(text(###KEY###), '" . escape_string(Config::Lang('dateformat_pgsql')) . "')";
        } else {
            $formatted_date = "DATE_FORMAT(###KEY###, '"   . escape_string(Config::Lang('dateformat_mysql')) . "')";
        }

        $colformat = array(
            # 'ts' fields are always returned as $formatted_date, and the raw value as _$field
            'ts' => "$formatted_date AS ###KEY###, ###KEY### AS _###KEY###",
            # 'bool' fields are always returned as 0/1, additonally _$field contains yes/no (already translated)
            'bool' => "CASE ###KEY### WHEN '" . db_get_boolean(true) . "' THEN '1'    WHEN '" . db_get_boolean(false) . "' THEN '0'   END as ###KEY###," .
                      "CASE ###KEY### WHEN '" . db_get_boolean(true) . "' THEN '$yes' WHEN '" . db_get_boolean(false) . "' THEN '$no' END as _###KEY###",
        );

        # get list of fields to display
        $extrafrom = "";
        foreach($this->struct as $key=>$row) {
            if ( ($row['display_in_list'] != 0 || $row['display_in_form'] != 0) && $row['not_in_db'] == 0 ) {
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
            if ($condition == "") $condition = '1=1';
            $where = " WHERE ( $condition ) ";
        }

        if ($this->domain_field != "") {
            $where .= " AND " . db_in_clause($this->domain_field, $this->allowed_domains);
        }

        $query = "SELECT $cols FROM $table $extrafrom $where ORDER BY " . $this->id_field;

        $limit  = (int) $limit; # make sure $limit and $offset are really integers
        $offset = (int) $offset;
        if ($limit > -1 && $offset > -1) {
            $query .= " LIMIT $limit OFFSET $offset ";
        }

        $result = db_query($query);

        $db_result = array();
        if ($result['rows'] != 0) {
            while ($row = db_assoc ($result['result'])) {
                $db_result[$row[$this->id_field]] = $row;
            }
        }

        $db_result = $this->read_from_db_postprocess($db_result);
        return $db_result;
    }

    /**
     * allows to postprocess the database result
     * called by read_from_db()
     */
    protected function read_from_db_postprocess($db_result) {
        return $db_result;
    }


    /**
     * get the values of an item
     * @param boolean (optional) - if false, $this->errormsg[] will not be filled in case of errors 
     * @return bool - true if item was found
     * The data is stored in $this->result (as associative array of column => value)
     * error messages (if any) are stored in $this->errormsg
     */
    public function view($errors=true) {
        $result = $this->read_from_db(array($this->id_field => $this->id) );
        if (count($result) == 1) {
            $this->result = $result[$this->id];
            return true;
        }

        if ($errors) $this->errormsg[] = Config::lang($this->msg['error_does_not_exist']);
#        $this->errormsg[] = $result['error'];
        return false;
    }

    /**
     * get a list of one or more items with all values
     * @param array or string $condition - see read_from_db for details
     * @param integer limit - maximum number of rows to return
     * @param integer offset - number of first row to return
     * @return bool - always true, no need to check ;-) (if $result is not an array, getList die()s)
     * The data is stored in $this->result (as array of rows, each row is an associative array of column => value)
     */
    public function getList($condition, $limit=-1, $offset=-1) {
        $result = $this->read_from_db($condition, $limit, $offset);

        if (!is_array($result)) {
            error_log('getList: read_from_db didn\'t return an array. table: ' . $this->db_table . ' - condition: $condition - limit: $limit - offset: $offset');
            error_log('getList: This is most probably caused by read_from_db_postprocess()');
            die('Unexpected error while reading from database! (Please check the error log for details, and open a bugreport)');
        }

        $this->result = $result;
        return true;
    }


    /**
     * Attempt to log a user in.
     * @param string $username
     * @param string $password
     * @return boolean true on successful login (i.e. password matches etc)
     */
    public function login($username, $password) {
        $username = escape_string($username);

        $table = table_by_key($this->db_table);
        $active = db_get_boolean(True);
        $query = "SELECT password FROM $table WHERE " . $this->id_field . "='$username' AND active='$active'";

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


    /**************************************************************************
     * functions to read protected variables
     */
    public function getStruct() {
        return $this->struct;
    }

    public function getId_field() {
        return $this->id_field;
    }

    /**
     * @return return value of previously called method
     */
    public function result() {
        return $this->result;
    }


    /**
     * compare two password fields
     * typically called from _validate_password2()
     * @param string $field1 - "password" field
     * @param string $field2 - "repeat password" field
     */
    protected function compare_password_fields($field1, $field2) {
        if ($this->RAWvalues[$field1] == $this->RAWvalues[$field2]) {
            unset ($this->errormsg[$field2]); # no need to warn about too short etc. passwords - it's enough to display this message at the 'password' field
            return true;
        }

        $this->errormsg[$field2] = Config::lang('pEdit_mailbox_password_text_error');
        return false;
    }

    /**
     * set field to default value
     * @param string $field - fieldname
     */
    protected function set_default_value($field) {
        if (isset($this->struct[$field]['default'])) {
            $this->RAWvalues[$field] = $this->struct[$field]['default'];
        }
    }


    /**************************************************************************
      * _inp_*()
      * functions for basic input validation
      * @return boolean - true if the value is valid, otherwise false
      * also set $this->errormsg[$field] if a value is invalid
      */

    /**
      * check if value is numeric and >= -1 (= minimum value for quota)
     */
    protected function _inp_num($field, $val) {
        $valid = is_numeric($val);
        if ($val < -1) $valid = false;
        if (!$valid) $this->errormsg[$field] = Config::Lang_f('must_be_numeric', $field);
        return $valid;
        # return (int)($val);
    }

    /**
      * check if value is (numeric) boolean - in other words: 0 or 1
     */
    protected function _inp_bool($field, $val) {
        if ($val == "0" || $val == "1") return true;
        $this->errormsg[$field] = Config::Lang_f('must_be_boolean', $field);
        return false;
        # return $val ? db_get_boolean(true): db_get_boolean(false);
    }

    /**
      * check if value of an enum field is in the list of allowed values
     */
    protected function _inp_enum($field, $val) {
        if(in_array($val, $this->struct[$field]['options'])) return true;
        $this->errormsg[$field] = Config::Lang_f('invalid_value_given', $field);
        return false;
    }

    /**
      * check if a password is secure enough
     */
    protected function _inp_pass($field, $val){
        $validpass = validate_password($val); # returns array of error messages, or empty array on success

        if(count($validpass) == 0) return true;

        $this->errormsg[$field] = $validpass[0]; # TODO: honor all error messages, not only the first one?
        return false;
    }

}
/* vim: set expandtab softtabstop=4 tabstop=4 shiftwidth=4: */
