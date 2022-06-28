<?php

abstract class PFAHandler {
    /**
     * public variables
     */

    /**
     * @var array
     */
    public $result = array();

    /**
     * @var array of error messages - if a method returns false, you'll find the error message(s) here
     */
    public $errormsg = array();

    /**
     * @var array of info messages (for example success messages)
     */
    public $infomsg = array();

    /**
     * @var array tasks available in CLI
     */
    public $taskNames = array('Help', 'Add', 'Update', 'Delete', 'View', 'Scheme');

    /**
     * variables that must be defined in all *Handler classes
     */

    /**
     * @var string (default) name of the database table
     * (can be overridden by $CONF[database_prefix] and $CONF[database_tables][*] via table_by_key())
     */
    protected $db_table = '';

    /**
     * @var string field containing the ID
     */
    protected $id_field = '';

    /**
     * @var string  field containing the label
     * defaults to $id_field if not set
     */
    protected $label_field;

    /**
     * field(s) to use in the ORDER BY clause
     * can contain multiple comma-separated fields
     * defaults to $id_field if not set
     * @var string
     */
    protected $order_by = '';

    /**
     * @var string
     * column containing the domain
     * if a table does not contain a domain column, leave empty and override no_domain_field())
     */
    protected $domain_field = "";

    /**
     * column containing the username (if logged in as non-admin)
     * @var string
     */
    protected $user_field = '';

    /**
     * skip empty password fields in edit mode
     * enabled by default to allow changing an admin, mailbox etc. without changing the password
     * disable for "edit password" forms
     * @var boolean
     */
    protected $skip_empty_pass = true;

    /**
     * @var array fields to search when using simple search ("?search[_]=...")
     * array with one or more fields to search (all fields will be OR'ed in the query)
     * searchmode is always 'contains' (using LIKE "%searchterm%")
     */
    protected $searchfields = array();

    /**
     * internal variables - filled by methods of *Handler
     */

    # if $domain_field is set, this is an array with the domain list
    # set in __construct()
    protected $allowed_domains = false;

    # if set, restrict $allowed_domains to this admin
    # set in __construct()
    protected $admin_username = "";

    # will be set to 0 if $admin_username is set and is not a superadmin
    protected $is_superadmin = 1;

    /**
     * @var string $username
     * if set, switch to user (non-admin) mode
     */
    protected $username = '';

    # will be set to 0 if a user (non-admin) is logged in
    protected $is_admin = 1;

    # the ID of the current item (where item can be an admin, domain, mailbox, alias etc.)
    # filled in init()
    protected $id = null;

    # the domain of the current item (used for logging)
    # filled in domain_from_id() via init()
    protected $domain = null;

    # the label of the current item (for usage in error/info messages)
    # filled in init() (only contains the "real" label in edit mode - in new mode, it will be the same as $id)
    protected $label = null;

    # can this item be edited?
    # filled in init() (only in edit mode)
    protected $can_edit = 1;

    # can this item be deleted?
    # filled in init() (only in edit mode)
    protected $can_delete = 1;
    # TODO: needs to be implemented in delete()

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
    protected $msg = array(
        'can_create' => true,
        'confirm_delete' => 'confirm',
        'list_header' => '', # headline used in list view
    );

    # called via another *Handler class? (use calledBy() to set this information)
    protected $called_by = '';


