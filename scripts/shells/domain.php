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
