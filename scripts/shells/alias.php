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

/* vim: set expandtab softtabstop=4 tabstop=4 shiftwidth=4: */
