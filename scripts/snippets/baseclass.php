<?php

# template for all postfixadmin classes (admins, domains, mailboxes etc.)
#
# What it DOES:
# * handling of listing (database -> array)
# * handling of editing/adding items (database -> variables for item-to-edit and variables -> database)
# * input validation for editing/adding items
# * permission checks
# * accepts / returns data as variables/arrays
#
# What it DOES NOT:
# * rendering of lists -> table_class
# * rendering of edit forms -> form_class
# * output HTML etc.

class postfixadminBaseclass {

    protected function __construct() {
        $this->initStruct;
        $this->initDefaults;
    }

    /*
    > ^^ What's name() trying to do?

    The intention was to use it as basename for generating links (to the "add new",
    "edit" and "list" pages). However, I'm not sure if this should be really inside
    the class, since it has to do with HTML rendering.

    -> removal candidate, I'll comment it out in my draft

    # name, also used for generating links
    public function name($listmode=0) {
        # usually:
        return "baseclass";

        # for "virtual" list vs. "mailbox" editing
        # if ($listmode == 0) {
        #     return "mailbox";
        # } else {
        #     return "virtual";
        # }
    }
    */

    # database table name (ensures that add/edit doesn't need to be overwritten 
    # in modules only using one table, just overwrite $table)
    private $table = "baseclass";

    #  * [read-only] table structure as array (like $fm_struct in
    #    fetchmail.php)

    private $struct;
    protected function initStruct() {
        $this->struct = array(
            # see fetchmail.php / $fm_struct for an example
        );
    }

    public function getStruct() {
        return $this->struct;
    }

    # [read-only] default values and available values for dropdowns
    private $defaults;
    protected function initDefaults() {
        $this->defaults = array(
            # see fetchmail.php / $fm_defaults for an example
        );
    }

    public function getDefaults() {
        return $this->defaults;
    }

    # primary key
    private $primarykey = 'id';


    #  * function get_list() (for list view)
    #  * function get_list_for_domain() (for list view)
    #    - both get_list* should have an optional $search parameter
    #  -> I decided to merge this to one function
    #  -> "list" is a reserved word, switched to "items"
    #
    # $filter can contain several parameters to filter the list.
    # All parameters in $filter are optional.
    # $filter = array(
    #   'domain' -> "",
    #   'admin'  -> "", 
    #   'search  -> "", 
    #   'offset' -> 0,
    #   'limit'  -> -1, # unlimited
    # )
    public function items (array $filter) {
        # TODO: implement $filter handling
        # TODO: never include password fields in the result
        $items = array();
        $res = db_query ("SELECT " . implode(",",escape_string(array_keys($this->struct))) . " FROM " . $this->table . " order by id desc");
        if ($res['rows'] > 0) {
            while ($row = db_array ($res['result'])) {
                $items[] = $row;
            }
        }
        return $items;
    }

    #  * function get_item() (current values, for edit form)
    public function item ($key) {
        # get item from database
        # return array (key -> value)
    }

    #  * function edit() (called when submitting the edit form)
    #    parameters given as array with named keys
    public function edit ($key, array $newvalues) {
        self::addOrEdit($key, $newvalues, 0);
    }

    #  * function add() (basically like edit())
    public function add (array $newvalues) {
        # TODO: fill $key from $newvalues (if a mail address etc. is used as key) 
        # or set it to NULL if the primary key is an auto_increment ID
        self::addOrEdit($key, $newvalues, 1);
    }

