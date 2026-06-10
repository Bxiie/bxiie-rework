<?php

/**
 * Static checks for platform developer access, platform pricing navigation,
 * tenant login links, and background job execution timestamps.
 */

declare(strict_types=1);

$root = dirname(__DIR__, 2);

$checks = [
    'tenant login hides create-account link by context' => [
        'file' => $root . '/app/Http/Controllers/Auth/LoginController.php',
        'must' => [
            'AuthPage::login',
            '$tenant === null',
        ],
    ],
    'auth page create-account link is optional' => [
        'file' => $root . '/app/Http/View/AuthPage.php',
        'must' => [
            'bool $showCreateAccount = true',
            '$createAccountLink = $showCreateAccount ?',
            '{$createAccountLink}',
        ],
    ],
    'marketing developer page is login gated' => [
        'file' => $root . '/app/Http/Controllers/Platform/MarketingController.php',
        'must' => [
            'public function developer(Request $request): Response',
            "if (!$this->currentUser())",
            "Location' => '/login?next=/developer'",
        ],
    ],
    'platform marketing top nav includes pricing' => [
        'file' => $root . '/app/Http/Controllers/Platform/MarketingController.php',
        'must' => [
            '<a{$activeClass('pricing')} href="/pricing">Pricing</a>',
        ],
    ],
    'jobs repository selects execution timestamps' => [
        'file' => $root . '/app/Platform/Jobs/JobAdminRepository.php',
        'must' => [
            'MIN(bja.started_at) AS first_started_at',
            'MAX(bja.finished_at) AS last_finished_at',
            'MAX(bja.created_at) AS last_attempt_at',
        ],
    ],
    'jobs controller renders execution timestamps' => [
        'file' => $root . '/app/Http/Controllers/Platform/Admin/JobsController.php',
        'must' => [
            '<th>Execution</th>',
            'formatJobExecutionTime($job)',
            'private function formatJobExecutionTime(array $job): string',
        ],
    ],
];

foreach ($checks as $label => $config) {
    $content = file_get_contents($config['file']);
    if ($content === false) {
        fwrite(STDERR, "FAILED: {$label}
Missing file: {$config['file']}
");
        exit(1);
    }

    foreach ($config['must'] as $needle) {
        if (!str_contains($content, $needle)) {
            fwrite(STDERR, "FAILED: {$label}
Missing: {$needle}
");
            exit(1);
        }
    }
}

echo "Platform access, tenant login, and jobs UI static checks passed.
";

// End of file.
