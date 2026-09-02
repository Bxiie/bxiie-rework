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
        private readonly ?SpamScoreService $spamScore = null,
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
        $existing = $this->signups->findByEmail($tenant, $email);
        $alreadyActive = $existing !== null
            && in_array((string) ($existing['consent_status'] ?? ''), ['pending', 'confirmed'], true);

        // Only score genuinely new addresses; an existing subscriber's original
        // score is preserved by EmailSignupRepository::upsert() regardless.
        $spamProbability = $existing === null
            ? $this->spamScore?->score($tenant, $email, $name, $source, $ipAddress)['probability']
            : null;

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
            spamProbability: $spamProbability,
        );

        if (!$alreadyActive) {
            $this->notifications->queueSignupNotification(
                tenant: $tenant,
                signupEmail: $email,
                signupName: $name,
            );
        }

        return $signupId;
    }
}

// End of file.
