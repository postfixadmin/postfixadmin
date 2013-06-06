<?php
# $Id$

class Config {
    /**
     * Determine if $__objects cache should be wrote
     *
     * @var boolean
     * @access private
     */
    private $__cache = false;
    /**
     * Holds and key => value array of objects type
     *
     * @var array
     * @access private
     */
    private $__objects = array();

    private static $instance = null;
    /**
     * Return a singleton instance of Configure.
     *
     * @return Configure instance
     * @access public
     */

    public static function getInstance() {
        if(self::$instance == null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Used to write a dynamic var in the Configure instance.
     *
     * Usage
     * Configure::write('One.key1', 'value of the Configure::One[key1]');
     * Configure::write(array('One.key1' => 'value of the Configure::One[key1]'));
     * Configure::write('One', array('key1'=>'value of the Configure::One[key1]', 'key2'=>'value of the Configure::One[key2]');
     * Configure::write(array('One.key1' => 'value of the Configure::One[key1]', 'One.key2' => 'value of the Configure::One[key2]'));
     *
     * @param array $config Name of var to write
     * @param mixed $value Value to set for var
     * @return void
     * @access public
     */
    public static function write($config, $value = null) {
        $_this = self::getInstance();

        if (!is_array($config)) {
            $config = array($config => $value);
        }

        foreach ($config as $names => $value) {
            $name = $_this->__configVarNames($names);

            switch (count($name)) {
            case 3:
                $_this->{$name[0]}[$name[1]][$name[2]] = $value;
                break;
            case 2:
                $_this->{$name[0]}[$name[1]] = $value;
                break;
            case 1:
                $_this->{$name[0]} = $value;
                break;
            }
        }

    }

    /**
     * Used to read Configure::$var
     *
     * Usage
     * Configure::read('Name'); will return all values for Name
     * Configure::read('Name.key'); will return only the value of Configure::Name[key]
     *
     * @param string $var Variable to obtain
     * @return string value of Configure::$var
     * @access public
     */
    public static function read($var) {
        $_this = self::getInstance();

        if ($var === 'all') {
            $return = array();
            foreach ($_this AS $key =>$var) {
                $return[$key] = $var;
            }
            return $return;
        }

        $name = $_this->__configVarNames($var);

        switch (count($name)) {
        case 3:
            if (isset($_this->{$name[0]}[$name[1]][$name[2]])) {
                return $_this->{$name[0]}[$name[1]][$name[2]];
            }
            break;
        case 2:
            if (isset($_this->{$name[0]}[$name[1]])) {
                return $_this->{$name[0]}[$name[1]];
            }
            break;
        case 1:
            if (isset($_this->{$name[0]})) {
                return $_this->{$name[0]};
            }
            break;
        }
        return null;
    }

    /** 
     * read Config::$var and apply sprintf on it
     * also checks if $var is changed by sprintf - if not, it writes a warning to error_log
     *
     * @param string $var Variable to obtain
     * @param string $value Value to use as sprintf parameter
     * @return string value of Config::$var, parsed by sprintf
     * @access public
     */
    public static function read_f($var, $value) {
        $text = self::read($var);

        $newtext = sprintf($text, $value);

        # check if sprintf changed something - if not, there are chances that $text didn't contain a %s
        if ($text == $newtext) error_log("$var used via read_f, but nothing replaced (value $value)");

        return $newtext;
    }

    /**
     * Used to read Config::$var, converted to boolean
     * (obviously only useful for settings that can be YES or NO)
     *
     * Usage
     * Configure::read('Name'); will return the value for Name, converted to boolean
     *
     * @param string $var Variable to obtain
     * @return bool value of Configure::$var (TRUE (on YES/yes) or FALSE (on NO/no/not set/unknown value)
     * @access public
     */

    public static function bool($var) {
        $value = self::read($var);

        if (strtoupper($value) == 'YES') { # YES
            return true;
        } else { # NO, unknown value
            # TODO: show/log error message on unknown value?
            return false;
        }
    }

    /**
     * Used to read Config::$var, converted to bool, returned as integer (0 or 1)
     * @see bool()
     */
    public static function intbool($var) {
        return Config::bool($var) ? 1 : 0;
    }



    function getAll() {
        $output = $this->config;
        return $output;
    }
    /**
     * Checks $name for dot notation to create dynamic Configure::$var as an array when needed.
     *
     * @param mixed $name Name to split
     * @return array Name separated in items through dot notation
     * @access private
     */
    private function __configVarNames($name) {
        if (is_string($name)) {
            if (strpos($name, ".")) {
                return explode(".", $name);
            }
            return array($name);
        }
        return $name;
    }

}

/* vim: set expandtab softtabstop=4 tabstop=4 shiftwidth=4: */
