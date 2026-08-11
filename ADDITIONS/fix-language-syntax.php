<?php

/**
 * Repair a small set of unambiguous syntax errors in PostfixAdmin language files.
 *
 * This standalone maintenance helper is intentionally not called by
 * languages/language-update.sh or CI.
 */

function php_syntax_error(string $source): ?string
{
    try {
        token_get_all($source, TOKEN_PARSE);
        return null;
    } catch (ParseError $error) {
        return $error->getMessage();
    }
}

function fix_simple_assignment_semicolons(string $source): array
{
    $quotedString = '(?:\'(?:\\\\.|[^\'\\\\])*\'|"(?:\\\\.|[^"\\\\])*")';
    $pattern = '~^(\h*\$PALANG\[' . $quotedString . '\]\h*=\h*' . $quotedString . ')(\h*)((?:#|//).*)?$~';
    $parts = preg_split('/(\r\n|\n|\r)/', $source, -1, PREG_SPLIT_DELIM_CAPTURE);
    $fixed = 0;

    foreach ($parts as $index => $line) {
        if ($index % 2 !== 0 || preg_match($pattern, $line, $matches) !== 1) {
            continue;
        }

        $parts[$index] = $matches[1] . ';' . $matches[2] . ($matches[3] ?? '');
        $fixed++;
    }

    return [implode('', $parts), $fixed];
}

function fix_heredoc_assignment_semicolons(string $source): array
{
    $quotedString = '(?:\'(?:\\\\.|[^\'\\\\])*\'|"(?:\\\\.|[^"\\\\])*")';
    $startPattern = '~^\h*\$PALANG\[' . $quotedString . '\]\h*=\h*<<<\h*' .
        '(?:\'([A-Za-z_][A-Za-z0-9_]*)\'|"([A-Za-z_][A-Za-z0-9_]*)"|([A-Za-z_][A-Za-z0-9_]*))\h*$~';
    $parts = preg_split('/(\r\n|\n|\r)/', $source, -1, PREG_SPLIT_DELIM_CAPTURE);
    $fixed = 0;

    for ($index = 0, $count = count($parts); $index < $count; $index += 2) {
        if (preg_match($startPattern, $parts[$index], $matches) !== 1) {
            continue;
        }

        $identifier = $matches[1] ?: ($matches[2] ?: $matches[3]);
        $quotedIdentifier = preg_quote($identifier, '~');
        $validEndPattern = "~^\\h*$quotedIdentifier;\\h*(?:(?:#|//).*)?$~";
        $missingEndPattern = "~^(\\h*$quotedIdentifier)(\\h*)((?:(?:#|//).*)?)$~";

        for ($end = $index + 2; $end < $count; $end += 2) {
            if (preg_match($validEndPattern, $parts[$end]) === 1) {
                break;
            }
            if (preg_match($missingEndPattern, $parts[$end], $endMatches) === 1) {
                $parts[$end] = $endMatches[1] . ';' . $endMatches[2] . $endMatches[3];
                $fixed++;
                break;
            }
        }
    }

    return [implode('', $parts), $fixed];
}

function apply_safe_fixes(string $source): array
{
    [$source, $simple] = fix_simple_assignment_semicolons($source);
    [$source, $heredoc] = fix_heredoc_assignment_semicolons($source);

    return [$source, [
        'missing assignment semicolon' => $simple,
        'missing heredoc semicolon' => $heredoc,
    ]];
}

function next_backup_filename(string $file): string
{
    $backup = "$file.bak";
    for ($suffix = 2; file_exists($backup); $suffix++) {
        $backup = "$file.bak$suffix";
    }

    return $backup;
}

function repair_language_file(string $file): bool
{
    if (!is_file($file)) {
        fwrite(STDERR, "*** $file: file not found ***\n");
        return false;
    }

    $source = file_get_contents($file);
    if ($source === false) {
        fwrite(STDERR, "*** $file: unable to read file ***\n");
        return false;
    }

    if (php_syntax_error($source) === null) {
        echo "*** $file: syntax is valid; no changes ***\n";
        return true;
    }

    [$fixedSource, $fixCounts] = apply_safe_fixes($source);
    $fixedTotal = array_sum($fixCounts);
    $remainingError = php_syntax_error($fixedSource);
    if ($fixedTotal === 0 || $remainingError !== null) {
        fwrite(STDERR, "*** $file: no safe complete repair; file left unchanged ***\n");
        return false;
    }

    $backup = next_backup_filename($file);
    if (!copy($file, $backup)) {
        fwrite(STDERR, "*** $file: unable to create backup $backup; file left unchanged ***\n");
        return false;
    }

    $written = file_put_contents($file, $fixedSource);
    if ($written !== strlen($fixedSource)) {
        if (!copy($backup, $file)) {
            fwrite(STDERR, "*** $file: write and backup restoration failed ***\n");
        } else {
            fwrite(STDERR, "*** $file: write failed; restored from $backup ***\n");
        }
        return false;
    }

    echo "*** $file: backup saved as $backup ***\n";
    foreach ($fixCounts as $description => $count) {
        if ($count > 0) {
            echo "*** $file: fixed $count $description(s) ***\n";
        }
    }

    return true;
}

function usage(string $script): void
{
    echo "Usage: php $script [language.lang ...]\n";
    echo "If no files are provided, all files in ../languages are checked.\n";
}

$arguments = array_slice($argv, 1);
if ($arguments === ['--help']) {
    usage($argv[0]);
    exit(0);
}
foreach ($arguments as $argument) {
    if (str_starts_with($argument, '-')) {
        fwrite(STDERR, "*** unknown option: $argument ***\n");
        usage($argv[0]);
        exit(2);
    }
}

$files = $arguments ?: (glob(__DIR__ . '/../languages/*.lang') ?: []);
sort($files);
if ($files === []) {
    fwrite(STDERR, "*** no language files found ***\n");
    exit(1);
}

$failed = false;
foreach ($files as $file) {
    if (!repair_language_file($file)) {
        $failed = true;
    }
}

exit($failed ? 1 : 0);
