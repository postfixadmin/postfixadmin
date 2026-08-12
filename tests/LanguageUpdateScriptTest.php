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
        file_put_contents($this->directory . '/after.lang', <<<'PHP'
<?php
$PALANG['one'] = 'One';
$PALANG['three'] = 'Three';
PHP);

        [$status, $stdout, $stderr] = $this->runScript(['test.lang', 'after.lang']);

        self::assertSame(1, $status);
        self::assertStringContainsString('PHP syntax error', $stderr);
        self::assertStringNotContainsString('--fix', $stderr);
        self::assertStringNotContainsString(' missing,', $stdout);
        self::assertStringNotContainsString('after.lang', $stdout);
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
