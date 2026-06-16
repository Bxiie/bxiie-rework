<?php

declare(strict_types=1);

namespace App\Http\Controllers\Tenant;

use App\Http\Request;
use App\Http\Response;
use App\Platform\Tenancy\TenantContext;
use App\Services\FirstPartyCaptcha;
use App\Support\Security\CsrfTokenService;
use App\Tenant\Artwork\ArtworkReadRepository;
use App\Tenant\Settings\TenantSettingsRepository;
use PDO;
use Throwable;

/**
 * Handles tenant public site routes.
 */
final class HomeController
{
    public function __construct(
        private readonly TenantSettingsRepository $settings,
        private readonly ArtworkReadRepository $artworks,
        private readonly PDO $pdo,
        private readonly ?CsrfTokenService $csrf = null,
    ) {
    }

    public function home(Request $request, TenantContext $tenant): Response
    {
        $this->track($request, $tenant, 'page_view');
        $siteTitle = $this->escape($this->settings->get($tenant, 'site_title', $tenant->name));
        $artistName = $this->escape($this->settings->get($tenant, 'artist_name', $this->settings->get($tenant, 'site_title', $tenant->name)));
        $homeIntro = (string) $this->settings->get(
            $tenant,
            'home_intro',
            'Contemporary mixed-media work, archival textures, fragments, signals, and beautiful static from the machine room of memory.'
        );

        $items = $this->artworks->latestPublished($tenant, 12);

        $body = <<<HTML
<section class="hero">
    <h1>{$artistName}</h1>
    <div class="prose">{$homeIntro}</div>
</section>
HTML;

        if ($items) {
            $body .= "<section class=\"grid home-grid\">
";
            foreach ($items as $item) {
                $title = $this->escape((string) $item['title']);
                $slug = rawurlencode((string) $item['slug']);
                $meta = $this->escape(trim((string) (($item['medium'] ?? '') . ' ' . ($item['year_created'] ?? ''))));
                $image = '';

                if (!empty($item['media_uuid'])) {
                    $src = '/media?uuid=' . rawurlencode((string) $item['media_uuid']);
                    $alt = $this->escape((string) ($item['media_alt_text'] ?? $item['title']));
                    $image = "<img src=\"{$src}\" alt=\"{$alt}\" loading=\"lazy\">";
                }

                $body .= <<<HTML
<a class="card artwork-card" href="/artwork/{$slug}">
    {$image}
    <span>{$title}</span>
    <small>{$meta}</small>
</a>

HTML;
            }
            $body .= "</section>
";
        }

        return Response::html($this->layout(
            tenant: $tenant,
            title: $siteTitle,
            body: $body,
        ));
    }

    public function portfolio(Request $request, TenantContext $tenant): Response
    {
        $this->track($request, $tenant, 'portfolio_view');
        $sectionSlug = trim((string) ($_GET['section'] ?? ''));
        $sections = $this->artworks->activeSections($tenant);
        $displayOrder = (string) $this->settings->get($tenant, 'artwork_display_order', 'date_desc');
        $items = $sectionSlug !== ''
            ? $this->artworks->publishedForSection($tenant, $sectionSlug, 240, $displayOrder)
            : $this->artworks->publishedOrdered($tenant, 240, $displayOrder);

        $body = "<h1>Portfolio</h1>\n";
        $body .= "<nav style=\"display:flex;gap:.5rem;flex-wrap:wrap;margin:1rem 0 2rem;\">\n";
        $body .= "    <a href=\"/portfolio\" style=\"padding:.5rem .75rem;border:1px solid #222;text-decoration:none;\">All</a>\n";

        foreach ($sections as $section) {
            $slug = rawurlencode((string) $section['slug']);
            $name = $this->escape((string) $section['name']);
            $body .= "    <a href=\"/portfolio?section={$slug}\" style=\"padding:.5rem .75rem;border:1px solid #222;text-decoration:none;\">{$name}</a>\n";
        }

        $body .= "</nav>\n";

        if (!$items) {
            $body .= "<p>No published artwork yet.</p>\n";
        } else {
            $body .= "<div style=\"display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:1.25rem;\">\n";

            foreach ($items as $item) {
                $title = $this->escape((string) $item['title']);
                $slug = rawurlencode((string) $item['slug']);
                $year = $this->escape((string) ($item['year_created'] ?? ''));
                $medium = $this->escape((string) ($item['medium'] ?? ''));
                $priceLine = $this->publicPriceLine($item);
                $image = '';

                if (!empty($item['media_uuid'])) {
                    $src = '/media?uuid=' . rawurlencode((string) $item['media_uuid']) . '&variant=thumb';
                    $alt = $this->escape((string) ($item['media_alt_text'] ?? $item['title']));
                    $image = "<img src=\"{$src}\" alt=\"{$alt}\" loading=\"lazy\" style=\"width:100%;height:240px;object-fit:contain;background:#fff;\">";
                }

                $body .= <<<HTML
<article style="border:1px solid #ddd;padding:1rem;background:#fffaf5;">
    <a href="/artwork/{$slug}">{$image}</a>
    <h2 style="font-size:1.1rem;margin:.75rem 0 .25rem;"><a href="/artwork/{$slug}">{$title}</a></h2>
    <p style="margin:.2rem 0;color:#666;">{$year}</p>
    <p style="margin:.2rem 0;color:#666;">{$medium}</p>
    {$priceLine}
</article>
HTML;
            }

            $body .= "</div>\n";
        }

        return Response::html($this->layout(
            tenant: $tenant,
            title: "{$this->escape($tenant->name)} | Portfolio",
            body: $body,
        ));
    }

