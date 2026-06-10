<?php

declare(strict_types=1);

namespace App\Platform\Domains;

/**
 * Performs read-only DNS checks for tenant custom domains.
 */
final class DnsVerifier
{
    public function __construct(
        private readonly array $expectedIpv4Addresses,
    ) {
    }

    public function verifyARecord(string $hostname): array
    {
        $hostname = $this->normalizeHostname($hostname);
        $records = dns_get_record($hostname, DNS_A);

        $actual = [];

        foreach ($records as $record) {
            if (isset($record['ip'])) {
                $actual[] = $record['ip'];
            }
        }

        $matches = array_values(array_intersect($actual, $this->expectedIpv4Addresses));

        return [
            'hostname' => $hostname,
            'expected_ipv4' => $this->expectedIpv4Addresses,
            'actual_ipv4' => $actual,
            'matches' => $matches,
            'verified' => count($matches) > 0,
        ];
    }

    private function normalizeHostname(string $hostname): string
    {
        $hostname = strtolower(trim($hostname));

        if (str_contains($hostname, ':')) {
            $hostname = explode(':', $hostname, 2)[0];
        }

        return rtrim($hostname, '.');
    }
}

// End of file.
