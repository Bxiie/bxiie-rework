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
use App\Http\Controllers\Platform\Admin\BillingConfigurationController as PlatformAdminBillingConfigurationController;
use App\Http\Controllers\Platform\Admin\BillingHealthController as PlatformAdminBillingHealthController;
use App\Http\Controllers\Platform\Admin\SalesController as PlatformAdminSalesController;
use App\Http\Controllers\Platform\Admin\SalesAnalyticsController as PlatformAdminSalesAnalyticsController;
use App\Http\Controllers\Platform\Admin\SettingsController as PlatformAdminSettingsController;
use App\Http\Controllers\Platform\Admin\SignupCodesController as PlatformAdminSignupCodesController;
use App\Http\Controllers\Platform\Admin\RoutesController as PlatformAdminRoutesController;
use App\Http\Controllers\Platform\Admin\EmailOutboxController as PlatformAdminEmailOutboxController;
use App\Http\Controllers\Platform\Admin\EmailTemplatesController as PlatformAdminEmailTemplatesController;
use App\Http\Controllers\Platform\Admin\EmailSignupsController as PlatformAdminEmailSignupsController;
use App\Http\Controllers\Platform\Admin\DomainsController as PlatformAdminDomainsController;
use App\Http\Controllers\Tenant\Admin\DomainsController as TenantAdminDomainsController;
use App\Http\Controllers\Platform\Admin\JobsController as PlatformAdminJobsController;
use App\Http\Controllers\Platform\Admin\WorkersController as PlatformAdminWorkersController;
use App\Http\Controllers\Platform\Admin\OperationsController as PlatformAdminOperationsController;
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
use App\Platform\Monitoring\OperationsMonitorRepository;
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
    $router->get('/admin', fn (Request $request): Response => new Response('', 302, ['Location' => '/platform/admin']));
    $router->get('/admin/pricing', fn (Request $request): Response => new Response('', 302, ['Location' => '/platform/admin/pricing']));
    $router->get('/admin/settings', fn (Request $request): Response => new Response('', 302, ['Location' => '/platform/admin/platform-settings']));
    $router->get('/admin/platform-settings', fn (Request $request): Response => new Response('', 302, ['Location' => '/platform/admin/platform-settings']));
    $router->get('/admin/routes', fn (Request $request): Response => new Response('', 302, ['Location' => '/platform/admin/routes']));
    $router->get('/admin/tenants', fn (Request $request): Response => new Response('', 302, ['Location' => '/platform/admin/tenants']));
    $router->get('/admin/scale-tenants', fn (Request $request): Response => new Response('', 302, ['Location' => '/platform/admin/scale-tenants']));
    $router->get('/admin/stats', fn (Request $request): Response => new Response('', 302, ['Location' => '/platform/admin/stats']));
    $router->get('/admin/contact-messages', fn (Request $request): Response => new Response('', 302, ['Location' => '/platform/admin/contact-messages']));
    $router->get('/admin/domains', fn (Request $request): Response => (new TenantAdminDomainsController(new RequireTenantRoleBrowser(new MembershipRepository($pdo)), new CsrfTokenService(), $pdo))->index($request, $tenant, $currentUser));
    $router->post('/admin/domains/action', fn (Request $request): Response => (new TenantAdminDomainsController(new RequireTenantRoleBrowser(new MembershipRepository($pdo)), new CsrfTokenService(), $pdo))->action($request, $tenant, $currentUser));
    $router->get('/admin/jobs', fn (Request $request): Response => new Response('', 302, ['Location' => '/platform/admin/jobs']));
    $router->get('/admin/workers', fn (Request $request): Response => new Response('', 302, ['Location' => '/platform/admin/workers']));
    $router->get('/admin/operations', fn (Request $request): Response => new Response('', 302, ['Location' => '/platform/admin/operations']));
    $router->get('/admin/email-outbox', fn (Request $request): Response => new Response('', 302, ['Location' => '/platform/admin/email-outbox']));
    $router->get('/admin/email-templates', fn (Request $request): Response => new Response('', 302, ['Location' => '/platform/admin/email-templates']));
    $router->get('/admin/audit-log', fn (Request $request): Response => new Response('', 302, ['Location' => '/platform/admin/audit-log']));
    $router->get('/admin/audit-log.csv', fn (Request $request): Response => new Response('', 302, ['Location' => '/platform/admin/audit-log.csv']));

    // ARTSFOLIO_PLATFORM_PASSWORD_ROUTES_V6
    $router->get('/password/forgot', fn (Request $request): Response => Response::html(AuthPage::forgotPassword('/password/forgot', (new CsrfTokenService())->getOrCreate())));
    $router->post('/password/forgot', function (Request $request) use ($pdo, $root): Response {
        $csrf = new CsrfTokenService();
        if (!$csrf->validate((string) ($_POST['csrf_token'] ?? ''))) {
            return Response::html(AuthPage::forgotPassword('/password/forgot', $csrf->getOrCreate()) . '<p class="error">The security check expired. Please try again.</p>', 419);
        }

        $email = strtolower(trim((string) ($_POST['email'] ?? '')));
        $resetRateKey = 'auth:password-forgot:' . 'platform' . ':' . hash('sha256', (string) $request->server('REMOTE_ADDR') . '|' . $email);
        if (!(new RateLimiter($pdo))->allow($resetRateKey, 3, 3600)) {
            return Response::html(AuthPage::pageMessage('Password reset requested', 'If that email address exists, a reset link has been queued.'), 202, ['Retry-After' => '3600']);
        }
        if ($email !== '') {
            $reset = (new PasswordResetService($pdo, new UserRepository($pdo), new PasswordHasher(), new PasswordResetTokenRepository($pdo)))->createResetTokenForEmail($email);
            if ($reset) {
                $resetUrl = 'https://' . $request->host() . '/password/reset?token=' . rawurlencode((string) $reset['reset_token']);
                (new LifecycleEmailService(new EmailOutboxRepository($pdo), new TemplateRenderer(), $root . '/template/email'))->queuePasswordReset($email, $resetUrl, (int) $reset['user_id']);
            }
        }

        return Response::html(AuthPage::pageMessage('Password reset requested', 'If that email address exists, a reset link has been queued.'));
    });
    $router->get('/password/reset', function (Request $request): Response {
        $token = (string) ($_GET['token'] ?? '');
        if ($token === '') {
            return Response::html(AuthPage::pageMessage('Password reset link missing', 'This password reset link is missing its token. Please request a new reset link.'), 400);
        }

        return Response::html(AuthPage::resetPassword('/password/reset', $token, (new CsrfTokenService())->getOrCreate()));
    });
    $router->post('/password/reset', function (Request $request) use ($pdo): Response {
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
        if (strlen($password) < 10) {
            return Response::html(AuthPage::resetPassword('/password/reset', $token, $csrf->getOrCreate(), 'Password must be at least 10 characters.'), 422);
        }
        if ($password !== $confirm) {
            return Response::html(AuthPage::resetPassword('/password/reset', $token, $csrf->getOrCreate(), 'Passwords do not match.'), 422);
        }
        try {
            (new PasswordResetService($pdo, new UserRepository($pdo), new PasswordHasher(), new PasswordResetTokenRepository($pdo)))->resetPassword($token, $password);
        } catch (Throwable $e) {
            return Response::html(AuthPage::pageMessage('Password reset failed', 'This password reset link is invalid or expired. Please request a new reset link.'), 400);
        }
        return Response::html(AuthPage::pageMessage('Password updated', 'Your password has been updated. You can now sign in with your new password.'));
    });
    $router->get('/pricing', fn (Request $request): Response => (new PricingController($pdo, new PlatformSettingsRepository($pdo)))->index($request));
    $router->get('/signup', fn (Request $request): Response => (new PlatformSignupController(new TenantSignupService($pdo, new PlatformSettingsRepository($pdo), new SignupCodeRepository($pdo)), new PasswordHasher(), new CsrfTokenService(), new SessionRepository($pdo), new SessionTokenService(), new PlatformSettingsRepository($pdo)))->show($request));
    $router->post('/signup', fn (Request $request): Response => (new PlatformSignupController(new TenantSignupService($pdo, new PlatformSettingsRepository($pdo), new SignupCodeRepository($pdo)), new PasswordHasher(), new CsrfTokenService(), new SessionRepository($pdo), new SessionTokenService(), new PlatformSettingsRepository($pdo)))->submit($request));
    $router->get('/', fn (Request $request): Response => $marketingController->home($request));
    $router->get('/directory', fn (Request $request): Response => (new DirectoryController($pdo))->index($request));
    $router->get('/contact', fn (Request $request): Response => $marketingController->contact($request));
    $router->post('/contact', fn (Request $request): Response => $marketingController->contact($request));
    $router->get('/help/{topic}', fn (Request $request, array $params): Response => $helpController->topic($request, (string) $params['topic']));

    $router->get('/account/timezone', fn (Request $request): Response => (new UserTimezoneController(new UserRepository($pdo), new CsrfTokenService()))->edit($request, $currentUser));
    $router->post('/account/timezone', fn (Request $request): Response => (new UserTimezoneController(new UserRepository($pdo), new CsrfTokenService()))->update($request, $currentUser));
    $router->get('/help', fn (Request $request): Response => (new HelpController())->index($request, $currentUser));
    $router->get('/help/{article}', fn (Request $request, array $params): Response => (new HelpController())->topic($request, $params, $currentUser));
    $router->get('/developer', fn (Request $request): Response => (new HelpController())->developer($request, $currentUser));
    $router->get('/help/developer', fn (Request $request): Response => (new HelpController())->developer($request, $currentUser));
    $router->get('/terms', fn (Request $request): Response => $marketingController->terms($request));
    $router->get('/privacy', fn (Request $request): Response => $marketingController->privacy($request));

    $router->get('/platform/admin', fn (Request $request): Response => (new PlatformAdminDashboardController(new RequirePlatformRole(new MembershipRepository($pdo))))->index($request, $currentUser));
    $router->get('/platform/admin/pricing', fn (Request $request): Response => (new PlatformAdminPricingController(new RequirePlatformRole(new MembershipRepository($pdo)), $pdo, new PlatformSettingsRepository($pdo), new CsrfTokenService(), new AuditLogRepository($pdo)))->index($request, $currentUser));
    $router->get('/platform/admin/billing-health', fn (Request $request): Response => (new PlatformAdminBillingHealthController(new RequirePlatformRole(new MembershipRepository($pdo)), $pdo))->index($request, $currentUser));
    $router->get('/platform/admin/billing-configuration', fn (Request $request): Response => (new PlatformAdminBillingConfigurationController(new RequirePlatformRole(new MembershipRepository($pdo)), $pdo))->index($request, $currentUser));
    $router->get('/platform/admin/sales/analytics', fn (Request $request): Response => (new PlatformAdminSalesAnalyticsController(new RequirePlatformRole(new MembershipRepository($pdo)), new SalesRepository($pdo)))->index($request, $currentUser));
    $router->get('/platform/admin/sales', fn (Request $request): Response => (new PlatformAdminSalesController(new RequirePlatformRole(new MembershipRepository($pdo)), new SalesRepository($pdo)))->index($request, $currentUser));
    $router->post('/platform/admin/pricing', fn (Request $request): Response => (new PlatformAdminPricingController(new RequirePlatformRole(new MembershipRepository($pdo)), $pdo, new PlatformSettingsRepository($pdo), new CsrfTokenService(), new AuditLogRepository($pdo)))->update($request, $currentUser));
    $router->get('/platform/admin/settings', fn (Request $request): Response => new Response('', 302, ['Location' => '/platform/admin/platform-settings']));
    $router->get('/platform/admin/routes', fn (Request $request): Response => (new PlatformAdminRoutesController(new RequirePlatformRole(new MembershipRepository($pdo))))->index($request, $currentUser));
    $router->get('/platform/admin/tenants', fn (Request $request): Response => (new PlatformAdminTenantsController(new RequirePlatformRole(new MembershipRepository($pdo)), new TenantAdminRepository($pdo), new AdminUserRepository($pdo), new PasswordHasher(), new CsrfTokenService(), new AuditLogRepository($pdo)))->index($request, $currentUser));
    $router->get('/platform/admin/scale-tenants', fn (Request $request): Response => (new PlatformAdminScaleTenantsController(new RequirePlatformRole(new MembershipRepository($pdo)), new ScaleTenantFixtureService($pdo, $root), new BackgroundJobRepository($pdo), new CsrfTokenService(), new AuditLogRepository($pdo)))->index($request, $currentUser));
    $router->post('/platform/admin/scale-tenants/create', fn (Request $request): Response => (new PlatformAdminScaleTenantsController(new RequirePlatformRole(new MembershipRepository($pdo)), new ScaleTenantFixtureService($pdo, $root), new BackgroundJobRepository($pdo), new CsrfTokenService(), new AuditLogRepository($pdo)))->create($request, $currentUser));
    $router->post('/platform/admin/scale-tenants/remove', fn (Request $request): Response => (new PlatformAdminScaleTenantsController(new RequirePlatformRole(new MembershipRepository($pdo)), new ScaleTenantFixtureService($pdo, $root), new BackgroundJobRepository($pdo), new CsrfTokenService(), new AuditLogRepository($pdo)))->remove($request, $currentUser));
    $router->get('/platform/admin/tenants/{id}', fn (Request $request, array $params): Response => (new PlatformAdminTenantsController(new RequirePlatformRole(new MembershipRepository($pdo)), new TenantAdminRepository($pdo), new AdminUserRepository($pdo), new PasswordHasher(), new CsrfTokenService(), new AuditLogRepository($pdo)))->show($request, $currentUser, (int) ($params['id'] ?? 0)));
    $router->post('/platform/admin/tenants/users/password', fn (Request $request): Response => (new PlatformAdminTenantsController(new RequirePlatformRole(new MembershipRepository($pdo)), new TenantAdminRepository($pdo), new AdminUserRepository($pdo), new PasswordHasher(), new CsrfTokenService(), new AuditLogRepository($pdo)))->updateTenantUserPassword($request, $currentUser));
    $router->post('/platform/admin/tenants/complementary', fn (Request $request): Response => (new PlatformAdminTenantsController(new RequirePlatformRole(new MembershipRepository($pdo)), new TenantAdminRepository($pdo), new AdminUserRepository($pdo), new PasswordHasher(), new CsrfTokenService(), new AuditLogRepository($pdo)))->updateComplementary($request, $currentUser));
    $router->post('/platform/admin/tenants/onboarding/reset', fn (Request $request): Response => (new PlatformAdminTenantsController(new RequirePlatformRole(new MembershipRepository($pdo)), new TenantAdminRepository($pdo), new AdminUserRepository($pdo), new PasswordHasher(), new CsrfTokenService(), new AuditLogRepository($pdo)))->resetOnboarding($request, $currentUser));
    $router->post('/platform/admin/users/suspend', fn (Request $request): Response => (new PlatformAdminUsersController(new RequirePlatformRole(new MembershipRepository($pdo)), new AdminUserRepository($pdo), new PasswordHasher(), new CsrfTokenService(), new AuditLogRepository($pdo)))->suspend($request, $currentUser));
    $router->post('/platform/admin/users/delete', fn (Request $request): Response => (new PlatformAdminUsersController(new RequirePlatformRole(new MembershipRepository($pdo)), new AdminUserRepository($pdo), new PasswordHasher(), new CsrfTokenService(), new AuditLogRepository($pdo)))->delete($request, $currentUser));
    $router->post('/platform/admin/tenants/suspend', fn (Request $request): Response => (new PlatformAdminTenantsController(new RequirePlatformRole(new MembershipRepository($pdo)), new TenantAdminRepository($pdo), new AdminUserRepository($pdo), new PasswordHasher(), new CsrfTokenService(), new AuditLogRepository($pdo)))->suspend($request, $currentUser));
    $router->post('/platform/admin/tenants/delete', fn (Request $request): Response => (new PlatformAdminTenantsController(new RequirePlatformRole(new MembershipRepository($pdo)), new TenantAdminRepository($pdo), new AdminUserRepository($pdo), new PasswordHasher(), new CsrfTokenService(), new AuditLogRepository($pdo)))->delete($request, $currentUser));

        $router->post('/platform/admin/tenants/status', fn (Request $request): Response => (new PlatformAdminTenantsController(new RequirePlatformRole(new MembershipRepository($pdo)), new TenantAdminRepository($pdo), new AdminUserRepository($pdo), new PasswordHasher(), new CsrfTokenService(), new AuditLogRepository($pdo)))->updateTenantStatus($request, $currentUser));
    $router->get('/platform/admin/users', fn (Request $request): Response => (new PlatformAdminUsersController(new RequirePlatformRole(new MembershipRepository($pdo)), new AdminUserRepository($pdo), new PasswordHasher(), new CsrfTokenService(), new AuditLogRepository($pdo), new EmailOutboxRepository($pdo)))->index($request, $currentUser));
    $router->post('/platform/admin/users/invite', fn (Request $request): Response => (new PlatformAdminUsersController(new RequirePlatformRole(new MembershipRepository($pdo)), new AdminUserRepository($pdo), new PasswordHasher(), new CsrfTokenService(), new AuditLogRepository($pdo), new EmailOutboxRepository($pdo)))->invite($request, $currentUser));
    $router->post('/platform/admin/users/password', fn (Request $request): Response => (new PlatformAdminUsersController(new RequirePlatformRole(new MembershipRepository($pdo)), new AdminUserRepository($pdo), new PasswordHasher(), new CsrfTokenService(), new AuditLogRepository($pdo), new EmailOutboxRepository($pdo)))->updatePassword($request, $currentUser));
    $router->post('/platform/admin/users/resend-invite', fn (Request $request): Response => (new PlatformAdminUsersController(new RequirePlatformRole(new MembershipRepository($pdo)), new AdminUserRepository($pdo), new PasswordHasher(), new CsrfTokenService(), new AuditLogRepository($pdo), new EmailOutboxRepository($pdo)))->resendInvite($request, $currentUser));
        $router->post('/platform/admin/users/status', fn (Request $request): Response => (new PlatformAdminUsersController(new RequirePlatformRole(new MembershipRepository($pdo)), new AdminUserRepository($pdo), new PasswordHasher(), new CsrfTokenService(), new AuditLogRepository($pdo)))->updateStatus($request, $currentUser));
    $router->get('/platform/admin/stats', fn (Request $request): Response => (new PlatformAdminStatsController(new RequirePlatformRole(new MembershipRepository($pdo)), $pdo))->index($request, $currentUser));
    $router->get('/platform/admin/contacts', fn (Request $request): Response => (new PlatformAdminContactMessagesController(new RequirePlatformRole(new MembershipRepository($pdo)), $pdo, new CsrfTokenService(), new AuditLogRepository($pdo), new PlatformContactMessageRepository($pdo)))->index($request, $currentUser));
    $router->get('/platform/admin/contacts.csv', fn (Request $request): Response => (new PlatformAdminContactMessagesController(new RequirePlatformRole(new MembershipRepository($pdo)), $pdo, new CsrfTokenService(), new AuditLogRepository($pdo), new PlatformContactMessageRepository($pdo)))->export($request, $currentUser));
    $router->post('/platform/admin/contacts/status', fn (Request $request): Response => (new PlatformAdminContactMessagesController(new RequirePlatformRole(new MembershipRepository($pdo)), $pdo, new CsrfTokenService(), new AuditLogRepository($pdo), new PlatformContactMessageRepository($pdo)))->updateStatus($request, $currentUser));
    $router->post('/platform/admin/contacts/delete', fn (Request $request): Response => (new PlatformAdminContactMessagesController(new RequirePlatformRole(new MembershipRepository($pdo)), $pdo, new CsrfTokenService(), new AuditLogRepository($pdo), new PlatformContactMessageRepository($pdo)))->delete($request, $currentUser));
    $router->get('/platform/admin/contact-messages', fn (Request $request): Response => new Response('', 302, ['Location' => '/platform/admin/contacts']));
    $router->get('/platform/admin/domains', fn (Request $request): Response => (new PlatformAdminDomainsController(new RequirePlatformRole(new MembershipRepository($pdo)), new DomainAdminRepository($pdo), new DomainAdminService($pdo), new CsrfTokenService(), new AuditLogRepository($pdo)))->index($request, $currentUser));
    $router->get('/platform/admin/jobs', fn (Request $request): Response => (new PlatformAdminJobsController(new RequirePlatformRole(new MembershipRepository($pdo)), new JobAdminRepository($pdo), new JobAdminService($pdo, new JobAttemptRepository($pdo)), new CsrfTokenService(), new AuditLogRepository($pdo), new JobAttemptRepository($pdo)))->index($request, $currentUser));
    $router->get('/platform/admin/operations', fn (Request $request): Response => (new PlatformAdminOperationsController(new RequirePlatformRole(new MembershipRepository($pdo)), new OperationsMonitorRepository($pdo)))->index($request, $currentUser));
    $router->get('/platform/admin/operations/metrics', fn (Request $request): Response => (new PlatformAdminOperationsController(new RequirePlatformRole(new MembershipRepository($pdo)), new OperationsMonitorRepository($pdo)))->metric($request, $currentUser));
    $router->get('/platform/admin/operations/runs/{id}', fn (Request $request, array $params): Response => (new PlatformAdminOperationsController(new RequirePlatformRole(new MembershipRepository($pdo)), new OperationsMonitorRepository($pdo)))->run($request, $currentUser, (int) ($params['id'] ?? 0)));
    $router->get('/platform/admin/workers', fn (Request $request): Response => (new PlatformAdminWorkersController(new RequirePlatformRole(new MembershipRepository($pdo)), new WorkerHeartbeatRepository($pdo)))->index($request, $currentUser));
    $router->get('/platform/admin/jobs/{id}', fn (Request $request, array $params): Response => (new PlatformAdminJobsController(new RequirePlatformRole(new MembershipRepository($pdo)), new JobAdminRepository($pdo), new JobAdminService($pdo, new JobAttemptRepository($pdo)), new CsrfTokenService(), new AuditLogRepository($pdo), new JobAttemptRepository($pdo)))->show($request, $currentUser, (int) ($params['id'] ?? 0)));
    $router->post('/platform/admin/jobs/action', fn (Request $request): Response => (new PlatformAdminJobsController(new RequirePlatformRole(new MembershipRepository($pdo)), new JobAdminRepository($pdo), new JobAdminService($pdo, new JobAttemptRepository($pdo)), new CsrfTokenService(), new AuditLogRepository($pdo), new JobAttemptRepository($pdo)))->action($request, $currentUser));
    $router->post('/platform/admin/domains/action', fn (Request $request): Response => (new PlatformAdminDomainsController(new RequirePlatformRole(new MembershipRepository($pdo)), new DomainAdminRepository($pdo), new DomainAdminService($pdo), new CsrfTokenService(), new AuditLogRepository($pdo)))->action($request, $currentUser));
    $router->get('/platform/admin/email-signups', fn (Request $request): Response => (new PlatformAdminEmailSignupsController(new RequirePlatformRole(new MembershipRepository($pdo)), $pdo))->index($request, $currentUser));
    $router->get('/platform/admin/email-outbox', fn (Request $request): Response => (new PlatformAdminEmailOutboxController(new RequirePlatformRole(new MembershipRepository($pdo)), new EmailOutboxRepository($pdo)))->index($request, $currentUser));
    $router->get('/platform/admin/email-templates', fn (Request $request): Response => (new PlatformAdminEmailTemplatesController(new RequirePlatformRole(new MembershipRepository($pdo)), new CsrfTokenService(), new AuditLogRepository($pdo), $root . '/template/email'))->index($request, $currentUser));
    $router->post('/platform/admin/email-templates', fn (Request $request): Response => (new PlatformAdminEmailTemplatesController(new RequirePlatformRole(new MembershipRepository($pdo)), new CsrfTokenService(), new AuditLogRepository($pdo), $root . '/template/email'))->update($request, $currentUser));
    $router->get('/platform/admin/audit-log', fn (Request $request): Response => (new PlatformAdminAuditLogController(new RequirePlatformRole(new MembershipRepository($pdo)), new AuditLogRepository($pdo)))->index($request, $currentUser));
    $router->get('/platform/admin/audit-log.csv', fn (Request $request): Response => (new PlatformAdminAuditLogController(new RequirePlatformRole(new MembershipRepository($pdo)), new AuditLogRepository($pdo)))->export($request, $currentUser));
    $router->get('/platform/admin/platform-settings', fn (Request $request): Response => (new PlatformAdminSettingsController(new RequirePlatformRole(new MembershipRepository($pdo)), new PlatformSettingsRepository($pdo), new CsrfTokenService(), new AuditLogRepository($pdo)))->edit($request, $currentUser));
    $router->post('/platform/admin/platform-settings', fn (Request $request): Response => (new PlatformAdminSettingsController(new RequirePlatformRole(new MembershipRepository($pdo)), new PlatformSettingsRepository($pdo), new CsrfTokenService(), new AuditLogRepository($pdo)))->update($request, $currentUser));
    $router->get('/platform/admin/signup-codes', fn (Request $request): Response => (new PlatformAdminSignupCodesController(new RequirePlatformRole(new MembershipRepository($pdo)), new SignupCodeRepository($pdo), new CsrfTokenService(), new AuditLogRepository($pdo), new App\Platform\Email\EmailOutboxRepository($pdo)))->index($request, $currentUser));
    $router->post('/platform/admin/signup-codes/create', fn (Request $request): Response => (new PlatformAdminSignupCodesController(new RequirePlatformRole(new MembershipRepository($pdo)), new SignupCodeRepository($pdo), new CsrfTokenService(), new AuditLogRepository($pdo), new App\Platform\Email\EmailOutboxRepository($pdo)))->create($request, $currentUser));
    $router->post('/platform/admin/signup-codes/send', fn (Request $request): Response => (new PlatformAdminSignupCodesController(new RequirePlatformRole(new MembershipRepository($pdo)), new SignupCodeRepository($pdo), new CsrfTokenService(), new AuditLogRepository($pdo), new App\Platform\Email\EmailOutboxRepository($pdo)))->send($request, $currentUser));
    $router->post('/platform/admin/signup-codes/revoke', fn (Request $request): Response => (new PlatformAdminSignupCodesController(new RequirePlatformRole(new MembershipRepository($pdo)), new SignupCodeRepository($pdo), new CsrfTokenService(), new AuditLogRepository($pdo), new App\Platform\Email\EmailOutboxRepository($pdo)))->revoke($request, $currentUser));
    $router->get('/auth/google', fn (Request $request): Response => (new OAuthController($pdo))->redirect($request, 'google'));
    $router->get('/auth/google/callback', fn (Request $request): Response => (new OAuthController($pdo))->callback($request, 'google'));
    $router->get('/auth/facebook', fn (Request $request): Response => (new OAuthController($pdo))->redirect($request, 'facebook'));
    $router->get('/auth/facebook/callback', fn (Request $request): Response => (new OAuthController($pdo))->callback($request, 'facebook'));

    $router->get('/login', fn (Request $request): Response => $passwordAuthController->loginForm($request));
    $router->post('/login/password', fn (Request $request): Response => $passwordAuthController->loginPassword($request));
    $router->post('/login', fn (Request $request): Response => $passwordAuthController->loginPassword($request));
    $router->get('/me', fn (Request $request): Response => new Response('', 302, ['Location' => '/platform/admin']));
    $router->get('/logout', fn (Request $request): Response => $passwordAuthController->logout($request));
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
};

// End of file.
