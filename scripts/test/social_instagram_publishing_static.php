<?php

declare(strict_types=1);

/**
 * Static regression coverage for the provider-neutral Instagram publishing subsystem.
 */

$root = dirname(__DIR__, 2);
$failures = [];

$requiredFiles = [
    'database/migrations/0070_social_instagram_publishing.sql',
    'app/Tenant/Social/SocialRepository.php',
    'app/Tenant/Social/SocialPermissionService.php',
    'app/Tenant/Social/SocialTokenCipher.php',
    'app/Tenant/Social/SocialTemplateRenderer.php',
    'app/Tenant/Social/SocialImageService.php',
    'app/Tenant/Social/InstagramClient.php',
    'app/Tenant/Social/SocialPublishingService.php',
    'app/Http/Controllers/Tenant/SocialFrontController.php',
    'app/Http/Controllers/Tenant/SocialComposeApiController.php',
    'app/Http/Controllers/Tenant/SocialPermissionAdminController.php',
    'public/assets/social-publishing.js',
    'docs/dev/social-instagram-publishing.md',
    'docs/admin/instagram-publishing.md',
    'docs/user/instagram-publishing.md',
    'template/email/social/instagram-status.txt',
];

foreach ($requiredFiles as $file) {
    if (!is_file($root . '/' . $file)) {
        $failures[] = "Missing required file: {$file}";
    }
}

$checks = [
    'database/migrations/0070_social_instagram_publishing.sql' => [
        'social_connections',
        'social_templates',
        'social_section_templates',
        'tenant_user_permissions',
        'social_posts',
        'social_post_items',
        'social_publish_attempts',
        'social_media_tokens',
        'social_caption',
        'social_hashtags',
        'social.publish_due',
    ],
    'app/Tenant/Social/SocialPermissionService.php' => [
        "PUBLISH_PERMISSION = 'social.publish'",
        "['tenant_owner', 'tenant_admin', 'owner', 'admin']",
        "['editor']",
    ],
    'app/Tenant/Social/SocialTokenCipher.php' => [
        'sodium_crypto_secretbox',
        'ARTSFOLIO_SOCIAL_TOKEN_KEY',
    ],
    'app/Tenant/Social/SocialTemplateRenderer.php' => [
        "'social_caption'",
        "'social_or_description'",
        "'artwork_url'",
        "'default_hashtags'",
        "'artwork_hashtags'",
        "'hashtags'",
    ],
    'app/Tenant/Social/InstagramClient.php' => [
        'instagram_business_basic',
        'instagram_business_content_publish',
        'graph.instagram.com',
        '/media_publish',
        'media_type',
        'CAROUSEL',
        'content_publishing_limit',
    ],
    'app/Tenant/Social/SocialPublishingService.php' => [
        'createDerivative',
        'createMediaToken',
        'createCarouselContainer',
        'markPublished',
        'authorization_required',
        'social.instagram.status',
    ],
    'app/Http/Controllers/Tenant/SocialFrontController.php' => [
        '/admin/social/compose',
        '/admin/social/post',
        '/admin/social/cancel',
        '/admin/social/artwork-metadata',
        '/social/instagram/callback',
        '/social/media/',
        'Studio, Professional, and Collective',
        'Internal artwork notes are intentionally unavailable',
    ],
    'app/Http/Controllers/Tenant/SocialPermissionAdminController.php' => [
        '/admin/social/editor-permission',
        'Tenant admin access required.',
    ],
    'public/assets/social-publishing.js' => [
        'Post to Instagram',
        'Publish to social media',
        'Instagram carousels may contain at most 10 images.',
        '/admin/social/compose-state',
        '/admin/social/render-template',
        "toLowerCase() === 'artworks'",
    ],
    'scripts/workers/run_once.php' => [
        "case 'social.publish_due':",
        'SocialPublishingService',
        "enqueueSingleton(\n                    'social.publish_due'",
    ],
    'public/index.php' => [
        'SocialFrontController',
        'SocialComposeApiController',
        'SocialPermissionAdminController',
        '/assets/social-publishing.js',
    ],
    '.env.example' => [
        'ARTSFOLIO_INSTAGRAM_CLIENT_ID',
        'ARTSFOLIO_INSTAGRAM_CLIENT_SECRET',
        'ARTSFOLIO_INSTAGRAM_REDIRECT_URI',
        'ARTSFOLIO_SOCIAL_TOKEN_KEY',
        'ARTSFOLIO_SOCIAL_STATE_KEY',
    ],
    'scripts/database/check_migration_integrity.php' => [
        '0070_social_instagram_publishing.sql',
        'social_connections',
        "'artworks' => ['social_caption', 'social_hashtags']",
    ],
    'app/Platform/Email/EmailTemplateCatalog.php' => [
        'social/instagram-status.txt',
        'social.instagram.status',
    ],
];

foreach ($checks as $file => $needles) {
    $path = $root . '/' . $file;
    if (!is_file($path)) {
        $failures[] = "Missing check file: {$file}";
        continue;
    }
    $contents = file_get_contents($path) ?: '';
    foreach ($needles as $needle) {
        if (!str_contains($contents, $needle)) {
            $failures[] = "{$file} missing marker: {$needle}";
        }
    }
}

// Internal notes must not be exposed as a social caption template placeholder.
$renderer = is_file($root . '/app/Tenant/Social/SocialTemplateRenderer.php')
    ? (file_get_contents($root . '/app/Tenant/Social/SocialTemplateRenderer.php') ?: '')
    : '';
if (preg_match('/[\x27\x22]notes(?:_html)?[\x27\x22]\s*=>/', $renderer) === 1) {
    $failures[] = 'SocialTemplateRenderer exposes artwork notes as a placeholder.';
}

if ($failures !== []) {
    foreach ($failures as $failure) {
        fwrite(STDERR, "[FAIL] {$failure}\n");
    }
    exit(1);
}

echo "[PASS] Instagram/social publishing static checks passed.\n";

// End of file.
