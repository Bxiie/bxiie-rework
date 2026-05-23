<?php

declare(strict_types=1);

namespace App\Http\Controllers\Tenant\Admin;

use App\Http\Middleware\RequireTenantRoleBrowser;
use App\Http\Request;
use App\Http\Response;
use App\Platform\Tenancy\TenantContext;
use App\Support\Security\CsrfTokenService;
use App\Tenant\Settings\TenantSettingsRepository;
use App\Http\Controllers\Tenant\Admin\AdminLayout;

final class ContentController
{
    public function __construct(
        private readonly RequireTenantRoleBrowser $roles,
        private readonly TenantSettingsRepository $settings,
        private readonly CsrfTokenService $csrf,
    ) {
    }

    public function edit(Request $request, TenantContext $tenant, ?array $currentUser): Response
    {
        if (!$this->canManage($currentUser, $tenant)) {
            return Response::html('<h1>Forbidden</h1><p>Tenant admin access required.</p>', 403);
        }

        $notice = isset($_GET['notice']) ? '<p class="admin-notice">Content saved.</p>' : '';

        $homeIntro = $this->escape($this->settings->get($tenant, 'home_intro', ''));
        $aboutContent = $this->escape($this->settings->get($tenant, 'about_content', ''));
        $contactDetails = $this->escape($this->settings->get($tenant, 'contact_details', ''));
        $instagram = $this->escape($this->settings->get($tenant, 'instagram_url', ''));
        $facebook = $this->escape($this->settings->get($tenant, 'facebook_url', ''));
        $linkedin = $this->escape($this->settings->get($tenant, 'linkedin_url', ''));

        $csrf = $this->escape($this->csrf->getOrCreate());

        $body = <<<HTML
{$notice}
<form method="post" action="/admin/content" class="admin-form admin-wide-form">
    <input type="hidden" name="csrf_token" value="{$csrf}">

    <div class="admin-form-grid">
        <section class="admin-panel admin-panel-wide">
            <h2>Home page</h2>
            <label>Home page text
                <textarea name="home_intro" rows="7">{$homeIntro}</textarea>
            </label>
        </section>

        <section class="admin-panel">
            <h2>About page</h2>
            <label>About content HTML
                <textarea name="about_content" rows="14">{$aboutContent}</textarea>
            </label>
        </section>

        <section class="admin-panel">
            <h2>Contact page</h2>
            <label>Contact details HTML
                <textarea name="contact_details" rows="14">{$contactDetails}</textarea>
            </label>
        </section>

        <section class="admin-panel admin-panel-wide">
            <h2>Social links</h2>
            <div class="admin-form-grid three">
                <label>Instagram URL
                    <input type="url" name="instagram_url" value="{$instagram}">
                </label>
                <label>Facebook URL
                    <input type="url" name="facebook_url" value="{$facebook}">
                </label>
                <label>LinkedIn URL
                    <input type="url" name="linkedin_url" value="{$linkedin}">
                </label>
            </div>
        </section>
    </div>

    <p><button type="submit">Save content</button></p>
</form>
HTML;

        return Response::html(AdminLayout::render('Content', $body));
    }

    public function update(Request $request, TenantContext $tenant, ?array $currentUser): Response
    {
        if (!$this->roles->allows($currentUser, $tenant, ['tenant_owner', 'tenant_admin', 'owner', 'admin'])) {
            return Response::html('<h1>Forbidden</h1>', 403);
        }

        if (!$this->csrf->validate((string) ($_POST['csrf_token'] ?? ''))) {
            return new Response('', 303, ['Location' => '/admin/content?error=csrf']);
        }

        foreach (['home_intro', 'about_content', 'contact_details', 'instagram_url', 'facebook_url', 'linkedin_url'] as $key) {
            $this->settings->set($tenant, $key, trim((string) ($_POST[$key] ?? '')));
        }

        return new Response('', 303, ['Location' => '/admin/content?notice=saved']);
    }
    private function canManage(?array $currentUser, TenantContext $tenant): bool
    {
        return $this->roles->allows($currentUser, $tenant, ['tenant_owner', 'tenant_admin', 'owner', 'admin']);
    }


}

// End of file.
