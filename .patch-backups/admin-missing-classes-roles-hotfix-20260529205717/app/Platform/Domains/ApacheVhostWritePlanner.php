<?php

declare(strict_types=1);

namespace App\Platform\Domains;

/**
 * Plans where an approved Apache vhost artifact would be written.
 *
 * This class does not write files.
 */
final class ApacheVhostWritePlanner
{
    public function __construct(
        private readonly string $availableSitesPath = '/etc/apache2/sites-available',
    ) {
    }

    public function plan(string $hostname): array
    {
        $hostname = $this->normalizeHostname($hostname);
        $filename = "artsfolio-tenant-{$hostname}.conf";

        return [
            'hostname' => $hostname,
            'target_path' => rtrim($this->availableSitesPath, '/') . '/' . $filename,
            'enable_command' => "a2ensite {$filename}",
            'reload_command' => 'systemctl reload apache2',
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
