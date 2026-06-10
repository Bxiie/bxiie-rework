<?php

declare(strict_types=1);

/**
 * Prints the latest rendered domain artifact for manual verification.
 */

use App\Platform\Domains\DomainArtifactRepository;
use App\Support\Database;

$root = dirname(__DIR__, 2);

require $root . '/bootstrap/app.php';

$hostname = $argv[1] ?? 'worker-test.example';

$repository = new DomainArtifactRepository(Database::connect($root));
$artifact = $repository->latestForHostname($hostname);

if (!$artifact) {
    echo "No artifact found for {$hostname}\n";
    exit(0);
}

echo json_encode([
    'id' => $artifact['id'],
    'tenant_id' => $artifact['tenant_id'],
    'hostname' => $artifact['hostname'],
    'artifact_type' => $artifact['artifact_type'],
    'status' => $artifact['status'],
    'created_at' => $artifact['created_at'],
], JSON_PRETTY_PRINT) . PHP_EOL;

echo "\n--- artifact_body ---\n";
echo $artifact['artifact_body'] . "\n";

// End of file.
