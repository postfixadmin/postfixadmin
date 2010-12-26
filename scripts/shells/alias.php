<?php


class PostfixAdminAlias extends Shell {

/**
 * Contains tasks to load and instantiate
 *
 * @var array
 * @access public
 */
        var $tasks = array('Add', 'Update', 'Delete', 'View');





/**
 * Show help for this shell.
 *
 * @access public
 */
        function help() {
                $head  = "Usage: postfixadmin-cli alias <task> [<address>] [] [-m <method>]\n";
                $head .= "-----------------------------------------------\n";
                $head .= "Parameters:\n\n";

                $commands = array(
                        'task' => "\t<task>\n" .
                                                "\t\tAvailable values:\n\n".
                                                "\t\t".sprintf("%-20s %s", "view: ",  "View an existing alias.")."\n".
                                                "\t\t".sprintf("%-20s %s", "add: ",  "Adds an alias.")."\n".
                                                "\t\t".sprintf("%-20s %s", "update: ",  "Updates an alias.")."\n".
                                                "\t\t".sprintf("%-20s %s", "delete: ",  "Deletes an alias")."\n",
                        'address' => "\t[<address>]\n" .
                                                "\t\tA address of recipient.\n",
                );

                $this->out($head);
                if (!isset($this->args[1])) {
                        foreach ($commands as $cmd) {
                                $this->out("{$cmd}\n\n");
                        }
                } elseif (isset($commands[low($this->args[1])])) {
                        $this->out($commands[low($this->args[1])] . "\n\n");
                } else {
                        $this->out("Command '" . $this->args[1] . "' not found");
                }
        }


}

