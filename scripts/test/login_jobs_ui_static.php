<?php

/**
 * Static checks for tenant login and background jobs UI behavior.
 */

declare(strict_types=1);

$root = dirname(__DIR__, 2);

$authPage = (string) file_get_contents($root . '/app/Http/View/AuthPage.php');
$loginController = (string) file_get_contents($root . '/app/Http/Controllers/Auth/LoginController.php');
$jobsController = (string) file_get_contents($root . '/app/Http/Controllers/Platform/Admin/JobsController.php');
$jobsRepository = (string) file_get_contents($root . '/app/Platform/Jobs/JobAdminRepository.php');

if (!str_contains($jobsController, '<th>Execution</th>') && !str_contains($jobsController, '<th>Execution time</th>')) {
    fwrite(STDERR, "FAILED: jobs controller renders execution timestamps\nMissing execution table header\n");
    exit(1);
}

$checks = [
    'auth page login supports optional create-account link' => [
        $authPage,
        [
            'bool $showCreateAccount = true',
            '$createAccountLink = $showCreateAccount ?',
            '{$createAccountLink}',
        ],
    ],
    'tenant login suppresses create-account link' => [
        $loginController,
        [
            'AuthPage::login',
            '$tenant === null',
        ],
    ],
    'jobs repository selects execution timestamps' => [
        $jobsRepository,
        [
            'MIN(bja.started_at) AS first_started_at',
            'MAX(bja.finished_at) AS last_finished_at',
            'MAX(bja.created_at) AS last_attempt_at',
        ],
    ],
    'jobs controller renders execution timestamps' => [
        $jobsController,
        [
            'formatJobExecutionTime($job)',
            'private function formatJobExecutionTime(array $job): string',
        ],
    ],
];

foreach ($checks as $label => [$content, $needles]) {
    foreach ($needles as $needle) {
        if (!str_contains($content, $needle)) {
            fwrite(STDERR, "FAILED: {$label}\nMissing: {$needle}\n");
            exit(1);
        }
    }
}

echo "Login and background jobs UI static checks passed.\n";

// End of file.
