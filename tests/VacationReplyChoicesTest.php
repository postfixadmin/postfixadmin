<?php

class VacationReplyChoicesTest extends \PHPUnit\Framework\TestCase
{
    #[\PHPUnit\Framework\Attributes\DataProvider('replyChoices')]
    public function testConfigurationNormalizesAfterLocalOverrides(?array $local, array $expected): void
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
            $this->assertSame($expected, $CONF['vacation_choice_of_reply']);
        } finally {
            $CONF = $original;
            if (file_exists($directory . '/config.local.php')) {
                unlink($directory . '/config.local.php');
            }
            unlink($directory . '/config.inc.php');
            rmdir($directory);
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
            'readable local choices' => [
                ['once' => 'reply_once', 'every' => 'reply_every_mail', 'day' => 'reply_once_per_day', 'week' => 'reply_once_per_week'],
                [0 => 'reply_once', 1 => 'reply_every_mail', 86400 => 'reply_once_per_day', 604800 => 'reply_once_per_week'],
            ],
            'numeric expressions and custom intervals' => [
                [0 => 'reply_once', 1 => 'reply_every_mail', 60 * 60 * 24 => 'reply_once_per_day', 3600 => 'x_hour'],
                [0 => 'reply_once', 1 => 'reply_every_mail', 86400 => 'reply_once_per_day', 3600 => 'x_hour'],
            ],
            'numeric entry overrides readable equivalent' => [
                ['day' => 'reply_once_per_day', 86400 => 'x_day', 'week' => 'reply_once_per_week'],
                [86400 => 'x_day', 604800 => 'reply_once_per_week'],
            ],
            'readable entry overrides numeric equivalent' => [
                [86400 => 'x_day', 'day' => 'reply_once_per_day'],
                [86400 => 'reply_once_per_day'],
            ],
            'empty local choices remain empty' => [[], []],
        ];
    }
}
