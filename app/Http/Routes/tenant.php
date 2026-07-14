<?php

declare(strict_types=1);

use App\Http\Controllers\Api\MeController;
use App\Http\Controllers\Api\AdminApiController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Api\TenantMeController;
use App\Http\Controllers\Auth\PasswordAuthController;
use App\Http\Controllers\Auth\UserTimezoneController;
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
use App\Http\Controllers\Tenant\Admin\OnboardingController as TenantAdminOnboardingController;
use App\Http\Controllers\Tenant\Admin\OnboardingPageController as TenantAdminOnboardingPageController;
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
use App\Platform\Auth\PostLoginDestination;
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

/** @return Closure(Router, array<string,mixed>): void */
return static function (Router $router, array $context): void {
    extract($context, EXTR_SKIP);
        $router->get('/error/{code}', fn (Request $request, array $params): Response => Response::error((int) ($params['code'] ?? 500)));



        // Protect every tenant-admin route before route dispatch so unauthenticated
        // users get a login redirect instead of a raw forbidden page.
        if (str_starts_with($request->path(), '/admin') && !in_array($request->path(), ['/admin/login'], true)) {
            $tenantRoleGuard = new RequireTenantRoleBrowser(new MembershipRepository($pdo));
            if (!$currentUser) {
                (new Response('', 303, ['Location' => '/login?notice=admin-login-required']))->send();
                exit;
            }
            $adminRoles = str_starts_with($request->path(), '/admin/curation')
                ? ['tenant_owner', 'tenant_admin', 'owner', 'admin', 'editor']
                : ['tenant_owner', 'tenant_admin', 'owner', 'admin'];
            if (!$tenantRoleGuard->allows($currentUser, $tenant, $adminRoles)) {
                Response::html(ErrorPage::unauthorized('/login', 'Tenant admin access required.'), 403)->send();
                exit;
            }
        }
    $router->get('/account/timezone', fn (Request $request): Response => (new UserTimezoneController(new UserRepository($pdo), new CsrfTokenService(), $tenant, $tenantSettings))->edit($request, $currentUser));
    $router->post('/account/timezone', fn (Request $request): Response => (new UserTimezoneController(new UserRepository($pdo), new CsrfTokenService(), $tenant, $tenantSettings))->update($request, $currentUser));
    $router->get('/help', fn (Request $request): Response => $helpController->index($request, $currentUser));
    $router->get('/help/{article}', fn (Request $request, array $params): Response => $helpController->article($request, (string) ($params['article'] ?? 'getting-started'), $currentUser));
    $router->get('/developer', fn (Request $request): Response => $helpController->developer($request, $currentUser));
    $router->get('/assets/platform-custom.css', fn (Request $request): Response => (new PlatformCssController(new PlatformSettingsRepository($pdo)))->show($request));
        $router->get('/tenant.css', fn (Request $request): Response => (new TenantCssController($tenantSettings))->show($request, $tenant));
        $router->get('/', fn (Request $request): Response => $tenantController->home($request, $tenant));
        $router->get('/portfolio', fn (Request $request): Response => $tenantController->portfolio($request, $tenant));
        $router->post('/curation/add', fn (Request $request): Response => $curationController->add($request, $tenant, $currentUser));
        $router->get('/messages', fn (Request $request): Response => $curationController->messages($request, $tenant, $currentUser));
        $router->get('/artwork/{slug}', fn (Request $request, array $params): Response => $tenantController->artwork($request, $tenant, (string) $params['slug']));
        $router->get('/about', fn (Request $request): Response => $tenantController->about($request, $tenant));
        $router->get('/caddy/ask', fn (Request $request): Response => (new CaddyAskController($pdo))->ask($request));

        // ARTSFOLIO_TENANT_CANONICAL_OAUTH_ROUTES
        // Tenant social-login entry points redirect to the platform OAuth host so
        // Google and Facebook only need https://artsfol.io callbacks registered.
        $tenantOauthRedirect = function (Request $request, string $provider): Response {
            $host = strtolower(trim(explode(':', $request->host(), 2)[0]));
            if ($host === '') {
                $host = 'artsfol.io';
            }
            $scheme = strtolower((string) $request->server('HTTP_X_FORWARDED_PROTO', '')) === 'https'
                || strtolower((string) $request->server('HTTPS', '')) === 'on'
                || (string) $request->server('HTTPS', '') === '1'
                ? 'https'
                : 'http';
            $returnTo = $scheme . '://' . $host . '/admin';
            return new Response('', 303, [
                'Location' => 'https://artsfol.io/auth/' . $provider . '?return_to=' . rawurlencode($returnTo),
            ]);
        };
        $router->get('/auth/google', fn (Request $request): Response => $tenantOauthRedirect($request, 'google'));
        $router->get('/auth/facebook', fn (Request $request): Response => $tenantOauthRedirect($request, 'facebook'));
        $router->post('/login', fn (Request $request): Response => (new LoginController(new PasswordAuthService(new UserRepository($pdo), new UserIdentityRepository($pdo), new PasswordHasher(), new SessionRepository($pdo), new SessionTokenService()), new CsrfTokenService(), $tenantSettings, new RateLimiter($pdo), new PostLoginDestination($pdo, new MembershipRepository($pdo))))->login($request, $tenant));
        $router->get('/logout', fn (Request $request): Response => (new LoginController(new PasswordAuthService(new UserRepository($pdo), new UserIdentityRepository($pdo), new PasswordHasher(), new SessionRepository($pdo), new SessionTokenService()), new CsrfTokenService(), $tenantSettings, new RateLimiter($pdo), new PostLoginDestination($pdo, new MembershipRepository($pdo))))->logout($request));
    $router->get('/help/developer', fn (Request $request): Response => (new HelpController())->developer($request, $currentUser));

        $router->post('/logout', fn (Request $request): Response => (new LoginController(new PasswordAuthService(new UserRepository($pdo), new UserIdentityRepository($pdo), new PasswordHasher(), new SessionRepository($pdo), new SessionTokenService()), new CsrfTokenService(), $tenantSettings, new RateLimiter($pdo), new PostLoginDestination($pdo, new MembershipRepository($pdo))))->logout($request));
        $router->get('/admin/curation', fn (Request $request): Response => $curationController->queue($request, $tenant, $currentUser));
        $router->post('/admin/curation/review', fn (Request $request): Response => $curationController->review($request, $tenant, $currentUser));
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
        $router->get('/admin/artworks/placement', fn (Request $request): Response => (new TenantAdminArtworkPlacementController(new RequireTenantRoleBrowser(new MembershipRepository($pdo)), $pdo, $csrf))->index($request, $tenant, $currentUser));
        $router->post('/admin/artworks/placement', fn (Request $request): Response => (new TenantAdminArtworkPlacementController(new RequireTenantRoleBrowser(new MembershipRepository($pdo)), $pdo, $csrf))->update($request, $tenant, $currentUser));
        $router->get('/admin/portfolio-sections/order', fn (Request $request): Response => (new TenantAdminArtworkPlacementController(new RequireTenantRoleBrowser(new MembershipRepository($pdo)), $pdo, $csrf))->order($request, $tenant, $currentUser));
        $router->post('/admin/portfolio-sections/order', fn (Request $request): Response => (new TenantAdminArtworkPlacementController(new RequireTenantRoleBrowser(new MembershipRepository($pdo)), $pdo, $csrf))->updateOrder($request, $tenant, $currentUser));
        $router->get('/admin/artworks', fn (Request $request): Response => (new TenantAdminArtworksController(new RequireTenantRoleBrowser(new MembershipRepository($pdo)), $pdo, new AuditLogRepository($pdo)))->index($request, $tenant, $currentUser));
        $router->get('/admin/artworks/edit', fn (Request $request): Response => (new TenantAdminArtworksController(new RequireTenantRoleBrowser(new MembershipRepository($pdo)), $pdo, new AuditLogRepository($pdo)))->edit($request, $tenant, $currentUser));
        $router->post('/admin/artworks/edit', fn (Request $request): Response => (new TenantAdminArtworksController(new RequireTenantRoleBrowser(new MembershipRepository($pdo)), $pdo, new AuditLogRepository($pdo)))->update($request, $tenant, $currentUser));
        $router->post('/admin/artworks/status', fn (Request $request): Response => (new TenantAdminArtworksController(new RequireTenantRoleBrowser(new MembershipRepository($pdo)), $pdo, new AuditLogRepository($pdo)))->updateStatus($request, $tenant, $currentUser));
        $router->post('/admin/artworks/directory-thumbnail', fn (Request $request): Response => (new TenantAdminArtworksController(new RequireTenantRoleBrowser(new MembershipRepository($pdo)), $pdo, new AuditLogRepository($pdo)))->updateDirectoryThumbnail($request, $tenant, $currentUser));
        $router->post('/admin/artworks/delete', fn (Request $request): Response => (new TenantAdminArtworksController(new RequireTenantRoleBrowser(new MembershipRepository($pdo)), $pdo, new AuditLogRepository($pdo)))->delete($request, $tenant, $currentUser));
        $router->get('/media', fn (Request $request): Response => (new TenantMediaController($pdo))->public($request, $tenant));
        $router->get('/admin/artwork/upload', fn (Request $request): Response => (new TenantAdminArtworkUploadController(new RequireTenantRoleBrowser(new MembershipRepository($pdo)), new CsrfTokenService(), new ArtworkUploadService($pdo), new AuditLogRepository($pdo), $pdo))->form($request, $tenant, $currentUser));
        $router->post('/admin/artwork/upload', fn (Request $request): Response => (new TenantAdminArtworkUploadController(new RequireTenantRoleBrowser(new MembershipRepository($pdo)), new CsrfTokenService(), new ArtworkUploadService($pdo), new AuditLogRepository($pdo), $pdo))->submit($request, $tenant, $currentUser));
        $router->get('/admin/getting-started', fn (Request $request): Response => (new TenantAdminGettingStartedController(new RequireTenantRoleBrowser(new MembershipRepository($pdo))))->index($request, $tenant, $currentUser));
        $router->get('/admin/onboarding', fn (Request $request): Response => (new TenantAdminOnboardingPageController(new RequireTenantRoleBrowser(new MembershipRepository($pdo)), $tenantSettings, new CsrfTokenService()))->index($request, $tenant, $currentUser));
        $router->post('/admin/onboarding/reset', fn (Request $request): Response => (new TenantAdminOnboardingController(new RequireTenantRoleBrowser(new MembershipRepository($pdo)), $pdo, new CsrfTokenService(), new AuditLogRepository($pdo)))->reset($request, $tenant, $currentUser));
        $router->get('/login', fn (Request $request): Response => (new LoginController(new PasswordAuthService(new UserRepository($pdo), new UserIdentityRepository($pdo), new PasswordHasher(), new SessionRepository($pdo), new SessionTokenService()), $csrf, $tenantSettings, new RateLimiter($pdo), new PostLoginDestination($pdo, new MembershipRepository($pdo))))->show($request, $tenant));
        $router->get('/help/{topic}', fn (Request $request, array $params): Response => $helpController->topic($request, (string) $params['topic']));
        
        $router->get('/password/reset', function (Request $request): Response {
            $csrf = new CsrfTokenService();
            $token = (string) ($_GET['token'] ?? '');

            if ($token === '') {
                return Response::html(AuthPage::pageMessage('Password reset link missing', 'This password reset link is missing its token. Please request a new reset link.'), 400);
            }

            return Response::html(AuthPage::resetPassword('/password/reset', $token, $csrf->getOrCreate()));
        });

        $router->post('/password/reset', function (Request $request) use ($pdo, $tenant): Response {
            $csrf = new CsrfTokenService();
            $token = (string) ($_POST['token'] ?? '');
            $password = (string) ($_POST['password'] ?? '');
            $confirm = (string) ($_POST['password_confirm'] ?? '');

            if (!$csrf->validate((string) ($_POST['csrf_token'] ?? ''))) {
                return Response::html(AuthPage::resetPassword('/password/reset', $token, $csrf->getOrCreate(), 'The security check expired. Please try again.'), 419);
            }

            if ($token === '') {
                return Response::html(AuthPage::pageMessage('Password reset link missing', 'This password reset link is missing its token. Please request a new reset link.'), 400);
            }

            if ($password === '' || strlen($password) < 10) {
                return Response::html(AuthPage::resetPassword('/password/reset', $token, $csrf->getOrCreate(), 'Password must be at least 10 characters.'), 422);
            }

            if ($password !== $confirm) {
                return Response::html(AuthPage::resetPassword('/password/reset', $token, $csrf->getOrCreate(), 'Passwords do not match.'), 422);
            }

            try {
                (new PasswordResetService($pdo, new UserRepository($pdo), new PasswordHasher(), new PasswordResetTokenRepository($pdo)))->resetPasswordForTenant($token, $password, (int) ($tenant->tenantId ?? $tenant->id ?? 0));
            } catch (Throwable $e) {
                return Response::html(AuthPage::pageMessage('Password reset failed', 'This password reset link is invalid or expired. Please request a new reset link.'), 400);
            }

            return Response::html(AuthPage::pageMessage('Password updated', 'Your password has been updated. You can now sign in with your new password.'));
        });
    $router->get('/admin/login', fn (Request $request): Response => new Response('', 303, ['Location' => '/login']));
    $router->get('/register', fn (Request $request): Response => Response::html(AuthPage::register('/register')));
    $router->get('/password/forgot', fn (Request $request): Response => Response::html(AuthPage::forgotPassword('/password/forgot', (new CsrfTokenService())->getOrCreate())));
        $router->post('/password/forgot', function (Request $request) use ($pdo, $root, $tenant, $tenantPasswordResetGuard): Response {
            $csrf = new CsrfTokenService();
            if (!$csrf->validate((string) ($_POST['csrf_token'] ?? ''))) {
                return Response::html(AuthPage::forgotPassword('/password/forgot', $csrf->getOrCreate()) . '<p class="error">The security check expired. Please try again.</p>', 419);
            }

            $email = strtolower(trim((string) ($_POST['email'] ?? '')));
            $resetRateKey = 'auth:password-forgot:' . (string) $tenant->tenantId . ':' . hash('sha256', (string) $request->server('REMOTE_ADDR') . '|' . $email);
            if (!(new RateLimiter($pdo))->allow($resetRateKey, 3, 3600)) {
                return Response::html(AuthPage::pageMessage('Password reset requested', 'If that email address exists for this artist site, a reset link has been queued.'), 202, ['Retry-After' => '3600']);
            }
            if ($email !== '' && $tenantPasswordResetGuard->recipientExists((int) $tenant->tenantId, $email)) {
                $reset = (new PasswordResetService($pdo, new UserRepository($pdo), new PasswordHasher(), new PasswordResetTokenRepository($pdo)))->createResetTokenForTenantEmail($email, (int) ($tenant->tenantId ?? $tenant->id ?? 0));
                if ($reset) {
                    $host = $request->host();
                    $resetUrl = 'https://' . $host . '/password/reset?token=' . rawurlencode((string) $reset['reset_token']);
                    (new LifecycleEmailService(new EmailOutboxRepository($pdo), new TemplateRenderer(), $root . '/template/email'))->queuePasswordReset($email, $resetUrl, (int) $reset['user_id']);
                }
            }

            return Response::html(AuthPage::pageMessage('Password reset requested', 'If that email address exists for this artist site, a reset link has been queued.'));
        });
    $router->get('/admin', fn (Request $request): Response => (new TenantAdminDashboardController($tenantSettings))->index($request, $tenant, $currentUser));
        $router->get('/admin/domains', fn (Request $request): Response => (new TenantAdminDomainsController(new RequireTenantRoleBrowser(new MembershipRepository($pdo)), new CsrfTokenService(), $pdo))->index($request, $tenant, $currentUser));
        $router->post('/admin/domains/action', fn (Request $request): Response => (new TenantAdminDomainsController(new RequireTenantRoleBrowser(new MembershipRepository($pdo)), new CsrfTokenService(), $pdo))->action($request, $tenant, $currentUser));
        $router->get('/admin/billing', fn (Request $request): Response => (new TenantAdminBillingController(new RequireTenantRoleBrowser(new MembershipRepository($pdo)), $pdo))->index($request, $tenant, $currentUser));
        $router->post('/admin/billing/plan', fn (Request $request): Response => (new TenantAdminBillingController(new RequireTenantRoleBrowser(new MembershipRepository($pdo)), $pdo))->updatePlan($request, $tenant, $currentUser));
        $router->post('/admin/billing/portal', fn (Request $request): Response => (new TenantAdminBillingController(new RequireTenantRoleBrowser(new MembershipRepository($pdo)), $pdo))->managePayment($request, $tenant, $currentUser));

    $router->post('/admin/billing/free-access-code', fn (Request $request): Response => (new TenantAdminBillingController(new RequireTenantRoleBrowser(new MembershipRepository($pdo)), $pdo))->applyFreeAccessCode($request, $tenant, $currentUser));
        $router->get('/admin/sales/analytics', fn (Request $request): Response => (new TenantAdminSalesAnalyticsController(new RequireTenantRoleBrowser(new MembershipRepository($pdo)), new SalesRepository($pdo)))->index($request, $tenant, $currentUser));
        $router->get('/admin/sale', fn (Request $request): Response => new Response('', 303, ['Location' => '/admin/sales']));
        $router->get('/admin/sales', fn (Request $request): Response => (new TenantAdminSalesController(new RequireTenantRoleBrowser(new MembershipRepository($pdo)), new SalesRepository($pdo), new CsrfTokenService(), new AuditLogRepository($pdo), new PlatformSettingsRepository($pdo), new EmailOutboxRepository($pdo)))->index($request, $tenant, $currentUser));
        $router->get('/admin/sales/order', fn (Request $request): Response => (new TenantAdminSalesController(new RequireTenantRoleBrowser(new MembershipRepository($pdo)), new SalesRepository($pdo), new CsrfTokenService(), new AuditLogRepository($pdo), new PlatformSettingsRepository($pdo), new EmailOutboxRepository($pdo)))->show($request, $tenant, $currentUser));
        $router->post('/admin/sales/update', fn (Request $request): Response => (new TenantAdminSalesController(new RequireTenantRoleBrowser(new MembershipRepository($pdo)), new SalesRepository($pdo), new CsrfTokenService(), new AuditLogRepository($pdo), new PlatformSettingsRepository($pdo), new EmailOutboxRepository($pdo)))->update($request, $tenant, $currentUser));
        $router->get('/admin/sales/refund', function (Request $request): Response {
            // Direct browser visits to the refund action are safe redirects.
            // Actual Stripe refunds are created only by POST /admin/sales/refund.
            $orderId = (int) ($_GET['order_id'] ?? $_GET['id'] ?? 0);
            $target = $orderId > 0 ? '/admin/sales/order?id=' . $orderId . '&notice=refund_direct' : '/admin/sales?notice=refund_direct';

            return new Response('', 303, ['Location' => $target]);
        });
        $router->get('/admin/sales/refund', fn (Request $request): Response => (new TenantAdminSalesController(new RequireTenantRoleBrowser(new MembershipRepository($pdo)), new SalesRepository($pdo), new CsrfTokenService(), new AuditLogRepository($pdo), new PlatformSettingsRepository($pdo)))->refundGet($request, $tenant, $currentUser));
        $router->post('/admin/sales/refund', fn (Request $request): Response => (new TenantAdminSalesController(new RequireTenantRoleBrowser(new MembershipRepository($pdo)), new SalesRepository($pdo), new CsrfTokenService(), new AuditLogRepository($pdo), new PlatformSettingsRepository($pdo), new EmailOutboxRepository($pdo)))->refund($request, $tenant, $currentUser));
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
        $router->post('/admin/email-signups/update', fn (Request $request): Response => (new TenantAdminEmailSignupsController(new RequireTenantRoleBrowser(new MembershipRepository($pdo)), new EmailSignupRepository($pdo), $csrf, new AuditLogRepository($pdo)))->update($request, $tenant, $currentUser));
        $router->post('/admin/email-signups/import', fn (Request $request): Response => (new TenantAdminEmailSignupsController(new RequireTenantRoleBrowser(new MembershipRepository($pdo)), new EmailSignupRepository($pdo), $csrf, new AuditLogRepository($pdo)))->import($request, $tenant, $currentUser));
        $router->post('/admin/email-signups/delete', fn (Request $request): Response => (new TenantAdminEmailSignupsController(new RequireTenantRoleBrowser(new MembershipRepository($pdo)), new EmailSignupRepository($pdo), $csrf, new AuditLogRepository($pdo)))->delete($request, $tenant, $currentUser));
        $router->get('/admin/settings', fn (Request $request): Response => (new TenantAdminSettingsController(new RequireTenantRoleBrowser(new MembershipRepository($pdo)), $tenantSettings, $csrf, new AuditLogRepository($pdo), $pdo))->edit($request, $tenant, $currentUser));
        $router->post('/admin/settings', fn (Request $request): Response => (new TenantAdminSettingsController(new RequireTenantRoleBrowser(new MembershipRepository($pdo)), $tenantSettings, $csrf, new AuditLogRepository($pdo), $pdo))->update($request, $tenant, $currentUser));
        $router->post('/admin/settings/stripe/connect', fn (Request $request): Response => (new TenantAdminSettingsController(new RequireTenantRoleBrowser(new MembershipRepository($pdo)), $tenantSettings, $csrf, new AuditLogRepository($pdo), $pdo))->connectStripe($request, $tenant, $currentUser));
        $router->get('/admin/settings/stripe/return', fn (Request $request): Response => (new TenantAdminSettingsController(new RequireTenantRoleBrowser(new MembershipRepository($pdo)), $tenantSettings, $csrf, new AuditLogRepository($pdo), $pdo))->stripeConnectReturn($request, $tenant, $currentUser));
        $router->get('/admin/settings/stripe/refresh', fn (Request $request): Response => (new TenantAdminSettingsController(new RequireTenantRoleBrowser(new MembershipRepository($pdo)), $tenantSettings, $csrf, new AuditLogRepository($pdo), $pdo))->stripeConnectRefresh($request, $tenant, $currentUser));
        $router->get('/contact', fn (Request $request): Response => $tenantController->contact($request, $tenant));
        $router->post('/contact', fn (Request $request): Response => $contactController->submit($request, $tenant));
        $router->get('/signup', fn (Request $request): Response => new Response('', 303, ['Location' => '/' . $contactSlug]));
        $router->post('/signup', fn (Request $request): Response => $signupController->submit($request, $tenant));
        $tenantSalesController = new TenantSalesController(new SalesRepository($pdo), new TenantSettingsRepository($pdo), new PlatformSettingsRepository($pdo), new CsrfTokenService(), $pdo);
        $router->get('/cart', fn (Request $request): Response => $tenantSalesController->cart($request, $tenant));
        $router->get('/cart/bridge-pixel', fn (Request $request): Response => $tenantSalesController->bridgePixel($request, $tenant));
        $router->get('/cart/bridge', fn (Request $request): Response => $tenantSalesController->bridge($request, $tenant));
        $router->post('/cart/add', fn (Request $request): Response => $tenantSalesController->add($request, $tenant));
        $router->post('/cart/update', fn (Request $request): Response => $tenantSalesController->update($request, $tenant));
        $router->post('/cart/remove', fn (Request $request): Response => $tenantSalesController->remove($request, $tenant));
        $router->post('/cart/checkout', fn (Request $request): Response => $tenantSalesController->checkout($request, $tenant));
        $router->get('/checkout/success', fn (Request $request): Response => $tenantSalesController->success($request, $tenant));
        $router->get('/api/me', fn (Request $request): Response => (new TenantMeController(tenantRoles: new RequireTenantRole(new MembershipRepository($pdo)), auditLog: new AuditLogRepository($pdo)))->show($request, $bearerToken, $tenant));
};

// End of file.
