<?php

declare(strict_types=1);

namespace App\Platform\Tenancy;

/**
 * Immutable request-scoped tenant context.
 */
final class TenantContext
{
    public function __construct(
        public readonly int $tenantId,
        public readonly string $tenantUuid,
        public readonly string $slug,
        public readonly string $name,
        public readonly string $hostname,
        public readonly string $domainType,
        public readonly bool $isPrimaryDomain,
        public readonly string $status = 'active',
    ) {
    }

    public function isSuspended(): bool
    {
        return in_array($this->status, ['suspended', 'deleted'], true);
    }
}

// End of file.
