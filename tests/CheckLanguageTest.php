<?php

use model\Languages;

class CheckLanguageTest extends \PHPUnit\Framework\TestCase
{
    public function testBasic()
    {
        $supported_languages = Languages::$SUPPORTED_LANGUAGES;

        $this->assertNotEmpty($supported_languages);

        $config = Config::getInstance();
        Config::write('default_language', 'test');

        unset($_SERVER['HTTP_ACCEPT_LANGUAGE']);

        $lang = Languages::check_language(false);

        $this->assertEquals('test', $lang);

        $_SERVER['HTTP_ACCEPT_LANGUAGE'] = 'en';

        $lang = Languages::check_language(false);
        $this->assertEquals('en', $lang);
    }

    public function testCookie()
    {
        $supported_languages = Languages::$SUPPORTED_LANGUAGES;

        $this->assertNotEmpty($supported_languages);


        $config = Config::getInstance();
        Config::write('default_language', 'test');

        $_SERVER['HTTP_ACCEPT_LANGUAGE'] = 'foo';

        $_COOKIE['lang'] = 'en';

        $lang = Languages::check_language(false);

        $this->assertEquals('en', $lang);
    }

    public function testPost()
    {
        $supported_languages = Languages::$SUPPORTED_LANGUAGES;

        $this->assertNotEmpty($supported_languages);


        $config = Config::getInstance();
        Config::write('default_language', 'test');

        $_SERVER['HTTP_ACCEPT_LANGUAGE'] = 'foo';

        $_POST['lang'] = 'en';

        $lang = Languages::check_language(true);

        $this->assertEquals('en', $lang);
    }
}

/* vim: set expandtab softtabstop=4 tabstop=4 shiftwidth=4: */
