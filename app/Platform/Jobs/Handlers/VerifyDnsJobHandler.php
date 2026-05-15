<?php

declare(strict_types=1);

namespace App\Platform\Jobs\Handlers;

use App\Platform\Domains\DnsVerifier;

/**
 * Handles read-only DNS verification jobs for tenant custom domains.
 */
final class VerifyDnsJobHandler
{
    public function __construct(
        private readonly DnsVerifier $verifier,
    ) {
    }

    public function handle(array $payload): string
    {
        $hostname = (string) ($payload['hostname'] ?? '');

        if ($hostname === '') {
            throw new \InvalidArgumentException('Missing hostname in DNS verification payload.');
        }

        $result = $this->verifier->verifyARecord($hostname);

        return json_encode($result, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR);
    }
}

// End of file.
