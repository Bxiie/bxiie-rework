#!/usr/bin/php
<?php

declare(strict_types=1);

use App\Platform\Auth\SignupPostRegistrationMailer;
use App\Platform\Email\EmailOutboxRepository;
use App\Support\Database;

$root = dirname(__DIR__, 2);
require $root . '/bootstrap/app.php';

$email = strtolower(trim((string) ($argv[1] ?? '')));
$tenantSlug = isset($argv[2]) ? trim((string) $argv[2]) : null;
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    fwrite(STDERR, "Usage: php scripts/admin/queue_signup_post_registration_email.php user@example.com [tenant-slug]\n");
    exit(2);
}

$pdo = Database::connect($root);
$mailer = new SignupPostRegistrationMailer($pdo, new EmailOutboxRepository($pdo));
try {
    $result = $mailer->queueForEmail($email, $tenantSlug);
} catch (Throwable $exception) {
    fwrite(STDERR, '[FAIL] ' . $exception->getMessage() . "\n");
    exit(1);
}

echo '[PASS] Verification: ' . ($result['verification'] ? 'queued' : 'already verified or pending') . "\n";
echo '[PASS] Welcome: ' . ($result['welcome'] ? 'queued' : 'already pending') . "\n";

// End of file.
