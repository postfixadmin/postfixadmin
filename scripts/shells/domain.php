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

/* vim: set expandtab softtabstop=4 tabstop=4 shiftwidth=4: */
