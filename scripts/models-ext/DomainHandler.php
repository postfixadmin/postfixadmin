<?php

/** 
 * Handlers User level alias actions - e.g. add alias, get aliases, update etc.
 */
class DomainHandler {

    private $username = null;


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
    
    public function add($domain, $desc, $a, $m, $t, $q, $default, $backup){
      
      $table_domain =  table_by_key('domain');
      $table_alias = table_by_key('alias');
      
      
      ($backup == true) ? $backup = db_get_boolean(true) : $backup = db_get_boolean(false);
      
      $arr = array(
            'domain' => $domain,
            'description' => $desc,
            'aliases' => $a,
            'mailboxes' => $m,
            'maxquota' => $q,
            'transport' => $this->getTransport($t),
            'backupmx' => $backup,
            );
        
        $result = db_insert($table_domain, $arr, array('created', 'modified') );
        if ($result != 1)
        {
            $this->errormsg[] = Lang::read('pAdminCreate_domain_result_error') . "\n($domain)\n";
            return 1;
        }
        else
        {
            if ($default)
            {
                foreach (Config::read('default_aliases') as $address=>$goto)
                {
                    $address = $address . "@" . $domain;
                    $arr = array(
                        'address' => $address,
                        'goto' => $goto,
                        'domain' => $domain,
                        );
                    $result = db_insert ($table_alias, $arr, array('created', 'modified') );
                }
            }
            $tMessage = Lang::read('pAdminCreate_domain_result_success') . "<br />($domain)</br />";
        }
        if (!domain_postcreation($domain))
        {
             $tMessage = Lang::read('pAdminCreate_domain_error');
        }
         db_log($this->username, $domain, 'create_domain', "");
      return 0;
    }
    
    public function view ($domain) {
        global $config;

        

        $table_domain = table_by_key('domain');
        
        $result = db_query("SELECT domain, description, aliases, mailboxes, maxquota, quota, transport, backupmx,  DATE_FORMAT(created, '%d.%m.%y') AS created, DATE_FORMAT(modified, '%d.%m.%y') AS modified, active FROM $table_domain WHERE domain='$domain'");
        if ($result['rows'] != 0) {
          $this->return = db_array($result['result']);
          return 0;
        }
        $this->errormsg = $result['error'];
        return 1;
    }
}

/* vim: set expandtab softtabstop=4 tabstop=4 shiftwidth=4: */
