<?php

declare(strict_types=1);

namespace App\Http\Controllers\Tenant;

use App\Http\Request;
use App\Http\Response;
use App\Platform\Tenancy\TenantContext;
use App\Tenant\Settings\TenantSettingsRepository;

final class TenantCssController
{
    public function __construct(
        private readonly TenantSettingsRepository $settings,
    ) {
    }

    public function show(Request $request, TenantContext $tenant): Response
    {
        $css = $this->settings->get($tenant, 'tenant_css', '');

        return new Response($css, 200, [
            'Content-Type' => 'text/css; charset=utf-8',
            'Cache-Control' => 'private, max-age=60',
        ]);
    }
}

// End of file.
