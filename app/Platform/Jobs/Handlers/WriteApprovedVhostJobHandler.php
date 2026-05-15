<?php

declare(strict_types=1);

namespace App\Platform\Jobs\Handlers;

use App\Platform\Domains\ApacheVhostWritePlanner;
use App\Platform\Domains\DomainArtifactRepository;

/**
 * Handles dry-run planning for writing an approved Apache vhost artifact.
 *
 * This handler does not write files, enable sites, reload Apache, or invoke Certbot.
 */
final class WriteApprovedVhostJobHandler
{
    public function __construct(
        private readonly DomainArtifactRepository $artifacts,
        private readonly ApacheVhostWritePlanner $planner,
    ) {
    }

    public function handle(array $payload): string
    {
        $hostname = (string) ($payload['hostname'] ?? '');

        if ($hostname === '') {
            throw new \InvalidArgumentException('Missing hostname in write approved vhost payload.');
        }

        $artifact = $this->artifacts->latestApprovedForHostname($hostname);

        if (!$artifact) {
            throw new \RuntimeException("No approved vhost artifact found for {$hostname}.");
        }

        $plan = $this->planner->plan($hostname);

        return json_encode([
            'dry_run' => true,
            'hostname' => $hostname,
            'artifact_id' => (int) $artifact['id'],
            'target_path' => $plan['target_path'],
            'enable_command' => $plan['enable_command'],
            'reload_command' => $plan['reload_command'],
            'artifact_body' => $artifact['artifact_body'],
        ], JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR);
    }
}

// End of file.
