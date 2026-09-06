<?php

class VirtualSearchTemplateTest extends \PHPUnit\Framework\TestCase
{
    public function testSearchHintIsTranslatedAndKeepsTheExistingRequestContract(): void
    {
        global $CONF;

        $smarty = new \Smarty\Smarty();
        $smarty->setCompileDir(sys_get_temp_dir());
        $smarty->setTemplateDir(__DIR__ . '/../templates');
        foreach (['en', 'es'] as $language) {
            $PALANG = [];
            require __DIR__ . '/../languages/' . $language . '.lang';
            $smarty->assign('PALANG', $PALANG);
            $html = $smarty->fetch('virtual-search.tpl');
            self::assertStringContainsString('method="post" action="list-virtual.php"', $html);
            self::assertStringContainsString('name="search[_]"', $html);
            self::assertStringContainsString('placeholder="' . $PALANG['search_mailboxes_aliases'] . '"', $html);
            self::assertStringContainsString('aria-label="' . $PALANG['search_mailboxes_aliases'] . '"', $html);
        }
        self::assertStringContainsString("{include file='virtual-search.tpl'}", file_get_contents(__DIR__ . '/../templates/list-virtual.tpl'));
    }
}
