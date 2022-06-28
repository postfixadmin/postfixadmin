<?php

# $Id$

/**
 * Handler for fetchmail jobs
 */
class FetchmailHandler extends PFAHandler {
    protected $db_table = 'fetchmail';
    protected $id_field = 'id';
    protected $domain_field = 'domain';
    protected $order_by = 'domain, mailbox';


    protected function initStruct() {
        $src_auth_options = array('password','kerberos_v5','kerberos','kerberos_v4','gssapi','cram-md5','otp','ntlm','msn','ssh','any');
        $src_protocol_options = array('POP3','IMAP','POP2','ETRN','AUTO');

        $extra = Config::intbool('fetchmail_extra_options');

        $this->struct=array(
            # field name                allow       display in...   type    $PALANG label                     $PALANG description                 default / options / ...
            #                           editing?    form    list
            'id'               => pacol(0,          0,      1,      'num' , ''                              , ''                                , '', array(),
                array('dont_write_to_db' => 1) ),
            'domain'           => pacol(0,          0,      1,      'text', ''                              , ''                                ),
            'mailbox'          => pacol(1,          1,      1,      'enum', 'pFetchmail_field_mailbox'      , 'pFetchmail_desc_mailbox'         ), # mailbox list
            'src_server'       => pacol(1,          1,      1,      'text', 'pFetchmail_field_src_server'   , 'pFetchmail_desc_src_server'      ),
            'src_port'         => pacol(1,          1,      1,      'num',  'pFetchmail_field_src_port'     , 'pFetchmail_desc_src_port'        , 0 ),
            'src_auth'         => pacol(1,          1,      1,      'enum', 'pFetchmail_field_src_auth'     , 'pFetchmail_desc_src_auth'        , '', $src_auth_options),
            'src_user'         => pacol(1,          1,      1,      'text', 'pFetchmail_field_src_user'     , 'pFetchmail_desc_src_user'        ),
            'src_password'     => pacol(1,          1,      0,      'b64p', 'pFetchmail_field_src_password' , 'pFetchmail_desc_src_password'    ),
            'src_folder'       => pacol(1,          1,      1,      'text', 'pFetchmail_field_src_folder'   , 'pFetchmail_desc_src_folder'      ),
            'poll_time'        => pacol(1,          1,      1,      'num' , 'pFetchmail_field_poll_time'    , 'pFetchmail_desc_poll_time'       , 10 ),
            'fetchall'         => pacol(1,          1,      1,      'bool', 'pFetchmail_field_fetchall'     , 'pFetchmail_desc_fetchall'        ),
            'keep'             => pacol(1,          1,      1,      'bool', 'pFetchmail_field_keep'         , 'pFetchmail_desc_keep'            ),
            'protocol'         => pacol(1,          1,      1,      'enum', 'pFetchmail_field_protocol'     , 'pFetchmail_desc_protocol'        , '', $src_protocol_options),
            'usessl'           => pacol(1,          1,      1,      'bool', 'pFetchmail_field_usessl'       , 'pFetchmail_desc_usessl'          ),
            'sslcertck'        => pacol(1,          1,      1,      'bool', 'pFetchmail_field_sslcertck'    , ''                                ),
            'sslcertpath'      => pacol($extra,     $extra, $extra, 'text', 'pFetchmail_field_sslcertpath'  , ''                                ),
            'sslfingerprint'   => pacol($extra,     $extra, $extra, 'text', 'pFetchmail_field_sslfingerprint',''                                ),
            'extra_options'    => pacol($extra,     $extra, $extra, 'text', 'pFetchmail_field_extra_options', 'pFetchmail_desc_extra_options'   ),
            'mda'              => pacol($extra,     $extra, $extra, 'text', 'pFetchmail_field_mda'          , 'pFetchmail_desc_mda'             ),
            'date'             => pacol(0,          0,      1,      'text', 'pFetchmail_field_date'         , 'pFetchmail_desc_date'            , '2000-01-01' ),
            'returned_text'    => pacol(0,          0,      1,      'text', 'pFetchmail_field_returned_text', 'pFetchmail_desc_returned_text'   ),
            'active'           => pacol(1,          1,      1,      'bool', 'active'                        , ''                                , 1 ),
            'created'          => pacol(0,          0,      0,      'ts',   'created'                       , ''                                ),
            'modified'         => pacol(0,          0,      1,      'ts',   'last_modified'                 , ''                                ),
        );

        # get list of mailboxes (for currently logged in user)
        $handler = new MailboxHandler(0, $this->admin_username);
        $handler->getList('1=1');
        $this->struct['mailbox']['options'] = array_keys($handler->result);
    }

