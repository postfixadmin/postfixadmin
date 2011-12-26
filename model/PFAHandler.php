<?php
class PFAHandler {

    protected $id = null;
    protected $db_table = null;
    protected $id_field = null;
    protected $struct = array();
    protected $new = 0; # 1 on create, otherwise 0
    protected $values = array();
    protected $RAWvalues = array(); # unchecked (!) input given to set() - use it carefully!
    protected $values_valid = false;
    protected $admin_username = "";     # if set, restrict $allowed_domains to this admin
    protected $domain_field = "";       # column containing the domain
    protected $allowed_domains = false; # if $domain_field is set, this is an array with the domain list

    public $errormsg = array();

    # messages used in various functions
    # (stored separately to make the functions reuseable)
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
                    if ($row['type'] != "pass" || strlen($values[$key]) > 0 || $this->new == 1) { # skip on empty (aka unchanged) password on edit
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
     * @return bool - true if at least one item was found
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


    /**************************************************************************
      * functions for basic input validation
      */
    function _inp_num($field, $val) {
        $valid = is_numeric($val);
        if ($val < -1) $valid = false;
        if (!$valid) $this->errormsg[$field] = "$field must be numeric";
        return $valid;
        # return (int)($val);
    }

    function _inp_bool($field, $val) {
        if ($val == "0" || $val == "1") return true;
        $this->errormsg[$field] = "$field must be boolean";
        return false;
        # return $val ? db_get_boolean(true): db_get_boolean(false);
    }

    function _inp_enum($field, $val) {
        if(in_array($val, $this->struct[$field]['options'])) return true;
        $this->errormsg[$field] = "Invalid parameter given for $field";
        return false;
    }

    function _inp_pass($field, $val){
        $validpass = validate_password($val);

        if(count($validpass) == 0) return true;

        $this->errormsg[$field] = $validpass[0]; # TODO: honor all error messages, not only the first one?
        return false;
    }

}
/* vim: set expandtab softtabstop=4 tabstop=4 shiftwidth=4: */