    # handles add and edit calls internally
    protected function addOrEdit($primarykey, array $newvalues, $mode) {
        # mode: 0 = edit, 1 = new

        #    calls: [TODO]
        #    - [non-public] function validate_by_type() (simple check against
        #      field type given in table structure)
        #      -> see fetchmail.php _inp_*()
        #      -> also check that only allowed fields are set
        #    - [non-public] function validate_special() (other checks that are
        #      not covered by type check)
        #    - save to database
        #    - logging

        $formvars=array();
        foreach($this->struct as $key=>$row){
            list($editible,$viewinedit, $view,$type)=$row;
            if ($editible != 0){
                $func="_inp_".$type;
                $val=safepost($key);
                if ($type!="password" || strlen($val) > 0) { # skip on empty (aka unchanged) password
                    $formvars[$key]= escape_string( function_exists($func) ?$func($val) :$val);
                }
            }
        }
        $formvars['id'] = $edit; # results in 0 on $new
        if($CONF['database_type'] == 'pgsql' && $new) {
            // skip - shouldn't need to specify this as it will default to the next available value anyway.
            unset($formvars['id']);
        }

        if (!in_array($formvars['mailbox'], $$this->defaults['mailbox'])) {
            flash_error($PALANG['pFetchmail_invalid_mailbox']);
            $save = 0; 
        }
        if ($formvars['src_server']    == '') {
            flash_error($PALANG['pFetchmail_server_missing']);
            # TODO: validate domain name
            $save = 0; 
        }
        if (empty($formvars['src_user']) ) {
            flash_error($PALANG['pFetchmail_user_missing']); 
            $save = 0; 
        }
        if ($new && empty($formvars['src_password']) ) {
            flash_error($PALANG['pFetchmail_password_missing']);
            $save = 0; 
        }

        if ($save) {
             if ($new) {
                $sql="INSERT INTO fetchmail (".implode(",",escape_string(array_keys($formvars))).") VALUES ('".implode("','",escape_string($formvars))."')";
            } else { # $edit
                foreach(array_keys($formvars) as $key) {
                    $formvars[$key] = escape_string($key) . "='" . escape_string($formvars[$key]) . "'";
                }
                $sql="UPDATE fetchmail SET ".implode(",",$formvars).",returned_text='', date=NOW() WHERE id=".$edit;
            }
            $result = db_query ($sql);
            if ($result['rows'] != 1)
            {
                flash_error($PALANG['pFetchmail_database_save_error']);
            } else {
                flash_info($PALANG['pFetchmail_database_save_success']);
                $edit = 0; $new = 0; # display list after saving
            }
        } else {
            $formvars['src_password'] = ''; # never display password
        }



    }

    #  * function delete()
    public function deleteItem($key) {
        $result = db_delete($this->table, $this->primarykey, $key);
        if ($result != 1)
        {
            flash_error($PALANG['pDelete_delete_error']);
        } else {
            flash_info(sprintf($PALANG['pDelete_delete_success'],$account));
        }
        #    TODO
        #    - logging
    }



    protected function _inp_num($val){
       return (int)($val);
    }

    protected function _inp_bool($val){
       return $val ? db_get_boolean(true): db_get_boolean(false);
    }

    protected function _inp_password($val){
       return base64_encode($val);
    }



    # TODO
    #  * [non-public] check_domain_permission() (check if the admin has
    #    permissions for this domain)
    #  * [non-public] check_other_permission() (check other permissions, for
    #    example if editing mailbox aliases is allowed)
    #  * other non-public functions as needed - target should be to have most
    #    code in the common class and as least as possible in the
    #    mailbox/alias/whatever class.
    #    -> this also means that functions should be split into subparts where needed



    # Usecases:
    #
    # Pseudo example:
    # 
    # Mailbox object:
    #   -> login($u, $p);
    #   -> addMailbox($name, $domain)
    #   -> deleteMailbox($name, [$domain]);
    #   -> updateMailbox($assoc_array_of_params);
    #
    # Vacation object:
    #   -> setAway($msg, $mailbox);
    #   -> setReturned($mailbox);
    #   -> isEnabled($mailbox);
    # 
    # Domain object:
    #   -> addNewDomain($name, $other, $parameters);
    #   -> listDomains();
    #   -> addMailbox($name);
    # 
    # Alias object:
    #   -> addNewAlias($source, $dest);
    #   -> listAliasesForDomain($domain_name, $paging, $parameters);
    #   -> removeAlias($source, $dest);
    #
    # Admin object:
    #    -> list admins
    #    -> list domains for admin
    #    -> create admin
    #    -> add domain to admin
    #
    # Since you already propose separate objects for mailbox, domain etc,
    # I'd prefer to have common names like "add", "edit", "delete".


}


# replacement for name() in HTML mode
# $pages = array(
#    'domains' -> 'domain',
#    'virtual' -> array('alias_domain', 'alias', 'mailbox'),
# );

/* vim: set expandtab softtabstop=4 tabstop=4 shiftwidth=4: */

