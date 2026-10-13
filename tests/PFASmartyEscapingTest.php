<?php

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class PFASmartyEscapingTest extends TestCase
{
    public static function modes(): array
    {
        return [
            'default' => [null, true],
            'explicit escaping' => [true, true],
            'explicit opt-out' => [PFASmarty::NO_ESCAPING, false],
            'legacy opt-out' => [false, false],
        ];
    }

    #[DataProvider('modes')]
    public function testAssignmentEscapingAndRawCompatibility(bool|string|null $mode, bool $escaped): void
    {
        $smarty = PFASmarty::getInstance();
        $value = ['nested' => ['text' => '<b>R&D</b>']];
        if ($mode === null) {
            $smarty->assign('escaping_test', $value);
        } else {
            $smarty->assign('escaping_test', $value, $mode);
        }
        ob_start();
        try {
            $smarty->display('string:{$escaping_test.nested.text}|{$RAW_escaping_test.nested.text}');
            $html = (string)ob_get_contents();
        } finally {
            ob_end_clean();
        }
        self::assertSame(($escaped ? '&lt;b&gt;R&amp;D&lt;/b&gt;' : '<b>R&D</b>') . '|<b>R&D</b>', $html);
    }
}
