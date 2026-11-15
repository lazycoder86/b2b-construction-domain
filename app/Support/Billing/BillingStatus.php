<?php

namespace App\Support\Billing;

enum BillingStatus: string
{
    case Free = 'free';
    case Trialing = 'trialing';
    case Active = 'active';
    case PastDue = 'past_due';
    case Cancelling = 'cancelling';
    // Plan set manually by sales/support (entitlement_overrides.plan).
    case Custom = 'custom';

    public static function fromSubscription(SubscriptionStatus $status): self
    {
        return match ($status) {
            SubscriptionStatus::Active => self::Active,
            SubscriptionStatus::PastDue => self::PastDue,
            SubscriptionStatus::Cancelling => self::Cancelling,
        };
    }
}
