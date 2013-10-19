<?php


class PostfixAdminMailbox extends Shell {

/**
 * Contains tasks to load and instantiate
 *
 * @var array
 * @access public
 */
        var $tasks = array('Add', 'Update', 'Delete', 'Password', 'View');




/**
 * Show help for this shell.
 *
 * @access public
 */
        function help() {
                $head  = "Usage: postfixadmin-cli mailbox <task> [<address>] [] [--<option> <value>]\n";
                $head .= "-----------------------------------------------\n";
                $head .= "Parameters:\n\n";

                $commands = array(
                        'task' => "\t<task>\n" .
                                                "\t\tAvailable values:\n\n".
                                                "\t\t".sprintf("%-20s %s", "view: ",  "View an existing mailbox.")."\n".
                                                "\t\t".sprintf("%-20s %s", "add: ",  "Adds a new mailbox.")."\n".
                                                "\t\t".sprintf("%-20s %s", "update: ",  "Updates a mailbox.")."\n".
                                                "\t\t".sprintf("%-20s %s", "delete: ",  "Deletes a mailbox")."\n".
                                                "\t\t".sprintf("%-20s %s", "password: ",  "Changes the PW for a mailbox.")."\n",
                        'address' => "\t[<address>]\n" .
                                                "\t\tMail address\n",
                );

                $this->out($head);
                if (!isset($this->args[1])) {
                        foreach ($commands as $cmd) {
                                $this->out("{$cmd}\n\n");
                        }
                } elseif (isset($commands[strtolower($this->args[1])])) {
                        $this->out($commands[strtolower($this->args[1])] . "\n\n");
                } else {
                        $this->out("Command '" . $this->args[1] . "' not found");
                }
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
                $question[] = "Which Address do you want to delete?";

                $address = $this->in(join("\n", $question));


                $question = "Do you really want to delete mailbox of '$address'?";
       
                $create = $this->in($question, array('y','n'));
                
                $create == 'y' ? $create = true : $create = false;
                
                if ($create)                
                      $this->__handle($address);
        
        }
 /**
 * Interactive
 *
 * @access private
 */
        function __handle($address) {

                $handler =  new MailboxHandler();
				if (!$handler->init($address)) {
                      $this->error("Error:", join("\n", $handler->errormsg));
				}

                $status = $handler->delete();
                if ( ! $status ) {
                      $this->err($handler->errormsg);

                } else {
                      $this->out("Mailbox of '$address' was deleted.");
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
                $this->out("Usage: postfixadmin-cli mailbox model <arg1>");
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
				        $random = false;
                if (empty($this->args)) {
                        $this->__interactive();
                }

                if (!empty($this->args[0])) {
                
                        $address = $this->args[0];
                        
                        if (isset($this->params['g']) && $this->params['g'] == true ) {
                            $random = true;
                            $password = NULL;
                        } elseif  (isset($this->args[1]) && strlen($this->args[1]) > 8) { # TODO use validate_password()
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
                    $handler =  new MailboxHandler();

					if (!$handler->init($address)) {
                        $this->error("Change Password",join("\n", $handler->errormsg));
					}
                    
                    if ( ! $handler->change_pw($password, NULL, false) ){
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
                $this->out("Usage: postfixadmin-cli mailbox password <address> [<newpw>] [-g]");
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


                $handler =  new MailboxHandler();
				if ( ! $handler->init($address)) {
					$this->error("Not found!", "The mailbox you have searched could not be found.");
				}
                if ( ! $handler->view() ) {
                  $this->error("Not Found!", "The mailbox you have searched could not be found.");
				      }
# TODO: offer alternative output formats (based on parameter)
# TODO: whitespace fix - 8 lines below
                      $result = $handler->result;
                      $this->out(sprintf("Entries for: %s\n", $address));
                      $this->out("");
                      $this->out(sprintf("+%'-25s+%'-15s+%'-10s+%'-20s+%'-8s+%'-8s+%'-6s+",'','','','','','',''));
                      $this->out(sprintf('|%25s|%15s|%10s|%20s|%8s|%8s|%6s|', 'Address', 'Name', 'Quota', 'Dir', 'Created', 'Modified', 'Active'));
                      $this->out(sprintf("+%'-25s+%'-15s+%'-10s+%'-20s+%'-8s+%'-8s+%'-6s+",'','','','','','',''));
					  $result['maildir'] = '--- skipped ---'; # TODO: include in view() result - or don't (try to) display it
                      $this->out(sprintf('|%25s|%15s|%10s|%20s|%8s|%8s|%6s|', $result['username'], $result['name'], $result['quota'], $result['maildir'], $result['created'], $result['modified'], $result['active']));
                      $this->out(sprintf("+%'-25s+%'-15s+%'-10s+%'-20s+%'-8s+%'-8s+%'-6s+",'','','','','','',''));
                      
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
                $this->out("Usage: postfixadmin-cli mailbox view <address>");
                $this->hr();
                $this->out('Commands:');
                $this->out("\n\tview\n\t\tView mailbox. Select address in interactive mode.");
                $this->out("\n\tview <address>\n\t\tView mailbox with address <address>");
                $this->out("");
                $this->_stop();
        }

}
