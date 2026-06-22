<?php

declare(strict_types=1);

namespace App\Http;

use App\Http\Controllers\Api\MeController;
use App\Http\Controllers\Api\AdminApiController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Api\TenantMeController;
use App\Http\Controllers\Auth\PasswordAuthController;
use App\Http\Controllers\Auth\OAuthController;
use App\Http\Controllers\Auth\TenantSessionBridgeController;
use App\Http\Controllers\Platform\Admin\DashboardController as PlatformAdminDashboardController;
use App\Http\Controllers\Platform\Admin\PricingController as PlatformAdminPricingController;
use App\Http\Controllers\Platform\Admin\SalesController as PlatformAdminSalesController;
use App\Http\Controllers\Platform\Admin\SalesAnalyticsController as PlatformAdminSalesAnalyticsController;
use App\Http\Controllers\Platform\Admin\SettingsController as PlatformAdminSettingsController;
use App\Http\Controllers\Platform\Admin\SignupCodesController as PlatformAdminSignupCodesController;
use App\Http\Controllers\Platform\Admin\RoutesController as PlatformAdminRoutesController;
use App\Http\Controllers\Platform\Admin\EmailOutboxController as PlatformAdminEmailOutboxController;
use App\Http\Controllers\Platform\Admin\DomainsController as PlatformAdminDomainsController;
use App\Http\Controllers\Tenant\Admin\DomainsController as TenantAdminDomainsController;
use App\Http\Controllers\Platform\Admin\JobsController as PlatformAdminJobsController;
use App\Http\Controllers\Platform\Admin\WorkersController as PlatformAdminWorkersController;
use App\Http\Controllers\Platform\Admin\AuditLogController as PlatformAdminAuditLogController;
use App\Http\Controllers\Platform\Admin\TenantsController as PlatformAdminTenantsController;
use App\Http\Controllers\Platform\Admin\ScaleTenantsController as PlatformAdminScaleTenantsController;
use App\Http\Controllers\Platform\Admin\UsersController as PlatformAdminUsersController;
use App\Http\Controllers\Platform\HelpController as PlatformHelpController;
use App\Http\Controllers\Platform\Admin\StatsController as PlatformAdminStatsController;
use App\Http\Controllers\Platform\Admin\ContactMessagesController as PlatformAdminContactMessagesController;
use App\Http\Controllers\Platform\HomeController as PlatformHomeController;
use App\Http\Controllers\Platform\StripeWebhookController;
use App\Http\Controllers\Platform\MarketingController;
use App\Http\Controllers\Platform\DirectoryController;
use App\Http\Controllers\Platform\PricingController;
use App\Http\Controllers\Platform\HelpController;
use App\Http\Controllers\Platform\PlatformCssController;
use App\Http\Controllers\Platform\CaddyAskController;
use App\Http\Controllers\Platform\SignupController as PlatformSignupController;
use App\Http\Controllers\Tenant\HomeController as TenantHomeController;
use App\Http\Controllers\Tenant\SalesController as TenantSalesController;
use App\Http\Controllers\Tenant\TenantCssController;
use App\Http\Controllers\Tenant\MediaController as TenantMediaController;
use App\Http\Controllers\Tenant\SignupController;
use App\Http\Controllers\Tenant\Admin\DashboardController as TenantAdminDashboardController;
use App\Http\Controllers\Tenant\Admin\DiscoverySettingsController as TenantAdminDiscoverySettingsController;
use App\Http\Controllers\Tenant\Admin\StatsController as TenantAdminStatsController;
use App\Http\Controllers\Tenant\Admin\GettingStartedController as TenantAdminGettingStartedController;
use App\Http\Controllers\Tenant\Admin\ArtworkUploadController as TenantAdminArtworkUploadController;
use App\Http\Controllers\Tenant\Admin\ArtworksController as TenantAdminArtworksController;
use App\Http\Controllers\Tenant\Admin\ArtworkPlacementController as TenantAdminArtworkPlacementController;
use App\Http\Controllers\Tenant\Admin\ContentController as TenantAdminContentController;
use App\Http\Controllers\Tenant\Admin\EventsController as TenantAdminEventsController;
use App\Http\Controllers\Tenant\Admin\PortfolioSectionsController as TenantAdminPortfolioSectionsController;
use App\Http\Controllers\Tenant\Admin\SettingsController as TenantAdminSettingsController;
use App\Http\Controllers\Tenant\Admin\RoutesController as TenantAdminRoutesController;
use App\Http\Controllers\Tenant\Admin\EmailSignupsController as TenantAdminEmailSignupsController;
use App\Http\Controllers\Tenant\Admin\AuditLogController as TenantAdminAuditLogController;
use App\Http\Controllers\Tenant\Admin\ContactMessagesController as TenantAdminContactMessagesController;
use App\Http\Controllers\Tenant\Admin\BillingController as TenantAdminBillingController;
use App\Http\Controllers\Tenant\Admin\SalesController as TenantAdminSalesController;
use App\Http\Controllers\Tenant\Admin\SalesAnalyticsController as TenantAdminSalesAnalyticsController;
use App\Http\Controllers\Tenant\Admin\UsersController as TenantAdminUsersController;
use App\Http\Controllers\Tenant\ContactController;
use App\Http\Middleware\BearerTokenAuth;
use App\Http\Middleware\CurrentUser;
use App\Http\Middleware\ResolveTenant;
use App\Http\Middleware\RequireTenantRole;
use App\Http\Middleware\RequireTenantRoleBrowser;
use App\Http\Middleware\RequirePlatformRole;
use App\Http\Request;
use App\Http\Response;
use App\Http\View\AuthPage;
use App\Http\View\ErrorPage;
use App\Http\Router;
use App\Platform\Audit\AuditLogRepository;
use App\Platform\Contact\PlatformContactMessageRepository;
use App\Platform\Email\EmailOutboxRepository;
use App\Platform\Domains\DomainAdminRepository;
use App\Platform\Domains\DomainAdminService;
use App\Platform\Jobs\BackgroundJobRepository;
use App\Platform\Jobs\JobAdminRepository;
use App\Platform\Jobs\JobAdminService;
use App\Platform\Jobs\JobAttemptRepository;
use App\Platform\Workers\WorkerHeartbeatRepository;
use App\Platform\Auth\OAuth\BearerTokenRepository;
use App\Platform\Auth\OAuth\BearerTokenService;
use App\Platform\Auth\Password\PasswordAuthService;
use App\Platform\Auth\Password\PasswordResetService;
use App\Platform\Auth\Password\PasswordResetTokenRepository;
use App\Platform\Email\LifecycleEmailService;
use App\Platform\Email\TemplateRenderer;
use App\Platform\Auth\Session\SessionRepository;
use App\Platform\Auth\Session\SessionBridgeRepository;
use App\Platform\Auth\Session\SessionTokenService;
use App\Platform\Identity\PasswordHasher;
use App\Platform\Identity\UserIdentityRepository;
use App\Platform\Identity\AdminUserRepository;
use App\Platform\Identity\UserRepository;
use App\Platform\Membership\MembershipRepository;
use App\Platform\Security\RateLimiter;
use App\Platform\Settings\PlatformSettingsRepository;
use App\Platform\Signup\SignupCodeRepository;
use App\Platform\Signup\TenantSignupService;
use App\Platform\Tenants\TenantAdminRepository;
use App\Platform\ScaleTesting\ScaleTenantFixtureService;
use App\Platform\Tenancy\TenantResolver;
use App\Support\Database;
use App\Support\Security\CsrfTokenService;
use App\Tenant\Artwork\ArtworkReadRepository;
use App\Tenant\Signup\SignupNotificationService;
use App\Tenant\Signup\EmailSignupService;
use App\Tenant\Signup\EmailSignupRepository;
use App\Tenant\Contact\ContactNotificationService;
use App\Tenant\Contact\ContactMessageService;
use App\Tenant\Contact\ContactMessageRepository;
use App\Tenant\Settings\TenantSettingsRepository;
use App\Tenant\Sales\SalesRepository;
use App\Tenant\Artwork\ArtworkUploadService;
use App\Http\Auth\TenantPasswordResetGuard;

