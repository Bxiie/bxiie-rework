<?php

declare(strict_types=1);

namespace App\Http\Controllers\Tenant;

use App\Http\Request;
use App\Http\Response;
use App\Http\View\ErrorPage;
use App\Http\View\TenantAdminLayout;
use App\Platform\Membership\MembershipRepository;
use App\Platform\Tenancy\TenantContext;
use App\Support\Security\CsrfTokenService;
use App\Tenant\Curation\CurationRepository;
use App\Tenant\Settings\TenantSettingsRepository;

/** Handles public submissions, editor queues, and tenant user messages. */
final class CurationController
{
    public function __construct(private readonly CurationRepository $curation,private readonly MembershipRepository $memberships,private readonly CsrfTokenService $csrf,private readonly TenantSettingsRepository $settings) {}

    public function add(Request $request,TenantContext $tenant,?array $user): Response
    {
        if(!$user) return new Response('',303,['Location'=>'/login?notice=curation-login-required']);
        if(!$this->curation->workflowEnabled($tenant->tenantId)) return Response::html('<h1>Curation workflow is not included in this plan.</h1>',403);
        if(!$this->csrf->validate((string)($_POST['csrf_token']??''))) return Response::invalidCsrf();
        $this->curation->add($tenant->tenantId,(int)($_POST['list_id']??$this->curation->centralListId($tenant->tenantId)),(int)($_POST['artwork_id']??0),(int)$user['user_id'],trim((string)($_POST['note']??'')));
        return new Response('',303,['Location'=>(string)($_POST['return_to']??'/portfolio').'?notice=curation-added']);
    }

    public function queue(Request $request,TenantContext $tenant,?array $user): Response
    {
        if(!$this->editor($tenant,$user)) return Response::html(ErrorPage::unauthorized('/login','Editor access required.'),403);
        $roles=$this->memberships->tenantRolesForUser($tenant->tenantId,(int)$user['user_id']);
        $all=(bool)array_intersect($roles,['owner','tenant_owner','admin','tenant_admin']);
        $rows=''; $token=$this->e($this->csrf->getOrCreate());
        foreach($this->curation->queue($tenant->tenantId,(int)$user['user_id'],$all) as $item){$id=(int)$item['id'];$title=$this->e($item['title']);$note=$this->e((string)$item['note']);$who=$this->e((string)($item['submitter_name']?:$item['submitter_email']));$status=$this->e($item['artwork_status']);$rows.="<article class=\"admin-panel\"><h2>{$title}</h2><p><strong>{$who}</strong> · artwork {$status}</p><blockquote>{$note}</blockquote><form method=\"post\" action=\"/admin/curation/review\"><input type=\"hidden\" name=\"csrf_token\" value=\"{$token}\"><input type=\"hidden\" name=\"item_id\" value=\"{$id}\"><label>Reply<br><textarea name=\"reply\" rows=\"4\"></textarea></label><button name=\"decision\" value=\"published\">Publish and reply</button> <button name=\"decision\" value=\"reviewing\">Keep reviewing</button> <button name=\"decision\" value=\"declined\">Decline</button></form></article>";}
        if($rows==='')$rows='<p>No artwork is waiting for review.</p>';
        return Response::html((new TenantAdminLayout($this->settings))->render($tenant,'Curation',$rows,'curation'));
    }

    public function review(Request $request,TenantContext $tenant,?array $user): Response
    {
        if(!$this->editor($tenant,$user)) return Response::html(ErrorPage::unauthorized('/login','Editor access required.'),403);
        if(!$this->csrf->validate((string)($_POST['csrf_token']??''))) return Response::invalidCsrf();
        $this->curation->review($tenant->tenantId,(int)($_POST['item_id']??0),(int)$user['user_id'],(string)($_POST['decision']??''),(string)($_POST['reply']??''));
        return new Response('',303,['Location'=>'/admin/curation']);
    }

    public function messages(Request $request,TenantContext $tenant,?array $user): Response
    {
        if(!$user) return new Response('',303,['Location'=>'/login']);
        if(isset($_GET['read']))$this->curation->markRead($tenant->tenantId,(int)$user['user_id'],(int)$_GET['read']);
        $body='<h1>Messages</h1>';
        foreach($this->curation->messages($tenant->tenantId,(int)$user['user_id']) as $m){$body.='<article class="card"><h2>'.$this->e($m['subject']).'</h2><p>'.$this->e($m['body']).'</p><small>'.$this->e($m['created_at']).'</small>'.(!$m['read_at']?' <a href="/messages?read='.(int)$m['id'].'">Mark read</a>':'').'</article>';}
        if($body==='<h1>Messages</h1>')$body.='<p>No messages yet.</p>';
        return Response::html('<!doctype html><html><head><meta charset="utf-8"><title>Messages</title><link rel="stylesheet" href="/assets/site.css"></head><body><main class="site-main">'.$body.'</main></body></html>');
    }

    public function form(int $tenantId,int $artworkId,string $returnTo,?array $user): string
    {
        if(!$user||!$this->curation->workflowEnabled($tenantId))return '';
        $options='';foreach($this->curation->editorLists($tenantId) as $l)$options.='<option value="'.(int)$l['id'].'">'.$this->e($l['name']).'</option>';
        $token=$this->e($this->csrf->getOrCreate());
        return '<form class="curation-card-form" method="post" action="/curation/add"><input type="hidden" name="csrf_token" value="'.$token.'"><input type="hidden" name="artwork_id" value="'.$artworkId.'"><input type="hidden" name="return_to" value="'.$this->e($returnTo).'"><label>Add to <select name="list_id">'.$options.'</select></label><textarea name="note" rows="2" placeholder="Curation note"></textarea><button type="submit">Add to curation</button></form>';
    }

    private function editor(TenantContext $tenant,?array $user): bool {if(!$user)return false;return (bool)array_intersect($this->memberships->tenantRolesForUser($tenant->tenantId,(int)$user['user_id']),['owner','tenant_owner','admin','tenant_admin','editor']);}
    private function e(mixed $v): string{return htmlspecialchars((string)$v,ENT_QUOTES,'UTF-8');}
}

// End of file.
