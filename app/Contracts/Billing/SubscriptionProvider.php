<?php

namespace App\Contracts\Billing;

use App\Models\Organization;
use App\Support\Billing\SubscriptionState;

/**
 * Source of truth for paid subscriptions (Stripe via Cashier).
 *
 * The entitlements layer only depends on this interface, so billing can be
 * swapped or disabled without touching feature checks.
 */
interface SubscriptionProvider
{
    /**
     * The subscription currently granting access, or null if there is none.
     */
    public function current(Organization $organization): ?SubscriptionState;
}
