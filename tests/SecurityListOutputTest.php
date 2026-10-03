<?php

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class SecurityListOutputTest extends TestCase
{
    public static function lists(): array
    {
        return [
            'application passwords' => ['app-passwords', 'pPasswords', 'passwords'],
            'TOTP exceptions' => ['totp-exceptions', 'pExceptions', 'exceptions'],
        ];
    }

    #[DataProvider('lists')]
    public function testStoredListValuesAreEscapedWithoutChangingRevokeControls(string $page, string $assigned, string $input): void
    {
        $engine = new \Smarty\Smarty();
        $engine->registerPlugin('function', 'CSRF_Token', static fn () => '<input name="CSRF_Token" value="fixture-token">');
        $engine->assign('PALANG', ['pTotp_exceptions_revoke' => 'Revoke']);
        $reflection = new ReflectionClass(PFASmarty::class);
        $smarty = $reflection->newInstanceWithoutConstructor();
        $reflection->getProperty('template')->setValue($smarty, $engine);

        $descriptions = [
            '<img data-security-fixture="injected" src=x onerror="alert(1)">',
            "R&D \"quoted\" 'text' <tag> UTF-8: \u{00F1}",
            'Legacy &lt;tag&gt; &amp; text',
        ];
        $rows = [];
        foreach ($descriptions as $index => $description) {
            $rows[] = [
                'id' => $index + 1,
                'username' => 'fixture@example.invalid',
                'ip' => '192.0.2.1',
                'description' => $description,
                'edit' => $index === 0 ? 1 : 0,
            ];
        }
        $$input = $rows;
        $controller = file_get_contents(__DIR__ . '/../public/users/' . $page . '.php');
        $pattern = '/\$smarty->assign\(\x27' . preg_quote($assigned, '/') . '\x27, \$' . preg_quote($input, '/') . '(?:, false)?\);/';
        $this->assertSame(1, preg_match($pattern, $controller, $assignment));
        // Execute the real controller assignment so disabling sanitization fails this regression.
        eval($assignment[0]);

        $template = file_get_contents(__DIR__ . '/../templates/' . $page . '.tpl');
        $this->assertSame(1, preg_match('/\{foreach \$' . preg_quote($assigned, '/') . ' .*?\{\/foreach\}/s', $template, $loop));
        $html = $engine->fetch('eval:<table>' . $loop[0] . '</table>');
        $document = new DOMDocument();
        $document->loadHTML('<?xml encoding="UTF-8">' . $html);
        $xpath = new DOMXPath($document);
        $this->assertSame(0, $xpath->query('//*[@data-security-fixture]')->length);
        $this->assertSame(2, $xpath->query('//button[@disabled]')->length);
        $this->assertSame(3, $xpath->query('//input[@name="CSRF_Token"]')->length);
        $descriptionColumn = $page === 'app-passwords' ? 2 : 3;
        $cells = $xpath->query('//tr/td[' . $descriptionColumn . ']');
        $this->assertSame(3, $cells->length);
        foreach ($descriptions as $index => $description) {
            $this->assertSame(html_entity_decode($description, ENT_QUOTES, 'UTF-8'), $cells->item($index)->textContent);
        }
        $this->assertStringNotContainsString('&amp;lt;tag&amp;gt;', $html);
    }
}
