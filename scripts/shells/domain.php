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
                        'd' => "\t[<domain quota in MB>]\n" .
                                                "\t\tDomain Quota in MB. -1 = disable | 0 = unlimited\n",
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
                } elseif (isset($commands[strtolower($this->args[1])])) {
                        $this->out($commands[strtolower($this->args[1])] . "\n\n");
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
                        # TODO: handle the various -* parameters (see help)
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
                    $values['description'] = $this->in($question);
                    
                     $question = "Number of Aliases:";
                    $values['aliases'] = $this->in($question);
                    
                     $question = "Numer of Mailboxes:";
                    $values['mailboxes'] = $this->in($question);
                    
                     $question = "Max Quota (in MB):";
                    $values['maxquota'] = $this->in($question);

                     $question = "Domain Quota (in MB):";
                    $values['quota'] = $this->in($question);
    
                    $handler = new DomainHandler();
                    $struct = $handler->getStruct();
                    $transports = $struct['transport']['options'];
                    $qt[] = 'Choose transport option';
                    foreach ($transports AS $key => $val) {
                        //workaround. $this->in hates number 0 
                        $key = $key + 1;
                      $qt[] = '['.$key.'] - '.$val;
                    }
                    
                    $t = $this->in( join("\n", $qt) );

                    $values['transport'] = $transports[$t-1]; # convert int to transport name

                    $question = "Add default Aliases:";
                    $values['default_aliases'] = $this->in($question, array('y','n'));
                    ($values['default_aliases'] == 'y') ? $values['default_aliases'] = true : $values['default_aliases'] = false;
                    
                     $question = "Use as Backup MX:";
                    $values['backupmx'] = $this->in($question, array('y','n'));
                    ($values['backupmx'] == 'y') ? $values['backupmx'] = true : $values['backupmx'] = false;
                
                
                $this->__handle($domain, $values);
        }
        
/**
 * Interactive
 *
 * @access private
 */
        function __handle($domain, $values) {
                

                $handler =  new DomainHandler(1);
                if (!$handler->init($domain)) {
                      $this->error("Error:",join("\n", $handler->errormsg));
                      return;
                } 

                if (!$handler->set($values)) {
                        $this->error("Error:", join("\n", $handler->errormsg));
                }

                if (!$handler->store()) {
                        $this->error("Error:", join("\n", $handler->errormsg));
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
# TODO: this is the DOMAIN shell...
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
                $question = "Which domain do you want to delete?";
                $address = $this->in($question);

                $question = "Do you really want to delete domain '$address'?";
                $create = $this->in($question, array('y','n'));
                
                $this->__handle($address);
        }
 /**
 * Interactive
 *
 * @access private
 */
        function __handle($address) {
                $handler =  new DomainHandler();
                if (!$handler->init($address)) {
                      $this->error("Error:",join("\n", $handler->errormsg));
                      return;
                } 

                $status = $handler->delete();
                if ($status == true) {
                      $this->out("Domain '$address' was deleted.");
                      
                } else {
                      $this->error("Error:", join("\n", $handler->errormsg));
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
##Deleted PasswordTask because its silly in domain shell
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


                $handler =  new DomainHandler();
                if (!$handler->init($domain)) {
                      $this->error("Error:",join("\n", $handler->errormsg));
                      return;
                }
                    
                $status = $handler->view();
                if (!$status) {
                      $this->error("Error:",join("\n", $handler->errormsg));
                } else {
                    $result = $handler->result();
                    $struct = $handler->getStruct();

                    # TODO: $totalfield should be in DomainHandler (in $struct or as separate array)
                    $totalfield = array(
                        'aliases' => 'alias_count',
                        'mailboxes' => 'mailbox_count',
                        'quota' => 'total_quota',
                    );

                    foreach($struct as $key => $field) {
                        if ($field['display_in_list']) {
                            if (isset($totalfield[$key])) {
                                # TODO: format based on $field['type'] (also in the else section)
                                $this->out($field['label'] . ": \t" . $result[$totalfield[$key]] . " / " . $result[$key] );
                            } else {
                                if (!in_array($key, $totalfield)) { # skip if we already displayed the field as part of "x/y"
                                    $this->out($field['label'] . ": \t" . $result[$key]);
                                }
                            }
                        }
                    }

                    return;
                }
        
        }
/**
 * Displays help contents
 *
 * @access public
 */
        function help() {
# TODO: this is the DOMAIN shell...
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
/* vim: set expandtab softtabstop=4 tabstop=4 shiftwidth=4: */
