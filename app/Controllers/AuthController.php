<?php
/**
 * Admin authentication controller.
 */

declare(strict_types=1);

namespace App\Controllers;

use App\Core\View;
use PDO;

final class AuthController
{
    private PDO $db;

    public function __construct(private array $container, private array $tenant)
    {
        $this->db = $container['db'];
    }

    public function login(string $method): void
    {
        $error = null;
        if ($method === 'POST') {
            $stmt = $this->db->prepare('SELECT * FROM users WHERE email = :email AND tenant_id = :tenant_id LIMIT 1');
            $stmt->execute(['email' => $_POST['email'] ?? '', 'tenant_id' => $this->tenant['id']]);
            $user = $stmt->fetch();
            if ($user && password_verify($_POST['password'] ?? '', $user['password_hash'])) {
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['tenant_id'] = $this->tenant['id'];
                header('Location: /admin');
                return;
            }
            $error = 'Invalid login.';
        }
        View::render('admin/login', ['tenant' => $this->tenant, 'error' => $error]);
    }

    public function logout(): void
    {
        session_destroy();
        header('Location: /admin/login');
    }

    public function requireLogin(): void
    {
        if (($_SESSION['tenant_id'] ?? null) !== $this->tenant['id'] || empty($_SESSION['user_id'])) {
            header('Location: /admin/login');
            exit;
        }
    }
}

// End of file.
