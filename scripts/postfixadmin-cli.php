#!/usr/bin/php
<?php
/**
 * Command-line code generation utility to automate administrator tasks.
 *
 * Shell dispatcher class
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


class PostfixAdmin {
/**
 * Version 
 *
 * @var string
 * @access protected
 */
  var $version ='0.2';

/**
 * Standard input stream.
 *
 * @var filehandle
 * @access public
 */
        var $stdin;
/**
 * Standard output stream.
 *
 * @var filehandle
 * @access public
 */
        var $stdout;
/**
 * Standard error stream.
 *
 * @var filehandle
 * @access public
 */
        var $stderr;
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
        var $shellClass = null;
/**
 * The command called if public methods are available.
 *
 * @var string
 * @access public
 */
        var $shellCommand = null;
/**
 * The path locations of shells.
 *
 * @var array
 * @access public
 */
        var $shellPaths = array();
/**
 * The path to the current shell location.
 *
 * @var string
 * @access public
 */
        var $shellPath = null;
/**
 * The name of the shell in camelized.
 *
 * @var string
 * @access public
 */
        var $shellName = null;
/**
 * Constructs this ShellDispatcher instance.
 *
 * @param array $args the argv.
 */
        function PostfixAdmin($args = array()) {
                $this->__construct($args);
        }
/**
 * Constructor
 *
 * @param array $args the argv.
 */
        function __construct($args = array()) {
                set_time_limit(0);
                $this->__initConstants();
                $this->parseParams($args);
                $this->__initEnvironment();
                /*$this->dispatch();
                die("\n");*/
        }
/**
 * Defines core configuration.
 *
 * @access private
 */
        function __initConstants() {
                if (function_exists('ini_set')) {
                        ini_set('display_errors', '1');
                        ini_set('error_reporting', E_ALL);
                        ini_set('html_errors', false);
                        ini_set('implicit_flush', true);
                        ini_set('max_execution_time', 0);
                }
                
                define('DS', DIRECTORY_SEPARATOR);
                define('PHP5', (PHP_VERSION >= 5));
                define('CORE_INCLUDE_PATH', dirname(__FILE__));
                define('CORE_PATH', substr(CORE_INCLUDE_PATH, 0, -8) );
                
                if(!defined('POSTFIXADMIN')) { # already defined if called from setup.php
                        define('POSTFIXADMIN', 1); # checked in included files
                }


        }
/**
 * Defines current working environment.
 *
 * @access private
 */
        function __initEnvironment() {
                $this->stdin = fopen('php://stdin', 'r');
                $this->stdout = fopen('php://stdout', 'w');
                $this->stderr = fopen('php://stderr', 'w');

                if (!$this->__bootstrap()) {
                        $this->stderr("");
                        $this->stderr("Unable to load.");
                        $this->stderr("\tMake sure /config.inc.php exists in " . PATH);
                        exit();
                }


                if (basename(__FILE__) !=  basename($this->args[0])) {
                        $this->stderr("\nCakePHP Console: ");
                        $this->stderr('Warning: the dispatcher may have been loaded incorrectly, which could lead to unexpected results...');
                        if ($this->getInput('Continue anyway?', array('y', 'n'), 'y') == 'n') {
                                exit();
                        }
                }

                $this->shiftArgs();
                
                

        }
/**
 * Initializes the environment and loads the Cake core.
 *
 * @return boolean Success.
 * @access private
 */
        function __bootstrap() {
                if ($this->params['webroot'] != '' ) {
                        define('PATH', $this->params['webroot'] );
                } else {
                        define('PATH', CORE_PATH);
                }            
                if (!file_exists(PATH)) {
                        $this->stderr( PATH . " don't exists");
                        return false;
                
                }
                $includes = array(
                        PATH.'/config.inc.php',
                        PATH.'/languages/language.php',
                        PATH.'/functions.inc.php',
                        PATH.'/languages/en.lang', # TODO: honor $CONF[default_language] and/or language from $_ENV
                        CORE_INCLUDE_PATH.'/common.php',
                        CORE_INCLUDE_PATH.'/inflector.php',
                );

                foreach ($includes as $inc) {
                        if (!require_once($inc)) {
                                $this->stderr("Failed to load {$inc}");
                                return false;
                        }
                }
                Config::getInstance();
                Config::write($CONF);
                
                Lang::getInstance();

                if($CONF['language_hook'] != '' && function_exists($CONF['language_hook'])) {
                    $hook_func = $CONF['language_hook'];
                    $PALANG = $hook_func ($PALANG, 'en'); # $includes is also hardcoded to 'en' - see TODO above
                }
                
                Lang::write($PALANG);

                return true;
        }