    /**
     * Constructor: fill $struct etc.
     * @param int $new - 0 is edit mode, set to 1 to switch to create mode
     * @param string $username - if an admin_username is specified, permissions will be restricted to the domains this admin may manage
     * @param int $is_admin - 0 if logged in as user, 1 if logged in as admin or superadmin
     */
    public function __construct($new = 0, $username = "", $is_admin = 1) {
        # set label_field if not explicitely set
        if (empty($this->id_field)) {
            throw new \InvalidArgumentException("id_field must be defined");
        }
        if (empty($this->db_table)) {
            throw new \InvalidArgumentException("db_table must be defined");
        }
        if (empty($this->label_field)) {
            $this->label_field = $this->id_field;
        }

        # set order_by if not explicitely set
        if (empty($this->order_by)) {
            $this->order_by = $this->id_field;
        }

        if ($new) {
            $this->new = 1;
        }

        if ($is_admin) {
            $this->admin_username = $username;
        } else {
            $this->username = $username;
            $this->is_admin = 0;
            $this->is_superadmin = 0;
        }

        if ($username != "" && (! authentication_has_role('global-admin'))) {
            $this->is_superadmin = 0;
        }

        if ($this->domain_field == "") {
            $this->no_domain_field();
        } else {
            if ($this->admin_username != "") {
                $this->allowed_domains = list_domains_for_admin($username);
            } else {
                $this->allowed_domains = list_domains();
            }
        }

        if ($this->user_field == '') {
            $this->no_user_field();
        }

        $this->initStruct();

        /**
         * @psalm-suppress InvalidArrayOffset
         */
        if (!isset($this->struct['_can_edit'])) {
            $this->struct['_can_edit'] = pacol(0,           0,      1,      'vnum', ''                   , ''                  , '', array(),
                /*not_in_db*/ 0,
                /*dont_write_to_db*/ 1,
                /*select*/ '1 as _can_edit'
                );
        }

        /**
         * @psalm-suppress InvalidArrayOffset
         */
        if (!isset($this->struct['_can_delete'])) {
            $this->struct['_can_delete'] = pacol(0,         0,      1,      'vnum', ''                   , ''                  , '', array(),
                /*not_in_db*/ 0,
                /*dont_write_to_db*/ 1,
                /*select*/ '1 as _can_delete'
                );
        }

        $struct_hook = Config::read($this->db_table . '_struct_hook');
        if (!empty($struct_hook) && is_string($struct_hook) && $struct_hook != 'NO' && function_exists($struct_hook)) {
            $this->struct = $struct_hook($this->struct);
        }

        $this->initMsg();
        $this->msg['id_field'] = $this->id_field;
        $this->msg['show_simple_search'] = count($this->searchfields) > 0;
    }

    /**
     * ensure a lazy programmer can't give access to all items accidently
     *
     * to intentionally disable the check if $this->domain_field is empty, override this function
     */
    protected function no_domain_field() {
        if ($this->admin_username != "") {
            die('Attemp to restrict domains without setting $this->domain_field!');
        }
    }

    /**
     * ensure a lazy programmer can't give access to all items accidently
     *
     * to intentionally disable the check if $this->user_field is empty, override this function
     */
    protected function no_user_field() {
        if ($this->username != '') {
            die('Attemp to restrict users without setting $this->user_field!');
        }
    }



