#!/usr/bin/php
<?php

declare(strict_types=1);
$root = dirname(__DIR__, 2);
$files = [
 'controller' => $root . '/app/Http/Controllers/Platform/Admin/EmailTemplatesController.php',
 'catalog' => $root . '/app/Platform/Email/EmailTemplateCatalog.php',
 'signup' => $root . '/app/Platform/Signup/TenantSignupService.php',
 'worker' => $root . '/scripts/workers/email_run_once.php',
 'scheduler' => $root . '/app/Platform/Email/RecurringLifecycleEmailScheduler.php',
 'routes' => $root . '/app/Http/Routes/platform.php',
];
foreach ($files as $name => $path) if (!is_file($path)) { fwrite(STDERR, "[FAIL] Missing {$name}: {$path}\n"); exit(1); }
$c = file_get_contents($files['controller']); $cat=file_get_contents($files['catalog']); $signup=file_get_contents($files['signup']); $worker=file_get_contents($files['worker']); $routes=file_get_contents($files['routes']);
$checks = [
 'minutes control' => str_contains($c, 'minutes_after_signup'),
 'recurring control' => str_contains($c, 'name="recurring"'),
 'create action' => str_contains($c, 'function create(') && str_contains($routes, '/email-templates/create'),
 'rename action' => str_contains($c, 'function rename(') && str_contains($routes, '/email-templates/rename'),
 'safe names' => str_contains($c, "preg_match('/^[A-Za-z0-9][A-Za-z0-9_-]{0,79}$/"),
 'schedule metadata' => str_contains($cat, 'scheduleMinutesKey') && str_contains($cat, 'scheduleRecurringKey'),
 'dynamic signup inventory' => str_contains($signup, "template/email/lifecycle/*.{txt,md,html}"),
 'recurrence after send' => str_contains($worker, 'RecurringLifecycleEmailScheduler'),
];
foreach ($checks as $label=>$ok) if (!$ok) { fwrite(STDERR, "[FAIL] {$label}\n"); exit(1); }
echo "[PASS] Email template signup schedule/create/rename static check passed.\n";
