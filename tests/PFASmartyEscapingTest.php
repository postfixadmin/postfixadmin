<?php

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * PFASmarty enables Smarty auto-escaping, so output is HTML-escaped when rendered
 * (not when assigned) unless the template explicitly uses |raw.
 */
class PFASmartyEscapingTest extends TestCase
{
    private const PAYLOAD = '<b>R&D</b> "x" \'y\'';

    public static function templates(): array
    {
        return [
            'plain variable is escaped' => [
                '{$t.nested.text}',
                '&lt;b&gt;R&amp;D&lt;/b&gt; &quot;x&quot; &#039;y&#039;',
            ],
            '|escape (html) does not double escape' => [
                '{$t.nested.text|escape}',
                '&lt;b&gt;R&amp;D&lt;/b&gt; &quot;x&quot; &#039;y&#039;',
            ],
            '|raw is not escaped' => [
                '{$t.nested.text|raw}',
                self::PAYLOAD,
            ],
            '|escape:url is not html escaped twice' => [
                '{$t.nested.text|escape:url}',
                rawurlencode(self::PAYLOAD),
            ],
            'javascript in attribute is forced through html escaping' => [
                '{$t.nested.text|escape:"javascript"|escape:"force"}',
                '&lt;b&gt;R&amp;D&lt;\\/b&gt; \\&quot;x\\&quot; \\&#039;y\\&#039;',
            ],
            // escape:javascript only backslash-escapes quotes, backslashes, newlines and "</";
            // auto-escaping then skips HTML escaping, so < > & reach the output unchanged.
            'javascript escaping is not html escaped' => [
                '{$t.nested.text|escape:"javascript"}',
                '<b>R&D<\\/b> \\"x\\" \\\'y\\\'',
            ],
            'existing entities are escaped (not passed through)' => [
                '{$t.entity}',
                '&amp;amp;',
            ],
            'array keys are not modified' => [
                '{foreach $t.keyed as $k => $v}{$k}={$v}{/foreach}',
                'a&lt;b=1',
            ],
        ];
    }

    #[DataProvider('templates')]
    public function testOutputEscaping(string $template, string $expected): void
    {
        $smarty = PFASmarty::getInstance();
        $smarty->assign('t', [
            'nested' => ['text' => self::PAYLOAD],
            'entity' => '&amp;',
            'keyed' => ['a<b' => 1],
        ]);
        self::assertSame($expected, $this->render($template));
    }

    public function testRequestSuperglobalsAreEscaped(): void
    {
        $_GET['escaping_test'] = '"><script>alert(1)</script>';
        try {
            self::assertSame(
                '&quot;&gt;&lt;script&gt;alert(1)&lt;/script&gt;',
                $this->render('{$smarty.get.escaping_test}')
            );
        } finally {
            unset($_GET['escaping_test']);
        }
    }

    public function testRawCopiesAreNoLongerAssigned(): void
    {
        $smarty = PFASmarty::getInstance();
        $smarty->assign('escaping_test', 'value');
        self::assertSame('missing', $this->render('{if isset($RAW_escaping_test)}present{else}missing{/if}'));
    }

    private function render(string $template): string
    {
        ob_start();
        try {
            PFASmarty::getInstance()->display('string:' . $template);
            return (string)ob_get_contents();
        } finally {
            ob_end_clean();
        }
    }
}