    protected function initMsg() {
        $this->msg['error_already_exists'] = 'fetchmail_already_exists';
        $this->msg['error_does_not_exist'] = 'fetchmail_does_not_exist';
        $this->msg['confirm_delete'] = 'confirm_delete_fetchmail';

        if ($this->new) {
            $this->msg['logname'] = 'create_fetchmail';
            $this->msg['store_error'] = 'pFetchmail_database_save_error';
            $this->msg['successmessage'] = 'pFetchmail_database_save_success';
        } else {
            $this->msg['logname'] = 'edit_fetchmail';
            $this->msg['store_error'] = 'pFetchmail_database_save_error';
            $this->msg['successmessage'] = 'pFetchmail_database_save_success';
        }
    }

    public function webformConfig() {
        return array(
            # $PALANG labels
            'formtitle_create' => 'pMenu_fetchmail',
            'formtitle_edit' => 'pMenu_fetchmail',
            'create_button' => 'pFetchmail_new_entry',

            # various settings
            'required_role' => 'admin',
            'listview' => 'list.php?table=fetchmail',
            'early_init' => 0,
            'prefill'       => array('mailbox'),
        );
    }


    protected function setmore(array $values) {
        # set domain based on the target mailbox
        if ($this->new || isset($values['mailbox'])) {
            list(/*NULL*/, $domain) = explode('@', $values['mailbox']);
            $this->values['domain'] = $domain;
            $this->domain = $domain;
        }
    }

    protected function validate_new_id() {
        # auto_increment - any non-empty ID is an error
        if ($this->id != '') {
            $this->errormsg[$this->id_field] = 'auto_increment value, you must pass an empty string!';
            return false;
        }

        return true;
    }



    /**
     *  @return boolean
     */
    public function delete() {
        if (! $this->view()) {
            $this->errormsg[] = Config::lang($this->msg['error_does_not_exist']);
            return false;
        }

        db_delete($this->db_table, $this->id_field, $this->id);

        db_log($this->id, 'delete_fetchmail', $this->result['id']);
        $this->infomsg[] = Config::Lang_f('pDelete_delete_success', $this->result['src_user'] . ' -> ' . $this->result['mailbox']);

        return true;
    }


    /**
     * validate src_server - must be non-empty and survive check_domain()
     */
    protected function _validate_src_server($field, $val) {
        if ($val == '') {
            $msg = Config::Lang('pFetchmail_server_missing');
        } else {
            $msg = check_domain($val);
        }

        if ($msg == '') {
            return true;
        } else {
            $this->errormsg[$field] = $msg;
            return false;
        }
    }

    /**
     * validate src_user and src_password - must be non-empty
     * (we can't assume anything about valid usernames and passwords on remote
     * servers, so the validation can't be more strict)
     */
    protected function _validate_src_user($field, $val) {
        if ($val == '') {
            $this->errormsg[$field] = Config::lang('pFetchmail_user_missing');
            return false;
        }
        return true;
    }

    protected function _validate_src_password($field, $val) {
        if ($val == '') {
            $this->errormsg[$field] = Config::lang('pFetchmail_password_missing');
            return false;
        }
        return true;
    }

    /**
     * validate poll interval - must be numeri and > 0
     */
    protected function _validate_poll_time($field, $val) {
        # must be > 0
        if ($val < 1) {
            $this->errormsg[$field] = Config::Lang_f('must_be_numeric_bigger_than_null', $field);
            return false;
        }
        return true;
    }

    public function domain_from_id() {
        return '';
    }
}

/* vim: set expandtab softtabstop=4 tabstop=4 shiftwidth=4: */
