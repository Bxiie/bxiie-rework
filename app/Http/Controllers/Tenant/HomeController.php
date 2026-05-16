<?php

declare(strict_types=1);

namespace App\Http\Controllers\Tenant;

use App\Http\Request;
use App\Http\Response;
use App\Platform\Tenancy\TenantContext;
use App\Tenant\Settings\TenantSettingsRepository;

/**
 * Handles tenant public site routes.
 */
final class HomeController
{
    public function __construct(
        private readonly TenantSettingsRepository $settings,
    ) {
    }

    public function home(Request $request, TenantContext $tenant): Response
    {
        $siteTitle = $this->settings->get($tenant, 'site_title', $tenant->name);

        return Response::html($this->layout(
            title: $siteTitle,
            body: <<<HTML
<h1>{$siteTitle}</h1>
<p>Tenant public site resolved for <strong>{$tenant->slug}</strong>.</p>
<p>Host: {$tenant->hostname}</p>
HTML
        ));
    }

    public function portfolio(Request $request, TenantContext $tenant): Response
    {
        return Response::html($this->layout(
            title: "{$tenant->name} | Portfolio",
            body: <<<HTML
<h1>Portfolio</h1>
<p>Portfolio route resolved for tenant <strong>{$tenant->slug}</strong>.</p>
HTML
        ));
    }

    public function about(Request $request, TenantContext $tenant): Response
    {
        return Response::html($this->layout(
            title: "{$tenant->name} | About",
            body: <<<HTML
<h1>About</h1>
<p>About route resolved for tenant <strong>{$tenant->slug}</strong>.</p>
HTML
        ));
    }

    public function contact(Request $request, TenantContext $tenant): Response
    {
        return Response::html($this->layout(
            title: "{$tenant->name} | Contact",
            body: <<<HTML
<h1>Contact</h1>
<p>Contact route resolved for tenant <strong>{$tenant->slug}</strong>.</p>
HTML
        ));
    }

    private function layout(string $title, string $body): string
    {
        return <<<HTML
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>{$title}</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
</head>
<body>
{$body}
</body>
</html>
HTML;
    }
}

// End of file.
