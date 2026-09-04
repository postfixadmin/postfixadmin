<?php

class DomainDnsListTemplateTest extends \PHPUnit\Framework\TestCase
{
    public function testDomainListHasConditionalDnsAlertFilterAndRefreshAction(): void
    {
        $template = file_get_contents(__DIR__ . '/../templates/list.tpl');
        self::assertIsString($template);
        self::assertStringContainsString('{if $dns_inactive_count > 0}', $template);
        self::assertStringContainsString('$dns_check_mode > 0', $template);
        self::assertStringContainsString('aria-label="Inactive DNS"', $template);
        self::assertStringContainsString('DNS ({$dns_inactive_count})', $template);
        self::assertStringContainsString('dns_filter=inactive', $template);
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
    }
}
