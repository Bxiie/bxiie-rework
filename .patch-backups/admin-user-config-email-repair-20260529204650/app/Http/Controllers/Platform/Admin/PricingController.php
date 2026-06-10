<?php

declare(strict_types=1);

namespace App\Http\Controllers\Platform\Admin;

use App\Http\Middleware\RequirePlatformRole;
use App\Http\Request;
use App\Http\Response;
use App\Http\View\AdminLayout;
use App\Http\View\ErrorPage;
use App\Platform\Membership\Roles;
use PDO;

/**
 * Shows platform pricing and plan configuration.
 */
final class PricingController
{
    public function __construct(private readonly RequirePlatformRole $roles, private readonly PDO $pdo) {}

    public function index(Request $request, ?array $currentUser): Response
    {
        if (!$this->roles->allows($currentUser, [Roles::PLATFORM_OWNER, Roles::PLATFORM_ADMIN, Roles::PLATFORM_SUPPORT])) {
            return Response::html(ErrorPage::unauthorized('/login', 'Platform admin access required.'), 403);
        }

        $rows = '';
        if ($this->tableExists('plans')) {
            $stmt = $this->pdo->query('SELECT slug, name, monthly_price_cents, custom_domain_included, is_active FROM plans ORDER BY monthly_price_cents ASC, id ASC');
            foreach ($stmt->fetchAll() as $plan) {
                $price = '$' . number_format(((int) $plan['monthly_price_cents']) / 100, 2);
                $rows .= '<tr><td>' . AdminLayout::escape((string) $plan['slug']) . '</td><td>' . AdminLayout::escape((string) $plan['name']) . '</td><td>' . $price . '</td><td>' . ((int) $plan['custom_domain_included'] ? 'yes' : 'no') . '</td><td>' . ((int) $plan['is_active'] ? 'active' : 'inactive') . '</td></tr>';
            }
        }
        if ($rows === '') { $rows = '<tr><td colspan="5">No plans found.</td></tr>'; }

        return Response::html(AdminLayout::render(title: 'Platform Pricing', body: <<<HTML
<p>Plan data is read from the <code>plans</code> table. Editing is intentionally deferred until billing writes are fully protected by audit and payment-provider reconciliation.</p>
<table class="admin-table"><thead><tr><th>Slug</th><th>Name</th><th>Monthly</th><th>Custom domain</th><th>Status</th></tr></thead><tbody>{$rows}</tbody></table>
<p><a class="admin-button" href="/pricing">View public pricing page</a> <a class="admin-button" href="/platform/admin/platform-settings">Platform settings</a></p>
HTML, active: 'pricing'));
    }

    private function tableExists(string $table): bool
    {
        $stmt = $this->pdo->prepare('SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = :table');
        $stmt->execute(['table' => $table]);
        return (int) $stmt->fetchColumn() > 0;
    }
}

// End of file.
