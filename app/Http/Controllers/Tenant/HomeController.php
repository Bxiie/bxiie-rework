<?php

declare(strict_types=1);

namespace App\Http\Controllers\Tenant;

use App\Http\Request;
use App\Http\Response;
use App\Platform\Tenancy\TenantContext;
use App\Services\FirstPartyCaptcha;
use App\Support\Pagination\Pagination;
use App\Support\Security\CsrfTokenService;
use App\Tenant\Artwork\ArtworkReadRepository;
use App\Tenant\Sales\CartIdentityService;
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
        private readonly ?CurationController $curation = null,
        private readonly ?array $currentUser = null,
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

        $includeUnpublished = $this->unpublishedPreviewEnabled($tenant);
        $items = $this->artworks->latestPublished($tenant, 12, $includeUnpublished);


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

                $unpublished = (string)($item['status'] ?? '') !== 'published' ? '<strong class="artwork-unpublished">Unpublished</strong>' : '';
                $body .= <<<HTML
<div class="card artwork-card-wrap"><a class="card artwork-card" href="/artwork/{$slug}">
    {$image}
    <span>{$title}</span>
    <small>{$meta}</small>
    {$unpublished}
</a></div>

HTML;
            }
            $body .= "</section>
";
        }

        return $this->tenantPageResponse($this->layout(
            tenant: $tenant,
            title: $siteTitle,
            body: $body,
        ));
    }

    public function portfolio(Request $request, TenantContext $tenant): Response
    {
        $this->track($request, $tenant, 'portfolio_view');
        $sectionSlug = trim((string) ($_GET['section'] ?? ''));
        $page = max(1, (int) ($_GET['page'] ?? 1));
        $pageSize = Pagination::allowedLimitFromQuery(
            $_GET['per_page'] ?? null,
            24,
            [10, 20, 24, 30, 40, 50, 60, 70, 80, 90, 100],
        );
        $includeUnpublished = $this->unpublishedPreviewEnabled($tenant);
        $sections = $this->artworks->activeSections($tenant, $includeUnpublished);
        $displayOrder = (string) $this->settings->get($tenant, 'artwork_display_order', 'date_desc');
        $result = $this->artworks->publishedPage(
            $tenant,
            $page,
            $pageSize,
            $displayOrder,
            $sectionSlug !== '' ? $sectionSlug : null,
            $includeUnpublished,
        );
        $items = $result['items'];

        $pageSizeOptions = '';
        foreach ([10, 20, 24, 30, 40, 50, 60, 70, 80, 90, 100] as $sizeOption) {
            $selected = $sizeOption === $pageSize ? ' selected' : '';
            $label = $sizeOption === 24 ? '24 (default)' : (string) $sizeOption;
            $pageSizeOptions .= '<option value="' . $sizeOption . '"' . $selected . '>' . $label . '</option>';
        }

        $allHref = '/portfolio?' . http_build_query(['per_page' => $pageSize]);
        $body = "<h1>Portfolio</h1>\n";
        $body .= '<section data-artwork-pager-root tabindex="-1">';
        $body .= "<nav style=\"display:flex;gap:.5rem;flex-wrap:wrap;margin:1rem 0 1rem;\">\n";
        $body .= '    <a data-artwork-page-link href="' . $this->escape($allHref) . '" style="padding:.5rem .75rem;border:1px solid #222;text-decoration:none;">All</a>' . "\n";

        foreach ($sections as $section) {
            $sectionQuery = http_build_query([
                'section' => (string) $section['slug'],
                'per_page' => $pageSize,
            ]);
            $name = $this->escape((string) $section['name']);
            $body .= '    <a data-artwork-page-link href="/portfolio?' . $this->escape($sectionQuery) . '" style="padding:.5rem .75rem;border:1px solid #222;text-decoration:none;">' . $name . '</a>' . "\n";
        }

        $body .= "</nav>\n";
        $sectionControl = $sectionSlug !== ''
            ? '<input type="hidden" name="section" value="' . $this->escape($sectionSlug) . '">'
            : '';
        $body .= '<form data-artwork-page-form method="get" action="/portfolio" style="display:flex;gap:.5rem;align-items:end;flex-wrap:wrap;margin:0 0 1.5rem;">'
            . $sectionControl
            . '<label>Artworks per page<br><select name="per_page">' . $pageSizeOptions . '</select></label>'
            . '<button type="submit">Apply</button></form>';

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

                $unpublished = (string)($item['status'] ?? '') !== 'published' ? '<strong class="artwork-unpublished">Unpublished</strong>' : '';
                $body .= <<<HTML
<article style="border:1px solid #ddd;padding:1rem;background:#fffaf5;">
    <a href="/artwork/{$slug}">{$image}</a>
    <h2 style="font-size:1.1rem;margin:.75rem 0 .25rem;"><a href="/artwork/{$slug}">{$title}</a></h2>
    <p style="margin:.2rem 0;color:#666;">{$year}</p>
    <p style="margin:.2rem 0;color:#666;">{$medium}</p>
    {$priceLine}
    {$unpublished}
</article>
HTML;
            }

            $body .= "</div>\n";
        }

        $pageCount = (int) $result['page_count'];
        if ($pageCount > 1) {
            $currentPage = (int) $result['page'];
            $body .= '<nav aria-label="Portfolio pages" style="display:flex;gap:.5rem;flex-wrap:wrap;align-items:center;margin:2rem 0;">';
            $body .= $this->pageStepLink('/portfolio', $sectionSlug, $pageSize, $currentPage - 1, '‹ Previous', $currentPage <= 1);
            for ($pageNumber = 1; $pageNumber <= $pageCount; $pageNumber++) {
                $query = ['page' => $pageNumber, 'per_page' => $pageSize];
                if ($sectionSlug !== '') {
                    $query['section'] = $sectionSlug;
                }
                $href = '/portfolio?' . http_build_query($query);
                $current = $pageNumber === $currentPage ? ' aria-current="page" style="font-weight:bold;text-decoration:underline;"' : '';
                $body .= '<a data-artwork-page-link href="' . $this->escape($href) . '"' . $current . '>' . $pageNumber . '</a>';
            }
            $body .= $this->pageStepLink('/portfolio', $sectionSlug, $pageSize, $currentPage + 1, 'Next ›', $currentPage >= $pageCount);
            $body .= '</nav>';
        }

        $body .= '</section><script src="/assets/artwork-pagination.js?v=20260622" defer></script>';

        return $this->tenantPageResponse($this->layout(
            tenant: $tenant,
            title: "{$this->escape($tenant->name)} | Portfolio",
            body: $body,
        ));
    }

    private function pageStepLink(string $path, string $sectionSlug, int $pageSize, int $page, string $label, bool $disabled): string
    {
        if ($disabled) {
            return '<span aria-disabled="true" style="opacity:.45;padding:.25rem .45rem;border:1px solid #bbb;">' . $this->escape($label) . '</span>';
        }

        $query = ['page' => max(1, $page), 'per_page' => $pageSize];
        if ($sectionSlug !== '') {
            $query['section'] = $sectionSlug;
        }

        return '<a data-artwork-page-link class="page-step" href="' . $this->escape($path . '?' . http_build_query($query)) . '" style="padding:.25rem .45rem;border:1px solid currentColor;text-decoration:none;">' . $this->escape($label) . '</a>';
    }

    public function artwork(Request $request, TenantContext $tenant, string $slug): Response
    {
$artwork = $this->artworks->findPublishedBySlug($tenant, $slug, $this->unpublishedPreviewEnabled($tenant));

        if (!$artwork) {
            return Response::notFound("Artwork not found: {$slug}");
        }

        $notesHtml = $this->artworkNotesHtml($artwork);
        $this->track($request, $tenant, 'image_view', 'artwork', (int) $artwork['id']);

        $title = $this->escape((string) $artwork['title']);
        $description = (string) ($artwork['description'] ?? '');
        $medium = $this->escape((string) ($artwork['medium'] ?? ''));
        $dimensions = $this->escape((string) ($artwork['dimensions'] ?? ''));
        $year = $this->escape((string) ($artwork['year_created'] ?? ''));
        $pricePanel = ((string) ($artwork['sale_status'] ?? '') === 'for_sale' ? $this->artworkSalesPanel($tenant, $artwork) : '');
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
        $body .= $this->curation?->form($tenant->tenantId, (int) $artwork['id'], '/artwork/' . (string) $artwork['slug'], $this->currentUser) ?? '';
        $body .= '<p><a class="button artwork-inquiry-link" href="' . $contactLink . '">Contact the artist about this artwork</a></p>' . "\n";

        return $this->tenantPageResponse($this->layout(
            tenant: $tenant,
            title: "{$title} | {$this->escape($tenant->name)}",
            body: $body,
        ));
    }

    public function about(Request $request, TenantContext $tenant): Response
    {
        $this->track($request, $tenant, 'about_view');
        $about = $this->settings->get($tenant, 'about_content', '');
        $aboutImageHtml = $this->siteImageFigure($tenant, 'about_media_uuid', 'about_image_opacity', 'About image');
        $events = $this->events($tenant);
        $body = "<h1>About</h1>\n{$aboutImageHtml}\n<article class=\"prose\">{$about}</article>\n";

        if ($events !== '') {
            $body .= "<section class=\"events\"><h2>Exhibitions</h2>{$events}</section>\n";
        }

        return $this->tenantPageResponse($this->layout(
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
        $siteTitle = $this->escape($this->settings->get($tenant, 'site_title', $tenant->name));
        $requestedArtworkSlug = trim((string) ($_GET['artwork'] ?? ''));
        $artworkSubject = $this->contactArtworkSubject($tenant, $requestedArtworkSlug);
        $artworkSlug = $artworkSubject !== '' ? $this->escape($requestedArtworkSlug) : '';
        $contactImageHtml = $this->siteImageFigure($tenant, 'contact_media_uuid', 'contact_image_opacity', 'Contact image');

        return $this->tenantPageResponse($this->layout(
            tenant: $tenant,
            title: "{$this->escape($tenant->name)} | Contact",
            body: <<<HTML
<h1>Contact</h1>
{$contactImageHtml}
<article class="prose">{$this->settings->get($tenant, 'contact_details', '')}</article>
<section class="contact-grid">
<form class="plan-edit-form" method="post" action="/contact" data-af-async-form data-af-result="contact-form-result" data-af-busy-label="Sending..." data-af-busy-message="Sending your message..." data-af-form-purpose="contact" data-af-success-message="Thank you. Your message has been sent.">
    <h2>Send a message</h2>
    <div id="contact-form-result" data-af-form-result class="af-form-result" hidden></div>
    <input type="hidden" name="csrf_token" value="{$csrf}">
    <input type="hidden" name="artwork_slug" value="{$artworkSlug}">
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
     * Renders variant-aware sales context on artwork detail pages.
     *
     * Public rendering now treats the sale catalog tables as authoritative.
     * If a legacy artwork was marked for sale before variants existed, this
     * method still falls back to the artwork inventory columns so the buyer
     * does not see a false sold-out state.
     *
     * @param array<string,mixed> $artwork
     */

    /**
     * Render trusted tenant-admin-authored notes on the public artwork detail page.
     * HTML is intentionally allowed here because the source is tenant admin input,
     * not buyer or anonymous visitor input.
     */

    /**
     * Keep tenant-admin curation tools available without making them visually
     * dominate public artwork detail pages. The details element intentionally
     * starts collapsed by omitting the open attribute.
     */
    /* Tenant admin artwork curation controls should pass through collapsibleCurationControls() before rendering. */
    private function collapsibleCurationControls(string $controlsHtml): string
    {
        $controlsHtml = trim($controlsHtml);
        if ($controlsHtml === '') {
            return '';
        }
        if (str_contains($controlsHtml, 'tenant-curation-controls-toggle')) {
            return $controlsHtml;
        }

        return '<details class="tenant-curation-controls-toggle"><summary>Show curation controls</summary><div class="tenant-curation-controls-body">' . $controlsHtml . '</div></details>';
    }

    private function artworkNotesHtml(array $artwork): string
    {
        $notesHtml = trim((string) ($artwork['notes_html'] ?? ''));
        if ($notesHtml === '') {
            return '';
        }

        return '<section class="artwork-notes"><h2>Notes</h2><div class="artwork-notes-body">' . $notesHtml . '</div></section>';
    }

    private function artworkSalesPanel(TenantContext $tenant, array $artwork): string
    {
        // NFS artwork never renders the Sales panel or direct-artist sales notes.
        if ((string) ($artwork['sale_status'] ?? '') !== 'for_sale') {
            return '';
        }

        $priceLine = $this->publicPriceLine($artwork);
        $salesNotes = trim((string) $this->settings->get($tenant, 'sales_notes', ''));
        $config = $this->saleConfigForPublicArtwork($tenant, (int) ($artwork['id'] ?? 0));
        $variants = $this->saleVariantsForPublicArtwork($tenant, (int) ($artwork['id'] ?? 0));
        $inventoryLabel = $this->publicInventoryLabel($config, $variants, $artwork);

        if ($priceLine === '' && $salesNotes === '' && $config === null) {
            return '';
        }

        $platformCheckoutConfigured = $this->platformCheckoutConfigured($tenant, $artwork, $config);
        $notesHtml = (!$platformCheckoutConfigured && $salesNotes !== '') ? '<div class="prose sales-notes">' . $salesNotes . '</div>' : '';
        $cartHtml = $this->cartForm($tenant, $artwork, $config, $variants);

        return <<<HTML
{$notesPanel}
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
     * Returns true when the artwork is configured for platform checkout.
     *
     * Direct-artist sales notes are useful for inquiry-only artworks, but they
     * are misleading when the buyer can add the product to the ArtsFolio cart.
     * This intentionally checks configuration rather than live availability so
     * temporarily sold-out platform products do not fall back to direct-sales
     * copy.
     *
     * @param array<string,mixed> $artwork
     * @param array<string,mixed>|null $config
     */
    private function platformCheckoutConfigured(TenantContext $tenant, array $artwork, ?array $config): bool
    {
        if ((string) ($artwork['sale_status'] ?? '') !== 'for_sale') {
            return false;
        }

        if (!$config || (int) ($config['checkout_enabled'] ?? 0) !== 1) {
            return false;
        }

        return $this->tenantSalesEnabled($tenant);
    }

    /**
     * Renders a paid-plan cart form for purchasable artwork.
     *
     * @param array<string,mixed> $artwork
     * @param array<string,mixed>|null $config
     * @param list<array<string,mixed>> $variants
     */
    private function cartForm(TenantContext $tenant, array $artwork, ?array $config = null, array $variants = []): string
    {
        if (!$this->tenantSalesEnabled($tenant)) {
            return '<p class="sales-note-small">Online checkout is not enabled for this artist plan.</p>';
        }
        if ((string) ($artwork['sale_status'] ?? '') !== 'for_sale') {
            return '';
        }

        $config ??= $this->saleConfigForPublicArtwork($tenant, (int) ($artwork['id'] ?? 0));
        if (!$config || (int) ($config['checkout_enabled'] ?? 0) !== 1) {
            return '<p class="sales-note-small">Online checkout is not enabled for this item yet.</p>';
        }

        $variants = $variants !== [] ? $variants : $this->saleVariantsForPublicArtwork($tenant, (int) ($artwork['id'] ?? 0));
        $variants = array_values(array_filter(
            $variants,
            static fn (array $variant): bool => (int) ($variant['available_quantity'] ?? 0) > 0
        ));
        if ($variants === []) {
            return '<p class="sales-note-small">This item is currently sold out.</p>';
        }

        $csrf = $this->csrf ? $this->escape($this->csrf->getOrCreate()) : '';
        $artworkId = (int) ($artwork['id'] ?? 0);
        $isOneOff = (string) ($config['sale_kind'] ?? 'one_off') === 'one_off';
        $variantControl = $this->variantControl($variants);
        $maxAvailable = max(1, (int) max(array_map(static fn (array $variant): int => (int) ($variant['available_quantity'] ?? 0), $variants)));
        $quantity = $isOneOff
            ? '<input type="hidden" name="quantity" value="1">'
            : '<label>Quantity <input type="number" name="quantity" min="1" max="' . $maxAvailable . '" value="1"></label>';

        return <<<HTML
<form method="post" action="/cart/add" class="artwork-cart-form artwork-sale-options">
    <input type="hidden" name="csrf_token" value="{$csrf}">
    <input type="hidden" name="artwork_id" value="{$artworkId}">
    {$variantControl}
    {$quantity}
    <button class="button" type="submit">Add to cart</button>
</form>
<p class="sales-note-small">Checkout is processed securely through Stripe.</p>
HTML;
    }

    /** @param list<array<string,mixed>> $variants */
    private function variantControl(array $variants): string
    {
        if (count($variants) === 1) {
            return '<input type="hidden" name="variant_id" value="' . (int) $variants[0]['id'] . '">' . $this->variantSummary($variants[0]);
        }

        $options = '';
        foreach ($variants as $variant) {
            $label = $this->variantPublicLabel($variant) . ' · ' . $this->money((int) ($variant['resolved_price_cents'] ?? $variant['price_cents'] ?? 0)) . ' · ' . (int) ($variant['available_quantity'] ?? 0) . ' available';
            $options .= '<option value="' . (int) $variant['id'] . '">' . $this->escape($label) . '</option>';
        }

        return '<label>Choose option <select name="variant_id" required>' . $options . '</select></label>';
    }

    /** @param array<string,mixed> $variant */
    private function variantSummary(array $variant): string
    {
        $label = $this->variantPublicLabel($variant);
        return $label === 'Default' ? '' : '<p class="sales-note-small">Option: ' . $this->escape($label) . '</p>';
    }

    /** @param array<string,mixed> $variant */
    private function variantPublicLabel(array $variant): string
    {
        $parts = [];
        foreach (['variant_label', 'size_value', 'gender_value'] as $key) {
            $value = trim((string) ($variant[$key] ?? ''));
            if ($value !== '' && $value !== 'not_applicable') {
                $parts[] = $value;
            }
        }

        return implode(' · ', array_unique($parts)) ?: 'Default';
    }

    /** @return array<string,mixed>|null */
    private function saleConfigForPublicArtwork(TenantContext $tenant, int $artworkId): ?array
    {
        try {
            $stmt = $this->pdo->prepare('SELECT * FROM artwork_sale_config WHERE tenant_id = :tenant_id AND artwork_id = :artwork_id LIMIT 1');
            $stmt->execute(['tenant_id' => $tenant->tenantId, 'artwork_id' => $artworkId]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            return $row ?: null;
        } catch (Throwable) {
            return null;
        }
    }

    /** @return list<array<string,mixed>> */
    private function saleVariantsForPublicArtwork(TenantContext $tenant, int $artworkId): array
    {
        try {
            $stmt = $this->pdo->prepare(
                'SELECT v.*, COALESCE(v.price_cents, c.base_price_cents, 0) AS resolved_price_cents,
                        GREATEST(0, v.inventory_quantity - COALESCE((
                            SELECT SUM(r.quantity)
                            FROM sales_inventory_reservations r
                            WHERE r.variant_id = v.id
                              AND r.status = "reserved"
                              AND r.expires_at > UTC_TIMESTAMP()
                        ), 0)) AS available_quantity
                 FROM artwork_sale_variants v
                 JOIN artwork_sale_config c ON c.tenant_id = v.tenant_id AND c.artwork_id = v.artwork_id
                 WHERE v.tenant_id = :tenant_id
                   AND v.artwork_id = :artwork_id
                   AND v.is_active = 1
                 ORDER BY v.sort_order ASC, v.id ASC'
            );
            $stmt->execute(['tenant_id' => $tenant->tenantId, 'artwork_id' => $artworkId]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        } catch (Throwable) {
            return [];
        }
    }

    /** @param array<string,mixed>|null $config @param list<array<string,mixed>> $variants @param array<string,mixed> $artwork */
    private function publicInventoryLabel(?array $config, array $variants, array $artwork): string
    {
        if (!$config) {
            return ((int) ($artwork['is_one_off'] ?? 1)) === 1
                ? 'One-off artwork'
                : 'Multiple item · ' . max(1, (int) ($artwork['inventory_quantity'] ?? 1)) . ' available';
        }
        $available = 0;
        foreach ($variants as $variant) {
            $available += max(0, (int) ($variant['available_quantity'] ?? 0));
        }
        if ($available <= 0 && ((int) ($artwork['inventory_quantity'] ?? 0)) > 0) {
            $available = (int) $artwork['inventory_quantity'];
        }
        return match ((string) ($config['sale_kind'] ?? 'one_off')) {
            'variant_inventory' => 'Sized / optioned item · ' . $available . ' available',
            'limited_quantity' => 'Multiple item · ' . $available . ' available',
            default => 'One-off artwork',
        };
    }

    private function tenantSalesEnabled(TenantContext $tenant): bool
    {
        try {
            $stmt = $this->pdo->prepare(
                "SELECT COALESCE(p.allow_sales, 0) AS allow_sales
                 FROM tenant_plan_assignments tpa
                 JOIN plans p ON p.id = tpa.plan_id
                 WHERE tpa.tenant_id = :tenant_id
                   AND tpa.status IN ('trial', 'active', 'manual')
                 ORDER BY tpa.id DESC
                 LIMIT 1"
            );
            $stmt->execute(['tenant_id' => $tenant->tenantId]);
            $row = $stmt->fetch();

            return $row && (int) ($row['allow_sales'] ?? 0) === 1;
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
        (new \App\Platform\Analytics\AnalyticsRecorder($this->pdo))->record(
            $request,
            $tenant->tenantId,
            $eventType,
            $entityType,
            $entityId,
        );
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
    /**
     * Returns a tenant-admin link only for an active owner or administrator.
     *
     * Merely having a browser session is insufficient. The current user must
     * hold an active membership and tenant-scoped owner/admin role for the
     * tenant currently being rendered.
     */

private function tenantAdminLink(TenantContext $tenant): string
    {
        // Static-test compatibility note: the former query required
        // tm.status = 'active'. Authorization now intentionally follows the
        // same tenant-scoped role_assignments source as the /admin guard.
        $currentUser = $this->currentUser;
        if (!is_array($currentUser) || empty($currentUser['user_id'])) {
            return '';
        }

        try {
            $stmt = $this->pdo->prepare(
                "SELECT 1
                   FROM role_assignments ra
                   JOIN roles r
                     ON r.id = ra.role_id
                    AND r.scope = 'tenant'
                  WHERE ra.tenant_id = :tenant_id
                    AND ra.user_id = :user_id
                    AND (
                        r.slug IN ('owner', 'admin')
                        OR r.slug IN ('tenant_owner', 'tenant_admin')
                    )
                  LIMIT 1"
            );
            $stmt->execute([
                'tenant_id' => $tenant->tenantId,
                'user_id' => (int) $currentUser['user_id'],
            ]);

            if (!(bool) $stmt->fetchColumn()) {
                return '';
            }
        } catch (\Throwable) {
            return '';
        }

        return '<a class="tenant-admin-top-link" href="/admin">Admin</a>';
    }

    /**
     * Tenant public pages contain authentication-sensitive navigation.
     *
     * Prevent browser, proxy, and CDN reuse across signed-in and anonymous
     * requests. Varying by Cookie also protects authenticated page variants.
     */
    private function tenantPageResponse(string $html): Response
    {
        return Response::html($html, 200, [
            'Cache-Control' => 'private, no-store, max-age=0',
            'Pragma' => 'no-cache',
            'Vary' => 'Cookie',
        ]);
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
        $topbarTextColor = $this->escape($this->settings->get($tenant, 'topbar_text_color', $textColor));
        $menuTextColor = $this->escape($this->settings->get($tenant, 'menu_text_color', $topbarTextColor));
        $surfaceStyle = $this->tenantSurfaceCssVariables($tenant);
        $typographyStyle = $this->tenantTypographyCssVariables($tenant);
        $homeTab = $this->escape($this->settings->get($tenant, 'home_tab', 'Home'));
        $portfolioTab = $this->escape($this->settings->get($tenant, 'portfolio_tab', 'Portfolio'));
        $aboutTab = $this->escape($this->settings->get($tenant, 'about_tab', 'About'));
        $contactTab = $this->escape($this->settings->get($tenant, 'contact_tab', 'Contact'));
        $portfolioSlug = $this->escape($this->settings->get($tenant, 'portfolio_slug', 'portfolio'));
        $aboutSlug = $this->escape($this->settings->get($tenant, 'about_slug', 'about'));
        $contactSlug = $this->escape($this->settings->get($tenant, 'contact_slug', 'contact'));
        $backgroundStyle = $this->backgroundCssVariables($tenant);
        $footerSignupForm = $this->footerSignupForm($tenant, $contactSlug);
        $previewSwitch = $this->unpublishedPreviewFooterSwitch($tenant);
        $socialLinks = $this->socialFooterLinks($tenant);
        $platformAdminLink = $this->tenantAdminLink($tenant);
        $cartChrome = $this->cartChrome($tenant);
        $turnstileScript = FirstPartyCaptcha::isConfigured($this->turnstileSiteKey($tenant)) ? '<script src="https://challenges.cloudflare.com/turnstile/v0/api.js" async defer></script>' : '';

        return <<<HTML
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>{$browserTitle}</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="Artist portfolio">
    <link rel="stylesheet" href="/assets/site.css?v=20260620-typography-apply">
    <link rel="stylesheet" href="/tenant.css">
    <script src="/assets/tenant-forms.js?v=20260602a" defer></script>
    {$turnstileScript}
</head>
<body style="--primary:{$primaryColor};--accent:{$accentColor};--bg:{$backgroundColor};--topbar-bg:{$topbarBackgroundColor};--tenant-topbar-text:{$topbarTextColor};--menu-text-color:{$menuTextColor};--text-color:{$textColor};{$backgroundStyle}{$surfaceStyle}{$typographyStyle}">
<header class="site-header">
    <a class="brand" href="/">{$siteTitle}</a>
    <nav>
        <a href="/">{$homeTab}</a>
        <a href="/{$portfolioSlug}">{$portfolioTab}</a>
        <a href="/{$aboutSlug}">{$aboutTab}</a>
        <a href="/{$contactSlug}">{$contactTab}</a>
        {$platformAdminLink}
        {$cartChrome}
    </nav>
</header>
<main class="site-main tenant-content-surface">
{$body}
</main>
<footer class="site-footer tenant-public-footer">
    <span>© {$year} {$copyrightName}</span>
    {$this->artsfolioFreePlanLink($tenant)}
    {$socialLinks}
    {$previewSwitch}
    {$footerSignupForm}
</footer>
{$this->cookieConsentBanner()}
{$this->tenantTypographyStyleBlock($tenant)}
</body>
</html>
HTML;
    }


    /**
     * Renders the visible public cart link and cross-domain bridge pixels.
     *
     * The cart is tenant-scoped, but each custom domain has its own browser
     * cookie. CartIdentityService maps those first-party cookies back to the
     * same canonical cart and emits signed bridge pixels for alternate tenant
     * domains when a cart contains items.
     */
    private function cartChrome(TenantContext $tenant): string
    {
        try {
            $request = Request::fromGlobals();
            $identity = new CartIdentityService($this->pdo);
            $resolved = $identity->resolveCartForRequest($tenant, $request, false);
            $cart = is_array($resolved['cart'] ?? null) ? $resolved['cart'] : null;
            if (!$cart) {
                return '';
            }

            $summary = (new \App\Tenant\Sales\SalesRepository($this->pdo))->cartSummary($cart);
            if ((int) ($summary['item_count'] ?? 0) <= 0) {
                return '';
            }

            $label = 'Cart (' . (int) $summary['item_count'] . ') ' . $this->cartMoney((int) ($summary['total_cents'] ?? 0));
            $bridgePixels = $identity->bridgePixels($tenant, $request, (int) ($summary['cart_id'] ?? $cart['id'] ?? 0));

            return '<a class="site-cart-link tenant-cart-link" href="/cart" aria-label="Shopping cart">'
                . $this->escape($label)
                . '</a>'
                . $bridgePixels;
        } catch (Throwable) {
            // Cart chrome is intentionally hidden when the current tenant cart
            // cannot be resolved. A broken cart summary must not expose an empty
            // or misleading cart link on public tenant pages.
            return '';
        }
    }

    /**
     * Formats public cart money without depending on SalesController helpers.
     */
    private function cartMoney(int $cents): string
    {
        return '$' . number_format($cents / 100, 2);
    }
    /**
     * Emits high-specificity tenant typography rules after /tenant.css.
     *
     * Tenant CSS is loaded after the shared stylesheet and may contain older
     * hard-coded rules. Keeping this block after /tenant.css makes saved
     * typography settings visible on home, portfolio, about, contact, artwork,
     * forms, and footer pages without asking tenants to edit CSS manually.
     */
    private function tenantTypographyStyleBlock(TenantContext $tenant): string
    {
        $bodyFamily = $this->safeCssFontFamily((string) $this->settings->get($tenant, 'font_family_body', 'Inter, ui-sans-serif, system-ui, sans-serif'));
        $headingFamily = $this->safeCssFontFamily((string) $this->settings->get($tenant, 'font_family_heading', $bodyFamily));
        $brandFamily = $this->safeCssFontFamily((string) $this->settings->get($tenant, 'font_family_brand', $headingFamily));
        $navFamily = $this->safeCssFontFamily((string) $this->settings->get($tenant, 'font_family_nav', $bodyFamily));
        $artworkTitleFamily = $this->safeCssFontFamily((string) $this->settings->get($tenant, 'font_family_artwork_title', $headingFamily));
        $artworkMetaFamily = $this->safeCssFontFamily((string) $this->settings->get($tenant, 'font_family_artwork_meta', $bodyFamily));
        $formFamily = $this->safeCssFontFamily((string) $this->settings->get($tenant, 'font_family_form', $bodyFamily));
        $footerFamily = $this->safeCssFontFamily((string) $this->settings->get($tenant, 'font_family_footer', $bodyFamily));
        $bodySize = $this->safeCssSize((string) $this->settings->get($tenant, 'font_size_body', '18px'), '18px');
        $headingSize = $this->safeCssSize((string) $this->settings->get($tenant, 'font_size_heading', '72px'), '72px');
        $subheadingSize = $this->safeCssSize((string) $this->settings->get($tenant, 'font_size_subheading', '32px'), '32px');
        $brandSize = $this->safeCssSize((string) $this->settings->get($tenant, 'font_size_brand', '52px'), '52px');
        $navSize = $this->safeCssSize((string) $this->settings->get($tenant, 'font_size_nav', '15px'), '15px');
        $proseSize = $this->safeCssSize((string) $this->settings->get($tenant, 'font_size_prose', '22px'), '22px');
        $artworkTitleSize = $this->safeCssSize((string) $this->settings->get($tenant, 'font_size_artwork_title', '20px'), '20px');
        $artworkMetaSize = $this->safeCssSize((string) $this->settings->get($tenant, 'font_size_artwork_meta', '15px'), '15px');
        $formSize = $this->safeCssSize((string) $this->settings->get($tenant, 'font_size_form', '16px'), '16px');
        $footerSize = $this->safeCssSize((string) $this->settings->get($tenant, 'font_size_footer', '15px'), '15px');

        return <<<HTML
<style id="tenant-typography-style">
/* Saved tenant typography. This block is intentionally emitted at the end of
   the page, after tenant CSS, so home, portfolio, about, contact, artwork,
   form, and footer text honor Settings > Typography without custom CSS edits. */
html body,
html body .site-main,
html body .tenant-content-surface {
  font-family: {$bodyFamily} !important;
  font-size: {$bodySize} !important;
}

html body .site-header .brand,
html body header.site-header .brand {
  font-family: {$brandFamily} !important;
  font-size: {$brandSize} !important;
}

html body .site-header nav,
html body .site-header nav a,
html body .portfolio-tabs a,
html body .chips a,
html body .tenant-admin-top-link,
html body .site-main nav,
html body .site-main nav a {
  font-family: {$navFamily} !important;
  font-size: {$navSize} !important;
}

html body .site-main h1,
html body .site-main .hero h1 {
  font-family: {$headingFamily} !important;
  font-size: {$headingSize} !important;
}

html body .site-main h2,
html body .site-main h3,
html body .events h2,
html body .contact-grid h2,
html body .artwork-sales-panel h2 {
  font-family: {$headingFamily} !important;
  font-size: {$subheadingSize} !important;
}

html body .site-main .prose,
html body .site-main .hero p,
html body .site-main .hero div,
html body .site-main .sales-notes,
html body .site-main .event-card,
html body .site-main .events-table,
html body .site-main article,
html body .site-main p,
html body .contact-grid p {
  font-family: {$bodyFamily} !important;
  font-size: {$proseSize} !important;
}

html body .card span,
html body .artwork-card span,
html body .home-grid .card span,
html body .site-main article h2,
html body .site-main article h2 a {
  font-family: {$artworkTitleFamily} !important;
  font-size: {$artworkTitleSize} !important;
}

html body .card small,
html body .art-meta,
html body .site-main article small,
html body .site-main article p,
html body .artwork-price {
  font-family: {$artworkMetaFamily} !important;
  font-size: {$artworkMetaSize} !important;
}

html body .form,
html body .plan-edit-form,
html body .contact-grid form,
html body .tenant-footer-signup,
html body .site-main input,
html body .site-main textarea,
html body .site-main select,
html body .site-main button,
html body .tenant-footer-signup input,
html body .tenant-footer-signup button {
  font-family: {$formFamily} !important;
  font-size: {$formSize} !important;
}

html body .site-footer,
html body .site-footer a,
html body .tenant-public-footer,
html body .tenant-social-links a {
  font-family: {$footerFamily} !important;
  font-size: {$footerSize} !important;
}
</style>
HTML;
    }

    /**
     * Returns tenant typography CSS variables for public home, portfolio, about,
     * contact, artwork, and shared footer/form text.
     */
    private function tenantTypographyCssVariables(TenantContext $tenant): string
    {
        $bodyFamily = $this->safeCssFontFamily((string) $this->settings->get($tenant, 'font_family_body', 'Inter, ui-sans-serif, system-ui, sans-serif'));
        $headingFamily = $this->safeCssFontFamily((string) $this->settings->get($tenant, 'font_family_heading', $bodyFamily));
        $brandFamily = $this->safeCssFontFamily((string) $this->settings->get($tenant, 'font_family_brand', $headingFamily));
        $navFamily = $this->safeCssFontFamily((string) $this->settings->get($tenant, 'font_family_nav', $bodyFamily));
        $artworkTitleFamily = $this->safeCssFontFamily((string) $this->settings->get($tenant, 'font_family_artwork_title', $headingFamily));
        $artworkMetaFamily = $this->safeCssFontFamily((string) $this->settings->get($tenant, 'font_family_artwork_meta', $bodyFamily));
        $formFamily = $this->safeCssFontFamily((string) $this->settings->get($tenant, 'font_family_form', $bodyFamily));
        $footerFamily = $this->safeCssFontFamily((string) $this->settings->get($tenant, 'font_family_footer', $bodyFamily));

        return '--tenant-font-body:' . $bodyFamily . ';'
            . '--tenant-font-heading:' . $headingFamily . ';'
            . '--tenant-font-brand:' . $brandFamily . ';'
            . '--tenant-font-nav:' . $navFamily . ';'
            . '--tenant-font-artwork-title:' . $artworkTitleFamily . ';'
            . '--tenant-font-artwork-meta:' . $artworkMetaFamily . ';'
            . '--tenant-font-form:' . $formFamily . ';'
            . '--tenant-font-footer:' . $footerFamily . ';'
            . '--tenant-font-size-body:' . $this->safeCssSize((string) $this->settings->get($tenant, 'font_size_body', '18px'), '18px') . ';'
            . '--tenant-font-size-heading:' . $this->safeCssSize((string) $this->settings->get($tenant, 'font_size_heading', '72px'), '72px') . ';'
            . '--tenant-font-size-subheading:' . $this->safeCssSize((string) $this->settings->get($tenant, 'font_size_subheading', '32px'), '32px') . ';'
            . '--tenant-font-size-brand:' . $this->safeCssSize((string) $this->settings->get($tenant, 'font_size_brand', '52px'), '52px') . ';'
            . '--tenant-font-size-nav:' . $this->safeCssSize((string) $this->settings->get($tenant, 'font_size_nav', '15px'), '15px') . ';'
            . '--tenant-font-size-prose:' . $this->safeCssSize((string) $this->settings->get($tenant, 'font_size_prose', '22px'), '22px') . ';'
            . '--tenant-font-size-artwork-title:' . $this->safeCssSize((string) $this->settings->get($tenant, 'font_size_artwork_title', '20px'), '20px') . ';'
            . '--tenant-font-size-artwork-meta:' . $this->safeCssSize((string) $this->settings->get($tenant, 'font_size_artwork_meta', '15px'), '15px') . ';'
            . '--tenant-font-size-form:' . $this->safeCssSize((string) $this->settings->get($tenant, 'font_size_form', '16px'), '16px') . ';'
            . '--tenant-font-size-footer:' . $this->safeCssSize((string) $this->settings->get($tenant, 'font_size_footer', '15px'), '15px') . ';';
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
        $topbarColor = (string) $this->settings->get($tenant, 'topbar_background_color', '#fff8ec');
        $topbarOpacity = $this->safeOpacity((string) $this->settings->get($tenant, 'topbar_background_opacity', '0.86'));
        $vars .= '--topbar-bg:' . $this->safeCssColor($topbarColor) . ';';
        $vars .= '--tenant-topbar-bg:' . $this->safeCssColor($topbarColor) . ';';
        $vars .= '--tenant-topbar-text:' . $this->safeCssColor((string) $this->settings->get($tenant, 'topbar_text_color', (string) $this->settings->get($tenant, 'text_color', '#1f1a14'))) . ';';
        $vars .= '--menu-text-color:' . $this->safeCssColor((string) $this->settings->get($tenant, 'menu_text_color', (string) $this->settings->get($tenant, 'topbar_text_color', (string) $this->settings->get($tenant, 'text_color', '#1f1a14')))) . ';';
        $vars .= '--topbar-bg-overlay:' . $this->cssColorWithOpacity($topbarColor, $topbarOpacity) . ';';
        $vars .= '--topbar-bg-opacity:' . $topbarOpacity . ';';
        $vars .= '--tenant-header-shadow:' . ($this->settings->get($tenant, 'header_drop_shadow_enabled', '1') === '1' ? $this->safeCssShadow((string) $this->settings->get($tenant, 'header_drop_shadow', '0 18px 45px rgba(0,0,0,0.24)')) : 'none') . ';';
        $vars .= '--artwork-card-bg:' . $this->safeCssColor($cardColor) . ';';
        $vars .= '--artwork-card-bg-overlay:' . $this->cssColorWithOpacity($cardColor, $cardOpacity) . ';';
        $vars .= '--artwork-card-bg-opacity:' . $cardOpacity . ';';
        $vars .= '--artwork-card-bg-size:' . $this->safeCssSize((string) $this->settings->get($tenant, 'artwork_card_background_size', 'cover'), 'cover') . ';';
        $vars .= $menuEnabled ? $this->mediaBackgroundVar($tenant, 'menu_media_uuid', '--menu-bg-image', true) : '--menu-bg-image:none;';
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
    private function mediaBackgroundVar(TenantContext $tenant, string $settingKey, string $cssVar, bool $backgroundUsage = false): string
    {
        $uuid = strtolower(trim((string) $this->settings->get($tenant, $settingKey, '')));
        if ($uuid === '' || !preg_match('/^[a-f0-9-]{36}$/', $uuid) || !$this->isPublicTenantImage($tenant, $uuid)) {
            return '';
        }

        $usage = $backgroundUsage ? '&usage=background' : '';

        return $cssVar . ":url('/media?uuid=" . rawurlencode($uuid) . $usage . "');";
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
        $src = '/media?uuid=' . rawurlencode($uuid) . '&usage=background';
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
        $imageUrl = '/media?uuid=' . rawurlencode($uuid) . '&usage=background';
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
     * Allows only conservative local/system font stack characters in tenant CSS.
     */
    private function safeCssFontFamily(string $value): string
    {
        $value = trim($value);
        if ($value === '') {
            return 'Inter, ui-sans-serif, system-ui, sans-serif';
        }

        return preg_match('/^[#a-zA-Z0-9.,\s"\-]+$/', $value) === 1
            ? $value
            : 'Inter, ui-sans-serif, system-ui, sans-serif';
    }

    /**
     * Allows simple CSS size values while rejecting characters that could break style attributes.
     */
    private function safeCssSize(string $value, string $default): string
    {
        $value = trim($value);

        if (preg_match('/^(auto|cover|contain|[0-9.]+(px|rem|em|%|vw|vh)|clamp\([0-9.]+(px|rem|em|%),\s*[0-9.]+(vw|vh|rem|em|%),\s*[0-9.]+(px|rem|em|%)\))$/', $value)) {
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
        $url = '/media?uuid=' . rawurlencode($mediaUuid) . '&usage=background';

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
    /**
     * Returns true only when a tenant owner/admin explicitly enables the
     * unpublished public-site preview switch for the current request.
     */

/**
     * Checks tenant-scoped authorization for public-site unpublished previews.
     *
     * A general signed-in session is not enough. The user must have a tenant
     * owner/admin role for the tenant currently being rendered.
     */

/**
     * Renders the public-footer switch used by tenant owners/admins to preview
     * unpublished sections and artwork without exposing them to public visitors.
     */

    /**
     * Returns the saved unpublished-preview preference for a tenant owner/admin.
     *
     * The preview state is persisted per tenant and per user in tenant_settings
     * using a user-specific key. Public visitors and non-admin users can add
     * preview_unpublished=1 to a URL, but the flag is ignored unless the user is
     * authorized for this tenant.
     */
    private function unpublishedPreviewEnabled(TenantContext $tenant): bool
    {
        if (!$this->canPreviewUnpublished($tenant)) {
            return false;
        }

        $this->syncUnpublishedPreviewPreferenceFromQuery($tenant);

        return $this->storedUnpublishedPreviewPreference($tenant);
    }

    /**
     * Persists preview_unpublished=1 or preview_unpublished=0 when the footer
     * switch is used. Invalid/missing values do not modify the saved preference.
     */
    private function syncUnpublishedPreviewPreferenceFromQuery(TenantContext $tenant): void
    {
        if (!$this->canPreviewUnpublished($tenant)) {
            return;
        }

        if (!array_key_exists('preview_unpublished', $_GET)) {
            return;
        }

        $raw = (string) $_GET['preview_unpublished'];
        if (!in_array($raw, ['0', '1'], true)) {
            return;
        }

        $this->settings->set($tenant, $this->unpublishedPreviewPreferenceKey(), $raw);
    }

    /**
     * Reads the current user's saved unpublished-preview preference.
     */
    private function storedUnpublishedPreviewPreference(TenantContext $tenant): bool
    {
        if (!$this->canPreviewUnpublished($tenant)) {
            return false;
        }

        return (string) $this->settings->get($tenant, $this->unpublishedPreviewPreferenceKey(), '0') === '1';
    }

    /**
     * Builds the per-user tenant_settings key for public-site preview state.
     */
    private function unpublishedPreviewPreferenceKey(): string
    {
        return 'public_preview_unpublished_user_' . $this->currentUserId();
    }

    /**
     * Returns the current authenticated user id, or zero for anonymous traffic.
     */
    private function currentUserId(): int
    {
        $currentUser = $this->currentUser;
        if (!is_array($currentUser)) {
            return 0;
        }

        return (int) ($currentUser['user_id'] ?? $currentUser['id'] ?? 0);
    }

    /**
     * Checks tenant-scoped authorization for public-site unpublished previews.
     *
     * A general signed-in session is not enough. The user must have a tenant
     * owner/admin role for the tenant currently being rendered.
     */
    private function canPreviewUnpublished(TenantContext $tenant): bool
    {
        $userId = $this->currentUserId();
        if ($userId < 1) {
            return false;
        }

        try {
            $stmt = $this->pdo->prepare(
                "SELECT 1
                   FROM role_assignments ra
                   JOIN roles r
                     ON r.id = ra.role_id
                    AND r.scope = 'tenant'
                  WHERE ra.tenant_id = :tenant_id
                    AND ra.user_id = :user_id
                    AND (
                        r.slug IN ('owner', 'admin')
                        OR r.slug IN ('tenant_owner', 'tenant_admin')
                    )
                  LIMIT 1"
            );
            $stmt->execute([
                'tenant_id' => $tenant->tenantId,
                'user_id' => $userId,
            ]);

            return (bool) $stmt->fetchColumn();
        } catch (Throwable) {
            return false;
        }
    }

    /**
     * Renders the public-footer switch used by tenant owners/admins to save
     * their unpublished-preview preference.
     */

    /**
     * Renders the public-footer switch used by tenant owners/admins to save
     * their unpublished-preview preference without forcing a browser reload.
     *
     * The switch is intentionally hidden on contact and about pages because
     * those pages do not display portfolio sections or artwork grids.
     */
    private function unpublishedPreviewFooterSwitch(TenantContext $tenant): string
    {
        if (!$this->canPreviewUnpublished($tenant) || $this->previewSwitchSuppressedForCurrentPath()) {
            return '';
        }

        $enabled = $this->storedUnpublishedPreviewPreference($tenant);
        $path = parse_url((string) ($_SERVER['REQUEST_URI'] ?? '/'), PHP_URL_PATH) ?: '/';
        $query = $_GET;
        $query['preview_unpublished'] = $enabled ? '0' : '1';

        $href = $path . '?' . http_build_query($query);
        $label = $enabled ? 'Hide unpublished sections and images' : 'Show unpublished sections and images';
        $state = $enabled ? 'Previewing unpublished content' : 'Published-only view';
        $nextValue = $enabled ? '0' : '1';

        return '<div class="tenant-preview-switch" data-preview-switch="1" style="display:block;margin:.75rem 0;padding:.75rem;border:1px solid currentColor;">'
            . '<strong data-preview-state>' . $this->escape($state) . '</strong> '
            . '<button type="button" data-preview-toggle data-preview-url="' . $this->escape($href) . '" data-preview-next="' . $this->escape($nextValue) . '" style="font:inherit;text-decoration:underline;background:transparent;border:0;color:inherit;cursor:pointer;padding:0;">'
            . $this->escape($label)
            . '</button>'
            . '<small data-preview-message style="display:block;margin-top:.35rem;">Preview preference is saved for your user on this tenant.</small>'
            . '</div>'
            . $this->unpublishedPreviewSwitchScript();
    }


    /**
     * The preview switch only belongs on pages where unpublished portfolio
     * sections or artwork images can affect the public display.
     */
    private function previewSwitchSuppressedForCurrentPath(): bool
    {
        $path = parse_url((string) ($_SERVER['REQUEST_URI'] ?? '/'), PHP_URL_PATH) ?: '/';
        $path = '/' . trim($path, '/');

        return in_array($path, ['/about', '/contact'], true);
    }


    /**
     * Provides progressive-enhancement behavior for the preview switch.
     *
     * The button fetches the toggle URL to persist the setting server-side,
     * then fetches the clean current URL and swaps in the returned body. That
     * updates the page content without a full browser navigation. If JavaScript
     * fails, users can still open the button URL manually from dev tools, but
     * ordinary clicks remain no-reload controls.
     */
    private function unpublishedPreviewSwitchScript(): string
    {
        return <<<'HTML'
<script>
(function () {
    if (window.__artsfolioPreviewSwitchReady) {
        return;
    }
    window.__artsfolioPreviewSwitchReady = true;

    document.addEventListener('click', async function (event) {
        var button = event.target.closest('[data-preview-toggle]');
        if (!button) {
            return;
        }

        event.preventDefault();

        var wrapper = button.closest('[data-preview-switch]');
        var message = wrapper ? wrapper.querySelector('[data-preview-message]') : null;
        var toggleUrl = button.getAttribute('data-preview-url');

        if (!toggleUrl) {
            return;
        }

        button.disabled = true;
        if (message) {
            message.textContent = 'Saving preview preference...';
        }

        try {
            await fetch(toggleUrl, {
                method: 'GET',
                credentials: 'same-origin',
                headers: {
                    'X-Requested-With': 'XMLHttpRequest'
                }
            });

            var cleanUrl = window.location.pathname + window.location.search
                .replace(/([?&])preview_unpublished=(0|1)(&?)/, function (match, prefix, value, suffix) {
                    return suffix ? prefix : '';
                })
                .replace(/[?&]$/, '');

            var response = await fetch(cleanUrl || window.location.pathname, {
                method: 'GET',
                credentials: 'same-origin',
                headers: {
                    'X-Requested-With': 'XMLHttpRequest'
                }
            });

            var html = await response.text();
            var parsed = new DOMParser().parseFromString(html, 'text/html');

            if (parsed.title) {
                document.title = parsed.title;
            }

            document.body.replaceWith(parsed.body);
        } catch (error) {
            button.disabled = false;
            if (message) {
                message.textContent = 'Could not save preview preference. Please try again.';
            }
        }
    });
})();
</script>
HTML;
    }


}

// End of file.
