<?php


class model {


  public  $errormsg = array();
  private $table = array();
	private $columns = array();
	private $values = array();
	
	/**
	 * id of database entry
	 * @access private
	 */
	private $id;
  

	public save() {
    $db = new Database;
    #Pseudecode if new
      $db->insert($this->table, $this->values);
    #pseudocode else
      $db->update( $this->table, $this->id, $this->values );
	}
	
	public load( $parameter = array() ) {
	  ##pseudocode if where is key in parameters
	    $w = $parameters['where']; # where = array('column', 'value' )
	    $sql = " WHERE $where['column'] = $where['value']";
	  ## elseif limit is key in parameters
	    $l = $parameter['limit']
	    $sq = " LIMIT $l[1], $l[2]";


      $db->query("SELECT * FROM $this->table $sql");
	}


}




//No closing tag only in index.php
//index.php needs drupal like path syntax like ?q=domain/new or ?q=domain/example.com or ?q=domain/example.com/edit
//which loads the needed controller and view.