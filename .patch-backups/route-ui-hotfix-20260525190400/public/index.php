<?php

declare(strict_types=1);

/**
 * Main HTTP front controller for local and production web requests.
 */

use App\Http\Controllers\Api\MeController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Api\TenantMeController;
use App\Http\Controllers\Auth\PasswordAuthController;
use App\Http\Controllers\Auth\OAuthController;
use App\Http\Controllers\Platform\Admin\DashboardController as PlatformAdminDashboardController;
use App\Http\Controllers\Platform\Admin\PricingController as PlatformAdminPricingController;
use App\Http\Controllers\Platform\Admin\SettingsController as PlatformAdminSettingsController;
use App\Http\Controllers\Platform\Admin\RoutesController as PlatformAdminRoutesController;
use App\Http\Controllers\Platform\Admin\EmailOutboxController as PlatformAdminEmailOutboxController;
use App\Http\Controllers\Platform\Admin\DomainsController as PlatformAdminDomainsController;
use App\Http\Controllers\Platform\Admin\JobsController as PlatformAdminJobsController;
use App\Http\Controllers\Platform\Admin\WorkersController as PlatformAdminWorkersController;
use App\Http\Controllers\Platform\Admin\AuditLogController as PlatformAdminAuditLogController;
use App\Http\Controllers\Platform\Admin\TenantsController as PlatformAdminTenantsController;
use App\Http\Controllers\Platform\HelpController as PlatformHelpController;
use App\Http\Controllers\Platform\Admin\StatsController as PlatformAdminStatsController;
use App\Http\Controllers\Platform\Admin\ContactMessagesController as PlatformAdminContactMessagesController;
use App\Http\Controllers\Platform\HomeController as PlatformHomeController;
use App\Http\Controllers\Platform\MarketingController;
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
use App\Http\Controllers\Tenant\Admin\EngagementController as TenantAdminEngagementController;
use App\Http\Controllers\Tenant\Admin\SettingsController as TenantAdminSettingsController;
use App\Http\Controllers\Tenant\Admin\RoutesController as TenantAdminRoutesController;
use App\Http\Controllers\Tenant\Admin\EmailSignupsController as TenantAdminEmailSignupsController;
use App\Http\Controllers\Tenant\Admin\AuditLogController as TenantAdminAuditLogController;
use App\Http\Controllers\Tenant\Admin\ContactMessagesController as TenantAdminContactMessagesController;
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
use App\Platform\Identity\UserRepository;
use App\Platform\Membership\MembershipRepository;
use App\Platform\Security\RateLimiter;
use App\Platform\Settings\PlatformSettingsRepository;
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

    $sessionRepository = new SessionRepository($pdo);
    $sessionTokens = new SessionTokenService();
    $currentUser = (new CurrentUser($sessionRepository, $sessionTokens))->resolve($request);

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
            new RateLimiter($pdo),
        );

        $contactController = new ContactController(
            new ContactMessageService(
                new ContactMessageRepository($pdo),
                new ContactNotificationService($emailOutbox, $tenantSettings),
            ),
            $csrf,
            new RateLimiter($pdo),
        );

        $signupController = new SignupController(
            new EmailSignupService(
                new EmailSignupRepository($pdo),
                new SignupNotificationService($emailOutbox, $tenantSettings),
            ),
            $csrf,
            new RateLimiter($pdo),
        );

        $router = new Router();
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
        $router->post('/login', fn (Request $request): Response => (new LoginController(new PasswordAuthService(new UserRepository($pdo), new UserIdentityRepository($pdo), new PasswordHasher(), new SessionRepository($pdo), new SessionTokenService()), new CsrfTokenService()))->login($request));
        $router->get('/logout', fn (Request $request): Response => (new LoginController(new PasswordAuthService(new UserRepository($pdo), new UserIdentityRepository($pdo), new PasswordHasher(), new SessionRepository($pdo), new SessionTokenService()), new CsrfTokenService()))->logout($request));
        $router->get('/admin/media', fn (Request $request): Response => (new TenantMediaController($pdo, new RequireTenantRoleBrowser(new MembershipRepository($pdo))))->admin($request, $tenant, $currentUser));
        $router->get('/admin/contact-messages', fn (Request $request): Response => (new TenantAdminEngagementController(new RequireTenantRoleBrowser(new MembershipRepository($pdo)), $pdo, $csrf))->contacts($request, $tenant, $currentUser));
        $router->post('/admin/contact-messages/delete', fn (Request $request): Response => (new TenantAdminEngagementController(new RequireTenantRoleBrowser(new MembershipRepository($pdo)), $pdo, $csrf))->deleteContact($request, $tenant, $currentUser));
        $router->get('/admin/email-signups', fn (Request $request): Response => (new TenantAdminEngagementController(new RequireTenantRoleBrowser(new MembershipRepository($pdo)), $pdo, $csrf))->subscribers($request, $tenant, $currentUser));
        $router->post('/admin/email-signups/import', fn (Request $request): Response => (new TenantAdminEngagementController(new RequireTenantRoleBrowser(new MembershipRepository($pdo)), $pdo, $csrf))->importSubscribers($request, $tenant, $currentUser));
        $router->get('/admin/portfolio-sections', fn (Request $request): Response => (new TenantAdminPortfolioSectionsController(new RequireTenantRoleBrowser(new MembershipRepository($pdo)), $pdo, $csrf))->index($request, $tenant, $currentUser));
        $router->get('/admin/portfolio-sections/edit', fn (Request $request): Response => (new TenantAdminPortfolioSectionsController(new RequireTenantRoleBrowser(new MembershipRepository($pdo)), $pdo, $csrf))->edit($request, $tenant, $currentUser));
        $router->post('/admin/portfolio-sections/edit', fn (Request $request): Response => (new TenantAdminPortfolioSectionsController(new RequireTenantRoleBrowser(new MembershipRepository($pdo)), $pdo, $csrf))->update($request, $tenant, $currentUser));
        $router->get('/admin/events', fn (Request $request): Response => (new TenantAdminEventsController(new RequireTenantRoleBrowser(new MembershipRepository($pdo)), $pdo, $csrf))->index($request, $tenant, $currentUser));
        $router->get('/admin/events/edit', fn (Request $request): Response => (new TenantAdminEventsController(new RequireTenantRoleBrowser(new MembershipRepository($pdo)), $pdo, $csrf))->edit($request, $tenant, $currentUser));
        $router->post('/admin/events/edit', fn (Request $request): Response => (new TenantAdminEventsController(new RequireTenantRoleBrowser(new MembershipRepository($pdo)), $pdo, $csrf))->update($request, $tenant, $currentUser));
        $router->post('/admin/events/order', fn (Request $request): Response => (new TenantAdminEventsController(new RequireTenantRoleBrowser(new MembershipRepository($pdo)), $pdo, new CsrfTokenService()))->order($request, $tenant, $currentUser));
        $router->get('/admin/content', fn (Request $request): Response => (new TenantAdminContentController(new RequireTenantRoleBrowser(new MembershipRepository($pdo)), $tenantSettings, $csrf))->edit($request, $tenant, $currentUser));
        $router->post('/admin/content', fn (Request $request): Response => (new TenantAdminContentController(new RequireTenantRoleBrowser(new MembershipRepository($pdo)), $tenantSettings, $csrf))->update($request, $tenant, $currentUser));
        $router->get('/admin/artworks', fn (Request $request): Response => (new TenantAdminArtworksController(new RequireTenantRoleBrowser(new MembershipRepository($pdo)), $pdo, new AuditLogRepository($pdo)))->index($request, $tenant, $currentUser));
        $router->get('/admin/artworks/edit', fn (Request $request): Response => (new TenantAdminArtworksController(new RequireTenantRoleBrowser(new MembershipRepository($pdo)), $pdo, new AuditLogRepository($pdo)))->edit($request, $tenant, $currentUser));
        $router->post('/admin/artworks/edit', fn (Request $request): Response => (new TenantAdminArtworksController(new RequireTenantRoleBrowser(new MembershipRepository($pdo)), $pdo, new AuditLogRepository($pdo)))->update($request, $tenant, $currentUser));
        $router->post('/admin/artworks/status', fn (Request $request): Response => (new TenantAdminArtworksController(new RequireTenantRoleBrowser(new MembershipRepository($pdo)), $pdo, new AuditLogRepository($pdo)))->updateStatus($request, $tenant, $currentUser));
        $router->post('/admin/artworks/delete', fn (Request $request): Response => (new TenantAdminArtworksController(new RequireTenantRoleBrowser(new MembershipRepository($pdo)), $pdo, new AuditLogRepository($pdo)))->delete($request, $tenant, $currentUser));
        $router->get('/media', fn (Request $request): Response => (new TenantMediaController($pdo))->public($request, $tenant));
        $router->get('/admin/artwork/upload', fn (Request $request): Response => (new TenantAdminArtworkUploadController(new RequireTenantRoleBrowser(new MembershipRepository($pdo)), new CsrfTokenService(), new ArtworkUploadService($pdo), new AuditLogRepository($pdo)))->form($request, $tenant, $currentUser));
        $router->post('/admin/artwork/upload', fn (Request $request): Response => (new TenantAdminArtworkUploadController(new RequireTenantRoleBrowser(new MembershipRepository($pdo)), new CsrfTokenService(), new ArtworkUploadService($pdo), new AuditLogRepository($pdo)))->submit($request, $tenant, $currentUser));
        $router->get('/admin/getting-started', fn (Request $request): Response => (new TenantAdminGettingStartedController(new RequireTenantRoleBrowser(new MembershipRepository($pdo))))->index($request, $tenant, $currentUser));
        $router->get('/login', fn (Request $request): Response => Response::html(AuthPage::login('/login', csrfToken: $csrf->getOrCreate(), brandName: $tenantSettings->get($tenant, 'artist_name', $tenantSettings->get($tenant, 'site_title', $tenant->name)), heading: 'Sign in to ' . $tenantSettings->get($tenant, 'artist_name', $tenantSettings->get($tenant, 'site_title', $tenant->name)))));
        $router->get('/help', fn (Request $request): Response => $helpController->index($request));
        $router->get('/help/{topic}', fn (Request $request, array $params): Response => $helpController->topic($request, (string) $params['topic']));
        $router->get('/developer', fn (Request $request): Response => (new MarketingController($pdo))->developer($request));
        $router->get('/admin/login', fn (Request $request): Response => new Response('', 303, ['Location' => '/login']));
        $router->get('/login', fn (Request $request): Response => Response::html(AuthPage::login('/login', csrfToken: $csrf->getOrCreate(), brandName: $tenantSettings->get($tenant, 'artist_name', $tenantSettings->get($tenant, 'site_title', $tenant->name)), heading: 'Sign in to ' . $tenantSettings->get($tenant, 'artist_name', $tenantSettings->get($tenant, 'site_title', $tenant->name)))));
    $router->get('/register', fn (Request $request): Response => Response::html(AuthPage::register('/register')));
    $router->get('/password/forgot', fn (Request $request): Response => Response::html(AuthPage::forgotPassword('/password/forgot')));
    $router->get('/admin/login', fn (Request $request): Response => new Response('', 303, ['Location' => '/login']));
    $router->get('/admin', fn (Request $request): Response => (new TenantAdminDashboardController($tenantSettings))->index($request, $tenant, $currentUser));
        $router->get('/admin/platform-discovery', fn (Request $request): Response => (new TenantAdminDiscoverySettingsController(new RequireTenantRoleBrowser(new MembershipRepository($pdo)), $tenantSettings, $csrf))->edit($request, $tenant, $currentUser));
        $router->post('/admin/platform-discovery', fn (Request $request): Response => (new TenantAdminDiscoverySettingsController(new RequireTenantRoleBrowser(new MembershipRepository($pdo)), $tenantSettings, $csrf))->update($request, $tenant, $currentUser));
        $router->get('/admin/routes', fn (Request $request): Response => (new TenantAdminRoutesController(new RequireTenantRoleBrowser(new MembershipRepository($pdo))))->index($request, $tenant, $currentUser));
        $router->get('/admin/stats', fn (Request $request): Response => (new TenantAdminStatsController(new RequireTenantRoleBrowser(new MembershipRepository($pdo)), $pdo))->index($request, $tenant, $currentUser));
        $router->get('/admin/audit-log', fn (Request $request): Response => (new TenantAdminAuditLogController(new RequireTenantRoleBrowser(new MembershipRepository($pdo)), new AuditLogRepository($pdo)))->index($request, $tenant, $currentUser));
        $router->get('/admin/audit-log.csv', fn (Request $request): Response => (new TenantAdminAuditLogController(new RequireTenantRoleBrowser(new MembershipRepository($pdo)), new AuditLogRepository($pdo)))->export($request, $tenant, $currentUser));
        $router->get('/admin/contact-messages', fn (Request $request): Response => (new TenantAdminContactMessagesController(new RequireTenantRoleBrowser(new MembershipRepository($pdo)), new ContactMessageRepository($pdo), $csrf, new AuditLogRepository($pdo)))->index($request, $tenant, $currentUser));
        $router->get('/admin/contact-messages.csv', fn (Request $request): Response => (new TenantAdminContactMessagesController(new RequireTenantRoleBrowser(new MembershipRepository($pdo)), new ContactMessageRepository($pdo), $csrf, new AuditLogRepository($pdo)))->export($request, $tenant, $currentUser));
        $router->post('/admin/contact-messages/status', fn (Request $request): Response => (new TenantAdminContactMessagesController(new RequireTenantRoleBrowser(new MembershipRepository($pdo)), new ContactMessageRepository($pdo), $csrf, new AuditLogRepository($pdo)))->updateStatus($request, $tenant, $currentUser));
        $router->get('/admin/email-signups', fn (Request $request): Response => (new TenantAdminEmailSignupsController(new RequireTenantRoleBrowser(new MembershipRepository($pdo)), new EmailSignupRepository($pdo), $csrf, new AuditLogRepository($pdo)))->index($request, $tenant, $currentUser));
        $router->get('/admin/email-signups.csv', fn (Request $request): Response => (new TenantAdminEmailSignupsController(new RequireTenantRoleBrowser(new MembershipRepository($pdo)), new EmailSignupRepository($pdo), $csrf, new AuditLogRepository($pdo)))->export($request, $tenant, $currentUser));
        $router->post('/admin/email-signups/consent', fn (Request $request): Response => (new TenantAdminEmailSignupsController(new RequireTenantRoleBrowser(new MembershipRepository($pdo)), new EmailSignupRepository($pdo), $csrf, new AuditLogRepository($pdo)))->updateConsent($request, $tenant, $currentUser));
        $router->post('/admin/email-signups/delete', fn (Request $request): Response => (new TenantAdminEmailSignupsController(new RequireTenantRoleBrowser(new MembershipRepository($pdo)), new EmailSignupRepository($pdo), $csrf, new AuditLogRepository($pdo)))->delete($request, $tenant, $currentUser));
        $router->get('/admin/settings', fn (Request $request): Response => (new TenantAdminSettingsController(new RequireTenantRoleBrowser(new MembershipRepository($pdo)), $tenantSettings, $csrf, new AuditLogRepository($pdo)))->edit($request, $tenant, $currentUser));
        $router->post('/admin/settings', fn (Request $request): Response => (new TenantAdminSettingsController(new RequireTenantRoleBrowser(new MembershipRepository($pdo)), $tenantSettings, $csrf, new AuditLogRepository($pdo)))->update($request, $tenant, $currentUser));
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
    $router->get('/pricing', fn (Request $request): Response => $platformController->pricing($request));
    $router->get('/signup', fn (Request $request): Response => (new PlatformSignupController(new TenantSignupService($pdo), new PasswordHasher(), new CsrfTokenService(), new SessionRepository($pdo), new SessionTokenService()))->show($request));
    $router->post('/signup', fn (Request $request): Response => (new PlatformSignupController(new TenantSignupService($pdo), new PasswordHasher(), new CsrfTokenService(), new SessionRepository($pdo), new SessionTokenService()))->submit($request));
    $router->get('/', fn (Request $request): Response => $marketingController->home($request));
    $router->get('/directory', fn (Request $request): Response => $marketingController->directory($request));
    $router->get('/signup', fn (Request $request): Response => $marketingController->signup($request));
    $router->get('/contact', fn (Request $request): Response => $marketingController->contact($request));
    $router->post('/contact', fn (Request $request): Response => $marketingController->contact($request));
    $router->get('/help', fn (Request $request): Response => $helpController->index($request));
    $router->get('/help/{topic}', fn (Request $request, array $params): Response => $helpController->topic($request, (string) $params['topic']));
    $router->get('/developer', fn (Request $request): Response => (new HelpController())->developer($request, $currentUser));
    $router->get('/privacy', fn (Request $request): Response => $marketingController->privacy($request));

    $router->get('/admin', fn (Request $request): Response => (new PlatformAdminDashboardController(new RequirePlatformRole(new MembershipRepository($pdo))))->index($request, $currentUser));
    $router->get('/admin/pricing', fn (Request $request): Response => (new PlatformAdminPricingController(new RequirePlatformRole(new MembershipRepository($pdo)), $pdo))->index($request, $currentUser));
    $router->get('/admin/settings', fn (Request $request): Response => new Response('', 302, ['Location' => '/admin/platform-settings']));
    $router->get('/admin/routes', fn (Request $request): Response => (new PlatformAdminRoutesController(new RequirePlatformRole(new MembershipRepository($pdo))))->index($request, $currentUser));
    $router->get('/admin/tenants', fn (Request $request): Response => (new PlatformAdminTenantsController(new RequirePlatformRole(new MembershipRepository($pdo)), new TenantAdminRepository($pdo)))->index($request, $currentUser));
    $router->get('/admin/stats', fn (Request $request): Response => (new PlatformAdminStatsController(new RequirePlatformRole(new MembershipRepository($pdo)), $pdo))->index($request, $currentUser));
    $router->get('/admin/contact-messages', fn (Request $request): Response => (new PlatformAdminContactMessagesController(new RequirePlatformRole(new MembershipRepository($pdo)), $pdo))->index($request, $currentUser));
    $router->get('/admin/domains', fn (Request $request): Response => (new PlatformAdminDomainsController(new RequirePlatformRole(new MembershipRepository($pdo)), new DomainAdminRepository($pdo), new DomainAdminService($pdo), new CsrfTokenService(), new AuditLogRepository($pdo)))->index($request, $currentUser));
    $router->get('/admin/jobs', fn (Request $request): Response => (new PlatformAdminJobsController(new RequirePlatformRole(new MembershipRepository($pdo)), new JobAdminRepository($pdo), new JobAdminService($pdo, new JobAttemptRepository($pdo)), new CsrfTokenService(), new AuditLogRepository($pdo), new JobAttemptRepository($pdo)))->index($request, $currentUser));
    $router->get('/admin/workers', fn (Request $request): Response => (new PlatformAdminWorkersController(new RequirePlatformRole(new MembershipRepository($pdo)), new WorkerHeartbeatRepository($pdo)))->index($request, $currentUser));
    $router->get('/admin/jobs/{id}', fn (Request $request, string $id): Response => (new PlatformAdminJobsController(new RequirePlatformRole(new MembershipRepository($pdo)), new JobAdminRepository($pdo), new JobAdminService($pdo, new JobAttemptRepository($pdo)), new CsrfTokenService(), new AuditLogRepository($pdo), new JobAttemptRepository($pdo)))->show($request, $currentUser, (int) $id));
    $router->post('/admin/jobs/action', fn (Request $request): Response => (new PlatformAdminJobsController(new RequirePlatformRole(new MembershipRepository($pdo)), new JobAdminRepository($pdo), new JobAdminService($pdo, new JobAttemptRepository($pdo)), new CsrfTokenService(), new AuditLogRepository($pdo), new JobAttemptRepository($pdo)))->action($request, $currentUser));
    $router->post('/admin/domains/action', fn (Request $request): Response => (new PlatformAdminDomainsController(new RequirePlatformRole(new MembershipRepository($pdo)), new DomainAdminRepository($pdo), new DomainAdminService($pdo), new CsrfTokenService(), new AuditLogRepository($pdo)))->action($request, $currentUser));
    $router->get('/admin/email-outbox', fn (Request $request): Response => (new PlatformAdminEmailOutboxController(new RequirePlatformRole(new MembershipRepository($pdo)), new EmailOutboxRepository($pdo)))->index($request, $currentUser));
    $router->get('/admin/audit-log', fn (Request $request): Response => (new PlatformAdminAuditLogController(new RequirePlatformRole(new MembershipRepository($pdo)), new AuditLogRepository($pdo)))->index($request, $currentUser));
    $router->get('/admin/audit-log.csv', fn (Request $request): Response => (new PlatformAdminAuditLogController(new RequirePlatformRole(new MembershipRepository($pdo)), new AuditLogRepository($pdo)))->export($request, $currentUser));
    $router->get('/admin/platform-settings', fn (Request $request): Response => (new PlatformAdminSettingsController(new RequirePlatformRole(new MembershipRepository($pdo)), new PlatformSettingsRepository($pdo), new CsrfTokenService(), new AuditLogRepository($pdo)))->edit($request, $currentUser));
    $router->post('/admin/platform-settings', fn (Request $request): Response => (new PlatformAdminSettingsController(new RequirePlatformRole(new MembershipRepository($pdo)), new PlatformSettingsRepository($pdo), new CsrfTokenService(), new AuditLogRepository($pdo)))->update($request, $currentUser));
    $router->get('/auth/google', fn (Request $request): Response => (new OAuthController(new TenantSignupService($pdo)))->redirect($request, 'google'));
    $router->get('/auth/google/callback', fn (Request $request): Response => (new OAuthController(new TenantSignupService($pdo)))->callback($request, 'google'));
    $router->get('/auth/facebook', fn (Request $request): Response => (new OAuthController(new TenantSignupService($pdo)))->redirect($request, 'facebook'));
    $router->get('/auth/facebook/callback', fn (Request $request): Response => (new OAuthController(new TenantSignupService($pdo)))->callback($request, 'facebook'));
    $router->get('/login', fn (Request $request): Response => $passwordAuthController->loginForm($request));
    $router->post('/login/password', fn (Request $request): Response => $passwordAuthController->loginPassword($request));
    $router->post('/login', fn (Request $request): Response => $passwordAuthController->loginPassword($request));
    $router->get('/me', fn (Request $request): Response => $passwordAuthController->me($request, $currentUser));
    $router->post('/logout', fn (Request $request): Response => $passwordAuthController->logout($request));
    $router->get('/api/me', fn (Request $request): Response => (new MeController())->show($request, $bearerToken));

    $router->dispatch($request)->send();
} catch (Throwable $e) {
    Response::html(
        "<h1>Application error</h1>\n<pre>" . htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8') . "</pre>\n",
        500
    )->send();
}

// End of file.
