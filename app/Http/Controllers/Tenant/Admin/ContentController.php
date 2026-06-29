<?php

declare(strict_types=1);

namespace App\Http\Controllers\Tenant\Admin;


use App\Http\View\ErrorPage;
use App\Http\Middleware\RequireTenantRoleBrowser;
use App\Http\Request;
use App\Http\Response;
use App\Platform\Tenancy\TenantContext;
use App\Support\Security\CsrfTokenService;
use App\Tenant\Settings\TenantSettingsRepository;
use App\Http\Controllers\Tenant\Admin\AdminLayout;
use PDO;

final class ContentController
{
    public function __construct(
        private readonly RequireTenantRoleBrowser $roles,
        private readonly TenantSettingsRepository $settings,
        private readonly CsrfTokenService $csrf,
        private readonly ?PDO $pdo = null,
    ) {
    }

    public function edit(Request $request, TenantContext $tenant, ?array $currentUser): Response
    {
        if (!$this->canManage($currentUser, $tenant)) {
            return Response::html(ErrorPage::unauthorized('/login', 'Tenant admin access required.'), 403);
        }

        $notice = isset($_GET['notice']) ? '<p class="admin-notice">Content saved.</p>' : '';

        $homeIntro = $this->escape($this->settings->get($tenant, 'home_intro', ''));
        $aboutContent = $this->escape($this->settings->get($tenant, 'about_content', ''));
        $contactDetails = $this->escape($this->settings->get($tenant, 'contact_details', ''));
        $instagram = $this->escape($this->settings->get($tenant, 'instagram_url', ''));
        $facebook = $this->escape($this->settings->get($tenant, 'facebook_url', ''));
        $linkedin = $this->escape($this->settings->get($tenant, 'linkedin_url', ''));
        $aboutMediaUuid = (string) $this->settings->get($tenant, 'about_media_uuid', '');
        $contactMediaUuid = (string) $this->settings->get($tenant, 'contact_media_uuid', '');
        $aboutImageOpacity = $this->escape($this->settings->get($tenant, 'about_image_opacity', '1'));
        $contactImageOpacity = $this->escape($this->settings->get($tenant, 'contact_image_opacity', '1'));
        $aboutImagePicker = $this->siteImagePicker($tenant, 'about_media_uuid', $aboutMediaUuid);
        $contactImagePicker = $this->siteImagePicker($tenant, 'contact_media_uuid', $contactMediaUuid);

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
            <label>About image opacity
                <input type="number" name="about_image_opacity" min="0" max="1" step="0.01" value="{$aboutImageOpacity}">
            </label>
            <h3>About image</h3>
            {$aboutImagePicker}
        </section>

        <section class="admin-panel">
            <h2>Contact page</h2>
            <label>Contact details HTML
                <textarea name="contact_details" rows="14">{$contactDetails}</textarea>
            </label>
            <label>Contact image opacity
                <input type="number" name="contact_image_opacity" min="0" max="1" step="0.01" value="{$contactImageOpacity}">
            </label>
            <h3>Contact image</h3>
            {$contactImagePicker}
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
            return Response::html(ErrorPage::unauthorized('/login'), 403);
        }

        if (!$this->csrf->validate((string) ($_POST['csrf_token'] ?? ''))) {
            return new Response('', 303, ['Location' => '/admin/content?error=csrf']);
        }

        $keys = ['home_intro', 'about_content', 'contact_details', 'instagram_url', 'facebook_url', 'linkedin_url', 'about_media_uuid', 'contact_media_uuid', 'about_image_opacity', 'contact_image_opacity'];

        foreach ($keys as $key) {
            $value = trim((string) ($_POST[$key] ?? ''));
            if (in_array($key, ['about_media_uuid', 'contact_media_uuid'], true)) {
                $value = $this->safeSiteImageMediaUuid($tenant, $value);
            }
            if (in_array($key, ['about_image_opacity', 'contact_image_opacity'], true)) {
                $value = $this->safeOpacity($value, '1');
            }
            $this->settings->set($tenant, $key, $value);
        }

        return new Response('', 303, ['Location' => '/admin/content?notice=saved']);
    }
    private function canManage(?array $currentUser, TenantContext $tenant): bool
    {
        return $this->roles->allows($currentUser, $tenant, ['tenant_owner', 'tenant_admin', 'owner', 'admin']);
    }


    /**
     * Builds a thumbnail picker from published artwork marked as Site Images.
     */

private function safeOpacity(string $value, string $default): string
    {
        $opacity = is_numeric($value) ? (float) $value : (float) $default;
        $opacity = max(0.0, min(1.0, $opacity));

        return rtrim(rtrim(sprintf('%.2F', $opacity), '0'), '.');
    }


    /**
     * Builds a thumbnail radio picker from tenant artworks marked as Site Images.
     *
     * Admin pickers include draft and published site images because site assets
     * are reusable design assets, not necessarily public portfolio entries.
     */

/**
     * Normalizes site image media UUIDs so arbitrary form values cannot be
     * persisted as public design assets.
     */

