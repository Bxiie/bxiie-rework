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
    ) {
    }
}

// End of file.
