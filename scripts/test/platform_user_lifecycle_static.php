<?php

declare(strict_types=1);

/**
 * Static regression checks for platform-admin user lifecycle actions.
 *
 * Suspend/delete actions must read and display the real users.status value,
 * hide soft-deleted users from the default list, and avoid raw unbranded
 * validation pages when a lifecycle request is invalid.
 */

$root = dirname(__DIR__, 2);
$repositoryPath = $root . '/app/Platform/Identity/AdminUserRepository.php';
$controllerPath = $root . '/app/Http/Controllers/Platform/Admin/UsersController.php';

$repository = file_get_contents($repositoryPath) ?: '';
$controller = file_get_contents($controllerPath) ?: '';

$checks = [
    'Platform users read real status' => [$repository, "COALESCE(u.status, 'active') AS user_status"],
    'Platform users default list hides deleted users' => [$repository, "WHERE COALESCE(u.status, 'active') <> 'deleted'"],
    'Platform users include suspended timestamp' => [$repository, 'u.suspended_at'],
    'Platform users include deleted timestamp' => [$repository, 'u.deleted_at'],
    'Suspend helper revokes sessions through status setter' => [$repository, "setUserStatus(\$userId, 'suspended')"],
    'Delete helper revokes sessions through status setter' => [$repository, "setUserStatus(\$userId, 'deleted')"],
    'Status action validates platform user' => [$controller, '!$this->users->userIsPlatformUser($userId)'],
    'Invalid status request is branded' => [$controller, 'return Response::error(422, \'Invalid user status request\''],
    'Invalid lifecycle request is branded' => [$controller, 'return Response::error(422, \'Invalid user lifecycle request\''],
    'Self lifecycle action is disabled in UI' => [$controller, 'Current user lifecycle actions are disabled.'],
    'Actions header matches single actions cell' => [$controller, '<th>Actions</th>'],
];


$platformUsersStart = strpos($repository, 'public function platformUsers');
$platformUsersEnd = strpos($repository, 'public function updatePasswordHash');
$platformUsersBlock = $platformUsersStart !== false && $platformUsersEnd !== false
    ? substr($repository, $platformUsersStart, $platformUsersEnd - $platformUsersStart)
    : '';

if ($platformUsersBlock === '' || str_contains($platformUsersBlock, "'active' AS user_status")) {
    fwrite(STDERR, "Failed platform user lifecycle static check: Platform users no longer hardcode active status\n");
    exit(1);
}

foreach ($checks as $label => $check) {
    $contents = $check[0];
    $needle = $check[1];
    $expected = $check[2] ?? true;
    $found = str_contains($contents, $needle);

    if ($found !== $expected) {
        fwrite(STDERR, "Failed platform user lifecycle static check: {$label}\n");
        exit(1);
    }
}

echo "Platform user lifecycle static checks passed.\n";

// End of file.
