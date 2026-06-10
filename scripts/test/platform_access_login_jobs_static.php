<?php

/**
 * Static checks for platform developer-resource access, platform pricing nav,
 * tenant login create-account suppression, and background job execution times.
 */

declare(strict_types=1);

$root = dirname(__DIR__, 2);

function requireNeedle(string $label, string $file, string $needle): void
{
    $content = file_get_contents($file);
    if ($content === false) {
        fwrite(STDERR, "FAILED: {$label}\nMissing file: {$file}\n");
        exit(1);
    }

    if (!str_contains($content, $needle)) {
        fwrite(STDERR, "FAILED: {$label}\nMissing: {$needle}\n");
        exit(1);
    }
}

function forbidNeedle(string $label, string $file, string $needle): void
{
    $content = file_get_contents($file);
    if ($content === false) {
        fwrite(STDERR, "FAILED: {$label}\nMissing file: {$file}\n");
        exit(1);
    }

    if (str_contains($content, $needle)) {
        fwrite(STDERR, "FAILED: {$label}\nForbidden stale content: {$needle}\n");
        exit(1);
    }
}

$authPage = $root . '/app/Http/View/AuthPage.php';
$loginController = $root . '/app/Http/Controllers/Auth/LoginController.php';
$marketingController = $root . '/app/Http/Controllers/Platform/MarketingController.php';
$helpController = $root . '/app/Http/Controllers/Platform/HelpController.php';
$jobsController = $root . '/app/Http/Controllers/Platform/Admin/JobsController.php';
$jobsRepository = $root . '/app/Platform/Jobs/JobAdminRepository.php';

requireNeedle('developer resources are gated', $helpController, 'developerResources');
requireNeedle('developer resources are gated', $helpController, 'currentUser');
requireNeedle('developer resources are gated', $helpController, 'Location');
requireNeedle('developer resources are gated', $helpController, '/login');

requireNeedle('platform pricing appears in top navigation', $marketingController, 'href="/pricing"');
requireNeedle('platform pricing appears in top navigation', $marketingController, '>Pricing<');

requireNeedle('auth page login supports optional create-account link', $authPage, 'bool $showCreateAccount = true');
requireNeedle('auth page login supports optional create-account link', $authPage, '$createAccountLink');
requireNeedle('tenant login suppresses create-account link', $loginController, '$tenant === null');

forbidNeedle('tenant login does not hard-code create-account CTA', $loginController, 'Create an account</a>');
forbidNeedle('tenant login does not hard-code create-account CTA', $loginController, 'href="/signup">Create an account');

requireNeedle('jobs repository selects execution timestamps', $jobsRepository, 'first_started_at');
requireNeedle('jobs repository selects execution timestamps', $jobsRepository, 'last_finished_at');
requireNeedle('jobs controller renders execution timestamps', $jobsController, 'formatJobExecutionTime($job)');
requireNeedle('jobs controller renders execution timestamps', $jobsController, 'private function formatJobExecutionTime(array $job): string');

$jobsContent = (string) file_get_contents($jobsController);
if (!str_contains($jobsContent, '<th>Execution</th>') && !str_contains($jobsContent, '<th>Execution time</th>')) {
    fwrite(STDERR, "FAILED: jobs controller renders execution timestamps\nMissing execution table header\n");
    exit(1);
}

echo "Platform access, tenant login, and jobs UI static checks passed.\n";

// End of file.
