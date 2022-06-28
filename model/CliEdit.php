<?php

# $Id$

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
            return $this->__interactive();
        } else {
            return $this->__handle_params();
        }
    }

    /**
     * non-interactive mode
     * read, check and handle all --* parameters
     * The list of allowed params is based on $handler->struct
     */
    private function __handle_params() {
        $handler = new $this->handler_to_use($this->new);
        $form_fields = $handler->getStruct();
        $id_field = $handler->getId_field();

        $values = array();
        $param_error = 0;

        foreach ($this->params as $key => $val) {
            $key = preg_replace('/^-/', '', $key); # allow --param, not only -param
            $key = str_replace('-', '_', $key);    # allow --foo-bar even if field is named foo_bar

            if (isset($form_fields[$key]) && $form_fields[$key]['editable'] && $form_fields[$key]['display_in_form'] && $key != $id_field) {
                if ($form_fields[$key]['type'] == 'txtl') {
                    $values[$key] = explode(',', $val);
                } elseif ($form_fields[$key]['type'] == 'bool') {
                    if (strtolower($val) == 'y') {
                        $val = 1;
                    } # convert y to 1
                    elseif (strtolower($val) == 'n') {
                        $val = 0;
                    } # convert n to 0
                    $values[$key] = $val; # don't modify any other value - *Handler will complain if it's invalid ;-)
                } else {
                    $values[$key] = $val;
                }
            } elseif ($key == 'webroot') {
                # always set, ignore
            } else { # not editable, unknown field etc.
                $param_error = 1;
                $this->err("invalid parameter --$key => $val");
                return 1;
            }
        }

        return $this->__handle($this->args[0], $values);
    }

    /**
     * Interactive mode
     */
    private function __interactive() {
        $handler = new $this->handler_to_use($this->new);

        $form_fields = $handler->getStruct();
        $id_field = $handler->getId_field();


        $values = array($id_field => '');
        while ($form_fields[$id_field]['editable'] != 0) { # endlees loop - except if input is valid or id_field is not editable (like auto_increment)
            $question = $form_fields[$id_field]['label'] . ":";
            if ($form_fields[$id_field]['desc'] != '') {
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

        foreach ($form_fields as $key => $field) {
            if ($field['editable'] && $field['display_in_form'] && $key != $id_field) {
                while (true) { # endlees loop - except if input is valid
                    $question = $field['label'] . ':';
                    if ($field['desc'] != '') {
                        $question .= "\n(" . $field['desc'] . ')';
                    }

                    if ($field['type'] == 'bool') {
                        $values[$key] = $this->in($question, array('y', 'n'));

                        if ($values[$key] == 'y') {
                            $values[$key] = 1;
                        } else {
                            $values[$key] = 0;
                        }
                    } elseif ($field['type'] == 'enum') {
                        $optiontxt = array();
                        $optionlist = array();

                        foreach ($field['options'] as $optionkey => $optionval) {
                            // $this->in hates number 0
                            $optionkey = $optionkey + 1;
                            $optiontxt[] = '[' . $optionkey . '] - ' . $optionval;
                            $optionlist[] = $optionkey;
                        }

                        $question .= "\n" . join("\n", $optiontxt) . "\n";

                        $selected = (int) $this->in($question, $optionlist);

                        $values[$key] = $field['options'][$selected - 1]; # convert int to option name
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

                    if (is_null($values[$key])) { # TODO: insull() is probably obsoleted by change in Shell class
                        $this->err("*** value of $key is NULL - this should not happen! ***");
                        return 1;
                    }

                    if ($values[$key] == '' && (!$this->new)) { # edit mode
                        unset($values[$key]); # empty input - don't change
                    }

                    # always use a fresh handler to avoid problems with previous error messages
                    $handler = new $this->handler_to_use($this->new);
                    $handler->init($values[$id_field]);

                    $handler->set($values);

                    if (isset($handler->errormsg[$key])) { # only check the errormessage for this field
                        $this->err($handler->errormsg[$key]);
                        return 1;
                    } else {
                        break;
                    }
                } # end while
            } # end if $field[editable] etc.
        } # end foreach

        return $this->__handle($values[$id_field], $values);
    }

    /**
     * (try to) store values
     */
    private function __handle($id, $values) {
        $handler = new $this->handler_to_use($this->new);
        if (!$handler->init($id)) {
            $this->err($handler->errormsg);
            return 1;
        }

        if (!$handler->set($values)) {
            $this->err($handler->errormsg);
            return 1;
        }

        if (!$handler->save()) {
            $this->err($handler->errormsg);
            return 1;
        }

        $this->out("");
        $this->out($handler->infomsg);
        $this->hr();
        return 0;
    }

    /**
     * Displays help contents
     */
    public function help() {
        if ($this->new) {
            $cmd = 'add';
            $cmdtext = 'Adds';
        } else {
            $cmd = 'update';
            $cmdtext = 'Changes';
        }

        $module = preg_replace('/Handler$/', '', $this->handler_to_use);
        $module = strtolower($module);

        $this->out(
            "Usage:

    postfixadmin-cli $module $cmd

        $cmdtext $module in interactive mode.

- or -

    postfixadmin-cli $module $cmd <address> --option value --option2 value [...]

        $cmdtext $module in non-interactive mode.

        Available options are:
"
        );

        $handler = new $this->handler_to_use($this->new);

        $form_fields = $handler->getStruct();
        $id_field = $handler->getId_field();

        foreach ($form_fields as $key => $field) {
            if ($field['editable'] && $field['display_in_form'] && $key != $id_field) {
                $optkey = str_replace('_', '-', $key);
                $this->out("        --$optkey");
                $this->out("            " . $field['label']);
                if ($field['desc']) {
                    $this->out("            " . $field['desc']);
                }
                $this->out("");
            }
        }


        $this->_stop(1);
    }
}
/* vim: set expandtab softtabstop=4 tabstop=4 shiftwidth=4: */
