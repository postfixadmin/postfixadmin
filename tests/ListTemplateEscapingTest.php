<?php

/**
 * templates/list.tpl relies on Smarty auto-escaping (see PFASmarty) - check hostile values are
 * escaped in text, link, attribute and JavaScript (onclick) contexts.
 */
class ListTemplateEscapingTest extends \PHPUnit\Framework\TestCase
{
    private const HOSTILE = "a'b\"c<i>&d@example.com";

    public function testHostileValuesAreEscapedInEveryContext(): void
    {
        $html = $this->renderList([
            self::HOSTILE => [
                'address' => self::HOSTILE,
                'goto' => '<b>bold</b> & co',
                'status' => '<span class="trusted-status">ok</span>',
                '_can_edit' => true,
                '_can_delete' => true,
            ],
        ]);

        // text + link context
        $this->assertStringContainsString(
            '<a href="edit.php?table=alias&amp;edit=' . rawurlencode(self::HOSTILE) . '">a&#039;b&quot;c&lt;i&gt;&amp;d@example.com</a>',
            $html
        );
        $this->assertStringContainsString('&lt;b&gt;bold&lt;/b&gt; &amp; co', $html);
        $this->assertStringNotContainsString('<i>', $html);
        $this->assertStringNotContainsString('<b>', $html);

        // html-type fields are trusted handler output and must not be escaped
        $this->assertStringContainsString('<span class="trusted-status">ok</span>', $html);

        // edit link and hidden delete field
        $this->assertStringContainsString('&amp;edit=' . rawurlencode(self::HOSTILE) . '"', $html);
        $this->assertStringContainsString('name="delete" value="a&#039;b&quot;c&lt;i&gt;&amp;d@example.com"', $html);

        // onclick: JavaScript-escaped, then HTML-escaped, so no raw quote can end the attribute
        $this->assertMatchesRegularExpression('/onclick="return confirm\(\'Delete \\\\&#039;a\\\\&#039;b\\\\&quot;c&lt;i&gt;&amp;d@example.com\\\\&#039;\?\'\)"/', $html);
    }

    public function testListTemplateCanBeIncludedTwice(): void
    {
        $smarty = $this->prepare(['x@example.com' => ['address' => 'x@example.com', 'goto' => 'y', 'status' => '', '_can_edit' => false, '_can_delete' => false]]);
        ob_start();
        try {
            $smarty->display('string:{include "list.tpl"}{include "list.tpl"}');
        } finally {
            $html = (string)ob_get_clean();
            $this->restore();
        }
        $this->assertSame(2, substr_count($html, '<a href="edit.php?table=alias&amp;edit=x%40example.com">x@example.com</a>'));
    }

    /** @var array<string, mixed> */
    private array $saved = [];

    private function prepare(array $items): PFASmarty
    {
        $this->saved = ['session' => $_SESSION ?? [], 'lang' => Config::read_array('__LANG')];
        $_SESSION = ['sessid' => ['roles' => ['global-admin'], 'username' => 'admin']];
        Config::write('__LANG', ['download_csv' => 'Download CSV', 'confirm_delete_alias' => "Delete '%s'?", 'edit' => 'Edit', 'del' => 'Delete',
            'alias' => 'Alias', 'to' => 'To', 'status' => 'Status']);

        $smarty = PFASmarty::getInstance();
        $smarty->assign('id_div', 'main_div');
        $smarty->assign('admin_list', []);
        $smarty->assign('msg', ['show_simple_search' => false, 'list_header' => '', 'can_create' => false, 'confirm_delete' => 'confirm_delete_alias']);
        $smarty->assign('table', 'alias');
        $smarty->assign('struct', [
            'address' => PFAHandler::pacol(0, 0, 1, 'mail', 'alias', '', '', [], 0, 0, '', '', 'edit.php?table=alias&edit=%s'),
            'goto' => PFAHandler::pacol(0, 0, 1, 'text', 'to', ''),
            'status' => PFAHandler::pacol(0, 0, 1, 'html', 'status', ''),
        ]);
        $smarty->assign('items', $items);
        $smarty->assign('id_field', 'address');
        $smarty->assign('formconf', ['create_button' => '']);
        $smarty->assign('search', []);
        $smarty->assign('searchmode', []);
        $smarty->assign('pagination', []);
        $smarty->assign('pagination_label', '');
        $smarty->assign('domain_selected', '');
        $smarty->assign('dns_check_mode', 0);
        return $smarty;
    }

    private function restore(): void
    {
        $_SESSION = $this->saved['session'];
        Config::write('__LANG', $this->saved['lang']);
    }

    private function renderList(array $items): string
    {
        $smarty = $this->prepare($items);
        ob_start();
        try {
            $smarty->display('list.tpl');
        } finally {
            $html = (string)ob_get_clean();
            $this->restore();
        }
        return $html;
    }
}
