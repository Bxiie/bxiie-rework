<?php

declare(strict_types=1);

/**
 * Main HTTP front controller for local and production web requests.
 */

use App\Http\Controllers\Api\MeController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Api\TenantMeController;
use App\Http\Controllers\Auth\PasswordAuthController;
use App\Http\Controllers\Platform\Admin\DashboardController as PlatformAdminDashboardController;
use App\Http\Controllers\Platform\Admin\SettingsController as PlatformAdminSettingsController;
use App\Http\Controllers\Platform\Admin\RoutesController as PlatformAdminRoutesController;
use App\Http\Controllers\Platform\Admin\EmailOutboxController as PlatformAdminEmailOutboxController;
use App\Http\Controllers\Platform\Admin\DomainsController as PlatformAdminDomainsController;
use App\Http\Controllers\Platform\Admin\JobsController as PlatformAdminJobsController;
use App\Http\Controllers\Platform\Admin\WorkersController as PlatformAdminWorkersController;
use App\Http\Controllers\Platform\Admin\AuditLogController as PlatformAdminAuditLogController;
use App\Http\Controllers\Platform\Admin\TenantsController as PlatformAdminTenantsController;
use App\Http\Controllers\Platform\HomeController as PlatformHomeController;
use App\Http\Controllers\Tenant\HomeController as TenantHomeController;
use App\Http\Controllers\Tenant\SignupController;
use App\Http\Controllers\Tenant\Admin\DashboardController as TenantAdminDashboardController;
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

    if ($tenant) {
        $tenantSettings = new TenantSettingsRepository($pdo);
        $emailOutbox = new EmailOutboxRepository($pdo);
        $csrf = new CsrfTokenService();

        $tenantController = new TenantHomeController(
            new TenantSettingsRepository($pdo),
            new ArtworkReadRepository($pdo),
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
        $router->get('/', fn (Request $request): Response => $tenantController->home($request, $tenant));
        $router->get('/portfolio', fn (Request $request): Response => $tenantController->portfolio($request, $tenant));
        $router->get('/artwork/{slug}', fn (Request $request, array $params): Response => $tenantController->artwork($request, $tenant, (string) $params['slug']));
        $router->get('/about', fn (Request $request): Response => $tenantController->about($request, $tenant));
        $router->get('/login', fn (Request $request): Response => (new LoginController(new PasswordAuthService(new UserRepository($pdo), new UserIdentityRepository($pdo), new PasswordHasher(), new SessionRepository($pdo), new SessionTokenService()), new CsrfTokenService()))->show($request));
        $router->post('/login', fn (Request $request): Response => (new LoginController(new PasswordAuthService(new UserRepository($pdo), new UserIdentityRepository($pdo), new PasswordHasher(), new SessionRepository($pdo), new SessionTokenService()), new CsrfTokenService()))->login($request));
        $router->get('/logout', fn (Request $request): Response => (new LoginController(new PasswordAuthService(new UserRepository($pdo), new UserIdentityRepository($pdo), new PasswordHasher(), new SessionRepository($pdo), new SessionTokenService()), new CsrfTokenService()))->logout($request));
        $router->get('/admin', fn (Request $request): Response => (new TenantAdminDashboardController(new RequireTenantRoleBrowser(new MembershipRepository($pdo))))->index($request, $tenant, $currentUser));
        $router->get('/admin/routes', fn (Request $request): Response => (new TenantAdminRoutesController(new RequireTenantRoleBrowser(new MembershipRepository($pdo))))->index($request, $tenant, $currentUser));
        $router->get('/admin/audit-log', fn (Request $request): Response => (new TenantAdminAuditLogController(new RequireTenantRoleBrowser(new MembershipRepository($pdo)), new AuditLogRepository($pdo)))->index($request, $tenant, $currentUser));
        $router->get('/admin/audit-log.csv', fn (Request $request): Response => (new TenantAdminAuditLogController(new RequireTenantRoleBrowser(new MembershipRepository($pdo)), new AuditLogRepository($pdo)))->export($request, $tenant, $currentUser));
        $router->get('/admin/contact-messages', fn (Request $request): Response => (new TenantAdminContactMessagesController(new RequireTenantRoleBrowser(new MembershipRepository($pdo)), new ContactMessageRepository($pdo), $csrf, new AuditLogRepository($pdo)))->index($request, $tenant, $currentUser));
        $router->get('/admin/contact-messages.csv', fn (Request $request): Response => (new TenantAdminContactMessagesController(new RequireTenantRoleBrowser(new MembershipRepository($pdo)), new ContactMessageRepository($pdo), $csrf, new AuditLogRepository($pdo)))->export($request, $tenant, $currentUser));
        $router->post('/admin/contact-messages/status', fn (Request $request): Response => (new TenantAdminContactMessagesController(new RequireTenantRoleBrowser(new MembershipRepository($pdo)), new ContactMessageRepository($pdo), $csrf, new AuditLogRepository($pdo)))->updateStatus($request, $tenant, $currentUser));
        $router->get('/admin/email-signups', fn (Request $request): Response => (new TenantAdminEmailSignupsController(new RequireTenantRoleBrowser(new MembershipRepository($pdo)), new EmailSignupRepository($pdo), $csrf, new AuditLogRepository($pdo)))->index($request, $tenant, $currentUser));
        $router->get('/admin/email-signups.csv', fn (Request $request): Response => (new TenantAdminEmailSignupsController(new RequireTenantRoleBrowser(new MembershipRepository($pdo)), new EmailSignupRepository($pdo), $csrf, new AuditLogRepository($pdo)))->export($request, $tenant, $currentUser));
        $router->post('/admin/email-signups/consent', fn (Request $request): Response => (new TenantAdminEmailSignupsController(new RequireTenantRoleBrowser(new MembershipRepository($pdo)), new EmailSignupRepository($pdo), $csrf, new AuditLogRepository($pdo)))->updateConsent($request, $tenant, $currentUser));
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

    $router = new Router();
    $router->get('/', fn (Request $request): Response => $platformController->home($request));
    $router->get('/pricing', fn (Request $request): Response => $platformController->pricing($request));
    $router->get('/signup', fn (Request $request): Response => $platformController->signup($request));
    $router->get('/admin', fn (Request $request): Response => (new PlatformAdminDashboardController(new RequirePlatformRole(new MembershipRepository($pdo))))->index($request, $currentUser));
    $router->get('/admin/routes', fn (Request $request): Response => (new PlatformAdminRoutesController(new RequirePlatformRole(new MembershipRepository($pdo))))->index($request, $currentUser));
    $router->get('/admin/tenants', fn (Request $request): Response => (new PlatformAdminTenantsController(new RequirePlatformRole(new MembershipRepository($pdo)), new TenantAdminRepository($pdo)))->index($request, $currentUser));
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
    $router->get('/login', fn (Request $request): Response => $passwordAuthController->loginForm($request));
    $router->post('/login/password', fn (Request $request): Response => $passwordAuthController->loginPassword($request));
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
