<?php
# $Id$

/**
 * Handlers User level alias actions - e.g. add alias, get aliases, update etc.
 */
class DomainHandler extends PFAHandler {

    protected $domain_field = 'domain';

   protected function validate_new_id() {
       $valid = check_domain($this->id);

       if ($valid) {
            return true;
       } else {
            $this->errormsg[$this->id_field] = Lang::read('pAdminCreate_domain_domain_text_error2'); # TODO: half of the errormsg is currently delivered via flash_error() in check_domain
            return false;
       }
   }

    # init $this->struct, $this->db_table and $this->id_field
    protected function initStruct() {
        $this->db_table = 'domain';
        $this->id_field = 'domain';

        # TODO: shorter PALANG labels ;-)
        # TODO: hardcode 'default' to Config::read in pacol()?

        $transp = boolconf('transport')     ? 1 : 0; # TOOD: use a function or write a Config::intbool function
        $quota  = boolconf('quota')         ? 1 : 0; # TOOD: use a function or write a Config::intbool function
        $dom_q  = boolconf('domain_quota')  ? 1 : 0; # TOOD: use a function or write a Config::intbool function

        # NOTE: There are dependencies between alias_count, mailbox_count and total_quota.
        # NOTE: If you disable "display in list" for one of them, the SQL query for the others might break.
        # NOTE: (Disabling all of them shouldn't be a problem.)

        $this->struct=array(
            # field name                allow       display in...   type    $PALANG label                    $PALANG description                 default / options / ...
            #                           editing?    form    list
           'domain'          => pacol(  $this->new, 1,      1,      'text', 'pAdminEdit_domain_domain'     , ''                                 ),
           'description'     => pacol(  1,          1,      1,      'text', 'pAdminEdit_domain_description', ''                                 ),
           'aliases'         => pacol(  1,          1,      1,      'num' , 'pAdminEdit_domain_aliases'    , 'pAdminEdit_domain_aliases_text'   , Config::read('aliases')   ),
           'alias_count'     => pacol(  0,          0,      1,      'vnum', ''                             , ''                                 , '', '',
               /*not_in_db*/ 0,
               /*dont_write_to_db*/ 1,
               /*select*/ 'coalesce(__alias_count,0) - coalesce(__mailbox_count,0)  as alias_count',
               /*extrafrom*/ 'left join ( select count(*) as __alias_count, domain as __alias_domain from ' . table_by_key('alias') .
                             ' group by domain) as __alias on domain = __alias_domain'),
           'mailboxes'       => pacol(  1,          1,      1,      'num' , 'pAdminEdit_domain_mailboxes'  , 'pAdminEdit_domain_mailboxes_text' , Config::read('mailboxes') ),
           'mailbox_count'   => pacol(  0,          0,      1,      'vnum', ''                             , ''                                 , '', '',
               /*not_in_db*/ 0,
               /*dont_write_to_db*/ 1,
               /*select*/ 'coalesce(__mailbox_count,0) as mailbox_count',
               /*extrafrom*/ 'left join ( select count(*) as __mailbox_count, sum(quota) as __total_quota, domain as __mailbox_domain from ' . table_by_key('mailbox') .
                             ' group by domain) as __mailbox on domain = __mailbox_domain'),
           'maxquota'        => pacol(  $quota,     $quota, $quota, 'num' , 'pAdminEdit_domain_maxquota'   , 'pAdminEdit_domain_maxquota_text'  , Config::read('maxquota')  ),
           'total_quota'     => pacol(  0,          0,      1,      'vnum', ''                             , ''                                 , '', '',
               /*not_in_db*/ 0,
               /*dont_write_to_db*/ 1,
               /*select*/ 'round(coalesce(__total_quota/' . intval(Config::read('quota_multiplier')) . ',0)) as total_quota' /*extrafrom*//* already in mailbox_count */ ),
           'quota'           => pacol(  $dom_q,     $dom_q, $dom_q, 'num' , 'pAdminEdit_domain_quota'      , 'pAdminEdit_domain_maxquota_text'  , Config::read('domain_quota_default') ),
           'transport'       => pacol(  $transp,    $transp,$transp,'enum', 'pAdminEdit_domain_transport'  , 'pAdminEdit_domain_transport_text' , Config::read('transport_default')     ,
               /*options*/ Config::read('transport_options')    ),
           'backupmx'        => pacol(  1,          1,      1,      'bool', 'pAdminEdit_domain_backupmx'   , ''                                 ),
           'active'          => pacol(  1,          1,      1,      'bool', 'pAdminEdit_domain_active'     , ''                                 , 1                         ),
           'default_aliases' => pacol(  $this->new, $this->new, 0,  'bool', 'pAdminCreate_domain_defaultaliases', ''                            , 1,'', /*not in db*/ 1     ),
           'created'         => pacol(  0,          0,      1,      'ts',   'created'                      , ''                                 ),
           'modified'        => pacol(  0,          0,      1,      'ts',   'pAdminList_domain_modified'   , ''                                 ),
        );
    }

