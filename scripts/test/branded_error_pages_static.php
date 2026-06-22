<?php

/**
 * Static and render regression checks for branded browser-facing error pages.
 */

declare(strict_types=1);

use App\Http\View\ErrorPage;
use App\Platform\Tenancy\TenantContext;

$root = dirname(__DIR__, 2);
require $root . '/bootstrap/app.php';

$filesAndMarkers = [
    'front controller exception handler' => [$root . '/app/Http/AppKernel.php', 'ErrorPage::sendException($e)'],
    'front controller fatal handler' => [$root . '/public/index.php', 'ErrorPage::sendFatal($error)'],
    'front controller error route' => [$root . '/app/Http/Routes/tenant.php', "/error/{code}"],
    'router generic not found' => [$root . '/app/Http/Router.php', 'return Response::notFound();'],
    'response generic error helper' => [$root . '/app/Http/Response.php', 'public static function error(int $statusCode'],
    'apache error document fallback' => [$root . '/public/.htaccess', 'ErrorDocument 404 /error/404'],
    'branded error css' => [$root . '/public/assets/error.css', '.error-brand'],
];

foreach ($filesAndMarkers as $label => [$file, $needle]) {
    $source = is_file($file) ? (string) file_get_contents($file) : '';
    if ($source === '' || !str_contains($source, $needle)) {
        fwrite(STDERR, "Missing branded error marker for {$label}: {$needle}\n");
        exit(1);
    }
}

$forbiddenLeakMarkers = [
    'No route for',
    '<h1>Application error</h1>',
    'Stack trace',
];

foreach ([$root . '/app/Http/Router.php', $root . '/public/index.php', $root . '/app/Http/AppKernel.php', $root . '/app/Http/Routes/tenant.php', $root . '/app/Http/Routes/platform.php', $root . '/app/Http/View/ErrorPage.php'] as $file) {
    $source = (string) file_get_contents($file);
    foreach ($forbiddenLeakMarkers as $needle) {
        if (str_contains($source, $needle)) {
            fwrite(STDERR, "Raw error marker still present in {$file}: {$needle}\n");
            exit(1);
        }
    }
}

$GLOBALS['artsfolio_tenant_context'] = null;
$GLOBALS['artsfolio_platform_context'] = true;
$platformHtml = ErrorPage::status(404);
if (!str_contains($platformHtml, 'ArtsFolio') || !str_contains($platformHtml, '/assets/error.css')) {
    fwrite(STDERR, "Platform error page did not render expected ArtsFolio branding.\n");
    exit(1);
}

$GLOBALS['artsfolio_tenant_context'] = new TenantContext(
    tenantId: 1,
    tenantUuid: 'test-tenant-uuid',
    slug: 'bxiie',
    name: 'James Payne Art',
    hostname: 'bxiie.artsfol.io',
    domainType: 'platform_subdomain',
    isPrimaryDomain: true,
    status: 'active',
);
$GLOBALS['artsfolio_platform_context'] = false;
$tenantHtml = ErrorPage::status(404);
if (!str_contains($tenantHtml, 'James Payne Art') || !str_contains($tenantHtml, 'Powered by ArtsFolio')) {
    fwrite(STDERR, "Tenant error page did not render expected tenant branding.\n");
    exit(1);
}

if (str_contains($tenantHtml, 'No route for') || str_contains($tenantHtml, 'Application error')) {
    fwrite(STDERR, "Tenant error page leaked raw routing/application copy.\n");
    exit(1);
}

echo "Branded error page static checks passed.\n";

// End of file.
