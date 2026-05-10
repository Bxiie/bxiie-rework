<?php
/**
 * Tenant-scoped admin controller.
 */

declare(strict_types=1);

namespace App\Controllers;

use App\Core\View;
use App\Services\ImageService;
use PDO;

final class AdminController
{
    private PDO $db;

    public function __construct(private array $container, private array $tenant)
    {
        $this->db = $container['db'];
        $this->touchCopyrightYear();
    }

    public function dashboard(): void
    {
        $counts = $this->counts();
        View::render('admin/dashboard', ['tenant' => $this->tenant, 'counts' => $counts]);
    }

    public function site(string $method): void
    {
        if ($method === 'POST') {
            foreach ($_POST as $key => $value) {
                $stmt = $this->db->prepare('INSERT INTO settings (tenant_id, setting_key, setting_value) VALUES (:tenant_id, :key, :value) ON CONFLICT(tenant_id, setting_key) DO UPDATE SET setting_value = excluded.setting_value');
                $stmt->execute(['tenant_id' => $this->tenant['id'], 'key' => $key, 'value' => $value]);
            }
            header('Location: /admin/site');
            return;
        }
        View::render('admin/site', ['tenant' => $this->tenant, 'settings' => $this->settings()]);
    }

    public function images(string $method): void
    {
        $notice = null;
        if ($method === 'POST' && isset($_FILES['image'])) {
            $service = new ImageService($this->container['config']);
            $result = $service->processUpload($_FILES['image'], (int) $this->tenant['id'], !empty($_POST['watermark']));
            $stmt = $this->db->prepare('INSERT INTO images (tenant_id, title, description, storage_key, original_path, mime_type, width, height, is_public, is_draft, featured_home, featured_rotator, watermarked, created_at) VALUES (:tenant_id, :title, :description, :storage_key, :original_path, :mime_type, :width, :height, :is_public, :is_draft, :featured_home, :featured_rotator, :watermarked, datetime("now"))');
            $stmt->execute(array_merge($result, [
                'tenant_id' => $this->tenant['id'],
                'title' => $_POST['title'] ?? 'Untitled',
                'description' => $_POST['description'] ?? '',
                'is_public' => empty($_POST['is_draft']) ? 1 : 0,
                'is_draft' => !empty($_POST['is_draft']) ? 1 : 0,
                'featured_home' => !empty($_POST['featured_home']) ? 1 : 0,
                'featured_rotator' => !empty($_POST['featured_rotator']) ? 1 : 0,
            ]));
            $notice = 'Image uploaded and derivatives created.';
        }
        $stmt = $this->db->prepare('SELECT * FROM images WHERE tenant_id = :tenant_id ORDER BY created_at DESC');
        $stmt->execute(['tenant_id' => $this->tenant['id']]);
        View::render('admin/images', ['tenant' => $this->tenant, 'images' => $stmt->fetchAll(), 'notice' => $notice]);
    }

    public function portfolio(string $method): void
    {
        if ($method === 'POST') {
            $slug = strtolower(trim(preg_replace('/[^a-z0-9]+/', '-', $_POST['name'] ?? ''), '-'));
            $stmt = $this->db->prepare('INSERT INTO portfolio_sections (tenant_id, name, slug, description, sort_order) VALUES (:tenant_id, :name, :slug, :description, :sort_order)');
            $stmt->execute(['tenant_id' => $this->tenant['id'], 'name' => $_POST['name'] ?? '', 'slug' => $slug, 'description' => $_POST['description'] ?? '', 'sort_order' => (int) ($_POST['sort_order'] ?? 100)]);
            header('Location: /admin/portfolio');
            return;
        }
        $stmt = $this->db->prepare('SELECT * FROM portfolio_sections WHERE tenant_id = :tenant_id ORDER BY sort_order, name');
        $stmt->execute(['tenant_id' => $this->tenant['id']]);
        View::render('admin/portfolio', ['tenant' => $this->tenant, 'sections' => $stmt->fetchAll()]);
    }

