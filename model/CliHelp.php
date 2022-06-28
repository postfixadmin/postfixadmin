<?php

# $Id$

class CliHelp extends Shell {
    public $handler_to_use = "__not_set__";

    /**
     * Show help for this shell.
     *
     * @access public
     */
    public function execute() {
        $this->help();
    }

    public function help() {
        $handler = new $this->handler_to_use();
        # TODO: adjust help text according to $handler->taskNames

        $module = preg_replace('/Handler$/', '', $this->handler_to_use);
        $module = strtolower($module);

        $this->out(
"Usage:

    postfixadmin-cli $module <task> [<address>] [--option value]
"
        );
        /*
                View $module in interactive mode.

        - or -

            postfixadmin-cli $module view <address>

                View $module <address> in non-interactive mode.
        "); */



        $head  = "Usage: postfixadmin-cli $module <task> [<address>] [--option value] [--option value]\n";
        $head .= "-----------------------------------------------\n";
        $head .= "Parameters:\n\n";

        $commands = array(
            'task' => "\t<task>\n" .
                        "\t\tAvailable values:\n\n".
                        "\t\t".sprintf("%-20s %s", "view: ",   "View an existing $module.")."\n".
                        "\t\t".sprintf("%-20s %s", "add: ",    "Add a $module.")."\n".
                        "\t\t".sprintf("%-20s %s", "update: ", "Update a $module.")."\n".
                        "\t\t".sprintf("%-20s %s", "delete: ", "Delete a $module")."\n",
            'address' => "\t[<address>]\n" .
                        "\t\tA address of recipient.\n",
        );

        foreach ($commands as $cmd) {
            $this->out("{$cmd}\n\n");
        }
    }
}

/* vim: set expandtab softtabstop=4 tabstop=4 shiftwidth=4: */
