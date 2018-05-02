<?php
# $Id$

# This class is too static - if you inherit a class from it, it will share the static $instance and all its contents
# Therefore the class is marked as final to prevent someone accidently does this ;-)
final class Config {
    private static $instance = null;

    /**
     * @var array
     */
    private $config;

    # do not error_log() 'undefined config option' for deprecated options
    private static $deprecated_options = array(
        'min_password_length',
    );

    /**
     * Return a singleton instance of Config
     * @return Config
     */

    public static function getInstance() {
        if (self::$instance == null) {
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
     * @param mixed $config string or array of var to write
     * @param mixed $value to set for key.
     * @return void
     */
    public static function write($config, $value = null) {
        $_this = self::getInstance();

        if (!is_array($config)) {
            $config = array($config => $value);
        }

        $newConfig = $_this->getAll();

        foreach ($config as $names => $value) {
            $name = $_this->__configVarNames($names);

            switch (count($name)) {
            case 3:
                $newConfig[$name[0]][$name[1]][$name[2]] = $value;
                break;
            case 2:
                $newConfig[$name[0]][$name[1]] = $value;
                break;
            case 1:
                $newConfig[$name[0]] = $value;
                break;
            }
        }
        $_this->setAll($newConfig);
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

        $config = $_this->getAll();

        if ($var === 'all') {
            return $config;
        }

        $name = $_this->__configVarNames($var);

        switch (count($name)) {
        case 3:
            $zero = $name[0];
            $one = $name[1];
            $two = $name[2];
            if (isset($config[$zero], $config[$zero][$one], $config[$zero][$one][$two])) {
                return $config[$zero][$one][$two];
            }
            break;
        case 2:
            $zero = $name[0];
            $one = $name[1];
            if (isset($config[$zero], $config[$zero][$one])) {
                return $config[$zero][$one];
            }
            break;
        case 1:
            $zero = $name[0];
            if (isset($config[$zero])) {
                return $config[$zero];
            }
            break;
        }

        if (!in_array(join('.', $name), self::$deprecated_options)) {
            error_log('Config::read(): attempt to read undefined config option "' . join('.', $name) . '", returning null');
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
        if ($text == $newtext) {
            if (is_array($var)) {
                $var = join('.', $var);
            }
            error_log("$var used via read_f, but nothing replaced (value $value)");
        }

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
        } elseif (strtoupper($value) == 'NO') { # NO
            return false;
        } else { # unknown value
            # show and log error message on unknown value
            $msg = "\$CONF['$var'] has an invalid value, should be 'YES' or 'NO'";
            flash_error($msg);
            error_log("$msg (value: $value)");
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



    /**
         * Get translated text from $PALANG
         * (wrapper for self::read(), see also the comments there)
         *
         * @param string $var Variable to obtain
         * @return string value of $PALANG[$var]
         * @access public
         */
    public static function lang($var) {
        return self::read(array('__LANG', $var));
    }

    /**
     * Get translated text from $PALANG and apply sprintf on it
     * (wrapper for self::read_f(), see also the comments there)
     *
     * @param string $var Text (from $PALANG) to obtain
     * @param string $value Value to use as sprintf parameter
     * @return string value of $PALANG[$var], parsed by sprintf
     * @access public
     */
    public static function lang_f($var, $value) {
        return self::read_f(array('__LANG', $var), $value);
    }

    /**
     * @return array
     */
    public function getAll() {
        $output = $this->config;
        return $output;
    }

    /**
     * @param array $config
     */
    public function setAll(array $config) {
        $this->config = $config;
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
