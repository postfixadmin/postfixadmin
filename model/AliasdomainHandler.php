<?php
# $Id$

/**
 * Handlers User level alias actions - e.g. add alias, get aliases, update etc.
 */
class AliasdomainHandler extends PFAHandler {

    # init $this->struct, $this->db_table and $this->id_field
    protected function initStruct() {
        $this->db_table = 'alias_domain';
        $this->id_field = 'alias_domain';

        $from_domains   = list_domains(); # TODO: include only domains that are not used as alias domain
        $target_domains = list_domains(); # TODO: see above
        # TODO: add public function set_options_for_admin() to list only domains available to that admin

        $this->struct=array(
            # field name                allow       display in...   type    $PALANG label                     $PALANG description                 default / options / ...
            #                           editing?    form    list
            'alias_domain'  => pacol(   $this->new, 1,      1,      'enum', 'pCreate_alias_domain_alias'    , 'pCreate_alias_domain_alias_text' , '',
                /*options*/ $from_domains   ),
            'target_domain' => pacol(   1,          1,      1,      'enum', 'pCreate_alias_domain_target'   , 'pCreate_alias_domain_target_text', '',
                /*options*/ $target_domains ),
            'active'        => pacol(   1,          1,      1,      'bool', 'pAdminEdit_domain_active'      , ''                                 , 1                         ),
            'created'       => pacol(   0,          0,      1,      'ts',   'created'                       , ''                                 ),
            'modified'      => pacol(   0,          0,      1,      'ts',   'pAdminList_domain_modified'    , ''                                 ),
        );

        # TODO: hook to modify $this->struct
    }

    # messages used in various functions.
    # always list the key to hand over to Lang::read
    # the only exception is 'logname' which uses the key for db_log
    protected function initMsg() {
        $this->msg['error_already_exists'] = 'pCreate_alias_domain_error2'; # TODO: better error message
        $this->msg['error_does_not_exist'] = 'pCreate_alias_domain_error2'; # TODO: better error message
        if ($this->new) {
            $this->msg['logname'] = 'create_alias_domain';
            $this->msg['store_error'] = 'pCreate_alias_domain_error3'; # TODO: error message could be better
        } else {
            $this->msg['logname'] = 'edit_alias_domain';
            $this->msg['store_error'] = 'pCreate_alias_domain_error3'; # TODO: error message could be better
        }
    }

   protected function validate_new_id() {
       return true; # alias_domain is enum, so we don't need to check its syntax etc.
   }

    /**
     * called by $this->store() after storing $this->values in the database
     * can be used to update additional tables, call scripts etc.
     */
    protected function storemore() {
        return true; # do nothing, successfully ;-)
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
