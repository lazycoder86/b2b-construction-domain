<?php

namespace App\Support\Billing;

use Carbon\CarbonImmutable;

/**
 * What a SubscriptionProvider reports about an organization's paid subscription.
 * Only subscriptions that still grant access are reported; otherwise the provider returns null.
 */
final readonly class SubscriptionState
{
    public function __construct(
        public string $planKey,
        public SubscriptionStatus $status,
        public ?string $interval = null,
        public ?CarbonImmutable $endsAt = null,
    ) {}
}
