<?php

/** Reply choices shared by the web selector and the PHP Vacation configuration. */
final class VacationReplyIntervals
{
    private const CHOICES = [
        'once' => [0, 'reply_once'],
        'every' => [1, 'reply_every_mail'],
        'day' => [86400, 'reply_once_per_day'],
        'week' => [604800, 'reply_once_per_week'],
    ];

    /** @return array<int, string>|null Null means the section was not declared. */
    public static function fromIni(array $configuration): ?array
    {
        if (!array_key_exists('reply_intervals', $configuration)) {
            return null;
        }
        $section = $configuration['reply_intervals'];
        if (!is_array($section) || $section === []) {
            throw new InvalidArgumentException('Vacation reply_intervals must contain at least one choice');
        }
        $choices = [];
        foreach ($section as $name => $seconds) {
            if (!isset(self::CHOICES[$name]) || !is_scalar($seconds)) {
                throw new InvalidArgumentException('Invalid Vacation reply_intervals entry');
            }
            [$interval, $label] = self::CHOICES[$name];
            if ((string)$seconds !== (string)$interval) {
                throw new InvalidArgumentException('Vacation reply_intervals must use the documented numeric equivalents');
            }
            $choices[$interval] = $label;
        }
        return $choices;
    }

    /** Missing files/sections preserve the effective PostfixAdmin choices. */
    public static function load(array $fallback, string $path): array
    {
        if ($path === '' || !file_exists($path)) {
            return $fallback;
        }
        // Parser warnings may contain configuration values, including credentials.
        $configuration = @parse_ini_file($path, true, INI_SCANNER_RAW);
        if ($configuration === false) {
            throw new RuntimeException('Cannot read or parse the Vacation INI configuration');
        }
        return self::fromIni($configuration) ?? $fallback;
    }
}