    public function artwork(Request $request, TenantContext $tenant, string $slug): Response
    {
        $artwork = $this->artworks->findPublishedBySlug($tenant, $slug);

        if (!$artwork) {
            return Response::notFound("Artwork not found: {$slug}");
        }

        $this->track($request, $tenant, 'image_view', 'artwork', (int) $artwork['id']);

        $title = $this->escape((string) $artwork['title']);
        $description = (string) ($artwork['description'] ?? '');
        $medium = $this->escape((string) ($artwork['medium'] ?? ''));
        $dimensions = $this->escape((string) ($artwork['dimensions'] ?? ''));
        $year = $this->escape((string) ($artwork['year_created'] ?? ''));
        $pricePanel = $this->artworkSalesPanel($tenant, $artwork);
        $contactLink = '/contact?artwork=' . rawurlencode((string) $artwork['slug']);

        $body = "<h1>{$title}</h1>\n";

        if (!empty($artwork['media_uuid'])) {
            $src = '/media?uuid=' . rawurlencode((string) $artwork['media_uuid']);
            $alt = $this->escape((string) ($artwork['media_alt_text'] ?? $artwork['title']));
            $body .= "<p><img src=\"{$src}\" alt=\"{$alt}\" style=\"max-width:720px;width:100%;height:auto;object-fit:contain;\"></p>\n";
        }

        $body .= "<p><strong>Medium:</strong> {$medium}</p>\n";
        $body .= "<p><strong>Dimensions:</strong> {$dimensions}</p>\n";
        $body .= "<p><strong>Year:</strong> {$year}</p>\n";
        $body .= "<div>{$description}</div>\n";
        $body .= $pricePanel;
        $body .= '<p><a class="button artwork-inquiry-link" href="' . $contactLink . '">Contact the artist about this artwork</a></p>' . "\n";

        return Response::html($this->layout(
            tenant: $tenant,
            title: "{$title} | {$this->escape($tenant->name)}",
            body: $body,
        ));
    }

    public function about(Request $request, TenantContext $tenant): Response
    {
        $this->track($request, $tenant, 'about_view');
        $about = $this->settings->get($tenant, 'about_content', '');
        $events = $this->events($tenant);
        $body = "<h1>About</h1>\n<article class=\"prose\">{$about}</article>\n";

        if ($events !== '') {
            $body .= "<section class=\"events\"><h2>Exhibitions</h2>{$events}</section>\n";
        }

        return Response::html($this->layout(
            tenant: $tenant,
            title: "{$this->escape($tenant->name)} | About",
            body: $body
        ));
    }

    public function contact(Request $request, TenantContext $tenant): Response
    {
        $this->track($request, $tenant, 'contact_view');
        $csrf = $this->csrf ? $this->escape($this->csrf->getOrCreate()) : '';
        $captcha = FirstPartyCaptcha::render('contact', (int) $tenant->tenantId, $this->turnstileSiteKey($tenant));
        $signupCaptcha = FirstPartyCaptcha::render('signup', (int) $tenant->tenantId, $this->turnstileSiteKey($tenant));
        $siteTitle = $this->escape($this->settings->get($tenant, 'site_title', $tenant->name));
        $artworkSubject = $this->contactArtworkSubject($tenant, (string) ($_GET['artwork'] ?? ''));

        return Response::html($this->layout(
            tenant: $tenant,
            title: "{$this->escape($tenant->name)} | Contact",
            body: <<<HTML
<h1>Contact</h1>
{$this->siteImageFigure($tenant, 'contact_media_uuid', 'contact_image_opacity', 'Contact image')}
<article class="prose">{$this->settings->get($tenant, 'contact_details', '')}</article>
<section class="contact-grid">
<form class="plan-edit-form" method="post" action="/contact" data-af-async-form data-af-result="contact-form-result" data-af-busy-label="Sending..." data-af-busy-message="Sending your message..." data-af-form-purpose="contact" data-af-success-message="Thank you. Your message has been sent.">
    <h2>Send a message</h2>
    <div id="contact-form-result" data-af-form-result class="af-form-result" hidden></div>
    <input type="hidden" name="csrf_token" value="{$csrf}">
    <p>
        <label>Name<br>
            <input type="text" name="name" autocomplete="name" required>
        </label>
    </p>
    <p>
        <label>Email<br>
            <input type="email" name="email" autocomplete="email" required>
        </label>
    </p>
    <p>
        <label>Subject<br>
            <input type="text" name="subject" value="{$artworkSubject}">
        </label>
    </p>
    <p>
        <label>Message<br>
            <textarea name="message" rows="8" required></textarea>
        </label>
    </p>
    <p>
        <label><input type="checkbox" name="join_email_list" value="1"> Also add me to {$siteTitle}'s email list.</label>
    </p>
    {$captcha}
    <button type="submit">Send message</button>
</form>
<form method="post" action="/signup" data-af-async-form data-af-result="signup-form-result" data-af-busy-label="Joining..." data-af-busy-message="Adding you to the list..." data-af-form-purpose="signup" data-af-success-message="Thank you. You have been added to the email list.">
    <h2>Email list</h2>
    <p>Get occasional updates from {$siteTitle}.</p>
    <div id="signup-form-result" data-af-form-result class="af-form-result" hidden></div>
    <input type="hidden" name="csrf_token" value="{$csrf}">
    <p>
        <label>Name<br>
            <input type="text" name="name" autocomplete="name">
        </label>
    </p>
    <p>
        <label>Email<br>
            <input type="email" name="email" autocomplete="email" required>
        </label>
    </p>
    {$signupCaptcha}
    <button type="submit">Join email list</button>
</form>
</section>
HTML
        ));
    }


