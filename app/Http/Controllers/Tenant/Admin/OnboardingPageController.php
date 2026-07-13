<?php

declare(strict_types=1);

namespace App\Http\Controllers\Tenant\Admin;

use App\Http\Middleware\RequireTenantRoleBrowser;
use App\Http\Request;
use App\Http\Response;
use App\Http\View\ErrorPage;
use App\Http\View\TenantAdminLayout;
use App\Platform\Tenancy\TenantContext;
use App\Support\Security\CsrfTokenService;
use App\Tenant\Settings\TenantSettingsRepository;

/**
 * Central tenant-admin home for onboarding resources and reset controls.
 */
final class OnboardingPageController
{
    public function __construct(
        private readonly RequireTenantRoleBrowser $roles,
        private readonly TenantSettingsRepository $settings,
        private readonly CsrfTokenService $csrf,
    ) {
    }

    public function index(Request $request, TenantContext $tenant, ?array $currentUser): Response
    {
        if (!$this->roles->allows(
            $currentUser,
            $tenant,
            ['tenant_owner', 'tenant_admin', 'owner', 'admin']
        )) {
            return Response::html(
                ErrorPage::unauthorized('/login', 'Tenant admin access required.'),
                403
            );
        }

        $token = TenantAdminLayout::escape($this->csrf->getOrCreate());
        $notice = (string) ($_GET['notice'] ?? '') === 'onboarding-reset'
            ? '<p class="admin-notice admin-notice-success">Onboarding was reset. The checklist and guided tour are ready to use again.</p>'
            : '';

        $body = <<<HTML
{$notice}
<p class="admin-muted">Use this page whenever you want a map of the setup process, a guided walkthrough, or a clean restart of onboarding progress.</p>

<div class="admin-card-grid">
    <section class="admin-card">
        <h2>Onboarding checklist</h2>
        <p>Work through the practical launch sequence for identity, content, portfolio structure, artwork, curation, events, engagement, and final review.</p>
        <p><a class="admin-button" href="/admin/getting-started">Open checklist</a></p>
    </section>

    <section class="admin-card">
        <h2>Guided tour</h2>
        <p>Follow the full new-admin tour with explanations of what to configure, what to verify, and where each tool lives in the sidebar.</p>
        <p><a class="admin-button" href="/help/new-admin-tour">Open guided tour</a></p>
    </section>

    <section class="admin-card">
        <h2>Training resources</h2>
        <p>Use the function index and training-video directory when you need a focused refresher rather than the whole setup sequence.</p>
        <p><a class="admin-button" href="/help/tenant-admin-functions">Open function index</a></p>
        <p><a href="/help/training-videos">View training videos</a></p>
    </section>
</div>

<section class="admin-panel">
    <h2>Reset onboarding</h2>
    <p class="admin-muted">This resets only onboarding checklist and guided-tour state. It does not change your artwork, content, branding, users, domains, sales, or billing.</p>

    <form method="post" action="/admin/onboarding/reset" id="tenant-onboarding-reset-form">
        <input type="hidden" name="csrf_token" value="{$token}">

        <label class="admin-toggle-row" for="reset_onboarding_confirm">
            <span>
                <strong>Reset onboarding state</strong>
                <small>Turn this on, then confirm below.</small>
            </span>
            <input
                type="checkbox"
                id="reset_onboarding_confirm"
                name="reset_onboarding_confirm"
                value="1"
                role="switch"
                aria-describedby="reset-onboarding-help"
            >
        </label>

        <p id="reset-onboarding-help" class="admin-muted">The reset button stays disabled until the switch is on.</p>
        <button type="submit" id="reset-onboarding-button" disabled>Reset onboarding</button>
    </form>
</section>

<script>
(function () {
    var form = document.getElementById('tenant-onboarding-reset-form');
    var toggle = document.getElementById('reset_onboarding_confirm');
    var button = document.getElementById('reset-onboarding-button');

    if (!form || !toggle || !button) {
        return;
    }

    function synchronize() {
        button.disabled = !toggle.checked;
    }

    toggle.addEventListener('change', synchronize);
    synchronize();

    form.addEventListener('submit', function (event) {
        if (!toggle.checked || !window.confirm(
            'Reset onboarding for this site? Your site content and settings will not change.'
        )) {
            event.preventDefault();
        }
    });

    if (new URLSearchParams(window.location.search).get('onboarding_reset') !== '1') {
        return;
    }

    try {
        Object.keys(window.localStorage).forEach(function (key) {
            var normalized = key.toLowerCase();
            if (
                normalized.indexOf('artsfolio') !== -1
                && (
                    normalized.indexOf('onboarding') !== -1
                    || normalized.indexOf('tour') !== -1
                    || normalized.indexOf('checklist') !== -1
                )
            ) {
                window.localStorage.removeItem(key);
            }
        });
    } catch (error) {
        // Server-backed state is authoritative; browser cleanup is best effort.
    }
})();
</script>
HTML;

        return Response::html(
            (new TenantAdminLayout($this->settings))->render(
                $tenant,
                'Onboarding',
                $body,
                'onboarding'
            )
        );
    }
}

// End of file.
