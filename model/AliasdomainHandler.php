<?php

# $Id$

/**
 * Handlers User level alias actions - e.g. add alias, get aliases, update etc.
 */
class AliasdomainHandler extends PFAHandler {
    protected $db_table = 'alias_domain';
    protected $id_field = 'alias_domain';
    protected $domain_field = 'alias_domain';
    protected $searchfields = array('alias_domain', 'target_domain');

    protected function initStruct() {
        $this->struct=array(
            # field name                allow       display in...   type    $PALANG label                     $PALANG description                 default / options / ...
            #                           editing?    form    list
            'alias_domain'     => pacol($this->new, 1,      1,      'enum', 'pCreate_alias_domain_alias'    , 'pCreate_alias_domain_alias_text' , '',
                /*options, filled below*/ array(),
                /* multiopt */ array('linkto' => 'list-virtual.php?domain=%s') ),
            'target_domain'    => pacol(1,          1,      1,      'enum', 'pCreate_alias_domain_target'   , 'pCreate_alias_domain_target_text', '',
                /*options*/ array() /* filled below */  ),
            'created'          => pacol(0,          0,      0,      'ts',   'created'                       , ''                                 ),
            'modified'         => pacol(0,          0,      1,      'ts',   'last_modified'                 , ''                                 ),
            'active'           => pacol(1,          1,      1,      'bool', 'active'                        , ''                                 , 1   ),
        );


        # check which domains are available as an alias- or target-domain
        $this->getList("");
        $used_targets = array();

        foreach ($this->allowed_domains as $dom) {
            if (isset($this->result[$dom])) { # already used as alias_domain
                $used_targets[$this->result[$dom]['target_domain']] = $this->result[$dom]['target_domain'];
            } else { # might be available
                $this->struct['alias_domain']['options'][$dom] = $dom;
                $this->struct['target_domain']['options'][$dom] = $dom;
            }
        }

        foreach ($this->struct['alias_domain']['options'] as $dom) {
            if (isset($used_targets[$dom])) {
                # don't allow chained domain aliases (domain1 -> domain2 -> domain3)
                unset($this->struct['alias_domain']['options'][$dom]);
            }
        }

        if (count($this->struct['alias_domain']['options']) == 1) { # only one alias_domain available - filter it out from target_domain list
            $keys = array_keys($this->struct['alias_domain']['options']);
            unset($this->struct['target_domain']['options'][$keys[0]]);
        }
    }

    public function init(string $id): bool {
        $success = parent::init($id);
        if ($success) {
            if (count($this->struct['alias_domain']['options']) == 0 && $this->new) {
                $this->errormsg[] = Config::lang('pCreate_alias_domain_error4');
                return false;
            }
            # TODO: check if target domains are available (in new and edit mode)
        }
        return $success;
    }

    protected function initMsg() {
        $this->msg['error_already_exists'] = 'alias_domain_already_exists';
        $this->msg['error_does_not_exist'] = 'alias_domain_does_not_exist';
        $this->msg['confirm_delete'] = 'confirm_delete_aliasdomain';

        if ($this->new) {
            $this->msg['logname'] = 'create_alias_domain';
            $this->msg['store_error'] = 'alias_domain_create_failed';
            $this->msg['successmessage'] = 'pCreate_alias_domain_success';
        } else {
            $this->msg['logname'] = 'edit_alias_domain';
            $this->msg['store_error'] = 'alias_domain_change_failed';
            $this->msg['successmessage'] = 'alias_domain_changed';
        }
    }

    public function webformConfig() {
        return array(
            # $PALANG labels
            'formtitle_create' => 'pCreate_alias_domain_welcome',
            'formtitle_edit' => 'pCreate_alias_domain_welcome',
            'create_button' => 'add_alias_domain',

            # various settings
            'required_role' => 'admin',
            'listview' => 'list-virtual.php',
            'early_init' => 1, # 0 for create-domain
            'prefill'       => array('alias_domain', 'target_domain'),
        );
    }

    protected function validate_new_id() {
        return true; # alias_domain is enum, so we don't need to check its syntax etc.
    }


    /**
     *  @return boolean
     */
    public function delete() {
        if (! $this->view()) {
            $this->errormsg[] = 'An alias domain with that name does not exist!'; # TODO: make translatable? (will a user ever see this?)
            return false;
        }

        db_delete($this->db_table, $this->id_field, $this->id);

        db_log($this->id, 'delete_alias_domain', $this->result['target_domain']);
        $this->infomsg[] = Config::Lang_f('pDelete_delete_success', $this->result['alias_domain'] . ' -> ' . $this->result['target_domain']);

        return true;
    }

    /**
     * validate target_domain field - it must be != $this->id to avoid a loop
     * @return boolean
     */
    protected function _validate_target_domain($field, $val) {
        if ($val == $this->id) {
            $this->errormsg[$field] = Config::lang('alias_domain_to_itsself');
            return false;
        }
        return true;
    }
}

/* vim: set expandtab softtabstop=4 tabstop=4 shiftwidth=4: */
