<?php

# $Id$

/**
 * Handlers User level alias actions - e.g. add alias, get aliases, update etc.
 */
class AliasHandler extends PFAHandler {
    protected $db_table = 'alias';
    protected $id_field = 'address';
    protected $domain_field = 'domain';
    protected $searchfields = array('address', 'goto');

    /**
     *
     * @public
     */
    public $return = null;

    protected function initStruct() {
        # hide 'goto_mailbox' if $this->new
        # (for existing aliases, init() hides it for non-mailbox aliases)
        $mbgoto = 1 - $this->new;

        $this->struct = array(
            # field name                allow       display in...   type    $PALANG label                     $PALANG description                 default / ...
            #                           editing?    form    list
            'status'           => pacol(0,          0,      0,      'html', ''                              , ''                                , '', array(),
                array('not_in_db' => 1)  ),
            'address'          => pacol($this->new, 1,      1,      'mail', 'alias'                         , 'pCreate_alias_catchall_text'     ),
            'localpart'        => pacol($this->new, 0,      0,      'text', 'alias'                         , 'pCreate_alias_catchall_text'     , '',
                /*options*/ array(),
                /*not_in_db*/ 1                         ),
            'domain'           => pacol($this->new, 0,      1,      'enum', ''                              , ''                                , '',
                /*options*/ $this->allowed_domains      ),
            'goto'             => pacol(1,          1,      1,      'txtl', 'to'                            , 'pEdit_alias_help'                , array() ),
            'is_mailbox'       => pacol(0,          0,      1,      'int', ''                             , ''                                , 0 ,
                # technically 'is_mailbox' is bool, but the automatic bool conversion breaks the query. Flagging it as int avoids this problem.
                # Maybe having a vbool type (without the automatic conversion) would be cleaner - we'll see if we need it.
                /*options*/ array(),
                /*not_in_db*/ 0,
                /*dont_write_to_db*/ 1,
                /*select*/ 'coalesce(__is_mailbox,0) as is_mailbox' ),
                /*extrafrom set via set_is_mailbox_extrafrom() */
            '__mailbox_username' => pacol( 0,       0,      1,      'vtxt', ''                              , ''                                , 0),  # filled via is_mailbox
            'goto_mailbox'     => pacol($mbgoto,    $mbgoto,$mbgoto,'bool', 'pEdit_alias_forward_and_store' , ''                                , 0,
                /*options*/ array(),
                /*not_in_db*/ 1                         ), # read_from_db_postprocess() sets the value
            'on_vacation'      => pacol(1,          0,      1,      'bool', 'pUsersMenu_vacation'           , ''                                , 0 ,
                /*options*/ array(),
                /*not_in_db*/ 1                         ), # read_from_db_postprocess() sets the value - TODO: read active flag from vacation table instead?
            'created'          => pacol(0,          0,      0,      'ts',   'created'                       , ''                                ),
            'modified'         => pacol(0,          0,      1,      'ts',   'last_modified'                 , ''                                ),
            'active'           => pacol(1,          1,      1,      'bool', 'active'                        , ''                                , 1     ),
            '_can_edit'        => pacol(0,          0,      1,      'vnum', ''                              , ''                                , 0 , array(),
                array('select' => '1 as _can_edit')  ),
            '_can_delete'      => pacol(0,          0,      1,      'vnum', ''                              , ''                                , 0 , array(),
                array('select' => '1 as _can_delete')  ), # read_from_db_postprocess() updates the value
                # aliases listed in $CONF[default_aliases] are read-only for domain admins if $CONF[special_alias_control] is NO.
        );

        $this->set_is_mailbox_extrafrom();
    }

    /*
     * set $this->struct['is_mailbox']['extrafrom'] based on the search conditions.
     * If a listing for a specific domain is requested, optimize the subquery to only return mailboxes from that domain.
     * This doesn't change the result of the main query, but improves the performance a lot on setups with lots of mailboxes.
     * When using this function to optimize the is_mailbox extrafrom, don't forget to reset it to the default value
     * (all domains for this admin) afterwards.
     */
    private function set_is_mailbox_extrafrom($condition=array(), $searchmode=array()) {
        $extrafrom = 'LEFT JOIN ( ' .
            ' SELECT 1 as __is_mailbox, username as __mailbox_username ' .
            ' FROM ' . table_by_key('mailbox') .
            ' WHERE username IS NOT NULL ';

        if (isset($condition['domain']) && !isset($searchmode['domain']) && in_array($condition['domain'], $this->allowed_domains)) {
            # listing for a specific domain, so restrict subquery to that domain
            $extrafrom .= ' AND ' . db_in_clause($this->domain_field, array($condition['domain']));
        } else {
            # restrict subquery to all domains accessible to this admin
            $extrafrom .= ' AND ' . db_in_clause($this->domain_field, $this->allowed_domains);
        }

        $extrafrom .= ' ) AS __mailbox ON __mailbox_username = address';

        $this->struct['is_mailbox']['extrafrom'] = $extrafrom;
    }


