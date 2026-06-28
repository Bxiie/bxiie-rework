<?php

declare(strict_types=1);

namespace App\Platform\Billing;

/**
 * Canonical billing status and pending-change vocabulary.
 *
 * Keep all billing controllers, webhook handlers, dashboards, and maintenance
 * scripts aligned with these constants instead of retyping string literals.
 */
final class BillingStatus
{
    public const ACTIVE = 'active';
    public const PAYMENT_PENDING = 'payment_pending';
    public const PAST_DUE = 'past_due';
    public const UNPAID = 'unpaid';
    public const CANCELED = 'canceled';
    public const CANCELLATION_PENDING = 'cancellation_pending';

    public const CHANGE_UPGRADE = 'upgrade';
    public const CHANGE_DOWNGRADE = 'downgrade';
    public const CHANGE_CANCEL = 'cancel';

    /**
     * @return list<string>
     */
    public static function billingStatuses(): array
    {
        return [
            self::ACTIVE,
            self::PAYMENT_PENDING,
            self::PAST_DUE,
            self::UNPAID,
            self::CANCELED,
            self::CANCELLATION_PENDING,
        ];
    }

    /**
     * @return list<string>
     */
    public static function pendingChangeTypes(): array
    {
        return [
            self::CHANGE_UPGRADE,
            self::CHANGE_DOWNGRADE,
            self::CHANGE_CANCEL,
        ];
    }

    public static function normalize(?string $status): string
    {
        $status = strtolower(trim((string) $status));

        return in_array($status, self::billingStatuses(), true) ? $status : self::ACTIVE;
    }

    public static function normalizePendingChange(?string $changeType): ?string
    {
        $changeType = strtolower(trim((string) $changeType));
        if ($changeType === '') {
            return null;
        }

        return in_array($changeType, self::pendingChangeTypes(), true) ? $changeType : null;
    }

    public static function requiresPaymentAction(?string $status): bool
    {
        return in_array(self::normalize($status), [self::PAYMENT_PENDING, self::PAST_DUE, self::UNPAID], true);
    }

    public static function isActiveAccess(?string $status): bool
    {
        return in_array(self::normalize($status), [self::ACTIVE, self::PAYMENT_PENDING, self::PAST_DUE, self::CANCELLATION_PENDING], true);
    }

    public static function isTerminal(?string $status): bool
    {
        return self::normalize($status) === self::CANCELED;
    }

    public static function severity(?string $status): string
    {
        return match (self::normalize($status)) {
            self::ACTIVE => 'OK',
            self::PAYMENT_PENDING, self::CANCELLATION_PENDING => 'WARN',
            self::PAST_DUE, self::UNPAID, self::CANCELED => 'CRIT',
            default => 'WARN',
        };
    }

    public static function label(?string $status): string
    {
        return match (self::normalize($status)) {
            self::ACTIVE => 'Active',
            self::PAYMENT_PENDING => 'Payment pending',
            self::PAST_DUE => 'Past due',
            self::UNPAID => 'Unpaid',
            self::CANCELED => 'Canceled',
            self::CANCELLATION_PENDING => 'Cancellation pending',
            default => 'Unknown',
        };
    }

    public static function pendingChangeLabel(?string $changeType): string
    {
        return match (self::normalizePendingChange($changeType)) {
            self::CHANGE_UPGRADE => 'Upgrade',
            self::CHANGE_DOWNGRADE => 'Downgrade',
            self::CHANGE_CANCEL => 'Cancellation',
            default => 'None',
        };
    }
}

// End of file.
