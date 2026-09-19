<?php

class GetSiteUrlTest extends \PHPUnit\Framework\TestCase
{
    public function testControlViaConfig()
    {
        $server = [];
        $orig = Config::getInstance()->getAll();
        $orig['site_url'] = 'https://example.com/postfixadmin-1.2.3.4/';
        Config::getInstance()->setAll($orig);

        $this->assertEquals('https://example.com/postfixadmin-1.2.3.4/', getSiteUrl($server));
    }

    public function testViaDiscovery()
    {
        $server = [
            'HTTP_HOST' => 'example.org',
            'REQUEST_SCHEME' => 'https',
            'REQUEST_URI' => '/postfixadmin-1.2.3.4/setup.php',
        ];

        $orig = Config::getInstance()->getAll();
        unset($orig['site_url']);
        Config::getInstance()->setAll($orig);

        $this->assertEquals('https://example.org/postfixadmin-1.2.3.4/', getSiteUrl($server));
    }

    public function testViaDiscoveryNoPrefix()
    {
        $server = [
            'HTTP_HOST' => 'example.org',
            'REQUEST_SCHEME' => 'https',
            'REQUEST_URI' => '/setup.php',
        ];

        $orig = Config::getInstance()->getAll();
        unset($orig['site_url']);
        Config::getInstance()->setAll($orig);

        $this->assertEquals('https://example.org/', getSiteUrl($server));
    }

    public function testViaDiscoveryhttp()
    {
        $server = [
            'HTTP_HOST' => 'example.org',
            'REQUEST_SCHEME' => 'http',
            'REQUEST_URI' => '/setup.php',
        ];

        $orig = Config::getInstance()->getAll();
        unset($orig['site_url']);
        Config::getInstance()->setAll($orig);

        $this->assertEquals('http://example.org/', getSiteUrl($server));
    }

    public function testPasswordRecoveryRequiresCanonicalUrl(): void
    {
        $config = Config::getInstance()->getAll();
        $config['site_url'] = null;
        Config::getInstance()->setAll($config);

        $this->expectException(RuntimeException::class);
        getPasswordRecoverySiteUrl();
    }

    public function testPasswordRecoveryUsesConfiguredUrlOnly(): void
    {
        $config = Config::getInstance()->getAll();
        $config['site_url'] = 'https://example.com/postfixadmin';
        Config::getInstance()->setAll($config);

        $this->assertSame('https://example.com/postfixadmin/', getPasswordRecoverySiteUrl());
    }

    public function testPasswordRecoveryRejectsUrlCredentials(): void
    {
        $config = Config::getInstance()->getAll();
        $config['site_url'] = 'https://user:password@example.com/postfixadmin/';
        Config::getInstance()->setAll($config);

        $this->expectException(RuntimeException::class);
        getPasswordRecoverySiteUrl();
    }
}
