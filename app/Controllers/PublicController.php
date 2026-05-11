<?php
/**
 * Public artist website controller.
 */

declare(strict_types=1);

namespace App\Controllers;

use App\Core\View;
use App\Services\StatsTracker;
use PDO;

final class PublicController
{
    private PDO $db;
    private StatsTracker $stats;

    public function __construct(private array $container, private array $tenant)
    {
        $this->db = $container['db'];
        $this->stats = new StatsTracker($this->db);
    }

    public function home(): void
    {
        $this->stats->hit((int) $this->tenant['id'], 'page');
        $images = $this->images('featured_home = 1 AND is_public = 1');
        View::render('public/home', $this->base(['images' => $images]));
    }

    public function portfolio(string $path): void
    {
        $this->stats->hit((int) $this->tenant['id'], 'page');
        $sectionSlug = null;
        if (preg_match('#^/portfolio/([^/]+)#', $path, $m)) {
            $sectionSlug = $m[1];
        }
        $sections = $this->sections();
        $images = $sectionSlug
            ? $this->images('is_public = 1 AND id IN (SELECT image_id FROM image_sections WHERE section_id = (SELECT id FROM portfolio_sections WHERE tenant_id = :tenant_id AND slug = :section_slug))', ['section_slug' => $sectionSlug])
            : $this->images('is_public = 1');
        View::render('public/portfolio', $this->base(['sections' => $sections, 'images' => $images, 'sectionSlug' => $sectionSlug]));
    }

    public function about(): void
    {
        $this->stats->hit((int) $this->tenant['id'], 'page');
        View::render('public/about', $this->base([
            'events' => $this->events(),
            'aboutImage' => $this->imageFromSetting('about_image_id'),
        ]));
    }

    public function contact(string $method): void
    {
        $message = null;
        if ($method === 'POST') {
            $stmt = $this->db->prepare('INSERT INTO contact_messages (tenant_id, name, email, message, created_at) VALUES (:tenant_id, :name, :email, :message, datetime("now"))');
            $stmt->execute([
                'tenant_id' => $this->tenant['id'],
                'name' => $_POST['name'] ?? '',
                'email' => $_POST['email'] ?? '',
                'message' => $_POST['message'] ?? '',
            ]);
            $message = 'Thanks. Your note has been saved.';
        }
        $this->stats->hit((int) $this->tenant['id'], 'page');
        View::render('public/contact', $this->base([
            'message' => $message,
            'contactImage' => $this->imageFromSetting('contact_image_id'),
        ]));
    }

    public function subscribe(string $method): void
    {
        if ($method === 'POST') {
            $email = trim((string) ($_POST['email'] ?? ''));
            if ($email !== '') {
                $stmt = $this->db->prepare('INSERT OR IGNORE INTO subscribers (tenant_id, email, name, source, created_at) VALUES (:tenant_id, :email, :name, :source, datetime("now"))');
                $stmt->execute([
                    'tenant_id' => $this->tenant['id'],
                    'email' => $email,
                    'name' => $_POST['name'] ?? '',
                    'source' => $_POST['source'] ?? 'site',
                ]);
            }
        }

        if (str_contains($_SERVER['HTTP_ACCEPT'] ?? '', 'application/json') || strtolower($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') === 'fetch') {
            header('Content-Type: application/json');
            echo json_encode(['ok' => true]);
            return;
        }

        header('Location: /contact');
    }

    public function image(string $path): void
    {
        preg_match('#^/image/(\d+)#', $path, $m);
        $id = (int) ($m[1] ?? 0);
        $stmt = $this->db->prepare('SELECT * FROM images WHERE id = :id AND tenant_id = :tenant_id AND is_public = 1');
        $stmt->execute(['id' => $id, 'tenant_id' => $this->tenant['id']]);
        $image = $stmt->fetch();
        if (!$image) {
            $this->notFound();
            return;
        }
        $this->stats->hit((int) $this->tenant['id'], 'image_view', $id);
        View::render('public/image', $this->base(['image' => $image]));
    }

    public function media(string $path): void
    {
        $file = basename($path);
        if (!preg_match('/^[a-f0-9]{24}-(thumb|medium|large)\.jpg$/', $file)) {
            $this->notFound();
            return;
        }
        $full = $this->container['config']['storage_path'] . '/cache/' . $this->tenant['id'] . '/' . $file;
        if (!is_file($full)) {
            $this->notFound();
            return;
        }
        header('Content-Type: image/jpeg');
        header('Cache-Control: public, max-age=31536000, immutable');
        readfile($full);
    }

    public function notFound(): void
    {
        http_response_code(404);
        echo 'Not found.';
    }

    private function base(array $extra = []): array
    {
        $settings = $this->settings();
        return array_merge([
            'tenant' => $this->tenant,
            'settings' => $settings,
            'sections' => $this->sections(),
            'backgroundImage' => $this->imageById((int) ($settings['background_image_id'] ?? 0)),
        ], $extra);
    }

    private function settings(): array
    {
        $stmt = $this->db->prepare('SELECT setting_key, setting_value FROM settings WHERE tenant_id = :tenant_id');
        $stmt->execute(['tenant_id' => $this->tenant['id']]);
        return array_column($stmt->fetchAll(), 'setting_value', 'setting_key');
    }

    private function sections(): array
    {
        $stmt = $this->db->prepare('SELECT * FROM portfolio_sections WHERE tenant_id = :tenant_id ORDER BY sort_order, name');
        $stmt->execute(['tenant_id' => $this->tenant['id']]);
        return $stmt->fetchAll();
    }

    private function images(string $where, array $params = []): array
    {
        $params['tenant_id'] = $this->tenant['id'];
        $stmt = $this->db->prepare('SELECT * FROM images WHERE tenant_id = :tenant_id AND ' . $where . ' ORDER BY sort_order, created_at DESC');
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    private function imageById(int $id): ?array
    {
        if ($id <= 0) {
            return null;
        }
        $stmt = $this->db->prepare('SELECT * FROM images WHERE tenant_id = :tenant_id AND id = :id AND is_public = 1');
        $stmt->execute(['tenant_id' => $this->tenant['id'], 'id' => $id]);
        $row = $stmt->fetch();

        return $row ?: null;
    }

    private function imageFromSetting(string $key): ?array
    {
        $settings = $this->settings();
        return $this->imageById((int) ($settings[$key] ?? 0));
    }

    private function events(): array
    {
        $stmt = $this->db->prepare('SELECT * FROM exhibitions WHERE tenant_id = :tenant_id AND is_recent = 1 ORDER BY event_date DESC, id DESC');
        $stmt->execute(['tenant_id' => $this->tenant['id']]);
        return $stmt->fetchAll();
    }
}

// End of file.
