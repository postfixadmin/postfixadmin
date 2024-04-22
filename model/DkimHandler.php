<?php

# $Id$

/**
 * Handler for domain keys
 */
class DkimHandler extends PFAHandler
{
    protected $db_table = 'dkim';
    protected $id_field = 'id';
    protected $label_field = 'description';
    protected $domain_field = 'domain_name';
    protected $order_by = 'domain_name, selector';


    protected function initStruct()
    {
        $this->struct = array(
            # field name                allow       display in...   type     $PALANG label           $PALANG description          default / options / ...
            #                           editing?    form    list
            'id'               => pacol(0,          0,      1,      'num' , 'pFetchmail_field_id' , ''                         , '', array(), array('dont_write_to_db' => 1)),
            'description'      => pacol(1,          1,      1,      'text', 'description'         , ''),
            'selector'         => pacol(1,          1,      1,      'text', 'pDkim_field_selector', 'pDkim_field_selector_desc'),
            'domain_name'      => pacol(1,          1,      1,      'enum', 'domain'              , 'pDkim_field_domain_desc'  , '', $this->allowed_domains),
            'private_key'      => pacol(1,          1,      0,      'txta', 'pDkim_field_pkey'    , 'pDkim_field_pkey_desc'),
            'public_key'       => pacol(1,          1,      0,      'txta', 'pDkim_field_pub'     , 'pDkim_field_pub_desc'),
        );
    }

    protected function initMsg()
    {
        $this->msg['error_already_exists'] = 'dkim_already_exists';
        $this->msg['error_does_not_exist'] = 'dkim_does_not_exist';
        $this->msg['confirm_delete'] = 'confirm_delete_dkim';

        if ($this->new) {
            $this->msg['logname'] = 'create_dkim_entry';
            $this->msg['store_error'] = 'pFetchmail_database_save_error';
            $this->msg['successmessage'] = 'pFetchmail_database_save_success';
        } else {
            $this->msg['logname'] = 'edit_dkim_entry';
            $this->msg['store_error'] = 'pFetchmail_database_save_error';
            $this->msg['successmessage'] = 'pFetchmail_database_save_success';
        }
    }

    public function webformConfig()
    {
        $required_role = 'global-admin';
        if (Config::bool('dkim_all_admins')) {
            $required_role = 'admin';
        }

        return array(
            # $PALANG labels
            'formtitle_create' => 'pDkim_new_key',
            'formtitle_edit' => 'pDkim_edit_key',
            'create_button' => 'pFetchmail_new_entry',

            # various settings
            'required_role' => $required_role,
            'listview' => 'list.php?table=dkim',
            'early_init' => 0,
        );
    }
    protected function validate_new_id()
    {
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
    public function delete()
    {
        if (! $this->view()) {
            $this->errormsg[] = Config::Lang($this->msg['error_does_not_exist']);
            return false;
        }

        db_delete($this->db_table, $this->id_field, $this->id);

        db_log($this->result['domain_name'], 'delete_dkim_entry', $this->result['description'] ?? '');
        $this->infomsg[] = Config::Lang_f('pDelete_delete_success', $this->result['label']);
        return true;
    }

    public function domain_from_id()
    {
        return '';
    }
}

/* vim: set expandtab softtabstop=4 tabstop=4 shiftwidth=4: */
