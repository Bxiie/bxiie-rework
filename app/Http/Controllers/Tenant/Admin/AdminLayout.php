<?php

declare(strict_types=1);

namespace App\Http\Controllers\Tenant\Admin;

/**
 * Compatibility facade for tenant-admin controllers that still reference the
 * historical namespace-local AdminLayout class.
 *
 * Rendering is delegated to the canonical view-layer layout, which resolves
 * the current tenant from the request host and applies that tenant's branding,
 * identity, navigation, and permissions.
 */
final class AdminLayout
{
    public static function render(...$args): string
    {
        return \App\Http\View\AdminLayout::render(...$args);
    }

    public static function renderShell(
        string $title,
        string $body,
        string $active = 'dashboard',
    ): string {
        return \App\Http\View\AdminLayout::render(
            title: $title,
            body: $body,
            active: $active,
        );
    }

    public static function escape(string $value): string
    {
        return \App\Http\View\AdminLayout::escape($value);
    }
}

// End of file.
