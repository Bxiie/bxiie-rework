<?php

declare(strict_types=1);

namespace App\Tenant\Signup;

use App\Platform\Tenancy\TenantContext;

/**
 * Coordinates email signup persistence and notification queueing.
 */
final class EmailSignupService
{
    public function __construct(
        private readonly EmailSignupRepository $signups,
        private readonly SignupNotificationService $notifications,
    ) {
    }

    public function receive(
        TenantContext $tenant,
        string $email,
        ?string $name = null,
        ?string $source = null,
        ?string $ipAddress = null,
        ?string $userAgent = null,
        ?string $country = null,
        ?string $region = null,
        ?string $city = null,
    ): int {
        $signupId = $this->signups->upsert(
            tenant: $tenant,
            email: $email,
            name: $name,
            source: $source,
            ipAddress: $ipAddress,
            userAgent: $userAgent,
            country: $country,
            region: $region,
            city: $city,
        );

        $this->notifications->queueSignupNotification(
            tenant: $tenant,
            signupEmail: $email,
            signupName: $name,
        );

        return $signupId;
    }
}

// End of file.
