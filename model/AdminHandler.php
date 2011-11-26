<?php
# $Id$

class AdminHandler extends PFAHandler {

   protected function validate_new_id() {
       $valid = check_email($this->id);

       if ($valid) {
            return true;
       } else {
            $this->errormsg[$this->id_field] = Lang::read('pAdminCreate_admin_username_text_error1'); # TODO: half of the errormsg is currently delivered via flash_error() in check_email / check_domain
            return false;
       }
   }

    # init $this->struct, $this->db_table and $this->id_field
    protected function initStruct() {
        $this->db_table = 'admin';
        $this->id_field = 'username';

        # TODO: shorter PALANG labels ;-)
        # TODO: hardcode 'default' to Config::read in pacol()?

        # values for the "type" column:
        # text  one line of text
        # pass  password (will be encrypted with pacrypt())  # TODO: not implemented yet
        # num   number
        # vnum  "virtual" number, coming from JOINs etc.
#TODO   # vbool "virtual" bool, coming from JOINs etc.
        # bool  boolean (converted to 0/1, additional column _$field with yes/no)
        # ts    timestamp (created/modified)
        # enum  list of options, must be given in column "options" as array
#TODO   # list  like enum, but allow multiple selections

        # NOTE: There are dependencies between domains and domain_count
        # NOTE: If you disable "display in list" for domain_count, the SQL query for domains might break.
        # NOTE: (Disabling both shouldn't be a problem.)

        $this->struct=array(
            # field name                allow       display in...   type    $PALANG label                    $PALANG description                 default / options / ...
            #                           editing?    form    list
            'username'        => pacol( $this->new, 1,      1,      'text', 'pAdminEdit_admin_username'    , 'pAdminCreate_admin_username_text' ),
            'password'        => pacol( 1,          1,      0,      'pass', 'pAdminEdit_admin_password'    , ''                                 ),
            'password2'       => pacol( 1,          1,      0,      'pass', 'pAdminEdit_admin_password2'   , ''                                 , '', '',
               /*not_in_db*/ 1  ),

            'superadmin'      => pacol( 1,          1,      1,      'vbool','pAdminEdit_admin_super_admin' , ''                                 , 0, '',
# TODO: (finally) replace the ALL domain with a column in the admin table
                /*not_in_db*/ 0,
                /*dont_write_to_db*/ 1,
               /*select*/ 'coalesce(__superadmin,0) as superadmin',
               /*extrafrom*/ 'LEFT JOIN ( ' .
                                ' SELECT count(*) AS __superadmin, username AS __superadmin_username FROM ' . table_by_key('domain_admins') .
                                ' WHERE domain = "ALL" GROUP BY username ' .
                             ' ) AS __superadmin on username = __superadmin_username'
            ),

            'domains'         => pacol( 1,          1,      0,      'list', 'pAdminCreate_admin_address'   , ''                                 , '', '',
# TODO: on read: split domains - on write: write to domain_admins table
               /*not_in_db*/ 0,
               /*dont_write_to_db*/ 1,
               /*select*/ 'coalesce(domains,"") as domains'
               /*extrafrom set in domain_count*/
            ),

            'domain_count'    => pacol( 0,          0,      1,      'vnum', ''                             , ''                                 , '', '',
               /*not_in_db*/ 0,
               /*dont_write_to_db*/ 1,
               /*select*/ 'coalesce(__domain_count,0) as domain_count',
               /*extrafrom*/ 'LEFT JOIN ( ' .
                                ' SELECT count(*) AS __domain_count, group_concat(domain) AS domains, username AS __domain_username ' .
                                ' FROM ' . table_by_key('domain_admins') .
                                ' WHERE domain != "ALL" GROUP BY username ' .
                             ' ) AS __domain on username = __domain_username'),

            'active'          => pacol( 1,          1,      1,      'bool', 'pAdminEdit_domain_active'     , ''                                 , 1     ),   # obsoletes pAdminEdit_admin_active
            'created'         => pacol( 0,          0,      1,      'ts',   'created'                      , ''                                 ),
            'modified'        => pacol( 0,          0,      1,      'ts',   'pAdminList_domain_modified'   , ''                                 ), # obsoletes pAdminList_admin_modified
        );

        # TODO: hook to modify $this->struct
    }

    # messages used in various functions.
    # always list the key to hand over to Lang::read
    # the only exception is 'logname' which uses the key for db_log
    protected function initMsg() {
        $this->msg['error_already_exists'] = 'pAdminCreate_admin_username_text_error2'; # TODO: better error message
        $this->msg['error_does_not_exist'] = 'pAdminEdit_admin_result_error'; # TODO: better error message
        if ($this->new) {
            $this->msg['logname'] = 'create_admin';
            $this->msg['store_error'] = 'pAdminCreate_admin_result_error';
        } else {
            $this->msg['logname'] = 'edit_admin';
            $this->msg['store_error'] = 'pAdminEdit_admin_result_error';
        }
    }

    /*
     * Configuration for the web interface
     */
    public function webformConfig() {
        if ($this->new) {
            $successmsg = 'pAdminCreate_admin_result_success';
        } else {
            $successmsg = 'pAdminEdit_admin_result_success';
        }

        return array(
            # $PALANG labels
            'formtitle_create' => 'pAdminCreate_admin_welcome',
            'formtitle_edit' => 'pAdminEdit_admin_welcome',
            'create_button' => 'pAdminCreate_admin_button',
            'successmessage' => $successmsg,

            # various settings
            'required_role' => 'global-admin',
            'listview' => 'list-admin.php',
            'early_init' => 0,
        );
    }

    /**
     * called by $this->store() after storing $this->values in the database
     * can be used to update additional tables, call scripts etc.
     */
    protected function storemore() {
        return false; # TODO: update domain_admins table - and then remove the "return false"
        return true; # TODO: don't hardcode
    }

    /**
     *  @return true on success false on failure
     */
    public function delete() {
        if ( ! $this->view() ) {
            $this->errormsg[] = 'An admin with that name does not exist.'; # TODO: make translatable
            return false;
        }

        $this->errormsg[] = '*** Admin deletion not implemented yet ***';
        return false; # XXX function aborts here until TODO below is implemented! XXX

        # TODO: recursively delete mailboxes, aliases, alias_domains, fetchmail entries etc. before deleting the domain
        # TODO: move the needed code from delete.php here
        $result = db_delete($this->db_table, $this->id_field, $this->id);
        if ( $result == 1 ) {
            list(/*NULL*/,$domain) = explode('@', $this->id);
            db_log ($domain, 'delete_admin', $this->id); # TODO delete_domain is not a valid db_log keyword yet because we don't yet log add/delete domain
            return true;
        }
    }

}

/* vim: set expandtab softtabstop=4 tabstop=4 shiftwidth=4: */
