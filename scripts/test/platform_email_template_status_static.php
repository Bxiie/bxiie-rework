#!/usr/bin/php
<?php

declare(strict_types=1);
$root=dirname(__DIR__,2);
$files=['catalog'=>$root.'/app/Platform/Email/EmailTemplateCatalog.php','outbox'=>$root.'/app/Platform/Email/EmailOutboxRepository.php','controller'=>$root.'/app/Http/Controllers/Platform/Admin/EmailTemplatesController.php','routes'=>$root.'/app/Http/Routes/platform.php'];
foreach($files as $k=>$p){if(!is_file($p)){fwrite(STDERR,"[FAIL] Missing {$k}\n");exit(1);} $files[$k]=(string)file_get_contents($p);}
$checks=[
'descriptions'=>str_contains($files['catalog'],'Sent when a user requests a password-reset link.'),
'settings key'=>str_contains($files['catalog'],'email_template.active.'),
'central gate'=>str_contains($files['outbox'],'EmailTemplateCatalog::isTemplateKeyActive'),
'active switch'=>str_contains($files['controller'],'name="active"'),
'suppressed copy'=>str_contains($files['controller'],'Suppressed templates are not added to the email outbox.'),
'audit'=>str_contains($files['controller'],'platform.email_template.status_updated'),
'route'=>str_contains($files['routes'], '$router->post(\'/platform/admin/email-templates/status\''),
];
foreach($checks as $l=>$ok){if(!$ok){fwrite(STDERR,"[FAIL] {$l}\n");exit(1);}}
echo "[PASS] Email template descriptions and suppression static check passed.\n";
// End of file.
