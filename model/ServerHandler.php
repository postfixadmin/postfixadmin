<?php
# $Id$

class ServerHandler extends PFAHandler {
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

    protected function initStruct() {
        $super = $this->is_superadmin;
        $this->struct=array(
           'server'         => pacol( $this->new, 1, 1, 'text', 'server',        ''),
           'description'    => pacol(   1,        1, 1, 'text', 'description',   ''),
           'address'        => pacol(   1,        1, 1, 'text', 'smtpaddress',   'server_address_description'),
           'created'        => pacol(   0,        0, 0, 'ts',   'created',       ''),
           'modified'       => pacol(   0,        0, 0, 'ts',   'last_modified', ''),
           '_can_edit'      => pacol(   0,        0, 1, 'int',  '',              '', 0, array(), 0, 1, (Config::bool('multiple_servers') && $this->is_superadmin) . ' as _can_edit' ),
           '_can_delete'    => pacol(   0,        0, 1, 'int',  '',              '', 0, array(), 0, 1, (Config::bool('multiple_servers') && $this->is_superadmin) . ' as _can_delete' ),
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

    protected function storemore() {
        if ($this->new) {
            if (!$this->server_postcreation()) {
                $this->errormsg[] = Config::lang('server_postcreate_failed');
            }
        }
        return true;
    }

    protected function server_postcreation() {
        $script=Config::read_string('server_postcreation_script');

        if (empty($script)) {
            return true;
        }

        if (empty($this->id)) {
            $this->errormsg[] = 'Empty server parameter in server_postcreation';
            return false;
        }

        $cmdarg1=escapeshellarg($this->id);
        $command= "$script $cmdarg1";
        $retval=0;
        $output=array();
        $firstline='';
        $firstline=exec($command,$output,$retval);
        if (0!=$retval) {
            error_log("Running $command yielded return value=$retval, first line of output=$firstline");
            $this->errormsg[] = 'Problems running server postcreation script!';
            return false;
        }

        return true;
    }

    protected function server_postdeletion() {
        $script=Config::read_string('server_postdeletion_script');

        if (empty($script)) {
            return true;
        }

        if (empty($this->id)) {
        }

        $cmdarg1=escapeshellarg($this->id);
        $command= "$script $cmdarg1";
        $retval=0;
        $output=array();
        $firstline='';
        $firstline=exec($command,$output,$retval);
        if (0!=$retval) {
            error_log("Running $command yielded return value=$retval, first line of output=$firstline");
            return false;
        }

        return true;
    }

    public function delete() {
        if (!(Config::bool('multiple_servers') && $this->is_superadmin)) {
            $this->errormsg[] = Config::Lang_f('no_delete_permissions', $this->id);
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

        if ( !$this->server_postdeletion() ) {
            $this->errormsg[] = Config::lang('server_postdel_failed');
        }

        db_log($this->id, 'delete_server', $this->id);

        $this->infomsg[] = Config::Lang_f('pDelete_delete_success', $this->id);
        return true;
    }
}
