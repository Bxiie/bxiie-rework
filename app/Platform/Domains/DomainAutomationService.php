<?php

declare(strict_types=1);

namespace App\Platform\Domains;

use App\Platform\Jobs\BackgroundJobRepository;
use App\Platform\Tenancy\TenantContext;
use App\Platform\Tenancy\TenantDomainRepository;

/**
 * Coordinates custom-domain automation requests.
 *
 * This service queues background jobs only. It does not mutate Apache,
 * reload services, or request TLS certificates directly.
 */
final class DomainAutomationService
{
    public function __construct(
        private readonly TenantDomainRepository $domains,
        private readonly BackgroundJobRepository $jobs,
    ) {
    }

    public function requestCustomDomain(TenantContext $tenant, string $hostname): int
    {
        $this->domains->addDomain(
            tenantId: $tenant->tenantId,
            hostname: $hostname,
            domainType: 'custom',
            status: 'pending_dns',
            isPrimary: false,
        );

        return $this->jobs->enqueue(
            jobType: 'custom_domain.verify_dns',
            payload: [
                'hostname' => $hostname,
            ],
            tenantId: $tenant->tenantId,
        );
    }

    public function queueVhostRender(TenantContext $tenant, string $hostname): int
    {
        $this->domains->setStatus($hostname, 'vhost_pending');

        return $this->jobs->enqueue(
            jobType: 'custom_domain.render_vhost',
            payload: [
                'hostname' => $hostname,
                'document_root' => '/var/www/artsfolio/public',
            ],
            tenantId: $tenant->tenantId,
        );
    }
}

// End of file.
