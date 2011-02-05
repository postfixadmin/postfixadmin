<?php


class Model {


  public  $errormsg = array();
  protected $table;
	protected $columns = array();
	protected $values = array();
	/**
	 * id of database entry
	 * @access private
	 */
	protected $id;
  protected $column_id;

	public function save() {
    $db = new Database;
    #Pseudecode if new
      $db->insert( $this->table, $this->values );
    #pseudocode else
      $db->update( $this->table, $this->id, $this->values );
	}
	
	public function load( $parameter = array() ) {
	  $sql = '';
	  $db = new Database;
	  if (array_key_exists( 'where', $parameter) ) {
	    $w = $parameters['where']; # where = array('column', 'value' )
	    $sql .= " WHERE $w[0] = $w[1]";
	  } elseif ( array_key_exists('limit', $parameter ) ) {
	    $l = $parameter['limit']; # limit = array(start, length)
	    $sql .= " LIMIT $l[0], $l[1]";
	  }


      $this->query = $db->query("SELECT * FROM \{ $this->table \} $sql");
	}
	
	public function delete( $parameter = array() ) {
	
	}
	public function Assoc() {
	  $db = new Database();
	  return $db->getAssoc($this->query);
	}
	public function Array() {
	  $db = new Database();
	  return $db->getArray($this->query);
	}
	public function Object() {
	  $db = new Database();
	  return $db->getObject($this->query);
	}
	public function Row() {
	  $db = new Database();
	  return $db->getRow($this->query);
	}

}




//No closing tag only in index.php
//index.php needs drupal like path syntax like ?q=domain/new or ?q=domain/example.com or ?q=domain/example.com/edit
//which loads the needed controller and view.