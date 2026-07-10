#!/usr/bin/env php
<?php

declare(strict_types=1);

/**
 * Update an existing PostfixAdmin installation with files from this tree.
 *
 * Run this script from the unpacked new release:
 *
 *   scripts/installto.sh [-y] [--dry-run] /path/to/existing/postfixadmin
 *
 * The web server should not need write access to the PostfixAdmin code tree.
 * Run this script as root or as the deployment user that owns the installation.
 */

function usage(): void
{
    echo <<<'EOF'
Usage: scripts/installto.sh [-y] [--dry-run] <TARGET>

Update TARGET with files from the PostfixAdmin tree containing this script.

Options:
  -y, --yes      Do not prompt for confirmation.
  -n, --dry-run  Show what would be done without modifying TARGET.
  -h, --help     Show this help.

The script preserves config.local.php, config.local.php.* and locally referenced
theme assets such as theme_logo, theme_favicon and theme_custom_css.
After copying files, it runs TARGET/install.sh to install dependencies and
prepare templates_c, then runs the database upgrade.
EOF;
    echo "\n";
}

function fail(string $message): never
{
    fwrite(STDERR, "ERROR: $message\n");
    exit(1);
}

function info(string $message): void
{
    echo " * $message\n";
}

function real_dir(string $path): string
{
    $real = realpath($path);
    if ($real === false || !is_dir($real)) {
        fail("Invalid directory: $path");
    }
    return $real;
}

function command_exists(string $command): bool
{
    $check = trim((string) shell_exec('command -v ' . escapeshellarg($command) . ' 2>/dev/null'));
    return $check !== '';
}

function run_command(string $command): void
{
    passthru($command, $exitCode);
    if ($exitCode !== 0) {
        fail("Command failed with exit code $exitCode: $command");
    }
}

function read_config_value(string $file, string $key): string
{
    if (!is_file($file)) {
        return '';
    }

    $contents = (string) file_get_contents($file);
    $pattern = "/^[ \t]*\\\$CONF\\[['\"]" . preg_quote($key, '/') . "['\"]\\][ \t]*=[ \t]*(['\"])(.*?)\\1[ \t]*;/m";

    if (preg_match_all($pattern, $contents, $matches) === 0) {
        return '';
    }

    return (string) end($matches[2]);
}

function read_version(string $dir): string
{
    return read_config_value($dir . DIRECTORY_SEPARATOR . 'config.inc.php', 'version');
}

function copy_file_preserving_path(string $source, string $destination): void
{
    $parent = dirname($destination);
    if (!is_dir($parent) && !mkdir($parent, 0777, true) && !is_dir($parent)) {
        fail("Could not create directory: $parent");
    }

    if (!copy($source, $destination)) {
        fail("Could not copy $source to $destination");
    }

    @chmod($destination, fileperms($source) & 0777);
    @touch($destination, filemtime($source));
}

function preserve_file(string $relativePath, string $targetDir, string $preserveDir): void
{
    $source = $targetDir . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relativePath);
    if (!is_file($source)) {
        return;
    }

    $destination = $preserveDir . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relativePath);
    copy_file_preserving_path($source, $destination);
    info("Preserving $relativePath");
}

function preserve_theme_asset(string $key, string $targetDir, string $preserveDir): void
{
    $configLocal = $targetDir . DIRECTORY_SEPARATOR . 'config.local.php';
    $value = read_config_value($configLocal, $key);

    if ($value === '' || preg_match('~^(?:https?://|/)~', $value) === 1) {
        return;
    }

    preserve_file('public/' . ltrim($value, '/'), $targetDir, $preserveDir);
}

function remove_tree(string $path): void
{
    if (!is_dir($path)) {
        return;
    }

    $items = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($path, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::CHILD_FIRST
    );

    foreach ($items as $item) {
        if ($item->isDir() && !$item->isLink()) {
            rmdir($item->getPathname());
        } else {
            unlink($item->getPathname());
        }
    }

    rmdir($path);
}

function shell_cd(string $dir, string $command): string
{
    return 'cd ' . escapeshellarg($dir) . ' && ' . $command;
}

function with_php_binary_path(string $command): string
{
    $path = getenv('PATH');
    $phpPath = dirname(PHP_BINARY) . ':' . ($path === false ? '' : $path);

    return 'PATH=' . escapeshellarg($phpPath) . ' ' . $command;
}

$yes = false;
$dryRun = false;
$targetArg = null;

$args = $argv;
array_shift($args);

while ($args !== []) {
    $arg = array_shift($args);

    if ($arg === '-y' || $arg === '--yes') {
        $yes = true;
    } elseif ($arg === '-n' || $arg === '--dry-run') {
        $dryRun = true;
    } elseif ($arg === '-h' || $arg === '--help') {
        usage();
        exit(0);
    } elseif ($arg === '--') {
        break;
    } elseif (str_starts_with($arg, '-')) {
        usage();
        fail("Unknown option: $arg");
    } elseif ($targetArg === null) {
        $targetArg = $arg;
    } else {
        usage();
        fail('Only one TARGET can be specified');
    }
}

