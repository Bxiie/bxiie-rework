<?php

declare(strict_types=1);

namespace App\Platform\Billing;

use DateTimeImmutable;
use DateTimeZone;

/**
 * Centralized subscription delinquency policy.
 *
 * This helper is intentionally policy-only. It does not query the database,
 * send mail, mutate tenant state, or restrict access. Enforcement can be wired
 * later once the grace and restriction thresholds have been observed in
 * production.
 */
final class BillingDelinquencyPolicy
{
    public const GRACE_DAYS = 7;
    public const RESTRICTION_DAYS = 14;
    public const FINAL_REVIEW_DAYS = 30;

    public const STATE_CURRENT = 'current';
    public const STATE_GRACE = 'grace';
    public const STATE_RESTRICT = 'restrict';
    public const STATE_FINAL_REVIEW = 'final_review';

    /**
     * @return list<string>
     */
    public static function states(): array
    {
        return [
            self::STATE_CURRENT,
            self::STATE_GRACE,
            self::STATE_RESTRICT,
            self::STATE_FINAL_REVIEW,
        ];
    }

    public static function stateFor(?string $billingStatus, ?string $actionRequiredAt, ?DateTimeImmutable $now = null): string
    {
        $billingStatus = strtolower(trim((string) $billingStatus));
        if (!in_array($billingStatus, ['past_due', 'unpaid'], true)) {
            return self::STATE_CURRENT;
        }

        $ageDays = self::ageDays($actionRequiredAt, $now);
        if ($ageDays < self::GRACE_DAYS) {
            return self::STATE_GRACE;
        }
        if ($ageDays < self::RESTRICTION_DAYS) {
            return self::STATE_RESTRICT;
        }

        return self::STATE_FINAL_REVIEW;
    }

    public static function actionForState(string $state): string
    {
        return match ($state) {
            self::STATE_GRACE => 'Email tenant owner and prompt payment-method update.',
            self::STATE_RESTRICT => 'Prepare to restrict paid-only features if payment is not recovered.',
            self::STATE_FINAL_REVIEW => 'Review for manual downgrade, cancellation, or account outreach.',
            default => 'No delinquency action required.',
        };
    }

    public static function labelForState(string $state): string
    {
        return match ($state) {
            self::STATE_GRACE => 'In grace period',
            self::STATE_RESTRICT => 'Restriction threshold reached',
            self::STATE_FINAL_REVIEW => 'Final review threshold reached',
            default => 'Current',
        };
    }

    public static function severityForState(string $state): string
    {
        return match ($state) {
            self::STATE_GRACE => 'WARN',
            self::STATE_RESTRICT, self::STATE_FINAL_REVIEW => 'CRIT',
            default => 'OK',
        };
    }

    public static function ageDays(?string $actionRequiredAt, ?DateTimeImmutable $now = null): int
    {
        $actionRequiredAt = trim((string) $actionRequiredAt);
        if ($actionRequiredAt === '') {
            return 0;
        }

        $now ??= new DateTimeImmutable('now', new DateTimeZone('UTC'));

        try {
            $then = new DateTimeImmutable($actionRequiredAt, new DateTimeZone('UTC'));
        } catch (\Throwable) {
            return 0;
        }

        $seconds = max(0, $now->getTimestamp() - $then->getTimestamp());

        return (int) floor($seconds / 86400);
    }

    public static function graceEndsAt(?string $actionRequiredAt): ?string
    {
        return self::thresholdDate($actionRequiredAt, self::GRACE_DAYS);
    }

    public static function restrictionBeginsAt(?string $actionRequiredAt): ?string
    {
        return self::thresholdDate($actionRequiredAt, self::RESTRICTION_DAYS);
    }

    public static function finalReviewAt(?string $actionRequiredAt): ?string
    {
        return self::thresholdDate($actionRequiredAt, self::FINAL_REVIEW_DAYS);
    }

    private static function thresholdDate(?string $actionRequiredAt, int $days): ?string
    {
        $actionRequiredAt = trim((string) $actionRequiredAt);
        if ($actionRequiredAt === '') {
            return null;
        }

        try {
            $then = new DateTimeImmutable($actionRequiredAt, new DateTimeZone('UTC'));
        } catch (\Throwable) {
            return null;
        }

        return $then->modify('+' . $days . ' days')->format('Y-m-d H:i:s');
    }
}

// End of file.