    protected function initMsg() {
        $this->msg['error_already_exists'] = 'email_address_already_exists';
        $this->msg['error_does_not_exist'] = 'alias_does_not_exist';
        $this->msg['confirm_delete'] = 'confirm_delete_alias';
        $this->msg['list_header'] = 'pOverview_alias_title';

        if ($this->new) {
            $this->msg['logname'] = 'create_alias';
            $this->msg['store_error'] = 'pCreate_alias_result_error';
            $this->msg['successmessage'] = 'pCreate_alias_result_success';
        } else {
            $this->msg['logname'] = 'edit_alias';
            $this->msg['store_error'] = 'pEdit_alias_result_error';
            $this->msg['successmessage'] = 'alias_updated';
        }
    }


    public function webformConfig() {
        if ($this->new) { # the webform will display a localpart field + domain dropdown on $new
            $this->struct['address']['display_in_form'] = 0;
            $this->struct['localpart']['display_in_form'] = 1;
            $this->struct['domain']['display_in_form'] = 1;
        }

        if (Config::bool('show_status')) {
            $this->struct['status']['display_in_list'] = 1;
            $this->struct['status']['label'] = ' ';
        }

        return array(
            # $PALANG labels
            'formtitle_create'  => 'pMain_create_alias',
            'formtitle_edit'    => 'pEdit_alias_welcome',
            'create_button'     => 'add_alias',

            # various settings
            'required_role' => 'admin',
            'listview'      => 'list-virtual.php',
            'early_init'    => 0,
            'prefill'       => array('domain'),
        );
    }

    /**
     * AliasHandler needs some special handling in init() and therefore overloads the function.
     * It also calls parent::init()
     */
    public function init(string $id): bool {
        $bits = explode('@', $id);
        if (sizeof($bits) == 2) {
            $local_part = $bits[0];
            $domain = $bits[1];
            if ($local_part == '*') { # catchall - postfix expects '@domain', not '*@domain'
                $id = '@' . $domain;
            }
        }

        $retval = parent::init($id);

        if (!$retval) {
            return false;
        } # parent::init() failed, no need to continue

        # hide 'goto_mailbox' for non-mailbox aliases
        # parent::init called view() before, so we can rely on having $this->result filled
        # (only validate_new_id() is called from parent::init and could in theory change $this->result)
        if ($this->new || $this->result['is_mailbox'] == 0) {
            $this->struct['goto_mailbox']['editable']        = 0;
            $this->struct['goto_mailbox']['display_in_form'] = 0;
            $this->struct['goto_mailbox']['display_in_list'] = 0;
        }

        if (!$this->new && $this->result['is_mailbox'] && $this->admin_username != ''&& !authentication_has_role('global-admin')) {
            # domain admins are not allowed to change mailbox alias $CONF['alias_control_admin'] = NO
            # TODO: apply the same restriction to superadmins?
            if (!Config::bool('alias_control_admin')) {
                # TODO: make translateable
                $this->errormsg[] = "Domain administrators do not have the ability to edit user's aliases (check config.inc.php - alias_control_admin)";
                return false;
            }
        }

        return $retval;
    }

    protected function domain_from_id() {
        list(/*NULL*/, $domain) = explode('@', $this->id);
        return $domain;
    }

    protected function validate_new_id() {
        if ($this->id == '') {
            $this->errormsg[$this->id_field] = Config::lang('pCreate_alias_address_text_error1');
            return false;
        }

        list($local_part, $domain) = explode('@', $this->id);

        if (!$this->create_allowed($domain)) {
            $this->errormsg[$this->id_field] = Config::lang('pCreate_alias_address_text_error3');
            return false;
        }

        # TODO: already checked in set() - does it make sense to check it here also? Only advantage: it's an early check
        #        if (!in_array($domain, $this->allowed_domains)) {
        #            $this->errormsg[] = Config::lang('pCreate_alias_address_text_error1');
        #            return false;
        #        }

        if ($local_part == '') { # catchall
            $valid = true;
        } else {
            $email_check = check_email($this->id);
            if ($email_check == '') {
                $valid = true;
            } else {
                $this->errormsg[$this->id_field] = $email_check;
                $valid = false;
            }
        }

        return $valid;
    }

    /**
     * check number of existing aliases for this domain - is one more allowed?
     */
    private function create_allowed($domain) {
        if ($this->called_by == 'MailboxHandler') {
            return true;
        } # always allow creating an alias for a mailbox

        $limit = get_domain_properties($domain);

        if ($limit['aliases'] == 0) {
            return true;
        } # unlimited
        if ($limit['aliases'] < 0) {
            return false;
        } # disabled
        if ($limit['alias_count'] >= $limit['aliases']) {
            return false;
        }
        return true;
    }


