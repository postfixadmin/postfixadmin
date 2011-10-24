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
