<?php

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../model/EditFormNavigation.php';

class EditFormNavigationTest extends TestCase
{
    public function testPreservesOriginAndListingState(): void
    {
        foreach (['main.php', 'list.php?table=domain&limit=20',
            'list-virtual.php?domain=example.org&limit=10&tab=alias&search[alias]=test'] as $origin) {
            self::assertSame($origin, EditFormNavigation::cancelUrl('/pfa/' . $origin, '/pfa/edit.php', 'fallback'));
            self::assertSame($origin, EditFormNavigation::cancelUrl($origin, '/edit.php', 'fallback'));
        }
    }

    public function testRejectsExternalOrNonOverviewDestinations(): void
    {
        foreach (['', 'https://example.org/list.php', '//example.org/list.php',
            '/other/list.php', '../list.php', '/pfa/../list.php', 'edit.php?table=mailbox',
            'delete.php?table=domain', 'javascript:alert(1)', 'list.php#fragment',
            "list.php\r\nLocation: https://example.org", 'list.php\\evil'] as $origin) {
            self::assertSame('list.php?table=domain', EditFormNavigation::cancelUrl(
                $origin,
                '/pfa/edit.php',
                'list.php?table=domain'
            ));
        }
    }

    public function testCancelRendersAsNavigationWithPersistentDestination(): void
    {
        $smarty = new \Smarty\Smarty();
        $smarty->setTemplateDir(__DIR__ . '/../templates');
        $smarty->setCompileDir(sys_get_temp_dir());
        $smarty->registerPlugin('function', 'CSRF_Token', static fn (): string => '');
        $smarty->assign([
            'table' => 'mailbox', 'formtitle' => 'Add mailbox', 'submitbutton' => 'Add mailbox',
            'struct' => [], 'fielderror' => [], 'PALANG' => ['cancel' => 'Cancel'],
        ]);
        $destination = 'list-virtual.php?domain=example.org&amp;limit=10';
        $smarty->assign('cancel_url', $destination);
        $html = $smarty->fetch('editform.tpl');
        self::assertStringContainsString('href="' . $destination . '">Cancel</a>', $html);
        self::assertStringContainsString('name="return_to" value="' . $destination . '"', $html);
        self::assertStringContainsString('type="submit" name="submit"', $html);
        $smarty->clearAssign('cancel_url');
        self::assertStringNotContainsString('>Cancel</a>', $smarty->fetch('editform.tpl'));
    }
}
