<?php


class PostfixAdminDomain extends Shell {

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
                $head  = "Usage: postfixadmin-cli domain <task> [<domain>] [-desc \"<description>\"] [-a <aliases>] [-m <mailboxes>] [-q <quota in MB>] [-t <transport>] [-default] [-backup]\n";
                $head .= "-----------------------------------------------\n";
                $head .= "Parameters:\n\n";

                $commands = array(
                        'task' => "\t<task>\n" .
                                                "\t\tAvailable values:\n\n".
                                                "\t\t".sprintf("%-20s %s", "view: ",  "View an existing domain.")."\n".
                                                "\t\t".sprintf("%-20s %s", "add: ",  "Adds a domain.")."\n".
                                                "\t\t".sprintf("%-20s %s", "update: ",  "Updates an domain.")."\n".
                                                "\t\t".sprintf("%-20s %s", "delete: ",  "Deletes a domain")."\n",
                        'domain' => "\t[<domain>]\n" .
                                                "\t\tA address of recipient.\n",
                        'a' => "\t[<aliaes>]\n" .
                                                "\t\tNumber of max aliases. -1 = disable | 0 = unlimited\n",
                        'm' => "\t[<mailboxes>]\n" .
                                                "\t\tNumber of max mailboxes. -1 = disable | 0 = unlimited\n",
                        'q' => "\t[<quota in MB>]\n" .
                                                "\t\tMax Quota in MB. -1 = disable | 0 = unlimited\n",
                        't' => "\t[<transport>]\n" .
                                                "\t\tTransport options from config.inc.php.\n",
                        'default' => "\t\tSet to add default Aliases.\n",
                        'backup' => "\t\tSet if mailserver is backup MX.\n",
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
                    $question = "Enter domain:";
                    $domain = $this->in($question);
                
                    if(preg_match("/^((?:(?:(?:[a-zA-Z0-9][\.\-_]?){0,62})[a-zA-Z0-9])+)\.([a-zA-Z0-9]{2,6})$/", $domain) == 1)
                        break;
                    
                    $this->err("Invalid domain");
  
                }
                    $question = "Description:";
                    $desc = $this->in($question);
                    
                     $question = "Number of Aliases:";
                    $a = $this->in($question);
                    
                     $question = "Numer of Mailboxes:";
                    $m = $this->in($question);
                    
                     $question = "Max Quota (in MB):";
                    $q = $this->in($question);
    
                    $handler = new DomainHandler('CONSOLE');
                    $transports = $handler->getTransports();
                    $qt[] = 'Choose transport option';
                    foreach ($transports AS $key => $val) {
                        //workaround. $this->in hates number 0 
                        $key = $key + 1;
                      $qt[] = '['.$key.'] - '.$val;
                    }
                    
                    $t = $this->in( join("\n", $qt) );
                    
                    $question = "Add default Aliases:";
                    $default = $this->in($question, array('y','n'));
                    ($default == 'y') ? $default = true : $default = false;
                    
                     $question = "Use as Backup MX:";
                    $backup = $this->in($question, array('y','n'));
                    ($backup == 'y') ? $backup = true : $backup = false;
                
                
                $this->__handle($domain, $desc, $a, $m, $t, $q, $default, $backup);
        }
        
/**
 * Interactive
 *
 * @access private
 */
        function __handle($domain, $desc, $a, $m, $t, $q, $default, $backup) {
                

                $handler =  new DomainHandler('CONSOLE');
                $return = $handler->add($domain, $desc, $a, $m, $t, $q, $default, $backup);

                if($return == 1) {
                        $this->err(join("\n", $handler->errormsg));
                } else {
                        $this->out("");
                        $this->out("Domain ( $domain ) generated.");
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
        $this->out("Not Implemented yet! ");
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
                $this->help();
                        //$this->__interactive();
                }

                if (!empty($this->args[0])) {
                $this->help();
                       //$output = $this->__handle($this->args[0]);
                       //$this->out($output);
                       
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
                $this->out("NOT Implemented yet.");
                $this->hr();
                $this->out("Usage: postfixadmin-cli user model <arg1>");
                $this->hr();
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
                $this->help();
                //        $this->__interactive();
                }

                if (!empty($this->args[0])) {
                $this->help();
                        //$address = $this->args[0];
                        
                        //if (isset($this->params['g']) && $this->params['g'] == true ) {
                        //    $random = true;
                        //   $password = NULL;
                        //} elseif  (isset($this->args[1]) && length($this->args[1]) > 8) {
                         //   $password = $this->args[1];
                        //} else {

                        //    $this->Dispatch->stderr('Missing <newpw> or -g. Falling back to interactive mode.');
                         //   $this->__interactive();
                        //}
                        //$this->__handle($address, $password, $random);
                
                        
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
                $this->out("NOT implemented yet.");
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
                $question[] = "Which Domain do you want to view?";

                $domain = $this->in(join("\n", $question));

                      $this->__handle($domain);


        
        }
 /**
 * Interactive
 *
 * @access private
 */
        function __handle($domain) {


                $handler =  new DomainHandler('CONSOLE');
                $status = $handler->view($domain);
                if ($status == 0) {
                      $result = $handler->return;
                      $this->out("Domain: \t".$result['domain']);
                      $this->out("Description: \t".$result['description']);
                      $this->out("Aliases: \t".$result['aliases']);
                      $this->out("Mailboxes: \t".$result['mailboxes']);
                      $this->out("Max. Quota: \t".$result['maxquota']);
                      $this->out("Transport: \t".$result['transport']);
                      $this->out("Backup MX: \t".$result['backupmx']);
                      $this->out("Active: \t".$result['active']);
                      $this->out("Modified: \t".$result['modified']);
                      $this->out("Created: \t".$result['created']);

                      
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