    /**
     * Shows price for published, unsold artwork marked for sale.
     *
     * @param array<string,mixed> $artwork
     */
    private function publicPriceLine(array $artwork): string
    {
        if ((string) ($artwork['sale_status'] ?? '') !== 'for_sale') {
            return '';
        }

        $price = trim((string) ($artwork['price'] ?? ''));
        if ($price === '') {
            return '<p class="artwork-price">For sale</p>';
        }

        return '<p class="artwork-price">' . $this->escape($this->formatPublicPrice($price)) . '</p>';
    }

    /**
     * Adds a visible default currency marker to bare numeric prices.
     */
    private function formatPublicPrice(string $price): string
    {
        $price = trim($price);
        if ($price === '') {
            return $price;
        }

        if (preg_match('/^[0-9]+(?:[,.][0-9]{2})?$/', $price) === 1) {
            return '$' . $price;
        }

        return $price;
    }

    /**
     * Renders phase-one sales context on artwork detail pages.
     *
     * @param array<string,mixed> $artwork
     */
    private function artworkSalesPanel(TenantContext $tenant, array $artwork): string
    {
        $priceLine = $this->publicPriceLine($artwork);
        $salesNotes = trim((string) $this->settings->get($tenant, 'sales_notes', ''));
        $inventoryLabel = ((int) ($artwork['is_one_off'] ?? 1)) === 1
            ? 'One-off artwork'
            : 'Multiple item · ' . max(1, (int) ($artwork['inventory_quantity'] ?? 1)) . ' available';

        if ($priceLine === '' && $salesNotes === '') {
            return '';
        }

        $notesHtml = $salesNotes !== '' ? '<div class="prose sales-notes">' . $salesNotes . '</div>' : '';
        $cartHtml = $this->cartForm($tenant, $artwork);

        return <<<HTML
<section class="artwork-sales-panel">
    <h2>Sales</h2>
    {$priceLine}
    <p class="artwork-inventory-mode">{$inventoryLabel}</p>
    {$notesHtml}
    {$cartHtml}
</section>
HTML;
    }

    /**
     * Renders a paid-plan cart form for purchasable artwork.
     *
     * @param array<string,mixed> $artwork
     */
    private function cartForm(TenantContext $tenant, array $artwork): string
    {
        if (!$this->tenantSalesEnabled($tenant)) {
            return '<p class="sales-note-small">Online checkout is available on paid ArtsFolio plans.</p>';
        }
        if ((string) ($artwork['sale_status'] ?? '') !== 'for_sale' || trim((string) ($artwork['price'] ?? '')) === '') {
            return '';
        }

        $csrf = $this->csrf ? $this->escape($this->csrf->getOrCreate()) : '';
        $artworkId = (int) ($artwork['id'] ?? 0);
        $isOneOff = ((int) ($artwork['is_one_off'] ?? 1)) === 1;
        $quantity = $isOneOff
            ? '<input type="hidden" name="quantity" value="1">'
            : '<label>Quantity <input type="number" name="quantity" min="1" max="' . max(1, (int) ($artwork['inventory_quantity'] ?? 1)) . '" value="1"></label>';

        return <<<HTML
<form method="post" action="/cart/add" class="artwork-cart-form">
    <input type="hidden" name="csrf_token" value="{$csrf}">
    <input type="hidden" name="artwork_id" value="{$artworkId}">
    {$quantity}
    <button class="button" type="submit">Add to cart</button>
</form>
<p class="sales-note-small">Checkout is processed securely through Stripe.</p>
HTML;
    }

    private function tenantSalesEnabled(TenantContext $tenant): bool
    {
        try {
            $stmt = $this->pdo->prepare(
                "SELECT p.monthly_price_cents
                 FROM tenant_plan_assignments tpa
                 JOIN plans p ON p.id = tpa.plan_id
                 WHERE tpa.tenant_id = :tenant_id
                   AND tpa.status IN ('trial', 'active', 'manual')
                 LIMIT 1"
            );
            $stmt->execute(['tenant_id' => $tenant->tenantId]);
            $row = $stmt->fetch();

            return $row && (int) ($row['monthly_price_cents'] ?? 0) > 0;
        } catch (Throwable) {
            return false;
        }
    }

    private function contactArtworkSubject(TenantContext $tenant, string $slug): string
    {
        $slug = trim($slug);
        if ($slug === '') {
            return '';
        }

        $artwork = $this->artworks->findPublishedBySlug($tenant, $slug);
        if (!$artwork) {
            return '';
        }

        return $this->escape('Inquiry about ' . (string) $artwork['title']);
    }

    private function cookieConsentBanner(): string
    {
        return <<<HTML
<div class="cookie-consent" data-cookie-consent hidden>
    <p>This site uses cookies for sessions, spam protection, analytics, mailing-list forms, and shopping cart support.</p>
    <button type="button" data-cookie-consent-accept>Accept cookies</button>
</div>
<script>
(() => {
    const banner = document.querySelector('[data-cookie-consent]');
    if (!banner || window.localStorage.getItem('artsfolio_cookie_consent') === 'accepted') {
        return;
    }
    banner.hidden = false;
    banner.querySelector('[data-cookie-consent-accept]')?.addEventListener('click', () => {
        window.localStorage.setItem('artsfolio_cookie_consent', 'accepted');
        banner.hidden = true;
    });
})();
</script>
HTML;
    }

