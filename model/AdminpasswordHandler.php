<?php

# $Id$

class AdminpasswordHandler extends PFAHandler {
    protected $db_table = 'admin';
    protected $id_field = 'username';

    # do not skip empty password fields
    protected $skip_empty_pass = false;

    protected function no_domain_field() {
        return true;
    }

    protected function validate_new_id() {
        return true;
    }

    # init $this->struct, $this->db_table and $this->id_field
    protected function initStruct() {
        # TODO: shorter PALANG labels ;-)

        $this->struct=array(
            # field name                allow       display in...   type    $PALANG label                    $PALANG description                 default / options / ...
            #                           editing?    form    list
            'username'         => pacol(0,          1,      1,      'text', 'admin'                        , ''                                 ),
            'oldpass'          => pacol(1,          1,      0,      'pass', 'pPassword_password_current'   , '', '', array(),
                /*not_in_db*/ 1  ),
            'password'         => pacol(1,          1,      0,      'pass', 'pPassword_password'           , ''                                 ),
            'password2'        => pacol(1,          1,      0,      'pass', 'pPassword_password2'          , ''                                 , '', array(),
                /*not_in_db*/ 0,
                /*dont_write_to_db*/ 1,
                /*select*/ 'password as password2'
            ),
        );
    }

    public function init(string $id): bool {
        # hardcode to logged in admin
        if ($this->admin_username == '') {
            die("No admin logged in");
        }
        $this->id = $this->admin_username;
        $this->values['username'] = $this->id;
        $this->struct['username']['default'] = $this->id;

        # hardcode to edit mode
        $this->new = 0;

        return parent::init($this->id);
    }

    public function initMsg() {
        $this->msg['error_already_exists'] = 'admin_already_exists'; # probably unused
        $this->msg['error_does_not_exist'] = 'admin_does_not_exist'; # probably unused
        $this->msg['confirm_delete'] = 'confirm_delete_admin'; # probably unused

        $this->msg['logname'] = 'edit_password';
        $this->msg['store_error'] = 'pPassword_result_error';
        $this->msg['successmessage'] = 'pPassword_result_success';
    }

    public function webformConfig() {
        return array(
            # $PALANG labels
            'formtitle_create' => 'pPassword_welcome',
            'formtitle_edit' => 'pPassword_welcome',
            'create_button' => 'change_password',

            # various settings
            'required_role' => 'admin',
            'listview' => 'main.php',
            'early_init' => 1,

            'hardcoded_edit' => true,
        );
    }

    /**
     * check if old password is correct
     */
    protected function _validate_oldpass($field, $val) {
        $l = new Login('admin');
        if ($l->login($this->id, $val)) {
            return true;
        }

        $this->errormsg[$field] = Config::lang('pPassword_password_current_text_error');
        return false;
    }

    /**
     * skip default validation (check if password is good enough) for old password
     */
    protected function _inp_pass($field, $val) {
        if ($field == 'oldpass') {
            return true;
        }

        return parent::_inp_pass($field, $val);
    }

    /**
     * compare password / password2 field
     * error message will be displayed at the password2 field
     */
    protected function _validate_password2($field, $val) {
        return $this->compare_password_fields('password', 'password2');
    }
}

/* vim: set expandtab softtabstop=4 tabstop=4 shiftwidth=4: */
