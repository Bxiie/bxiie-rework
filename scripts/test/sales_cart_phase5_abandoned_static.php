#!/usr/bin/php
<?php

/**
 * Static regression checks for shopping-cart phase 5 abandoned-cart reminders.
 */

declare(strict_types=1);

$root = dirname(__DIR__, 2);
$checks = [
    'app/Tenant/Sales/AbandonedCartEmailQueueService.php' => [
        'sales.abandoned_cart_1d',
        'sales.abandoned_cart_3d',
        'sales.abandoned_cart_7d',
        'abandoned_1d_email_sent_at',
        'abandoned_3d_email_sent_at',
        'abandoned_7d_email_sent_at',
        '/cart/bridge?token=',
        'email_signups',
        'newsletter_subscribers',
        'checkout_pending',
        'artwork_sale_variants',
    ],
    'app/Platform/Jobs/Handlers/QueueAbandonedCartEmailsJobHandler.php' => [
        'AbandonedCartEmailQueueService',
        'queueDue',
        'Queued abandoned-cart reminders',
    ],
    'scripts/workers/run_once.php' => [
        'QueueAbandonedCartEmailsJobHandler',
        'sales.cart.queue_abandoned_reminders',
        'AbandonedCartEmailQueueService($pdo, $root)',
        "enqueueSingleton('sales.cart.queue_abandoned_reminders'",
    ],
    'scripts/email/queue_abandoned_cart_emails.php' => [
        'Queues 1-day, 3-day, and 7-day abandoned-cart reminder emails',
        'AbandonedCartEmailQueueService',
        'ARTSFOLIO_ABANDONED_CART_LIMIT_PER_STAGE',
    ],
    'template/email/sales/abandoned-cart-1d.md' => ['{{ cart_url }}', '{{ tenant_name }}', '{{ cart_total }}'],
    'template/email/sales/abandoned-cart-3d.md' => ['availability can change', '{{ cart_url }}'],
    'template/email/sales/abandoned-cart-7d.md' => ['final reminder', '{{ cart_url }}'],
    'database/migrations/0057_abandoned_cart_reminder_job.sql' => [
        'sales.cart.queue_abandoned_reminders',
        'idx_sales_carts_abandoned_schedule',
        'interval_seconds',
    ],
    'scripts/test/pricing_billing_auth_social_static.php' => [
        'abandoned_1d_email_sent_at',
        'abandoned_3d_email_sent_at',
        'abandoned_7d_email_sent_at',
    ],
    'docs/dev/sales-cart-checkout.md' => ['Phase 5', 'sales.cart.queue_abandoned_reminders', '1-day, 3-day, and 7-day'],
    'docs/admin/sales-cart-products.md' => ['abandoned-cart reminders', '1, 3, and 7 days'],
    'docs/user/buying-artwork.md' => ['saved cart reminder', 'restore your cart'],
    'PROJECT_STATE.md' => ['sales.cart.queue_abandoned_reminders', 'abandoned-cart reminders at 1, 3, and 7 days'],
];

foreach ($checks as $file => $needles) {
    $path = $root . '/' . $file;
    if (!is_file($path)) {
        fwrite(STDERR, "Missing {$file}
");
        exit(1);
    }
    $text = file_get_contents($path);
    foreach ($needles as $needle) {
        if (!str_contains((string) $text, $needle)) {
            fwrite(STDERR, "{$file} missing {$needle}
");
            exit(1);
        }
    }
}

$script = file_get_contents($root . '/scripts/email/queue_abandoned_cart_emails.php') ?: '';
foreach (['abandoned_12h_email_sent_at', 'abandoned_24h_email_sent_at', 'sales.abandoned_cart_12h', 'sales.abandoned_cart_24h'] as $oldNeedle) {
    if (str_contains($script, $oldNeedle)) {
        fwrite(STDERR, "queue_abandoned_cart_emails.php still references old {$oldNeedle}
");
        exit(1);
    }
}

echo "Shopping cart phase 5 abandoned-cart static checks passed.
";

// End of file.
