<?php

declare(strict_types=1);

namespace App\Platform\Membership;

/**
 * Canonical platform and tenant role slugs.
 */
final class Roles
{
    public const PLATFORM_OWNER = 'owner';
    public const PLATFORM_ADMIN = 'admin';
    public const PLATFORM_SUPPORT = 'support';
    public const PLATFORM_READONLY = 'readonly';

    public const TENANT_OWNER = 'owner';
    public const TENANT_ADMIN = 'admin';
    public const TENANT_EDITOR = 'editor';
    public const TENANT_VIEWER = 'viewer';

    public const PLATFORM_SCOPE = 'platform';
    public const TENANT_SCOPE = 'tenant';
}

// End of file.
