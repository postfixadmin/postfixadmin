<?php


class UserModel extends Model {



  public  function __construct() {
    $this->table = 'mailbox';
    $this->columns = array('username' => array('varchar(255)', false), 
                            'password' => array('varchar(255)', false), 
                            'name' => array('varchar(255)', false), 
                            'maildir' => array('varchar(255)', false), 
                            'quota' => array('bigint', false, 0), 
                            'local_part' => array('varchar(255)', false), 
                            'domain' => array('varchar(255)', false), 
                            'created' => array('datetime', false), 
                            'modified' => array('datetime', false), 
                            'active' => array('tinyint(1)', false, 1) );
    $this->coloumn_id = 'username';
  }
  
}
  