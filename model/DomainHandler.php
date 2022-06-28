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

        if ($domain_check != '') {
            $this->errormsg[$this->id_field] = $domain_check;
            return false;
        }

        if (Config::read('vacation_domain') == $this->id) {
            $this->errormsg[$this->id_field] = Config::Lang('domain_conflict_vacation_domain');
            return false;
        }

        # still here? good.
        return true;
    }

    protected function initStruct() {
        # TODO: shorter PALANG labels ;-)

        $super = $this->is_superadmin;

        $transp = min($super, Config::intbool('transport'));
        $editquota  = min($super, Config::intbool('quota'));
        $quota  = Config::intbool('quota');
        $edit_dom_q  = min($super, Config::intbool('domain_quota'), $quota);
        $dom_q  = min(Config::intbool('domain_quota'), $quota);
        $pwexp = min($super, Config::intbool('password_expiration'));

        $query_used_domainquota = 'round(coalesce(__total_quota/' . intval(Config::read('quota_multiplier')) . ',0))';

        # NOTE: There are dependencies between alias_count, mailbox_count and total_quota.
        # NOTE: If you disable "display in list" for one of them, the SQL query for the others might break.
        # NOTE: (Disabling all of them shouldn't be a problem.)
        #

        // https://github.com/postfixadmin/postfixadmin/issues/299
        $domain_quota_default = Config::read('domain_quota_default');
        if ($domain_quota_default === null) {
            $domain_quota_default = -1;
        }

        $this->struct=array(
            # field name                allow       display in...   type    $PALANG label                    $PALANG description                 default / options / ...
            #                           editing?    form    list
           'domain'            => pacol($this->new, 1,      1,      'text', 'domain'                       , ''                                 , '', array(),
               array('linkto' => 'list-virtual.php?domain=%s') ),
           'description'       => pacol($super,     $super, $super, 'text', 'description'                  , ''                                 ),

           # Aliases
           'aliases'           => pacol($super,     $super, 0,      'num' , 'aliases'                      , 'pAdminEdit_domain_aliases_text'   , Config::read('aliases')   ),
           'alias_count'       => pacol(0,          0,      1,      'vnum', ''                             , ''                                 , '', array(),
               /*not_in_db*/ 0,
               /*dont_write_to_db*/ 1,
               /*select*/ 'coalesce(__alias_count,0) - coalesce(__mailbox_count,0)  as alias_count',
               /*extrafrom*/ 'left join ( select count(*) as __alias_count, domain as __alias_domain from ' . table_by_key('alias') .
                             ' group by domain) as __alias on domain = __alias_domain'),
            'aliases_quot'     => pacol(0,          0,      1,      'quot', 'aliases'                      , ''                                  , 0, array(),
                array('select' => db_quota_text(   '__alias_count - coalesce(__mailbox_count,0)', 'aliases', 'aliases_quot'))   ),
            '_aliases_quot_percent' => pacol( 0, 0,      1,      'vnum', ''                   ,''                   , 0, array(),
                array('select' => db_quota_percent('__alias_count - coalesce(__mailbox_count,0)', 'aliases', '_aliases_quot_percent'))   ),

            # Mailboxes
           'mailboxes'         => pacol($super,     $super, 0,      'num' , 'mailboxes'                    , 'pAdminEdit_domain_aliases_text'   , Config::read('mailboxes') ),
           'mailbox_count'     => pacol(0,          0,      1,      'vnum', ''                             , ''                                 , '', array(),
               /*not_in_db*/ 0,
               /*dont_write_to_db*/ 1,
               /*select*/ 'coalesce(__mailbox_count,0) as mailbox_count',
               /*extrafrom*/ 'left join ( select count(*) as __mailbox_count, sum(quota) as __total_quota, domain as __mailbox_domain from ' . table_by_key('mailbox') .
                             ' group by domain) as __mailbox on domain = __mailbox_domain'),
            'mailboxes_quot'   => pacol(0,          0,      1,       'quot', 'mailboxes'                    , ''                                 , 0, array(),
                array('select' => db_quota_text(   '__mailbox_count', 'mailboxes', 'mailboxes_quot'))   ),
            '_mailboxes_quot_percent' => pacol( 0,  0,      1,       'vnum', ''                             , ''                                 , 0, array(),
                array('select' => db_quota_percent('__mailbox_count', 'mailboxes', '_mailboxes_quot_percent'))   ),

           'maxquota'          => pacol($editquota,$editquota,$quota, 'num', 'pOverview_get_quota'          , 'pAdminEdit_domain_maxquota_text'  , Config::read('maxquota')  ),

            # Domain quota
            'quota'            => pacol($edit_dom_q,$edit_dom_q, 0, 'num',  'pAdminEdit_domain_quota'      , 'pAdminEdit_domain_maxquota_text'  , $domain_quota_default ),
            'total_quota'      => pacol(0,          0,      1,      'vnum', ''                             , ''                                 , '', array(),
                array('select' => "$query_used_domainquota AS total_quota") /*extrafrom*//* already in mailbox_count */ ),
            'total_quot'     => pacol( 0,          0,      $dom_q,  'quot', 'pAdminEdit_domain_quota'      , ''                                 , 0, array(),
                array('select' => db_quota_text(   $query_used_domainquota, 'quota', 'total_quot'))   ),
            '_total_quot_percent'=> pacol( 0,      0,      $dom_q,  'vnum', ''                             , ''                                 , 0, array(),
                array('select' => db_quota_percent($query_used_domainquota, 'quota', '_total_quot_percent'))   ),

           'transport'         => pacol($transp,    $transp,$transp,'enum', 'transport'                    , 'pAdminEdit_domain_transport_text' , Config::read('transport_default')     ,
               /*options*/ Config::read_array('transport_options')    ),
           'backupmx'          => pacol($super,     $super, 1,      'bool', 'pAdminEdit_domain_backupmx'   , ''                                 , 0),
           'active'            => pacol($super,     $super, 1,      'bool', 'active'                       , ''                                 , 1                         ),
           'default_aliases'   => pacol($this->new, $this->new, 0,  'bool', 'pAdminCreate_domain_defaultaliases', ''                            , 1,array(), /*not in db*/ 1     ),
           'created'           => pacol(0,          0,      0,      'ts',   'created'                      , ''                                 ),
           'modified'          => pacol(0,          0,      $super, 'ts',   'last_modified'                , ''                                 ),
           'password_expiry'   => pacol($super,     $pwexp, $pwexp, 'num',  'password_expiration'          , 'password_expiration_desc'         , 365),
            '_can_edit'        => pacol(0,          0,      1,      'int', ''                             , ''                                , 0 ,
                /*options*/ array(),
                /*not_in_db*/ 0,
                /*dont_write_to_db*/ 1,
                /*select*/ $this->is_superadmin . ' as _can_edit'              ),
            '_can_delete'      => pacol(0,          0,      1,      'int', ''                             , ''                                , 0 ,
                /*options*/ array(),
                /*not_in_db*/ 0,
                /*dont_write_to_db*/ 1,
                /*select*/ $this->is_superadmin . ' as _can_delete'            ),
        );
    }

    protected function initMsg() {
        $this->msg['error_already_exists'] = 'pAdminCreate_domain_domain_text_error';
        $this->msg['error_does_not_exist'] = 'domain_does_not_exist';
        $this->msg['confirm_delete'] = 'confirm_delete_domain';

        if ($this->new) {
            $this->msg['logname'] = 'create_domain';
            $this->msg['store_error'] = 'pAdminCreate_domain_result_error';
            $this->msg['successmessage'] = 'pAdminCreate_domain_result_success';
        } else {
            $this->msg['logname'] = 'edit_domain';
            $this->msg['store_error'] = 'pAdminEdit_domain_result_error';
            $this->msg['successmessage'] = 'domain_updated';
        }
        $this->msg['can_create'] = $this->is_superadmin;
    }

    public function webformConfig() {
        return array(
            # $PALANG labels
            'formtitle_create' => 'pAdminCreate_domain_welcome',
            'formtitle_edit' => 'pAdminEdit_domain_welcome',
            'create_button' => 'pAdminCreate_domain_button',

            # various settings
            'required_role' => 'admin',
            'listview' => 'list.php?table=domain',
            'early_init' => 0,
        );
    }


    protected function preSave(): bool {
        # TODO: is this function superfluous? _can_edit should already cover this
        if ($this->is_superadmin) {
            return true;
        }
        $this->errormsg[] = Config::Lang_f('edit_not_allowed', $this->id);
        return false;
    }

    /**
     * called by $this->store() after storing $this->values in the database
     * can be used to update additional tables, call scripts etc.
     */
    protected function postSave(): bool {
        if ($this->new && $this->values['default_aliases']) {
            foreach (Config::read_array('default_aliases') as $address=>$goto) {
                $address = $address . "@" . $this->id;
                # if $goto doesn't contain @, let the alias point to the same domain
                if (!strstr($goto, '@')) {
                    $goto = $goto . "@" . $this->id;
                }
                # TODO: use AliasHandler->add instead of writing directly to the alias table
                $arr = array(
                    'address' => $address,
                    'goto' => $goto,
                    'domain' => $this->id,
                );
                $result = db_insert('alias', $arr);
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
     *  @return bool
     */
    public function delete() {
        # TODO: check for _can_delete instead
        if (! $this->is_superadmin) {
            $this->errormsg[] = Config::Lang_f('no_delete_permissions', $this->id);
            return false;
        }

        if (! $this->view()) {
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
        db_delete($this->db_table, $this->id_field, $this->id);

        if (!$this->domain_postdeletion()) {
            $this->errormsg[] = Config::Lang('domain_postdel_failed');
        }

        db_log($this->id, 'delete_domain', $this->id); # TODO delete_domain is not a valid db_log keyword yet
        $this->infomsg[] = Config::Lang_f('pDelete_delete_success', $this->id);
        return true;
    }



    /**
     * get formatted version of fields
     *
     * @param array values of current item
     */
    public function _formatted_aliases($item) {
        return $item['alias_count']   . ' / ' . $item['aliases']  ;
    }
    public function _formatted_mailboxes($item) {
        return $item['mailbox_count'] . ' / ' . $item['mailboxes'];
    }
    public function _formatted_quota($item) {
        return $item['total_quota']   . ' / ' . $item['quota']    ;
    }

    /**
     * Called after a domain has been added
     *
     * @return boolean
     */
    protected function domain_postcreation() {
        $script=Config::read_string('domain_postcreation_script');

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
        $firstline=exec($command, $output, $retval);
        if (0!=$retval) {
            error_log("Running $command yielded return value=$retval, first line of output=$firstline");
            $this->errormsg[] = 'Problems running domain postcreation script!';
            return false;
        }

        return true;
    }

    /**
     * Called after a domain has been deleted
     *
     * @return boolean
     */
    protected function domain_postdeletion() {
        $script=Config::read_string('domain_postdeletion_script');

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
        $firstline=exec($command, $output, $retval);
        if (0!=$retval) {
            error_log("Running $command yielded return value=$retval, first line of output=$firstline");
            $this->errormsg[] = 'Problems running domain postdeletion script!';
            return false;
        }

        return true;
    }
}
/* vim: set expandtab softtabstop=4 tabstop=4 shiftwidth=4: */
