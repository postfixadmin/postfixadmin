<?php

class ValidatePasswordTest extends \PHPUnit\Framework\TestCase
{
    public function testBasic()
    {
        $config = Config::getInstance();

        // Set to the defaults, just to make sure.
        Config::write('password_validation', array(
        #    '/regular expression/' => '$PALANG key (optional: + parameter)',
            '/.{5}/' => 'password_too_short 5',      # minimum length 5 characters
            '/([a-zA-Z].*){3}/' => 'password_no_characters 3',  # must contain at least 3 characters
            '/([0-9].*){2}/' => 'password_no_digits 2',      # must contain at least 2 digits
        #    '/([!\".,*&^%$£)(_+=\-`\'#@~\[\]\\<>\/].*){1}/' => 'password_no_special 1', # must contain at least 1 special character
        ));

        $this->assertEmpty(validate_password('fishSheep01'));
        $this->assertEmpty(validate_password('Password01'));
        $this->assertNotEmpty(validate_password('pas')); // notEmpty == fail
        $this->assertNotEmpty(validate_password('pa1'));
    }

    public function testSpecial()
    {
        $config = Config::getInstance();

        // Set to the defaults, just to make sure.
        Config::write('password_validation', array(
            '/([!\".,*&^%$£)(_+=\-`\'#@~\[\]\\<>\/].*){1,}/' => 'password_no_special 1', # must contain at least 1 special character
        ));

        $this->assertEmpty(validate_password('fish^Sh$$p01'));
        $this->assertEmpty(validate_password(']/>'));
        $this->assertEmpty(validate_password("P'55w\\ord"));
        $this->assertEmpty(validate_password("P'55word"), "should contain 1 special char");
        $this->assertNotEmpty(validate_password("fishSheep01"), "does not contain any special chars...");
    }
}
