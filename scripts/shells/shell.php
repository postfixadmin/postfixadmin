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
        var $Dispatch = null;
/**
 * If true, the script will ask for permission to perform actions.
 *
 * @var boolean
 * @access public
 */
        var $interactive = true;
/**
 * Holds the DATABASE_CONFIG object for the app. Null if database.php could not be found,
 * or the app does not exist.
 *
 * @var object
 * @access public
 */
        var $DbConfig = null;
/**
 * Contains command switches parsed from the command line.
 *
 * @var array
 * @access public
 */
        var $params = array();
/**
 * Contains arguments parsed from the command line.
 *
 * @var array
 * @access public
 */
        var $args = array();
/**
 * The file name of the shell that was invoked.
 *
 * @var string
 * @access public
 */
        var $shell = null;
/**
 * The class name of the shell that was invoked.
 *
 * @var string
 * @access public
 */
        var $className = null;
/**
 * The command called if public methods are available.
 *
 * @var string
 * @access public
 */
        var $command = null;
/**
 * The name of the shell in camelized.
 *
 * @var string
 * @access public
 */
        var $name = null;
/**
 * Contains tasks to load and instantiate
 *
 * @var array
 * @access public
 */
        var $tasks = array();
/**
 * Contains the loaded tasks
 *
 * @var array
 * @access public
 */
        var $taskNames = array();
/**
 * Contains models to load and instantiate
 *
 * @var array
 * @access public
 */
        var $uses = array();
/**
 *  Constructs this Shell instance.
 *
 */
        function __construct(&$dispatch) {
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

                $shellKey = Inflector::underscore($this->className);

                if (!PHP5 && isset($this->args[0])) {
                        if(strpos($this->className, low(Inflector::camelize($this->args[0]))) !== false) {
                                $dispatch->shiftArgs();
                        }
                        if (low($this->command) == low(Inflector::variable($this->args[0])) && method_exists($this, $this->command)) {
                                $dispatch->shiftArgs();
                        }
                }

                $this->Dispatch =& $dispatch;
        }
        
/**
 * Initializes the Shell
 * acts as constructor for subclasses
 * allows configuration of tasks prior to shell execution
 *
 * @access public
 */
        function initialize() {
        }
/**
 * Starts up the the Shell
 * allows for checking and configuring prior to command or main execution
 * can be overriden in subclasses
 *
 * @access public
 */
        function startup() {
                $this->_welcome();
        }
/**
 * Displays a header for the shell
 *
 * @access protected
 */
        function _welcome() {
                $this->out("\nWelcome to Postfixadmin-CLI v" . $this->Dispatch->version);
                $this->out("---------------------------------------------------------------");
                $this->out('Path: '. PATH);
                $this->hr();
        }
        
        /**
 * Loads tasks defined in var $tasks
 *
 * @return bool
 * @access public
 */
        function loadTasks() {
                if ($this->tasks === null || $this->tasks === false) {
                        return;
                }

                if ($this->tasks !== true && !empty($this->tasks)) {

                        $tasks = $this->tasks;
                        if (!is_array($tasks)) {
                                $tasks = array($tasks);
                        }

                        foreach ($tasks as $taskName) {
                                $task = Inflector::underscore($taskName);
                                $taskClass = Inflector::camelize($taskName.'Task');
                                $taskKey = Inflector::underscore($taskClass);

                                if (!class_exists($taskClass)) {
                                        foreach ($this->Dispatch->shellPaths as $path) {
                                                $taskPath = $path . 'tasks' . DS . $task.'.php';
                                                if (file_exists($taskPath)) {
                                                        require_once $taskPath;
                                                        break;
                                                }
                                        }
                                }
                                
                                        $this->taskNames[] = $taskName;
                                        if (!PHP5) {
                                                $this->{$taskName} =& new $taskClass($this->Dispatch);
                                        } else {
                                                $this->{$taskName} = new $taskClass($this->Dispatch);
                                        }
                                

                                if (!isset($this->{$taskName})) {
                                        $this->err("Task '".$taskName."' could not be loaded");
                                        $this->_stop();
                                }
                        }
                }

                return false;
        }
        /**
 * Prompts the user for input, and returns it.
 *
 * @param string $prompt Prompt text.
 * @param mixed $options Array or string of options.
 * @param string $default Default input value.
 * @return Either the default value, or the user-provided input.
 * @access public
 */
        function in($prompt, $options = null, $default = null) {
                if (!$this->interactive) {
                        return $default;
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
                        while ($in == '' || ($in && (!in_array(low($in), $options) && !in_array(up($in), $options)) && !in_array($in, $options))) {
                                $in = $this->Dispatch->getInput($prompt, $options, $default);
                        }
                }
                if ($in) {
                        return $in;
                }
        }
/**
 * Outputs to the stdout filehandle.
 *
 * @param string $string String to output.
 * @param boolean $newline If true, the outputs gets an added newline.
 * @access public
 */
        function out($string, $newline = true) {
                if (is_array($string)) {
                        $str = '';
                        foreach($string as $message) {
                                $str .= $message ."\n";
                        }
                        $string = $str;
                }
                return $this->Dispatch->stdout($string, $newline);
        }
/**
 * Outputs to the stderr filehandle.
 *
 * @param string $string Error text to output.
 * @access public
 */
        function err($string) {
                if (is_array($string)) {
                        $str = '';
                        foreach($string as $message) {
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
        function hr($newline = false) {
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
        function error($title, $msg) {
                $out  = "$title\n";
                $out .= "$msg\n";
                $out .= "\n";
                $this->err($out);
                $this->_stop();
        }
        /**
 * Outputs usage text on the standard output. Implement it in subclasses.
 *
 * @access public
 */
        function help() {
                if ($this->command != null) {
                        $this->err("Unknown {$this->name} command '$this->command'.\nFor usage, try 'cake {$this->shell} help'.\n\n");
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
        function _stop($status = 0) {
                exit($status);
        }
        
        
 
 }