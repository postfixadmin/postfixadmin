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
 * Modified for PostfixAdmin by Valkum 2011
 * Modified for PostfixAdmin by Christian Boltz 2011-2013
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
     */
    public $version ='0.2';

    /**
     * Standard input stream.
     *
     * @var filehandle
     */
    public $stdin;

    /**
     * Standard output stream.
     *
     * @var filehandle
     */
    public $stdout;

    /**
     * Standard error stream.
     *
     * @var filehandle
     */
    public $stderr;

    /**
     * Contains command switches parsed from the command line.
     *
     * @var array
     */
    public $params = array();

    /**
     * Contains arguments parsed from the command line.
     *
     * @var array
     */
    public $args = array();

    /**
     * The file name of the shell that was invoked.
     *
     * @var string
     */
    public $shell = null;

    /**
     * The class name of the shell that was invoked.
     *
     * @var string
     */
    public $shellClass = null;

    /**
     * The command called if public methods are available.
     *
     * @var string
     */
    public $shellCommand = null;

    /**
     * The name of the shell in camelized.
     *
     * @var string
     */
    public $shellName = null;

    /**
     * Constructor
     *
     * @param array $args the argv.
     */
    public function __construct($args = array()) {
        set_time_limit(0);
        $this->__initConstants();
        $this->parseParams($args);
        $this->__initEnvironment();
    }

    /**
     * Defines core configuration.
     */
    private function __initConstants() {
        ini_set('display_errors', '1');
        ini_set('error_reporting', E_ALL);
        ini_set('html_errors', false);
        ini_set('implicit_flush', true);
        ini_set('max_execution_time', 0);
    }

    /**
     * Defines current working environment.
     */
    private function __initEnvironment() {
        $this->stdin = fopen('php://stdin', 'r');
        $this->stdout = fopen('php://stdout', 'w');
        $this->stderr = fopen('php://stderr', 'w');

        if (basename(__FILE__) !=  basename($this->args[0])) {
            $this->stderr('Warning: the dispatcher may have been loaded incorrectly, which could lead to unexpected results...');
            if ($this->getInput('Continue anyway?', array('y', 'n'), 'y') == 'n') {
                exit(1);
            }
        }

        $this->shiftArgs();
    }

    /**
     * Dispatches a CLI request
     */
    public function dispatch() {
        check_db_version(); # ensure the database layout is up to date

        if (!isset($this->args[0])) {
            $this->help();
            return;
        }

        $this->shell = $this->args[0];
        $this->shiftArgs();
        $this->shellName = ucfirst($this->shell);
        $this->shellClass = $this->shellName . 'Handler';


        if ($this->shell == 'help') {
            $this->help();
            return;
        }

        $command = 'help'; # not the worst default ;-)
        if (isset($this->args[0])) {
            $command = $this->args[0];
        }

        $this->shellCommand = $command;
        $this->shellClass = 'Cli' . ucfirst($command);

        if (ucfirst($command) == 'Add' || ucfirst($command) == 'Update') {
            $this->shellClass = 'CliEdit';
        }

        if (!class_exists($this->shellClass)) {
            $this->stderr('Unknown task ' . $this->shellCommand);
            return;
        }

        $shell = new $this->shellClass($this);

        $shell->handler_to_use = ucfirst($this->shell) . 'Handler';

        if (!class_exists($shell->handler_to_use)) {
            $this->stderr('Unknown module ' . $this->shell);
            return;
        }

        $task = ucfirst($command);

        $shell->new = 0;
        if ($task == 'Add') {
            $shell->new = 1;
        }

        # TODO: add a way to Cli* to signal if the selected handler is supported (for example, not all *Handler support changing the password)

        if (strtolower(get_parent_class($shell)) == 'shell') {
            $handler = new $shell->handler_to_use;
            if (in_array($task, $handler->taskNames)) {
                $this->shiftArgs();
                $shell->startup();

                if (isset($this->args[0]) && $this->args[0] == 'help') {
                    if (method_exists($shell, 'help')) {
                        $shell->help();
                        exit();
                    } else {
                        $this->help();
                    }
                }

                $shell->execute();
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
            'in', 'out', 'err', 'hr', 'log',
            '__construct', 'dispatch', 'stdout', 'stderr'
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
            $this->stderr("Unknown {$this->shellName} command '$command'.\nFor usage, try 'postfixadmin-cli {$this->shell} help'.\n\n");
        }
    }

    /**
     * Prompts the user for input, and returns it.
     *
     * @param string $prompt Prompt text.
     * @param mixed $options Array or string of options.
     * @param string $default Default input value.
     * @return Either the default value, or the user-provided input.
     */
    public function getInput($prompt, $options = null, $default = null) {
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

        if ($result === false) {
            exit(1);
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
     */
    public function stdout($string, $newline = true) {
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
     */
    public function stderr($string) {
        fwrite($this->stderr, 'Error: '. $string . "\n");
    }

    /**
     * Parses command line options
     *
     * @param array $params Parameters to parse
     */
    public function parseParams($params) {
        $this->__parseParams($params);

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
     */
    private function __parseParams($params) {
        $count = count($params);
        for ($i = 0; $i < $count; $i++) {
            if (isset($params[$i])) {
                if ($params[$i] != '' && $params[$i]{0} === '-') {
                    $key = substr($params[$i], 1);
                    $this->params[$key] = true;
                    unset($params[$i]);
                    if (isset($params[++$i])) {
                        # TODO: ideally we should know if a parameter can / must have a value instead of whitelisting known valid values starting with '-' (probably only bool doesn't need a value)
                        if ($params[$i]{0} !== '-' or $params[$i] != '-1') {
                            $this->params[$key] = $params[$i];
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
     */
    public function shiftArgs() {
        if (empty($this->args)) {
            return false;
        }
        unset($this->args[0]);
        $this->args = array_values($this->args);
        return true;
    }

    /**
     * prints help message and exits.
     */
    public function help() {
        $this->stdout("\nWelcome to Postfixadmin-CLI v" . $this->version);
        $this->stdout("---------------------------------------------------------------");
        $this->stdout("Usage:");
        $this->stdout("    postfixadmin-cli <module> <task> [--option value --option2 value]");
        $this->stdout("");
        $this->stdout("Available modules:");

        $modules = explode(',', 'admin,domain,mailbox,alias,aliasdomain,fetchmail');
        foreach ($modules as $module) {
            $this->stdout("    $module");
        }
        $this->stdout("");
        $this->stdout("Most modules support the following tasks:");
        $this->stdout("    view      View an item");
        $this->stdout("    add       Add an item");
        $this->stdout("    update    Update an item");
        $this->stdout("    delete    Delete an item");
        $this->stdout("    scheme    Print database scheme (useful for developers only)");
        $this->stdout("    help      Print help output");
        $this->stdout("");
        $this->stdout("");
        $this->stdout("For module-specific help, see:");
        $this->stdout("");
        $this->stdout("    postfixadmin-cli <module> help");
        $this->stdout("        print a detailed list of available commands");
        $this->stdout("");
        $this->stdout("    postfixadmin-cli <module> <task> help");
        $this->stdout("        print a list of available options.");
        $this->stdout("");

        exit();
    }
}


define("POSTFIXADMIN_CLI", 1);

require_once(dirname(__FILE__) . '/../common.php');

$dispatcher = new PostfixAdmin($argv);
$dispatcher->dispatch();

/* vim: set expandtab softtabstop=4 tabstop=4 shiftwidth=4: */