    public function events(string $method): void
    {
        if ($method === 'POST') {
            $stmt = $this->db->prepare('INSERT INTO exhibitions (tenant_id, title, venue, city, event_date, url, description, is_recent) VALUES (:tenant_id, :title, :venue, :city, :event_date, :url, :description, :is_recent)');
            $stmt->execute(['tenant_id' => $this->tenant['id'], 'title' => $_POST['title'] ?? '', 'venue' => $_POST['venue'] ?? '', 'city' => $_POST['city'] ?? '', 'event_date' => $_POST['event_date'] ?? '', 'url' => $_POST['url'] ?? '', 'description' => $_POST['description'] ?? '', 'is_recent' => !empty($_POST['is_recent']) ? 1 : 0]);
            header('Location: /admin/events');
            return;
        }
        $stmt = $this->db->prepare('SELECT * FROM exhibitions WHERE tenant_id = :tenant_id ORDER BY event_date DESC');
        $stmt->execute(['tenant_id' => $this->tenant['id']]);
        View::render('admin/events', ['tenant' => $this->tenant, 'events' => $stmt->fetchAll()]);
    }

    public function content(string $method): void
    {
        if ($method === 'POST') {
            $fields = ['about_content', 'contact_details', 'facebook_url', 'instagram_url', 'linkedin_url'];
            foreach ($fields as $field) {
                $stmt = $this->db->prepare('INSERT INTO settings (tenant_id, setting_key, setting_value) VALUES (:tenant_id, :key, :value) ON CONFLICT(tenant_id, setting_key) DO UPDATE SET setting_value = excluded.setting_value');
                $stmt->execute(['tenant_id' => $this->tenant['id'], 'key' => $field, 'value' => $_POST[$field] ?? '']);
            }
            header('Location: /admin/content');
            return;
        }
        View::render('admin/content', ['tenant' => $this->tenant, 'settings' => $this->settings()]);
    }

    public function users(string $method): void
    {
        if ($method === 'POST') {
            $stmt = $this->db->prepare('INSERT INTO users (tenant_id, name, email, role, password_hash, created_at) VALUES (:tenant_id, :name, :email, :role, :password_hash, datetime("now"))');
            $stmt->execute(['tenant_id' => $this->tenant['id'], 'name' => $_POST['name'] ?? '', 'email' => $_POST['email'] ?? '', 'role' => $_POST['role'] ?? 'viewer', 'password_hash' => password_hash($_POST['password'] ?? bin2hex(random_bytes(8)), PASSWORD_DEFAULT)]);
            header('Location: /admin/users');
            return;
        }
        $stmt = $this->db->prepare('SELECT id, name, email, role, created_at FROM users WHERE tenant_id = :tenant_id ORDER BY created_at DESC');
        $stmt->execute(['tenant_id' => $this->tenant['id']]);
        View::render('admin/users', ['tenant' => $this->tenant, 'users' => $stmt->fetchAll()]);
    }

    public function stats(): void
    {
        $from = $_GET['from'] ?? date('Y-m-d', strtotime('-30 days'));
        $to = $_GET['to'] ?? date('Y-m-d');
        $stmt = $this->db->prepare('SELECT event_type, path, country_code, COUNT(*) AS hits FROM page_views WHERE tenant_id = :tenant_id AND date(created_at) BETWEEN :from AND :to GROUP BY event_type, path, country_code ORDER BY hits DESC LIMIT 250');
        $stmt->execute(['tenant_id' => $this->tenant['id'], 'from' => $from, 'to' => $to]);
        View::render('admin/stats', ['tenant' => $this->tenant, 'rows' => $stmt->fetchAll(), 'from' => $from, 'to' => $to]);
    }

    public function notFound(): void
    {
        http_response_code(404);
        echo 'Admin page not found.';
    }

    private function settings(): array
    {
        $stmt = $this->db->prepare('SELECT setting_key, setting_value FROM settings WHERE tenant_id = :tenant_id');
        $stmt->execute(['tenant_id' => $this->tenant['id']]);
        return array_column($stmt->fetchAll(), 'setting_value', 'setting_key');
    }

    private function counts(): array
    {
        $counts = [];
        foreach (['images', 'subscribers', 'contact_messages', 'page_views'] as $table) {
            $stmt = $this->db->prepare("SELECT COUNT(*) AS c FROM {$table} WHERE tenant_id = :tenant_id");
            $stmt->execute(['tenant_id' => $this->tenant['id']]);
            $counts[$table] = (int) $stmt->fetch()['c'];
        }
        return $counts;
    }

    private function touchCopyrightYear(): void
    {
        $year = date('Y');
        $stmt = $this->db->prepare('INSERT INTO settings (tenant_id, setting_key, setting_value) VALUES (:tenant_id, "copyright_year", :year) ON CONFLICT(tenant_id, setting_key) DO UPDATE SET setting_value = excluded.setting_value');
        $stmt->execute(['tenant_id' => $this->tenant['id'], 'year' => $year]);
    }
}

// End of file.