if ($args !== []) {
    if ($targetArg !== null || count($args) > 1) {
        usage();
        fail('Only one TARGET can be specified');
    }
    $targetArg = $args[0];
}

if ($targetArg === null) {
    usage();
    fail('Missing TARGET');
}

$sourceDir = real_dir(dirname(__DIR__));
$targetDir = real_dir($targetArg);

if ($targetDir === DIRECTORY_SEPARATOR) {
    fail('Refusing to update /');
}

if ($sourceDir === $targetDir) {
    fail('Source and target are the same directory');
}

foreach (['config.inc.php', 'install.sh', 'public', 'templates'] as $path) {
    if (!file_exists($sourceDir . DIRECTORY_SEPARATOR . $path)) {
        fail("Source does not look like PostfixAdmin: missing $path");
    }
    if (!file_exists($targetDir . DIRECTORY_SEPARATOR . $path)) {
        fail("Target does not look like PostfixAdmin: missing $path");
    }
}

$oldVersion = read_version($targetDir);
$newVersion = read_version($sourceDir);

if ($oldVersion === '') {
    fail('Could not read target version');
}

if ($newVersion === '') {
    fail('Could not read source version');
}

echo "PostfixAdmin source: $sourceDir\n";
echo "PostfixAdmin target: $targetDir\n";
echo "Target version:      $oldVersion\n";
echo "Source version:      $newVersion\n\n";

if (!$yes) {
    echo 'Continue updating target? (y/N) ';
    $answer = trim((string) fgets(STDIN));
    if (!in_array($answer, ['y', 'Y', 'yes', 'YES'], true)) {
        echo "Update cancelled.\n";
        exit(0);
    }
}

if (!$dryRun) {
    if (!command_exists('rsync')) {
        fail('rsync is required');
    }
}

$tmpBase = rtrim(sys_get_temp_dir(), DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . 'postfixadmin-installto.' . getmypid();
$preserveDir = $tmpBase . DIRECTORY_SEPARATOR . 'preserve';

register_shutdown_function(static function () use ($tmpBase): void {
    remove_tree($tmpBase);
});

if (!mkdir($preserveDir, 0777, true) && !is_dir($preserveDir)) {
    fail("Could not create temporary directory: $preserveDir");
}

preserve_file('config.local.php', $targetDir, $preserveDir);
foreach (glob($targetDir . DIRECTORY_SEPARATOR . 'config.local.php.*') ?: [] as $file) {
    if (is_file($file)) {
        preserve_file(basename($file), $targetDir, $preserveDir);
    }
}

preserve_theme_asset('theme_logo', $targetDir, $preserveDir);
preserve_theme_asset('theme_favicon', $targetDir, $preserveDir);
preserve_theme_asset('theme_custom_css', $targetDir, $preserveDir);

echo "\n";
info('Copying release files to target');

$rsyncCopy = 'rsync -aC --delete'
    . ' --exclude ' . escapeshellarg('/.git/')
    . ' --exclude ' . escapeshellarg('/config.local.php')
    . ' --exclude ' . escapeshellarg('/config.local.php.*')
    . ' --exclude ' . escapeshellarg('/templates_c/')
    . ' --exclude ' . escapeshellarg('/public/users/')
    . ' ' . escapeshellarg($sourceDir . DIRECTORY_SEPARATOR)
    . ' ' . escapeshellarg($targetDir . DIRECTORY_SEPARATOR);

if ($dryRun) {
    echo "DRY-RUN: $rsyncCopy\n";
} else {
    run_command($rsyncCopy);
}

info('Restoring preserved local files');
$rsyncRestore = 'rsync -a ' . escapeshellarg($preserveDir . DIRECTORY_SEPARATOR) . ' ' . escapeshellarg($targetDir . DIRECTORY_SEPARATOR);
if ($dryRun) {
    echo "DRY-RUN: $rsyncRestore\n";
} else {
    run_command($rsyncRestore);
}

info('Running install.sh in target');
$installCommand = shell_cd($targetDir, with_php_binary_path('sh install.sh'));
if ($dryRun) {
    echo "DRY-RUN: $installCommand\n";
} else {
    run_command($installCommand);
}

info('Running database upgrade');
$upgradeCommand = shell_cd($targetDir . DIRECTORY_SEPARATOR . 'public', escapeshellarg(PHP_BINARY) . ' upgrade.php');
if ($dryRun) {
    echo "DRY-RUN: $upgradeCommand\n";
} else {
    run_command($upgradeCommand);
}

echo "\nUpgrade complete.\n";
