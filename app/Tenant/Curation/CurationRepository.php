<?php

declare(strict_types=1);

namespace App\Tenant\Curation;

use PDO;

/** Persists tenant-scoped curation queues and user messages. */
final class CurationRepository
{
    public function __construct(private readonly PDO $pdo) {}

    public function workflowEnabled(int $tenantId): bool
    {
        $stmt = $this->pdo->prepare("SELECT COALESCE(p.curation_workflow_included, p.monthly_price_cents > 0) FROM tenant_plan_assignments tpa JOIN plans p ON p.id=tpa.plan_id WHERE tpa.tenant_id=:tenant_id AND tpa.status IN ('trial','active','manual') ORDER BY tpa.id DESC LIMIT 1");
        $stmt->execute(['tenant_id'=>$tenantId]);
        return (bool)$stmt->fetchColumn();
    }

    public function centralListId(int $tenantId): int
    {
        $stmt=$this->pdo->prepare("SELECT id FROM curation_lists WHERE tenant_id=:tenant_id AND is_central=1 AND editor_user_id IS NULL LIMIT 1");
        $stmt->execute(['tenant_id'=>$tenantId]);
        $id=$stmt->fetchColumn();
        if ($id) return (int)$id;
        $ins=$this->pdo->prepare("INSERT INTO curation_lists (tenant_id,editor_user_id,name,is_central) VALUES (:tenant_id,NULL,'Central curation queue',1)");
        $ins->execute(['tenant_id'=>$tenantId]);
        return (int)$this->pdo->lastInsertId();
    }

    public function editorLists(int $tenantId): array
    {
        $stmt=$this->pdo->prepare("SELECT cl.id,cl.name,cl.editor_user_id,u.display_name,u.email FROM curation_lists cl LEFT JOIN users u ON u.id=cl.editor_user_id WHERE cl.tenant_id=:tenant_id ORDER BY cl.is_central DESC,cl.name");
        $stmt->execute(['tenant_id'=>$tenantId]);
        $rows=$stmt->fetchAll();
        if (!$rows) { $this->centralListId($tenantId); return $this->editorLists($tenantId); }
        return $rows;
    }

    public function add(int $tenantId,int $listId,int $artworkId,int $userId,string $note): void
    {
        $check=$this->pdo->prepare("SELECT 1 FROM curation_lists WHERE id=:list_id AND tenant_id=:tenant_id");
        $check->execute(['list_id'=>$listId,'tenant_id'=>$tenantId]);
        if (!$check->fetchColumn()) throw new \InvalidArgumentException('Invalid curation list.');
        $stmt=$this->pdo->prepare("INSERT INTO curation_items (tenant_id,list_id,artwork_id,submitted_by_user_id,note,status) SELECT :tenant_id,:list_id,a.id,:user_id,:note,'queued' FROM artworks a WHERE a.id=:artwork_id AND a.tenant_id=:tenant_id");
        $stmt->execute(['tenant_id'=>$tenantId,'list_id'=>$listId,'artwork_id'=>$artworkId,'user_id'=>$userId,'note'=>$note]);
        if ($stmt->rowCount()!==1) throw new \InvalidArgumentException('Artwork is not available to this tenant.');
    }

    public function queue(int $tenantId,int $editorUserId,bool $allCentral): array
    {
        $extra=$allCentral ? "cl.is_central=1" : "(cl.is_central=1 OR cl.editor_user_id=:editor_id)";
        $stmt=$this->pdo->prepare("SELECT ci.id,ci.note,ci.status,ci.created_at,a.id artwork_id,a.title,a.slug,a.status artwork_status,u.display_name submitter_name,u.email submitter_email,cl.name list_name FROM curation_items ci JOIN curation_lists cl ON cl.id=ci.list_id JOIN artworks a ON a.id=ci.artwork_id JOIN users u ON u.id=ci.submitted_by_user_id WHERE ci.tenant_id=:tenant_id AND ci.status IN ('queued','reviewing') AND {$extra} ORDER BY ci.created_at");
        $params=['tenant_id'=>$tenantId]; if(!$allCentral)$params['editor_id']=$editorUserId;
        $stmt->execute($params); return $stmt->fetchAll();
    }

    public function review(int $tenantId,int $itemId,int $editorUserId,string $decision,string $reply): void
    {
        if(!in_array($decision,['published','declined','reviewing'],true)) throw new \InvalidArgumentException('Invalid decision.');
        $this->pdo->beginTransaction();
        try {
            $stmt=$this->pdo->prepare("SELECT ci.submitted_by_user_id,ci.artwork_id,a.title FROM curation_items ci JOIN artworks a ON a.id=ci.artwork_id WHERE ci.id=:id AND ci.tenant_id=:tenant_id FOR UPDATE");
            $stmt->execute(['id'=>$itemId,'tenant_id'=>$tenantId]); $item=$stmt->fetch();
            if(!$item) throw new \InvalidArgumentException('Curation item not found.');
            if($decision==='published') { $p=$this->pdo->prepare("UPDATE artworks SET status='published',published_at=COALESCE(published_at,CURRENT_TIMESTAMP),updated_at=CURRENT_TIMESTAMP WHERE id=:artwork_id AND tenant_id=:tenant_id"); $p->execute(['artwork_id'=>$item['artwork_id'],'tenant_id'=>$tenantId]); }
            $u=$this->pdo->prepare("UPDATE curation_items SET status=:status,reviewed_by_user_id=:editor,reviewed_at=CURRENT_TIMESTAMP WHERE id=:id AND tenant_id=:tenant_id");
            $u->execute(['status'=>$decision,'editor'=>$editorUserId,'id'=>$itemId,'tenant_id'=>$tenantId]);
            if(trim($reply)!=='') { $m=$this->pdo->prepare("INSERT INTO user_messages (tenant_id,recipient_user_id,sender_user_id,curation_item_id,subject,body) VALUES (:tenant_id,:recipient,:sender,:item,:subject,:body)"); $m->execute(['tenant_id'=>$tenantId,'recipient'=>$item['submitted_by_user_id'],'sender'=>$editorUserId,'item'=>$itemId,'subject'=>'Curation reply: '.$item['title'],'body'=>trim($reply)]); }
            $this->pdo->commit();
        } catch(\Throwable $e) { $this->pdo->rollBack(); throw $e; }
    }

    public function messages(int $tenantId,int $userId): array
    {
        $stmt=$this->pdo->prepare("SELECT um.id,um.subject,um.body,um.read_at,um.created_at,u.display_name sender_name,u.email sender_email FROM user_messages um LEFT JOIN users u ON u.id=um.sender_user_id WHERE um.tenant_id=:tenant_id AND um.recipient_user_id=:user_id ORDER BY um.created_at DESC");
        $stmt->execute(['tenant_id'=>$tenantId,'user_id'=>$userId]); return $stmt->fetchAll();
    }

    public function markRead(int $tenantId,int $userId,int $messageId): void
    {
        $stmt=$this->pdo->prepare("UPDATE user_messages SET read_at=COALESCE(read_at,CURRENT_TIMESTAMP) WHERE id=:id AND tenant_id=:tenant_id AND recipient_user_id=:user_id");
        $stmt->execute(['id'=>$messageId,'tenant_id'=>$tenantId,'user_id'=>$userId]);
    }
}

// End of file.
