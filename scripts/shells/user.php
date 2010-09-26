<?php


class PostfixAdminUser extends Shell {

/**
 * Contains tasks to load and instantiate
 *
 * @var array
 * @access public
 */
        var $tasks = array('Add', 'Update', 'Delete', 'Password', 'View');


/**
 * Starts up the the Shell
 * allows for checking and configuring prior to command or main execution
 * can be overriden in subclasses
 *
 * @access public
 */
        function startup() {
                $this->_welcome();
                $CONF = Config::read('all');
        }
/**
 * Displays a header for the shell
 *
 * @access protected
 */
        function _welcome() {
                $this->out("\nWelcome to Postfixadmin-CLI v" . $this->Dispatch->version);
                $this->out("---------------------------------------------------------------");
                $this->out('Path: '. PATH);
                $this->hr();
        }

/**
 * Show help for this shell.
 *
 * @access public
 */
        function help() {
                $head  = "Usage: postfixadmin-cli user <task> [<address>] [] [-m <method>]\n";
                $head .= "-----------------------------------------------\n";
                $head .= "Parameters:\n\n";

                $commands = array(
                        'task' => "\t<task>\n" .
                                                "\t\tAvailable values:\n\n".
                                                "\t\t".sprintf("%-20s %s", "view: ",  "View an existing user.")."\n".
                                                "\t\t".sprintf("%-20s %s", "add: ",  "Adds a new user with mailbox.")."\n".
                                                "\t\t".sprintf("%-20s %s", "update: ",  "Updates a user.")."\n".
                                                "\t\t".sprintf("%-20s %s", "delete: ",  "Deletes a user")."\n".
                                                "\t\t".sprintf("%-20s %s", "password: ",  "Changes the PW for a user.")."\n",
                        'address' => "\t[<address>]\n" .
                                                "\t\tA CakePHP core class name (e.g: Component, HtmlHelper).\n",
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
                        if ($this->params['g']) {
                            $this->__handle($this->args[0], NULL, true, $this->args[1], $this->args[2]);
                        } else {
                            $this->__handle($this->args[0], $this->args[1], false, $this->args[2], $this->args[3]);
                        }
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
                $question = "Do you want to generate a random password?";
                $random = $this->in($question, array('y','n'));
                
                $random == 'y' ? $random = true : $random = false;
                
                
                $password = NULL;
                if ($random == false) {
                        $question = "Enter the password:";
                        $password = $this->in($question);
                }
                
                $question = "Enter name:";
                $name = $this->in($question);
                
                $question = "Enter quota (MB):";
                $quota = $this->in($question);
                
                $question1[] = "Do you reallywant to add mailbox with this options?";
                $question1[] = "Address:  \t$address";
                if($random) 
                        $question1[] = "Random Password.";
                else
                        $question1[] = "Password: \t$password";
                $question1[] = "Name:     \t$name";
                $question1[] = "Quota:    \t$quota MB";
                $create = $this->in(join("\n", $question1), array('y','n'));
                
                $create == 'y' ? $random = true : $random = false;
                
                if ($create)                
                        $this->__handle($address, $password, $random, $name, $quota);
        }
        
/**
 * Interactive
 *
 * @access private
 */
        function __handle($address, $password, $gen = false, $name = '', $quota = 0) {
                $pw = NULL;
                if ($gen) {
                      $pw = generate_password();
                } elseif ($password  != NULL) {
                      $pw = $password;
                }
                
                $handler =  new UserHandler($address);
                $return = $handler->add($pw,  $name, $quota, true, true  );

                if($return == 1) {
                        $this->err(join("\n", $handler->errormsg));
                } else {
                        $this->out("");
                        if ($name != '')
                                $this->out("Mailbox for $name generated.");
                        else
                                $this->out("Mailbox generated.");
                        $this->hr();
                        $this->out(sprintf('Mailaddress:  %-20s', $address));
                        $this->out(sprintf('Password:    %-20s',$pw));
                        $this->out(sprintf('Quota:       %-20sMB',$quota));
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
        $this->out("Not Implemented yet! If you want to change a password use the password command.");
                /*$this->out("Usage: postfixadmin-cli user update <args>");
                $this->hr();
                $this->out('Commands:');
                $this->out("\n\tmodel\n\t\tbakes model in interactive mode.");
                $this->out("\n\tmodel <name>\n\t\tbakes model file with no associations or validation");
                $this->out("");*/
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
                        $this->__interactive();
                }

                if (!empty($this->args[0])) {
                       $output = $this->__handle($this->args[0]);
                       $this->out($output);
                       
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


                $handler =  new UserHandler($address);
                $status = $handler->delete();
                if ($status == 0) {
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
                $this->out("Usage: postfixadmin-cli user model <arg1>");
                $this->hr();
                $this->out('Commands:');
                $this->out("\n\tdelete\n\t\tdeletes mailbox in interactive mode.");
                $this->out("\n\tdelete <address>\n\t\tdeletes mailbox with address <address>");
                $this->out("");
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
                       $output = $this->__handle($this->args[0]);
                       $this->out($output);
                       
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

                      $this->__handle($address);


        
        }
 /**
 * Interactive
 *
 * @access private
 */
        function __handle($address) {


                $handler =  new UserHandler($address);
                $status = $handler->view();
                if ($status == 0) {
                      $result = $handler->return;
                      $this->out(sprintf("Entries for: %s\n", $address));
                      $this->out("");
                      $this->out(sprintf("+%'-25s+%'-15s+%'-10s+%'-20s+%'-8s+%'-8s+%'-6s+",'','','','','','',''));
                      $this->out(sprintf('|%25s|%15s|%10s|%20s|%8s|%8s|%6s|', 'Address', 'Name', 'Quota', 'Dir', 'Created', 'Modified', 'Active'));
                      $this->out(sprintf("+%'-25s+%'-15s+%'-10s+%'-20s+%'-8s+%'-8s+%'-6s+",'','','','','','',''));
                      $this->out(sprintf('|%25s|%15s|%10s|%20s|%8s|%8s|%6s|', $result['username'], $result['name'], $result['quota'], $result['maildir'], $result['created'], $result['modified'], $result['active']));
                      $this->out(sprintf("+%'-25s+%'-15s+%'-10s+%'-20s+%'-8s+%'-8s+%'-6s+",'','','','','','',''));
                      
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