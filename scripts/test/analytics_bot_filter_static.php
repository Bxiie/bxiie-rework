<?php

declare(strict_types=1);

/**
 * Static regression coverage for analytics bot/noise filtering.
 *
 * Marker strings are NOWDOC literals so this test can look for PHP source
 * snippets containing quotes and variables without evaluating them.
 */

$root = dirname(__DIR__, 2);
$recorderPath = $root . '/app/Platform/Analytics/AnalyticsRecorder.php';

if (!is_file($recorderPath)) {
    fwrite(STDERR, "Missing AnalyticsRecorder.php.\n");
    exit(1);
}

$recorder = file_get_contents($recorderPath);
if ($recorder === false) {
    fwrite(STDERR, "Could not read AnalyticsRecorder.php.\n");
    exit(1);
}

$required = [
    <<<'MARKER'
private function shouldRecord(Request $request): bool
MARKER,
    <<<'MARKER'
if ($request->method() !== 'GET')
MARKER,
    <<<'MARKER'
BOT_USER_AGENT_FRAGMENTS
MARKER,
    <<<'MARKER'
IGNORED_PATH_PREFIXES
MARKER,
    <<<'MARKER'
IGNORED_EXACT_PATHS
MARKER,
    <<<'MARKER'
'curl/'
MARKER,
    <<<'MARKER'
'petalbot'
MARKER,
    <<<'MARKER'
'amazonbot'
MARKER,
    <<<'MARKER'
'mj12bot'
MARKER,
    <<<'MARKER'
'claudebot'
MARKER,
    <<<'MARKER'
'bingbot'
MARKER,
    <<<'MARKER'
'applebot'
MARKER,
    <<<'MARKER'
'gptbot'
MARKER,
    <<<'MARKER'
'ccbot'
MARKER,
    <<<'MARKER'
'ahrefsbot'
MARKER,
    <<<'MARKER'
'semrushbot'
MARKER,
    <<<'MARKER'
'python-requests'
MARKER,
    <<<'MARKER'
'go-http-client'
MARKER,
    <<<'MARKER'
'meta-externalagent'
MARKER,
    <<<'MARKER'
'facebookexternalhit'
MARKER,
    <<<'MARKER'
'panscient'
MARKER,
    <<<'MARKER'
'aiwebindex'
MARKER,
    <<<'MARKER'
'/admin'
MARKER,
    <<<'MARKER'
'/platform/admin'
MARKER,
    <<<'MARKER'
'/assets/'
MARKER,
    <<<'MARKER'
'/media/'
MARKER,
    <<<'MARKER'
'/favicon.ico'
MARKER,
    <<<'MARKER'
$userAgent === ''
MARKER,
    <<<'MARKER'
str_contains($normalizedUserAgent, $fragment)
MARKER,
    <<<'MARKER'
if (!$this->shouldRecord($request))
MARKER,
];

foreach ($required as $needle) {
    if (!str_contains($recorder, $needle)) {
        fwrite(STDERR, "Missing analytics bot filter marker: {$needle}\n");
        exit(1);
    }
}

echo "Analytics bot/noise filtering static checks passed.\n";

// End of file.
