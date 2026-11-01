<?php

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class PFASmartyEscapingTest extends TestCase
{
    public static function modes(): array
    {
        return [
            'default' => [null, true],
            'explicit HTML' => [TemplateEscape::HTML, true],
            'explicit RAW' => [TemplateEscape::RAW, false],
        ];
    }

    #[DataProvider('modes')]
    public function testAssignmentEscapingAndRawCompatibility(?TemplateEscape $mode, bool $escaped): void
    {
        $smarty = PFASmarty::getInstance();
        $value = ['nested' => ['text' => '<b>R&D</b>']];
        if ($mode === null) {
            $smarty->assign('escaping_test', $value);
        } else {
            $smarty->assign('escaping_test', $value, $mode);
        }
        self::assertSame(($escaped ? '&lt;b&gt;R&amp;D&lt;/b&gt;' : '<b>R&D</b>') . '|<b>R&D</b>', $this->renderValue());
    }

    public static function invalidModes(): array
    {
        return [
            'false' => [false],
            'true' => [true],
            'null' => [null],
            'zero' => [0],
            'empty string' => [''],
            'HTML string' => ['html'],
            'RAW string' => ['raw'],
            'old constant value' => ['no_escaping'],
            'array' => [[]],
        ];
    }

    #[DataProvider('invalidModes')]
    public function testInvalidModeIsRejectedBeforeAssignment(mixed $mode): void
    {
        $smarty = PFASmarty::getInstance();
        $smarty->assign('escaping_test', ['nested' => ['text' => '<b>original</b>']]);
        try {
            $smarty->assign('escaping_test', ['nested' => ['text' => 'replacement']], $mode);
            self::fail('An invalid mode must throw TypeError');
        } catch (TypeError $error) {
            self::assertStringContainsString('TemplateEscape', $error->getMessage());
        }
        self::assertSame('&lt;b&gt;original&lt;/b&gt;|<b>original</b>', $this->renderValue());
    }

    private function renderValue(): string
    {
        ob_start();
        try {
            PFASmarty::getInstance()->display('string:{$escaping_test.nested.text}|{$RAW_escaping_test.nested.text}');
            return (string)ob_get_contents();
        } finally {
            ob_end_clean();
        }
    }
}
