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
    if ($content === false || !str_contains($content, $needle)) {
        fwrite(STDERR, "FAILED: {$label}
Missing: {$needle}
");
        exit(1);
    }
}

$authPage = $root . '/app/Http/View/AuthPage.php';
$loginController = $root . '/app/Http/Controllers/Auth/LoginController.php';
$marketingController = $root . '/app/Http/Controllers/Platform/MarketingController.php';
$helpController = $root . '/app/Http/Controllers/Platform/HelpController.php';
$jobsController = $root . '/app/Http/Controllers/Platform/Admin/JobsController.php';
$jobsRepository = $root . '/app/Platform/Jobs/JobAdminRepository.php';

requireNeedle('developer resources are gated', $helpController, 'isDeveloperResourceRequest');
requireNeedle('developer resources are gated', $helpController, '?array $currentUser = null');
requireNeedle('developer resources are gated', $helpController, "['Location' => '/login']");

requireNeedle('platform pricing appears in top navigation', $marketingController, 'href="/pricing"');
requireNeedle('platform pricing appears in top navigation', $marketingController, 'Pricing');

requireNeedle('auth page login supports optional create-account link', $authPage, 'bool $showCreateAccount = true');
requireNeedle('auth page login supports optional create-account link', $authPage, '$createAccountLink');
requireNeedle('tenant login suppresses create-account link', $loginController, '$tenant === null');

requireNeedle('jobs repository selects execution timestamps', $jobsRepository, 'first_started_at');
requireNeedle('jobs repository selects execution timestamps', $jobsRepository, 'last_finished_at');
requireNeedle('jobs controller renders execution timestamps', $jobsController, 'formatJobExecutionTime($job)');
requireNeedle('jobs controller renders execution timestamps', $jobsController, 'private function formatJobExecutionTime(array $job): string');

$jobsContent = (string) file_get_contents($jobsController);
if (!str_contains($jobsContent, '<th>Execution</th>') && !str_contains($jobsContent, '<th>Execution time</th>')) {
    fwrite(STDERR, "FAILED: jobs controller renders execution timestamps
Missing execution table header
");
    exit(1);
}

echo "Platform access, tenant login, and jobs UI static checks passed.
";

// End of file.
