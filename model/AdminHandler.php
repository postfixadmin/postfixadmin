<?php

# $Id$

class AdminHandler extends PFAHandler {
    protected $db_table = 'admin';
    protected $id_field = 'username';

    protected function validate_new_id() {
        $email_check = check_email($this->id);

        if ($email_check == '') {
            return true;
        } else {
            $this->errormsg[] = $email_check;
            $this->errormsg[$this->id_field] = Config::lang('pAdminCreate_admin_username_text_error1');
            return false;
        }
    }

    protected function no_domain_field() {
        # PFAHandler die()s if domain field is not set. Disable this behaviour for AdminHandler.
    }

    # init $this->struct, $this->db_table and $this->id_field
    protected function initStruct() {
        # NOTE: There are dependencies between domains and domain_count
        # NOTE: If you disable "display in list" for domain_count, the SQL query for domains might break.
        # NOTE: (Disabling both shouldn't be a problem.)

        # TODO: move to a db_group_concat() function?
        if (db_pgsql()) {
            $domains_grouped = "array_to_string(array_agg(domain), ',')";
        } else { # mysql
            $domains_grouped = 'group_concat(domain)';
        }

        $passwordReset = (int) Config::bool('forgotten_admin_password_reset');

        $reset_by_sms = 0;
        if ($passwordReset && Config::read('sms_send_function')) {
            $reset_by_sms = 1;
        }

        $this->struct=array(
            # field name                allow       display in...   type    $PALANG label          $PALANG description   default / options / ...
            #                           editing?    form    list
            'username'         => pacol($this->new, 1,      1,      'text', 'admin'              , 'email_address'     , '', array(),
                array('linkto' => 'list.php?table=domain&username=%s') ),
            'password'         => pacol(1,          1,      0,      'pass', 'password'           , ''                  ),
            'password2'        => pacol(1,          1,      0,      'pass', 'password_again'     , ''                  , '', array(),
                /*not_in_db*/ 0,
                /*dont_write_to_db*/ 1,
                /*select*/ 'password as password2'
            ),

            'superadmin'       => pacol(1,          1,      0,      'bool', 'super_admin'        , 'super_admin_desc'  , 0
# TODO: (finally) replace the ALL domain with a column in the admin table
# TODO: current status: 'superadmin' column exists and is written when storing an admin with AdminHandler,
# TODO: but the superadmin status is still (additionally) stored in the domain_admins table ("ALL" dummy domain)
# TODO: to keep the database backwards-compatible with 2.3.x.
# TODO: Note: superadmins created with 2.3.x after running upgrade_1284() will not work until you re-run upgrade_1284()
# TODO: Create them with the trunk version to avoid this problem.
            ),

            'domains'          => pacol(1,          1,      0,      'list', 'domain'             , ''                  , array(), list_domains(),
               /*not_in_db*/ 0,
               /*dont_write_to_db*/ 1,
               /*select*/ "coalesce(domains,'') as domains"
               /*extrafrom set in domain_count*/
            ),

            'domain_count'     => pacol(0,          0,      1,      'vnum', 'pAdminList_admin_count', ''               , '', array(),
               /*not_in_db*/ 0,
               /*dont_write_to_db*/ 1,
               /*select*/ 'coalesce(__domain_count,0) as domain_count',
               /*extrafrom*/ 'LEFT JOIN ( ' .
                                ' SELECT count(*) AS __domain_count, ' . $domains_grouped . ' AS domains, username AS __domain_username ' .
                                ' FROM ' . table_by_key('domain_admins') .
                                " WHERE domain != 'ALL' GROUP BY username " .
                             ' ) AS __domain on username = __domain_username'),

            'active'           => pacol(1,          1,      1,      'bool', 'active'             , ''                  , 1     ),
            'phone'            => pacol(1,  $reset_by_sms,  0,      'text', 'pCreate_mailbox_phone', 'pCreate_mailbox_phone_desc', ''),
            'email_other'      => pacol(1,  $passwordReset, 0,      'mail', 'pCreate_mailbox_email', 'pCreate_mailbox_email_desc', ''),
            'token'            => pacol(1,          0,      0,      'text', ''                   , ''                  ),
            'token_validity'   => pacol(1,          0,      0,      'ts',   ''                   , '', date("Y-m-d H:i:s",time())),
            'created'          => pacol(0,          0,      0,      'ts',   'created'            , ''                  ),
            'modified'         => pacol(0,          0,      1,      'ts',   'last_modified'      , ''                  ),
        );
    }