/**
 * Dispatches a CLI request
 *
 * @access public
 */
        function dispatch() {
        $CONF = Config::read('all');
                if (isset($this->args[0])) {
                        $plugin = null;
                        $shell = $this->args[0];
                        if (strpos($shell, '.') !== false)  {
                                list($plugin, $shell) = explode('.', $this->args[0]);
                        }

                        $this->shell = $shell;
                        $this->shiftArgs();
                        $this->shellName = Inflector::camelize($this->shell);
                        $this->shellClass = 'PostfixAdmin'.$this->shellName;
                        

                        if ($this->shell == 'help') {
                                $this->help();
                        } else {
                                $loaded = false;
                                $paths = array();

                                if ($plugin !== null) {
                                        $pluginPaths = Config::read('pluginPaths');
                                        $count = count($pluginPaths);
                                        for ($i = 0; $i < $count; $i++) {
                                                $paths[] = $pluginPaths[$i] . $plugin . DS . 'vendors' . DS . 'shells' . DS;
                                        }
                                }


                                $paths[] = CORE_INCLUDE_PATH . DS . "shells" . DS;

                                $this->shellPaths = $paths;
                                foreach ($this->shellPaths as $path) {
                                        $this->shellPath = $path . $this->shell . ".php";
                                        if (file_exists($this->shellPath)) {
                                                $loaded = true;
                                                break;
                                        }
                                }
                                if ($loaded) {
                                        if (!class_exists('Shell')) {
                                                require CORE_INCLUDE_PATH . DS . "shells" . DS . 'shell.php';
                                        }

                                        require $this->shellPath;
                                        if (class_exists($this->shellClass)) {
                                                $command = null;
                                                if (isset($this->args[0])) {
                                                        $command = $this->args[0];
                                                }
                                                $this->shellCommand = $command;
                                                $shell = new $this->shellClass($this);

                                                if (strtolower(get_parent_class($shell)) == 'shell') {
                                                        $shell->initialize();
                                                        $shell->loadTasks();

                                                        foreach ($shell->taskNames as $task) {
                                                                if (strtolower(get_parent_class($shell)) == 'shell') {
                                                                        $shell->{$task}->initialize();
                                                                        $shell->{$task}->loadTasks();
                                                                }
                                                        }

                                                        $task = Inflector::camelize($command);
                                                        if (in_array($task, $shell->taskNames)) {
                                                                $this->shiftArgs();
                                                                $shell->{$task}->startup();
                                                                if (isset($this->args[0]) && $this->args[0] == 'help') {
                                                                        if (method_exists($shell->{$task}, 'help')) {
                                                                                $shell->{$task}->help();
                                                                                exit();
                                                                        } else {
                                                                                $this->help();
                                                                        }
                                                                }
                                                                $shell->{$task}->execute();
                                                                return;
                                                        }
                                                }

                                                $classMethods = get_class_methods($shell);

                                                $privateMethod = $missingCommand = false;
                                                if ((in_array($command, $classMethods) || in_array(strtolower($command), $classMethods)) && strpos($command, '_', 0) === 0) {
                                                        $privateMethod = true;
                                                }

                                                if (!in_array($command, $classMethods) && !in_array(strtolower($command), $classMethods)) {
                                                        $missingCommand = true;
                                                }

                                                $protectedCommands = array(
                                                        'initialize','in','out','err','hr',
                                                        'createfile', 'isdir','copydir','object','tostring',
                                                        'requestaction','log','cakeerror', 'shelldispatcher',
                                                        '__initconstants','__initenvironment','__construct',
                                                        'dispatch','__bootstrap','getinput','stdout','stderr','parseparams','shiftargs'
                                                );

                                                if (in_array(strtolower($command), $protectedCommands)) {
                                                        $missingCommand = true;
                                                }

                                                if ($missingCommand && method_exists($shell, 'main')) {
                                                        $shell->startup();
                                                        $shell->main();
                                                } elseif (!$privateMethod && method_exists($shell, $command)) {
                                                        $this->shiftArgs();
                                                        $shell->startup();
                                                        $shell->{$command}();
                                                } else {
                                                        $this->stderr("Unknown {$this->shellName} command '$command'.\nFor usage, try 'cake {$this->shell} help'.\n\n");
                                                }
                                        } else {
                                                $this->stderr('Class '.$this->shellClass.' could not be loaded');
                                        }
                                } else {
                                        $this->help();
                                }
                        }
                } else {
                        $this->help();
                }
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
        function getInput($prompt, $options = null, $default = null) {
                if (!is_array($options)) {
                        $print_options = '';
                } else {
                        $print_options = '(' . implode('/', $options) . ')';
                }

                if ($default == null) {
                        $this->stdout($prompt . " $print_options \n" . '> ', false);
                } else {
                        $this->stdout($prompt . " $print_options \n" . "[$default] > ", false);
                }
                $result = fgets($this->stdin);

                if ($result === false){
                        exit;
                }
                $result = trim($result);

                if ($default != null && empty($result)) {
                        return $default;
                }
                return $result;
        }
/**
 * Outputs to the stdout filehandle.
 *
 * @param string $string String to output.
 * @param boolean $newline If true, the outputs gets an added newline.
 * @access public
 */
        function stdout($string, $newline = true) {
                if ($newline) {
                        fwrite($this->stdout, $string . "\n");
                } else {
                        fwrite($this->stdout, $string);
                }
        }
/**
 * Outputs to the stderr filehandle.
 *
 * @param string $string Error text to output.
 * @access public
 */
        function stderr($string) {
                fwrite($this->stderr, 'Error: '. $string . "\n");
        }

/**
 * Parses command line options
 *
 * @param array $params Parameters to parse
 * @access public
 */
        function parseParams($params) {
                $this->__parseParams($params);

                $defaults = array('webroot' => CORE_PATH);

                $params = array_merge($defaults, array_intersect_key($this->params, $defaults));

                $isWin = array_filter(array_map('strpos', $params, array('\\')));

                $params = str_replace('\\', '/', $params);


                if (!empty($matches[0]) || !empty($isWin)) {
                        $params = str_replace('/', '\\', $params);
                }

                $this->params = array_merge($this->params, $params);
        }
/**
 * Helper for recursively paraing params
 *
 * @return array params
 * @access private
 */
        function __parseParams($params) {
                $count = count($params);
                for ($i = 0; $i < $count; $i++) {
                        if (isset($params[$i])) {
                                if ($params[$i]{0} === '-') {
                                        $key = substr($params[$i], 1);
                                        $this->params[$key] = true;
                                        unset($params[$i]);
                                        if (isset($params[++$i])) {
                                                if ($params[$i]{0} !== '-') {
                                                        $this->params[$key] = str_replace('"', '', $params[$i]);
                                                        unset($params[$i]);
                                                } else {
                                                        $i--;
                                                        $this->__parseParams($params);
                                                }
                                        }
                                } else {
                                        $this->args[] = $params[$i];
                                        unset($params[$i]);
                                }

                        }
                }
        }
/**
 * Removes first argument and shifts other arguments up
 *
 * @return boolean False if there are no arguments
 * @access public
 */
        function shiftArgs() {
                if (empty($this->args)) {
                        return false;
                }
                unset($this->args[0]);
                $this->args = array_values($this->args);
                return true;
        }

        function help() {
                $this->stdout("\nWelcome to Postfixadmin-CLI v" . $this->version);
                $this->stdout("---------------------------------------------------------------");
                $this->stdout("Options:");
                $this->stdout(" -webroot: " . $this->params['webroot']);
                $this->stdout("");
                $this->stdout("Changing Paths:");
                $this->stdout("your webroot should be the same as your postfixadmin path");
                $this->stdout("to change your path use the '-webroot' param.");
                $this->stdout("Example: -webroot r/absolute/path/to/postfixadmin");

                $this->stdout("\nAvailable Commands:");
                foreach ($this->commands() AS $command => $desc) {
                        if (is_array($desc)) {
                                $this->stdout($command . ":");
                                foreach($desc AS $command2 => $desc2) {
                                        $this->stdout(sprintf("%-20s %s", "   ".$command2 .": ", $desc2));
                                }
                                $this->stdout("");
                        } else {
                                $this->stdout(sprintf("%-20s %s", $command .": ",  $desc));
                        }
                }
                $this->stdout("\nTo run a command, type 'postfixadmin-cli command [args]'");
                $this->stdout("To get help on a specific command, type 'postfixadmin-cli command help'");
                exit();

        }
/**
 * Removes first argument and shifts other arguments up
 *
 * @return array List of commands
 * @access public
 */
        function commands() {
        
        
        
        return array(
                'mailbox' => array(
                           'add'=> 'Adds a new mailbox.', 
                           'update'=> 'Updates a mailbox.', 
                           'delete' => 'Deletes a mailbox.', 
                           'pw' => 'Changes the PW for a mailbox.',
                ), 
                'alias' => array(
                            'add' => 'Adds a new alias.',
                            'update' => 'Updates a alias.',
                            'delete' => 'Deletes a alias.',
                ), 
                'version' => 'Prints version of Postfixadmin and Postfixadmin-CLI' 
                );
        
        
        
        
        }



}


define ("POSTFIXADMIN_CLI", 1);

$dispatcher = new PostfixAdmin($argv);

$CONF = Config::read('all');

//bugfix shitty globals and OOP.....

$table_admin = table_by_key ('admin');
$table_alias = table_by_key ('alias');
$table_alias_domain = table_by_key ('alias_domain');
$table_domain = table_by_key ('domain');
$table_domain_admins = table_by_key ('domain_admins');
$table_log = table_by_key ('log');
$table_mailbox = table_by_key ('mailbox');
$table_vacation = table_by_key ('vacation');
$table_vacation_notification = table_by_key('vacation_notification');
$table_quota = table_by_key ('quota');
$table_quota2 = table_by_key ('quota2');

$dispatcher->dispatch();
?>
