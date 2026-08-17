<?php

declare(strict_types=1);

$path = $argv[1] ?? '';

if ($path === '' || ! is_file($path)) {
    fwrite(STDERR, 'The test suite failed without producing a JUnit report.'.PHP_EOL);
    exit(1);
}

$document = simplexml_load_file($path);

if ($document === false) {
    fwrite(STDERR, 'The test suite produced an unreadable JUnit report.'.PHP_EOL);
    exit(1);
}

$failedCases = $document->xpath('//testcase[error or failure]') ?: [];
$failedCase = $failedCases[0] ?? null;

if ($failedCase === null) {
    fwrite(STDERR, 'The test suite failed without a recorded test error.'.PHP_EOL);
    exit(1);
}

$detail = isset($failedCase->error[0]) ? $failedCase->error[0] : $failedCase->failure[0];
$name = trim((string) $failedCase['name']);
$message = trim((string) $detail);
$message = mb_substr($message, 0, 8000);

fwrite(STDOUT, sprintf(
    '::error title=%s::%s%s',
    escapeWorkflowProperty("Test failed: {$name}"),
    escapeWorkflowData($message),
    PHP_EOL,
));

exit(1);

function escapeWorkflowData(string $value): string
{
    return str_replace(['%', "\r", "\n"], ['%25', '%0D', '%0A'], $value);
}

function escapeWorkflowProperty(string $value): string
{
    return str_replace(['%', "\r", "\n", ':', ','], ['%25', '%0D', '%0A', '%3A', '%2C'], $value);
}
