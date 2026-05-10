<?php
/**
 * Bxiie Artist CMS configuration.
 * Keep secrets outside web root. Override these values with environment variables in production.
 */
return [
    'app_name' => getenv('APP_NAME') ?: 'Bxiie Artist CMS',
    'base_url' => getenv('BASE_URL') ?: '',
    'database_path' => getenv('DATABASE_PATH') ?: __DIR__ . '/../database/bxiie.sqlite',
    'storage_path' => getenv('STORAGE_PATH') ?: __DIR__ . '/../storage',
    'admin_path' => getenv('ADMIN_PATH') ?: '/admin',
    'session_name' => getenv('SESSION_NAME') ?: 'bxiie_admin',
    'trusted_proxy' => getenv('TRUSTED_PROXY') ?: false,
    'default_tenant_slug' => getenv('DEFAULT_TENANT_SLUG') ?: 'bxiie',
    'default_domain' => getenv('DEFAULT_DOMAIN') ?: 'bxiie.com',
    'image_sizes' => [
        'thumb' => 420,
        'medium' => 1200,
        'large' => 2200,
    ],
    'watermark_text' => getenv('WATERMARK_TEXT') ?: '© Bxiie',
    'smtp' => [
        'enabled' => false,
        'from_email' => getenv('MAIL_FROM') ?: 'noreply@bxiie.com',
        'contact_to' => getenv('CONTACT_TO') ?: 'artist@bxiie.com',
    ],
];

// End of file.
