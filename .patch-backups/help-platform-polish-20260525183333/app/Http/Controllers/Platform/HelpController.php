<?php

declare(strict_types=1);

namespace App\Http\Controllers\Platform;

use App\Http\Request;
use App\Http\Response;

/**
 * Linkable help center pages for every visible admin/help bullet.
 */
final class HelpController
{
    private const TOPICS = [
        'getting-started' => ['Getting started', 'Create an account, create a tenant, choose a subdomain, sign in, and open tenant admin.'],
        'branding' => ['Branding and CSS', 'Set site title, artist name, logo behavior, colors, top bar, page images, background, and editable tenant CSS.'],
        'content' => ['Public content', 'Edit home, about, contact, exhibition copy, formatted HTML blocks, and public labels.'],
        'artworks' => ['Artwork catalog', 'Upload images, edit metadata, assign sections, publish/archive works, and prepare sales fields.'],
        'portfolio-sections' => ['Portfolio sections', 'Create sections, order them, assign artworks, and optionally show sections as public tabs.'],
        'events' => ['Events and exhibitions', 'Create, filter, sort, order, publish, and archive exhibitions, fairs, residencies, and chronology entries.'],
        'messages' => ['Contact messages', 'Review contact submissions, mark status, export CSV, archive, and delete records.'],
        'email-signups' => ['Email signups', 'Review subscribers, import/export lists, manage consent, and delete bad records.'],
        'stats' => ['Stats', 'Read tenant and platform traffic, artwork views, location rollups, referrers, day-of-week graphs, and hour-of-day graphs.'],
        'audit-log' => ['Audit log', 'Review security and admin activity, filter by action/user/tenant, and export records for investigation.'],
        'discovery' => ['Discovery', 'Control whether a tenant appears in the public ArtsFolio directory and front-page artwork mosaic.'],
        'platform-admin' => ['Platform admin', 'Manage tenants, domains, pricing/settings, jobs, workers, email outbox, stats, routes, and audit records.'],
        'sessions' => ['Sessions and logout', 'Browser-session login expires when the browser session ends. Keep-me-logged-in uses the configured persistent period.'],
    ];

    public function index(Request $request): Response
    {
        $items = '';
        foreach (self::TOPICS as $slug => [$title, $summary]) {
            $items .= '<article><h3><a href="/help/' . $slug . '">' . self::e($title) . '</a></h3><p>' . self::e($summary) . '</p></article>';
        }
        return Response::html($this->page('Help', '<section class="platform-page-heading"><p class="eyebrow">Help center</p><h1>ArtsFolio help</h1><p>Each help topic opens into a walk-through page instead of being a dead bullet.</p></section><section class="platform-section"><div class="feature-grid">' . $items . '</div></section>'));
    }

    public function topic(Request $request, string $topic): Response
    {
        if (!isset(self::TOPICS[$topic])) { return Response::html($this->page('Help topic not found', '<h1>Help topic not found</h1><p><a href="/help">Back to help</a></p>'), 404); }
        [$title, $summary] = self::TOPICS[$topic];
        $body = '<section class="platform-page-heading"><p class="eyebrow">Help</p><h1>' . self::e($title) . '</h1><p>' . self::e($summary) . '</p></section>'
            . '<section class="platform-section docs-section"><h2>Walk-through</h2><ol class="flow-list compact">'
            . '<li><strong>Open the relevant admin page</strong><span>Use the sidebar link that matches this topic.</span></li>'
            . '<li><strong>Review the existing records and settings</strong><span>Use filters, search, and sort controls before changing data.</span></li>'
            . '<li><strong>Make one focused change</strong><span>Save, then confirm the public page or admin list reflects the update.</span></li>'
            . '<li><strong>Verify stats or audit when relevant</strong><span>Important admin actions should leave an audit trail.</span></li>'
            . '</ol><p><a class="button secondary" href="/help">Back to all help topics</a></p></section>';
        return Response::html($this->page($title, $body));
    }

    private function page(string $title, string $body): string
    {
        $safe = self::e($title);
        return '<!doctype html><html lang="en"><head><meta charset="utf-8"><title>' . $safe . ' | ArtsFolio Help</title><meta name="viewport" content="width=device-width, initial-scale=1"><link rel="stylesheet" href="/assets/site.css"></head><body><header class="platform-header"><a class="platform-brand" href="/"><img src="/assets/logo_2.png" alt="ArtsFolio"></a><nav><a href="/pricing">Pricing</a><a href="/signup">Sign up</a><a href="/login">Sign in</a></nav></header><main>' . $body . '</main></body></html>';
    }

    private static function e(string $v): string { return htmlspecialchars($v, ENT_QUOTES, 'UTF-8'); }
}

// End of file.
