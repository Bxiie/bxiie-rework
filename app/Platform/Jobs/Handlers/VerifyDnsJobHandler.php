<?php

declare(strict_types=1);

namespace App\Platform\Jobs\Handlers;

use App\Platform\Domains\DnsVerifier;
use App\Platform\Jobs\BackgroundJobRepository;
use App\Platform\Tenancy\TenantDomainRepository;

/**
 * Handles DNS verification jobs for tenant custom domains.
 *
 * With Caddy on-demand TLS there is no Apache vhost artifact to render. Once
 * the expected A record is present, the domain is marked active so the tenant
 * resolver and Caddy ask endpoint can authorize the hostname.
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

        try {
            $result = $this->verifier->verifyARecord($hostname);
        } catch (\Throwable $e) {
            $this->domains->recordDnsVerificationResult($hostname, ['hostname' => $hostname, 'verified' => false], $e->getMessage());
            throw $e;
        }

        if ($result['verified'] === true) {
            $this->domains->setStatus($hostname, 'active');
            $result['domain_status'] = 'active';
            $result['tls_mode'] = 'caddy_on_demand';
        } else {
            $this->domains->setStatus($hostname, 'pending_dns');
            $result['domain_status'] = 'pending_dns';
        }

        $this->domains->recordDnsVerificationResult($hostname, $result);

        return json_encode($result, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR);
    }
}

// End of file.
