<?php


class UserController extends Controller {



  public function view($id = NULL) {
    if ($id != NULL) {
      $this->User->load( array( 'where' => array( $this->User->coloumn_id, $id ) ) );
    } else {
      $this->Userload();
    }
    
    //function for sending the layout the arg $this->User->Assoc();
    //return $this->User->Assoc();
  }
  public function edit($id) {
    if($id == NULL) {
      $this->errormsg[] = 'The User is nonexistent.';
      return false;
    }
    $this->User->load(array( 'where' => array( $this->User->coloumn_id, $id )));
    //function for sending the layout the arg $this->User->Assoc();
    
    //postswtich
    //bla bla smarty stuff for getting the values
    //$this->User->values = array('frit@example.com', 'hased HMAC_MD5 PW', 'Fritz', '/home/fritz/maildir', 51200000, 'fritz', 'example.com', '{[CREATED]}', '{[MODIFIED]}'); {} = Model should replace something, [] = constant not tablenames
    
    if( ! $this->User->save() ) {
      $this->errormsg[] = "The data can't be saved.";
      return false;
    }
    
    //redirect to view($id)
  }
  
  public function add() {
  
  
  //only if $_POST of $_GET
   
      //bla bla smarty stuff for filling the values
    //$this->User->values = array('frit@example.com', 'hased HMAC_MD5 PW', 'Fritz', '/home/fritz/maildir', 51200000, 'fritz', 'example.com', '{[CREATED]}', '{[MODIFIED]}'); {} = Model should replace something, [] = constant not tablenames
    
    if( ! $this->User->save() ) {
      $this->errormsg[] = "The data can't be saved.";
      return false;
    }
    //redirect to view($id)
  }
  public function delete($id) {
  
  }
  public function activate() {
  
  }
  public function deactivate() {
  
  }