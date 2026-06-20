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
 * Platform admin UI for one-time, blanket, and free-month tenant signup passcodes.
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
        [$showUsed, $showRevoked, $filterHeaders] = $this->signupCodeFilterState();
        $showUsedChecked = $showUsed ? ' checked' : '';
        $showRevokedChecked = $showRevoked ? ' checked' : '';
        $rows = '';
        foreach ($this->codes->listRecent(200, $showUsed, $showRevoked) as $row) {
            $id = (int) $row['id'];
            $code = AdminLayout::escape((string) $row['code']);
            $type = AdminLayout::escape((string) $row['code_type']);
            $label = AdminLayout::escape((string) $row['label']);
            $recipient = AdminLayout::escape((string) ($row['recipient_email'] ?? ''));
            $statusValue = $this->normalizedStatus($row);
            $status = AdminLayout::escape($statusValue);
            $usage = (int) $row['redemption_count'] . ' / ' . (int) $row['max_redemptions'];
            $freeMonths = (int) ($row['free_access_months'] ?? 0);
            $freeAccess = $freeMonths > 0 ? AdminLayout::escape($freeMonths . ' month' . ($freeMonths === 1 ? '' : 's') . ' any plan') : '';
            $tenant = AdminLayout::escape(trim((string) ($row['redeemed_tenant_slug'] ?? '')));
            $inviteStatus = $this->inviteStatus($row);
            $rows .= <<<HTML
<tr>
    <td><code>{$code}</code><br><small>{$label}</small></td>
    <td>{$type}</td>
    <td>{$recipient}</td>
    <td>{$usage}</td>
    <td>{$freeAccess}</td>
    <td>{$status}</td>
    <td>{$tenant}</td>
    <td>{$inviteStatus}</td>
    <td>
        <form method="post" action="/platform/admin/signup-codes/send">
            <input type="hidden" name="csrf_token" value="{$csrf}">
            <input type="hidden" name="id" value="{$id}">
            <label>Email<br><input type="email" name="recipient_email" value="{$recipient}" required></label>
            <button type="submit">Send invite</button>
        </form>
        <form method="post" action="/platform/admin/signup-codes/revoke" onsubmit="return confirm('Revoke this signup code? It cannot be used after revocation.');">
            <input type="hidden" name="csrf_token" value="{$csrf}">
            <input type="hidden" name="id" value="{$id}">
            <button type="submit" class="button-link-danger">Revoke</button>
        </form>
    </td>
</tr>
HTML;
        }
        if ($rows === '') {
            $rows = '<tr><td colspan="9">No signup codes exist yet.</td></tr>';
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
                    <option value="free_months">Free access code</option>
                </select>
            </label>
            <label>Label<input type="text" name="label" placeholder="June gallery prospect list"></label>
            <label>Recipient email, optional<input type="email" name="recipient_email"></label>
            <label>Blanket/free-code redemption limit<input type="number" name="max_redemptions" min="1" max="10000" value="25"></label>
            <label>Free access months<input type="number" name="free_access_months" min="1" max="60" value="3"></label>
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
        <label>Mode<select name="code_type"><option value="one_time">Individual one-time codes</option><option value="blanket">One blanket code for all recipients</option><option value="free_months">One free-access code for all recipients</option></select></label>
        <label>Blanket/free-code redemption limit<input type="number" name="max_redemptions" min="1" max="10000" value="100"></label>
        <label>Free access months<input type="number" name="free_access_months" min="1" max="60" value="3"></label>
        <button type="submit">Create and queue invites</button>
    </form>
</section>
<section class="admin-panel">
    <h2>Signup code list options</h2>
    <form method="get" action="/platform/admin/signup-codes" class="admin-form">
        <input type="hidden" name="signup_code_filter_saved" value="1">
        <label><input type="checkbox" name="show_used" value="1"{$showUsedChecked}> Show used codes</label>
        <label><input type="checkbox" name="show_revoked" value="1"{$showRevokedChecked}> Show revoked codes</label>
        <button type="submit">Save list options</button>
    </form>
    <p class="admin-muted">These options are saved in this browser and reused when returning to the signup code page.</p>
</section>
<div class="admin-table-wrap"><table class="admin-table"><thead><tr><th>Code</th><th>Type</th><th>Recipient</th><th>Use</th><th>Free access</th><th>Status</th><th>Tenant</th><th>Invite status</th><th>Actions</th></tr></thead><tbody>{$rows}</tbody></table></div>
HTML;

        return Response::html(AdminLayout::render('Signup Codes', $body, 'codes'), 200, $filterHeaders);
    }

    public function create(Request $request, ?array $currentUser): Response
    {
        if (!$this->canManage($currentUser)) {
            return Response::html(ErrorPage::unauthorized('/login', 'Platform admin access required.'), 403);
        }
        if (!$this->csrf->validate($_POST['csrf_token'] ?? null)) {
            return Response::invalidCsrf();
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
            freeAccessMonths: (int) ($_POST['free_access_months'] ?? 0),
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
            return Response::invalidCsrf();
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

    public function revoke(Request $request, ?array $currentUser): Response
    {
        if (!$this->canManage($currentUser)) {
            return Response::html(ErrorPage::unauthorized('/login', 'Platform admin access required.'), 403);
        }
        if (!$this->csrf->validate($_POST['csrf_token'] ?? null)) {
            return Response::invalidCsrf();
        }

        $id = (int) ($_POST['id'] ?? 0);
        if ($id < 1) {
            return Response::html('<h1>Invalid signup code</h1>', 422);
        }

        $this->codes->revoke($id);
        $this->audit($request, $currentUser, 'platform.signup_code.revoked', (string) $id, []);

        return new Response('', 303, ['Location' => '/platform/admin/signup-codes?notice=revoked']);
    }

    private function bulkCreate(Request $request, ?array $currentUser): void
    {
        $emails = preg_split('/[\r\n,;]+/', (string) ($_POST['bulk_emails'] ?? '')) ?: [];
        $emails = array_values(array_unique(array_filter(array_map(static fn ($e) => strtolower(trim((string) $e)), $emails), static fn ($e) => filter_var($e, FILTER_VALIDATE_EMAIL))));
        $type = (string) ($_POST['code_type'] ?? 'one_time');
        if (in_array($type, ['blanket', 'free_months'], true)) {
            $code = $this->codes->create($type, (string) ($_POST['label'] ?? ($type === 'free_months' ? 'Free access prospect code' : 'Blanket prospect code')), null, (int) ($_POST['max_redemptions'] ?? count($emails)), isset($currentUser['user_id']) ? (int) $currentUser['user_id'] : null, (int) ($_POST['free_access_months'] ?? 0));
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


    /**
     * Reads and persists admin signup-code list filters in browser cookies.
     *
     * Query-string values are authoritative when present. Otherwise the page
     * reuses the last saved browser preference. Used and revoked codes are
     * hidden by default to keep the active-code list operational.
     *
     * @return array{0: bool, 1: bool, 2: array<string, array<int, string>|string>}
     */
    private function signupCodeFilterState(): array
    {
        $filterSubmitted = (string) ($_GET['signup_code_filter_saved'] ?? '') === '1';

        $showUsed = $filterSubmitted
            ? (string) ($_GET['show_used'] ?? '0') === '1'
            : (string) ($_COOKIE['artsfolio_signup_codes_show_used'] ?? '0') === '1';
        $showRevoked = $filterSubmitted
            ? (string) ($_GET['show_revoked'] ?? '0') === '1'
            : (string) ($_COOKIE['artsfolio_signup_codes_show_revoked'] ?? '0') === '1';

        $headers = [];
        if ($filterSubmitted) {
            $expires = gmdate('D, d M Y H:i:s', time() + 31536000) . ' GMT';
            $headers['Set-Cookie'] = [
                'artsfolio_signup_codes_show_used=' . ($showUsed ? '1' : '0') . '; Expires=' . $expires . '; Path=/platform/admin/signup-codes; SameSite=Lax; Secure; HttpOnly',
                'artsfolio_signup_codes_show_revoked=' . ($showRevoked ? '1' : '0') . '; Expires=' . $expires . '; Path=/platform/admin/signup-codes; SameSite=Lax; Secure; HttpOnly',
            ];
        }

        return [$showUsed, $showRevoked, $headers];
    }

    /**
     * Normalizes legacy redeemed status to the current user-facing used status.
     */
    private function normalizedStatus(array $row): string
    {
        $status = strtolower(trim((string) ($row['status'] ?? '')));
        if ($status === 'redeemed') {
            return 'used';
        }

        if ($status === 'active' && (int) ($row['redemption_count'] ?? 0) >= (int) ($row['max_redemptions'] ?? 1)) {
            return 'used';
        }

        return $status !== '' ? $status : 'unknown';
    }


    private function inviteStatus(array $row): string
    {
        $queuedCount = (int) ($row['invite_email_count'] ?? 0);
        $sentCount = (int) ($row['invite_email_sent_count'] ?? 0);
        $pendingCount = (int) ($row['invite_email_pending_count'] ?? 0);
        $lastSentAt = trim((string) ($row['invite_email_last_sent_at'] ?? ''));
        $lastQueuedAt = trim((string) ($row['invite_email_last_queued_at'] ?? ''));

        if ($queuedCount <= 0) {
            return '<span class="admin-muted">Not sent</span>';
        }

        if ($sentCount > 0 && $pendingCount <= 0) {
            $detail = $lastSentAt !== '' ? '<br><small>Last sent ' . AdminLayout::escape($lastSentAt) . '</small>' : '';
            return '<strong>Sent</strong> <span class="admin-muted">(' . $sentCount . ' / ' . $queuedCount . ')</span>' . $detail;
        }

        if ($sentCount > 0) {
            $detail = $lastSentAt !== '' ? '<br><small>Last sent ' . AdminLayout::escape($lastSentAt) . '</small>' : '';
            return '<strong>Partially sent</strong> <span class="admin-muted">(' . $sentCount . ' / ' . $queuedCount . ')</span>' . $detail;
        }

        $detail = $lastQueuedAt !== '' ? '<br><small>Queued ' . AdminLayout::escape($lastQueuedAt) . '</small>' : '';
        return '<span class="admin-muted">Queued, not sent yet</span>' . $detail;
    }

    private function notice(string $notice): string
    {
        $text = match ($notice) {
            'created' => 'Signup code created.',
            'bulk-created' => 'Signup codes created and invite emails queued.',
            'invite-queued' => 'Signup invite email queued.',
            'revoked' => 'Signup code revoked.',
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
