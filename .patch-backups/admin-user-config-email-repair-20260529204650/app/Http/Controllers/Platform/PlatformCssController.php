<?php

declare(strict_types=1);

namespace App\Http\Controllers\Platform;

use App\Http\Request;
use App\Http\Response;
use App\Platform\Settings\PlatformSettingsRepository;

/**
 * Serves platform-owned custom CSS configured by platform admins.
 */
final class PlatformCssController
{
    public function __construct(
        private readonly PlatformSettingsRepository $settings,
    ) {
    }

    public function show(Request $request): Response
    {
        $css = trim((string) $this->settings->get('platform_custom_css', ''));
        $body = $css !== '' ? $css . "\n" : "/* No platform custom CSS has been configured. */\n";

        return new Response($body, 200, [
            'Content-Type' => 'text/css; charset=utf-8',
            'Cache-Control' => 'no-store, max-age=0',
        ]);
    }
}

// End of file.