    protected function initMsg() {
        $this->msg['error_already_exists'] = 'pAdminCreate_domain_domain_text_error';
        $this->msg['error_does_not_exist'] = 'domain_does_not_exist';
        if ($this->new) {
            $this->msg['logname'] = 'create_domain';
            $this->msg['store_error'] = 'pAdminCreate_domain_result_error';
            $this->msg['successmessage'] = 'pAdminCreate_domain_result_success';
        } else {
            $this->msg['logname'] = 'edit_domain';
            $this->msg['store_error'] = 'pAdminEdit_domain_result_error';
            $this->msg['successmessage'] = 'pAdminCreate_domain_result_success'; # TODO: better message for edit
        }
    }

    public function webformConfig() {
        return array(
            # $PALANG labels
            'formtitle_create' => 'pAdminCreate_domain_welcome',
            'formtitle_edit' => 'pAdminEdit_domain_welcome',
            'create_button' => 'pAdminCreate_domain_button',

            # various settings
            'required_role' => 'global-admin',
            'listview' => 'list-domain.php',
            'early_init' => 0,
        );
    }

    /**
     * called by $this->store() after storing $this->values in the database
     * can be used to update additional tables, call scripts etc.
     */
    protected function storemore() {
        if ($this->new && $this->values['default_aliases']) {
            foreach (Config::read('default_aliases') as $address=>$goto) {
                $address = $address . "@" . $this->id;
                # TODO: use AliasHandler->add instead of writing directly to the alias table
                $arr = array(
                    'address' => $address,
                    'goto' => $goto,
                    'domain' => $this->id,
                );
                $result = db_insert ('alias', $arr);
                # TODO: error checking
            }
        }
        if ($this->new) {
            $tMessage = Lang::read('pAdminCreate_domain_result_success') . " (" . $this->id . ")"; # TODO: tMessage is not used/returned anywhere
        } else {
            # TODO: success message for edit
        }

        if ($this->new) {
            if (!domain_postcreation($this->id)) {
                $this->errormsg[] = Lang::read('pAdminCreate_domain_error');
            }
        } else {
            # we don't have domain_postedit()
        }
        return true; # TODO: don't hardcode
    }

    /**
     *  @return true on success false on failure
     */
    public function delete() {
        if ( ! $this->view() ) {
            $this->errormsg[] = 'A domain with that name does not exist.'; # TODO: make translatable
            return false;
        }

        $this->errormsg[] = '*** Domain deletion not implemented yet ***';
        return false; # XXX function aborts here until TODO below is implemented! XXX

        # TODO: recursively delete mailboxes, aliases, alias_domains, fetchmail entries etc. before deleting the domain
        # TODO: move the needed code from delete.php here
        $result = db_delete($this->db_table, $this->id_field, $this->id);
        if ( $result == 1 ) {
            list(/*NULL*/,$domain) = explode('@', $this->id);
            db_log ($domain, 'delete_domain', $this->id); # TODO delete_domain is not a valid db_log keyword yet because we don't yet log add/delete domain
            return true;
        }
    }

}

/* vim: set expandtab softtabstop=4 tabstop=4 shiftwidth=4: */
