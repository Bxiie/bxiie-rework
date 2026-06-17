<?php

declare(strict_types=1);

/**
 * Static checks for signup code invite sent/not-sent visibility.
 */

$repoPath = __DIR__ . '/../../app/Platform/Signup/SignupCodeRepository.php';
$controllerPath = __DIR__ . '/../../app/Http/Controllers/Platform/Admin/SignupCodesController.php';

$repo = file_get_contents($repoPath);
$controller = file_get_contents($controllerPath);

foreach ([$repoPath => $repo, $controllerPath => $controller] as $path => $contents) {
    if ($contents === false) {
        fwrite(STDERR, "Could not read {$path}\n");
        exit(1);
    }
}

$checks = [
    'Signup code list counts invite outbox rows' => [$repo, 'invite_email_count'],
    'Signup code list counts sent invite rows' => [$repo, 'invite_email_sent_count'],
    'Signup code list counts pending invite rows' => [$repo, 'invite_email_pending_count'],
    'Signup code list uses tenant invite template key' => [$repo, "platform.tenant_signup_invite"],
    'Signup code list matches outbox body by code' => [$repo, "eo.body_text LIKE CONCAT('%', c.code, '%')"],
    'Signup code table has invite status header' => [$controller, 'Invite status'],
    'Signup code table renders invite status cell' => [$controller, '{$inviteStatus}'],
    'Signup code invite helper shows not sent' => [$controller, 'Not sent'],
    'Signup code invite helper shows queued not sent yet' => [$controller, 'Queued, not sent yet'],
    'Signup code invite helper shows sent count' => [$controller, 'invite_email_sent_count'],
];

foreach ($checks as $label => [$contents, $needle]) {
    if (!str_contains($contents, $needle)) {
        fwrite(STDERR, "Failed signup code invite status static check: {$label}\n");
        exit(1);
    }
}

echo "Signup code invite status static checks passed.\n";

// End of file.
