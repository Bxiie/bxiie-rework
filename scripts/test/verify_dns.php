<?php

declare(strict_types=1);

/**
 * Manual verification script for read-only DNS A-record checks.
 */

use App\Platform\Domains\DnsVerifier;

$root = dirname(__DIR__, 2);

require $root . '/bootstrap/app.php';

$hostname = $argv[1] ?? 'bxiie.com';
$expected = $argv[2] ?? (getenv('ARTSFOLIO_EXPECTED_IPV4') ?: '127.0.0.1');

$expectedIps = array_filter(array_map('trim', explode(',', $expected)));
$verifier = new DnsVerifier($expectedIps);

echo json_encode(
    $verifier->verifyARecord($hostname),
    JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR
) . PHP_EOL;

// End of file.