/** Coordinates request context, service setup, guards, and route registrars. */
final class AppKernel
{
    public function __construct(private readonly string $root)
    {
    }

    public function run(Request $request): void
    {
        $root = $this->root;

        try {
    // ARTSFOLIO_PLATFORM_ADMIN_CANONICAL_HOST
    // Platform admin is global ArtsFolio operations. Never serve it from
    // tenant subdomains or custom tenant domains.
    if (str_starts_with($request->path(), '/platform/admin') && $request->host() !== 'artsfol.io') {
        $target = 'https://artsfol.io' . $request->path();
        $queryString = (string) ($_SERVER['QUERY_STRING'] ?? '');

        if ($queryString !== '') {
            $target .= '?' . $queryString;
        }

        (new Response('', 302, ['Location' => $target]))->send();
        exit;
    }


    $pdo = Database::connect($root);
    $tenantResolver = new TenantResolver($pdo);
    $tenant = (new ResolveTenant($tenantResolver))->handle($request);
    $GLOBALS['artsfolio_tenant_context'] = $tenant;
    $GLOBALS['artsfolio_platform_context'] = $tenant === null;

    if ($tenant && method_exists($tenant, 'isSuspended') && $tenant->isSuspended()) {
        Response::error(403, 'This artist site is temporarily unavailable. Please check back later or contact ArtsFolio support.')->send();
        exit;
    }

    $sessionRepository = new SessionRepository($pdo);
    $sessionTokens = new SessionTokenService();
    $currentUser = (new CurrentUser($sessionRepository, $sessionTokens))->resolve($request);
    $GLOBALS['artsfolio_current_user'] = $currentUser;

    $sessionBridgeController = new TenantSessionBridgeController(
        new SessionBridgeRepository($pdo),
        $sessionRepository,
        $sessionTokens,
    );

    if ($tenant && $request->path() === '/auth/tenant-session/bridge') {
        $sessionBridgeController->bridge($request, $tenant, $currentUser)->send();
        exit;
    }

    if ($request->query('af_session_bridge') !== null) {
        $sessionBridgeController->consume($request)->send();
        exit;
    }

    if ($tenant && !$currentUser && str_starts_with($request->path(), '/admin')) {
        $sessionBridgeController->tenantDomainBridgeRedirect($request, $tenant)->send();
        exit;
    }

    $helpController = new HelpController();

    $passwordAuthController = new PasswordAuthController(
        new PasswordAuthService(
            new UserRepository($pdo),
            new UserIdentityRepository($pdo),
            new PasswordHasher(),
            $sessionRepository,
            $sessionTokens,
        ),
        $sessionRepository,
        $sessionTokens,
        new CsrfTokenService(),
        new AuditLogRepository($pdo),
    );

    $bearerToken = (new BearerTokenAuth(
        new BearerTokenRepository($pdo),
        new BearerTokenService(),
    ))->resolve($request);

    if ($request->path() === '/caddy/ask') {
        (new CaddyAskController($pdo))->ask($request)->send();
        exit;
    }
    if ($request->path() === '/stripe/webhook') {
        (new StripeWebhookController(new PlatformSettingsRepository($pdo), new SalesRepository($pdo)))->receive($request)->send();
        exit;
    }


$suspendedTenant = $tenantResolver->suspendedTenantForHost($request->server('HTTP_HOST') ?? '');
    if (!$tenant && $suspendedTenant) {
        Response::error(503, 'This artist site is not currently available. Please check back later or contact the artist directly if you have an existing relationship.')->send();
        exit;
    }

    // Platform-admin routes are canonical to the platform host. Tenant hosts such as
    if ($tenant) {
// Tenant login is intentionally mounted at /login on each tenant domain; the tenant root remains public content.
        $tenantSettings = new TenantSettingsRepository($pdo);
        $emailOutbox = new EmailOutboxRepository($pdo);
        $csrf = new CsrfTokenService();

        $portfolioSlug = $tenantSettings->get($tenant, 'portfolio_slug', 'portfolio');
        $aboutSlug = $tenantSettings->get($tenant, 'about_slug', 'about');
        $contactSlug = $tenantSettings->get($tenant, 'contact_slug', 'contact');

        $tenantController = new TenantHomeController(
            new TenantSettingsRepository($pdo),
            new ArtworkReadRepository($pdo),
            $pdo,
            $csrf,
        );

        $contactController = new ContactController(
            new ContactMessageService(
                new ContactMessageRepository($pdo),
                new ContactNotificationService($emailOutbox, $tenantSettings),
            ),
            $csrf,
            new RateLimiter($pdo),
            $pdo,
        );

        $signupController = new SignupController(
            new EmailSignupService(
                new EmailSignupRepository($pdo),
                new SignupNotificationService($emailOutbox, $tenantSettings),
            ),
            $csrf,
            new RateLimiter($pdo),
            $pdo,
        );

        $router = new Router();
            $tenantPasswordResetGuard = new TenantPasswordResetGuard($pdo);
            $tenantRegistrar = require $root . '/app/Http/Routes/tenant.php';
            $tenantRegistrar($router, get_defined_vars());
            $router->dispatch($request)->send();
            exit;
        }

    $platformController = new PlatformHomeController($pdo);
    $marketingController = new MarketingController($pdo);
    $helpController = new PlatformHelpController();

    $GLOBALS['artsfolio_platform_context'] = true;

    $router = new Router();
    $router->get('/error/{code}', fn (Request $request, array $params): Response => Response::error((int) ($params['code'] ?? 500)));

    // Track platform-host page views, including platform admin pages, after the
    // tenant router has had its chance to handle tenant sites.
    $trackPlatformPage = static function (Request $request) use ($pdo): void {
        if ($request->method() !== 'GET') {
            return;
        }
        $path = $request->path();
        if (str_starts_with($path, '/assets/') || $path === '/favicon.ico' || $path === '/caddy/ask') {
            return;
        }

        (new \App\Platform\Analytics\AnalyticsRecorder($pdo))->record(
            $request,
            null,
            str_starts_with($path, '/platform/admin') ? 'platform_admin_page_view' : 'platform_page_view',
        );
    };
    $trackPlatformPage($request);

    // Protect platform admin routes before route dispatch.
    if (str_starts_with($request->path(), '/platform/admin') || str_starts_with($request->path(), '/admin')) {
        $platformRoleGuard = new RequirePlatformRole(new MembershipRepository($pdo));
        if (!$currentUser) {
            (new Response('', 303, ['Location' => '/login?notice=platform-admin-login-required']))->send();
            exit;
        }
        if (!$platformRoleGuard->allows($currentUser, [\App\Platform\Membership\Roles::PLATFORM_OWNER, \App\Platform\Membership\Roles::PLATFORM_ADMIN, \App\Platform\Membership\Roles::PLATFORM_SUPPORT])) {
            Response::html(ErrorPage::unauthorized('/login', 'Platform admin access required.'), 403)->send();
            exit;
        }
    }

    // Compatibility redirects from the old platform admin mount. Tenant domains
    // still use /admin/* because they are dispatched before this platform router.
        $platformRegistrar = require $root . '/app/Http/Routes/platform.php';
        $platformRegistrar($router, get_defined_vars());
        $router->dispatch($request)->send();
        } catch (\Throwable $e) {
            ErrorPage::sendException($e);
        }
    }
}

// End of file.
