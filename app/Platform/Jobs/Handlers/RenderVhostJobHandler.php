<?php

declare(strict_types=1);

namespace App\Platform\Jobs\Handlers;

use App\Platform\Domains\ApacheVhostRenderer;
use App\Platform\Domains\DomainArtifactRepository;
use App\Platform\Tenancy\TenantDomainRepository;

/**
 * Handles dry-run Apache vhost rendering jobs.
 */
final class RenderVhostJobHandler
{
    public function __construct(
        private readonly ApacheVhostRenderer $renderer,
        private readonly DomainArtifactRepository $artifacts,
        private readonly TenantDomainRepository $domains,
    ) {
    }

    public function handle(array $payload, ?int $tenantId = null): string
    {
        if ($tenantId === null) {
            throw new \InvalidArgumentException('Missing tenant ID for render vhost job.');
        }

        $hostname = (string) ($payload['hostname'] ?? '');
        $documentRoot = (string) ($payload['document_root'] ?? '/var/www/artsfolio/public');

        if ($hostname === '') {
            throw new \InvalidArgumentException('Missing hostname in render vhost payload.');
        }

        $rendered = $this->renderer->renderHttpVhost($hostname, $documentRoot);

        $artifactId = $this->artifacts->create(
            tenantId: $tenantId,
            hostname: $hostname,
            artifactType: 'apache_http_vhost',
            artifactBody: $rendered,
        );

        $this->domains->setStatus($hostname, 'vhost_pending');

        return "Rendered vhost artifact {$artifactId} for {$hostname}.\n\n{$rendered}";
    }
}

// End of file.
