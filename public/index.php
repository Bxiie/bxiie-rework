<?php

declare(strict_types=1);

/**
 * Main HTTP front controller for local and production web requests.
 */

use App\Http\Controllers\Api\MeController;
use App\Http\Controllers\Api\AdminApiController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Api\TenantMeController;
use App\Http\Controllers\Auth\PasswordAuthController;
use App\Http\Controllers\Auth\OAuthController;
use App\Http\Controllers\Platform\Admin\DashboardController as PlatformAdminDashboardController;
use App\Http\Controllers\Platform\Admin\PricingController as PlatformAdminPricingController;
use App\Http\Controllers\Platform\Admin\SettingsController as PlatformAdminSettingsController;
use App\Http\Controllers\Platform\Admin\SignupCodesController as PlatformAdminSignupCodesController;
use App\Http\Controllers\Platform\Admin\RoutesController as PlatformAdminRoutesController;
use App\Http\Controllers\Platform\Admin\EmailOutboxController as PlatformAdminEmailOutboxController;
use App\Http\Controllers\Platform\Admin\DomainsController as PlatformAdminDomainsController;
use App\Http\Controllers\Platform\Admin\JobsController as PlatformAdminJobsController;
use App\Http\Controllers\Platform\Admin\WorkersController as PlatformAdminWorkersController;
use App\Http\Controllers\Platform\Admin\AuditLogController as PlatformAdminAuditLogController;
use App\Http\Controllers\Platform\Admin\TenantsController as PlatformAdminTenantsController;
use App\Http\Controllers\Platform\Admin\UsersController as PlatformAdminUsersController;
use App\Http\Controllers\Platform\HelpController as PlatformHelpController;
use App\Http\Controllers\Platform\Admin\StatsController as PlatformAdminStatsController;
use App\Http\Controllers\Platform\Admin\ContactMessagesController as PlatformAdminContactMessagesController;
use App\Http\Controllers\Platform\HomeController as PlatformHomeController;
use App\Http\Controllers\Platform\MarketingController;
use App\Http\Controllers\Platform\DirectoryController;
use App\Http\Controllers\Platform\PricingController;
use App\Http\Controllers\Platform\HelpController;
use App\Http\Controllers\Platform\PlatformCssController;
use App\Http\Controllers\Platform\CaddyAskController;
use App\Http\Controllers\Platform\SignupController as PlatformSignupController;
use App\Http\Controllers\Tenant\HomeController as TenantHomeController;
use App\Http\Controllers\Tenant\TenantCssController;
use App\Http\Controllers\Tenant\MediaController as TenantMediaController;
use App\Http\Controllers\Tenant\SignupController;
use App\Http\Controllers\Tenant\Admin\DashboardController as TenantAdminDashboardController;
use App\Http\Controllers\Tenant\Admin\DiscoverySettingsController as TenantAdminDiscoverySettingsController;
use App\Http\Controllers\Tenant\Admin\StatsController as TenantAdminStatsController;
use App\Http\Controllers\Tenant\Admin\GettingStartedController as TenantAdminGettingStartedController;
use App\Http\Controllers\Tenant\Admin\ArtworkUploadController as TenantAdminArtworkUploadController;
use App\Http\Controllers\Tenant\Admin\ArtworksController as TenantAdminArtworksController;
use App\Http\Controllers\Tenant\Admin\ContentController as TenantAdminContentController;
use App\Http\Controllers\Tenant\Admin\EventsController as TenantAdminEventsController;
use App\Http\Controllers\Tenant\Admin\PortfolioSectionsController as TenantAdminPortfolioSectionsController;
use App\Http\Controllers\Tenant\Admin\SettingsController as TenantAdminSettingsController;
use App\Http\Controllers\Tenant\Admin\RoutesController as TenantAdminRoutesController;
use App\Http\Controllers\Tenant\Admin\EmailSignupsController as TenantAdminEmailSignupsController;
use App\Http\Controllers\Tenant\Admin\AuditLogController as TenantAdminAuditLogController;
use App\Http\Controllers\Tenant\Admin\ContactMessagesController as TenantAdminContactMessagesController;
use App\Http\Controllers\Tenant\Admin\BillingController as TenantAdminBillingController;
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
use App\Platform\Email\EmailOutboxRepository;
use App\Platform\Domains\DomainAdminRepository;
use App\Platform\Domains\DomainAdminService;
use App\Platform\Jobs\JobAdminRepository;
use App\Platform\Jobs\JobAdminService;
use App\Platform\Jobs\JobAttemptRepository;
use App\Platform\Workers\WorkerHeartbeatRepository;
use App\Platform\Auth\OAuth\BearerTokenRepository;
use App\Platform\Auth\OAuth\BearerTokenService;
use App\Platform\Auth\Password\PasswordAuthService;
use App\Platform\Auth\Session\SessionRepository;
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

use App\Tenant\Artwork\ArtworkUploadService;

$root = dirname(__DIR__);

require $root . '/bootstrap/app.php';

session_start();

$request = Request::fromGlobals();

try {
    $pdo = Database::connect($root);
    $tenantResolver = new TenantResolver($pdo);
    $tenant = (new ResolveTenant($tenantResolver))->handle($request);

    if ($tenant && method_exists($tenant, 'isSuspended') && $tenant->isSuspended()) {
        Response::html('<!doctype html><html lang="en"><head><meta charset="utf-8"><title>Content unavailable | ArtsFolio</title><meta name="viewport" content="width=device-width, initial-scale=1"><link rel="stylesheet" href="/assets/platform.css"></head><body class="platform-page"><main class="platform-main"><section class="platform-page-heading"><p class="eyebrow">ArtsFolio</p><h1>We are sorry, this content cannot be shown right now.</h1><p>This artist site is temporarily unavailable. Please check back later or contact ArtsFolio support.</p><p><a class="button primary" href="https://artsfol.io/">Return to ArtsFolio</a></p></section></main></body></html>', 403)->send();
        exit;
    }

    $sessionRepository = new SessionRepository($pdo);
    $sessionTokens = new SessionTokenService();
    $currentUser = (new CurrentUser($sessionRepository, $sessionTokens))->resolve($request);
    $GLOBALS['artsfolio_current_user'] = $currentUser;
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
    echo (new CaddyAskController($pdo))->ask($request)->send();
    exit;
}

