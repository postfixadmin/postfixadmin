<?php
class PFAHandler {

    /**
     * public variables
     */

    # array of error messages - if a method returns false, you'll find the error message(s) here
    public $errormsg = array();


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



    /**
     * Constructor: fill $struct etc.
     * @param string $new
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

    protected function no_domain_field() {
            if ($this->admin_username != "") die('Attemp to restrict domains without setting $this->domain_field!');
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
                $this->errormsg[$this->id_field] = Lang::read($this->msg['error_already_exists']);
                return false;
            } elseif (!$this->validate_new_id() ) {
                # errormsg filled by validate_new_id()
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
                    # if a field is editable and not set, call $this->_missing_$fieldname()
                    # (if the method exists - otherwise the field won't be set, resulting in an error later)
                    $func="_missing_".$key;
                    if (method_exists($this, $func) ) {
                        $this->{$func}($key); # function can set $this->RAWvalues[$key] (or do nothing if it can't set a useful value)
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
                    if ($row['type'] != "pass" || strlen($values[$key]) > 0 || $this->new == 1) { # skip on empty (aka unchanged) password on edit
                        $valid = true; # trust input unless validator objects

                        # validate based on field type ($this->_inp_$type)
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
     * calls $this->storemore() where additional things can be done
     * @return bool - true if all values are valid, otherwise false
     * error messages (if any) are stored in $this->errormsg
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
            $this->errormsg[] = Lang::read($this->msg['store_error']) . "\n(" . $this->id . ")\n"; # TODO: change message + use sprintf
            return false;
        }

        $result = $this->storemore();

        if ($result) {
            db_log ($this->id, $this->msg['logname'], "");
        }
        return $result;
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
     * @param array or string - condition (an array will be AND'ed using db_where_clause, a string will be directly used)
     * @param integer limit - maximum number of rows to return
     * @param integer offset - number of first row to return
     * @return array - rows (as associative array, with the ID as key)
     */
    protected function read_from_db($condition, $limit=-1, $offset=-1) {
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
            $where = " WHERE $condition ";
        }

        if ($this->domain_field != "") {
            $where .= " AND " . db_in_clause($this->domain_field, $this->allowed_domains);
        }

        $query = "SELECT $cols FROM $table $extrafrom $where ORDER BY " . $this->id_field;

        if ($limit > -1 && $offset > -1) {
            # TODO: make sure $limit and $offset are really integers - cast via (int) ?
            # TODO: make sure $limit is > 0 (0 doesn't break anything, but guarantees an empty resultset, so it's pointless)
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

    protected function read_from_db_postprocess($db_result) {
        return $db_result;
    }


    /**
     * get the values of an item
     * @param array or string $condition
     * @return bool - true if at least one item was found
     * The data is stored in $this->return (as associative array of column => value)
     * error messages (if any) are stored in $this->errormsg
     */
    public function view($errors=true) {
        $result = $this->read_from_db(array($this->id_field => $this->id) );
        if (count($result) == 1) {
            $this->return = $result[$this->id];
            return true;
        }

        if ($errors) $this->errormsg[] = Lang::read($this->msg['error_does_not_exist']);
#        $this->errormsg[] = $result['error'];
        return false;
    }

    /**
     * get a list of one or more items with all values
     * @param array or string $condition
     * @param integer limit - maximum number of rows to return
     * @param integer offset - number of first row to return
     * @return bool - true if at least one item was found
     * The data is stored in $this->return (as array of rows, each row is an associative array of column => value)
     */
    public function getList($condition, $limit=-1, $offset=-1) {
        $result = $this->read_from_db($condition, $limit, $offset);
        if (count($result) >= 1) {
            $this->return = $result;
            return true;
        }

#        $this->errormsg[] = Lang::read($this->msg['error_does_not_exist']);
#        $this->errormsg[] = $result['error'];
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
        return $this->return;
    }


    /**
     * compare two password fields
     * typically called from _field_password2()
     * @param string $field1 - "password" field
     * @param string $field2 - "repeat password" field
     */
    protected function compare_password_fields($field1, $field2) {
        if ($this->RAWvalues[$field1] == $this->RAWvalues[$field2]) {
            unset ($this->errormsg[$field2]); # no need to warn about too short etc. passwords - it's enough to display this message at the 'password' field
            return true;
        }

        $this->errormsg[$field2] = Lang::read('pEdit_mailbox_password_text_error');
        return false;
    }

    /**
     * set field to default value
     * typically called from _missing_$fieldname()
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
    function _inp_num($field, $val) {
        $valid = is_numeric($val);
        if ($val < -1) $valid = false;
        if (!$valid) $this->errormsg[$field] = "$field must be numeric"; # TODO: make translateable
        return $valid;
        # return (int)($val);
    }

    /**
      * check if value is (numeric) boolean - in other words: 0 or 1
     */
    function _inp_bool($field, $val) {
        if ($val == "0" || $val == "1") return true;
        $this->errormsg[$field] = "$field must be boolean"; # TODO: make translateable
        return false;
        # return $val ? db_get_boolean(true): db_get_boolean(false);
    }

    /**
      * check if value of an enum field is in the list of allowed values
     */
    function _inp_enum($field, $val) {
        if(in_array($val, $this->struct[$field]['options'])) return true;
        $this->errormsg[$field] = "Invalid parameter given for $field"; # TODO: make translateable
        return false;
    }

    /**
      * check if a password is secure enough
     */
    function _inp_pass($field, $val){
        $validpass = validate_password($val); # returns array of error messages, or empty array on success

        if(count($validpass) == 0) return true;

        $this->errormsg[$field] = $validpass[0]; # TODO: honor all error messages, not only the first one?
        return false;
    }

}
/* vim: set expandtab softtabstop=4 tabstop=4 shiftwidth=4: */
