#!/usr/bin/php
<?php

/**
 * Queues 12-hour and 24-hour abandoned-cart reminder emails.
 *
 * This script queues email_outbox rows only. Delivery is handled by the normal
 * background worker, so smoke/preflight tests do not send SMTP mail.
 */

declare(strict_types=1);

use App\Platform\Email\EmailOutboxRepository;
use App\Platform\Email\TemplateRenderer;
use App\Support\Database;

$root = dirname(__DIR__, 2);
require $root . '/bootstrap/app.php';

$pdo = Database::connect($root);
$outbox = new EmailOutboxRepository($pdo);
$renderer = new TemplateRenderer();
$templateRoot = $root . '/template/email/sales';

$columns = [];
$stmt = $pdo->query("SHOW COLUMNS FROM sales_carts");
foreach ($stmt ? $stmt->fetchAll(PDO::FETCH_ASSOC) : [] as $column) {
    $columns[(string) $column['Field']] = true;
}
foreach (['customer_email', 'abandoned_12h_email_sent_at', 'abandoned_24h_email_sent_at'] as $required) {
    if (!isset($columns[$required])) {
        fwrite(STDERR, "Missing sales_carts.{$required}; run migrations before queueing abandoned-cart emails.\n");
        exit(1);
    }
}

$select = $pdo->query(
    "SELECT c.id, c.tenant_id, c.cart_token, c.customer_email, c.customer_name,
            td.hostname
     FROM sales_carts c
     JOIN tenant_domains td ON td.tenant_id = c.tenant_id AND td.status <> 'disabled'
     WHERE c.status = 'active'
       AND c.customer_email IS NOT NULL
       AND c.updated_at <= DATE_SUB(CURRENT_TIMESTAMP, INTERVAL 12 HOUR)
       AND c.abandoned_12h_email_sent_at IS NULL
     GROUP BY c.id, c.tenant_id, c.cart_token, c.customer_email, c.customer_name, td.hostname
     ORDER BY c.updated_at ASC
     LIMIT 200"
);
$count12 = 0;
foreach ($select ? $select->fetchAll(PDO::FETCH_ASSOC) : [] as $cart) {
    $url = 'https://' . (string) $cart['hostname'] . '/cart';
    $body = $renderer->renderFile($templateRoot . '/abandoned-cart-12h.md', ['cart_url' => $url]);
    $outbox->queue(
        recipientEmail: (string) $cart['customer_email'],
        subject: 'Your ArtsFolio cart is waiting',
        bodyText: $body,
        recipientName: $cart['customer_name'] ? (string) $cart['customer_name'] : null,
        tenantId: (int) $cart['tenant_id'],
        templateKey: 'sales.abandoned_cart_12h',
    );
    $update = $pdo->prepare('UPDATE sales_carts SET abandoned_12h_email_sent_at = CURRENT_TIMESTAMP WHERE id = :id');
    $update->execute(['id' => (int) $cart['id']]);
    $count12++;
}

$select = $pdo->query(
    "SELECT c.id, c.tenant_id, c.cart_token, c.customer_email, c.customer_name,
            td.hostname
     FROM sales_carts c
     JOIN tenant_domains td ON td.tenant_id = c.tenant_id AND td.status <> 'disabled'
     WHERE c.status = 'active'
       AND c.customer_email IS NOT NULL
       AND c.updated_at <= DATE_SUB(CURRENT_TIMESTAMP, INTERVAL 24 HOUR)
       AND c.abandoned_24h_email_sent_at IS NULL
     GROUP BY c.id, c.tenant_id, c.cart_token, c.customer_email, c.customer_name, td.hostname
     ORDER BY c.updated_at ASC
     LIMIT 200"
);
$count24 = 0;
foreach ($select ? $select->fetchAll(PDO::FETCH_ASSOC) : [] as $cart) {
    $url = 'https://' . (string) $cart['hostname'] . '/cart';
    $body = $renderer->renderFile($templateRoot . '/abandoned-cart-24h.md', ['cart_url' => $url]);
    $outbox->queue(
        recipientEmail: (string) $cart['customer_email'],
        subject: 'Reminder: your ArtsFolio cart is still open',
        bodyText: $body,
        recipientName: $cart['customer_name'] ? (string) $cart['customer_name'] : null,
        tenantId: (int) $cart['tenant_id'],
        templateKey: 'sales.abandoned_cart_24h',
    );
    $update = $pdo->prepare('UPDATE sales_carts SET abandoned_24h_email_sent_at = CURRENT_TIMESTAMP WHERE id = :id');
    $update->execute(['id' => (int) $cart['id']]);
    $count24++;
}

echo json_encode(['queued_12h' => $count12, 'queued_24h' => $count24], JSON_PRETTY_PRINT) . "\n";

// End of file.
