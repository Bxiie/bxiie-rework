<?php

declare(strict_types=1);

/**
 * Static regression checks for duplicate public email-list signup notifications.
 *
 * Re-submitting an address that is already active on a tenant email list must
 * not queue another tenant signup notification email.
 */

$servicePath = __DIR__ . '/../..//app/Tenant/Signup/EmailSignupService.php';
$repositoryPath = __DIR__ . '/../..//app/Tenant/Signup/EmailSignupRepository.php';

$service = file_get_contents($servicePath);
$repository = file_get_contents($repositoryPath);

if ($service === false) {
    fwrite(STDERR, "Could not read EmailSignupService.php\n");
    exit(1);
}

if ($repository === false) {
    fwrite(STDERR, "Could not read EmailSignupRepository.php\n");
    exit(1);
}

foreach ([
    'service checks existing signup before upsert' => '$existing = $this->signups->findByEmail($tenant, $email);',
    'service defines active signup status guard' => '$alreadyActive',
    'active statuses include pending and confirmed' => "['pending', 'confirmed']",
    'notification is skipped for already-active addresses' => 'if (!$alreadyActive)',
] as $label => $needle) {
    if (!str_contains($service, $needle)) {
        fwrite(STDERR, "Missing duplicate signup notification service check: {$label}\n");
        exit(1);
    }
}

foreach ([
    'repository can find existing signup by email' => 'public function findByEmail(',
    'upsert does not downgrade active confirmed subscribers' => 'WHEN consent_status IN ("pending", "confirmed") THEN consent_status',
    'duplicate upsert resolves existing id' => '$existing = $this->findByEmail($tenant, $email);',
] as $label => $needle) {
    if (!str_contains($repository, $needle)) {
        fwrite(STDERR, "Missing duplicate signup notification repository check: {$label}\n");
        exit(1);
    }
}

echo "Email signup duplicate notification static checks passed.\n";

// End of file.
