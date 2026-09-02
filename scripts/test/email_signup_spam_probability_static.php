<?php

declare(strict_types=1);

// Regression coverage for the email-signup spam-probability heuristic added
// 2026-09-01. Checks the full chain: migration column/index, SpamScoreService
// itself, the repository plumbing that scores only genuinely new addresses,
// the two creation paths (public signup, tenant admin CSV import) that must
// call the scorer, and the admin list column that displays it.

$root = dirname(__DIR__, 2);
$failures = [];

$requiredFiles = [
    'app/Tenant/Signup/SpamScoreService.php',
    'database/migrations/0069_email_signup_spam_probability.sql',
];
foreach ($requiredFiles as $file) {
    if (!is_file($root . '/' . $file)) {
        $failures[] = "Missing {$file}";
    }
}

$migration = file_get_contents($root . '/database/migrations/0069_email_signup_spam_probability.sql') ?: '';
foreach (['ADD COLUMN spam_probability', 'idx_email_signups_ip_created'] as $needle) {
    if (!str_contains($migration, $needle)) {
        $failures[] = "Migration 0069 missing {$needle}";
    }
}

$scorer = file_get_contents($root . '/app/Tenant/Signup/SpamScoreService.php') ?: '';
foreach ([
    'function score(',
    'recentSignupCountForIp',
    'DISPOSABLE_DOMAINS',
] as $needle) {
    if (!str_contains($scorer, $needle)) {
        $failures[] = "SpamScoreService missing {$needle}";
    }
}

$repo = file_get_contents($root . '/app/Tenant/Signup/EmailSignupRepository.php') ?: '';
foreach (['?int $spamProbability = null', 'spam_probability', 'function recentSignupCountForIp'] as $needle) {
    if (!str_contains($repo, $needle)) {
        $failures[] = "EmailSignupRepository missing {$needle}";
    }
}
// spam_probability must never be in the ON DUPLICATE KEY UPDATE clause, or a
// repeat signup by an already-known address would overwrite its original score.
if (preg_match('/ON DUPLICATE KEY UPDATE(.*?)\'\s*\)/s', $repo, $onDuplicateMatch) === 1
    && str_contains($onDuplicateMatch[1], 'spam_probability')) {
    $failures[] = 'EmailSignupRepository::upsert() must not update spam_probability on duplicate-key conflict.';
}

$service = file_get_contents($root . '/app/Tenant/Signup/EmailSignupService.php') ?: '';
foreach (['?SpamScoreService $spamScore = null', 'spamProbability:'] as $needle) {
    if (!str_contains($service, $needle)) {
        $failures[] = "EmailSignupService missing {$needle}";
    }
}

$kernel = file_get_contents($root . '/app/Http/AppKernel.php') ?: '';
if (!str_contains($kernel, 'new SpamScoreService(')) {
    $failures[] = 'AppKernel does not wire SpamScoreService into the public signup path.';
}

$controller = file_get_contents($root . '/app/Http/Controllers/Tenant/Admin/EmailSignupsController.php') ?: '';
foreach ([
    '?SpamScoreService $spam = null',
    'function formatSpamProbability',
    'Spam probability',
    'spam_probability',
] as $needle) {
    if (!str_contains($controller, $needle)) {
        $failures[] = "EmailSignupsController missing {$needle}";
    }
}

$tenantRoutes = file_get_contents($root . '/app/Http/Routes/tenant.php') ?: '';
if (!str_contains($tenantRoutes, 'new SpamScoreService(new EmailSignupRepository($pdo))')) {
    $failures[] = 'tenant.php does not wire SpamScoreService into the CSV import route.';
}

if ($failures !== []) {
    fwrite(STDERR, implode(PHP_EOL, $failures) . PHP_EOL);
    exit(1);
}

echo "Email signup spam probability static checks passed.\n";

// End of file.
