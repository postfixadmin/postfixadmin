<?php

/** 
 * Handlers User level alias actions - e.g. add alias, get aliases, update etc.
 */
class DomainHandler {

    private $username = null; # actually it's the domain - variable name kept for consistence with the other classes


    public $errormsg = array();
    /**
     * @param string $username
     */
    public function __construct($username) {
        $this->username = $username;
    }

    public function getTransports() {
        return Config::read('transport_options');
    }
    
    public function getTransport($id) {
        $transports = Config::read('transport_options');
        return $transports[$id-1];
    }
    
    public function add($desc, $a, $m, $t, $q, $default, $backup) {
      
        ($backup == true) ? $backup = db_get_boolean(true) : $backup = db_get_boolean(false);
      
        $arr = array(
            'domain' => $this->username,
            'description' => $desc,
            'aliases' => $a,
            'mailboxes' => $m,
            'maxquota' => $q,
            'transport' => $this->getTransport($t),
            'backupmx' => $backup,
        );
        
        $result = db_insert('domain', $arr);
        if ($result != 1) {
            $this->errormsg[] = Lang::read('pAdminCreate_domain_result_error') . "\n($domain)\n";
            return 1;
        } else {
            if ($default) {
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
        db_log('CONSOLE', $domain, 'create_domain', "");
        return 0;
    }
    
    public function view () {
        $table_domain = table_by_key('domain');
       
        $E_domain = escape_string($this->username);
        $result = db_query("SELECT domain, description, aliases, mailboxes, maxquota, quota, transport, backupmx,  DATE_FORMAT(created, '%d.%m.%y') AS created, DATE_FORMAT(modified, '%d.%m.%y') AS modified, active FROM $table_domain WHERE domain='$E_domain'");
        if ($result['rows'] != 0) {
            $this->return = db_array($result['result']);
            return 0;
        }
        $this->errormsg = $result['error'];
        return 1;
    }
}

/* vim: set expandtab softtabstop=4 tabstop=4 shiftwidth=4: */
