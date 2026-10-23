<?php

/** Resolve Cancel destinations without allowing external URLs or action endpoints. */
class EditFormNavigation
{
    public static function cancelUrl(string $candidate, string $scriptName, string $fallback): string
    {
        $parts = parse_url($candidate);
        if ($parts === false || isset($parts['scheme']) || isset($parts['host'])
            || isset($parts['fragment']) || preg_match('/[\\\\\x00-\x20]/', $candidate)) {
            return $fallback;
        }

        $path = $parts['path'] ?? '';
        $directory = rtrim(dirname($scriptName), '/');
        foreach (['main.php', 'list.php', 'list-virtual.php', 'users/main.php'] as $page) {
            if ($path === $page || $path === $directory . '/' . $page) {
                return $page . (isset($parts['query']) ? '?' . $parts['query'] : '');
            }
        }

        return $fallback;
    }
}
