<?php

use PHPUnit\Framework\TestCase;

class LanguageUpdateScriptTest extends TestCase
{
    private string $directory;
    private string $script;

    protected function setUp(): void
    {
        if (PHP_OS_FAMILY === 'Windows' || !is_executable('/bin/bash')) {
            $this->markTestSkipped('language-update.sh requires a Unix-like environment with Bash');
        }

        $this->directory = sys_get_temp_dir() . '/postfixadmin-language-update-' . bin2hex(random_bytes(6));
        self::assertTrue(mkdir($this->directory));
        $script = realpath(__DIR__ . '/../languages/language-update.sh');
        self::assertNotFalse($script);
        $this->script = $script;

        file_put_contents($this->directory . '/en.lang', <<<'PHP'
<?php
$PALANG['one'] = 'One';
$PALANG['body'] = <<<EOM
Body
EOM;
$PALANG['three'] = 'Three';
PHP);
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

    public function testInvalidSyntaxStopsBeforeMissingKeyDetection(): void
    {
        file_put_contents($this->directory . '/test.lang', <<<'PHP'
<?php
$PALANG['one'] = 'One'
$PALANG['body'] = <<<EOM
Body
EOM;
$PALANG['three'] = 'Three';
PHP);

        [$status, $stdout, $stderr] = $this->runScript(['test.lang']);

        self::assertSame(1, $status);
        self::assertStringContainsString('PHP syntax error', $stderr);
        self::assertStringContainsString('--fix test.lang', $stderr);
        self::assertStringNotContainsString(' missing,', $stdout);
    }

    public function testMissingTranslationsReturnFailure(): void
    {
        file_put_contents($this->directory . '/test.lang', <<<'PHP'
<?php
$PALANG['one'] = 'One';
$PALANG['three'] = 'Three';
PHP);

        [$status, $stdout, $stderr] = $this->runScript(['test.lang']);

        self::assertSame(1, $status, $stderr);
        self::assertStringContainsString('1 missing, 0 obsolete', $stdout);
    }

    public function testObsoleteTranslationsReturnFailure(): void
    {
        file_put_contents($this->directory . '/test.lang', <<<'PHP'
<?php
$PALANG['one'] = 'One';
$PALANG['body'] = <<<EOM
Body
EOM;
$PALANG['three'] = 'Three';
$PALANG['obsolete'] = 'Obsolete';
PHP);

        [$status, $stdout, $stderr] = $this->runScript(['test.lang']);

        self::assertSame(1, $status, $stderr);
        self::assertStringContainsString('0 missing, 1 obsolete', $stdout);
    }

    public function testFixRepairsSafeErrorsAndIsIdempotent(): void
    {
        $file = $this->directory . '/test.lang';
        file_put_contents($file, <<<'PHP'
<?php
$PALANG['one'] = 'One'
$PALANG['body'] = <<<EOM
Body
EOM
$PALANG['three'] = 'Three';
PHP);

        [$status, $stdout, $stderr] = $this->runScript(['--fix', 'test.lang']);

        self::assertSame(0, $status, $stderr);
        self::assertStringContainsString('fixed 1 missing semicolon(s)', $stdout);
        self::assertStringContainsString('fixed 1 missing heredoc semicolon(s)', $stdout);
        $fixed = file_get_contents($file);
        self::assertFileExists($file . '.bak');
        self::assertStringNotContainsString('$PALANG[\'one\'] = \'One\';', file_get_contents($file . '.bak'));
        self::assertStringContainsString('$PALANG[\'one\'] = \'One\';', $fixed);
        self::assertStringContainsString("\nEOM;\n", $fixed);

        [$secondStatus, , $secondStderr] = $this->runScript(['--fix', 'test.lang']);

        self::assertSame(0, $secondStatus, $secondStderr);
        self::assertSame($fixed, file_get_contents($file));
        self::assertFileDoesNotExist($file . '.bak2');
    }

    public function testFixNumbersExistingBackups(): void
    {
        $file = $this->directory . '/test.lang';
        $invalid = <<<'PHP'
<?php
$PALANG['one'] = 'One'
$PALANG['body'] = <<<EOM
Body
EOM;
$PALANG['three'] = 'Three';
PHP;
        file_put_contents($file, $invalid);
        file_put_contents($file . '.bak', 'existing backup');

        [$status, $stdout, $stderr] = $this->runScript(['--fix', 'test.lang']);

        self::assertSame(0, $status, $stderr);
        self::assertStringContainsString('backup saved as test.lang.bak2', $stdout);
        self::assertSame('existing backup', file_get_contents($file . '.bak'));
        self::assertSame($invalid, file_get_contents($file . '.bak2'));
    }

    public function testFixLeavesAmbiguousSyntaxUnchanged(): void
    {
        $file = $this->directory . '/test.lang';
        $invalid = <<<'PHP'
<?php
$PALANG['one'] = 'Unclosed string;
$PALANG['three'] = 'Three';
PHP;
        file_put_contents($file, $invalid);

        [$status, , $stderr] = $this->runScript(['--fix', 'test.lang']);

        self::assertSame(1, $status);
        self::assertStringContainsString('no safe automatic repair', $stderr);
        self::assertSame($invalid, file_get_contents($file));
    }

    private function runScript(array $arguments): array
    {
        $command = array_merge(['/bin/bash', $this->script], $arguments);
        $pipes = [];
        $process = proc_open($command, [
            0 => ['pipe', 'r'],
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w'],
        ], $pipes, $this->directory);
        self::assertIsResource($process);
        fclose($pipes[0]);
        $stdout = stream_get_contents($pipes[1]);
        $stderr = stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);

        return [proc_close($process), $stdout, $stderr];
    }
}