    protected function initMsg() {
        $this->msg['error_already_exists'] = 'admin_already_exists';
        $this->msg['error_does_not_exist'] = 'admin_does_not_exist';
        $this->msg['confirm_delete'] = 'confirm_delete_admin';

        if ($this->new) {
            $this->msg['logname'] = 'create_admin';
            $this->msg['store_error'] = 'pAdminCreate_admin_result_error';
            $this->msg['successmessage'] = 'pAdminCreate_admin_result_success';
        } else {
            $this->msg['logname'] = 'edit_admin';
            $this->msg['store_error'] = 'pAdminEdit_admin_result_error';
            $this->msg['successmessage'] = 'pAdminEdit_admin_result_success';
        }
    }

    public function webformConfig() {
        return array(
            # $PALANG labels
            'formtitle_create' => 'pAdminCreate_admin_welcome',
            'formtitle_edit' => 'pAdminEdit_admin_welcome',
            'create_button' => 'pAdminCreate_admin_button',

            # various settings
            'required_role' => 'global-admin',
            'listview' => 'list.php?table=admin',
            'early_init' => 0,
        );
    }

    /**
     * called by $this->store() after storing $this->values in the database
     * can be used to update additional tables, call scripts etc.
     */
    protected function postSave(): bool {
        # store list of allowed domains in the domain_admins table
        if (isset($this->values['domains'])) {
            if (is_array($this->values['domains'])) {
                $domains = $this->values['domains'];
            } elseif ($this->values['domains'] == '') {
                $domains = array();
            } else {
                $domains = explode(',', $this->values['domains']);
            }

            db_delete('domain_admins', 'username', $this->id, "AND domain != 'ALL'");

            foreach ($domains as $domain) {
                $values = array(
                    'username'  => $this->id,
                    'domain'    => $domain,
                );
                db_insert('domain_admins', $values, array('created'));
                # TODO: check for errors
            }
        }

        # Temporary workaround to keep the database compatible with 2.3.x
        if (isset($this->values['superadmin'])) {
            if ($this->values['superadmin'] == 1) {
                $values = array(
                    'username'  => $this->id,
                    'domain'    => 'ALL',
                );
                $where = db_where_clause(array('username' => $this->id, 'domain' => 'ALL'), $this->struct);
                $result = db_query_one("SELECT username from " . table_by_key('domain_admins') . " " . $where);
                if (empty($result)) {
                    db_insert('domain_admins', $values, array('created'));
                    # TODO: check for errors
                }
            } else {
                db_delete('domain_admins', 'username', $this->id, "AND domain = 'ALL'");
            }
        }

        return true; # TODO: don't hardcode
    }

    protected function read_from_db_postprocess($db_result) {
        foreach ($db_result as $key => $row) {
            # convert 'domains' field to an array
            if ($row['domains'] == '') {
                $db_result[$key]['domains'] = array();
            } else {
                $db_result[$key]['domains'] = explode(',', $row['domains']);
            }
            if ($row['superadmin']) {
                $db_result[$key]['domain_count'] = Config::lang('super_admin');
            }
        }
        return $db_result;
    }

    /**
     *  @return bool
     */
    public function delete() {
        if (! $this->view()) {
            $this->errormsg[] = Config::Lang($this->msg['error_does_not_exist']);
            return false;
        }

        db_delete('domain_admins', $this->id_field, $this->id);
        db_delete($this->db_table, $this->id_field, $this->id);

        db_log('admin', 'delete_admin', $this->id); # TODO delete_admin is not a valid db_log keyword yet, and 'admin' is not displayed in viewlog.php
        $this->infomsg[] = Config::Lang_f('pDelete_delete_success', $this->id);
        return true;
    }


    # TODO: generate password if $new, no password specified and $CONF['generate_password'] is set
    # TODO: except if $this->admin_username == setup.php --- this exception should be handled directly in setup.php ("if $values['password'] == '' error_out")

    /**
     * compare password / password2 field
     * error message will be displayed at the password2 field
     */
    protected function _validate_password2($field, $val) {
        return $this->compare_password_fields('password', 'password2');
    }
}

/* vim: set expandtab softtabstop=4 tabstop=4 shiftwidth=4: */
