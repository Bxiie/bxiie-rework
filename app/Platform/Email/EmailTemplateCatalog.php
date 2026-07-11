<?php

declare(strict_types=1);

namespace App\Platform\Email;

use App\Platform\Settings\PlatformSettingsRepository;
use PDO;

/** Central metadata and delivery policy for filesystem-backed email templates. */
final class EmailTemplateCatalog
{
    /** @return array<string,array{description:string,keys:list<string>}> */
    public static function definitions(): array
    {
        return [
            'auth/email-verification-request.md' => ['description' => 'Sent when a user must verify ownership of an email address.', 'keys' => ['auth.email_verification_request']],
            'auth/password-reset-request.md' => ['description' => 'Sent when a user requests a password-reset link.', 'keys' => ['auth.password_reset_request']],
            'auth/platform-admin-invite.md' => ['description' => 'Invites a user to administer the ArtsFolio platform.', 'keys' => ['platform_admin_invite']],
            'auth/tenant-admin-invite.md' => ['description' => 'Invites a user to administer a specific artist site.', 'keys' => ['tenant_admin_invite']],
            'billing/checkout-completed.txt' => ['description' => 'Confirms successful completion of subscription checkout.', 'keys' => ['billing.checkout_completed']],
            'billing/payment-failed.txt' => ['description' => 'Warns tenant owners that a subscription payment failed.', 'keys' => ['billing.payment_failed']],
            'billing/payment-recovered.txt' => ['description' => 'Confirms that a previously failed subscription payment recovered.', 'keys' => ['billing.payment_recovered']],
            'billing/plan-change-applied.txt' => ['description' => 'Confirms that a scheduled plan change or cancellation took effect.', 'keys' => ['billing.plan_change_applied']],
            'billing/plan-change-scheduled.txt' => ['description' => 'Confirms that a plan change or cancellation has been scheduled.', 'keys' => ['billing.plan_change_scheduled']],
            'billing/plan-upgraded.txt' => ['description' => 'Confirms an immediate subscription-plan upgrade.', 'keys' => ['billing.plan_upgraded']],
            'billing/platform-delinquency-report.txt' => ['description' => 'Platform-admin report summarizing delinquent or unhealthy subscriptions.', 'keys' => ['billing.platform_delinquency_report']],
            'billing/subscription-canceled.txt' => ['description' => 'Confirms that an ArtsFolio subscription was canceled.', 'keys' => ['billing.subscription_canceled']],
            'lifecycle/tenant_admin_cancelled_6h.txt' => ['description' => 'Sent six hours after cancellation to ask why the user left.', 'keys' => ['lifecycle.tenant_admin_cancelled_6h']],
            'lifecycle/tenant_admin_feature_deep_dive_1d.txt' => ['description' => 'One-day onboarding lesson covering important site-management features.', 'keys' => ['lifecycle.tenant_admin_feature_deep_dive_1d']],
            'lifecycle/tenant_admin_weekly_checkin.txt' => ['description' => 'Weekly education, inspiration, and satisfaction check-in for tenant admins.', 'keys' => ['lifecycle.tenant_admin_weekly_checkin']],
            'lifecycle/tenant_admin_welcome_6h.txt' => ['description' => 'Welcome and orientation sent six hours after tenant signup.', 'keys' => ['lifecycle.tenant_admin_welcome_6h']],
            'lifecycle/tenant_admin_winback_1m.txt' => ['description' => 'One-month cancellation win-back invitation.', 'keys' => ['lifecycle.tenant_admin_winback_1m']],
            'lifecycle/tenant_admin_winback_1w.txt' => ['description' => 'One-week cancellation win-back invitation.', 'keys' => ['lifecycle.tenant_admin_winback_1w']],
            'lifecycle/welcome.md' => ['description' => 'General welcome email queued for a newly created user.', 'keys' => ['lifecycle.welcome']],
            'platform/tenant-signup-invite.txt' => ['description' => 'Invitation containing a prospective tenant’s one-time signup code and complimentary-plan offer.', 'keys' => ['platform.tenant_signup_invite']],
            'sales/abandoned-cart-12h.md' => ['description' => 'Early abandoned-cart reminder sent approximately twelve hours after abandonment.', 'keys' => ['sales.abandoned_cart_12h']],
            'sales/abandoned-cart-1d.md' => ['description' => 'First abandoned-cart reminder sent approximately one day after abandonment.', 'keys' => ['sales.abandoned_cart_1d']],
            'sales/abandoned-cart-24h.md' => ['description' => 'Alternate twenty-four-hour abandoned-cart reminder.', 'keys' => ['sales.abandoned_cart_24h']],
            'sales/abandoned-cart-3d.md' => ['description' => 'Second abandoned-cart reminder sent approximately three days after abandonment.', 'keys' => ['sales.abandoned_cart_3d']],
            'sales/abandoned-cart-7d.md' => ['description' => 'Final abandoned-cart reminder sent approximately seven days after abandonment.', 'keys' => ['sales.abandoned_cart_7d']],
        ];
    }

    public static function description(string $path): string
    {
        return self::definitions()[$path]['description'] ?? 'Filesystem-backed email used by ArtsFolio. Its exact trigger is determined by the sending code.';
    }

    /** @return list<string> */
    public static function templateKeys(string $path): array
    {
        return self::definitions()[$path]['keys'] ?? [];
    }

    public static function pathForTemplateKey(?string $templateKey): ?string
    {
        $needle = strtolower(trim((string) $templateKey));
        if ($needle === '') return null;
        foreach (self::definitions() as $path => $definition) {
            foreach ($definition['keys'] as $key) if (strtolower($key) === $needle) return $path;
        }
        return null;
    }

    public static function settingKey(string $path): string
    {
        return 'email_template.active.' . hash('sha256', $path);
    }

    public static function isActive(PlatformSettingsRepository $settings, string $path): bool
    {
        return $settings->get(self::settingKey($path), '1') !== '0';
    }

    public static function isTemplateKeyActive(PDO $pdo, ?string $templateKey): bool
    {
        $path = self::pathForTemplateKey($templateKey);
        return $path === null || self::isActive(new PlatformSettingsRepository($pdo), $path);
    }
}

// End of file.
