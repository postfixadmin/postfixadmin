<?php
/**
 * class to handle add and edit in Cli
 *
 * extends the "Shell" class
 */

class CliEdit extends Shell {

    public $handler_to_use = "";
    public $new = 0;


    /**
    * Execution method always used for tasks
    */
    public function execute() {
        if (empty($this->args)) {
            $this->__interactive();
        }

        if (!empty($this->args[0])) {
            # print_r($this->args); print_r($this->params); die;
            # TODO: handle the various -* parameters (see help)
            $this->__handle($this->args[0], $this->args[1]);
        }
    }

    /**
    * Interactive mode
    */
    private function __interactive() {

        $handler = new $this->handler_to_use($this->new);

        $form_fields = $handler->getStruct();
        $id_field    = $handler->getId_field();

        while(0==0) { # endlees loop - except if input is valid
            $question = $form_fields[$id_field]['label'] . ":";
            if ( $form_fields[$id_field]['desc'] != '') {
                $question .= "\n(" . $form_fields[$id_field]['desc'] . ')';
            }

            $values[$id_field] = $this->in($form_fields[$id_field]['label'] . ':');

            if ($handler->init($values[$id_field])) {
                break;
            } else {
                $this->err($handler->errormsg);
                # always use a fresh handler to avoid problems with previous error messages
                $handler = new $this->handler_to_use($this->new);
            }
        }

        # update $form_fields (needed for example to display the correct allowed quota)
        # TODO: doesn't (always?) work - wrong time for the refresh?
#        $handler->set(array());
        $form_fields = $handler->getStruct();

        foreach($form_fields as $key => $field) {

            if ($field['editable'] && $field['display_in_form'] && $key != $id_field) {

                while(0==0) { # endlees loop - except if input is valid
                    $question = $field['label'] . ':';
                    if ($field['desc'] != '') {
                        $question .= "\n(" . $field['desc'] . ')';
                    }

                    if ($field['type'] == 'bool') {
                        $values[$key] = $this->in($question, array ('y', 'n') );

                        if ($values[$key] == 'y') {
                            $values[$key] = 1;
                        } else {
                            $values[$key] = 0;
                        }

                    } elseif ($field['type'] == 'enum') {
                        $optiontxt = array();
                        $optionlist = array();

                        foreach ($field['options'] AS $optionkey => $optionval) {
                            // $this->in hates number 0
                            $optionkey = $optionkey + 1;
                            $optiontxt[] = '['.$optionkey.'] - '.$optionval;
                            $optionlist[] = $optionkey;
                        }

                        $question .= "\n" . join("\n", $optiontxt) . "\n";

                        $values[$key] = $this->in($question, $optionlist);

                        $values[$key] = $field['options'][$values[$key]-1]; # convert int to option name

                    } elseif ($field['type'] == 'txtl') {
                        $values[$key] = array();
                        $nextval = $this->in($question);
                        while ($nextval != '') {
                            if ($nextval != '') {
                                $values[$key][] = $nextval;
                            }
                            $nextval = $this->in("");
                        }

                    } else {
                        $values[$key] = $this->in($question);
                    }

                    if (is_null($values[$key]) ) { # TODO: insull() is probably obsoleted by change in Shell class
echo "*** value of $key is NULL - this should not happen! ***";
                    }

                    if ($values[$key] == '' && (!$this->new) ) { # edit mode
                        unset ($values[$key]); # empty input - don't change
                    }

                    # always use a fresh handler to avoid problems with previous error messages
                    $handler = new $this->handler_to_use($this->new);
                    $handler->init($values[$id_field]);

                    $handler->set($values);

                    if ( isset($handler->errormsg[$key]) ) { # only check the errormessage for this field
                        $this->err($handler->errormsg[$key]);
                    } else {
                        break;
                    }
                } # end while

            } # end if $field[editable] etc.
        } # end foreach

        $this->__handle($values[$id_field], $values);
    }

    /**
    * (try to) store values
    */
    private function __handle($id, $values) {

        $handler =  new $this->handler_to_use($this->new);
        if (!$handler->init($id)) {
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
            $this->out($handler->infomsg);
            $this->hr();
        }
        return;
    }

    /**
    * Displays help contents
    */
    public function help() {
# TODO: generate from $struct
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
/* vim: set expandtab softtabstop=4 tabstop=4 shiftwidth=4: */