    /**
     * merge localpart and domain to address
     * called by edit.php (if id_field is editable and hidden in editform) _before_ ->init
     */
    public function mergeId($values) {
        if ($this->struct['localpart']['display_in_form'] == 1 && $this->struct['domain']['display_in_form']) { # webform mode - combine to 'address' field
            if (empty($values['localpart']) || empty($values['domain'])) { # localpart or domain not set
                return "";
            }
            if ($values['localpart'] == '*') {
                $values['localpart'] = '';
            } # catchall
            return $values['localpart'] . '@' . $values['domain'];
        } else {
            return $values[$this->id_field];
        }
    }

    protected function setmore(array $values) {
        if ($this->new) {
            if ($this->struct['address']['display_in_form'] == 1) { # default mode - split off 'domain' field from 'address' # TODO: do this unconditional?
                list(/*NULL*/, $domain) = explode('@', $values['address']);
                $this->values['domain'] = $domain;
            }
        }

        if (! $this->new) { # edit mode - preserve vacation and mailbox alias if they were included before
            $old_ah = new AliasHandler();

            if (!$old_ah->init($this->id)) {
                $this->errormsg[] = $old_ah->errormsg[0];
            } elseif (!$old_ah->view()) {
                $this->errormsg[] = $old_ah->errormsg[0];
            } else {
                $oldvalues = $old_ah->result();

                if (!isset($values['goto'])) { # no new value given?
                    $values['goto'] = $oldvalues['goto'];
                }

                if (!isset($values['on_vacation'])) { # no new value given?
                    $values['on_vacation'] = $oldvalues['on_vacation'];
                }

                if ($values['on_vacation']) {
                    $values['goto'][] = $this->getVacationAlias();
                }

                if ($oldvalues['is_mailbox']) { # alias belongs to a mailbox - add/keep mailbox to/in goto
                    if (!isset($values['goto_mailbox'])) { # no new value given?
                        $values['goto_mailbox'] = $oldvalues['goto_mailbox'];
                    }
                    if ($values['goto_mailbox']) {
                        $values['goto'][] = $this->id;

                        # if the alias points to the mailbox, don't display the "empty goto" error message
                        if (isset($this->errormsg['goto']) && $this->errormsg['goto'] == Config::lang('pEdit_alias_goto_text_error1')) {
                            unset($this->errormsg['goto']);
                        }
                    }
                }
            }
        }

        $this->values['goto'] = join(',', $values['goto']);
    }

    protected function postSave(): bool {
        # TODO: if alias belongs to a mailbox, update mailbox active status
        return true;
    }

    protected function read_from_db_postprocess($db_result) {
        foreach ($db_result as $key => $value) {
            # split comma-separated 'goto' into an array
            $goto = $db_result[$key]['goto'] ?? null;
            if (is_string($goto)) {
                $db_result[$key]['goto'] = explode(',', $goto);
            }

            # Vacation enabled?
            list($db_result[$key]['on_vacation'], $db_result[$key]['goto']) = remove_from_array($db_result[$key]['goto'], $this->getVacationAlias());

            # if it is a mailbox, does the alias point to the mailbox?
            if ($db_result[$key]['is_mailbox']) {
                # this intentionally does not match mailbox targets with recipient delimiter.
                # if it would, we would have to make goto_mailbox a text instead of a bool (which would annoy 99% of the users)
                list($db_result[$key]['goto_mailbox'], $db_result[$key]['goto']) = remove_from_array($db_result[$key]['goto'], $key);
            } else { # not a mailbox
                $db_result[$key]['goto_mailbox'] = 0;
            }

            # editing a default alias (postmaster@ etc.) is only allowed if special_alias_control is allowed or if the user is a superadmin
            $tmp = preg_split('/\@/', $db_result[$key]['address']);
            if (!$this->is_superadmin && !Config::bool('special_alias_control') && array_key_exists($tmp[0], Config::read_array('default_aliases'))) {
                $db_result[$key]['_can_edit'] = 0;
                $db_result[$key]['_can_delete'] = 0;
            }

            if ($this->struct['status']['display_in_list'] && Config::bool('show_status')) {
                $db_result[$key]['status'] = gen_show_status($db_result[$key]['address']);
            }
        }

        return $db_result;
    }

    private function condition_ignore_mailboxes($condition, $searchmode) {
        # only list aliases that do not belong to mailboxes
        if (is_array($condition)) {
            $condition['__mailbox_username'] = 1;
            $searchmode['__mailbox_username'] = 'NULL';
        } else {
            if ($condition != '') {
                $condition = " ( $condition ) AND ";
            }
            $condition = " $condition __mailbox_username IS NULL ";
        }
        return array($condition, $searchmode);
    }

