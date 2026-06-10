<?php
/**
 * Admin authentication controller.
 */

declare(strict_types=1);

namespace App\Controllers;


use App\Http\View\AuthPage;
use App\Core\View;
use PDO;

final class AuthController
{
    private PDO $db;

    public function __construct(private array $container, private array $tenant)
    {
        $this->db = $container['db'];
    }

    public function login(string $method = 'GET'): void
    {
        if (strtoupper($method) === 'GET') {
            echo AuthPage::login('/login');
            return;
        }

        $this->authenticate();
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
