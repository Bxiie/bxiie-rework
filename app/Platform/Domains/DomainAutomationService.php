<?php

declare(strict_types=1);

namespace App\Platform\Domains;

use App\Platform\Jobs\BackgroundJobRepository;
use App\Platform\Tenancy\TenantContext;
use App\Platform\Tenancy\TenantDomainRepository;

/**
 * Coordinates custom-domain automation requests.
 *
 * The production deployment uses Caddy on-demand TLS. This service queues DNS
 * verification and intentionally avoids creating Apache vhost render jobs.
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

        return $this->queueDnsVerification($tenant, $hostname);
    }

    public function queueDnsVerification(TenantContext $tenant, string $hostname): int
    {
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
        $this->domains->setStatus($hostname, 'active');

        return $this->jobs->enqueue(
            jobType: 'custom_domain.verify_dns',
            payload: [
                'hostname' => $hostname,
            ],
            tenantId: $tenant->tenantId,
        );
    }
}

// End of file.
