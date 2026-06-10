<?php

declare(strict_types=1);

namespace App\Platform\Auth;

/**
 * Documents supported authentication models.
 */
final class AuthArchitecture
{
    public const UI_AUTH_MODELS = [
        'oauth_oidc',
        'local_email_password',
    ];

    public const API_AUTH_MODEL = 'oauth2_bearer_tokens';

    public const SUPPORTED_EXTERNAL_PROVIDERS = [
        'google',
        'facebook',
    ];

    public const SUPPORTED_LOCAL_PROVIDERS = [
        'local_email_password',
    ];
}

// End of file.