    public function getList($condition, $searchmode = array(), $limit=-1, $offset=-1): bool {
        list($condition, $searchmode) = $this->condition_ignore_mailboxes($condition, $searchmode);
        $this->set_is_mailbox_extrafrom($condition, $searchmode);
        $result = parent::getList($condition, $searchmode, $limit, $offset);
        $this->set_is_mailbox_extrafrom(); # reset to default
        return $result;
    }

    public function getPagebrowser($condition, $searchmode = array()) {
        list($condition, $searchmode) = $this->condition_ignore_mailboxes($condition, $searchmode);
        $this->set_is_mailbox_extrafrom($condition, $searchmode);
        $result = parent::getPagebrowser($condition, $searchmode);
        $this->set_is_mailbox_extrafrom(); # reset to default
        return $result;
    }



    protected function _validate_goto($field, $val) {
        if (count($val) == 0) {
            # empty is ok for mailboxes - this is checked in setmore() which can clear the error message
            $this->errormsg[$field] = Config::lang('pEdit_alias_goto_text_error1');
            return false;
        }

        $errors = array();

        foreach ($val as $singlegoto) {
            if (substr($this->id, 0, 1) == '@' && substr($singlegoto, 0, 1) == '@') { # domain-wide forward - check only the domain part
                # only allowed if $this->id is a catchall
                # Note: alias domains are better, but we should keep this way supported for backward compatibility
                #       and because alias domains can't forward to external domains
                list(/*NULL*/, $domain) = explode('@', $singlegoto);
                $domain_check = check_domain($domain);
                if ($domain_check != '') {
                    $errors[] = "$singlegoto: $domain_check";
                }
            } else {
                $email_check = check_email($singlegoto);
                // preg_match -> allows for redirect to a local system account.
                if ($email_check != '' && !preg_match('/^[a-z0-9]+$/', $singlegoto)) {
                    $errors[] = "$singlegoto: $email_check";
                }
            }
            if ($this->called_by != "MailboxHandler" && $this->id == $singlegoto) {
                // The MailboxHandler needs to create an alias that points to itself (for the mailbox)
                // Otherwise, disallow such aliases as they cause severe trouble in the mail system
                $errors[] = "$singlegoto: " . Config::Lang('alias_points_to_itself');
            }
        }

        if (count($errors)) {
            $this->errormsg[$field] = join("   ", $errors); # TODO: find a way to display multiple error messages per field
            return false;
        } else {
            return true;
        }
    }

    /**
     * on $this->new, set localpart based on address
     */
    protected function _missing_localpart($field) {
        if (isset($this->RAWvalues['address'])) {
            $parts = explode('@', $this->RAWvalues['address']);
            if (count($parts) == 2) {
                $this->RAWvalues['localpart'] = $parts[0];
            }
        }
    }

    /**
     * on $this->new, set domain based on address
     */
    protected function _missing_domain($field) {
        if (isset($this->RAWvalues['address'])) {
            $parts = explode('@', $this->RAWvalues['address']);
            if (count($parts) == 2) {
                $this->RAWvalues['domain'] = $parts[1];
            }
        }
    }


    /**
    * Returns the vacation alias for this user.
    * i.e. if this user's username was roger@example.com, and the autoreply domain was set to
    * autoreply.fish.net in config.inc.php we'd return roger#example.com@autoreply.fish.net
    *
    * @return string an email alias.
    */
    protected function getVacationAlias() {
        if ($this->id !== null) {
            $vacation_goto = str_replace('@', '#', $this->id);
            return $vacation_goto . '@' . Config::read_string('vacation_domain');
        }
        return "unknown@" . Config::read_string('vacation_domain');
    }

    /**
     *  @return boolean
     */
    public function delete() {
        if (! $this->view()) {
            $this->errormsg[] = Config::Lang('alias_does_not_exist');
            return false;
        }

        if ($this->result['is_mailbox']) {
            $this->errormsg[] = Config::Lang('mailbox_alias_cant_be_deleted');
            return false;
        }

        if (!$this->can_delete) {
            $this->errormsg[] = Config::Lang_f('protected_alias_cant_be_deleted', $this->id);
            return false;
        }

        db_delete('alias', 'address', $this->id);

        list(/*NULL*/, $domain) = explode('@', $this->id);
        db_log($domain, 'delete_alias', $this->id);
        $this->infomsg[] = Config::Lang_f('pDelete_delete_success', $this->id);
        return true;
    }
}

/* vim: set expandtab softtabstop=4 tabstop=4 shiftwidth=4: */
