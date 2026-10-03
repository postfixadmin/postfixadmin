<?php

class DomainDnsListTemplateTest extends \PHPUnit\Framework\TestCase
{
    public function testIndividualRefreshHeaderRendersSavedAndUncheckedStatus(): void
    {
        $source = file_get_contents(__DIR__ . '/../templates/list-virtual.tpl');
        $header = substr($source, 0, strpos($source, '<div class="card-body">')) . '</div>';
        $smarty = new \Smarty\Smarty();
        $smarty->setCompileDir(sys_get_temp_dir());
        $smarty->setTemplateDir(__DIR__ . '/../templates');
        $smarty->setConfigDir(__DIR__ . '/../configs');
        $smarty->configLoad('menu.conf');
        $smarty->registerPlugin('function', 'CSRF_Token', static fn () => '<input name="CSRF_Token" value="test-token">');
        $smarty->assign([
            'dns_check_mode' => 1,
            'domain_list' => ['example.com'],
            'domain_selected' => 'example.com',
            'PALANG' => ['go' => 'Go', 'dns_refresh' => 'Refresh DNS status', 'dns_active' => 'Active DNS',
                'dns_inactive' => 'Inactive DNS', 'dns_last_check' => 'Last checked', 'dns_not_checked' => 'Not checked',
                'search_mailboxes_aliases' => 'Search mailboxes and aliases...'],
            'domain_dns_status' => ['dns_active' => 0, 'dns_checked' => '2000-01-01 00:00:00'],
        ]);
        $html = $smarty->fetch('string:' . $header);
        self::assertStringContainsString('name="domain" value="example.com"', $html);
        self::assertStringContainsString('name="CSRF_Token"', $html);
        self::assertStringContainsString('bi-exclamation-triangle', $html);
        self::assertStringContainsString('Inactive DNS', $html);
        self::assertStringContainsString('Last checked: 2000-01-01 00:00:00', $html);
        $smarty->assign('domain_dns_status', ['dns_active' => 1, 'dns_checked' => '2000-01-01 00:00:00']);
        $html = $smarty->fetch('string:' . $header);
        self::assertStringNotContainsString('Active DNS', $html);
        self::assertStringNotContainsString('bi-check-circle', $html);
        $smarty->assign('domain_dns_status', ['dns_active' => null, 'dns_checked' => null]);
        $html = $smarty->fetch('string:' . $header);
        self::assertStringContainsString('Last checked: Not checked', $html);
        self::assertStringNotContainsString('Inactive DNS', $html);
        self::assertStringNotContainsString('Active DNS', $html);
        $smarty->assign('dns_check_mode', 0);
        self::assertStringNotContainsString('refresh-domain-dns.php', $smarty->fetch('string:' . $header));
    }

    public function testDomainListHasConditionalDnsAlertFilterAndRefreshAction(): void
    {
        $template = file_get_contents(__DIR__ . '/../templates/list.tpl');
        self::assertIsString($template);
        self::assertStringContainsString('{if $dns_inactive_count > 0}', $template);
        self::assertStringContainsString('$dns_check_mode > 0', $template);
        self::assertStringContainsString('aria-label="Inactive DNS"', $template);
        self::assertStringContainsString('DNS ({$dns_inactive_count})', $template);
        self::assertStringContainsString('search%5Bdns_active%5D=0', $template);
        self::assertStringContainsString('action="refresh-domain-dns.php"', $template);
        self::assertStringContainsString('{CSRF_Token}', $template);
        self::assertLessThan(
            strpos($template, 'DNS ({$dns_inactive_count})'),
            strpos($template, 'action="refresh-domain-dns.php"')
        );
        self::assertLessThan(
            strpos($template, '{if $msg.show_simple_search}'),
            strpos($template, 'action="refresh-domain-dns.php"')
        );

        $endpoint = file_get_contents(__DIR__ . '/../public/refresh-domain-dns.php');
        self::assertIsString($endpoint);
        self::assertStringContainsString("authentication_require_role('admin')", $endpoint);
        self::assertStringContainsString('CsrfToken::assertValid', $endpoint);
        self::assertStringNotContainsString('dns_filter', $endpoint);
    }
}
