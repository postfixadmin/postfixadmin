<?php

class MailboxNavigationTemplateTest extends \PHPUnit\Framework\TestCase
{
    public function testMailboxNavigationUsesOneHeaderAndThePasswordProtectedExceptionPage(): void
    {
        global $CONF;

        if (!defined('YES')) {
            define('YES', 'YES');
        }
        $session = $_SESSION ?? [];
        $host = $_SERVER['HTTP_HOST'] ?? null;
        $_SERVER['HTTP_HOST'] = 'localhost';
        $_SESSION = ['sessid' => ['username' => 'user@example.com']];
        try {
            foreach (['en', 'es'] as $language) {
                $PALANG = [];
                require __DIR__ . '/../languages/' . $language . '.lang';
                $_SESSION['lang'] = $language;
                foreach (['YES', 'NO'] as $totp) {
                    $smarty = new \Smarty\Smarty();
                    $smarty->setCompileDir(sys_get_temp_dir());
                    $smarty->setTemplateDir(__DIR__ . '/../templates');
                    $smarty->setConfigDir(__DIR__ . '/../configs');
                    $smarty->assign([
                        'CONF' => [
                            'theme_logo' => '../images/corporate-logo.png', 'theme_favicon' => '',
                            'theme_css' => '../css/bootstrap.css', 'theme_custom_css' => '',
                            'vacation' => 'YES', 'edit_alias' => 'YES', 'totp' => $totp,
                            'app_passwords' => 'NO', 'motd_user' => '', 'show_footer_text' => 'NO',
                        ],
                        'PALANG' => $PALANG, 'rel_path' => '../', 'version' => 'test',
                        'authentication_has_role' => ['user' => true, 'admin' => false, 'global_admin' => false],
                        'user_can_edit_mailbox_name' => false, 'tummVacationtext' => 'Vacation',
                        'smarty_template' => 'users_main',
                    ]);
                    $html = $smarty->fetch('index.tpl');
                    $document = new DOMDocument();
                    @$document->loadHTML($html);
                    $xpath = new DOMXPath($document);
                    self::assertSame(1, $xpath->query('//nav')->length);
                    self::assertSame(1, $xpath->query('//*[@id="login_header_logo"]')->length);
                    self::assertSame('../images/corporate-logo.png', $xpath->query('//*[@id="login_header_logo"]')->item(0)->getAttribute('src'));
                    self::assertSame(1, $xpath->query('//*[@id="darkModeSwitch"]')->length);
                    self::assertSame(1, $xpath->query('//*[@id="navbarSupportedContent"]')->length);
                    self::assertSame(1, $xpath->query('//button[@data-bs-target="#navbarSupportedContent"]')->length);
                    self::assertSame(0, $xpath->query('//nav[contains(@class, "fixed-top")]')->length);
                    self::assertSame($totp === 'YES' ? 2 : 0, $xpath->query('//a[@href="totp-exceptions.php"]')->length);
                    self::assertSame(0, $xpath->query('//a[contains(@href, "table=totpexception")]')->length);
                }
            }
        } finally {
            $_SESSION = $session;
            if ($host === null) {
                unset($_SERVER['HTTP_HOST']);
            } else {
                $_SERVER['HTTP_HOST'] = $host;
            }
        }
    }
}
