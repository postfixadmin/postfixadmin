<?php

# $Id$

/**
 * Handlers User level alias actions - e.g. add alias, get aliases, update etc.
 */
class DomainHandler extends PFAHandler
{
    protected string $db_table = 'domain';
    protected string $id_field = 'domain';
    protected ?string $domain_field = 'domain';

    protected function _validate_password_expiry($field, $val)
    {
        $value = (string)$val;
        $valid = preg_match('/^(0|[1-9][0-9]*)$/D', $value) === 1
            && (int)$value <= PASSWORD_EXPIRATION_MAX_DAYS;

        if (!$valid) {
            $this->errormsg[$field] = Config::lang_f('invalid_value_given', $field);
        }

        return $valid;
    }

    protected function setmore(array $values)
    {
        if (array_key_exists('password_expiry', $this->values)) {
            $this->values['password_expiry'] = (int)$this->values['password_expiry'];
        }
    }

    protected function validate_new_id()
    {
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

    protected function initStruct()
    {
        # TODO: shorter PALANG labels ;-)

        $super = $this->is_superadmin;

        $transp = min($super, Config::intbool('transport'));
        $editquota  = min($super, Config::intbool('quota'));
        $quota  = Config::intbool('quota');
        $edit_dom_q  = min($super, Config::intbool('domain_quota'), $quota);
        $dom_q  = min(Config::intbool('domain_quota'), $quota);
        $pwexp = min($super, Config::intbool('password_expiration'));
        $show_maxquota = $quota;
        $show_pwexp = $pwexp;
        $pwexp_label = 'password_expiration';

        if (Config::bool('domain_list_hide_maxquota')) {
            $show_maxquota = 0;
        }
        if (Config::bool('domain_list_hide_password_expiry')) {
            $show_pwexp = 0;
        }

        $query_used_domainquota = 'round(coalesce(__total_quota/' . intval(Config::read('quota_multiplier')) . ',0))';
        $query_display_domainquota = $query_used_domainquota;
        $mailbox_quota_select = '';
        $mailbox_quota_join = '';
        $mailbox_full_select = ', 0 as __full_mailbox_count';

        if (Config::bool('used_quotas')) {
            $mailbox_table = table_by_key('mailbox');

            if (Config::bool('new_quota_table')) {
                $quota2_table = table_by_key('quota2');
                $mailbox_quota_select = ', sum(coalesce(' . $quota2_table . '.bytes,0)) as __used_quota';
                $mailbox_full_select = ', sum(case when ' . $mailbox_table . '.quota > 0 and (100 * coalesce(' . $quota2_table . '.bytes,0) / ' . $mailbox_table . '.quota) > ' . intval(Config::read('quota_level_high_pct')) . ' then 1 else 0 end) as __full_mailbox_count';
                $mailbox_quota_join = ' left join ' . $quota2_table . ' on ' . $mailbox_table . '.username=' . $quota2_table . '.username';
            } else {
                $quota_table = table_by_key('quota');
                $mailbox_quota_select = ', sum(coalesce(' . $quota_table . '.current,0)) as __used_quota';
                $mailbox_full_select = ', sum(case when ' . $mailbox_table . '.quota > 0 and (100 * coalesce(' . $quota_table . '.current,0) / ' . $mailbox_table . '.quota) > ' . intval(Config::read('quota_level_high_pct')) . ' then 1 else 0 end) as __full_mailbox_count';
                $mailbox_quota_join = ' left join ' . $quota_table . ' on ' . $mailbox_table . '.username=' . $quota_table . '.username'
                    . " and (" . $quota_table . ".path='quota/storage' or " . $quota_table . ".path is null)";
            }

            $query_display_domainquota = 'round(coalesce(__used_quota/' . intval(Config::read('quota_multiplier')) . ',0))';
        }

        # NOTE: There are dependencies between alias_count, mailbox_count and total_quota.
        # NOTE: If you disable "display in list" for one of them, the SQL query for the others might break.
        # NOTE: (Disabling all of them shouldn't be a problem.)
        #

        // https://github.com/postfixadmin/postfixadmin/issues/299
        $domain_quota_default = Config::read('domain_quota_default');
        if ($domain_quota_default === null) {
            $domain_quota_default = -1;
        }

        $this->struct = array(
            # field name                allow       display in...   type    $PALANG label                    $PALANG description                 default / options / ...
            #                           editing?    form    list
           'domain'            => self::pacol($this->new, 1,      1,      'text', 'domain'                       , ''                                 ,'', array(), 0, 0, "", "", 'list-virtual.php?domain=%s'),
           'full_mailbox_count' => self::pacol(0,          0,      1,      'vnum', ''                             , ''                                 , '', array(), 0, 0, 'coalesce(__full_mailbox_count,0) as full_mailbox_count'),
           'description'       => self::pacol($super,     $super, $super, 'text', 'description'                  , ''),

           # Aliases
           'aliases'           => self::pacol($super,     $super, 0,      'num' , 'aliases'                      , 'pAdminEdit_domain_aliases_text'   , Config::read('aliases')),
           'alias_count'       => self::pacol(0,          0,      1,      'vnum', ''                             , ''                                 , '', array(),
               /*not_in_db*/ 0,
               /*dont_write_to_db*/ 1,
               /*select*/ 'coalesce(__alias_count,0) - coalesce(__mailbox_count,0)  as alias_count',
               /*extrafrom*/ 'left join ( select count(*) as __alias_count, domain as __alias_domain from ' . table_by_key('alias') .
                             ' group by domain) as __alias on domain = __alias_domain'),
            'aliases_quot'     => self::pacol(0,          0,      1,      'quot', 'aliases'                      , ''                                  , 0, array(), 0, 0,  db_quota_text('__alias_count - coalesce(__mailbox_count,0)', 'aliases', 'aliases_quot')),
            '_aliases_quot_percent' => self::pacol(0, 0,      1,      'vnum', ''                   ,''                   , 0, array(), 0, 0, db_quota_percent('__alias_count - coalesce(__mailbox_count,0)', 'aliases', '_aliases_quot_percent')),

            # Mailboxes
           'mailboxes'         => self::pacol($super,     $super, 0,      'num' , 'mailboxes'                    , 'pAdminEdit_domain_aliases_text'   , Config::read('mailboxes')),
           'mailbox_count'     => self::pacol(0,          0,      1,      'vnum', ''                             , ''                                 , '', array(), 0, 1,
               /*select*/ 'coalesce(__mailbox_count,0) as mailbox_count',
               /*extrafrom*/ 'left join ( select count(*) as __mailbox_count, sum(quota) as __total_quota' . $mailbox_quota_select . $mailbox_full_select . ', domain as __mailbox_domain from ' . table_by_key('mailbox') . $mailbox_quota_join .
                             ' group by domain) as __mailbox on domain = __mailbox_domain'),
            'mailboxes_quot'   => self::pacol(0,          0,      1,       'quot', 'mailboxes'                    , ''                                 , 0, array(), 0, 0,  db_quota_text('__mailbox_count', 'mailboxes', 'mailboxes_quot')),
            '_mailboxes_quot_percent' => self::pacol(0,  0,      1,       'vnum', ''                             , ''                                 , 0, array(), 0, 0,   db_quota_percent('__mailbox_count', 'mailboxes', '_mailboxes_quot_percent')),

           'maxquota'          => self::pacol($editquota,$editquota,$show_maxquota, 'num', 'pAdminEdit_domain_maxquota', 'pAdminEdit_domain_maxquota_text'  , Config::read('maxquota')),

            # Domain quota
            'quota'            => self::pacol($edit_dom_q,$edit_dom_q, 0, 'num',  'pAdminEdit_domain_quota'      , 'pAdminEdit_domain_maxquota_text'  , $domain_quota_default),
            'total_quota'      => self::pacol(0,          0,      1,      'vnum', ''                             , ''                                 , '', array(), 0, 0,  "$query_used_domainquota AS total_quota" /*extrafrom*//* already in mailbox_count */),
            'total_quota_used' => self::pacol(0,          0,      1,      'vnum', ''                             , ''                                 , '', array(), 0, 0,  "$query_display_domainquota AS total_quota_used" /*extrafrom*//* already in mailbox_count */),
            'total_quot'     => self::pacol(0,          0,      $dom_q,  'quot', 'pAdminList_domain_quota'      , ''                                 , 0, array(), 0, 0,  db_quota_text($query_used_domainquota, 'quota', 'total_quot')),
            '_total_quot_percent' => self::pacol(0,      0,      $dom_q,  'vnum', ''                             , ''                                 , 0, array(), 0, 0, db_quota_percent($query_display_domainquota, 'quota', '_total_quot_percent')),

           'transport'         => self::pacol($transp,    $transp,$transp,'enum', 'transport'                    , 'pAdminEdit_domain_transport_text' , Config::read('transport_default')     ,
               /*options*/ Config::read_array('transport_options')),
           'backupmx'          => self::pacol($super,     $super, 1,      'bool', 'pAdminEdit_domain_backupmx'   , ''                                 , 0),
           'active'            => self::pacol($super,     $super, 1,      'bool', 'active'                       , ''                                 , 1),
           'default_aliases'   => self::pacol($this->new, $this->new, 0,  'bool', 'pAdminCreate_domain_defaultaliases', ''                            , 1,array(), /*not in db*/ 1),
           'created'           => self::pacol(0,          0,      0,      'ts',   'created'                      , ''),
           'modified'          => self::pacol(0,          0,      $super, 'ts',   'last_modified'                , ''),
           'password_expiry'   => self::pacol($super,     $pwexp, $show_pwexp, 'num',  $pwexp_label              , 'password_expiration_desc'         , 365),
            '_can_edit'        => self::pacol(0,          0,      1,      'int', ''                             , ''                                , 0 ,
                /*options*/ array(),
                /*not_in_db*/ 0,
                /*dont_write_to_db*/ 1,
                /*select*/ $this->is_superadmin . ' as _can_edit'),
            '_can_delete'      => self::pacol(0,          0,      1,      'int', ''                             , ''                                , 0 ,
                /*options*/ array(),
                /*not_in_db*/ 0,
                /*dont_write_to_db*/ 1,
                /*select*/ $this->is_superadmin . ' as _can_delete'),

            # Per-domain OIDC configuration (stored in domain_oidc table, not domain)
            'oidc_enabled'     => self::pacol($super,     $super, 0,      'bool', 'oidc_enable'                  , ''                                 , 0, array(), 1, 1),
            'oidc_issuer_url'  => self::pacol($super,     $super, 0,      'text', 'oidc_issuer_url'              , 'oidc_issuer_url_desc'             , '', array(), 1, 1),
            'oidc_client_id'   => self::pacol($super,     $super, 0,      'text', 'oidc_client_id'               , ''                                 , '', array(), 1, 1),
            'oidc_client_secret' => self::pacol($super, $super, 0, 'b64p', 'oidc_client_secret', 'oidc_client_secret_desc', '', array(), 1, 1),
            'oidc_scopes'      => self::pacol($super,     $super, 0,      'text', 'oidc_scopes'                  , ''                                 , 'openid email profile', array(), 1, 1),
            'oidc_login_button_text' => self::pacol($super, $super, 0, 'text', 'oidc_login_button_text'      , ''                                 , 'Login with SSO', array(), 1, 1),
            'oidc_auto_provision' => self::pacol($super,  $super, 0,      'bool', 'oidc_auto_provision'          , 'oidc_auto_provision_desc'         , 0, array(), 1, 1),
            'oidc_mfa_policy'  => self::pacol($super,     $super, 0,      'enum', 'oidc_mfa_policy'              , ''                                 , 'none',
                /*options*/ array('none' => 'none', 'mfa_or_totp' => 'mfa_or_totp', 'idp_mfa' => 'idp_mfa'), 1, 1),
        );
    }

    protected function initMsg()
    {
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

    public function webformConfig()
    {
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


    protected function preSave(): bool
    {
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
    protected function read_from_db_postprocess($db_result)
    {
        if (empty($this->id)) {
            return $db_result;
        }
        // Load per-domain OIDC configuration
        $oidcHandler = new DomainOidcHandler($this->id);
        $oidcEnabled = 0;
        $oidcConfig = [];
        if ($oidcHandler->exists()) {
            $oidcEnabled = 1;
            $oidcConfig = $oidcHandler->get();
        }
        $fieldMap = [
            'oidc_issuer_url' => 'issuer_url',
            'oidc_client_id' => 'client_id',
            'oidc_client_secret' => 'client_secret',
            'oidc_scopes' => 'scopes',
            'oidc_login_button_text' => 'login_button_text',
            'oidc_auto_provision' => 'auto_provision',
            'oidc_mfa_policy' => 'mfa_policy',
        ];
        foreach ($db_result as $key => $_) {
            $db_result[$key]['oidc_enabled'] = $oidcEnabled;
            foreach ($fieldMap as $structKey => $dbKey) {
                if (isset($oidcConfig[$dbKey])) {
                    $db_result[$key][$structKey] = $oidcConfig[$dbKey];
                }
            }
        }
        return $db_result;
    }

    protected function postSave(): bool
    {
        if ($this->new && $this->values['default_aliases']) {
            foreach (Config::read_array('default_aliases') as $address => $goto) {
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
                db_insert('alias', $arr);
                # TODO: error checking
            }
        }

        // Save per-domain OIDC configuration
        if (!empty($this->values['oidc_enabled'])) {
            $oidcHandler = new DomainOidcHandler($this->id);
            $existing = $oidcHandler->get();
            $secret = $this->values['oidc_client_secret'] ?? '';
            // Preserve existing secret if field left empty (password fields don't display stored value)
            if ($secret === '' && $existing) {
                $secret = $existing['client_secret'] ?? '';
            }
            $oidcHandler->save([
                'issuer_url' => $this->values['oidc_issuer_url'] ?? '',
                'client_id' => $this->values['oidc_client_id'] ?? '',
                'client_secret' => $secret,
                'scopes' => $this->values['oidc_scopes'] ?? 'openid email profile',
                'login_button_text' => $this->values['oidc_login_button_text'] ?? 'Login with SSO',
                'auto_provision' => $this->values['oidc_auto_provision'] ?? 0,
                'mfa_policy' => $this->values['oidc_mfa_policy'] ?? 'none',
            ]);
        } else {
            // OIDC disabled - clean up any existing config
            $oidcHandler = new DomainOidcHandler($this->id);
            if ($oidcHandler->exists()) {
                $oidcHandler->delete();
            }
        }

        if ($this->new) {
            if (!$this->domain_postcreation()) {
                $this->errormsg[] = Config::lang('domain_postcreate_failed');
            }
        } else {
            if (!$this->domain_postedit()) {
                $this->errormsg[] = Config::lang('domain_postedit_failed');
            }
        }
        return true; # TODO: don't hardcode
    }

    /**
     *  @return bool
     */
    public function delete()
    {
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
            $handler->getList(array('target_domain' => $this->id));
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
        $like_domain_value = '%@' . $this->id;

        db_delete('domain_admins',         'domain',        $this->id);
        db_delete('alias',                 'domain',        $this->id);
        db_delete('mailbox',               'domain',        $this->id);
        db_delete('alias_domain',          'alias_domain',  $this->id);
        db_delete('vacation',              'domain',        $this->id);
        db_delete('vacation_notification', 'on_vacation',   $this->id, "OR on_vacation LIKE ?", [$like_domain_value]);
        db_delete('quota',                 'username',      $this->id, "OR username    LIKE ?", [$like_domain_value]);
        db_delete('quota2',                'username',      $this->id, "OR username    LIKE ?", [$like_domain_value]);
        db_delete('fetchmail',             'mailbox',       $this->id, "OR mailbox     LIKE ?", [$like_domain_value]);
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
    public function _formatted_aliases($item)
    {
        return $item['alias_count']   . ' / ' . $item['aliases']  ;
    }
    public function _formatted_mailboxes($item)
    {
        return $item['mailbox_count'] . ' / ' . $item['mailboxes'];
    }
    public function _formatted_quota($item)
    {
        return $item['total_quota']   . ' / ' . $item['quota']    ;
    }

    /**
     * Called after a domain has been added
     *
     * @return boolean
     */
    protected function domain_postcreation()
    {
        $script = Config::read_string('domain_postcreation_script');

        if (empty($script)) {
            return true;
        }

        if (empty($this->id)) {
            $this->errormsg[] = 'Empty domain parameter in domain_postcreation';
            return false;
        }

        $cmdarg1 = escapeshellarg($this->id);
        $command = "$script $cmdarg1";
        $retval = 0;
        $output = array();
        $firstline = exec($command, $output, $retval);
        if (0 != $retval) {
            error_log("Running $command yielded return value=$retval, first line of output=$firstline");
            $this->errormsg[] = 'Problems running domain postcreation script!';
            return false;
        }

        return true;
    }

    /**
     * Called after a domain has been edited
     *
     * @return boolean
     */
    protected function domain_postedit()
    {
        $script = Config::read_string('domain_postedit_script');

        if (empty($script)) {
            return true;
        }

        if (empty($this->id)) {
            $this->errormsg[] = 'Empty domain parameter in domain_postedit';
            return false;
        }

        $cmdarg1 = escapeshellarg($this->id);
        $command = "$script $cmdarg1";
        $retval = 0;
        $output = array();
        $firstline = exec($command, $output, $retval);
        if (0 != $retval) {
            error_log("Running $command yielded return value=$retval, first line of output=$firstline");
            $this->errormsg[] = 'Problems running domain postedit script!';
            return false;
        }

        return true;
    }

    /**
     * Called after a domain has been deleted
     *
     * @return boolean
     */
    protected function domain_postdeletion()
    {
        $script = Config::read_string('domain_postdeletion_script');

        if (empty($script)) {
            return true;
        }

        if (empty($this->id)) {
            $this->errormsg[] = 'Empty domain parameter in domain_postdeletion';
            return false;
        }

        $cmdarg1 = escapeshellarg($this->id);
        $command = "$script $cmdarg1";
        $retval = 0;
        $output = array();
        $firstline = exec($command, $output, $retval);
        if (0 != $retval) {
            error_log("Running $command yielded return value=$retval, first line of output=$firstline");
            $this->errormsg[] = 'Problems running domain postdeletion script!';
            return false;
        }

        return true;
    }
}
/* vim: set expandtab softtabstop=4 tabstop=4 shiftwidth=4: */
