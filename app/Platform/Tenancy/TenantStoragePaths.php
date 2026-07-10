<?php

declare(strict_types=1);

namespace App\Platform\Tenancy;

/**
 * Builds tenant-isolated storage paths for media and private tenant artifacts.
 */
final class TenantStoragePaths
{
    public function __construct(
        private readonly TenantContext $tenant,
    ) {
        if (!preg_match('/^[a-z0-9](?:[a-z0-9-]{1,61}[a-z0-9])$/', $this->tenant->slug)) {
            throw new \InvalidArgumentException('Invalid tenant slug for storage path construction.');
        }
    }

    public function originalsPath(string $filename): string
    {
        return $this->build('originals', $filename);
    }

    public function derivativesPath(string $filename): string
    {
        return $this->build('derivatives', $filename);
    }

    public function importsPath(string $filename): string
    {
        return $this->build('imports', $filename);
    }

    public function privatePath(string $filename): string
    {
        return $this->build('private', $filename);
    }

    private function build(string $area, string $filename): string
    {
        $safeFilename = basename($filename);

        return sprintf(
            'tenants/%s/%s/%s',
            $this->tenant->slug,
            $area,
            $safeFilename
        );
    }
}

// End of file.
