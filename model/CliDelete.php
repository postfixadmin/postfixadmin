<?php
# $Id$ 
/**
 * class to handle 'delete' in Cli
 */

class CliDelete extends Shell {

    /**
     * Execution method always used for tasks
     */
    public function execute() {

        if (empty($this->args)) {
            $this->__interactive();
        }

        if (!empty($this->args[0])) {
            $this->__handle($this->args[0]);
        }
    }

    /**
     * Interactive mode
     */
    protected function __interactive() {
        $module = preg_replace('/Handler$/', '', $this->handler_to_use);
        $module = strtolower($module);

        $question = "Which $module do you want to delete?";
        $address = $this->in($question);

        $question = "Do you really want to delete '$address'?";
        $create = $this->in($question, array('y','n'));

        if ($create == 'y') $this->__handle($address);
    }

    /**
    * actually delete something
    *
    * @param string address to delete
    */
    protected function __handle($address) {
        $handler =  new $this->handler_to_use($this->new);

        if (!$handler->init($address)) {
            $this->err($handler->errormsg);
            return;
        } 

        if (!$handler->delete()) {
            $this->err($handler->errormsg);
        } else {
            $this->out($handler->infomsg);
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

    postfixadmin-cli $module delete

        Deletes $module in interactive mode.

- or -

    postfixadmin-cli $module delete <address>

        Deletes $module <address> in non-interactive mode.
");
        $this->_stop();
    }

}

/* vim: set expandtab softtabstop=4 tabstop=4 shiftwidth=4: */
