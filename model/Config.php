<?php

# $Id$

# This class is too static - if you inherit a class from it, it will share the static $instance and all its contents
# Therefore the class is marked as final to prevent someone accidently does this ;-)
final class Config {
    private static $instance = null;

    /**
     * @var array
     */
    private $config = [];

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
     * @param string $key
     * @param mixed $value to set for key.
     * @return void
     */
    public static function write(string $key, $value = null) {
        $_this = self::getInstance();
        $newConfig = $_this->getAll();
        $newConfig[$key] = $value;
        $_this->setAll($newConfig);
    }

    /**
     * @param string $var
     * @return array
     */
    public static function read_array(string $var): array {
        $stuff = self::read($var);

        if (!is_array($stuff)) {
            trigger_error('In ' . __FUNCTION__ . ": expected config $var to be an array, but received a " . gettype($stuff), E_USER_ERROR);
        }

        return $stuff;
    }

    public static function has(string $var): bool {
        $x = self::getInstance()->getAll();
        return array_key_exists($var, $x);
    }
    /**
     * @param string $var
     * @return string
     */
    public static function read_string(string $var): string {
        $stuff = self::read($var);

        if ($stuff === null) {
            return '';
        }

        if (!is_string($stuff)) {
            trigger_error('In ' . __FUNCTION__ . ": expected config $var to be a string, but received a " . gettype($stuff), E_USER_ERROR);
            return '';
        }

        return $stuff;
    }

    /**
     * @param string $var Variable to obtain
     * @return callable|array|string|null|bool some value
     */
    public static function read(string $var) {
        $_this = self::getInstance();

        $config = $_this->getAll();

        if ($var === 'all') {
            return $config;
        }

        if (isset($config[$var])) {
            return $config[$var];
        }

        if (!in_array($var, self::$deprecated_options)) {
            error_log('Config::read(): attempt to read undefined config option "' . $var . '", returning null');
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
     */
    public static function read_f(string $var, string $value): string {
        $text = self::read_string($var);

        $newtext = sprintf($text, $value);

        # check if sprintf changed something - if not, there are chances that $text didn't contain a %s
        if ($text == $newtext) {
            error_log("$var used via read_f, but nothing replaced (value $value)");
        }

        return $newtext;
    }

    /**
     * Used to read Config::$var, converted to boolean
     * (obviously only useful for settings that can be YES or NO, or boolean like values)
     *
     * Usage
     * Configure::read('Name'); will return the value for Name, converted to boolean
     *
     * @param string $var Variable to obtain
     * @return bool value of Configure::$var (TRUE (on YES/yes) or FALSE (on NO/no/not set/unknown value)
     */
    public static function bool(string $var): bool {
        $value = self::read($var);

        if (is_bool($value)) {
            return $value;
        }

        if (!is_string($value)) {
            trigger_error('In ' . __FUNCTION__ . ": expected config $var to be a string, but received a " . gettype($value), E_USER_ERROR);
            error_log("config $var should be a string, found: " . json_encode($value));
            return false;
        }

        $value = strtoupper($value);
        if ($value == 'YES' || $value == 'TRUE') { # YES
            return true;
        } elseif ($value == 'NO' || $value == 'FALSE') { # NO
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
    public static function intbool($var): int {
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
    public static function lang(string $var): string {
        $languages = self::read_array('__LANG');

        $value = $languages[$var] ?? '';

        if (!is_string($value)) {
            trigger_error('In ' . __FUNCTION__ . ": expected config $var to be a string , but received a " . gettype($value), E_USER_ERROR);
        }

        return $value;
    }

    /**
     * Get translated text from $PALANG and apply sprintf on it
     * (wrapper for self::read_f(), see also the comments there)
     *
     * @param string $var Text (from $PALANG) to obtain
     * @param string $value Value to use as sprintf parameter
     * @return string value of $PALANG[$var], parsed by sprintf
     */
    public static function lang_f(string $var, $value): string {
        $all = self::read_array('__LANG');

        $text = $all[$var] ?? '';

        $newtext = sprintf($text, $value);

        # check if sprintf changed something - if not, there are chances that $text didn't contain a %s
        if ($text == $newtext) {
            error_log("$var used via read_f, but nothing replaced (value $value)");
        }

        return $newtext;
    }

    /**
     * @return array
     */
    public function getAll(): array {
        return $this->config;
    }

    /**
     * @param array $config
     */
    public function setAll(array $config) {
        $this->config = $config;
    }
}

/* vim: set expandtab softtabstop=4 tabstop=4 shiftwidth=4: */
