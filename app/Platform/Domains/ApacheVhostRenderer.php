<?php

declare(strict_types=1);

namespace App\Platform\Domains;

/**
 * Renders Apache virtual host configuration for tenant custom domains.
 *
 * This class only renders text. It does not write files, reload Apache,
 * or request TLS certificates.
 */
final class ApacheVhostRenderer
{
    public function renderHttpVhost(
        string $hostname,
        string $documentRoot,
        string $serverAdmin = 'webmaster@artsfol.io',
    ): string {
        $hostname = $this->normalizeHostname($hostname);
        $documentRoot = rtrim($documentRoot, '/');

        return <<<APACHE
<VirtualHost *:80>
    ServerName {$hostname}
    ServerAdmin {$serverAdmin}

    DocumentRoot {$documentRoot}

    <Directory {$documentRoot}>
        Options -Indexes +FollowSymLinks
        AllowOverride All
        Require all granted
    </Directory>

    ErrorLog \${APACHE_LOG_DIR}/{$hostname}-error.log
    CustomLog \${APACHE_LOG_DIR}/{$hostname}-access.log combined

    RewriteEngine On
    RewriteRule ^ https://%{HTTP_HOST}%{REQUEST_URI} [R=301,L]
</VirtualHost>

APACHE;
    }

    private function normalizeHostname(string $hostname): string
    {
        $hostname = strtolower(trim($hostname));

        if (str_contains($hostname, ':')) {
            $hostname = explode(':', $hostname, 2)[0];
        }

        $hostname = rtrim($hostname, '.');
        if (!preg_match('/^(?=.{1,253}$)(?:[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?)(?:\.[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?)+$/', $hostname)) {
            throw new \RuntimeException('Invalid hostname format.');
        }

        return $hostname;
    }
}

// End of file.
