<?php

use PHPUnit\Framework\TestCase;

class ViewlogOutputSecurityTest extends TestCase
{
    public function testControllerUsesDefaultOutputSanitisation(): void
    {
        $controller = file_get_contents(__DIR__ . '/../public/viewlog.php');

        $this->assertStringContainsString("assign('tLog', \$tLog);", $controller);
        $this->assertStringNotContainsString("assign('tLog', \$tLog, false)", $controller);
    }

    public function testTemplateDoesNotEmbedLogDataInJavascript(): void
    {
        $template = file_get_contents(__DIR__ . '/../templates/viewlog.tpl');

        $this->assertStringNotContainsString('alert(', $template);
        $this->assertStringNotContainsString('assign var=item_data', $template);
        $this->assertStringContainsString('<details>', $template);
    }
}
