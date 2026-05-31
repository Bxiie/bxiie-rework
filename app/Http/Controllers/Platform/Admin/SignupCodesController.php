<?php

declare(strict_types=1);

namespace App\Http\Controllers\Platform\Admin;

use App\Http\Middleware\RequirePlatformRole;
use App\Http\Request;
use App\Http\Response;
use App\Http\View\AdminLayout;
use App\Http\View\ErrorPage;
use App\Platform\Audit\AuditLogRepository;
use App\Platform\Email\EmailOutboxRepository;
use App\Platform\Membership\Roles;
use App\Platform\Signup\SignupCodeRepository;
use App\Support\Security\CsrfTokenService;

/**
 * Platform admin UI for one-time and blanket tenant signup passcodes.
 */
final class SignupCodesController
{
    public function __construct(
        private readonly RequirePlatformRole $roles,
        private readonly SignupCodeRepository $codes,
        private readonly CsrfTokenService $csrf,
        private readonly AuditLogRepository $auditLog,
        private readonly EmailOutboxRepository $outbox,
    ) {
    }

    public function index(Request $request, ?array $currentUser): Response
    {
        if (!$this->canManage($currentUser)) {
            return Response::html(ErrorPage::unauthorized('/login', 'Platform admin access required.'), 403);
        }

        $csrf = AdminLayout::escape($this->csrf->getOrCreate());
        $notice = $this->notice((string) ($_GET['notice'] ?? ''));
        $rows = '';
        foreach ($this->codes->listRecent() as $row) {
            $id = (int) $row['id'];
            $code = AdminLayout::escape((string) $row['code']);
            $type = AdminLayout::escape((string) $row['code_type']);
            $label = AdminLayout::escape((string) $row['label']);
            $recipient = AdminLayout::escape((string) ($row['recipient_email'] ?? ''));
            $status = AdminLayout::escape((string) $row['status']);
            $usage = (int) $row['redemption_count'] . ' / ' . (int) $row['max_redemptions'];
            $tenant = AdminLayout::escape(trim((string) ($row['redeemed_tenant_slug'] ?? '')));
            $rows .= <<<HTML
<tr>
    <td><code>{$code}</code><br><small>{$label}</small></td>
    <td>{$type}</td>
    <td>{$recipient}</td>
    <td>{$usage}</td>
    <td>{$status}</td>
    <td>{$tenant}</td>
    <td>
        <form method="post" action="/platform/admin/signup-codes/send">
            <input type="hidden" name="csrf_token" value="{$csrf}">
            <input type="hidden" name="id" value="{$id}">
            <label>Email<br><input type="email" name="recipient_email" value="{$recipient}" required></label>
            <button type="submit">Send invite</button>
        </form>
    </td>
</tr>
HTML;
        }
        if ($rows === '') {
            $rows = '<tr><td colspan="7">No signup codes exist yet.</td></tr>';
        }

        $body = <<<HTML
{$notice}
<section class="admin-panel">
    <h2>Create signup code</h2>
    <form method="post" action="/platform/admin/signup-codes/create" class="admin-form">
        <input type="hidden" name="csrf_token" value="{$csrf}">
        <div class="admin-form-grid">
            <label>Code type
                <select name="code_type">
                    <option value="one_time">One-time code</option>
                    <option value="blanket">Blanket code</option>
                </select>
            </label>
            <label>Label<input type="text" name="label" placeholder="June gallery prospect list"></label>
            <label>Recipient email, optional<input type="email" name="recipient_email"></label>
            <label>Blanket redemption limit<input type="number" name="max_redemptions" min="1" max="10000" value="25"></label>
        </div>
        <button type="submit">Create code</button>
    </form>
</section>
<section class="admin-panel">
    <h2>Prospective tenant email list</h2>
    <p class="admin-muted">Paste one email per line. Choose individual one-time codes or a shared blanket code for the list.</p>
    <form method="post" action="/platform/admin/signup-codes/create" class="admin-form">
        <input type="hidden" name="csrf_token" value="{$csrf}">
        <input type="hidden" name="bulk" value="1">
        <label>Prospective tenant emails<textarea name="bulk_emails" rows="8"></textarea></label>
        <label>Campaign label<input type="text" name="label" placeholder="Prospects"></label>
        <label>Mode<select name="code_type"><option value="one_time">Individual one-time codes</option><option value="blanket">One blanket code for all recipients</option></select></label>
        <label>Blanket redemption limit<input type="number" name="max_redemptions" min="1" max="10000" value="100"></label>
        <button type="submit">Create and queue invites</button>
    </form>
</section>
<div class="admin-table-wrap"><table class="admin-table"><thead><tr><th>Code</th><th>Type</th><th>Recipient</th><th>Use</th><th>Status</th><th>Tenant</th><th>Invite</th></tr></thead><tbody>{$rows}</tbody></table></div>
HTML;

        return Response::html(AdminLayout::render('Signup Codes', $body, 'codes'));
    }

