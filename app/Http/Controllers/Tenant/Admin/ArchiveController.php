<?php

declare(strict_types=1);

namespace App\Http\Controllers\Tenant\Admin;

use App\Http\Middleware\RequireTenantRoleBrowser;
use App\Http\Request;
use App\Http\Response;
use App\Platform\Tenancy\TenantContext;
use App\Support\Security\CsrfTokenService;
use App\Tenant\Archive\ArchivedItemRepository;

final class ArchiveController
{
    public function __construct(
        private readonly RequireTenantRoleBrowser $roles,
        private readonly ArchivedItemRepository $archives,
        private readonly CsrfTokenService $csrf,
    ) {}

    public function restore(Request $request, TenantContext $tenant, ?array $user, string $kind): Response
    {
        if (!$this->roles->allows($user, $tenant, ['tenant_owner', 'tenant_admin', 'owner', 'admin'])) {
            return Response::error(403, 'Tenant admin access required.');
        }
        if ($request->method() !== 'POST') {
            return new Response('', 405, ['Allow' => 'POST']);
        }
        if (!$this->csrf->validate((string) ($_POST['csrf_token'] ?? ''))) {
            return Response::invalidCsrf();
        }
        if (!$this->archives->restore($tenant, $kind, (int) ($_POST['id'] ?? 0), (int) $user['user_id'])) {
            return Response::error(404, 'Archived item not found. It may already have been restored.');
        }
        $destination = $kind === 'artwork'
            ? '/admin/artworks?status=archived&notice=artwork-restored'
            : '/admin/portfolio-sections?view=archived&notice=restored';
        return new Response('', 303, ['Location' => $destination]);
    }
}
