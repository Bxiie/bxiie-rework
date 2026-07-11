#!/usr/bin/php
<?php
declare(strict_types=1);
$root=dirname(__DIR__,2); $path=$root.'/app/Http/Controllers/Platform/Admin/EmailTemplatesController.php';
if(!is_file($path)){fwrite(STDERR,"[FAIL] Missing controller\n");exit(1);} $body=(string)file_get_contents($path);
$required=["'free_access_months' =>","'signup_code' =>","'signup_url' =>","'{{RECIPIENT_EMAIL}}' =>","'{{FREE_ACCESS_MONTHS}}' =>","'{{SIGNUP_CODE}}' =>","'{{SIGNUP_URL}}' =>",'<th>Form</th>'];
foreach($required as $m){if(!str_contains($body,$m)){fwrite(STDERR,"[FAIL] Missing marker: {$m}\n");exit(1);}}
echo "[PASS] All uppercase signup-invite placeholders are cataloged.\n";
// End of file.
