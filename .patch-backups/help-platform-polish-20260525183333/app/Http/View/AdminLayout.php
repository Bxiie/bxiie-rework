<?php

declare(strict_types=1);

namespace App\Http\View;

/**
 * Platform-admin shell branded as ArtsFolio, not as a tenant site.
 */
final class AdminLayout
{
    public static function render(...$args): string
    {
        $title = array_key_exists('title', $args) ? (string) $args['title'] : (string) ($args[0] ?? 'Platform Admin');
        $body = array_key_exists('body', $args) ? (string) ($args['body'] ?? '') : (string) ($args[1] ?? '');
        $active = array_key_exists('active', $args) ? (string) $args['active'] : self::activeFromTitle($title);
        return self::renderShell($title, $body, $active);
    }

    public static function renderShell(string $title, string $body, string $active = 'dashboard'): string
    {
        $safeTitle = self::escape($title);
        $nav = self::adminNav($active);
        $csrf = self::escape($_SESSION['csrf_token'] ?? '');
        return <<<HTML
<!doctype html><html lang="en"><head><meta charset="utf-8"><title>{$safeTitle} | ArtsFolio Platform Admin</title><meta name="viewport" content="width=device-width, initial-scale=1"><link rel="stylesheet" href="/assets/site.css"><link rel="stylesheet" href="/assets/tenant-admin.css?v=20260630-content-colors-bg-image-picker-layout"></head>
<body class="tenant-admin-page platform-admin-page"><header class="site-header tenant-admin-public-header"><a class="brand" href="/admin"><img src="/assets/logo_2.png" alt="ArtsFolio" style="height:38px;width:auto"></a><nav><a href="/">Public site</a><a href="/help">Help</a><form method="post" action="/logout" style="display:inline"><input type="hidden" name="csrf_token" value="{$csrf}"><button type="submit" class="link-button">Log out</button></form></nav></header>
<div class="tenant-admin-shell"><aside class="tenant-admin-sidebar"><div class="tenant-admin-sidebar-title"><strong>Platform Admin</strong><span>ArtsFolio</span></div>{$nav}</aside><main class="tenant-admin-main"><section class="tenant-admin-panel"><h1>{$safeTitle}</h1>{$body}</section></main></div>
<footer class="site-footer tenant-admin-footer"><span>ArtsFolio platform administration</span><nav><a href="/admin/routes">Routes</a><a href="/admin/platform-settings">Settings</a><a href="/help">Help</a></nav></footer></body></html>
HTML;
    }

    public static function escape(string $value): string { return htmlspecialchars($value, ENT_QUOTES, 'UTF-8'); }

    private static function adminNav(string $active): string
    {
        $items = [
            'dashboard'=>['/admin','Dashboard'], 'tenants'=>['/admin/tenants','Tenants'], 'domains'=>['/admin/domains','Domains'],
            'pricing'=>['/pricing','Pricing'], 'stats'=>['/admin/stats','Stats'], 'jobs'=>['/admin/jobs','Jobs'], 'workers'=>['/admin/workers','Workers'],
            'email'=>['/admin/email-outbox','Email Outbox'], 'audit'=>['/admin/audit-log','Audit Log'], 'settings'=>['/admin/platform-settings','Settings'], 'routes'=>['/admin/routes','Routes'],
        ];
        $html='<nav>'; foreach($items as $key=>$item){[$href,$label]=$item; $class=$active===$key?' class="active"':''; $html.='<a'.$class.' href="'.self::escape($href).'">'.self::escape($label).'</a>'; } return $html.'</nav>';
    }

    private static function activeFromTitle(string $title): string
    {
        $t=strtolower($title); foreach(['tenants','domains','pricing','stats','jobs','workers','email','audit','settings','routes'] as $key){ if(str_contains($t,$key)) return $key; } return 'dashboard';
    }
}

// End of file.
