<?php

class ListTemplatePaginationTest extends \PHPUnit\Framework\TestCase
{
    public function testRendersPaginationAboveAndBelowGenericList(): void
    {
        $html = $this->renderList([
            [
                'label' => 'aa-cz',
                'url' => '?table=domain&amp;limit=0#main_div',
                'active' => true,
            ],
            [
                'label' => 'de-zz',
                'url' => '?table=domain&amp;limit=10#main_div',
            ],
        ]);

        $this->assertSame(2, substr_count($html, '<nav aria-label="Domains">'));
        $this->assertSame(2, substr_count($html, 'aria-current="page">aa-cz'));
        $this->assertSame(2, substr_count($html, 'href="?table=domain&amp;limit=10#main_div"'));
    }

    public function testOmitsPaginationForSinglePageList(): void
    {
        $html = $this->renderList([]);

        $this->assertStringNotContainsString('<nav ', $html);
    }

    /**
     * @param array<int, array<string, bool|string>> $pagination
     */
    private function renderList(array $pagination): string
    {
        $original_session = $_SESSION;
        $original_language = Config::read_array('__LANG');

        try {
            $_SESSION = [
                'sessid' => [
                    'roles' => ['global-admin'],
                    'username' => 'admin',
                ],
            ];
            Config::write('__LANG', ['download_csv' => 'Download CSV']);

            $smarty = PFASmarty::getInstance();
            $smarty->assign('id_div', 'main_div');
            $smarty->assign('admin_list', []);
            $smarty->assign('msg', [
                'show_simple_search' => false,
                'list_header' => '',
                'can_create' => false,
            ]);
            $smarty->assign('table', 'domain');
            $smarty->assign('struct', []);
            $smarty->assign('items', []);
            $smarty->assign('id_field', 'domain');
            $smarty->assign('formconf', ['create_button' => '']);
            $smarty->assign('search', []);
            $smarty->assign('searchmode', []);
            $smarty->assign('pagination', $pagination);
            $smarty->assign('pagination_label', 'Domains');
            $smarty->assign('domain_selected', '');

            ob_start();
            $smarty->display('list.tpl');
            return (string)ob_get_clean();
        } finally {
            $_SESSION = $original_session;
            Config::write('__LANG', $original_language);
        }
    }
}
