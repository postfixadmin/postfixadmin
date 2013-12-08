<?php
# $Id$

/**
 * Handlers User level alias actions - e.g. add alias, get aliases, update etc.
 */
class DomainHandler extends PFAHandler {

    protected $db_table = 'domain';
    protected $id_field = 'domain';
    protected $domain_field = 'domain';

   protected function validate_new_id() {
       $domain_check = check_domain($this->id);

       if ($domain_check == '') {
            return true;
       } else {
            $this->errormsg[$this->id_field] = $domain_check;
            return false;
       }
   }

    # init $this->struct, $this->db_table and $this->id_field
    protected function initStruct() {
        # TODO: shorter PALANG labels ;-)

        $transp = Config::intbool('transport');
        $quota  = Config::intbool('quota');
        $dom_q  = Config::intbool('domain_quota');

        # NOTE: There are dependencies between alias_count, mailbox_count and total_quota.
        # NOTE: If you disable "display in list" for one of them, the SQL query for the others might break.
        # NOTE: (Disabling all of them shouldn't be a problem.)

        $this->struct=array(
            # field name                allow       display in...   type    $PALANG label                    $PALANG description                 default / options / ...
            #                           editing?    form    list
           'domain'          => pacol(  $this->new, 1,      1,      'text', 'domain'                       , ''                                 ),
           'description'     => pacol(  1,          1,      1,      'text', 'description'                  , ''                                 ),
           'aliases'         => pacol(  1,          1,      1,      'num' , 'aliases'                      , 'pAdminEdit_domain_aliases_text'   , Config::read('aliases')   ),
           'alias_count'     => pacol(  0,          0,      1,      'vnum', ''                             , ''                                 , '', '',
               /*not_in_db*/ 0,
               /*dont_write_to_db*/ 1,
               /*select*/ 'coalesce(__alias_count,0) - coalesce(__mailbox_count,0)  as alias_count',
               /*extrafrom*/ 'left join ( select count(*) as __alias_count, domain as __alias_domain from ' . table_by_key('alias') .
                             ' group by domain) as __alias on domain = __alias_domain'),
           'mailboxes'       => pacol(  1,          1,      1,      'num' , 'mailboxes'                    , 'pAdminEdit_domain_aliases_text'   , Config::read('mailboxes') ),
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
           'transport'       => pacol(  $transp,    $transp,$transp,'enum', 'transport'                    , 'pAdminEdit_domain_transport_text' , Config::read('transport_default')     ,
               /*options*/ Config::read('transport_options')    ),
           'backupmx'        => pacol(  1,          1,      1,      'bool', 'pAdminEdit_domain_backupmx'   , ''                                 , 0),
           'active'          => pacol(  1,          1,      1,      'bool', 'active'                       , ''                                 , 1                         ),
           'default_aliases' => pacol(  $this->new, $this->new, 0,  'bool', 'pAdminCreate_domain_defaultaliases', ''                            , 1,'', /*not in db*/ 1     ),
           'created'         => pacol(  0,          0,      1,      'ts',   'created'                      , ''                                 ),
           'modified'        => pacol(  0,          0,      1,      'ts',   'last_modified'                , ''                                 ),
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
            $this->msg['successmessage'] = 'domain_updated';
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
            if (!$this->domain_postcreation()) {
                $this->errormsg[] = Config::lang('domain_postcreate_failed');
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
            $this->errormsg[] = Config::Lang('domain_does_not_exist'); # TODO: can users hit this message at all? init() should already fail...
            return false;
        }

        if (Config::bool('alias_domain')) {
            # check if this domain is an alias domain target - if yes, do not allow to delete it 
            $handler = new AliasdomainHandler(0, $this->admin_username);
            $handler->getList("target_domain = '" . escape_string($this->id) . "'");
            $aliasdomains = $handler->result();

            if (count($aliasdomains) > 0) {
                $this->errormsg[] = Config::Lang_f('delete_domain_aliasdomain_target', $this->id);
                return false;
            }
        }

        # the correct way would be to recursively delete mailboxes, aliases, alias_domains, fetchmail entries 
        # with *Handler before deleting the domain, but this would be terribly slow on domains with many aliases etc., 
        # so we do it the fast way on the database level
        # cleaning up all tables doesn't hurt, even if vacation or displaying the quota is disabled

        # some tables don't have a domain field, so we need a workaround
        $like_domain = "LIKE '" . escape_string('%@' . $this->id) . "'";

        db_delete('domain_admins',         'domain',        $this->id);
        db_delete('alias',                 'domain',        $this->id);
        db_delete('mailbox',               'domain',        $this->id);
        db_delete('alias_domain',          'alias_domain',  $this->id);
        db_delete('vacation',              'domain',        $this->id);
        db_delete('vacation_notification', 'on_vacation',   $this->id, "OR on_vacation $like_domain");
        db_delete('quota',                 'username',      $this->id, "OR username    $like_domain");
        db_delete('quota2',                'username',      $this->id, "OR username    $like_domain");
        db_delete('fetchmail',             'mailbox',       $this->id, "OR mailbox     $like_domain");
        db_delete('log',                   'domain',        $this->id); # TODO: should we really delete the log?

        # finally delete the domain
        db_delete($this->db_table,         $this->id_field, $this->id);

        if ( !$this->domain_postdeletion() ) {
            $this->error_msg[] = $PALANG['domain_postdel_failed'];
        }

        db_log ($this->id, 'delete_domain', $this->id); # TODO delete_domain is not a valid db_log keyword yet
        $this->infomsg[] = Config::Lang_f('pDelete_delete_success', $this->id);
        return true;
    }



    /**
     * get formatted version of fields
     *
     * @param array values of current item
     */
    public function _formatted_aliases  ($item) { return $item['alias_count']   . ' / ' . $item['aliases']  ; }
    public function _formatted_mailboxes($item) { return $item['mailbox_count'] . ' / ' . $item['mailboxes']; }
    public function _formatted_quota    ($item) { return $item['total_quota']   . ' / ' . $item['quota']    ; }

    /**
     * Called after a domain has been added
     *
     * @return boolean
     */
    protected function domain_postcreation() {
        $script=Config::read('domain_postcreation_script');

        if (empty($script)) {
            return true;
        }

        if (empty($this->id)) {
            $this->errormsg[] = 'Empty domain parameter in domain_postcreation';
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
            $this->errormsg[] = 'Problems running domain postcreation script!';
            return FALSE;
        }

        return TRUE;
    }

    /**
     * Called after a domain has been deleted
     *
     * @return boolean
     */
    protected function domain_postdeletion() {
        $script=Config::read('domain_postdeletion_script');

        if (empty($script)) {
            return true;
        }

        if (empty($this->id)) {
            $this->errormsg[] = 'Empty domain parameter in domain_postdeletion';
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
            $this->errormsg[] = 'Problems running domain postdeletion script!';
            return FALSE;
        }

        return TRUE;
    }

}
/* vim: set expandtab softtabstop=4 tabstop=4 shiftwidth=4: */
