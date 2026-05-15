<?php

declare(strict_types=1);

namespace App\Platform\Jobs\Handlers;

use App\Platform\Domains\DnsVerifier;
use App\Platform\Jobs\BackgroundJobRepository;
use App\Platform\Tenancy\TenantDomainRepository;

/**
 * Handles read-only DNS verification jobs for tenant custom domains.
 */
final class VerifyDnsJobHandler
{
    public function __construct(
        private readonly DnsVerifier $verifier,
        private readonly TenantDomainRepository $domains,
        private readonly BackgroundJobRepository $jobs,
    ) {
    }

    public function handle(array $payload, ?int $tenantId = null): string
    {
        $hostname = (string) ($payload['hostname'] ?? '');

        if ($hostname === '') {
            throw new \InvalidArgumentException('Missing hostname in DNS verification payload.');
        }

        $result = $this->verifier->verifyARecord($hostname);

        if ($result['verified'] === true) {
            $this->domains->setStatus($hostname, 'dns_verified');

            $this->jobs->enqueue(
                jobType: 'custom_domain.render_vhost',
                payload: [
                    'hostname' => $hostname,
                    'document_root' => '/var/www/artsfolio/public',
                ],
                tenantId: $tenantId,
            );
        } else {
            $this->domains->setStatus($hostname, 'pending_dns');
        }

        return json_encode($result, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR);
    }
}

// End of file.
