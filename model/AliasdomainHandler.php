<?php
# $Id$

/**
 * Handlers User level alias actions - e.g. add alias, get aliases, update etc.
 */
class AliasdomainHandler extends PFAHandler {

    protected $db_table = 'alias_domain';
    protected $id_field = 'alias_domain';
    protected $domain_field = 'alias_domain';

    # init $this->struct, $this->db_table and $this->id_field
    protected function initStruct() {
        # TODO: add public function set_options_for_admin() to list only domains available to that admin

        $this->struct=array(
            # field name                allow       display in...   type    $PALANG label                     $PALANG description                 default / options / ...
            #                           editing?    form    list
            'alias_domain'  => pacol(   $this->new, 1,      1,      'enum', 'pCreate_alias_domain_alias'    , 'pCreate_alias_domain_alias_text' , '',
                /*options*/ array() /* filled below */  ),
            'target_domain' => pacol(   1,          1,      1,      'enum', 'pCreate_alias_domain_target'   , 'pCreate_alias_domain_target_text', '',
                /*options*/ array() /* filled below */  ),
            'active'        => pacol(   1,          1,      1,      'bool', 'active'                        , ''                                 , 1                         ),
            'created'       => pacol(   0,          0,      1,      'ts',   'created'                       , ''                                 ),
            'modified'      => pacol(   0,          0,      1,      'ts',   'last_modified'                 , ''                                 ),
        );


        # check which domains are available as an alias- or target-domain
        $this->getList("");
        $used_targets = array();

        foreach ($this->allowed_domains as $dom) {
            if (isset($this->return[$dom]) ) { # already used as alias_domain
                $used_targets[$this->return[$dom]['target_domain']] = $this->return[$dom]['target_domain'];
            } else { # might be available
                $this->struct['alias_domain']['options'][$dom] = $dom;
                $this->struct['target_domain']['options'][$dom] = $dom;
            }
        }

        foreach ($this->struct['alias_domain']['options'] as $dom) {
            if (isset($used_targets[$dom])) unset ($this->struct['alias_domain']['options'][$dom]); # don't allow chained domain aliases (domain1 -> domain2 -> domain3)
        }

        if (count($this->struct['alias_domain']['options']) == 1) { # only one alias_domain available - filter it out from target_domain list
            $keys = array_keys($this->struct['alias_domain']['options']);
            unset ($this->struct['target_domain']['options'][$keys[0]]);
        }
    }

    public function init($id) {
        $success = parent::init($id);
        if ($success) {
            if (count($this->struct['alias_domain']['options']) == 0 && $this->new) {
               $this->errormsg[] = Lang::read('pCreate_alias_domain_error4');
               return false;
            }
            # TODO: check if target domains are available (in new and edit mode)
        }
        return $success;
    }

    protected function initMsg() {
        $this->msg['error_already_exists'] = 'pCreate_alias_domain_error2'; # TODO: better error message
        $this->msg['error_does_not_exist'] = 'pCreate_alias_domain_error2'; # TODO: better error message
        if ($this->new) {
            $this->msg['logname'] = 'create_alias_domain';
            $this->msg['store_error'] = 'pCreate_alias_domain_error3'; # TODO: error message could be better
            $this->msg['successmessage'] = 'pCreate_alias_domain_success';
        } else {
            $this->msg['logname'] = 'edit_alias_domain';
            $this->msg['store_error'] = 'pCreate_alias_domain_error3'; # TODO: error message could be better
            $this->msg['successmessage'] = 'pCreate_alias_domain_success'; # TODO: better message for edit
        }
    }

    public function webformConfig() {
        return array(
            # $PALANG labels
            'formtitle_create' => 'pCreate_alias_domain_welcome',
            'formtitle_edit' => 'pCreate_alias_domain_welcome',
            'create_button' => 'add-alias-domain',

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
     *  @return true on success false on failure
     */
    public function delete() {
        if ( ! $this->view() ) {
            $this->errormsg[] = 'An alias domain with that name does not exist.'; # TODO: make translatable / move to $this->msg
            return false;
        }

        $this->errormsg[] = '*** Alias domain deletion not implemented yet ***';
        return false; # XXX function aborts here until TODO below is implemented! XXX

        # TODO: move the needed code from delete.php here
        $result = db_delete($this->db_table, $this->id_field, $this->id);
        if ( $result == 1 ) {
            list(/*NULL*/,$domain) = explode('@', $this->id);
            db_log ($domain, 'delete_alias_domain', $this->id);
            return true;
        } else {
            $this->errormsg[] = $PALANG['pAdminDelete_alias_domain_error'];
            return false;
        }
    }

    /**
     * validate target_domain field - it must be != $this->id to avoid a loop
     */
    protected function _field_target_domain($field, $val) {
        if ($val == $this->id) {
            $this->errormsg[$field] = Lang::read('pCreate_alias_domain_error2'); # TODO: error message could be better...
            return false;
        }
        return true;
    }

}

/* vim: set expandtab softtabstop=4 tabstop=4 shiftwidth=4: */