    /**
     * init $this->struct (an array of pacol() results)
     * see pacol() in functions.inc.php for all available parameters
     *
     * available values for the "type" column:
     *    text  one line of text
     *   *vtxt  "virtual" line of text, coming from JOINs etc.
     *    html  raw html (use carefully, won't get auto-escaped by smarty! Don't use with user input!)
     *    pass  password (will be encrypted with pacrypt())
     *    b64p  password (will be stored with base64_encode() - but will NOT be decoded automatically)
     *    num   number
     *    txtl  text "list" - array of one line texts
     *   *vnum  "virtual" number, coming from JOINs etc.
     *    bool  boolean (converted to 0/1, additional column _$field with yes/no)
     *    ts    timestamp (created/modified)
     *    enum  list of options, must be given in column "options" as array
     *    enma  list of options, must be given in column "options" as associative array
     *    list  like enum, but allow multiple selections
     *   *quot  used / total quota ("5 / 10") - for field "quotausage", there must also be a "_quotausage_percent" (type vnum)
     * You can use custom types, but you'll have to add handling for them in *Handler and the smarty templates
     *
     * Field types marked with * will automatically be skipped in store().
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
    public function init(string $id): bool {

        // postfix treats address lookups (aliases, mailboxes) as if they were lowercase.
        // MySQL is normally case insenstive, PostgreSQL is case sensitive.
        // http://www.postfix.org/aliases.5.html
        // http://www.postfix.org/virtual.8.html

        $this->id = strtolower($id);
        $this->label = $this->id;

        $exists = $this->view(false);

        if ($this->new) {
            if ($exists) {
                $this->errormsg[$this->id_field] = Config::lang($this->msg['error_already_exists']);
                return false;
            } elseif (!$this->validate_new_id()) {
                # errormsg filled by validate_new_id()
                return false;
            }
        } else { # view or edit mode
            if (!$exists) {
                $this->errormsg[$this->id_field] = Config::lang($this->msg['error_does_not_exist']);
                return false;
            } else {
                $this->can_edit   = $this->result['_can_edit'];
                $this->can_delete = $this->result['_can_delete'];
                $this->label      = $this->result[$this->label_field];
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
     * @param string $field - field
     * @param string $val - prefill value
     */
    public function prefill($field, $val) {
        $func="_prefill_".$field;
        if (method_exists($this, $func)) {
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
    public function set(array $values) {
        if (!$this->can_edit) {
            $this->errormsg[] = Config::Lang_f('edit_not_allowed', $this->label);
            return false;
        }

        if ($this->new == 1) {
            $values[$this->id_field] = $this->id;
        }

        $this->RAWvalues = $values; # allows comparison of two fields before the second field is checked
        # Warning: $this->RAWvalues contains unchecked input data - use it carefully!

        if ($this->new) {
            foreach ($this->struct as $key=>$row) {
                if ($row['editable'] && !isset($values[$key])) {
                    /**
                    * when creating a new item:
                    * if a field is editable and not set,
                    * - if $this->_missing_$fieldname() exists, call it
                    *   (it can set $this->RAWvalues[$fieldname] - or do nothing if it can't set a useful value)
                    * - otherwise use the default value from $this->struct
                    *   (if you don't want this, create an empty _missing_$fieldname() function)
                    */
                    $func="_missing_".$key;
                    if (method_exists($this, $func)) {
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
        foreach ($this->struct as $key=>$row) {
            if ($row['editable'] == 0) { # not editable
                if ($this->new == 1) {
                    # on $new, always set non-editable field to default value on $new (even if input data contains another value)
                    $this->values[$key] = $row['default'];
                }
            } else { # field is editable
                if (isset($values[$key])) {
                    if (
                        ($row['type'] != "pass" && $row['type'] != 'b64p') || # field  type is NOT 'pass' or 'b64p' - or -
                        strlen($values[$key]) > 0 ||    # new value is not empty - or -
                        $this->new == 1 ||              # create mode - or -
                        $this->skip_empty_pass != true  # skip on empty (aka unchanged) password on edit
                    ) {
                        # TODO: do not skip "password2" if "password" is filled, but "password2" is empty
                        $valid = true; # trust input unless validator objects

                        # validate based on field type ($this->_inp_$type)
                        $func="_inp_".$row['type'];
                        if (method_exists($this, $func)) {
                            if (!$this->{$func}($key, $values[$key])) {
                                $valid = false;
                            }
                        } else {
                            # TODO: warning if no validation function exists?
                        }

                        # validate based on field name (_validate_$fieldname)
                        $func="_validate_".$key;
                        if (method_exists($this, $func)) {
                            if (!$this->{$func}($key, $values[$key])) {
                                $valid = false;
                            }
                        }

                        if (isset($this->errormsg[$key]) && $this->errormsg[$key] != '') {
                            $valid = false;
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
    protected function setmore(array $values) {
        # do nothing
    }

    /**
     * save $this->values to the database
     *
     * converts values based on $this->struct[*][type] (boolean, password encryption)
     *
     * calls $this->postSave() where additional things can be done
     * @return bool - true if all values were stored in the database, otherwise false
     *     error messages (if any) are stored in $this->errormsg
     */
    public function save(): bool {
        # backwards compability: save() was once (up to 3.2.x) named store(). If a child class still uses the old name, let it override save().
        if (method_exists($this, 'store')) {
            error_log('store() is deprecated, please rename it to save()');
            return $this->store();
        }

        if ($this->values_valid == false) {
            $this->errormsg[] = "one or more values are invalid!";
            return false;
        }

        if (!$this->preSave()) {
            return false;
        }

        $db_values = $this->values;

        foreach ($db_values as $key => $val) {
            switch ($this->struct[$key]['type']) { # modify field content for some types
                case 'bool':
                    $val = (string) $val;
                    $db_values[$key] = db_get_boolean($val);
                    break;
                case 'pass':
                    $val = (string) $val;
                    $db_values[$key] = pacrypt($val); // throws Exception
                    break;
                case 'b64p':
                    $db_values[$key] = base64_encode($val);
                    break;
                case 'quot':
                case 'vnum':
                case 'vtxt':
                    unset($db_values[$key]); # virtual field, never write it
                    break;
            }
            if ($this->struct[$key]['not_in_db'] == 1) {
                unset($db_values[$key]);
            } # remove 'not in db' columns
            if ($this->struct[$key]['dont_write_to_db'] == 1) {
                unset($db_values[$key]);
            } # remove 'dont_write_to_db' columns
        }

        try {
            if ($this->new) {
                $result = db_insert($this->db_table, $db_values,  array('created', 'modified'),true);
            } else {
                $result = db_update($this->db_table, $this->id_field, $this->id, $db_values, array('modified'), true);
            }
        } catch (PDOException $e) {
            $this->errormsg[] = Config::lang_f($this->msg['store_error'], $this->label);
            return false;
        }

        $result = $this->postSave();

        # db_log() even if postSave() failed
        db_log($this->domain, $this->msg['logname'], $this->id);

        if ($result) {
            # return success message
            # TODO: add option to override the success message (for example to include autogenerated passwords)
            $this->infomsg['success'] = Config::lang_f($this->msg['successmessage'], $this->label);
        }

        return $result;
    }

    /**
     * called by $this->save() before storing the values in the database
     * @return bool - if false, save() will abort
     */
    protected function preSave(): bool {
        # backwards compability: preSave() was once (up to 3.2.x) named beforestore(). If a child class still uses the old name, let it override preSave().
        # Note: if a child class also has preSave(), it will override this function and obviously also the compability code.
        if (method_exists($this, 'beforestore')) {
            error_log('beforestore() is deprecated, please rename it to preSave()');
            return $this->beforestore();
        }

        return true; # do nothing, successfully ;-)
    }

    /**
     * called by $this->save() after storing $this->values in the database
     * can be used to update additional tables, call scripts etc.
     */
    protected function postSave(): bool {
        # backwards compability: postSave() was once (up to 3.2.x) named storemore(). If a child class still uses the old name, let it override postSave().
        # Note: if a child class also has postSave(), it will override this function and obviously also the compability code.
        if (method_exists($this, 'storemore')) {
            error_log('storemore() is deprecated, please rename it to postSave()');
            return $this->storemore();
        }

        return true; # do nothing, successfully ;-)
    }


    /**
     * build_select_query
     *
     * helper function to build the inner part of the select query
     * can be used by read_from_db() and for generating the pagebrowser
     *
     * @param array or string - condition (an array will be AND'ed using db_where_clause, a string will be directly used)
     *                          (if you use a string, make sure it is correctly escaped!)
     *                        - WARNING: will be changed to array only in the future, with an option to include a raw string inside the array
     * @param array searchmode - operators to use (=, <, >) if $condition is an array. Defaults to = if not specified for a field.
     * @return array - contains query parts
     */
    protected function build_select_query($condition, $searchmode) {
        $select_cols = array();

        $yes = escape_string(Config::lang('YES'));
        $no  = escape_string(Config::lang('NO'));

        if (db_pgsql()) {
            $formatted_date = "TO_CHAR(###KEY###, '" . escape_string(Config::Lang('dateformat_pgsql')) . "')";
        # $base64_decode = "DECODE(###KEY###, 'base64')";
        } elseif (db_sqlite()) {
            $formatted_date = "strftime(###KEY###, '" . escape_string(Config::Lang('dateformat_mysql')) . "')";
        # $base64_decode = "base64_decode(###KEY###)";
        } else {
            $formatted_date = "DATE_FORMAT(###KEY###, '"   . escape_string(Config::Lang('dateformat_mysql')) . "')";
            # $base64_decode = "FROM_BASE64(###KEY###)"; # requires MySQL >= 5.6
        }

        $colformat = array(
            # 'ts' fields are always returned as $formatted_date, and the raw value as _$field
            'ts' => "$formatted_date AS ###KEY###, ###KEY### AS _###KEY###",
            # 'bool' fields are always returned as 0/1, additonally _$field contains yes/no (already translated)
            'bool' => "CASE ###KEY### WHEN '" . db_get_boolean(true) . "' THEN '1'    WHEN '" . db_get_boolean(false) . "' THEN '0'   END as ###KEY###," .
                      "CASE ###KEY### WHEN '" . db_get_boolean(true) . "' THEN '$yes' WHEN '" . db_get_boolean(false) . "' THEN '$no' END as _###KEY###",
            # 'b64p' => "$base64_decode AS ###KEY###",  # not available in MySQL < 5.6, therefore not decoding for any database
        );

        # get list of fields to display
        $extrafrom = "";
        foreach ($this->struct as $key=>$row) {
            if (($row['display_in_list'] != 0 || $row['display_in_form'] != 0) && $row['not_in_db'] == 0) {
                if ($row['select'] != '') {
                    $key = $row['select'];
                }

                if ($row['extrafrom'] != '') {
                    $extrafrom = $extrafrom . " " . $row['extrafrom'] . "\n";
                }

                if (isset($colformat[$row['type']])) {
                    $select_cols[] = str_replace('###KEY###', $key, $colformat[$row['type']]);
                } else {
                    $select_cols[] = $key;
                }
            }
        }

        $cols = join(',', $select_cols);
        $table = table_by_key($this->db_table);

        $additional_where = '';
        if ($this->domain_field != "") {
            $additional_where .= " AND " . db_in_clause($this->domain_field, $this->allowed_domains);
        }

        # if logged in as user, restrict to the items the user is allowed to see
        if ((!$this->is_admin) && $this->user_field != '') {
            $additional_where .= " AND " . $this->user_field . " = '" . escape_string($this->username) . "' ";
        }

        if (is_array($condition)) {
            if (isset($condition['_']) && count($this->searchfields) > 0) {
                $simple_search = array();
                foreach ($this->searchfields as $field) {
                    $simple_search[] = "$field LIKE '%" . escape_string($condition['_']) . "%'";
                }
                $additional_where .= " AND ( " . join(" OR ", $simple_search) . " ) ";
                unset($condition['_']);
            }
            $where = db_where_clause($condition, $this->struct, $additional_where, $searchmode);
        } else {
            if ($condition == "") {
                $condition = '1=1';
            }
            $where = " WHERE ( $condition ) $additional_where";
        }

        return array(
            'select_cols'       => " SELECT $cols ",
            'from_where_order'  => " FROM $table $extrafrom $where ORDER BY " . $this->order_by,
        );
    }

    /**
     * getPagebrowser
     *
     * @param array or string condition (see build_select_query() for details)
     * @param array searchmode - (see build_select_query() for details)
     * @return array - pagebrowser keys ("aa-cz", "de-pf", ...)
     */
    public function getPagebrowser($condition, $searchmode) {
        $queryparts = $this->build_select_query($condition, $searchmode);
        return create_page_browser($this->label_field, $queryparts['from_where_order']);
    }

    /**
     * read_from_db
     *
     * reads all fields specified in $this->struct from the database
     * and auto-converts them to database-independent values based on the field type (see $colformat)
     *
     * calls $this->read_from_db_postprocess() to postprocess the result
     *
     * @param array|string $condition -see build_select_query() for details
     * @param array $searchmode - see build_select_query() for details
     * @param int $limit - maximum number of rows to return
     * @param int $offset - number of first row to return
     * @return array - rows (as associative array, with the ID as key)
     */
    protected function read_from_db($condition, $searchmode = array(), $limit=-1, $offset=-1): array {
        $queryparts = $this->build_select_query($condition, $searchmode);

        $query = $queryparts['select_cols'] . $queryparts['from_where_order'];

        $limit  = (int) $limit; # make sure $limit and $offset are really integers
        $offset = (int) $offset;
        if ($limit > -1 && $offset > -1) {
            $query .= " LIMIT $limit OFFSET $offset ";
        }

        $db_result = array();


        $result = db_query_all($query);

        foreach ($result as $row) {
            $db_result[$row[$this->id_field]] = $row;
        }

        return $this->read_from_db_postprocess($db_result);
    }

    /**
     * allows to postprocess the database result
     * called by read_from_db()
     * @param array $db_result
     * @return array
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
        $result = $this->read_from_db(array($this->id_field => $this->id));
        if (count($result) == 1) {
            $this->result = reset($result);
            return true;
        }

        if ($errors) {
            $this->errormsg[] = Config::lang($this->msg['error_does_not_exist']);
        }
        #        $this->errormsg[] = $result['error'];
        return false;
    }

    /**
     * get a list of one or more items with all values
     * @param array|string $condition - see read_from_db for details
     *        WARNING: will be changed to array only in the future, with an option to include a raw string inside the array
     * @param array $searchmode - modes to use if $condition is an array - see read_from_db for details
     * @param int $limit - maximum number of rows to return
     * @param int $offset - number of first row to return
     * @return bool - always true, no need to check ;-) (if $result is not an array, getList die()s)
     * The data is stored in $this->result (as array of rows, each row is an associative array of column => value)
     */
    public function getList($condition, $searchmode = array(), $limit=-1, $offset=-1): bool {
        if (is_array($condition)) {
            $real_condition = array();
            foreach ($condition as $key => $value) {
                # allow only access to fields the user can access to avoid information leaks via search parameters
                if (isset($this->struct[$key]) && ($this->struct[$key]['display_in_list'] || $this->struct[$key]['display_in_form'])) {
                    $real_condition[$key] = $value;
                } elseif (($key == '_') && count($this->searchfields)) {
                    $real_condition[$key] = $value;
                } else {
                    $this->errormsg[] = "Ignoring unknown search field $key";
                }
            }
        } else {
            # warning: no sanity checks are applied if $condition is not an array!
            $real_condition = $condition;
        }

        $this->result = $this->read_from_db($real_condition, $searchmode, $limit, $offset);
        return true;
    }

    /**
     * Verify user's one time password reset token
     * @param string $username
     * @param string $token
     * @return boolean true on success (i.e. code matches etc)
     */
    public function checkPasswordRecoveryCode($username, $token) {
        $table = table_by_key($this->db_table);
        $active = db_get_boolean(true);

        $now = date('Y-m-d H:i:s');

        $query = "SELECT token FROM $table WHERE {$this->id_field} = :username AND token <> '' AND active = :active AND token_validity > :now ";
        $values = array('username' => $username, 'active' => $active, 'now' => $now);

        $result = db_query_all($query, $values);
        if (sizeof($result) == 1) {
            $row = $result[0];

            $crypt_token = pacrypt($token, $row['token']);

            if ($row['token'] == $crypt_token) {
                db_update($this->db_table, $this->id_field, $username, array(
                    'token' => '',
                    'token_validity' => '2000-01-01 00:00:00',
                ));
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

    public function getMsg() {
        return $this->msg;
    }

    public function getId_field() {
        return $this->id_field;
    }

    /**
     * @return mixed return value of previously called method
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
            unset($this->errormsg[$field2]); # no need to warn about too short etc. passwords - it's enough to display this message at the 'password' field
            return true;
        }

        $this->errormsg[$field2] = Config::lang('pEdit_mailbox_password_text_error');
        return false;
    }

    /**
     * set field to default value
     * @param string $field - fieldname
     * @return void
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
     * @param string $field
     * @param string $val
     * @return boolean
     */
    protected function _inp_num($field, $val) {
        $valid = is_numeric($val);
        if ($val < -1) {
            $valid = false;
        }
        if (!$valid) {
            $this->errormsg[$field] = Config::Lang_f('must_be_numeric', $field);
        }
        return $valid;
        # return (int)($val);
    }

    /**
     * check if value is (numeric) boolean - in other words: 0 or 1
     * @param string $field
     * @param string $val
     * @return boolean
     */
    protected function _inp_bool($field, $val) {
        if ($val == "0" || $val == "1") {
            return true;
        }
        $this->errormsg[$field] = Config::Lang_f('must_be_boolean', $field);
        return false;
        # return $val ? db_get_boolean(true): db_get_boolean(false);
    }

    /**
     * check if value of an enum field is in the list of allowed values
     * @param string $field
     * @param string $val
     * @return boolean
     */
    protected function _inp_enum($field, $val) {
        if (in_array($val, $this->struct[$field]['options'])) {
            return true;
        }
        $this->errormsg[$field] = Config::Lang_f('invalid_value_given', $field);
        return false;
    }

    /**
     * check if value of an enum field is in the list of allowed values
     * @param string $field
     * @param string $val
     * @return boolean
     */
    protected function _inp_enma($field, $val) {
        if (array_key_exists($val, $this->struct[$field]['options'])) {
            return true;
        }
        $this->errormsg[$field] = Config::Lang_f('invalid_value_given', $field);
        return false;
    }

    /**
     * check if a password is secure enough
     * @param string $field
     * @param string $val
     * @return boolean
     */
    protected function _inp_pass($field, $val) {
        $validpass = validate_password($val); # returns array of error messages, or empty array on success

        if (count($validpass) == 0) {
            return true;
        }

        $this->errormsg[$field] = $validpass[0]; # TODO: honor all error messages, not only the first one?
        return false;
    }
}
/* vim: set expandtab softtabstop=4 tabstop=4 shiftwidth=4: */
