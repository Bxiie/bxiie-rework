<?php

declare(strict_types=1);

/**
 * Approves the latest rendered domain artifact for a hostname.
 */

use App\Platform\Domains\DomainArtifactRepository;
use App\Support\Database;

$root = dirname(__DIR__, 2);

require $root . '/bootstrap/app.php';

$hostname = $argv[1] ?? null;

if (!$hostname) {
    fwrite(STDERR, "Usage: php scripts/test/approve_domain_artifact.php artifact-test.example\n");
    exit(1);
}

$repository = new DomainArtifactRepository(Database::connect($root));
$artifact = $repository->latestForHostname($hostname);

if (!$artifact) {
    fwrite(STDERR, "No artifact found for {$hostname}\n");
    exit(1);
}

$repository->approve((int) $artifact['id']);

echo "Approved artifact {$artifact['id']} for {$hostname}\n";

// End of file.
