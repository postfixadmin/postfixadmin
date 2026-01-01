<?php

# $Id$

class ServerHandler extends PFAHandler
{
    protected $db_table = 'server';
    protected $id_field = 'server';

    protected function validate_new_id() {
        if ($this->id == null || $this->id == "") {
            $this->errormsg[$this->id_field] = Config::lang('server_must_not_be_empty');
            return false;
        } else {
            return true;
        }
    }

    protected function no_domain_field() {
    }

    protected function initStruct()
    {
        $super = $this->is_superadmin;
        $this->struct = array(
            'server'      => self::pacol( $this->new, 1, 1, 'text', 'server',        ''),
            'description' => self::pacol(   1,        1, 1, 'text', 'description',   ''),
            'address'     => self::pacol(   1,        1, 1, 'text', 'smtpaddress',   'server_address_description'),
            'created'     => self::pacol(   0,        0, 0, 'ts',   'created',       ''),
            'modified'    => self::pacol(   0,        0, 0, 'ts',   'last_modified', ''),
            '_can_edit'   => self::pacol(   0,        0, 1, 'int',  '',              '', 0, array(), 0, 1, (Config::bool('multiple_servers') && $this->is_superadmin) . ' as _can_edit' ),
            '_can_delete' => self::pacol(   0,        0, 1, 'int',  '',              '', 0, array(), 0, 1, (Config::bool('multiple_servers') && $this->is_superadmin) . ' as _can_delete' ),
        );
    }

    protected function initMsg() {
        $this->msg['error_already_exists'] = 'server_already_exists';
        $this->msg['error_does_not_exist'] = 'server_does_not_exist';
        $this->msg['confim_delete'] = 'confirm_delete_server';
        if ($this->new) {
            $this->msg['logname'] = 'create_server';
            $this->msg['store_error'] = 'server_create_error';
            $this->msg['successmessage'] = 'server_store_success';
        } else {
            $this->msg['logname'] = 'edit_server';
            $this->msg['store_error'] = 'server_edit_error';
            $this->msg['successmessage'] = 'server_updated';
        }
        $this->msg['can_create'] = Config::bool('multiple_servers') && $this->is_superadmin;
    }

    public function webformConfig() {
        return array(
            'formtitle_create' => 'server_creation',
            'formtitle_edit' => 'server_edit',
            'create_button' => 'server_button',

            'required_role' => 'global-admin',
            'listview' => 'list.php?table=server',
            'early_init' => 0,
        );
    }

    public function delete() {
        if (!(Config::bool('multiple_servers') && $this->is_superadmin)) {
            $this->errormsg[] = Config::Lang_f('no_delete_permission', $this->id);
            return false;
        }

        if ( !$this->view() ) {
            $this->errormsg[] = Config::lang('server_does_not_exist');
            return false;
        }

        $handler = new DomainHandler(0, $this->admin_username);
        $handler->getList(array("primarymx" => $this->id));
        $domains = $handler->result();
        if (count($domains) > 0) {
            $this->errormsg[] = Config::Lang_f('delete_server_domain_target', $this->id);
            return false;
        }

        db_delete($this->db_table, $this->id_field, $this->id);

        db_log($this->id, 'delete_server', $this->id);

        $this->infomsg[] = Config::Lang_f('pDelete_delete_success', $this->id);
        return true;
    }
}	
