<?php

use PHPUnit\Framework\TestCase;

class EditFormNavigationTest extends TestCase
{
    public function testCancelRendersAsNavigationToHandlerList(): void
    {
        $smarty = new \Smarty\Smarty();
        $smarty->setTemplateDir(__DIR__ . '/../templates');
        $smarty->setCompileDir(sys_get_temp_dir());
        $smarty->registerPlugin('function', 'CSRF_Token', static fn (): string => '');
        $smarty->assign([
            'table' => 'mailbox', 'formtitle' => 'Add mailbox', 'submitbutton' => 'Add mailbox',
            'struct' => [], 'fielderror' => [], 'PALANG' => ['cancel' => 'Cancel'],
        ]);
        $destination = 'list-virtual.php';
        $smarty->assign('cancel_url', $destination);
        $html = $smarty->fetch('editform.tpl');
        self::assertStringContainsString('href="' . $destination . '">Cancel</a>', $html);
        self::assertStringNotContainsString('name="return_to"', $html);
        self::assertStringContainsString('type="submit" name="submit"', $html);
    }

    public function testMailComposersCanBeAbandonedWithoutSubmitting(): void
    {
        foreach (['sendmail.tpl', 'broadcast-message.tpl'] as $template) {
            $smarty = new \Smarty\Smarty();
            $smarty->setTemplateDir(__DIR__ . '/../templates');
            $smarty->setCompileDir(sys_get_temp_dir());
            $smarty->registerPlugin('function', 'CSRF_Token', static fn (): string => '');
            $source = file_get_contents(__DIR__ . '/../templates/' . $template);
            preg_match_all('/PALANG[.]([a-zA-Z_]+)/', $source, $keys);
            $language = array_fill_keys($keys[1], 'Label');
            $language['cancel'] = 'Cancel';
            $smarty->assign([
                'PALANG' => $language,
                'smtp_from_email' => 'admin@example.org', 'allowed_domains' => ['example.org'],
                'CONF' => ['welcome_text' => 'Welcome'],
            ]);
            $html = $smarty->fetch($template);
            self::assertStringContainsString('href="main.php">Cancel</a>', $html);
            self::assertStringNotContainsString('name="return_to"', $html);
        }
    }
}
