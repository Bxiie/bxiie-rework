<?php

declare(strict_types=1);

namespace App\Http\Controllers\Tenant\Admin;

use App\Http\Middleware\RequireTenantRoleBrowser;
use App\Http\Request;
use App\Http\Response;
use App\Http\View\ErrorPage;
use App\Platform\Audit\AuditLogRepository;
use App\Platform\Tenancy\TenantContext;
use App\Support\Flash\FlashMessages;
use App\Support\Security\CsrfTokenService;
use PDO;

/**
 * Resets tenant-wide first-run onboarding state.
 */
final class OnboardingController
{
    public function __construct(
        private readonly RequireTenantRoleBrowser $roles,
        private readonly PDO $pdo,
        private readonly CsrfTokenService $csrf,
        private readonly AuditLogRepository $auditLog,
    ) {
    }

    public function reset(Request $request, TenantContext $tenant, ?array $currentUser): Response
    {
        if (!$this->roles->allows($currentUser, $tenant, ['tenant_owner', 'tenant_admin', 'owner', 'admin'])) {
            return Response::html(ErrorPage::unauthorized('/login', 'Tenant admin access required.'), 403);
        }

        if (!$this->csrf->validate((string) ($_POST['csrf_token'] ?? ''))) {
            return Response::invalidCsrf();
        }

        if ((string) ($_POST['reset_onboarding_confirm'] ?? '') !== '1') {
            return Response::html('<h1>Confirm onboarding reset</h1>', 422);
        }

        $stmt = $this->pdo->prepare(
            "DELETE FROM tenant_settings
             WHERE tenant_id = :tenant_id
               AND (
                    setting_key LIKE 'onboarding\\_%'
                 OR setting_key LIKE 'admin\\_onboarding\\_%'
                 OR setting_key LIKE 'admin\\_tour\\_%'
                 OR setting_key LIKE 'getting\\_started\\_%'
                 OR setting_key LIKE 'dashboard\\_checklist\\_%'
               )"
        );
        $stmt->execute(['tenant_id' => $tenant->tenantId]);

        $this->auditLog->record(
            'tenant.onboarding.reset',
            $tenant->tenantId,
            isset($currentUser['user_id']) ? (int) $currentUser['user_id'] : null,
            'tenant',
            (string) $tenant->tenantId,
            ['deleted_setting_count' => $stmt->rowCount(), 'source' => 'tenant_admin'],
            $request->server('REMOTE_ADDR'),
        );

        FlashMessages::success('Onboarding was reset. The checklist and guided tour are ready to use again.');

        return new Response('', 303, [
            'Location' => '/admin/onboarding?notice=onboarding-reset&onboarding_reset=1',
        ]);
    }
}

// End of file.