    public function create(Request $request, ?array $currentUser): Response
    {
        if (!$this->canManage($currentUser)) {
            return Response::html(ErrorPage::unauthorized('/login', 'Platform admin access required.'), 403);
        }
        if (!$this->csrf->validate($_POST['csrf_token'] ?? null)) {
            return Response::html('<h1>Invalid CSRF token</h1>', 419);
        }

        if ((string) ($_POST['bulk'] ?? '') === '1') {
            $this->bulkCreate($request, $currentUser);
            return new Response('', 303, ['Location' => '/platform/admin/signup-codes?notice=bulk-created']);
        }

        $code = $this->codes->create(
            kind: (string) ($_POST['code_type'] ?? 'one_time'),
            label: (string) ($_POST['label'] ?? ''),
            recipientEmail: (string) ($_POST['recipient_email'] ?? '') ?: null,
            maxRedemptions: (int) ($_POST['max_redemptions'] ?? 1),
            createdByUserId: isset($currentUser['user_id']) ? (int) $currentUser['user_id'] : null,
        );
        $this->audit($request, $currentUser, 'platform.signup_code.created', (string) ($code['id'] ?? $code['code']), ['code_type' => $code['code_type'] ?? '']);

        return new Response('', 303, ['Location' => '/platform/admin/signup-codes?notice=created']);
    }

    public function send(Request $request, ?array $currentUser): Response
    {
        if (!$this->canManage($currentUser)) {
            return Response::html(ErrorPage::unauthorized('/login', 'Platform admin access required.'), 403);
        }
        if (!$this->csrf->validate($_POST['csrf_token'] ?? null)) {
            return Response::html('<h1>Invalid CSRF token</h1>', 419);
        }
        $id = (int) ($_POST['id'] ?? 0);
        $email = strtolower(trim((string) ($_POST['recipient_email'] ?? '')));
        foreach ($this->codes->listRecent(500) as $row) {
            if ((int) $row['id'] === $id) {
                $this->queueInvite($email, (string) $row['code']);
                $this->audit($request, $currentUser, 'platform.signup_code.invite_queued', (string) $id, ['recipient_email' => $email]);
                break;
            }
        }

        return new Response('', 303, ['Location' => '/platform/admin/signup-codes?notice=invite-queued']);
    }

    private function bulkCreate(Request $request, ?array $currentUser): void
    {
        $emails = preg_split('/[\r\n,;]+/', (string) ($_POST['bulk_emails'] ?? '')) ?: [];
        $emails = array_values(array_unique(array_filter(array_map(static fn ($e) => strtolower(trim((string) $e)), $emails), static fn ($e) => filter_var($e, FILTER_VALIDATE_EMAIL))));
        $type = (string) ($_POST['code_type'] ?? 'one_time');
        if ($type === 'blanket') {
            $code = $this->codes->create('blanket', (string) ($_POST['label'] ?? 'Blanket prospect code'), null, (int) ($_POST['max_redemptions'] ?? count($emails)), isset($currentUser['user_id']) ? (int) $currentUser['user_id'] : null);
            foreach ($emails as $email) {
                $this->queueInvite($email, (string) $code['code']);
            }
            return;
        }
        foreach ($emails as $email) {
            $code = $this->codes->create('one_time', (string) ($_POST['label'] ?? 'Prospect code'), $email, 1, isset($currentUser['user_id']) ? (int) $currentUser['user_id'] : null);
            $this->queueInvite($email, (string) $code['code']);
        }
    }

    private function queueInvite(string $email, string $code): void
    {
        $signupUrl = 'https://artsfol.io/signup?code=' . rawurlencode($code);
        $body = "You are invited to create an ArtsFolio site.\n\nSignup code: {$code}\nCreate your site: {$signupUrl}\n";
        $this->outbox->queue($email, 'Create your ArtsFolio site', $body, nl2br(htmlspecialchars($body, ENT_QUOTES, 'UTF-8')), null, null, null, 'platform.tenant_signup_invite');
    }

    private function notice(string $notice): string
    {
        $text = match ($notice) {
            'created' => 'Signup code created.',
            'bulk-created' => 'Signup codes created and invite emails queued.',
            'invite-queued' => 'Signup invite email queued.',
            default => '',
        };
        return $text === '' ? '' : '<p class="admin-notice admin-notice-success">' . AdminLayout::escape($text) . '</p>';
    }

    private function canManage(?array $currentUser): bool
    {
        return $this->roles->allows($currentUser, [Roles::PLATFORM_OWNER, Roles::PLATFORM_ADMIN]);
    }

    private function audit(Request $request, ?array $currentUser, string $action, string $entityId, array $details): void
    {
        $this->auditLog->record($action, null, isset($currentUser['user_id']) ? (int) $currentUser['user_id'] : null, 'tenant_signup_code', $entityId, $details, $request->server('REMOTE_ADDR'));
    }
}

// End of file.