private function escape(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
    }

    /**
     * Builds a collapsed thumbnail picker from tenant artworks marked as Site Images.
     *
     * Admin pickers include every non-archived Site Images artwork regardless
     * of published/draft status because these are tenant design assets.
     */
    private function siteImagePicker(TenantContext $tenant, string $fieldName, string $selectedUuid): string
    {
        $choices = [[
            'uuid' => '',
            'label' => 'No image',
            'src' => '',
            'selected' => $selectedUuid === '',
        ]];

        if ($this->pdo !== null) {
            $stmt = $this->pdo->prepare(
                "SELECT DISTINCT
                        m.uuid,
                        COALESCE(NULLIF(m.title, ''), NULLIF(a.title, ''), m.original_filename) AS label,
                        a.year_created,
                        a.status
                   FROM media_assets m
                   INNER JOIN artworks a
                      ON a.primary_media_id = m.id
                     AND a.tenant_id = m.tenant_id
                   INNER JOIN artwork_type_assignments ata
                      ON ata.artwork_id = a.id
                   INNER JOIN artwork_types atype
                      ON atype.id = ata.type_id
                     AND atype.code = 'site_images'
                  WHERE m.tenant_id = :tenant_id
                    AND m.is_private = 0
                    AND COALESCE(a.status, '') <> 'archived'
                    AND (m.mime_type LIKE 'image/%' OR m.mime_type IS NULL)
                  ORDER BY label ASC
                  LIMIT 300"
            );
            $stmt->execute(['tenant_id' => $tenant->tenantId]);

            foreach ($stmt->fetchAll() as $row) {
                $uuid = (string) $row['uuid'];
                $status = (string) ($row['status'] ?? 'draft');
                $isPublished = $status === 'published';
                $label = (string) $row['label'];
                if (!empty($row['year_created'])) {
                    $label .= ' · ' . (string) $row['year_created'];
                }
                if (!empty($row['status'])) {
                    $label .= ' · ' . (string) $row['status'];
                }

                $choices[] = [
                    'uuid' => $uuid,
                    'label' => $label,
                    'status' => $status,
                    'is_published' => $isPublished,
                    'src' => $isPublished ? '/admin/media?uuid=' . rawurlencode($uuid) . '&variant=thumb' : '',
                    'selected' => $uuid === $selectedUuid,
                ];
            }
        }

        $selectedChoice = null;
        foreach ($choices as $choice) {
            if ($choice['selected']) {
                $selectedChoice = $choice;
                break;
            }
        }
        $selectedChoice ??= $choices[0] ?? [
            'uuid' => '',
            'label' => 'No image',
            'src' => '',
            'selected' => true,
        ];

        $selectedPreview = $selectedChoice['src'] !== ''
            ? '<img src="' . $this->escape((string) $selectedChoice['src']) . '" alt="">'
            : ((string) ($selectedChoice['status'] ?? '') === 'draft'
                ? '<span class="site-image-picker-draft-warning">draft: will not show in interface until published.</span>'
                : '<span class="site-image-picker-empty">No image</span>');

        $summaryClass = ((string) ($selectedChoice['status'] ?? '') === 'draft') ? 'site-image-picker-summary is-draft' : 'site-image-picker-summary';
        $summary = '<summary class="' . $summaryClass . '">'
            . '<span>Selected image</span>'
            . $selectedPreview
            . '<strong>' . $this->escape((string) $selectedChoice['label']) . '</strong>'
            . '<em>Change</em>'
            . '</summary>';

        $cards = '';
        foreach ($choices as $choice) {
            $uuid = (string) $choice['uuid'];
            $safeUuid = $this->escape($uuid);
            $safeLabel = $this->escape((string) $choice['label']);
            $checked = $choice['selected'] ? ' checked' : '';
            $image = $choice['src'] !== ''
                ? '<img src="' . $this->escape((string) $choice['src']) . '" alt="">'
                : ((string) ($choice['status'] ?? '') === 'draft'
                    ? '<span class="site-image-picker-draft-warning">draft: will not show in interface until published.</span>'
                    : '<span class="site-image-picker-empty">No image</span>');

            $cardClass = ((string) ($choice['status'] ?? '') === 'draft') ? 'site-image-picker-card is-draft' : 'site-image-picker-card';
            $cards .= '<label class="' . $cardClass . '">'
                . '<input type="radio" name="' . $this->escape($fieldName) . '" value="' . $safeUuid . '"' . $checked . '>'
                . $image
                . '<span>' . $safeLabel . '</span>'
                . '</label>';
        }

        return '<details class="site-image-picker-shell">'
            . $summary
            . '<div class="site-image-picker">'
            . $cards
            . '</div>'
            . '</details>';
    }


    /**
     * Normalizes site image media UUIDs so arbitrary form values cannot be
     * persisted as public design assets.
     */
    private function safeSiteImageMediaUuid(TenantContext $tenant, string $value): string
    {
        $value = strtolower(trim($value));
        if ($value === '') {
            return '';
        }
        if (!preg_match('/^[a-f0-9-]{36}$/', $value) || $this->pdo === null) {
            return '';
        }

        $stmt = $this->pdo->prepare(
            "SELECT m.uuid
               FROM media_assets m
               INNER JOIN artworks a
                  ON a.primary_media_id = m.id
                 AND a.tenant_id = m.tenant_id
               INNER JOIN artwork_type_assignments ata
                  ON ata.artwork_id = a.id
               INNER JOIN artwork_types atype
                  ON atype.id = ata.type_id
                 AND atype.code = 'site_images'
              WHERE m.tenant_id = :tenant_id
                AND m.uuid = :media_uuid
                AND m.is_private = 0
                AND COALESCE(a.status, '') <> 'archived'
                AND (m.mime_type LIKE 'image/%' OR m.mime_type IS NULL)
              LIMIT 1"
        );
        $stmt->execute(['tenant_id' => $tenant->tenantId, 'media_uuid' => $value]);

        return $stmt->fetch() ? $value : '';
    }


}

// End of file.
