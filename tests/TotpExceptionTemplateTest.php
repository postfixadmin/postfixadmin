<?php

class TotpExceptionTemplateTest extends \PHPUnit\Framework\TestCase
{
    public function testTargetFieldIsOnlyRenderedForAdministrators(): void
    {
        global $CONF;
        $smarty = new \Smarty\Smarty();
        $smarty->setCompileDir(sys_get_temp_dir());
        $smarty->setTemplateDir(__DIR__ . '/../templates');
        $smarty->registerPlugin('function', 'CSRF_Token', static fn (): string => '');
        $smarty->assign('pExceptions', []);
        foreach (['en', 'es'] as $language) {
            $PALANG = [];
            require __DIR__ . '/../languages/' . $language . '.lang';
            $smarty->assign('PALANG', $PALANG);
            foreach (['user', 'admin', 'global_admin'] as $role) {
                $smarty->assign('authentication_has_role', [
                    'user' => $role === 'user', 'admin' => $role === 'admin',
                    'global_admin' => $role === 'global_admin',
                ]);
                $html = $smarty->fetch('totp-exceptions.tpl');
                if ($role === 'user') {
                    $this->assertStringNotContainsString('name="fUser"', $html);
                    $this->assertStringNotContainsString('for="fUser"', $html);
                } else {
                    $this->assertStringContainsString('name="fUser"', $html);
                    $this->assertStringContainsString('for="fUser"', $html);
                }
                $this->assertStringContainsString('name="fPassword_current"', $html);
                $this->assertStringContainsString('name="fIp"', $html);
            }
        }
    }
}
