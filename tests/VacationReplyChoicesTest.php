<?php

require_once dirname(__DIR__) . '/model/VacationReplyIntervals.php';

class VacationReplyChoicesTest extends \PHPUnit\Framework\TestCase
{
    #[\PHPUnit\Framework\Attributes\DataProvider('replyChoices')]
    public function testConfigurationPreservesNumericLocalOverrides(?array $local, array $expected, ?string $ini = null): void
    {
        global $CONF;
        $original = $CONF;
        $directory = tempnam(sys_get_temp_dir(), 'pfa-reply-choices-');
        unlink($directory);
        mkdir($directory);

        try {
            copy(__DIR__ . '/../config.inc.php', $directory . '/config.inc.php');
            if ($local !== null) {
                file_put_contents(
                    $directory . '/config.local.php',
                    "<?php\n\$CONF['vacation_choice_of_reply'] = " . var_export($local, true) . ";\n"
                );
            }
            require $directory . '/config.inc.php';
            if ($ini !== null) {
                file_put_contents($directory . '/vacation.ini', $ini);
            }
            $choices = VacationReplyIntervals::load(
                $CONF['vacation_choice_of_reply'],
                $directory . '/vacation.ini'
            );
            $this->assertSame($expected, $choices);
        } finally {
            $CONF = $original;
            if (file_exists($directory . '/config.local.php')) {
                unlink($directory . '/config.local.php');
            }
            if (file_exists($directory . '/vacation.ini')) {
                unlink($directory . '/vacation.ini');
            }
            unlink($directory . '/config.inc.php');
            rmdir($directory);
        }
    }

    public function testDisabledIniLoadingPreservesChoices(): void
    {
        $fallback = [3600 => 'x_hour'];
        $this->assertSame($fallback, VacationReplyIntervals::load($fallback, ''));
    }

    public function testMalformedIniFailsWithoutParserDetails(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'pfa-reply-invalid-');
        try {
            file_put_contents($path, "[reply_intervals\n");
            $this->expectException(\RuntimeException::class);
            $this->expectExceptionMessage('Cannot read or parse the Vacation INI configuration');
            VacationReplyIntervals::load([0 => 'reply_once'], $path);
        } finally {
            unlink($path);
        }
    }

    public static function replyChoices(): array
    {
        return [
            'defaults include daily but not every mail' => [
                null,
                [0 => 'reply_once', 86400 => 'reply_once_per_day', 604800 => 'reply_once_per_week'],
            ],
            'legacy local choices are not expanded' => [
                [0 => 'reply_once', 60 * 60 * 24 * 7 => 'reply_once_per_week'],
                [0 => 'reply_once', 604800 => 'reply_once_per_week'],
            ],
            'numeric expressions and custom intervals' => [
                [0 => 'reply_once', 1 => 'reply_every_mail', 60 * 60 * 24 => 'reply_once_per_day', 3600 => 'x_hour'],
                [0 => 'reply_once', 1 => 'reply_every_mail', 86400 => 'reply_once_per_day', 3600 => 'x_hour'],
            ],
            'empty local choices remain empty' => [[], []],
            'INI section overrides effective local choices' => [
                [3600 => 'x_hour'],
                [0 => 'reply_once', 86400 => 'reply_once_per_day', 604800 => 'reply_once_per_week'],
                "[reply_intervals]\nonce = 0\n; every = 1\nday = 86400\nweek = 604800\n",
            ],
            'absent section preserves effective local choices' => [
                [3600 => 'x_hour'],
                [3600 => 'x_hour'],
                "[reply]\naccount_name = true\n",
            ],
            'every mail remains opt-in' => [
                null,
                [1 => 'reply_every_mail'],
                "[reply_intervals]\nevery = 1\n",
            ],
        ];
    }
}
