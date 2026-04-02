<?php

/**
 * Postfix Admin
 *
 * LICENSE
 * This source file is subject to the GPL license that is bundled with
 * this package in the file LICENSE.TXT.
 *
 * Further details on the project are available at https://github.com/postfixadmin/postfixadmin
 *
 * @license GNU GPL v2 or later.
 *
 * File: update-check.php
 * Checks for available updates via the GitHub Releases API and displays
 * version information with changelogs for global admins.
 *
 * Template File: update-check.tpl
 */

require_once('common.php');

authentication_require_role('global-admin');

$smarty = PFASmarty::getInstance();

$current_version = Config::read_string('version');

/**
 * Fetch content from a URL using cURL (preferred) or file_get_contents as fallback.
 *
 * @param string $url
 * @param int $timeout
 * @param array<string> $headers
 * @return string|false
 */
function http_fetch(string $url, int $timeout = 10, array $headers = [])
{
    if (function_exists('curl_init')) {
        $ch = curl_init($url);
        if ($ch === false) {
            return false;
        }
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, $timeout);
        curl_setopt($ch, CURLOPT_USERAGENT, 'PostfixAdmin-UpdateCheck');
        if (!empty($headers)) {
            curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        }
        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        if (!is_string($response) || $http_code >= 400) {
            return false;
        }
        return $response;
    }

    // Fallback to file_get_contents (requires allow_url_fopen)
    $context = stream_context_create([
        'http' => [
            'method' => 'GET',
            'header' => array_merge(['User-Agent: PostfixAdmin-UpdateCheck'], $headers),
            'timeout' => $timeout,
            'follow_location' => true,
        ],
    ]);

    /** @psalm-suppress InvalidArgument */
    return @file_get_contents($url, false, $context);
}

/**
 * Normalize a version tag to a comparable version string.
 * Handles formats like "v4.0.1", "postfixadmin-3.3.16", "3.3.8".
 */
function normalize_version(string $tag): string
{
    $result = preg_replace('/^(postfixadmin-|postfxadmin-|v)/i', '', $tag);
    return $result ?? $tag;
}

// --- Main logic ---

$error = '';
$latest_release = null;
$newer_releases = [];
$update_available = false;

$has_curl = function_exists('curl_init');
$allow_url_fopen = (bool)ini_get('allow_url_fopen');
$can_fetch = $has_curl || $allow_url_fopen;

if (!$can_fetch) {
    $error = Config::lang('pUpdate_fetch_error');
} else {
    $response = http_fetch(
        'https://api.github.com/repos/postfixadmin/postfixadmin/releases',
        10,
        ['Accept: application/vnd.github.v3+json']
    );

    if ($response === false) {
        $error = Config::lang('pUpdate_network_error');
    } else {
        $releases = json_decode($response, true);
        if (!is_array($releases)) {
            $error = Config::lang('pUpdate_network_error');
        } else {
            foreach ($releases as $release) {
                if (!empty($release['draft']) || !empty($release['prerelease'])) {
                    continue;
                }

                $release_version = normalize_version($release['tag_name']);

                if (version_compare($release_version, $current_version, '>')) {
                    $release['normalized_version'] = $release_version;
                    $newer_releases[] = $release;
                }
            }

            usort($newer_releases, function ($a, $b) {
                return version_compare($b['normalized_version'], $a['normalized_version']);
            });

            if (!empty($newer_releases)) {
                $update_available = true;
                $latest_release = $newer_releases[0];
            }
        }
    }
}

$smarty->assign('current_version', $current_version);
$smarty->assign('error', $error);
$smarty->assign('update_available', $update_available);
$smarty->assign('latest_release', $latest_release);
$smarty->assign('newer_releases', $newer_releases);
$smarty->assign('smarty_template', 'update-check');
$smarty->display('index.tpl');