    private function track(Request $request, TenantContext $tenant, string $eventType, ?string $entityType = null, ?int $entityId = null): void
    {
        try {
            $stmt = $this->pdo->prepare(
                'INSERT INTO analytics_events (
                    tenant_id,
                    event_type,
                    path,
                    referrer,
                    ip_hash,
                    user_agent,
                    entity_type,
                    entity_id,
                    country,
                    region,
                    city,
                    created_at
                ) VALUES (
                    :tenant_id,
                    :event_type,
                    :path,
                    :referrer,
                    :ip_hash,
                    :user_agent,
                    :entity_type,
                    :entity_id,
                    :country,
                    :region,
                    :city,
                    NOW()
                )'
            );

            $ip = $this->requestIp($request);
            $ipHash = hash('sha256', $ip . '|artsfolio-analytics');
            $location = (new \App\Platform\Analytics\AnalyticsLocationResolver($this->pdo))->resolve($request, $ip, $ipHash);

            $stmt->execute([
                'tenant_id' => $tenant->tenantId,
                'event_type' => $eventType,
                'path' => $request->path(),
                'referrer' => mb_substr((string) $request->server('HTTP_REFERER', ''), 0, 1000),
                'ip_hash' => $ipHash,
                'user_agent' => mb_substr((string) $request->server('HTTP_USER_AGENT', ''), 0, 1000),
                'entity_type' => $entityType,
                'entity_id' => $entityId,
                'country' => $location['country'],
                'region' => $location['region'],
                'city' => $location['city'],
            ]);
        } catch (Throwable) {
            // Analytics must never break the public tenant site.
        }
    }

    private function requestIp(Request $request): string
    {
        foreach (['HTTP_CF_CONNECTING_IP', 'HTTP_X_FORWARDED_FOR', 'REMOTE_ADDR'] as $key) {
            $value = trim((string) $request->server($key, ''));
            if ($value === '') {
                continue;
            }

            $first = trim(explode(',', $value)[0]);
            if (filter_var($first, FILTER_VALIDATE_IP)) {
                return $first;
            }
        }

        return '';
    }


    /**
     * Returns the tenant-admin link for signed-in users on tenant public pages.
     *
     * Tenant hosts must never link their public Admin tab to /platform/admin;
     * that route belongs to the canonical platform host. The tenant admin entry
     * point is always /admin on the current tenant hostname.
     */
    private function tenantAdminLink(): string
    {
        $currentUser = $GLOBALS['artsfolio_current_user'] ?? null;
        if (!is_array($currentUser) || empty($currentUser['user_id'])) {
            return '';
        }

        return '<a class="tenant-admin-top-link" href="/admin">Admin</a>';
    }

    private function layout(TenantContext $tenant, string $title, string $body): string
    {
        $siteTitle = $this->escape($this->settings->get($tenant, 'site_title', $tenant->name));
        $browserTitle = $this->escape($title);
        $copyrightName = $this->settings->get($tenant, 'copyright_name', $siteTitle);
        $year = date('Y');
        $primaryColor = $this->escape($this->settings->get($tenant, 'primary_color', '#111111'));
        $accentColor = $this->escape($this->settings->get($tenant, 'accent_color', '#c9a85f'));
        $backgroundColor = $this->escape($this->settings->get($tenant, 'background_color', '#f7f2e8'));
        $topbarBackgroundColor = $this->escape($this->settings->get($tenant, 'topbar_background_color', 'color-mix(in srgb, var(--bg), white 50%)'));
        $textColor = $this->escape($this->settings->get($tenant, 'text_color', '#1f1a14'));
        $surfaceStyle = $this->tenantSurfaceCssVariables($tenant);
        $homeTab = $this->escape($this->settings->get($tenant, 'home_tab', 'Home'));
        $portfolioTab = $this->escape($this->settings->get($tenant, 'portfolio_tab', 'Portfolio'));
        $aboutTab = $this->escape($this->settings->get($tenant, 'about_tab', 'About'));
        $contactTab = $this->escape($this->settings->get($tenant, 'contact_tab', 'Contact'));
        $portfolioSlug = $this->escape($this->settings->get($tenant, 'portfolio_slug', 'portfolio'));
        $aboutSlug = $this->escape($this->settings->get($tenant, 'about_slug', 'about'));
        $contactSlug = $this->escape($this->settings->get($tenant, 'contact_slug', 'contact'));
        $backgroundStyle = $this->backgroundCssVariables($tenant);
        $footerSignupForm = $this->footerSignupForm($tenant, $contactSlug);
        $socialLinks = $this->socialFooterLinks($tenant);
        $platformAdminLink = $this->tenantAdminLink();
        $turnstileScript = FirstPartyCaptcha::isConfigured($this->turnstileSiteKey($tenant)) ? '<script src="https://challenges.cloudflare.com/turnstile/v0/api.js" async defer></script>' : '';

        return <<<HTML
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>{$browserTitle}</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="Artist portfolio">
    <link rel="stylesheet" href="/assets/site.css">
    <link rel="stylesheet" href="/tenant.css">
    <script src="/assets/tenant-forms.js?v=20260602a" defer></script>
    {$turnstileScript}
</head>
<body style="--primary:{$primaryColor};--accent:{$accentColor};--bg:{$backgroundColor};--topbar-bg:{$topbarBackgroundColor};--text-color:{$textColor};{$backgroundStyle}{$surfaceStyle}">
<header class="site-header">
    <a class="brand" href="/">{$siteTitle}</a>
    <nav>
        <a href="/">{$homeTab}</a>
        <a href="/{$portfolioSlug}">{$portfolioTab}</a>
        <a href="/{$aboutSlug}">{$aboutTab}</a>
        <a href="/{$contactSlug}">{$contactTab}</a>
        {$platformAdminLink}
    </nav>
</header>
<main class="site-main tenant-content-surface">
{$body}
</main>
<footer class="site-footer tenant-public-footer">
    <span>© {$year} {$copyrightName}</span>
    {$this->artsfolioFreePlanLink($tenant)}
    {$socialLinks}
    {$footerSignupForm}
</footer>
{$this->cookieConsentBanner()}
</body>
</html>
HTML;
    }


    /**
     * Returns public and admin-visible surface CSS variables from tenant settings.
     */
    private function tenantSurfaceCssVariables(TenantContext $tenant): string
    {
        $vars = '';
        $headingColor = (string) $this->settings->get($tenant, 'heading_background_color', '#fff8ec');
        $headingOpacity = $this->safeOpacity((string) $this->settings->get($tenant, 'heading_background_opacity', '0.78'));
        $contentColor = (string) $this->settings->get($tenant, 'content_background_color', '#fffaf0');
        $contentOpacity = $this->safeOpacity((string) $this->settings->get($tenant, 'content_background_opacity', '0.00'));
        $textBgColor = (string) $this->settings->get($tenant, 'text_background_color', '#fff7e8');
        $textBgOpacity = $this->safeOpacity((string) $this->settings->get($tenant, 'text_background_opacity', '0.72'));
        $menuColor = (string) $this->settings->get($tenant, 'menu_background_color', (string) $this->settings->get($tenant, 'topbar_background_color', '#fff8ec'));
        $menuOpacity = $this->safeOpacity((string) $this->settings->get($tenant, 'menu_background_opacity', '0.86'));
        $menuEnabled = (string) $this->settings->get($tenant, 'menu_background_enabled', '1') !== '0' && (float) $menuOpacity > 0.0;
        $cardColor = (string) $this->settings->get($tenant, 'artwork_card_background_color', '#fffaf0');
        $cardOpacity = $this->safeOpacity((string) $this->settings->get($tenant, 'artwork_card_background_opacity', '0.00'));

        $vars .= '--heading-bg:' . $this->safeCssColor($headingColor) . ';';
        $vars .= '--heading-bg-overlay:' . $this->cssColorWithOpacity($headingColor, $headingOpacity) . ';';
        $vars .= '--heading-bg-opacity:' . $headingOpacity . ';';
        $vars .= '--content-bg:' . $this->safeCssColor($contentColor) . ';';
        $vars .= '--content-bg-overlay:' . $this->cssColorWithOpacity($contentColor, $contentOpacity) . ';';
        $vars .= '--content-bg-opacity:' . $contentOpacity . ';';
        $vars .= '--text-bg:' . $this->safeCssColor($textBgColor) . ';';
        $vars .= '--text-bg-overlay:' . $this->cssColorWithOpacity($textBgColor, $textBgOpacity) . ';';
        $vars .= '--text-bg-opacity:' . $textBgOpacity . ';';
        $vars .= '--menu-bg:' . $this->safeCssColor($menuColor) . ';';
        $vars .= '--menu-bg-overlay:' . ($menuEnabled ? $this->cssColorWithOpacity($menuColor, $menuOpacity) : 'transparent') . ';';
        $vars .= '--menu-bg-opacity:' . ($menuEnabled ? $menuOpacity : '0') . ';';
        $vars .= '--menu-panel-padding:' . ($menuEnabled ? '0.35rem 0.55rem' : '0') . ';';
        $vars .= '--menu-panel-radius:' . ($menuEnabled ? '999px' : '0') . ';';
        $vars .= '--menu-panel-shadow:' . ($menuEnabled ? '0 12px 32px rgba(0,0,0,0.08)' : 'none') . ';';
        $vars .= '--topbar-bg-opacity:' . $this->safeOpacity((string) $this->settings->get($tenant, 'topbar_background_opacity', '0.86')) . ';';
        $vars .= '--tenant-header-shadow:' . ($this->settings->get($tenant, 'header_drop_shadow_enabled', '1') === '1' ? $this->safeCssShadow((string) $this->settings->get($tenant, 'header_drop_shadow', '0 18px 45px rgba(0,0,0,0.24)')) : 'none') . ';';
        $vars .= '--artwork-card-bg:' . $this->safeCssColor($cardColor) . ';';
        $vars .= '--artwork-card-bg-overlay:' . $this->cssColorWithOpacity($cardColor, $cardOpacity) . ';';
        $vars .= '--artwork-card-bg-opacity:' . $cardOpacity . ';';
        $vars .= '--artwork-card-bg-size:' . $this->safeCssSize((string) $this->settings->get($tenant, 'artwork_card_background_size', 'cover'), 'cover') . ';';
        $vars .= $menuEnabled ? $this->mediaBackgroundVar($tenant, 'menu_media_uuid', '--menu-bg-image') : '--menu-bg-image:none;';
        $vars .= $this->mediaBackgroundVar($tenant, 'topbar_media_uuid', '--topbar-bg-image');
        $vars .= $this->mediaBackgroundVar($tenant, 'artwork_card_media_uuid', '--artwork-card-bg-image');

        return $vars;
    }


    /**
     * Free tenant pages carry a small ArtsFolio notification/link as part of the free plan disclosure.
     */
    private function artsfolioFreePlanLink(TenantContext $tenant): string
    {
        $plan = strtolower($this->effectivePlanSlug($tenant));
        if (!in_array($plan, ['free', 'starter'], true)) {
            return '';
        }

        return '<span class="tenant-powered-by"><a href="https://artsfol.io/" rel="noopener">Created with ArtsFolio</a></span>';
    }

    /**
     * Builds tenant social links from content settings.
     */
    private function socialFooterLinks(TenantContext $tenant): string
    {
        $links = [];
        foreach ([
            'instagram_url' => 'Instagram',
            'facebook_url' => 'Facebook',
            'linkedin_url' => 'LinkedIn',
        ] as $key => $label) {
            $url = trim((string) $this->settings->get($tenant, $key, ''));
            if ($url === '' || !preg_match('#^https?://#i', $url)) {
                continue;
            }
            $links[] = '<a href="' . $this->escape($url) . '" rel="me noopener" target="_blank">' . $this->escape($label) . '</a>';
        }

        return $links === [] ? '' : '<nav class="tenant-social-links" aria-label="Social links">' . implode(' ', $links) . '</nav>';
    }

    /**
     * Returns the database-selected plan before falling back to legacy tenant settings.
     */
    private function effectivePlanSlug(TenantContext $tenant): string
    {
        try {
            $stmt = $this->pdo->prepare('SELECT p.slug FROM tenant_plan_assignments tpa JOIN plans p ON p.id = tpa.plan_id WHERE tpa.tenant_id = :tenant_id AND tpa.status IN ("trial", "active", "manual") ORDER BY tpa.id DESC LIMIT 1');
            $stmt->execute(['tenant_id' => $tenant->tenantId]);
            $slug = $stmt->fetchColumn();
            if (is_string($slug) && $slug !== '') {
                return $slug;
            }
        } catch (Throwable) {
        }

        return (string) $this->settings->get($tenant, 'billing_plan', 'studio');
    }

    /**
     * Builds the compact footer signup form available on every public tenant page.
     */
    private function footerSignupForm(TenantContext $tenant, string $contactSlug): string
    {
        $csrf = $this->csrf ? $this->escape($this->csrf->getOrCreate()) : '';
        $captcha = FirstPartyCaptcha::render('signup', (int) $tenant->tenantId, $this->turnstileSiteKey($tenant));
        $contactSlug = trim($contactSlug, '/') !== '' ? trim($contactSlug, '/') : 'contact';

        return <<<HTML
<form method="post" action="/signup" class="tenant-footer-signup" data-af-async-form data-af-result="footer-signup-result" data-af-busy-label="Subscribing..." data-af-busy-message="Adding you to the mailing list..." data-af-form-purpose="signup" data-af-success-message="Thank you. You have been added to the email list.">
    <label for="footer-signup-email">Join the mailing list</label>
    <div class="tenant-footer-signup-row">
        <input id="footer-signup-email" type="email" name="email" autocomplete="email" placeholder="Email address" required>
        <input type="hidden" name="csrf_token" value="{$csrf}">
        <input type="hidden" name="source" value="footer">
        <input type="hidden" name="return_to" value="/{$contactSlug}?signup_sent=1">
        <button type="submit">Subscribe</button>
    </div>
    <div id="footer-signup-result" data-af-form-result class="af-form-result" hidden></div>
    <div class="tenant-footer-captcha">{$captcha}</div>
</form>
HTML;
    }

    /**
     * Adds a CSS variable for a tenant-selected Site Image when the UUID remains valid.
     */
    private function mediaBackgroundVar(TenantContext $tenant, string $settingKey, string $cssVar): string
    {
        $uuid = strtolower(trim((string) $this->settings->get($tenant, $settingKey, '')));
        if ($uuid === '' || !preg_match('/^[a-f0-9-]{36}$/', $uuid) || !$this->isPublicTenantImage($tenant, $uuid)) {
            return '';
        }

        return $cssVar . ":url('/media?uuid=" . rawurlencode($uuid) . "');";
    }

    /**
     * Allows conservative color tokens plus rgba()/color-mix() values used by tenants.
     */
    /**
     * Converts common CSS color forms into an alpha-applied overlay value.
     */
    private function cssColorWithOpacity(string $color, string $opacity): string
    {
        $color = trim($color);
        $alpha = $this->safeOpacity($opacity);

        if (preg_match('/^#([0-9a-fA-F]{6})$/', $color, $matches) === 1) {
            $hex = $matches[1];
            $red = hexdec(substr($hex, 0, 2));
            $green = hexdec(substr($hex, 2, 2));
            $blue = hexdec(substr($hex, 4, 2));

            return sprintf('rgba(%d,%d,%d,%s)', $red, $green, $blue, $alpha);
        }

        if (preg_match('/^#([0-9a-fA-F]{3})$/', $color, $matches) === 1) {
            $hex = $matches[1];
            $red = hexdec(str_repeat($hex[0], 2));
            $green = hexdec(str_repeat($hex[1], 2));
            $blue = hexdec(str_repeat($hex[2], 2));

            return sprintf('rgba(%d,%d,%d,%s)', $red, $green, $blue, $alpha);
        }

        if (preg_match('/^rgba?\(([^)]+)\)$/i', $color, $matches) === 1) {
            $parts = array_map('trim', explode(',', $matches[1]));
            if (count($parts) >= 3) {
                return 'rgba(' . $parts[0] . ',' . $parts[1] . ',' . $parts[2] . ',' . $alpha . ')';
            }
        }

        return $this->safeCssColor($color);
    }

    /**
     * Restricts box-shadow settings to simple, non-executable CSS tokens.
     */
    private function safeCssShadow(string $value): string
    {
        $value = trim($value);
        if ($value === '' || strtolower($value) === 'none') {
            return 'none';
        }

        return preg_match('/^[#a-zA-Z0-9.,%()\s-]+$/', $value) ? $value : '0 18px 45px rgba(0,0,0,0.24)';
    }

    private function safeCssColor(string $value): string
    {
        $value = trim($value);
        if ($value === '') {
            return 'rgba(255,255,255,0.72)';
        }

        return preg_match('/^[#a-zA-Z0-9.,%()\s-]+$/', $value) ? $value : 'rgba(255,255,255,0.72)';
    }


    /**
     * Renders optional published Site Images for public about/contact content.
     */
    private function siteImageFigure(TenantContext $tenant, string $mediaSetting, string $opacitySetting, string $alt): string
    {
        $uuid = strtolower(trim((string) $this->settings->get($tenant, $mediaSetting, '')));
        if ($uuid === '' || !preg_match('/^[a-f0-9-]{36}$/', $uuid) || !$this->isPublishedSiteImage($tenant, $uuid)) {
            return '';
        }

        $opacity = $this->safeOpacity((string) $this->settings->get($tenant, $opacitySetting, '1'));
        $src = '/media?uuid=' . rawurlencode($uuid);
        $safeAlt = $this->escape($alt);

        return '<figure class="site-content-image" style="--site-content-image-opacity:' . $opacity . '"><img src="' . $this->escape($src) . '" alt="' . $safeAlt . '" loading="lazy"></figure>';
    }

    /**
     * Verifies that a previously selected visual asset is a public image for this tenant.
     *
     * Site image pick lists stay restricted to published Site Images, but rendering must
     * preserve legacy selections so existing custom-domain headers do not disappear.
     */
    private function isPublicTenantImage(TenantContext $tenant, string $uuid): bool
    {
        try {
            $stmt = $this->pdo->prepare(
                "SELECT 1
                 FROM media_assets
                 WHERE tenant_id = :tenant_id
                   AND uuid = :media_uuid
                   AND is_private = 0
                   AND (mime_type LIKE 'image/%' OR mime_type IS NULL)
                 LIMIT 1"
            );
            $stmt->execute(['tenant_id' => $tenant->tenantId, 'media_uuid' => $uuid]);

            return (bool) $stmt->fetch();
        } catch (Throwable) {
            return false;
        }
    }

    /**
     * Verifies that a configured media UUID is still a published Site Image.
     */
    private function isPublishedSiteImage(TenantContext $tenant, string $uuid): bool
    {
        try {
            $stmt = $this->pdo->prepare(
                "SELECT 1
                 FROM media_assets m
                 INNER JOIN artworks a ON a.primary_media_id = m.id AND a.tenant_id = m.tenant_id AND a.status = 'published'
                 INNER JOIN artwork_type_assignments ata ON ata.artwork_id = a.id
                 INNER JOIN artwork_types atype ON atype.id = ata.type_id AND atype.code = 'site_images'
                 WHERE m.tenant_id = :tenant_id
                   AND m.uuid = :media_uuid
                   AND m.is_private = 0
                   AND (m.mime_type LIKE 'image/%' OR m.mime_type IS NULL)
                 LIMIT 1"
            );
            $stmt->execute(['tenant_id' => $tenant->tenantId, 'media_uuid' => $uuid]);

            return (bool) $stmt->fetch();
        } catch (Throwable) {
            return false;
        }
    }

    /**
     * Returns inline CSS variables used by site.css for tenant background images.
     */
    private function backgroundCssVariables(TenantContext $tenant): string
    {
        $uuid = strtolower(trim((string) $this->settings->get($tenant, 'background_media_uuid', '')));

        if ($uuid === '' || !preg_match('/^[a-f0-9-]{36}$/', $uuid) || !$this->isPublicTenantImage($tenant, $uuid)) {
            return '';
        }

        $mode = (string) $this->settings->get($tenant, 'background_mode', 'single');
        $tileSize = $this->safeCssSize((string) $this->settings->get($tenant, 'background_tile_size', '360px'), '360px');
        $opacity = $this->safeOpacity((string) $this->settings->get($tenant, 'background_opacity', '0.12'));
        $imageUrl = '/media?uuid=' . rawurlencode($uuid);
        $repeat = $mode === 'tile' ? 'repeat' : 'no-repeat';
        $size = $mode === 'tile' ? $tileSize : 'cover';

        return "--site-bg-image:url('{$this->escape($imageUrl)}');"
            . '--site-bg-repeat:' . $repeat . ';'
            . '--site-bg-size:' . $this->escape($size) . ';'
            . '--site-bg-opacity:' . $opacity . ';';
    }

    private function contactNotice(): string
    {
        if (trim((string) ($_GET['contact_sent'] ?? '')) !== '') {
            return '<p class="notice" role="status">Thank you. Your message has been sent.</p>';
        }

        $error = trim((string) ($_GET['contact_error'] ?? ''));
        if ($error === '') {
            return '';
        }

        $messages = [
            'security' => 'The form security check expired. Please try again.',
            'recaptcha' => 'The Turnstile check did not pass. Please try again.',
            'rate_limited' => 'Too many submissions were received. Please wait a few minutes and try again.',
            'missing' => 'Please complete the required fields.',
            'email' => 'Please enter a valid email address.',
        ];

        return '<p class="error" role="alert">' . $this->escape($messages[$error] ?? 'The message could not be sent. Please try again.') . '</p>';
    }

    private function signupNotice(): string
    {
        if (trim((string) ($_GET['signup_sent'] ?? '')) !== '') {
            return '<p class="notice" role="status">Thank you. You have been added to the email list.</p>';
        }

        $error = trim((string) ($_GET['signup_error'] ?? ''));
        if ($error === '') {
            return '';
        }

        $messages = [
            'security' => 'The signup security check expired. Please try again.',
            'recaptcha' => 'The Turnstile check did not pass. Please try again.',
            'rate_limited' => 'Too many signup attempts were received. Please wait a few minutes and try again.',
            'missing' => 'Please enter an email address.',
            'email' => 'Please enter a valid email address.',
        ];

        return '<p class="error" role="alert">' . $this->escape($messages[$error] ?? 'The signup could not be completed. Please try again.') . '</p>';
    }

    /**
     * Allows simple CSS size values while rejecting characters that could break style attributes.
     */
    private function safeCssSize(string $value, string $default): string
    {
        $value = trim($value);

        if (preg_match('/^(auto|cover|contain|[0-9.]+(px|rem|em|%|vw|vh))$/', $value)) {
            return $value;
        }

        return $default;
    }

    /**
     * Clamps tenant-provided opacity to the supported CSS range.
     */
    private function safeOpacity(string $value): string
    {
        $opacity = is_numeric($value) ? (float) $value : 0.12;
        $opacity = max(0.0, min(1.0, $opacity));

        return rtrim(rtrim(sprintf('%.2F', $opacity), '0'), '.');
    }



    private function backgroundStyle(TenantContext $tenant): string
    {
        $mediaUuid = trim((string) $this->settings->get($tenant, 'background_media_uuid', ''));
        if ($mediaUuid === '') {
            return '';
        }

        $mode = $this->settings->get($tenant, 'background_mode', 'single') === 'tile' ? 'repeat' : 'no-repeat';
        $size = $this->settings->get($tenant, 'background_mode', 'single') === 'tile'
            ? $this->settings->get($tenant, 'background_tile_size', '360px')
            : 'cover';
        $opacity = $this->cssNumber($this->settings->get($tenant, 'background_opacity', '0.12'), '0.12');
        $url = '/media?uuid=' . rawurlencode($mediaUuid);

        return '--background-image:url(' . $url . ');--background-repeat:' . $mode . ';--background-size:' . $this->cssToken($size, 'cover') . ';--background-opacity:' . $opacity . ';';
    }

    private function cssNumber(?string $value, string $fallback): string
    {
        $value = trim((string) $value);
        return preg_match('/^(?:0(?:\.\d+)?|1(?:\.0+)?)$/', $value) ? $value : $fallback;
    }

    private function cssToken(?string $value, string $fallback): string
    {
        $value = trim((string) $value);
        return preg_match('/^[a-zA-Z0-9 .%_\-()]+$/', $value) ? $value : $fallback;
    }

    private function events(TenantContext $tenant): string
    {
        $stmt = $this->pdo->prepare(
            "SELECT exhibition_date, name, exhibition_type, location, city, state_region, work_name, notes
             FROM exhibitions
             WHERE tenant_id = :tenant_id
               AND status = 'active'
             ORDER BY sort_order ASC, id DESC"
        );
        $stmt->execute(['tenant_id' => $tenant->tenantId]);
        $rows = $stmt->fetchAll();

        if (!$rows) {
            return '';
        }

        $mode = $this->settings->get($tenant, 'exhibitions_display_mode', 'text');

        if ($mode === 'table') {
            $html = '<table class="events-table"><tr><th>Date</th><th>Exhibition</th><th>Type</th><th>Location</th><th>Work</th><th>Additional information</th></tr>';
            foreach ($rows as $event) {
                $date = $this->escape((string) ($event['exhibition_date'] ?? ''));
                $name = $this->escape((string) $event['name']);
                $type = $this->escape((string) ($event['exhibition_type'] ?? ''));
                $locationRaw = (string) (($event['location'] ?? '') ?: (($event['city'] ?? '') . ', ' . ($event['state_region'] ?? '')));
                $location = $this->escape(trim($locationRaw, ', '));
                $work = $this->escape((string) ($event['work_name'] ?? ''));
                $notes = (string) ($event['notes'] ?? '');
                $html .= "<tr><td>{$date}</td><td>{$name}</td><td>{$type}</td><td>{$location}</td><td>{$work}</td><td><div class=\"prose small\">{$notes}</div></td></tr>";
            }
            return $html . '</table>';
        }

        $html = '';
        foreach ($rows as $event) {
            $date = $this->escape((string) ($event['exhibition_date'] ?? ''));
            $name = $this->escape((string) $event['name']);
            $type = $this->escape((string) ($event['exhibition_type'] ?? ''));
            $locationRaw = (string) (($event['location'] ?? '') ?: (($event['city'] ?? '') . ', ' . ($event['state_region'] ?? '')));
            $location = $this->escape(trim($locationRaw, ', '));
            $work = $this->escape((string) ($event['work_name'] ?? ''));
            $notes = (string) ($event['notes'] ?? '');

            $html .= "<article class=\"event-row\">";
            $html .= "<div class=\"event-date\">{$date}</div><div>";
            $html .= "<h3>{$name}</h3>";
            if ($type !== '') {
                $html .= "<p><strong>{$type}</strong></p>";
            }
            if ($location !== '') {
                $html .= "<p>{$location}</p>";
            }
            if ($work !== '') {
                $html .= "<p>{$work}</p>";
            }
            if ($notes !== '') {
                $html .= "<div class=\"prose small\">{$notes}</div>";
            }
            $html .= "</div></article>\n";
        }

        return $html;
    }


    /**
     * Tenant public forms use the built-in ArtsFolio CAPTCHA.
     *
     * Platform marketing forms still use Cloudflare Turnstile. Tenant and
     * custom-domain sites intentionally do not inherit the platform Turnstile
     * widget because Cloudflare hostname limits do not scale with tenants.
     */
    private function turnstileSiteKey(TenantContext $tenant): string
    {
        return '';
    }

    private function escape(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
    }
}

// End of file.
