<?php

class Crypt {
    /**
     * @access private
     */
    protected $plain = '';

    /**
     * @access private
     */
    protected $password;

    protected $size;


    public function __construct($plaintext) {
        $this->plain = $plaintext;
    }

    /**
     * @return bool
     */
    public function crypt($algorithm) {
        return true;
    }

    public function get() {
        return $this->password;
    }
}
