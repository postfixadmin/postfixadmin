<?php

class VirtualSearchTemplateTest extends \PHPUnit\Framework\TestCase
{
    public function testSearchHintIsTranslatedAndKeepsTheExistingRequestContract(): void
    {
        global $CONF;

        $smarty = new \Smarty\Smarty();

        $smarty->setEscapeHtml(true); // match PFASmarty
        $smarty->setCompileDir(sys_get_temp_dir());
        $smarty->setTemplateDir(__DIR__ . '/../templates');
        foreach (['en', 'es'] as $language) {
            $PALANG = [];
            require __DIR__ . '/../languages/' . $language . '.lang';
            $smarty->assign('PALANG', $PALANG);
            $html = $smarty->fetch('virtual-search.tpl');
            self::assertStringContainsString('method="get" action="list-virtual.php"', $html);
            self::assertStringContainsString('name="search[_]"', $html);
            self::assertStringContainsString('<button class="btn btn-secondary" type="submit" title="' . $PALANG['pMain_search_button'] . '"', $html);
            self::assertStringContainsString('class="bi bi-search"', $html);
            self::assertStringContainsString('placeholder="' . $PALANG['search_mailboxes_aliases'] . '"', $html);
            self::assertStringContainsString('aria-label="' . $PALANG['search_mailboxes_aliases'] . '"', $html);
        }
        self::assertStringContainsString("{include file='virtual-search.tpl'}", file_get_contents(__DIR__ . '/../templates/list-virtual.tpl'));
    }

    public function testSearchTermIsShownEscapedInTheSearchBox(): void
    {
        $smarty = new \Smarty\Smarty();
        $smarty->setEscapeHtml(true); // match PFASmarty
        $smarty->setCompileDir(sys_get_temp_dir());
        $smarty->setTemplateDir(__DIR__ . '/../templates');
        $smarty->assign('PALANG', ['search_mailboxes_aliases' => 'Search']);

        $smarty->assign('search', ['_' => 'R&D "x" <b>']);
        self::assertStringContainsString('value="R&amp;D &quot;x&quot; &lt;b&gt;"', $smarty->fetch('virtual-search.tpl'));

        $smarty->assign('search', []);
        self::assertStringContainsString('value=""', $smarty->fetch('virtual-search.tpl'));

        $smarty->clearAssign('search');
        self::assertStringContainsString('value=""', $smarty->fetch('virtual-search.tpl'));
    }
}
