<?php

use PHPUnit\Framework\TestCase;

class LanguageSyntaxFixScriptTest extends TestCase
{
    private string $directory;
    private string $script;

    protected function setUp(): void
    {
        $this->directory = sys_get_temp_dir() . '/postfixadmin-language-fix-' . bin2hex(random_bytes(6));
        self::assertTrue(mkdir($this->directory));
        $script = realpath(__DIR__ . '/../ADDITIONS/fix-language-syntax.php');
        self::assertNotFalse($script);
        $this->script = $script;
    }

    protected function tearDown(): void
    {
        if (!isset($this->directory) || !is_dir($this->directory)) {
            return;
        }

        foreach (new DirectoryIterator($this->directory) as $file) {
            if (!$file->isDot()) {
                unlink($file->getPathname());
            }
        }
        rmdir($this->directory);
    }

    public function testRepairsSafeErrorsAndCreatesBackup(): void
    {
        $file = $this->directory . '/test.lang';
        $invalid = <<<'PHP'
<?php
$PALANG['one'] = 'One'
$PALANG['body'] = <<<EOM
Body
EOM
$PALANG['three'] = 'Three';
PHP;
        file_put_contents($file, $invalid);

        [$status, $stdout, $stderr] = $this->runScript([$file]);

        self::assertSame(0, $status, $stderr);
        self::assertStringContainsString('fixed 1 missing assignment semicolon(s)', $stdout);
        self::assertStringContainsString('fixed 1 missing heredoc semicolon(s)', $stdout);
        self::assertSame($invalid, file_get_contents($file . '.bak'));
        self::assertStringContainsString('$PALANG[\'one\'] = \'One\';', file_get_contents($file));

        [$secondStatus, $secondStdout, $secondStderr] = $this->runScript([$file]);

        self::assertSame(0, $secondStatus, $secondStderr);
        self::assertStringContainsString('syntax is valid; no changes', $secondStdout);
        self::assertFileDoesNotExist($file . '.bak2');
    }

    public function testNumbersExistingBackups(): void
    {
        $file = $this->directory . '/test.lang';
        $invalid = "<?php\n\$PALANG['one'] = 'One'\n";
        file_put_contents($file, $invalid);
        file_put_contents($file . '.bak', 'existing backup');

        [$status, $stdout, $stderr] = $this->runScript([$file]);

        self::assertSame(0, $status, $stderr);
        self::assertStringContainsString('backup saved as ' . $file . '.bak2', $stdout);
        self::assertSame('existing backup', file_get_contents($file . '.bak'));
        self::assertSame($invalid, file_get_contents($file . '.bak2'));
    }

    public function testLeavesAmbiguousSyntaxUnchanged(): void
    {
        $file = $this->directory . '/test.lang';
        $invalid = "<?php\n\$PALANG['one'] = 'Unclosed string;\n";
        file_put_contents($file, $invalid);

        [$status, , $stderr] = $this->runScript([$file]);

        self::assertSame(1, $status);
        self::assertStringContainsString('no safe complete repair', $stderr);
        self::assertSame($invalid, file_get_contents($file));
        self::assertFileDoesNotExist($file . '.bak');
    }

    private function runScript(array $arguments): array
    {
        $command = array_merge([PHP_BINARY, $this->script], $arguments);
        $pipes = [];
        $process = proc_open($command, [
            0 => ['pipe', 'r'],
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w'],
        ], $pipes);
        self::assertIsResource($process);
        fclose($pipes[0]);
        $stdout = stream_get_contents($pipes[1]);
        $stderr = stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);

        return [proc_close($process), $stdout, $stderr];
    }
}
