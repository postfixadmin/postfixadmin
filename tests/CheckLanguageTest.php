<?php

class CheckLanguageTest extends \PHPUnit\Framework\TestCase {
    public function testBasic() {
        global $supported_languages;

        $this->assertNotEmpty($supported_languages);

        $config = Config::getInstance();
        Config::write('default_language', 'test');

        unset($_SERVER['HTTP_ACCEPT_LANGUAGE']);

        $lang = check_language(false);

        $this->assertEquals('test', $lang);

        $_SERVER['HTTP_ACCEPT_LANGUAGE'] = 'en';

        $lang = check_language(false);
        $this->assertEquals('en', $lang);
    }

    public function testCookie() {
        global $supported_languages;

        $this->assertNotEmpty($supported_languages);


        $config = Config::getInstance();
        Config::write('default_language', 'test');

        $_SERVER['HTTP_ACCEPT_LANGUAGE'] = 'foo';

        $_COOKIE['lang'] = 'en';

        $lang = check_language(false);

        $this->assertEquals('en', $lang);
    }

    public function testPost() {
        global $supported_languages;

        $this->assertNotEmpty($supported_languages);


        $config = Config::getInstance();
        Config::write('default_language', 'test');

        $_SERVER['HTTP_ACCEPT_LANGUAGE'] = 'foo';

        $_POST['lang'] = 'en';

        $lang = check_language(true);

        $this->assertEquals('en', $lang);
    }
}

/* vim: set expandtab softtabstop=4 tabstop=4 shiftwidth=4: */
