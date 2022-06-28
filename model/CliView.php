<?php

# $Id$
/**
 * class to handle 'view' in Cli
 */

class CliView extends Shell {
    /**
     * Execution method always used for tasks
     */
    public function execute() {
        if (empty($this->args)) {
            return $this->__interactive();
        }

        if (!empty($this->args[0])) {
            return $this->__handle($this->args[0]);
        }
    }

    /**
     * Interactive mode
     */
    protected function __interactive() {
        $module = preg_replace('/Handler$/', '', $this->handler_to_use);
        $module = strtolower($module);

        $question = "Which $module do you want to view?";
        $address = $this->in($question);

        return $this->__handle($address);
    }

    /**
    * actually view something
    *
    * @param string address to view
    */
    protected function __handle($address) {
        $handler =  new $this->handler_to_use($this->new);

        if (!$handler->init($address)) {
            $this->err($handler->errormsg);
            return 1;
        }

        if (!$handler->view()) {
            $this->err($handler->errormsg);
            return 1;
        }

        $result = $handler->result();
        $struct = $handler->getStruct();

        foreach (array_keys($struct) as $field) {
            if (isset($struct[$field]) && empty($struct[$field]['label'])) {
                # $struct[$field]['label'] = "--- $field ---";
                $struct[$field]['display_in_list'] = 0;
            }

            if ($struct[$field]['display_in_list'] == 0) {
                # do nothing
            } else {
                $value = $result[$field];

                $func="_formatted_".$field;
                if (method_exists($handler, $func)) {
                    $value = $handler->{$func}($result); # call _formatted_$fieldname()
                }


                if ($struct[$field]['type'] == 'txtl') {
                    # $value = join("\n" . str_repeat(" ", 20 + 2), $value); # multiline, one item per line
                $value = join(", ", $value); # one line, comma-separated
                } elseif ($struct[$field]['type'] == 'bool') {
                    $value = Config::Lang($value ? 'YES' : 'NO');
                }

                $this->out(sprintf("%20s: %s", $struct[$field]['label'], $value));
            }
        }
    }

    /**
    * Display help contents
    *
    * @access public
    */
    public function help() {
        $module = preg_replace('/Handler$/', '', $this->handler_to_use);
        $module = strtolower($module);

        $this->out(
"Usage:

    postfixadmin-cli $module view

        View $module in interactive mode.

- or -

    postfixadmin-cli $module view <address>

        View $module <address> in non-interactive mode.
"
        );
        $this->_stop();
    }
}

/* vim: set expandtab softtabstop=4 tabstop=4 shiftwidth=4: */
