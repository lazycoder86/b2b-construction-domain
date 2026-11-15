<?php

namespace App\Support\Billing;

use App\Contracts\Billing\SubscriptionProvider;
use App\Models\Organization;

/**
 * Used until Stripe is connected: no organization has a paid subscription.
 * Replace the binding in AppServiceProvider with a Cashier-backed provider.
 */
class NullSubscriptionProvider implements SubscriptionProvider
{
    public function current(Organization $organization): ?SubscriptionState
    {
        return null;
    }
}
