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
 * Checks for available updates via the GitHub Releases API and allows
 * global admins to download and apply updates from the web UI.
 *
 * Template File: update-check.tpl
 */

require_once('common.php');

authentication_require_role('global-admin');

$smarty = PFASmarty::getInstance();

$current_version = Config::read_string('version');

/**
 * Fetch content from a URL using cURL (preferred) or file_get_contents as fallback.
 * Returns the response body as a string, or false on failure.
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
 * Fetch release information from the GitHub Releases API.
 * Returns an array of releases or an empty array on failure.
 */
function fetch_github_releases(): array
{
    $response = http_fetch(
        'https://api.github.com/repos/postfixadmin/postfixadmin/releases',
        10,
        ['Accept: application/vnd.github.v3+json']
    );

    if ($response === false) {
        return [];
    }

    $releases = json_decode($response, true);
    if (!is_array($releases)) {
        return [];
    }

    return $releases;
}

/**
 * Normalize a version tag to a comparable version string.
 * Handles formats like "v4.0.1", "postfixadmin-3.3.16", "3.3.8".
 */
function normalize_version(string $tag): string
{
    // Strip common prefixes
    $result = preg_replace('/^(postfixadmin-|postfxadmin-|v)/i', '', $tag);
    return $result ?? $tag;
}

/**
 * Find the tarball asset URL from a release's assets array.
 * Returns the download URL or null if no suitable asset is found.
 */
function find_tarball_asset(array $release): ?string
{
    if (empty($release['assets'])) {
        return null;
    }

    foreach ($release['assets'] as $asset) {
        if (preg_match('/\.tar\.gz$/', $asset['name'])) {
            return $asset['browser_download_url'];
        }
    }

    return null;
}

/**
 * Download a file from a URL to a local path.
 * Uses cURL if available, falls back to file_get_contents.
 * Returns true on success, false on failure.
 */
function download_file(string $url, string $dest): bool
{
    if (function_exists('curl_init')) {
        $ch = curl_init($url);
        if ($ch === false) {
            return false;
        }
        $fp = fopen($dest, 'w');
        if ($fp === false) {
            return false;
        }
        curl_setopt($ch, CURLOPT_FILE, $fp);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 120);
        curl_setopt($ch, CURLOPT_USERAGENT, 'PostfixAdmin-UpdateCheck');
        $success = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        fclose($fp);
        if (!$success || $http_code >= 400) {
            @unlink($dest);
            return false;
        }
        return filesize($dest) > 0;
    }

    // Fallback to file_get_contents
    $response = http_fetch($url, 120);
    if ($response === false) {
        return false;
    }
    return (bool)file_put_contents($dest, $response);
}

/**
 * Apply an update from a downloaded tarball.
 * Extracts the archive over the current installation directory.
 * Returns true on success, false on failure.
 */
function apply_update(string $tarball_path): bool
{
    $install_dir = dirname(__DIR__);
    $tmp_dir = sys_get_temp_dir() . '/postfixadmin-update-' . uniqid();

    if (!mkdir($tmp_dir, 0700, true)) {
        flash_error('Failed to create temporary directory for extraction.');
        return false;
    }

    // Extract the tarball (.tar.gz)
    try {
        $phar = new PharData($tarball_path);
        // For .tar.gz, PharData needs to decompress first, then extract
        if (preg_match('/\.tar\.gz$|\.tgz$/i', $tarball_path)) {
            $phar->decompress(); // creates .tar file alongside the .tar.gz
            $tar_path = preg_replace('/\.gz$/i', '', $tarball_path);
            if ($tar_path && file_exists($tar_path)) {
                $phar = new PharData($tar_path);
                $phar->extractTo($tmp_dir);
                @unlink($tar_path);
            } else {
                // Fallback: try direct extraction (some PHP versions handle .tar.gz directly)
                $phar->extractTo($tmp_dir);
            }
        } else {
            $phar->extractTo($tmp_dir);
        }
    } catch (Exception $e) {
        flash_error('Failed to extract update archive: ' . $e->getMessage());
        cleanup_dir($tmp_dir);
        return false;
    }

    // Find the extracted directory (tarball usually contains a single top-level dir)
    $extracted_dirs = glob($tmp_dir . '/*', GLOB_ONLYDIR);
    if (count($extracted_dirs) === 1) {
        $source_dir = $extracted_dirs[0];
    } else {
        $source_dir = $tmp_dir;
    }

    // Check we're not about to overwrite config.local.php
    if (file_exists($source_dir . '/config.local.php')) {
        flash_error('Update archive unexpectedly contains config.local.php — aborting for safety.');
        cleanup_dir($tmp_dir);
        return false;
    }

    // Copy files from extracted archive to installation directory
    if (!recursive_copy($source_dir, $install_dir)) {
        flash_error('Failed to copy updated files to installation directory.');
        cleanup_dir($tmp_dir);
        return false;
    }

    cleanup_dir($tmp_dir);
    return true;
}

/**
 * Recursively copy files from source to destination, skipping config.local.php.
 */
