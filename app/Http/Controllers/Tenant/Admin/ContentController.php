<?php

declare(strict_types=1);

namespace App\Http\Controllers\Tenant\Admin;

use App\Http\Middleware\RequireTenantRoleBrowser;
use App\Http\Request;
use App\Http\Response;
use App\Platform\Tenancy\TenantContext;
use App\Support\Security\CsrfTokenService;
use App\Tenant\Settings\TenantSettingsRepository;

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
        if (!$this->roles->allows($currentUser, $tenant, ['tenant_owner', 'tenant_admin', 'owner', 'admin'])) {
            return Response::html('<h1>Forbidden</h1>', 403);
        }

        $token = htmlspecialchars($this->csrf->getOrCreate(), ENT_QUOTES, 'UTF-8');
        $about = htmlspecialchars($this->settings->get($tenant, 'about_content', ''), ENT_QUOTES, 'UTF-8');
        $contact = htmlspecialchars($this->settings->get($tenant, 'contact_details', ''), ENT_QUOTES, 'UTF-8');
        $instagram = htmlspecialchars($this->settings->get($tenant, 'instagram_url', ''), ENT_QUOTES, 'UTF-8');
        $facebook = htmlspecialchars($this->settings->get($tenant, 'facebook_url', ''), ENT_QUOTES, 'UTF-8');
        $linkedin = htmlspecialchars($this->settings->get($tenant, 'linkedin_url', ''), ENT_QUOTES, 'UTF-8');

        return Response::html(<<<HTML
<!doctype html>
<html>
<head><title>Content</title><meta name="viewport" content="width=device-width, initial-scale=1"></head>
<body>
<main>
<p><a href="/admin">&larr; Admin</a></p>
<h1>Content</h1>
<form method="post" action="/admin/content">
<input type="hidden" name="csrf_token" value="{$token}">
<p><label>About content<br><textarea name="about_content" rows="14" style="width:100%">{$about}</textarea></label></p>
<p><label>Contact details<br><textarea name="contact_details" rows="10" style="width:100%">{$contact}</textarea></label></p>
<p><label>Instagram URL<br><input name="instagram_url" value="{$instagram}" style="width:100%"></label></p>
<p><label>Facebook URL<br><input name="facebook_url" value="{$facebook}" style="width:100%"></label></p>
<p><label>LinkedIn URL<br><input name="linkedin_url" value="{$linkedin}" style="width:100%"></label></p>
<button>Save content</button>
</form>
</main>
</body>
</html>
HTML);
    }

    public function update(Request $request, TenantContext $tenant, ?array $currentUser): Response
    {
        if (!$this->roles->allows($currentUser, $tenant, ['tenant_owner', 'tenant_admin', 'owner', 'admin'])) {
            return Response::html('<h1>Forbidden</h1>', 403);
        }

        if (!$this->csrf->isValid((string) ($_POST['csrf_token'] ?? ''))) {
            return Response::html('<h1>Invalid request</h1><p>CSRF token failed.</p>', 419);
        }

        foreach (['about_content', 'contact_details', 'instagram_url', 'facebook_url', 'linkedin_url'] as $key) {
            $this->settings->set($tenant, $key, trim((string) ($_POST[$key] ?? '')));
        }

        return Response::redirect('/admin/content?notice=saved');
    }
}

// End of file.
