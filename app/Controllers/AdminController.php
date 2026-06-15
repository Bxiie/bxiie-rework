<?php

declare(strict_types=1);

namespace App\Controllers;


use App\Http\View\ErrorPage;
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
        View::render('admin/dashboard', ['tenant' => $this->tenant, 'counts' => $this->counts()]);
    }

    public function site(string $method): void
    {
        if ($method === 'POST') {
            $this->saveSettings([
                'site_title',
                'browser_title',
                'artist_name',
                'copyright_name',
                'home_tab',
                'portfolio_tab',
                'about_tab',
                'contact_tab',
                'primary_color',
                'accent_color',
                'background_color',
                'home_intro',
                'about_image_id',
                'contact_image_id',
                'background_image_id',
                'background_mode',
                'background_tile_size',
                'background_opacity',
                'about_image_size',
                'contact_image_size',
                'turnstile_site_key',
                'turnstile_secret_key',
                'tenant_css',
                'portfolio_slug',
                'about_slug',
                'contact_slug',
                'exhibitions_heading',
                'exhibitions_display_mode',
            ]);
            $this->flash('Site settings saved.');
            $this->redirect('/admin/site');
        }

        View::render('admin/site', [
            'tenant' => $this->tenant,
            'settings' => $this->settings(),
            'images' => $this->imageOptions(),
        ]);
    }

    public function images(string $method): void
    {
        if ($method === 'POST' && isset($_FILES['image'])) {
            $service = new ImageService($this->container['config']);
            $result = $service->processUpload($_FILES['image'], (int) $this->tenant['id'], !empty($_POST['watermark']));
            $stmt = $this->db->prepare(
                'INSERT INTO images (
                    tenant_id,
                    title,
                    description,
                    medium,
                    year,
                    dimensions,
                    price,
                    sale_status,
                    location,
                    tags,
                    alt_text,
                    sort_order,
                    storage_key,
                    original_path,
                    mime_type,
                    width,
                    height,
                    is_public,
                    is_draft,
                    featured_home,
                    featured_rotator,
                    featured_about,
                    featured_contact,
                    background_image,
                    watermarked,
                    created_at
                ) VALUES (
                    :tenant_id,
                    :title,
                    :description,
                    :medium,
                    :year,
                    :dimensions,
                    :price,
                    :sale_status,
                    :location,
                    :tags,
                    :alt_text,
                    :sort_order,
                    :storage_key,
                    :original_path,
                    :mime_type,
                    :width,
                    :height,
                    :is_public,
                    :is_draft,
                    :featured_home,
                    :featured_rotator,
                    :featured_about,
                    :featured_contact,
                    :background_image,
                    :watermarked,
                    datetime("now")
                )'
            );
            $stmt->execute(array_merge($result, [
                'tenant_id' => $this->tenant['id'],
                'title' => $_POST['title'] ?? 'Untitled',
                'description' => $_POST['description'] ?? '',
                'medium' => $_POST['medium'] ?? '',
                'year' => $_POST['year'] ?? '',
                'dimensions' => $_POST['dimensions'] ?? '',
                'price' => $_POST['price'] ?? '',
                'sale_status' => $_POST['sale_status'] ?? '',
                'location' => $_POST['location'] ?? '',
                'tags' => $_POST['tags'] ?? '',
                'alt_text' => $_POST['alt_text'] ?? ($_POST['title'] ?? 'Untitled'),
                'sort_order' => (int) ($_POST['sort_order'] ?? 100),
                'is_public' => empty($_POST['is_draft']) ? 1 : 0,
                'is_draft' => !empty($_POST['is_draft']) ? 1 : 0,
                'featured_home' => !empty($_POST['featured_home']) ? 1 : 0,
                'featured_rotator' => !empty($_POST['featured_rotator']) ? 1 : 0,
                'featured_about' => !empty($_POST['featured_about']) ? 1 : 0,
                'featured_contact' => !empty($_POST['featured_contact']) ? 1 : 0,
                'background_image' => !empty($_POST['background_image']) ? 1 : 0,
            ]));
            $imageId = (int) $this->db->lastInsertId();
            $this->replaceImageSections($imageId, $_POST['section_ids'] ?? []);
            $this->flash('Image uploaded and derivatives created.');
            $this->redirect('/admin/images');
        }

        $stmt = $this->db->prepare('SELECT * FROM images WHERE tenant_id = :tenant_id ORDER BY sort_order, created_at DESC');
        $stmt->execute(['tenant_id' => $this->tenant['id']]);
        View::render('admin/images', [
            'tenant' => $this->tenant,
            'images' => $stmt->fetchAll(),
            'sections' => $this->portfolioSections(),
        ]);
    }

    public function imageEdit(string $method): void
    {
        $id = (int) ($_GET['id'] ?? $_POST['id'] ?? 0);
        $image = $this->findTenantRow('images', $id);
        if (!$image) {
            $this->notFound();
            return;
        }

        if ($method === 'POST') {
            $stmt = $this->db->prepare(
                'UPDATE images SET
                    title = :title,
                    description = :description,
                    medium = :medium,
                    year = :year,
                    dimensions = :dimensions,
                    price = :price,
                    sale_status = :sale_status,
                    location = :location,
                    tags = :tags,
                    alt_text = :alt_text,
                    sort_order = :sort_order,
                    is_public = :is_public,
                    is_draft = :is_draft,
                    featured_home = :featured_home,
                    featured_rotator = :featured_rotator,
                    featured_about = :featured_about,
                    featured_contact = :featured_contact,
                    background_image = :background_image,
                    watermarked = :watermarked
                 WHERE id = :id AND tenant_id = :tenant_id'
            );
            $stmt->execute([
                'id' => $id,
                'tenant_id' => $this->tenant['id'],
                'title' => $_POST['title'] ?? '',
                'description' => $_POST['description'] ?? '',
                'medium' => $_POST['medium'] ?? '',
                'year' => $_POST['year'] ?? '',
                'dimensions' => $_POST['dimensions'] ?? '',
                'price' => $_POST['price'] ?? '',
                'sale_status' => $_POST['sale_status'] ?? '',
                'location' => $_POST['location'] ?? '',
                'tags' => $_POST['tags'] ?? '',
                'alt_text' => $_POST['alt_text'] ?? '',
                'sort_order' => (int) ($_POST['sort_order'] ?? 100),
                'is_public' => empty($_POST['is_draft']) ? 1 : 0,
                'is_draft' => !empty($_POST['is_draft']) ? 1 : 0,
                'featured_home' => !empty($_POST['featured_home']) ? 1 : 0,
                'featured_rotator' => !empty($_POST['featured_rotator']) ? 1 : 0,
                'featured_about' => !empty($_POST['featured_about']) ? 1 : 0,
                'featured_contact' => !empty($_POST['featured_contact']) ? 1 : 0,
                'background_image' => !empty($_POST['background_image']) ? 1 : 0,
                'watermarked' => !empty($_POST['watermarked']) ? 1 : 0,
            ]);
            $this->replaceImageSections($id, $_POST['section_ids'] ?? []);
            $this->flash('Image details saved.');
            $this->redirect('/admin/images/edit?id=' . $id);
        }

        View::render('admin/image_edit', [
            'tenant' => $this->tenant,
            'image' => $image,
            'sections' => $this->portfolioSections(),
            'selectedSectionIds' => $this->imageSectionIds($id),
        ]);
    }

    public function portfolio(string $method): void
    {
        if ($method === 'POST') {
            $this->savePortfolioSection(null);
            $this->flash('Portfolio section created.');
            $this->redirect('/admin/portfolio');
        }

        View::render('admin/portfolio', [
            'tenant' => $this->tenant,
            'sections' => $this->portfolioSections(),
        ]);
    }

    public function portfolioEdit(string $method): void
    {
        $id = (int) ($_GET['id'] ?? $_POST['id'] ?? 0);
        $section = $this->findTenantSection($id);
        if (!$section) {
            $this->notFound();
            return;
        }

        if ($method === 'POST') {
            $this->savePortfolioSection($id);
            $this->flash('Portfolio section saved.');
            $this->redirect('/admin/portfolio/edit?id=' . $id);
        }

        View::render('admin/portfolio_edit', [
            'tenant' => $this->tenant,
            'section' => $section,
        ]);
    }

    public function events(string $method): void
    {
        if ($method === 'POST') {
            $this->saveEvent(null);
            $this->flash('Event saved.');
            $this->redirect('/admin/events');
        }
        $stmt = $this->db->prepare('SELECT * FROM exhibitions WHERE tenant_id = :tenant_id ORDER BY event_date DESC, id DESC');
        $stmt->execute(['tenant_id' => $this->tenant['id']]);
        View::render('admin/events', ['tenant' => $this->tenant, 'events' => $stmt->fetchAll()]);
    }

    public function eventEdit(string $method): void
    {
        $id = (int) ($_GET['id'] ?? $_POST['id'] ?? 0);
        $event = $this->findTenantRow('exhibitions', $id);
        if (!$event) {
            $this->notFound();
            return;
        }

        if ($method === 'POST') {
            $this->saveEvent($id);
            $this->flash('Event details saved.');
            $this->redirect('/admin/events/edit?id=' . $id);
        }

        View::render('admin/event_edit', ['tenant' => $this->tenant, 'event' => $event]);
    }

    public function content(string $method): void
    {
        if ($method === 'POST') {
            $this->saveSettings(['about_content', 'contact_details', 'facebook_url', 'instagram_url', 'linkedin_url']);
            $this->flash('Content details saved.');
            $this->redirect('/admin/content');
        }
        View::render('admin/content', ['tenant' => $this->tenant, 'settings' => $this->settings()]);
    }

    public function messages(): void
    {
        $stmt = $this->db->prepare('SELECT * FROM contact_messages WHERE tenant_id = :tenant_id ORDER BY created_at DESC');
        $stmt->execute(['tenant_id' => $this->tenant['id']]);
        View::render('admin/messages', ['tenant' => $this->tenant, 'messages' => $stmt->fetchAll()]);
    }

    public function deleteMessage(string $method): void
    {
        if ($method !== 'POST') {
            $this->notFound();
            return;
        }

        $id = (int) ($_POST['id'] ?? 0);
        if ($id > 0) {
            $stmt = $this->db->prepare('DELETE FROM contact_messages WHERE id = :id AND tenant_id = :tenant_id');
            $stmt->execute([
                'id' => $id,
                'tenant_id' => $this->tenant['id'],
            ]);
            $this->flash('Contact message deleted.');
        }

        $this->redirect('/admin/messages');
    }

    public function subscribers(): void
    {
        $stmt = $this->db->prepare('SELECT * FROM subscribers WHERE tenant_id = :tenant_id ORDER BY created_at DESC, email');
        $stmt->execute(['tenant_id' => $this->tenant['id']]);
        View::render('admin/subscribers', ['tenant' => $this->tenant, 'subscribers' => $stmt->fetchAll()]);
    }

    public function exportSubscribers(): void
    {
        $stmt = $this->db->prepare('SELECT email, name, source, created_at FROM subscribers WHERE tenant_id = :tenant_id ORDER BY email');
        $stmt->execute(['tenant_id' => $this->tenant['id']]);

        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="bxiie-subscribers.csv"');

        $out = fopen('php://output', 'wb');
        fputcsv($out, ['email', 'name', 'source', 'created_at']);
        foreach ($stmt->fetchAll() as $row) {
            fputcsv($out, [$row['email'], $row['name'], $row['source'], $row['created_at']]);
        }
        fclose($out);
        exit;
    }

    public function users(string $method): void
    {
        if ($method === 'POST') {
            $stmt = $this->db->prepare('INSERT INTO users (tenant_id, name, email, role, password_hash, created_at) VALUES (:tenant_id, :name, :email, :role, :password_hash, datetime("now"))');
            $stmt->execute([
                'tenant_id' => $this->tenant['id'],
                'name' => $_POST['name'] ?? '',
                'email' => $_POST['email'] ?? '',
                'role' => $_POST['role'] ?? 'viewer',
                'password_hash' => password_hash($_POST['password'] ?? bin2hex(random_bytes(8)), PASSWORD_DEFAULT),
            ]);
            $this->flash('User saved.');
            $this->redirect('/admin/users');
        }
        $stmt = $this->db->prepare('SELECT id, name, email, role, created_at FROM users WHERE tenant_id = :tenant_id ORDER BY created_at DESC');
        $stmt->execute(['tenant_id' => $this->tenant['id']]);
        View::render('admin/users', ['tenant' => $this->tenant, 'users' => $stmt->fetchAll()]);
    }

    public function stats(): void
    {
        $from = $_GET['from'] ?? date('Y-m-d', strtotime('-30 days'));
        $to = $_GET['to'] ?? date('Y-m-d');
        $q = trim((string) ($_GET['q'] ?? ''));
        $group = $_GET['group'] ?? 'path';
        $statsStartedAt = $this->statsStartedAt();

        if ($group === 'location') {
            $sql = 'SELECT city, state, country, country_code, COUNT(*) AS hits
                    FROM page_views
                    WHERE tenant_id = :tenant_id AND date(created_at) BETWEEN :from AND :to
                    GROUP BY city, state, country, country_code
                    ORDER BY hits DESC
                    LIMIT 250';
            $stmt = $this->db->prepare($sql);
            $stmt->execute(['tenant_id' => $this->tenant['id'], 'from' => $from, 'to' => $to]);
            View::render('admin/stats', [
                'tenant' => $this->tenant,
                'rows' => $stmt->fetchAll(),
                'from' => $from,
                'to' => $to,
                'q' => $q,
                'group' => $group,
                'stats_started_at' => $statsStartedAt,
            ]);
            return;
        }

        $params = ['tenant_id' => $this->tenant['id'], 'from' => $from, 'to' => $to];
        $searchSql = '';
        if ($q !== '') {
            $searchSql = ' AND (i.title LIKE :q OR pv.path LIKE :q OR pv.city LIKE :q OR pv.state LIKE :q OR pv.country LIKE :q)';
            $params['q'] = '%' . $q . '%';
        }

        $stmt = $this->db->prepare(
            'SELECT
                pv.event_type,
                pv.path,
                pv.country_code,
                pv.city,
                pv.state,
                pv.country,
                pv.image_id,
                i.title AS image_title,
                i.storage_key,
                COUNT(*) AS hits
             FROM page_views pv
             LEFT JOIN images i ON i.id = pv.image_id AND i.tenant_id = pv.tenant_id
             WHERE pv.tenant_id = :tenant_id AND date(pv.created_at) BETWEEN :from AND :to' . $searchSql . '
             GROUP BY pv.event_type, pv.path, pv.country_code, pv.city, pv.state, pv.country, pv.image_id, i.title, i.storage_key
             ORDER BY hits DESC
             LIMIT 250'
        );
        $stmt->execute($params);
        View::render('admin/stats', [
            'tenant' => $this->tenant,
            'rows' => $stmt->fetchAll(),
            'from' => $from,
            'to' => $to,
            'q' => $q,
            'group' => $group,
            'stats_started_at' => $statsStartedAt,
        ]);
    }

    public function resetStats(string $method): void
    {
        if ($method !== 'POST') {
            $this->notFound();
            return;
        }

        $confirm = trim((string) ($_POST['confirm'] ?? ''));
        if ($confirm !== 'RESET') {
            $this->flash('Stats were not reset. Type RESET to confirm.');
            $this->redirect('/admin/stats');
        }

        $stmt = $this->db->prepare('DELETE FROM page_views WHERE tenant_id = :tenant_id');
        $stmt->execute(['tenant_id' => $this->tenant['id']]);

        $startedAt = date('Y-m-d H:i:s');
        $stmt = $this->db->prepare(
            'INSERT INTO settings (tenant_id, setting_key, setting_value)
             VALUES (:tenant_id, "stats_started_at", :started_at)
             ON CONFLICT(tenant_id, setting_key) DO UPDATE SET setting_value = excluded.setting_value'
        );
        $stmt->execute([
            'tenant_id' => $this->tenant['id'],
            'started_at' => $startedAt,
        ]);

        $this->flash('Usage statistics reset. New stats start: ' . $startedAt . '.');
        $this->redirect('/admin/stats');
    }

    public function notFound(): void
    {
        http_response_code(404);
        echo ErrorPage::notFound($_SERVER['REQUEST_METHOD'] . ' ' . parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH));
    }

    private function saveEvent(?int $id): void
    {
        $params = [
            'tenant_id' => $this->tenant['id'],
            'title' => $_POST['title'] ?? '',
            'venue' => $_POST['venue'] ?? '',
            'city' => $_POST['city'] ?? '',
            'state' => $_POST['state'] ?? '',
            'event_date' => $_POST['event_date'] ?? '',
            'display_date' => $_POST['display_date'] ?? ($_POST['event_date'] ?? ''),
            'url' => $_POST['url'] ?? '',
            'description' => $_POST['description'] ?? '',
            'event_type' => $_POST['event_type'] ?? '',
            'work_name' => $_POST['work_name'] ?? '',
            'additional_info' => $_POST['additional_info'] ?? '',
            'is_recent' => !empty($_POST['is_recent']) ? 1 : 0,
        ];

        if ($id === null) {
            $stmt = $this->db->prepare(
                'INSERT INTO exhibitions (tenant_id, title, venue, city, state, event_date, display_date, url, description, event_type, work_name, additional_info, is_recent)
                 VALUES (:tenant_id, :title, :venue, :city, :state, :event_date, :display_date, :url, :description, :event_type, :work_name, :additional_info, :is_recent)'
            );
            $stmt->execute($params);
            return;
        }

        $params['id'] = $id;
        $stmt = $this->db->prepare(
            'UPDATE exhibitions SET
                title = :title,
                venue = :venue,
                city = :city,
                state = :state,
                event_date = :event_date,
                display_date = :display_date,
                url = :url,
                description = :description,
                event_type = :event_type,
                work_name = :work_name,
                additional_info = :additional_info,
                is_recent = :is_recent
             WHERE id = :id AND tenant_id = :tenant_id'
        );
        $stmt->execute($params);
    }

    private function statsStartedAt(): string
    {
        $stmt = $this->db->prepare('SELECT setting_value FROM settings WHERE tenant_id = :tenant_id AND setting_key = "stats_started_at"');
        $stmt->execute(['tenant_id' => $this->tenant['id']]);
        $row = $stmt->fetch();

        if ($row && trim((string) $row['setting_value']) !== '') {
            return (string) $row['setting_value'];
        }

        $stmt = $this->db->prepare('SELECT MIN(created_at) AS started_at FROM page_views WHERE tenant_id = :tenant_id');
        $stmt->execute(['tenant_id' => $this->tenant['id']]);
        $row = $stmt->fetch();

        if ($row && trim((string) ($row['started_at'] ?? '')) !== '') {
            return (string) $row['started_at'];
        }

        return 'No hits recorded yet';
    }

    private function settings(): array
    {
        $stmt = $this->db->prepare('SELECT setting_key, setting_value FROM settings WHERE tenant_id = :tenant_id');
        $stmt->execute(['tenant_id' => $this->tenant['id']]);
        return array_column($stmt->fetchAll(), 'setting_value', 'setting_key');
    }

    private function saveSettings(array $fields): void
    {
        foreach ($fields as $field) {
            $stmt = $this->db->prepare(
                'INSERT INTO settings (tenant_id, setting_key, setting_value)
                 VALUES (:tenant_id, :key, :value)
                 ON CONFLICT(tenant_id, setting_key) DO UPDATE SET setting_value = excluded.setting_value'
            );
            $stmt->execute([
                'tenant_id' => $this->tenant['id'],
                'key' => $field,
                'value' => $_POST[$field] ?? '',
            ]);
        }
    }

    private function savePortfolioSection(?int $id): void
    {
        $name = trim((string) ($_POST['name'] ?? ''));
        $slug = $this->safeSlug((string) ($_POST['slug'] ?? ''), $name);

        if ($name === '') {
            $this->flash('Portfolio section name is required.');
            $this->redirect('/admin/portfolio');
        }

        if ($id === null) {
            $stmt = $this->db->prepare(
                'INSERT INTO portfolio_sections (tenant_id, name, slug, description, sort_order)
                 VALUES (:tenant_id, :name, :slug, :description, :sort_order)
                 ON CONFLICT(tenant_id, slug) DO UPDATE SET
                    name = excluded.name,
                    description = excluded.description,
                    sort_order = excluded.sort_order'
            );
            $stmt->execute([
                'tenant_id' => $this->tenant['id'],
                'name' => $name,
                'slug' => $slug,
                'description' => $_POST['description'] ?? '',
                'sort_order' => (int) ($_POST['sort_order'] ?? 100),
            ]);
            return;
        }

        $stmt = $this->db->prepare(
            'UPDATE portfolio_sections SET
                name = :name,
                slug = :slug,
                description = :description,
                sort_order = :sort_order
             WHERE id = :id AND tenant_id = :tenant_id'
        );
        $stmt->execute([
            'id' => $id,
            'tenant_id' => $this->tenant['id'],
            'name' => $name,
            'slug' => $slug,
            'description' => $_POST['description'] ?? '',
            'sort_order' => (int) ($_POST['sort_order'] ?? 100),
        ]);
    }

    private function portfolioSections(): array
    {
        $stmt = $this->db->prepare('SELECT * FROM portfolio_sections WHERE tenant_id = :tenant_id ORDER BY sort_order, name');
        $stmt->execute(['tenant_id' => $this->tenant['id']]);
        return $stmt->fetchAll();
    }

    private function findTenantSection(int $id): ?array
    {
        if ($id <= 0) {
            return null;
        }

        $stmt = $this->db->prepare('SELECT * FROM portfolio_sections WHERE id = :id AND tenant_id = :tenant_id');
        $stmt->execute(['id' => $id, 'tenant_id' => $this->tenant['id']]);
        $row = $stmt->fetch();

        return $row ?: null;
    }

    private function imageSectionIds(int $imageId): array
    {
        $stmt = $this->db->prepare(
            'SELECT ps.id
             FROM portfolio_sections ps
             INNER JOIN image_sections ims ON ims.section_id = ps.id
             WHERE ps.tenant_id = :tenant_id AND ims.image_id = :image_id'
        );
        $stmt->execute([
            'tenant_id' => $this->tenant['id'],
            'image_id' => $imageId,
        ]);

        return array_map('intval', array_column($stmt->fetchAll(), 'id'));
    }

    private function replaceImageSections(int $imageId, array|string $sectionIds): void
    {
        if ($imageId <= 0) {
            return;
        }

        $ids = is_array($sectionIds) ? $sectionIds : [$sectionIds];
        $ids = array_values(array_unique(array_filter(array_map('intval', $ids), static fn(int $id): bool => $id > 0)));

        $stmt = $this->db->prepare(
            'DELETE FROM image_sections
             WHERE image_id = :image_id
             AND section_id IN (SELECT id FROM portfolio_sections WHERE tenant_id = :tenant_id)'
        );
        $stmt->execute([
            'image_id' => $imageId,
            'tenant_id' => $this->tenant['id'],
        ]);

        if (!$ids) {
            return;
        }

        $validate = $this->db->prepare('SELECT id FROM portfolio_sections WHERE id = :id AND tenant_id = :tenant_id');
        $insert = $this->db->prepare('INSERT OR IGNORE INTO image_sections (image_id, section_id) VALUES (:image_id, :section_id)');

        foreach ($ids as $sectionId) {
            $validate->execute(['id' => $sectionId, 'tenant_id' => $this->tenant['id']]);
            if (!$validate->fetch()) {
                continue;
            }
            $insert->execute(['image_id' => $imageId, 'section_id' => $sectionId]);
        }
    }

    private function safeSlug(string $value, string $fallback): string
    {
        $source = trim($value) !== '' ? $value : $fallback;
        $slug = strtolower(trim($source));
        $slug = preg_replace('/[^a-z0-9]+/', '-', $slug) ?? '';
        $slug = trim($slug, '-');

        return $slug !== '' ? $slug : 'section';
    }

    private function imageOptions(): array
    {
        $stmt = $this->db->prepare('SELECT id, title, storage_key, year FROM images WHERE tenant_id = :tenant_id ORDER BY title');
        $stmt->execute(['tenant_id' => $this->tenant['id']]);
        return $stmt->fetchAll();
    }

    private function findTenantRow(string $table, int $id): ?array
    {
        if (!in_array($table, ['images', 'exhibitions'], true)) {
            return null;
        }
        $stmt = $this->db->prepare("SELECT * FROM {$table} WHERE id = :id AND tenant_id = :tenant_id");
        $stmt->execute(['id' => $id, 'tenant_id' => $this->tenant['id']]);
        $row = $stmt->fetch();

        return $row ?: null;
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
        $stmt = $this->db->prepare(
            'INSERT INTO settings (tenant_id, setting_key, setting_value)
             VALUES (:tenant_id, "copyright_year", :year)
             ON CONFLICT(tenant_id, setting_key) DO UPDATE SET setting_value = excluded.setting_value'
        );
        $stmt->execute(['tenant_id' => $this->tenant['id'], 'year' => $year]);
    }

    private function flash(string $message): void
    {
        $_SESSION['flash'] = $message;
    }

    private function redirect(string $path): never
    {
        header('Location: ' . $path);
        exit;
    }
}

// End of file.