function recursive_copy(string $src, string $dst): bool
{
    $dir = opendir($src);
    if ($dir === false) {
        return false;
    }

    if (!is_dir($dst) && !mkdir($dst, 0755, true)) {
        return false;
    }

    while (($file = readdir($dir)) !== false) {
        if ($file === '.' || $file === '..') {
            continue;
        }

        // Never overwrite the local config
        if ($file === 'config.local.php') {
            continue;
        }

        $src_path = $src . '/' . $file;
        $dst_path = $dst . '/' . $file;

        if (is_dir($src_path)) {
            if (!recursive_copy($src_path, $dst_path)) {
                closedir($dir);
                return false;
            }
        } else {
            if (!copy($src_path, $dst_path)) {
                closedir($dir);
                return false;
            }
        }
    }

    closedir($dir);
    return true;
}

/**
 * Recursively remove a directory and its contents.
 */
function cleanup_dir(string $dir): void
{
    if (!is_dir($dir)) {
        return;
    }

    $items = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($dir, RecursiveDirectoryIterator::SKIP_DOTS),
        RecursiveIteratorIterator::CHILD_FIRST
    );

    foreach ($items as $item) {
        if ($item->isDir()) {
            rmdir($item->getRealPath());
        } else {
            unlink($item->getRealPath());
        }
    }

    rmdir($dir);
}

// --- Main logic ---

$error = '';
$latest_release = null;
$newer_releases = [];
$update_available = false;
$tarball_url = null;

// Handle POST: apply update
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    CsrfToken::assertValid(safepost('CSRF_Token'));

    $action = safepost('action');

    if ($action === 'apply_update') {
        $download_url = safepost('download_url');
        $update_version = safepost('update_version');

        if (empty($download_url) || empty($update_version)) {
            flash_error('Missing update information.');
        } elseif (!preg_match('#^https://github\.com/postfixadmin/postfixadmin/releases/download/#', $download_url)) {
            flash_error('Invalid download URL — only official GitHub release assets are allowed.');
        } else {
            // Check the install directory is writable
            $install_dir = dirname(__DIR__);
            if (!is_writable($install_dir)) {
                flash_error('Installation directory is not writable by the web server. Cannot apply update.');
            } else {
                // Download to temp file (PharData needs .tar.gz extension to detect format)
                $tmp_base = tempnam(sys_get_temp_dir(), 'postfixadmin-update-');
                $tmp_file = $tmp_base ? $tmp_base . '.tar.gz' : false;
                if ($tmp_base === false || $tmp_file === false) {
                    flash_error('Failed to create temporary file.');
                } elseif (!rename($tmp_base, $tmp_file)) {
                    flash_error('Failed to prepare temporary file.');
                    @unlink($tmp_base);
                } elseif (!download_file($download_url, $tmp_file)) {
                    flash_error('Failed to download update from: ' . htmlspecialchars($download_url));
                    @unlink($tmp_file);
                } elseif (apply_update($tmp_file)) {
                    @unlink($tmp_file);
                    // Clear OPcache so PHP serves the new files
                    if (function_exists('opcache_reset')) {
                        opcache_reset();
                    }
                    flash_info("Successfully updated to version " . htmlspecialchars($update_version) . ". Please run <a href='upgrade.php'>upgrade.php</a> to apply any database changes.");
                    header('Location: update-check.php');
                    exit;
                } else {
                    @unlink($tmp_file);
                    // flash_error was already called inside apply_update()
                }
            }
        }
    }
}

// Check we have a way to make HTTP requests before trying
$has_curl = function_exists('curl_init');
$allow_url_fopen = (bool)ini_get('allow_url_fopen');
$can_download = $has_curl || $allow_url_fopen;

if (!$can_download) {
    $error = 'Neither cURL nor allow_url_fopen is available. Install php-curl or enable allow_url_fopen in php.ini.';
    $releases = [];
} else {
    $releases = fetch_github_releases();
}

if (!$error && empty($releases)) {
    $error = 'Could not fetch release information from GitHub. Check that your server can reach api.github.com.';
} else {
    // Filter to non-draft, non-prerelease, and find newer versions
    foreach ($releases as $release) {
        if (!empty($release['draft']) || !empty($release['prerelease'])) {
            continue;
        }

        $release_version = normalize_version($release['tag_name']);

        if (version_compare($release_version, $current_version, '>')) {
            $release['normalized_version'] = $release_version;
            $release['tarball_asset'] = find_tarball_asset($release);
            $newer_releases[] = $release;
        }
    }

    // Sort newest first
    usort($newer_releases, function ($a, $b) {
        return version_compare($b['normalized_version'], $a['normalized_version']);
    });

    if (!empty($newer_releases)) {
        $update_available = true;
        $latest_release = $newer_releases[0];
        $tarball_url = $latest_release['tarball_asset'];
    }
}

// Check prerequisites for applying updates
$install_dir = dirname(__DIR__);
$is_writable = is_writable($install_dir);
$has_phar = class_exists('PharData');

$smarty->assign('current_version', $current_version);
$smarty->assign('error', $error);
$smarty->assign('update_available', $update_available);
$smarty->assign('latest_release', $latest_release);
$smarty->assign('newer_releases', $newer_releases);
$smarty->assign('tarball_url', $tarball_url);
$smarty->assign('is_writable', $is_writable);
$smarty->assign('has_phar', $has_phar);
$smarty->assign('can_download', $can_download);
$smarty->assign('install_dir', $install_dir);
$smarty->assign('smarty_template', 'update-check');
$smarty->display('index.tpl');
