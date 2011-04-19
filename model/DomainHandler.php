<?php
# $Id$ 

/** 
 * Handlers User level alias actions - e.g. add alias, get aliases, update etc.
 */
class DomainHandler extends PFAHandler {

    protected $username = null; # actually it's the domain - variable name kept for consistence with the other classes
    protected $id_field = null;
    protected $struct = array();
    protected $defaults = array();
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
        $this->id_field = 'domain';

        # TODO: merge $struct and $defaults to one array?
        # TODO: use a helper function to fill $struct with named keys instead of [0], [1], ...
        # TODO: find a way to handle field labels - not sure if the fetchmail way (construct $LANG keys from field name) is perfect

        $this->struct=array(   //   list($editible,$view,$type)
            # field name                allow       display field?  type
            #                           editing?    form    list
           "domain"          => array(  $this->new, 1,      1,      'text'      ),
           "description"     => array(  1,          1,      1,      'text'      ),
           "aliases"         => array(  1,          1,      1,      'num'       ),
           "mailboxes"       => array(  1,          1,      1,      'num'       ),
           "maxquota"        => array(  1,          1,      1,      'num'       ),
           "quota"           => array(  0,          0,      0,      'num'       ),
           "transport"       => array(  1,          1,      1,      'enum'      ),
           "backupmx"        => array(  1,          1,      1,      'bool'      ),
           "active"          => array(  1,          1,      1,      'bool'      ),
           "created"         => array(  0,          0,      1,      'text'      ),
           "modified"        => array(  0,          0,      1,      'text'      ),
        );
        # labels and descriptions are taken from $PALANG['pFetchmail_field_xxx'] and $PALANG['pFetchmail_desc_xxx']

        $this->defaults=array(
            'aliases'   => Config::read('aliases'),
            'mailboxes' => Config::read('mailboxes'),
            'maxquota'  => Config::read('maxquota'),
            'quota'     => Config::read('domain_quota_default'),
            'transport' => $this->getTransports(),
            'backupmx'  => 0,
            'active'    => 1,
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
#    ($desc, $a, $m, $t, $q, $default, $backup)

        # TODO: make this a generic function for add and edit
        # TODO: move DB writes etc. to separate save() function

        ($values['backupmx'] == true) ? $values['backupmx'] = db_get_boolean(true) : $values['backupmx'] = db_get_boolean(false);
      
        $values['domain'] = $this->username;

        # base validation
        $checked = array();
        foreach($this->struct as $key=>$row) {
            list($editable, $displayform, $displaylist, $type) = $row;
            if ($editable != 0){
                $func="_inp_".$type;
                $val=safepost($key);
                if ($type!="password" || strlen($values[$key]) > 0 || $this->new == 1) { # skip on empty (aka unchanged) password on edit
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

        $domain = $this->username; # TODO fix variable names below

        $checked['domain'] = $this->username;
        $result = db_insert('domain', $checked);
        if ($result != 1) {
            $this->errormsg[] = Lang::read('pAdminCreate_domain_result_error') . "\n($domain)\n";
            return false;
        } else {
            if ($this->new && $values['default_aliases']) {
                foreach (Config::read('default_aliases') as $address=>$goto) {
                    $address = $address . "@" . $domain;
                    # TODO: use AliasHandler->add instead of writing directly to the alias table
                    $arr = array(
                        'address' => $address,
                        'goto' => $goto,
                        'domain' => $domain,
                    );
                    $result = db_insert ('alias', $arr);
                }
            }
            $tMessage = Lang::read('pAdminCreate_domain_result_success') . "<br />($domain)</br />";
        }
        if (!domain_postcreation($domain)) {
            $tMessage = Lang::read('pAdminCreate_domain_error');
        }
        db_log ($domain, 'create_domain', "");
        return true;
    }
    
    public function view () {
        $table_domain = table_by_key('domain');
       
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
        $result = db_delete('domain', 'domain', $this->username);
        if( $result == 1 ) {
            list(/*NULL*/,$domain) = explode('@', $this->username);
            db_log ($domain, 'delete_domain', $this->username); # TODO delete_domain is not a valid db_log keyword yet because we don't yet log add/delete domain
            return true;
        }
    }

}

/* vim: set expandtab softtabstop=4 tabstop=4 shiftwidth=4: */
