<?php
declare(strict_types=1);
$checks=[['app/Http/Controllers/Platform/HelpController.php','Qj_PFrHNWvQ'],['app/Http/Controllers/Platform/HelpController.php','Z4XHLpUxCFk'],['app/Http/Controllers/Platform/HelpController.php','xFSaqWzGWYI'],['app/Http/Controllers/Platform/HelpController.php','3cPqM9qbe34'],['app/Http/View/TenantAdminLayout.php','tenant-admin-menu-toggle'],['public/assets/tenant-admin.css','tenant-admin-menu-open'],['app/Http/Controllers/Platform/Admin/EmailOutboxController.php','Status / timestamp'],['app/Http/Controllers/Platform/Admin/EmailOutboxController.php','sent_at'],['app/Http/Controllers/Platform/MarketingController.php','thumbnail_media_uuid'],['app/Http/Controllers/Platform/MarketingController.php','directory-card-thumb']];
$f=[];foreach($checks as [$file,$needle]){$source=@file_get_contents($file);if($source===false||!str_contains($source,$needle))$f[]="$file missing: $needle";}if($f){fwrite(STDERR,"[FAIL] Platform polish checks failed:
 - ".implode("
 - ",$f)."
");exit(1);}echo "[PASS] Help videos, mobile admin menu, outbox timestamps, and directory thumbnails are wired.
";
