<?php

declare(strict_types=1);

namespace App\Http\Controllers\Tenant\Admin;

use App\Http\View\AdminLayout as PlatformAwareAdminLayout;

/**
 * Backward-compatible tenant-admin layout shim.
 *
 * Older tenant controllers imported this class directly. Delegate to the
 * platform-aware layout so tenant hosts still receive the real tenant shell
 * and the shared tenant-admin navigation.
 */
final class AdminLayout
{
    public static function render(...$args): string
    {
        return PlatformAwareAdminLayout::render(...$args);
    }

    public static function renderShell(string $title, string $body, string $active = 'dashboard'): string
    {
        return PlatformAwareAdminLayout::render(title: $title, body: $body, active: $active);
    }

    public static function escape(string $value): string
    {
        return PlatformAwareAdminLayout::escape($value);
    }
}

// End of file.
