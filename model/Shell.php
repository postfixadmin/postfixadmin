<?php
/**
 * Base class for Shells
 *
 * Long description for file
 *
 * PHP versions 4 and 5
 *
 * CakePHP(tm) :  Rapid Development Framework <http://www.cakephp.org/>
 * Copyright 2005-2008, Cake Software Foundation, Inc.
 *                                                              1785 E. Sahara Avenue, Suite 490-204
 *                                                              Las Vegas, Nevada 89104
 * Modified for Postfixadmin by Valkum
 *
 * Copyright 2010
 *
 * Licensed under The MIT License
 * Redistributions of files must retain the above copyright notice.
 *
 * @filesource
 * @copyright           Copyright 2005-2008, Cake Software Foundation, Inc.
 * @link                                http://postfixadmin.sourceforge.net/ Postfixadmin on Sourceforge
 * @package                     postfixadmin
 * @subpackage          -
 * @since                       -
 * @version                     $Revision$
 * @modifiedby          $LastChangedBy$
 * @lastmodified        $Date$
 * @license                     http://www.opensource.org/licenses/mit-license.php The MIT License
 */

class Shell {
    /**
     * An instance of the ShellDispatcher object that loaded this script
     *
     * @var object
     * @access public
     */
    public $Dispatch;

    /**
     * If true, the script will ask for permission to perform actions.
     *
     * @var boolean
     * @access public
     */
    public $interactive = true;
    /**
     * Contains command switches parsed from the command line.
     *
     * @var array
     * @access public
     */
    public $params = array();
    /**
     * Contains arguments parsed from the command line.
     *
     * @var array
     * @access public
     */
    public $args = array();

    /**
     * The file name of the shell that was invoked.
     *
     * @var string
     * @access public
     */
    public $shell;

    /**
     * The class name of the shell that was invoked.
     *
     * @var string
     * @access public
     */
    public $className;

    /**
     * The command called if public methods are available.
     *
     * @var string
     * @access public
     */

    public $command;
    /**
     * The name of the shell in camelized.
     *
     * @var string
     * @access public
     */
    public $name;

    /**
     * @param string
     */
    public $handler_to_use;

    public $new;
    /**
     *  Constructs this Shell instance.
     *
     */
    public function __construct(&$dispatch) {
        $vars = array('params', 'args', 'shell', 'shellCommand'=> 'command');
        foreach ($vars as $key => $var) {
            if (is_string($key)) {
                $this->{$var} =& $dispatch->{$key};
            } else {
                $this->{$var} =& $dispatch->{$var};
            }
        }

        $this->className = get_class($this);

        if ($this->name == null) {
            $this->name = str_replace(array('shell', 'Shell', 'task', 'Task'), '', $this->className);
        }

        $this->Dispatch =& $dispatch;
    }

    /**
     * Starts up the the Shell
     * allows for checking and configuring prior to command or main execution
     * can be overriden in subclasses
     *
     * @access public
     */
    public function startup() {
        if (empty($this->params['q'])) {
            $this->_welcome();
        }
    }
    /**
     * Displays a header for the shell
     *
     * @access protected
     */
    public function _welcome() {
        $this->out("\nWelcome to Postfixadmin-CLI v" . $this->Dispatch->version);
        $this->hr();
    }

    /**
     * Prompts the user for input, and returns it.
     *
     * @param string $prompt Prompt text.
     * @param mixed $options Array or string of options.
     * @param string $default Default input value.
     * @return string either the default value, or the user-provided input.
     */
    public function in($prompt, $options = null, $default = '') {
        if (!$this->interactive) {
            return $default;
        }
        if ($prompt != '') {
            $this->out("");
        }
        $in = $this->Dispatch->getInput($prompt, $options, $default);

        if ($options && is_string($options)) {
            if (strpos($options, ',')) {
                $options = explode(',', $options);
            } elseif (strpos($options, '/')) {
                $options = explode('/', $options);
            } else {
                $options = array($options);
            }
        }
        if (is_array($options)) {
            while ($in == '' || ($in && (!in_array(strtolower($in), $options) && !in_array(strtoupper($in), $options)) && !in_array($in, $options))) {
                $this->err("Invalid input"); # TODO: make translateable
                $in = $this->Dispatch->getInput($prompt, $options, $default);
            }
        }

        return $in;
    }
    /**
     * Outputs to the stdout filehandle.
     *
     * @param string|array $string String to output.
     * @param boolean $newline If true, the outputs gets an added newline.
     */
    public function out($string, $newline = true) {
        if (is_array($string)) {
            $str = '';
            foreach ($string as $message) {
                $str .= $message ."\n";
            }
            $string = $str;
        }
        return $this->Dispatch->stdout($string, $newline);
    }
    /**
     * Outputs to the stderr filehandle.
     *
     * @param string|array $string Error text to output.
     * @access public
     */
    public function err($string) {
        if (is_array($string)) {
            $str = '';
            foreach ($string as $message) {
                $str .= $message ."\n";
            }
            $string = $str;
        }
        return $this->Dispatch->stderr($string."\n");
    }
    /**
     * Outputs a series of minus characters to the standard output, acts as a visual separator.
     *
     * @param boolean $newline If true, the outputs gets an added newline.
     * @access public
     */
    public function hr($newline = false) {
        if ($newline) {
            $this->out("\n");
        }
        $this->out('---------------------------------------------------------------');
        if ($newline) {
            $this->out("\n");
        }
    }
    /**
     * Displays a formatted error message and exits the application
     *
     * @param string $title Title of the error message
     * @param string $msg Error message
     * @access public
     */
    public function error($title, $msg) {
        $out  = "$title\n";
        $out .= "$msg\n";
        $out .= "\n";
        $this->err($out);
        $this->_stop(1);
    }
    /**
     * Outputs usage text on the standard output. Implement it in subclasses.
     *
     * @access public
     */
    public function help() {
        if ($this->command != null) {
            $this->err("Unknown {$this->name} command '$this->command'.\nFor usage, try 'postfixadmin-cli {$this->shell} help'.\n\n");
        } else {
            $this->Dispatch->help();
        }
    }
    /**
     * Stop execution of the current script
     *
     * @param $status see http://php.net/exit for values
     * @return void
     * @access public
     */
    public function _stop($status = 0) {
        exit($status);
    }
}
