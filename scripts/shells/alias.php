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
                } elseif (isset($commands[strtolower($this->args[1])])) {
                        $this->out($commands[strtolower($this->args[1])] . "\n\n");
                } else {
                        $this->out("Command '" . $this->args[1] . "' not found");
                }
        }


}

#TODO: implement
class DeleteTask extends Shell {
/**
 * Execution method always used for tasks
 *
 * @access public
 */
        function execute() {

                if (empty($this->args)) {
#                       $this->help();
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
                $question[] = "Which Address do you want to delete?";

                $address = $this->in(join("\n", $question));


                $question = "Do you really want to delete the alias '$address'?";
       
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

                $handler =  new AliasHandler();
                $handler->init($address);
                $status = $handler->delete();
                if ($status == true) {
                      $this->out("Alias '$address' was deleted.");
                      
                } else {
                      $this->err($handler->errormsg);
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


                $handler =  new AliasHandler();
                $handler->init($address);
                if ( ! $handler->view() ) {
                    $this->error("Error: Not Found", "The requested alias was not found!");
                } else {
                      $result = $handler->return;

                      $this->out(sprintf("Entries for: %s\n", $address));
                      $this->out("Goto: \t");
                      foreach($result['goto'] AS $goto) {
                        $this->out("\t -> ".$goto);
                      }
                    if( $result['is_mailbox'] ) {
                        $this->out("A mailbox was set for this alias!\n");
                    }
                    if( $result['goto_mailbox'] ) {
                        $this->out("The alias delivers to the mailbox!\n");
                    }
                    if( $result['on_vacation'] ) {
                        $this->out("This alias is a vacation address!");
                    }
                    $this->out("Active: " . $result['active']);
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

/* vim: set expandtab softtabstop=4 tabstop=4 shiftwidth=4: */