class AddTask extends Shell {
/**
 * Execution method always used for tasks
 *
 * @access public
 */
        function execute() {
                if (empty($this->args)) {
                        $this->__interactive();
                }

                if (!empty($this->args[0])) {
                        $this->__handle($this->args[0], $this->args[1]);

                }
        }
/**
 * Interactive
 *
 * @access private
 */
        function __interactive() {
                while(0==0) {
                    $question = "Enter address:";
                    $address = $this->in($question);
                
                    if(preg_match("/^((?:(?:(?:[a-zA-Z0-9][\.\-\+_]?)*)[a-zA-Z0-9])+)\@((?:(?:(?:[a-zA-Z0-9][\.\-_]?){0,62})[a-zA-Z0-9])+)\.([a-zA-Z0-9]{2,6})$/", $address) == 1)
                        break;
                    
                    $this->err("Invalid emailaddress");
  
                }
                while(0==0) {
                    $question = "Forward to:";
                    $random = $this->in($question);
                    
                    if(preg_match("/^((?:(?:(?:[a-zA-Z0-9][\.\-\+_]?)*)[a-zA-Z0-9])+)\@((?:(?:(?:[a-zA-Z0-9][\.\-_]?){0,62})[a-zA-Z0-9])+)\.([a-zA-Z0-9]{2,6})$/", $address) == 1)
                        break;
                    
                    $this->err("Invalid emailaddress");
                }
                
                $this->__handle($address, $goto);
        }
        
/**
 * Interactive
 *
 * @access private
 */
        function __handle($address, $goto) {
                
                $handler =  new AliasHandler($address);
                $return = $handler->add($goto);

                if($return == 1) {
                        $this->err(join("\n", $handler->errormsg));
                } else {
                        $this->out("");
                        $this->out("Alias ( $address -> $goto ) generated.");
                        $this->hr();
                }
        return;
        }
/**
 * Displays help contents
 *
 * @access public
 */
        function help() {
                $this->hr();
                $this->out("Usage: postfixadmin-cli user add <address> [<password>] <name> <quota> [-g]");
                $this->hr();
                $this->out('Commands:');
                $this->out("\n\tadd\n\t\tAdds mailbox in interactive mode.");
                $this->out("\n\tadd <address> [<password>] [-g] <name> <quota>\n\t\tAdds mailbox for <address> with password <password> of if -g with rand pw. <quota> in MB.");
                $this->out("");
                $this->_stop();
        }

}
class UpdateTask extends Shell {
/**
 * Execution method always used for tasks
 *
 * @access public
 */
        function execute() {
                if (empty($this->args)) {
                        $this->help();
                        //$this->__interactive();
                }

                if (!empty($this->args[0])) {
                        $this->help();
                }
        }
/**
 * Interactive
 *
 * @access private
 */
        function __interactive() {
        
        }
/**
 * Displays help contents
 *
 * @access public
 */
        function help() {
                $this->hr();
        $this->out("Not Implemented yet!");
                /*$this->out("Usage: postfixadmin-cli user update <args>");
                //$this->hr();
                //$this->out('Commands:');
                //$this->out("\n\tmodel\n\t\tbakes model in interactive mode.");
                //$this->out("\n\tmodel <name>\n\t\tbakes model file with no associations or validation");
                //$this->out("");*/
                $this->_stop();
        }

}
class DeleteTask extends Shell {
/**
 * Execution method always used for tasks
 *
 * @access public
 */
        function execute() {

                if (empty($this->args)) {
                        $this->help();
                        //$this->__interactive();
                }

                if (!empty($this->args[0])) {
                     $this->help();
                      // $output = $this->__handle($this->args[0]);
                      // $this->out($output);
                       
                }
        }
/**
 * Interactive
 *
 * @access private
 */
        function __interactive() {
                $question[] = "Which Address do you want to view?";

                $address = $this->in(join("\n", $question));


                $question = "Do you really want to delete mailbox of '$address'?";
       
                $create = $this->in($question, array('y','n'));
                
                $create == 'y' ? $random = true : $random = false;
                
                if ($create)                
                      $this->__handle($address);


        
        }
 /**
 * Interactive
 *
 * @access private
 */
        function __handle($address) {

### TODO: don't use UserHandler, instead add delete function to AliasHandler (if not already there)
### using UserHandler for deleting aliases is like taking a sledgehammer to crack a nut
### (and will probably cause some error messages that I added today ;-)
                $handler =  new UserHandler($address);
                $status = $handler->delete();
                if ($status == true) {
                      $this->out("Mailbox of '$address' was deleted.");
                      
                } else {
                      $this->err(join("\n", $handler->errormsg));
                }
                return;
        
        }
/**
 * Displays help contents
 *
 * @access public
 */
        function help() {
                $this->hr();
                $this->out("NOT implemented yet.");
                //$this->out("Usage: postfixadmin-cli user model <arg1>");
                //$this->hr();
                //$this->out('Commands:');
                //$this->out("\n\tdelete\n\t\tdeletes mailbox in interactive mode.");
                //$this->out("\n\tdelete <address>\n\t\tdeletes mailbox with address <address>");
                //$this->out("");
                $this->_stop();
        }

}
class PasswordTask extends Shell {
/**
 * Execution method always used for tasks
 *
 * @access public
 */
        function execute() {
                if (empty($this->args)) {
                        $this->__interactive();
                }

                if (!empty($this->args[0])) {
                
                        $address = $this->args[0];
                        
                        if (isset($this->params['g']) && $this->params['g'] == true ) {
                            $random = true;
                            $password = NULL;
                        } elseif  (isset($this->args[1]) && length($this->args[1]) > 8) {
                            $password = $this->args[1];
                        } else {

                            $this->Dispatch->stderr('Missing <newpw> or -g. Falling back to interactive mode.');
                            $this->__interactive();
                        }
                        $this->__handle($address, $password, $random);
                
                        
                }
        }
/**
 * Interactive
 *
 * @access private
 */
        function __interactive() {
                
                while(0==0) {
                    $question = "Which address' password do you want to change?";
                    $address = $this->in($question);
                
                    if(preg_match("/^((?:(?:(?:[a-zA-Z0-9][\.\-\+_]?)*)[a-zA-Z0-9])+)\@((?:(?:(?:[a-zA-Z0-9][\.\-_]?){0,62})[a-zA-Z0-9])+)\.([a-zA-Z0-9]{2,6})$/", $address) == 1)
                        break;
                    
                    $this->err("Invalid emailaddress");
  
                }
                
                
                $question2[] = "Do you want to change the password?";
                $question2[] = "Are you really sure?";
                $sure = $this->in(join("\n", $question2), array('y','n'));
                
                
                if ($sure == 'n' ) {
                  $this->out('You\'re not sure.');
                  $this->_stop();
                }
                
                $question = "Do you want to generate a random password?";
                $random = $this->in($question, array('y','n'));
                
                $random == 'y' ? $random = true : $random = false;
                
                
                $password = NULL;
                if ($random == false) {
                        $question = "Pleas enter the new password?";
                        $password = $this->in($question);
                }
                var_dump($random);
                $this->__handle($address, $password, $random);
                


        
        }
 /**
 * Interactive
 *
 * @access private
 */
        function __handle($address, $password = NULL, $random = false) {
# TODO: Does PasswordTask really make sense for Aliases? Probably not...
                if ($random == true) {
                    $password = generate_password();
                }
                if ($password != NULL) {
                    $handler =  new UserHandler($address);
                    
                    if ($handler->change_pw($password, NULL, false) == 1){
                        $this->error("Change Password",join("\n", $handler->errormsg));
                    }
                }
                
                $this->out("");
                        $this->out("Password updated.");
                        $this->hr();
                        $this->out(sprintf('The Mail address is  %20s', $address));
                        $this->out(sprintf('The new password is %20s',$password));
                        $this->hr();
                
                return ;
        }
/**
 * Displays help contents
 *
 * @access public
 */
        function help() {
                $this->out("");
                $this->hr();
                $this->out("Usage: postfixadmin-cli user password <address> [<newpw>] [-g]");
                $this->hr();
                $this->out('Commands:');
                $this->out("\n\tpassword\n\t\tchanges the password in interactive mode.");
                $this->out("\n\tpassword <address> [<newpw>] [-g]\n\t\tchanges the password to <newpw> or if -g genereate a new pw for <address>");
                $this->out("");
                $this->_stop();
        }

}
class ViewTask extends Shell {
/**
 * Execution method always used for tasks
 *
 * @access public
 */
        function execute() {

                if (empty($this->args)) {
                        $this->__interactive();
                }

                if (!empty($this->args[0])) {
                        $this->__handle($this->args[0]);
                       
                }
        }
/**
 * Interactive
 *
 * @access private
 */
        function __interactive() {
                $question[] = "Which Alias do you want to view?";

                $address = $this->in(join("\n", $question));

                      $this->__handle($address);


        
        }
 /**
 * Interactive
 *
 * @access private
 */
        function __handle($address) {


                $handler =  new AliasHandler($address);
                $status = $handler->get(); # TODO: set the "all" flag?
                if ( ! $status) {
                    # TODO: error message
                } else {
                      $result = $handler->return;

                      $this->out(sprintf("Entries for: %s\n", $address));
                      $this->out("Goto: \t");
                      foreach($result AS $goto) {
                        $this->out("\t -> ".$goto);
                      }
                      # TODO: display "deliver to mailbox"
                      # TODO: display if vacation is on?
                }
                return;
        
        }
/**
 * Displays help contents
 *
 * @access public
 */
        function help() {
                $this->out("");
                $this->hr();
                $this->out("Usage: postfixadmin-cli user view <address>");
                $this->hr();
                $this->out('Commands:');
                $this->out("\n\tview\n\t\tView user. Select address in interactive mode.");
                $this->out("\n\tview <address>\n\t\tView user with address <address>");
                $this->out("");
                $this->_stop();
        }

}
