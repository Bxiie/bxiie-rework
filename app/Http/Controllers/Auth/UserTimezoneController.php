<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Http\Request;
use App\Http\Response;
use App\Http\View\AdminLayout;
use App\Http\View\TenantAdminLayout;
use App\Platform\Tenancy\TenantContext;
use App\Platform\Identity\UserRepository;
use App\Tenant\Settings\TenantSettingsRepository;
use App\Support\Security\CsrfTokenService;
use App\Support\Time\UserTimezoneContext;

/**
 * Personal administrator time-zone preference.
 */
final class UserTimezoneController
{
    public function __construct(
        private readonly UserRepository $users,
        private readonly CsrfTokenService $csrf,
        private readonly ?TenantContext $tenant = null,
        private readonly ?TenantSettingsRepository $tenantSettings = null,
    ) {
    }

    public function edit(Request $request, ?array $currentUser): Response
    {
        if (!$currentUser || empty($currentUser['user_id'])) {
            return new Response('', 303, ['Location' => '/login']);
        }

        $selected = UserTimezoneContext::normalize(
            isset($currentUser['timezone']) ? (string) $currentUser['timezone'] : null
        );

        $options = '';

        foreach (UserTimezoneContext::identifiers() as $timezone) {
            $safe = AdminLayout::escape($timezone);
            $selectedAttribute = $timezone === $selected ? ' selected' : '';
            $options .= '<option value="' . $safe . '"' . $selectedAttribute . '>'
                . $safe
                . '</option>';
        }

        $csrf = AdminLayout::escape($this->csrf->getOrCreate());
        $current = AdminLayout::escape(date('M j, Y g:i:s A T'));
        $safeSelected = AdminLayout::escape($selected);

        $body = <<<HTML
<form class="admin-form" method="post" action="/account/timezone">
    <input type="hidden" name="csrf_token" value="{$csrf}">
    <fieldset>
        <legend>Local time zone</legend>
        <label>Time zone
            <select name="timezone" required>{$options}</select>
        </label>
        <p class="admin-muted">Current preference: <strong>{$safeSelected}</strong></p>
        <p class="admin-muted">Current local time: {$current}</p>
        <p class="admin-muted">Administrative timestamps are displayed in this time zone. Stored timestamps remain UTC.</p>
    </fieldset>
    <button type="submit">Save time zone</button>
</form>
HTML;

        return Response::html($this->renderPage(
            title: 'My Time Zone',
            body: $body,
        ));
    }

    /**
     * Uses the tenant admin shell on tenant hosts and the platform shell on the
     * canonical platform host.
     */
    private function renderPage(string $title, string $body): string
    {
        if ($this->tenant !== null && $this->tenantSettings !== null) {
            return (new TenantAdminLayout($this->tenantSettings))->render(
                $this->tenant,
                $title,
                $body,
                ''
            );
        }

        return AdminLayout::render(
            title: $title,
            body: $body,
            active: 'account',
        );
    }

    public function update(Request $request, ?array $currentUser): Response
    {
        if (!$currentUser || empty($currentUser['user_id'])) {
            return new Response('', 303, ['Location' => '/login']);
        }

        if (!$this->csrf->validate($_POST['csrf_token'] ?? null)) {
            return Response::invalidCsrf();
        }

        $timezone = trim((string) ($_POST['timezone'] ?? ''));

        if (!in_array($timezone, UserTimezoneContext::identifiers(), true)) {
            return Response::html(
                $this->renderPage(
                    title: 'Invalid Time Zone',
                    body: '<p>Select a valid IANA time zone.</p>',
                ),
                422,
            );
        }

        $this->users->updateTimezone((int) $currentUser['user_id'], $timezone);

        return new Response('', 303, ['Location' => '/account/timezone']);
    }
}

// End of file.