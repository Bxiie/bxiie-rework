#!/usr/bin/php
<?php

/**
 * Regression checks for automatic one-time signup-code invitations.
 */

declare(strict_types=1);

$root = dirname(__DIR__, 2);
$controllerPath = $root . '/app/Http/Controllers/Platform/Admin/SignupCodesController.php';
if (!is_file($controllerPath)) {
    fwrite(STDERR, "[FAIL] Missing SignupCodesController.php\n");
    exit(1);
}

$controller = (string) file_get_contents($controllerPath);
$checks = [
    'checked automatic-send control exists' => str_contains($controller, 'name="send_invite" value="1" checked'),
    'checkbox explicitly describes one-time behavior' => str_contains($controller, 'Used only when creating a one-time code.'),
    'automatic sending is limited to one-time codes' => str_contains($controller, '$sendInvite = $kind === \'one_time\''),
    'recipient is validated before creation' => strpos($controller, 'if ($sendInvite && !filter_var($recipientEmail, FILTER_VALIDATE_EMAIL))') < strpos($controller, '$code = $this->codes->create('),
    'automatic invite receives created code row' => str_contains($controller, '$this->queueInvite($recipientEmail, $code);'),
    'automatic invite is audited' => str_contains($controller, "'automatic' => true"),
    'combined success notice exists' => str_contains($controller, "'created-and-invite-queued' => 'Signup code created and invite email queued.'"),
];

foreach ($checks as $label => $passed) {
    if (!$passed) {
        fwrite(STDERR, "[FAIL] {$label}\n");
        exit(1);
    }
}

echo "[PASS] Signup-code automatic invite static check passed.\n";

// End of file.
