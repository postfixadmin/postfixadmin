<?php
# $Id$ 

/** 
 * Handlers User level alias actions - e.g. add alias, get aliases, update etc.
 */
class DomainHandler extends PFAHandler {

    protected $username = null; # actually it's the domain - variable name kept for consistence with the other classes
    protected $db_table = null;
    protected $id_field = null;
    protected $struct = array();
    protected $new = 0; # 1 on create, otherwise 0

    public $errormsg = array();
    /**
     * @param string $username
     */
    public function __construct($username, $new = 0) {
        $this->username = strtolower($username); # TODO: find a better place for strtolower() to avoid a special constructor in DomainHandler (or agree that $username should be lowercase in all *Handler classes ;-)
        if ($new) $this->new = 1;
        # TODO: if $new == 1, check that item does NOT exist and is a valid (in this case) domain
        # TODO: else: check if item exists. error out if not.
        # TODO: target: if construct succeeds, $this->username is valid
        $this->initStruct();
    }

    private function initStruct() {
        $this->db_table = 'domain';
        $this->id_field = 'domain';

        # TODO: shorter PALANG labels ;-)
        # TODO: hardcode 'default' to Config::read in pacol()?

        $transp = boolconf('transport')     ? 1 : 0; # TOOD: use a function or write a Config::intbool function
        $quota  = boolconf('quota')         ? 1 : 0; # TOOD: use a function or write a Config::intbool function
        $dom_q  = boolconf('domain_quota')  ? 1 : 0; # TOOD: use a function or write a Config::intbool function

        $this->struct=array(
            # field name                allow       display in...   type    $PALANG label                    $PALANG description                 default / options / not in database
            #                           editing?    form    list
           "domain"          => pacol(  $this->new, 1,      1,      'text', 'pAdminEdit_domain_domain'     , ''                                 ),
           "description"     => pacol(  1,          1,      1,      'text', 'pAdminEdit_domain_description', ''                                 ),
           "aliases"         => pacol(  1,          1,      1,      'num' , 'pAdminEdit_domain_aliases'    , 'pAdminEdit_domain_aliases_text'   , Config::read('aliases')   ),
           "mailboxes"       => pacol(  1,          1,      1,      'num' , 'pAdminEdit_domain_mailboxes'  , 'pAdminEdit_domain_mailboxes_text' , Config::read('mailboxes') ),
           "maxquota"        => pacol(  $quota,     $quota, $quota, 'num' , 'pAdminEdit_domain_maxquota'   , 'pAdminEdit_domain_maxquota_text'  , Config::read('maxquota')  ),
           "quota"           => pacol(  $dom_q,     $dom_q, $dom_q, 'num' , 'pAdminEdit_domain_quota'      , 'pAdminEdit_domain_maxquota_text'  , Config::read('domain_quota_default') ),
           "transport"       => pacol(  $transp,    $transp,$transp,'enum', 'pAdminEdit_domain_transport'  , 'pAdminEdit_domain_transport_text' , Config::read('transport_default')     ,
                                                                                                                                /*options*/ $this->getTransports()     ),
           "backupmx"        => pacol(  1,          1,      1,      'bool', 'pAdminEdit_domain_backupmx'   , ''                                 ),
           "active"          => pacol(  1,          1,      1,      'bool', 'pAdminEdit_domain_active'     , ''                                 ),
           "default_aliases" => pacol(  $this->new, 1,      0,      'bool', 'pAdminCreate_domain_defaultaliases ', ''                           , '','', /*not in db*/ 1    ),
           "created"         => pacol(  0,          0,      0,      'ts',    '' /* TODO: "created" label */ , ''                                 ),
           "modified"        => pacol(  0,          0,      1,      'ts',   'pAdminList_domain_modified'   , ''                                 ),
        );

    }

    public function getTransports() {
        return Config::read('transport_options');
    }

    # TODO: specific for CLI? If yes, move to CLI code
    public function getTransport($id) {
        $transports = Config::read('transport_options');
        return $transports[$id-1];
    }
    
