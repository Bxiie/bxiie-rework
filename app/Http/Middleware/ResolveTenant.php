<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Http\Request;
use App\Platform\Tenancy\TenantContext;
use App\Platform\Tenancy\TenantResolver;

/**
 * Resolves the current tenant from the request host.
 */
final class ResolveTenant
{
    public function __construct(
        private readonly TenantResolver $resolver,
    ) {
    }

    public function handle(Request $request): ?TenantContext
    {
        return $this->resolver->resolveFromHost($request->host());
    }
}

// End of file.
