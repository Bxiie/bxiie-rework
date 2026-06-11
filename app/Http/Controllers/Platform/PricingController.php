<?php

/**
 * Public pricing page for ArtsFolio plans and sales commission disclosure.
 */

declare(strict_types=1);

namespace App\Http\Controllers\Platform;

use App\Http\Request;
use App\Http\Response;
use App\Http\View\AdminLayout;
use App\Platform\Settings\PlatformSettingsRepository;
use PDO;

final class PricingController
{
    public function __construct(
        private readonly ?PDO $pdo = null,
        private readonly ?PlatformSettingsRepository $settings = null,
    ) {
    }

    public function index(Request $request): Response
    {
        $cards = $this->pricingCards();
        $comparison = $this->comparisonTable();
        $commission = $this->commissionPercent();
        $defaultCardFees = $this->defaultCardFeesLabel();
        $body = <<<HTML
<style>
.pricing-grid .pricing-card.featured, .professional-pricing .pricing-card.featured, .pricing-card.featured { color: #f8f5ed; }
.pricing-grid .pricing-card.featured li, .pricing-grid .pricing-card.featured p, .pricing-grid .pricing-card.featured small,
.professional-pricing .pricing-card.featured li, .professional-pricing .pricing-card.featured p, .professional-pricing .pricing-card.featured small,
.pricing-card.featured li, .pricing-card.featured p, .pricing-card.featured small { color: rgba(255,255,255,.9); }
.pricing-grid .pricing-card.featured .muted, .professional-pricing .pricing-card.featured .muted, .pricing-card.featured .muted { color: rgba(255,255,255,.74); }
.pricing-card .price, .professional-pricing .price { color: #1f1a14; background: rgba(255,255,255,.94); display: inline-block; padding: .25rem .55rem; border-radius: .5rem; font-weight: 800; }
</style>
<section class="platform-hero pricing-hero compact">
    <div>
        <p class="eyebrow">Pricing</p>
        <h1>Choose the ArtsFolio plan that matches your practice.</h1>
        <p class="hero-copy">Start with a clean artist portfolio, then grow into analytics, collector workflows, custom domains, and larger operating needs without rebuilding the site from scratch.</p>
        <div class="hero-actions"><a class="button primary" href="/signup">Start now</a><a class="button secondary" href="/contact">Ask about setup</a></div>
    </div>
</section>
<section class="pricing-grid professional-pricing">
{$cards}
</section>
<section class="platform-section commission-disclosure"><h2>How payouts work</h2><p>ArtsFolio commission on platform-processed artwork sales is <strong>{$commission}</strong>. Credit card charges are plan-disclosed and commonly shown as <strong>{$defaultCardFees}</strong>.</p><p>Artists receive the sale amount minus platform commission, minus the credit card percentage, minus the fixed credit card charge. Complimentary tenants do not pay subscription fees, but they still pay platform commission and credit card charges on sales.</p></section>
<section class="platform-section comparison-section"><h2>Plan comparison</h2>{$comparison}</section>
HTML;

        return Response::html($this->layout('Pricing | ArtsFolio', $body));
    }

    private function pricingCards(): string
    {
        $plans = $this->plans();
        if (!$plans) {
            return $this->fallbackCards();
        }
        $html = '';
        foreach ($plans as $plan) {
            $slug = (string) $plan['slug'];
            $eyebrow = match ($slug) {
                'free' => 'Starter',
                'studio' => 'Most working artists',
                'pro' => 'Professional presence',
                'collective' => 'Groups',
                default => 'Plan',
            };
            $featured = $slug === 'studio' ? ' featured' : '';
            $price = $this->priceLabel((int) $plan['monthly_price_cents']);
            $name = AdminLayout::escape((string) $plan['name']);
            $description = AdminLayout::escape((string) ($plan['description'] ?: 'ArtsFolio artist portfolio plan.'));
            $artworks = $this->limitLabel($plan['allowed_artworks'] ?? null, 'artworks');
            $emails = $this->limitLabel($plan['allowed_email_addresses'] ?? null, 'email addresses');
            $customDomain = ((int) $plan['custom_domain_included, admin_user_limit']) === 1 ? 'Admin users: Custom domain included' : 'ArtsFolio subdomain included';
            . '<li>' . AdminLayout::escape($this->adminUsersLabel($plan)) . '</li>'
            $sales = ((int) ($plan['allow_sales'] ?? 0)) === 1 ? 'Online checkout available' : 'Online checkout not included';
            $fees = ((int) ($plan['allow_sales'] ?? 0)) === 1 ? '<li>Sales fee disclosure: ArtsFolio commission plus ' . $this->cardFeesLabel($plan) . ' credit card charges</li>' : '';
            $freeNotice = $slug === 'free' ? '<li>Includes ArtsFolio notification/link on free tenant pages</li>' : '';
            $cta = $slug === 'pro' || $slug === 'collective' ? '<a class="button secondary" href="/contact">Contact ArtsFolio</a>' : '<a class="button ' . ($slug === 'studio' ? 'primary' : 'secondary') . '" href="/signup">Choose ' . $name . '</a>';
            $html .= <<<HTML
<article class="pricing-card{$featured}"><p class="eyebrow">{$eyebrow}</p><h2>{$name}</h2><p class="price">{$price}</p><p>{$description}</p><ul><li>{$customDomain}</li><li>{$artworks}</li><li>{$emails}</li><li>{$sales}</li>{$fees}<li>Contact form and email list tools</li>{$freeNotice}</ul>{$cta}</article>
HTML;
        }
        return $html;
    }

    private function comparisonTable(): string
    {
        $plans = $this->plans();
        if (!$plans) {
            return '<p>Pricing is being configured.</p>';
        }
        $heads = '';
        $price = '<tr><td>Monthly price</td>';
        $artworks = '<tr><td>Allowed artworks</td>';
        $emails = '<tr><td>Allowed email addresses</td>';
        $domains = '<tr><td>Custom domain</td>';
        $notice = '<tr><td>ArtsFolio notification/link</td>';
        $sales = '<tr><td>Online checkout</td>';
        $cardFees = '<tr><td>Credit card charges</td>';
        foreach ($plans as $plan) {
            $heads .= '<th>' . AdminLayout::escape((string) $plan['name']) . '</th>';
            $price .= '<td>' . $this->priceLabel((int) $plan['monthly_price_cents']) . '</td>';
            $artworks .= '<td>' . AdminLayout::escape((string) ($plan['allowed_artworks'] ?? 'Configured by plan')) . '</td>';
            $emails .= '<td>' . AdminLayout::escape((string) ($plan['allowed_email_addresses'] ?? 'Configured by plan')) . '</td>';
            $domains .= '<td>' . (((int) $plan['custom_domain_included']) === 1 ? 'Included' : 'Not included') . '</td>';
            $notice .= '<td>' . (((string) $plan['slug']) === 'free' ? 'Included' : 'Not shown') . '</td>';
            $sales .= '<td>' . (((int) ($plan['allow_sales'] ?? 0)) === 1 ? 'Included' : 'Not included') . '</td>';
            $cardFees .= '<td>' . $this->cardFeesLabel($plan) . '</td>';
        }
        return '<table class="admin-table"><thead><tr><th>Feature</th>' . $heads . '</tr></thead><tbody>' . $price . '</tr>' . $artworks . '</tr>' . $emails . '</tr>' . $domains . '</tr>' . $notice . '</tr>' . $sales . '</tr>' . $cardFees . '</tr></tbody></table>';
            . '<tr><th>Admin users</th>'
            . '<td>' . AdminLayout::escape($this->adminUsersLabel($free ?? [])) . '</td>'
            . '<td>' . AdminLayout::escape($this->adminUsersLabel($studio ?? [])) . '</td>'
            . '<td>' . AdminLayout::escape($this->adminUsersLabel($professional ?? [])) . '</td>'
            . '<td>' . AdminLayout::escape($this->adminUsersLabel($collective ?? [])) . '</td></tr>'
    }

    private function plans(): array
    {
        if (!$this->pdo || !$this->tableExists('plans')) {
            return [];
        }
        $columns = $this->planColumns();
        $select = 'id, slug, name, monthly_price_cents, custom_domain_included, is_active, created_at'
            . ($columns['description'] ? ', description' : ', NULL AS description')
            . ($columns['allowed_artworks'] ? ', allowed_artworks' : ', NULL AS allowed_artworks')
            . ($columns['allowed_email_addresses'] ? ', allowed_email_addresses' : ', NULL AS allowed_email_addresses')
            . ($columns['display_order'] ? ', display_order' : ', 100 AS display_order')
            . ($columns['allow_sales'] ? ', allow_sales' : ', 0 AS allow_sales')
            . ($columns['credit_card_fee_basis_points'] ? ', credit_card_fee_basis_points' : ', 290 AS credit_card_fee_basis_points')
            . ($columns['credit_card_fixed_fee_cents'] ? ', credit_card_fixed_fee_cents' : ', 30 AS credit_card_fixed_fee_cents');
        return $this->pdo->query("SELECT {$select} FROM plans WHERE is_active = 1 ORDER BY display_order ASC, monthly_price_cents ASC, id ASC")->fetchAll(PDO::FETCH_ASSOC);
    }

    private function fallbackCards(): string
    {
        return '<article class="pricing-card"><p class="eyebrow">Starter</p><h2>Free</h2><p class="price">$0</p><p>For evaluation, students, and artists publishing a compact first portfolio.</p><ul><li>ArtsFolio subdomain</li><li>Core portfolio pages</li><li>Basic contact form</li><li>Includes ArtsFolio notification/link on free tenant pages</li></ul><a class="button secondary" href="/signup">Start Free</a></article>';
    }

    private function priceLabel(int $cents): string
    {
        return $cents === 0 ? '$0' : '$' . number_format($cents / 100, 2) . ' / month';
    }

    private function limitLabel(mixed $limit, string $label): string
    {
        $value = (int) ($limit ?? 0);
        return $value > 0 ? number_format($value) . ' ' . $label : 'Plan-configured ' . $label;
    }

    private function cardFeesLabel(array $plan): string
    {
        $basisPoints = max(0, min(10000, (int) ($plan['credit_card_fee_basis_points'] ?? 290)));
        $fixedCents = max(0, (int) ($plan['credit_card_fixed_fee_cents'] ?? 30));
        return number_format($basisPoints / 100, 2) . '% + $' . number_format($fixedCents / 100, 2);
    }

    private function defaultCardFeesLabel(): string
    {
        foreach ($this->plans() as $plan) {
            if ((int) ($plan['allow_sales'] ?? 0) === 1) {
                return $this->cardFeesLabel($plan);
            }
        }
        return '2.90% + $0.30';
    }

    private function commissionPercent(): string
    {
        $basisPoints = max(0, min(10000, (int) ($this->settings?->get('platform_sales_commission_basis_points', '500') ?? '500')));
        return number_format($basisPoints / 100, 2) . '%';
    }

    private function planColumns(): array
    {
        $columns = ['description' => false, 'allowed_artworks' => false, 'allowed_email_addresses' => false, 'display_order' => false, 'allow_sales' => false, 'credit_card_fee_basis_points' => false, 'credit_card_fixed_fee_cents' => false];
        if (!$this->pdo) {
            return $columns;
        }
        $stmt = $this->pdo->prepare('SELECT column_name FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = :table');
        $stmt->execute(['table' => 'plans']);
        foreach ($stmt->fetchAll(PDO::FETCH_COLUMN) as $column) {
            if (array_key_exists((string) $column, $columns)) {
                $columns[(string) $column] = true;
            }
        }
        return $columns;
    }

    private function tableExists(string $table): bool
    {
        if (!$this->pdo) {
            return false;
        }
        $stmt = $this->pdo->prepare('SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = :table');
        $stmt->execute(['table' => $table]);
        return (int) $stmt->fetchColumn() > 0;
    }

    private function layout(string $title, string $body): string
    {
        $platformAdminLink = \App\Http\View\PlatformChrome::platformAdminLink();
        $platformCopyright = \App\Http\View\PlatformChrome::copyrightLine();

        return <<<HTML
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>{$title}</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="ArtsFolio pricing for artist portfolio and sales platform plans.">
    <link rel="stylesheet" href="/assets/platform.css">
    <link rel="stylesheet" href="/assets/platform-custom.css">
    <link rel="stylesheet" href="/assets/tenant-admin.css">
</head>
<body>
<header class="platform-header"><a class="platform-brand logo-brand compact-logo" href="/"><img src="/assets/logo_2.png" alt="ArtsFolio"></a><nav><a class="active" href="/pricing">Pricing</a><a href="/directory">Artists</a><a href="/help">Help</a>{$platformAdminLink}<a href="/login">Sign in</a></nav></header>
<main>{$body}</main>
<footer class="platform-footer"><span>{$platformCopyright}</span><nav><a href="/help">Help</a><a href="/privacy">Privacy</a><a href="/contact">Contact</a></nav></footer>
</body>
</html>
HTML;
    }
    /**
     * Format plan admin-user limits for pricing display.
     */
    private function formatAdminUsers(mixed $value): string
    {
        if ($value === null || $value === '' || (int) $value < 0) {
            return 'Unlimited admin users';
        }

        $count = (int) $value;
        return $count === 1 ? '1 admin user' : $count . ' admin users';
    }
    private function adminUsersLabel(array $plan): string
    {
        $value = $plan['admin_user_limit'] ?? $plan['admin_users'] ?? null;
        if ($value === null || $value === '' || (int) $value < 0) {
            return 'Unlimited admin users';
        }

        $count = (int) $value;
        return $count === 1 ? '1 admin user' : $count . ' admin users';
    }
}