    public function add($values) {
        # TODO: make this a generic function for add and edit
        # TODO: move DB writes etc. to separate save() function (to allow on-the-fly validation before saving to DB)

        ($values['backupmx'] == true) ? $values['backupmx'] = db_get_boolean(true) : $values['backupmx'] = db_get_boolean(false);

        if ($this->new == 1) {
            $values[$this->id_field] = $this->username;
        }

        # base validation
        $checked = array();
        foreach($this->struct as $key=>$row) {
            if ($row['editable'] == 0){ # not editable
                if ($this->new == 1) {
                    $checked[$key] = $row['default'];
                }
            } else { 
                $func="_inp_".$row['type'];
                # TODO: error out if an editable field is not set in $values (on $this->new) -or- skip if in edit mode
                $val=$values[$key];
                if ($row['type'] != "password" || strlen($values[$key]) > 0 || $this->new == 1) { # skip on empty (aka unchanged) password on edit
                    if (method_exists($this, $func) ) {
                        $checked[$key] = $this->{$func}($values[$key]);
                    } else {
                        # TODO: warning if no validation function exists?
                        $checked[$key] = $values[$key];
                    }
                }
            }
        }

        # TODO: more validation

#        $checked[$this->id_field] = $this->username; # should already be set (if $this->new) via values[$this->id_field] and the base check

        $db_values = $checked;
        unset ($db_values['default_aliases']); # TODO: automate based on $this->struct

        $result = db_insert($this->db_table, $db_values);
        if ($result != 1) {
            $this->errormsg[] = Lang::read('pAdminCreate_domain_result_error') . "\n(" . $this->username . ")\n";
            return false;
        } else {
            if ($this->new && $values['default_aliases']) {
                foreach (Config::read('default_aliases') as $address=>$goto) {
                    $address = $address . "@" . $this->username;
                    # TODO: use AliasHandler->add instead of writing directly to the alias table
                    $arr = array(
                        'address' => $address,
                        'goto' => $goto,
                        'domain' => $this->username,
                    );
                    $result = db_insert ('alias', $arr);
                    # TODO: error checking
                }
            }
            $tMessage = Lang::read('pAdminCreate_domain_result_success') . "<br />(" . $this->username . ")</br />"; # TODO: remove <br> # TODO: tMessage is not used/returned anywhere
        }
        if (!domain_postcreation($this->username)) {
            $tMessage = Lang::read('pAdminCreate_domain_error'); # TODO: tMessage is not used/returned anywhere
        }
        db_log ($this->username, 'create_domain', "");
        return true;
    }
    
    public function view () {
        $table_domain = table_by_key($this->db_table);
       
        $E_domain = escape_string($this->username);
        $result = db_query("SELECT domain, description, aliases, mailboxes, maxquota, quota, transport, backupmx,  DATE_FORMAT(created, '%d.%m.%y') AS created, DATE_FORMAT(modified, '%d.%m.%y') AS modified, active FROM $table_domain WHERE domain='$E_domain'");
        if ($result['rows'] != 0) {
            $this->return = db_array($result['result']);
            return true;
        }
        $this->errormsg[] = "Domain " . $this->username . " does not exist.";
#        $this->errormsg[] = $result['error'];
        return false;
    }
    /**
     *  @return true on success false on failure
     */
    public function delete(){
        if( ! $this->view() ) {
            $this->errormsg[] = 'A domain with that name does not exist.'; # TODO: make translatable
            return false;
        }

        $this->errormsg[] = '*** Domain deletion not implemented yet ***';
        return false; # XXX function aborts here until TODO below is implemented! XXX

        # TODO: recursively delete mailboxes, aliases, alias_domains, fetchmail entries etc. before deleting the domain
        # TODO: move the needed code from delete.php here
        $result = db_delete($this->db_table, $this->id_field, $this->username);
        if( $result == 1 ) {
            list(/*NULL*/,$domain) = explode('@', $this->username);
            db_log ($domain, 'delete_domain', $this->username); # TODO delete_domain is not a valid db_log keyword yet because we don't yet log add/delete domain
            return true;
        }
    }

}

/* vim: set expandtab softtabstop=4 tabstop=4 shiftwidth=4: */
