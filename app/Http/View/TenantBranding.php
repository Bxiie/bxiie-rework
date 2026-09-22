<?php

declare(strict_types=1);

namespace App\Http\View;

use App\Platform\Tenancy\TenantContext;
use App\Tenant\Settings\TenantSettingsRepository;

/** Shared public-site footer attribution. */
final class TenantBranding
{
    public static function render(TenantContext $tenant, TenantSettingsRepository $settings): string
    {
        if ($settings->get($tenant, TenantSettingsRepository::ARTSFOLIO_BRANDING) === '0') {
            return '';
        }

        return '<span class="tenant-powered-by"><a href="https://artsfol.io/" target="_blank" rel="noopener noreferrer" aria-label="Created with ArtsFolio (opens in a new window)">Created with ArtsFolio</a></span>';
    }
}