$suspendedTenant = $tenantResolver->suspendedTenantForHost($request->server('HTTP_HOST') ?? '');
    if (!$tenant && $suspendedTenant) {
        $tenantName = htmlspecialchars((string) ($suspendedTenant['name'] ?? 'this artist site'), ENT_QUOTES, 'UTF-8');
        Response::html("<!doctype html><html lang=\"en\"><head><meta charset=\"utf-8\"><title>Content unavailable | ArtsFolio</title><meta name=\"viewport\" content=\"width=device-width, initial-scale=1\"><link rel=\"stylesheet\" href=\"/assets/platform.css\"></head><body class=\"platform-page\"><header class=\"platform-header\"><a class=\"platform-logo\" href=\"https://artsfol.io/\"><img src=\"/assets/logo_2.png\" alt=\"ArtsFolio\"></a></header><main class=\"platform-main\"><section class=\"platform-hero\"><p class=\"eyebrow\">ArtsFolio</p><h1>We’re sorry, this content can’t be shown.</h1><p>The site for {$tenantName} is not currently available. Please check back later or contact the artist directly if you have an existing relationship.</p><p><a class=\"button primary\" href=\"https://artsfol.io/\">Return to ArtsFolio</a></p></section></main></body></html>", 503)->send();
        exit;
    }

    // Platform-admin routes are canonical to the platform host. Tenant hosts such as
    // bxiie.artsfol.io must not try to dispatch /platform/admin through the tenant
    // router, because that produces confusing unbranded tenant 404s. Redirect browser
    // requests to the platform host and preserve the path/query string.
    if ($tenant && str_starts_with($request->path(), '/platform/admin')) {
        $queryString = (string) ($_SERVER['QUERY_STRING'] ?? '');
        $target = 'https://artsfol.io' . $request->path() . ($queryString !== '' ? '?' . $queryString : '');
        Response::html('', 302, ['Location' => $target])->send();
        exit;
    }

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

        // Protect every tenant-admin route before route dispatch so unauthenticated
        // users get a login redirect instead of a raw forbidden page.
        if (str_starts_with($request->path(), '/admin') && !in_array($request->path(), ['/admin/login'], true)) {
            $tenantRoleGuard = new RequireTenantRoleBrowser(new MembershipRepository($pdo));
            if (!$currentUser) {
                (new Response('', 303, ['Location' => '/login?notice=admin-login-required']))->send();
                exit;
            }
            if (!$tenantRoleGuard->allows($currentUser, $tenant, ['tenant_owner', 'tenant_admin', 'owner', 'admin'])) {
                Response::html(ErrorPage::unauthorized('/login', 'Tenant admin access required.'), 403)->send();
                exit;
            }
        }
    $router->get('/help', fn (Request $request): Response => $helpController->index($request, $currentUser));
    $router->get('/help/{article}', fn (Request $request, array $params): Response => $helpController->article($request, (string) ($params['article'] ?? 'getting-started'), $currentUser));
    $router->get('/developer', fn (Request $request): Response => $helpController->developer($request, $currentUser));
        $router->get('/help', fn (Request $request): Response => $helpController->index($request, $currentUser));
        $router->get('/help/{article}', fn (Request $request, array $params): Response => $helpController->article($request, (string) ($params['article'] ?? 'getting-started'), $currentUser));
        $router->get('/developer', fn (Request $request): Response => $helpController->developer($request, $currentUser));
    $router->get('/assets/platform-custom.css', fn (Request $request): Response => (new PlatformCssController(new PlatformSettingsRepository($pdo)))->show($request));
        $router->get('/tenant.css', fn (Request $request): Response => (new TenantCssController($tenantSettings))->show($request, $tenant));
        $router->get('/', fn (Request $request): Response => $tenantController->home($request, $tenant));
        $router->get('/' . $portfolioSlug, fn (Request $request): Response => $tenantController->portfolio($request, $tenant));
        $router->get('/' . $aboutSlug, fn (Request $request): Response => $tenantController->about($request, $tenant));
        $router->get('/' . $contactSlug, fn (Request $request): Response => $tenantController->contact($request, $tenant));
        $router->get('/portfolio', fn (Request $request): Response => $tenantController->portfolio($request, $tenant));
        $router->get('/artwork/{slug}', fn (Request $request, array $params): Response => $tenantController->artwork($request, $tenant, (string) $params['slug']));
        $router->get('/about', fn (Request $request): Response => $tenantController->about($request, $tenant));
        $router->get('/caddy/ask', fn (Request $request): Response => (new CaddyAskController($pdo))->ask($request));
        $router->post('/login', fn (Request $request): Response => (new LoginController(new PasswordAuthService(new UserRepository($pdo), new UserIdentityRepository($pdo), new PasswordHasher(), new SessionRepository($pdo), new SessionTokenService()), new CsrfTokenService(), $tenantSettings))->login($request, $tenant));
        $router->get('/logout', fn (Request $request): Response => (new LoginController(new PasswordAuthService(new UserRepository($pdo), new UserIdentityRepository($pdo), new PasswordHasher(), new SessionRepository($pdo), new SessionTokenService()), new CsrfTokenService(), $tenantSettings))->logout($request));
        $router->get('/help', fn (Request $request): Response => (new HelpController())->index($request, $currentUser));
        $router->get('/help/{article}', fn (Request $request, array $params): Response => (new HelpController())->topic($request, $params, $currentUser));
        $router->get('/developer', fn (Request $request): Response => (new HelpController())->developer($request, $currentUser));
    $router->get('/help/developer', fn (Request $request): Response => (new HelpController())->developer($request, $currentUser));

        $router->post('/logout', fn (Request $request): Response => (new LoginController(new PasswordAuthService(new UserRepository($pdo), new UserIdentityRepository($pdo), new PasswordHasher(), new SessionRepository($pdo), new SessionTokenService()), new CsrfTokenService(), $tenantSettings))->logout($request));
        $router->get('/admin/media', fn (Request $request): Response => (new TenantMediaController($pdo, new RequireTenantRoleBrowser(new MembershipRepository($pdo))))->admin($request, $tenant, $currentUser));
        $router->get('/admin/portfolio-sections', fn (Request $request): Response => (new TenantAdminPortfolioSectionsController(new RequireTenantRoleBrowser(new MembershipRepository($pdo)), $pdo, $csrf))->index($request, $tenant, $currentUser));
        $router->get('/admin/portfolio-sections/edit', fn (Request $request): Response => (new TenantAdminPortfolioSectionsController(new RequireTenantRoleBrowser(new MembershipRepository($pdo)), $pdo, $csrf))->edit($request, $tenant, $currentUser));
        $router->post('/admin/portfolio-sections/edit', fn (Request $request): Response => (new TenantAdminPortfolioSectionsController(new RequireTenantRoleBrowser(new MembershipRepository($pdo)), $pdo, $csrf))->update($request, $tenant, $currentUser));
        $router->get('/admin/events', fn (Request $request): Response => (new TenantAdminEventsController(new RequireTenantRoleBrowser(new MembershipRepository($pdo)), $pdo, $csrf))->index($request, $tenant, $currentUser));
        $router->get('/admin/events/edit', fn (Request $request): Response => (new TenantAdminEventsController(new RequireTenantRoleBrowser(new MembershipRepository($pdo)), $pdo, $csrf))->edit($request, $tenant, $currentUser));
        $router->post('/admin/events/edit', fn (Request $request): Response => (new TenantAdminEventsController(new RequireTenantRoleBrowser(new MembershipRepository($pdo)), $pdo, $csrf))->update($request, $tenant, $currentUser));
        $router->post('/admin/events/order', fn (Request $request): Response => (new TenantAdminEventsController(new RequireTenantRoleBrowser(new MembershipRepository($pdo)), $pdo, new CsrfTokenService()))->order($request, $tenant, $currentUser));
        $router->get('/admin/content', fn (Request $request): Response => (new TenantAdminContentController(new RequireTenantRoleBrowser(new MembershipRepository($pdo)), $tenantSettings, $csrf, $pdo))->edit($request, $tenant, $currentUser));
        $router->post('/admin/content', fn (Request $request): Response => (new TenantAdminContentController(new RequireTenantRoleBrowser(new MembershipRepository($pdo)), $tenantSettings, $csrf, $pdo))->update($request, $tenant, $currentUser));
        $router->get('/admin/artworks', fn (Request $request): Response => (new TenantAdminArtworksController(new RequireTenantRoleBrowser(new MembershipRepository($pdo)), $pdo, new AuditLogRepository($pdo)))->index($request, $tenant, $currentUser));
        $router->get('/admin/artworks/edit', fn (Request $request): Response => (new TenantAdminArtworksController(new RequireTenantRoleBrowser(new MembershipRepository($pdo)), $pdo, new AuditLogRepository($pdo)))->edit($request, $tenant, $currentUser));
        $router->post('/admin/artworks/edit', fn (Request $request): Response => (new TenantAdminArtworksController(new RequireTenantRoleBrowser(new MembershipRepository($pdo)), $pdo, new AuditLogRepository($pdo)))->update($request, $tenant, $currentUser));
        $router->post('/admin/artworks/status', fn (Request $request): Response => (new TenantAdminArtworksController(new RequireTenantRoleBrowser(new MembershipRepository($pdo)), $pdo, new AuditLogRepository($pdo)))->updateStatus($request, $tenant, $currentUser));
        $router->post('/admin/artworks/delete', fn (Request $request): Response => (new TenantAdminArtworksController(new RequireTenantRoleBrowser(new MembershipRepository($pdo)), $pdo, new AuditLogRepository($pdo)))->delete($request, $tenant, $currentUser));
        $router->get('/media', fn (Request $request): Response => (new TenantMediaController($pdo))->public($request, $tenant));
        $router->get('/admin/artwork/upload', fn (Request $request): Response => (new TenantAdminArtworkUploadController(new RequireTenantRoleBrowser(new MembershipRepository($pdo)), new CsrfTokenService(), new ArtworkUploadService($pdo), new AuditLogRepository($pdo), $pdo))->form($request, $tenant, $currentUser));
        $router->post('/admin/artwork/upload', fn (Request $request): Response => (new TenantAdminArtworkUploadController(new RequireTenantRoleBrowser(new MembershipRepository($pdo)), new CsrfTokenService(), new ArtworkUploadService($pdo), new AuditLogRepository($pdo), $pdo))->submit($request, $tenant, $currentUser));
        $router->get('/admin/getting-started', fn (Request $request): Response => (new TenantAdminGettingStartedController(new RequireTenantRoleBrowser(new MembershipRepository($pdo))))->index($request, $tenant, $currentUser));
        $router->get('/login', fn (Request $request): Response => (new LoginController(new PasswordAuthService(new UserRepository($pdo), new UserIdentityRepository($pdo), new PasswordHasher(), new SessionRepository($pdo), new SessionTokenService()), $csrf, $tenantSettings))->show($request, $tenant));
        $router->get('/help/{topic}', fn (Request $request, array $params): Response => $helpController->topic($request, (string) $params['topic']));
        $router->get('/admin/login', fn (Request $request): Response => new Response('', 303, ['Location' => '/login']));
        $router->get('/login', fn (Request $request): Response => (new LoginController(new PasswordAuthService(new UserRepository($pdo), new UserIdentityRepository($pdo), new PasswordHasher(), new SessionRepository($pdo), new SessionTokenService()), $csrf, $tenantSettings))->show($request, $tenant));
    $router->get('/register', fn (Request $request): Response => Response::html(AuthPage::register('/register')));
    $router->get('/password/forgot', fn (Request $request): Response => Response::html(AuthPage::forgotPassword('/password/forgot')));
    $router->get('/admin/login', fn (Request $request): Response => new Response('', 303, ['Location' => '/login']));
    $router->get('/admin', fn (Request $request): Response => (new TenantAdminDashboardController($tenantSettings))->index($request, $tenant, $currentUser));
        $router->get('/admin/billing', fn (Request $request): Response => (new TenantAdminBillingController(new RequireTenantRoleBrowser(new MembershipRepository($pdo)), $pdo))->index($request, $tenant, $currentUser));
        $router->get('/admin/users', fn (Request $request): Response => (new TenantAdminUsersController(new RequireTenantRoleBrowser(new MembershipRepository($pdo)), new AdminUserRepository($pdo), new PasswordHasher(), new CsrfTokenService(), $tenantSettings, new AuditLogRepository($pdo), new EmailOutboxRepository($pdo)))->index($request, $tenant, $currentUser));
        $router->post('/admin/users/password', fn (Request $request): Response => (new TenantAdminUsersController(new RequireTenantRoleBrowser(new MembershipRepository($pdo)), new AdminUserRepository($pdo), new PasswordHasher(), new CsrfTokenService(), $tenantSettings, new AuditLogRepository($pdo), new EmailOutboxRepository($pdo)))->updatePassword($request, $tenant, $currentUser));
        $router->post('/admin/users/invite', fn (Request $request): Response => (new TenantAdminUsersController(new RequireTenantRoleBrowser(new MembershipRepository($pdo)), new AdminUserRepository($pdo), new PasswordHasher(), new CsrfTokenService(), $tenantSettings, new AuditLogRepository($pdo), new EmailOutboxRepository($pdo)))->invite($request, $tenant, $currentUser));
        $router->post('/admin/users/resend-invite', fn (Request $request): Response => (new TenantAdminUsersController(new RequireTenantRoleBrowser(new MembershipRepository($pdo)), new AdminUserRepository($pdo), new PasswordHasher(), new CsrfTokenService(), $tenantSettings, new AuditLogRepository($pdo), new EmailOutboxRepository($pdo)))->resendInvite($request, $tenant, $currentUser));
        $router->post('/admin/users/promote-owner', fn (Request $request): Response => (new TenantAdminUsersController(new RequireTenantRoleBrowser(new MembershipRepository($pdo)), new AdminUserRepository($pdo), new PasswordHasher(), new CsrfTokenService(), $tenantSettings, new AuditLogRepository($pdo), new EmailOutboxRepository($pdo)))->promoteOwner($request, $tenant, $currentUser));
        $router->post('/admin/users/delete', fn (Request $request): Response => (new TenantAdminUsersController(new RequireTenantRoleBrowser(new MembershipRepository($pdo)), new AdminUserRepository($pdo), new PasswordHasher(), new CsrfTokenService(), $tenantSettings, new AuditLogRepository($pdo), new EmailOutboxRepository($pdo)))->delete($request, $tenant, $currentUser));
        $router->get('/admin/routes', fn (Request $request): Response => (new TenantAdminRoutesController(new RequireTenantRoleBrowser(new MembershipRepository($pdo))))->index($request, $tenant, $currentUser));

        $router->get('/admin/directory', fn (Request $request): Response => (new TenantAdminDiscoverySettingsController(new RequireTenantRoleBrowser(new MembershipRepository($pdo)), $tenantSettings, $csrf, new AuditLogRepository($pdo), $pdo))->edit($request, $tenant, $currentUser));
        $router->post('/admin/directory', fn (Request $request): Response => (new TenantAdminDiscoverySettingsController(new RequireTenantRoleBrowser(new MembershipRepository($pdo)), $tenantSettings, $csrf, new AuditLogRepository($pdo), $pdo))->update($request, $tenant, $currentUser));
        $router->get('/admin/platform-discovery', fn (Request $request): Response => new Response('', 303, ['Location' => '/admin/directory']));
        $router->post('/admin/platform-discovery', fn (Request $request): Response => (new TenantAdminDiscoverySettingsController(new RequireTenantRoleBrowser(new MembershipRepository($pdo)), $tenantSettings, $csrf, new AuditLogRepository($pdo), $pdo))->update($request, $tenant, $currentUser));
        $router->get('/admin/stats', fn (Request $request): Response => (new TenantAdminStatsController(new RequireTenantRoleBrowser(new MembershipRepository($pdo)), $pdo))->index($request, $tenant, $currentUser));
        $router->get('/admin/audit-log', fn (Request $request): Response => (new TenantAdminAuditLogController(new RequireTenantRoleBrowser(new MembershipRepository($pdo)), new AuditLogRepository($pdo)))->index($request, $tenant, $currentUser));
        $router->get('/admin/audit-log.csv', fn (Request $request): Response => (new TenantAdminAuditLogController(new RequireTenantRoleBrowser(new MembershipRepository($pdo)), new AuditLogRepository($pdo)))->export($request, $tenant, $currentUser));
        $router->get('/admin/contact-messages', fn (Request $request): Response => (new TenantAdminContactMessagesController(new RequireTenantRoleBrowser(new MembershipRepository($pdo)), new ContactMessageRepository($pdo), $csrf, new AuditLogRepository($pdo)))->index($request, $tenant, $currentUser));
        $router->get('/admin/contact-messages.csv', fn (Request $request): Response => (new TenantAdminContactMessagesController(new RequireTenantRoleBrowser(new MembershipRepository($pdo)), new ContactMessageRepository($pdo), $csrf, new AuditLogRepository($pdo)))->export($request, $tenant, $currentUser));
        $router->post('/admin/contact-messages/status', fn (Request $request): Response => (new TenantAdminContactMessagesController(new RequireTenantRoleBrowser(new MembershipRepository($pdo)), new ContactMessageRepository($pdo), $csrf, new AuditLogRepository($pdo)))->updateStatus($request, $tenant, $currentUser));
        $router->post('/admin/contact-messages/delete', fn (Request $request): Response => (new TenantAdminContactMessagesController(new RequireTenantRoleBrowser(new MembershipRepository($pdo)), new ContactMessageRepository($pdo), $csrf, new AuditLogRepository($pdo)))->delete($request, $tenant, $currentUser));
        $router->get('/admin/email-signups', fn (Request $request): Response => (new TenantAdminEmailSignupsController(new RequireTenantRoleBrowser(new MembershipRepository($pdo)), new EmailSignupRepository($pdo), $csrf, new AuditLogRepository($pdo)))->index($request, $tenant, $currentUser));
        $router->get('/admin/email-signups.csv', fn (Request $request): Response => (new TenantAdminEmailSignupsController(new RequireTenantRoleBrowser(new MembershipRepository($pdo)), new EmailSignupRepository($pdo), $csrf, new AuditLogRepository($pdo)))->export($request, $tenant, $currentUser));
        $router->post('/admin/email-signups/consent', fn (Request $request): Response => (new TenantAdminEmailSignupsController(new RequireTenantRoleBrowser(new MembershipRepository($pdo)), new EmailSignupRepository($pdo), $csrf, new AuditLogRepository($pdo)))->updateConsent($request, $tenant, $currentUser));
        $router->post('/admin/email-signups/delete', fn (Request $request): Response => (new TenantAdminEmailSignupsController(new RequireTenantRoleBrowser(new MembershipRepository($pdo)), new EmailSignupRepository($pdo), $csrf, new AuditLogRepository($pdo)))->delete($request, $tenant, $currentUser));
        $router->get('/admin/settings', fn (Request $request): Response => (new TenantAdminSettingsController(new RequireTenantRoleBrowser(new MembershipRepository($pdo)), $tenantSettings, $csrf, new AuditLogRepository($pdo), $pdo))->edit($request, $tenant, $currentUser));
        $router->post('/admin/settings', fn (Request $request): Response => (new TenantAdminSettingsController(new RequireTenantRoleBrowser(new MembershipRepository($pdo)), $tenantSettings, $csrf, new AuditLogRepository($pdo), $pdo))->update($request, $tenant, $currentUser));
        $router->get('/contact', fn (Request $request): Response => $tenantController->contact($request, $tenant));
        $router->post('/contact', fn (Request $request): Response => $contactController->submit($request, $tenant));
        $router->post('/signup', fn (Request $request): Response => $signupController->submit($request, $tenant));
        $router->get('/api/me', fn (Request $request): Response => (new TenantMeController(tenantRoles: new RequireTenantRole(new MembershipRepository($pdo)), auditLog: new AuditLogRepository($pdo)))->show($request, $bearerToken, $tenant));

        $router->dispatch($request)->send();
        exit;
    }

    $platformController = new PlatformHomeController();
    $marketingController = new MarketingController($pdo);
    $helpController = new PlatformHelpController();

    $router = new Router();

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
        try {
            $ip = trim((string) ($request->server('HTTP_CF_CONNECTING_IP') ?: $request->server('REMOTE_ADDR', '')));
            $ipHash = hash('sha256', $ip . '|artsfolio-analytics');
            $location = (new \App\Platform\Analytics\AnalyticsLocationResolver($pdo))->resolve($request, $ip, $ipHash);
            $stmt = $pdo->prepare(
                'INSERT INTO analytics_events (tenant_id, event_type, path, referrer, ip_hash, ip_address, user_agent, country, region, city, created_at)
                 VALUES (NULL, :event_type, :path, :referrer, :ip_hash, :ip_address, :user_agent, :country, :region, :city, NOW())'
            );
            $stmt->execute([
                'event_type' => str_starts_with($path, '/platform/admin') ? 'platform_admin_page_view' : 'platform_page_view',
                'path' => $path,
                'referrer' => mb_substr((string) $request->server('HTTP_REFERER', ''), 0, 1000),
                'ip_hash' => $ipHash,
                'ip_address' => mb_substr($ip, 0, 64),
                'user_agent' => mb_substr((string) $request->server('HTTP_USER_AGENT', ''), 0, 1000),
                'country' => $location['country'],
                'region' => $location['region'],
                'city' => $location['city'],
            ]);
        } catch (\Throwable) {
            // Analytics must never break platform pages.
        }
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
    $router->get('/admin', fn (Request $request): Response => new Response('', 302, ['Location' => '/platform/admin']));
    $router->get('/admin/pricing', fn (Request $request): Response => new Response('', 302, ['Location' => '/platform/admin/pricing']));
    $router->get('/admin/settings', fn (Request $request): Response => new Response('', 302, ['Location' => '/platform/admin/platform-settings']));
    $router->get('/admin/platform-settings', fn (Request $request): Response => new Response('', 302, ['Location' => '/platform/admin/platform-settings']));
    $router->get('/admin/routes', fn (Request $request): Response => new Response('', 302, ['Location' => '/platform/admin/routes']));
    $router->get('/admin/tenants', fn (Request $request): Response => new Response('', 302, ['Location' => '/platform/admin/tenants']));
    $router->get('/admin/stats', fn (Request $request): Response => new Response('', 302, ['Location' => '/platform/admin/stats']));
    $router->get('/admin/contact-messages', fn (Request $request): Response => new Response('', 302, ['Location' => '/platform/admin/contact-messages']));
    $router->get('/admin/domains', fn (Request $request): Response => new Response('', 302, ['Location' => '/platform/admin/domains']));
    $router->post('/admin/domains/action', fn (Request $request): Response => (new PlatformAdminDomainsController(new RequirePlatformRole(new MembershipRepository($pdo)), new DomainAdminRepository($pdo), new DomainAdminService($pdo), new CsrfTokenService(), new AuditLogRepository($pdo)))->action($request, $currentUser));
    $router->get('/admin/jobs', fn (Request $request): Response => new Response('', 302, ['Location' => '/platform/admin/jobs']));
    $router->get('/admin/workers', fn (Request $request): Response => new Response('', 302, ['Location' => '/platform/admin/workers']));
    $router->get('/admin/email-outbox', fn (Request $request): Response => new Response('', 302, ['Location' => '/platform/admin/email-outbox']));
    $router->get('/admin/audit-log', fn (Request $request): Response => new Response('', 302, ['Location' => '/platform/admin/audit-log']));
    $router->get('/admin/audit-log.csv', fn (Request $request): Response => new Response('', 302, ['Location' => '/platform/admin/audit-log.csv']));
    $router->get('/pricing', fn (Request $request): Response => (new PricingController($pdo, new PlatformSettingsRepository($pdo)))->index($request));
    $router->get('/signup', fn (Request $request): Response => (new PlatformSignupController(new TenantSignupService($pdo, new PlatformSettingsRepository($pdo), new SignupCodeRepository($pdo)), new PasswordHasher(), new CsrfTokenService(), new SessionRepository($pdo), new SessionTokenService()))->show($request));
    $router->post('/signup', fn (Request $request): Response => (new PlatformSignupController(new TenantSignupService($pdo, new PlatformSettingsRepository($pdo), new SignupCodeRepository($pdo)), new PasswordHasher(), new CsrfTokenService(), new SessionRepository($pdo), new SessionTokenService()))->submit($request));
    $router->get('/', fn (Request $request): Response => $marketingController->home($request));
    $router->get('/directory', fn (Request $request): Response => (new DirectoryController($pdo))->index($request));
    $router->get('/signup', fn (Request $request): Response => $marketingController->signup($request));
    $router->get('/contact', fn (Request $request): Response => $marketingController->contact($request));
    $router->post('/contact', fn (Request $request): Response => $marketingController->contact($request));
    $router->get('/help/{topic}', fn (Request $request, array $params): Response => $helpController->topic($request, (string) $params['topic']));

    $router->get('/help', fn (Request $request): Response => (new HelpController())->index($request, $currentUser));
    $router->get('/help/{article}', fn (Request $request, array $params): Response => (new HelpController())->topic($request, $params, $currentUser));
    $router->get('/developer', fn (Request $request): Response => (new HelpController())->developer($request, $currentUser));
    $router->get('/help/developer', fn (Request $request): Response => (new HelpController())->developer($request, $currentUser));
    $router->get('/privacy', fn (Request $request): Response => $marketingController->privacy($request));

    $router->get('/platform/admin', fn (Request $request): Response => (new PlatformAdminDashboardController(new RequirePlatformRole(new MembershipRepository($pdo))))->index($request, $currentUser));
    $router->get('/platform/admin/pricing', fn (Request $request): Response => (new PlatformAdminPricingController(new RequirePlatformRole(new MembershipRepository($pdo)), $pdo, new PlatformSettingsRepository($pdo), new CsrfTokenService(), new AuditLogRepository($pdo)))->index($request, $currentUser));
    $router->post('/platform/admin/pricing', fn (Request $request): Response => (new PlatformAdminPricingController(new RequirePlatformRole(new MembershipRepository($pdo)), $pdo, new PlatformSettingsRepository($pdo), new CsrfTokenService(), new AuditLogRepository($pdo)))->update($request, $currentUser));
    $router->get('/platform/admin/settings', fn (Request $request): Response => new Response('', 302, ['Location' => '/platform/admin/platform-settings']));
    $router->get('/platform/admin/routes', fn (Request $request): Response => (new PlatformAdminRoutesController(new RequirePlatformRole(new MembershipRepository($pdo))))->index($request, $currentUser));
    $router->get('/platform/admin/tenants', fn (Request $request): Response => (new PlatformAdminTenantsController(new RequirePlatformRole(new MembershipRepository($pdo)), new TenantAdminRepository($pdo), new AdminUserRepository($pdo), new PasswordHasher(), new CsrfTokenService(), new AuditLogRepository($pdo)))->index($request, $currentUser));
    $router->get('/platform/admin/tenants/{id}', fn (Request $request, array $params): Response => (new PlatformAdminTenantsController(new RequirePlatformRole(new MembershipRepository($pdo)), new TenantAdminRepository($pdo), new AdminUserRepository($pdo), new PasswordHasher(), new CsrfTokenService(), new AuditLogRepository($pdo)))->show($request, $currentUser, (int) ($params['id'] ?? 0)));
    $router->post('/platform/admin/tenants/users/password', fn (Request $request): Response => (new PlatformAdminTenantsController(new RequirePlatformRole(new MembershipRepository($pdo)), new TenantAdminRepository($pdo), new AdminUserRepository($pdo), new PasswordHasher(), new CsrfTokenService(), new AuditLogRepository($pdo)))->updateTenantUserPassword($request, $currentUser));
    $router->post('/platform/admin/users/suspend', fn (Request $request): Response => (new PlatformAdminUsersController(new RequirePlatformRole(new MembershipRepository($pdo)), new AdminUserRepository($pdo), new PasswordHasher(), new CsrfTokenService(), new AuditLogRepository($pdo)))->suspend($request, $currentUser));
    $router->post('/platform/admin/users/delete', fn (Request $request): Response => (new PlatformAdminUsersController(new RequirePlatformRole(new MembershipRepository($pdo)), new AdminUserRepository($pdo), new PasswordHasher(), new CsrfTokenService(), new AuditLogRepository($pdo)))->delete($request, $currentUser));
    $router->post('/platform/admin/tenants/suspend', fn (Request $request): Response => (new PlatformAdminTenantsController(new RequirePlatformRole(new MembershipRepository($pdo)), new TenantAdminRepository($pdo), new AdminUserRepository($pdo), new PasswordHasher(), new CsrfTokenService(), new AuditLogRepository($pdo)))->suspend($request, $currentUser));
    $router->post('/platform/admin/tenants/delete', fn (Request $request): Response => (new PlatformAdminTenantsController(new RequirePlatformRole(new MembershipRepository($pdo)), new TenantAdminRepository($pdo), new AdminUserRepository($pdo), new PasswordHasher(), new CsrfTokenService(), new AuditLogRepository($pdo)))->delete($request, $currentUser));

        $router->post('/platform/admin/tenants/status', fn (Request $request): Response => (new PlatformAdminTenantsController(new RequirePlatformRole(new MembershipRepository($pdo)), new TenantAdminRepository($pdo), new AdminUserRepository($pdo), new PasswordHasher(), new CsrfTokenService(), new AuditLogRepository($pdo)))->updateTenantStatus($request, $currentUser));
    $router->get('/platform/admin/users', fn (Request $request): Response => (new PlatformAdminUsersController(new RequirePlatformRole(new MembershipRepository($pdo)), new AdminUserRepository($pdo), new PasswordHasher(), new CsrfTokenService(), new AuditLogRepository($pdo), new EmailOutboxRepository($pdo)))->index($request, $currentUser));
    $router->post('/platform/admin/users/password', fn (Request $request): Response => (new PlatformAdminUsersController(new RequirePlatformRole(new MembershipRepository($pdo)), new AdminUserRepository($pdo), new PasswordHasher(), new CsrfTokenService(), new AuditLogRepository($pdo), new EmailOutboxRepository($pdo)))->updatePassword($request, $currentUser));
    $router->post('/platform/admin/users/resend-invite', fn (Request $request): Response => (new PlatformAdminUsersController(new RequirePlatformRole(new MembershipRepository($pdo)), new AdminUserRepository($pdo), new PasswordHasher(), new CsrfTokenService(), new AuditLogRepository($pdo), new EmailOutboxRepository($pdo)))->resendInvite($request, $currentUser));
        $router->post('/platform/admin/users/status', fn (Request $request): Response => (new PlatformAdminUsersController(new RequirePlatformRole(new MembershipRepository($pdo)), new AdminUserRepository($pdo), new PasswordHasher(), new CsrfTokenService(), new AuditLogRepository($pdo)))->updateStatus($request, $currentUser));
    $router->get('/platform/admin/stats', fn (Request $request): Response => (new PlatformAdminStatsController(new RequirePlatformRole(new MembershipRepository($pdo)), $pdo))->index($request, $currentUser));
    $router->get('/platform/admin/contact-messages', fn (Request $request): Response => (new PlatformAdminContactMessagesController(new RequirePlatformRole(new MembershipRepository($pdo)), $pdo))->index($request, $currentUser));
    $router->get('/platform/admin/domains', fn (Request $request): Response => (new PlatformAdminDomainsController(new RequirePlatformRole(new MembershipRepository($pdo)), new DomainAdminRepository($pdo), new DomainAdminService($pdo), new CsrfTokenService(), new AuditLogRepository($pdo)))->index($request, $currentUser));
    $router->get('/platform/admin/jobs', fn (Request $request): Response => (new PlatformAdminJobsController(new RequirePlatformRole(new MembershipRepository($pdo)), new JobAdminRepository($pdo), new JobAdminService($pdo, new JobAttemptRepository($pdo)), new CsrfTokenService(), new AuditLogRepository($pdo), new JobAttemptRepository($pdo)))->index($request, $currentUser));
    $router->get('/platform/admin/workers', fn (Request $request): Response => (new PlatformAdminWorkersController(new RequirePlatformRole(new MembershipRepository($pdo)), new WorkerHeartbeatRepository($pdo)))->index($request, $currentUser));
    $router->get('/platform/admin/jobs/{id}', fn (Request $request, string $id): Response => (new PlatformAdminJobsController(new RequirePlatformRole(new MembershipRepository($pdo)), new JobAdminRepository($pdo), new JobAdminService($pdo, new JobAttemptRepository($pdo)), new CsrfTokenService(), new AuditLogRepository($pdo), new JobAttemptRepository($pdo)))->show($request, $currentUser, (int) $id));
    $router->post('/platform/admin/jobs/action', fn (Request $request): Response => (new PlatformAdminJobsController(new RequirePlatformRole(new MembershipRepository($pdo)), new JobAdminRepository($pdo), new JobAdminService($pdo, new JobAttemptRepository($pdo)), new CsrfTokenService(), new AuditLogRepository($pdo), new JobAttemptRepository($pdo)))->action($request, $currentUser));
    $router->post('/platform/admin/domains/action', fn (Request $request): Response => (new PlatformAdminDomainsController(new RequirePlatformRole(new MembershipRepository($pdo)), new DomainAdminRepository($pdo), new DomainAdminService($pdo), new CsrfTokenService(), new AuditLogRepository($pdo)))->action($request, $currentUser));
    $router->get('/platform/admin/email-outbox', fn (Request $request): Response => (new PlatformAdminEmailOutboxController(new RequirePlatformRole(new MembershipRepository($pdo)), new EmailOutboxRepository($pdo)))->index($request, $currentUser));
    $router->get('/platform/admin/audit-log', fn (Request $request): Response => (new PlatformAdminAuditLogController(new RequirePlatformRole(new MembershipRepository($pdo)), new AuditLogRepository($pdo)))->index($request, $currentUser));
    $router->get('/platform/admin/audit-log.csv', fn (Request $request): Response => (new PlatformAdminAuditLogController(new RequirePlatformRole(new MembershipRepository($pdo)), new AuditLogRepository($pdo)))->export($request, $currentUser));
    $router->get('/platform/admin/platform-settings', fn (Request $request): Response => (new PlatformAdminSettingsController(new RequirePlatformRole(new MembershipRepository($pdo)), new PlatformSettingsRepository($pdo), new CsrfTokenService(), new AuditLogRepository($pdo)))->edit($request, $currentUser));
    $router->post('/platform/admin/platform-settings', fn (Request $request): Response => (new PlatformAdminSettingsController(new RequirePlatformRole(new MembershipRepository($pdo)), new PlatformSettingsRepository($pdo), new CsrfTokenService(), new AuditLogRepository($pdo)))->update($request, $currentUser));
    $router->get('/platform/admin/signup-codes', fn (Request $request): Response => (new PlatformAdminSignupCodesController(new RequirePlatformRole(new MembershipRepository($pdo)), new SignupCodeRepository($pdo), new CsrfTokenService(), new AuditLogRepository($pdo), new App\Platform\Email\EmailOutboxRepository($pdo)))->index($request, $currentUser));
    $router->post('/platform/admin/signup-codes/create', fn (Request $request): Response => (new PlatformAdminSignupCodesController(new RequirePlatformRole(new MembershipRepository($pdo)), new SignupCodeRepository($pdo), new CsrfTokenService(), new AuditLogRepository($pdo), new App\Platform\Email\EmailOutboxRepository($pdo)))->create($request, $currentUser));
    $router->post('/platform/admin/signup-codes/send', fn (Request $request): Response => (new PlatformAdminSignupCodesController(new RequirePlatformRole(new MembershipRepository($pdo)), new SignupCodeRepository($pdo), new CsrfTokenService(), new AuditLogRepository($pdo), new App\Platform\Email\EmailOutboxRepository($pdo)))->send($request, $currentUser));
    $router->get('/auth/google', fn (Request $request): Response => (new OAuthController(new TenantSignupService($pdo)))->redirect($request, 'google'));
    $router->get('/auth/google/callback', fn (Request $request): Response => (new OAuthController(new TenantSignupService($pdo)))->callback($request, 'google'));
    $router->get('/auth/facebook', fn (Request $request): Response => (new OAuthController(new TenantSignupService($pdo)))->redirect($request, 'facebook'));
    $router->get('/auth/facebook/callback', fn (Request $request): Response => (new OAuthController(new TenantSignupService($pdo)))->callback($request, 'facebook'));
    $router->get('/login', fn (Request $request): Response => $passwordAuthController->loginForm($request));
    $router->post('/login/password', fn (Request $request): Response => $passwordAuthController->loginPassword($request));
    $router->post('/login', fn (Request $request): Response => $passwordAuthController->loginPassword($request));
    $router->get('/me', fn (Request $request): Response => new Response('', 302, ['Location' => '/platform/admin']));
    $router->post('/logout', fn (Request $request): Response => $passwordAuthController->logout($request));
    $router->get('/api/admin/tenants', fn (Request $request): Response => (new AdminApiController($pdo, $bearerToken))->tenants($request));
    $router->post('/api/admin/tenants', fn (Request $request): Response => (new AdminApiController($pdo, $bearerToken))->tenants($request));
    $router->get('/api/admin/tenants/{id}', fn (Request $request, array $params): Response => (new AdminApiController($pdo, $bearerToken))->tenant($request, (int) ($params['id'] ?? 0)));
    $router->post('/api/admin/tenants/{id}', fn (Request $request, array $params): Response => (new AdminApiController($pdo, $bearerToken))->tenant($request, (int) ($params['id'] ?? 0)));
    $router->get('/api/admin/tenants/{id}/settings', fn (Request $request, array $params): Response => (new AdminApiController($pdo, $bearerToken))->tenantSettings($request, (int) ($params['id'] ?? 0)));
    $router->post('/api/admin/tenants/{id}/settings', fn (Request $request, array $params): Response => (new AdminApiController($pdo, $bearerToken))->tenantSettings($request, (int) ($params['id'] ?? 0)));
    $router->get('/api/admin/tenants/{id}/{entity}', fn (Request $request, array $params): Response => (new AdminApiController($pdo, $bearerToken))->collection($request, (int) ($params['id'] ?? 0), (string) ($params['entity'] ?? '')));
    $router->post('/api/admin/tenants/{id}/{entity}', fn (Request $request, array $params): Response => (new AdminApiController($pdo, $bearerToken))->collection($request, (int) ($params['id'] ?? 0), (string) ($params['entity'] ?? '')));
    $router->post('/api/admin/tenants/{id}/{entity}/{item_id}', fn (Request $request, array $params): Response => (new AdminApiController($pdo, $bearerToken))->item($request, (int) ($params['id'] ?? 0), (string) ($params['entity'] ?? ''), (int) ($params['item_id'] ?? 0)));
    $router->add('DELETE', '/api/admin/tenants/{id}/{entity}/{item_id}', fn (Request $request, array $params): Response => (new AdminApiController($pdo, $bearerToken))->item($request, (int) ($params['id'] ?? 0), (string) ($params['entity'] ?? ''), (int) ($params['item_id'] ?? 0)));
    $router->get('/api/me', fn (Request $request): Response => (new MeController())->show($request, $bearerToken));

    $router->dispatch($request)->send();
} catch (Throwable $e) {
    Response::html(
        "<h1>Application error</h1>\n<pre>" . htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8') . "</pre>\n",
        500
    )->send();
}

// End of file.
